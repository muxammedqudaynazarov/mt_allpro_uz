<?php

namespace App\Http\Controllers\Api;

use App\Actions\SyncLessonAttendance;
use App\Exceptions\ExternalServiceException;
use App\Http\Controllers\Controller;
use App\Http\Resources\LessonResource;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Models\ZoomParticipation;
use App\Support\AttendanceCalculator;
use App\Support\Xlsx;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Bitta darsning Zoom'dagi haqiqiy vaqtlari va talabalar davomati. */
class LessonAttendanceController extends Controller
{
    public function show(Request $request, Lesson $lesson): JsonResponse
    {
        Gate::authorize('view', $lesson);

        return response()->json(['data' => $this->payload($request, $lesson)]);
    }

    /** Zoom hisobotini hozir qayta olish (masalan, avtomatik urinishlar tugagan bo'lsa). */
    public function sync(Request $request, Lesson $lesson, SyncLessonAttendance $sync): JsonResponse
    {
        Gate::authorize('view', $lesson);
        abort_if(! $lesson->zoom_meeting_id || ($lesson->isOpen() && $lesson->zoom_state !== Lesson::ZOOM_ENDED), 422, __('Davomat dars tugagandan keyin olinadi.'));

        try {
            $ready = $sync->handle($lesson);
        } catch (ExternalServiceException $e) {
            $lesson->forceFill(['attendance_error' => mb_substr($e->getMessage(), 0, 255)])->save();
            throw $e;
        }
        abort_unless($ready, 409, __('Zoom hisoboti hali tayyor emas. Bir necha daqiqadan soʻng qayta urinib koʻring.'));

        return response()->json(['data' => $this->payload($request, $lesson->refresh())]);
    }

    /** Dars hisoboti .xlsx: dars ma'lumotlari va har bir talabaning qatnashuvi. */
    public function export(Request $request, Lesson $lesson): BinaryFileResponse
    {
        Gate::authorize('view', $lesson);
        $lesson->load(['group', 'teacher', 'slot']);
        $rows = $this->students($lesson)->where('ignored', false)->values();
        $host = ZoomParticipation::query()->where('lesson_id', $lesson->id)->where('is_host', true)->min('joined_at');
        $filename = Str::slug($lesson->group->name).'-'.$lesson->starts_at->format('Y-m-d-Hi').'.xlsx';

        return Xlsx::download($filename, function (Xlsx $xlsx) use ($lesson, $rows, $host) {
            $xlsx->sheet(__('Davomat'), [34, 30, 14, 14, 14, 10, 10], freezeRow: 13)
                ->title(__('Dars hisoboti').': '.$lesson->group->name)
                ->meta(__('Sana'), $lesson->starts_at->format('d.m.Y'))
                ->meta(__('Juftlik'), $lesson->slot?->label() ?? '—')
                ->meta(__('Oʻqituvchi'), $lesson->teacher->displayName())
                ->meta(__('Mavzu'), $lesson->topic)
                ->meta(__('Jadval boʻyicha'), $lesson->starts_at->format('H:i').'–'.$lesson->ends_at->format('H:i'))
                ->meta(__('Zoom’da boshlandi'), $lesson->zoom_started_at?->format('H:i') ?? ($lesson->zoom_state === Lesson::ZOOM_MISSED ? __('Oʻtkazilmagan') : '—'))
                ->meta(__('Host kechikishi (daqiqa)'), $lesson->startDelayMinutes())
                ->meta(__('Haqiqiy davomiylik (daqiqa)'), $lesson->actualMinutes())
                ->meta(__('Qatnashgan talabalar'), $rows->count())
                ->meta(__('Host birinchi kirgan'), $host ? Carbon::parse($host)->format('H:i') : '—')
                ->blank()
                ->header(array_map(fn (string $title) => __($title), ['Talaba', 'Zoom’dagi ismlar', 'Birinchi kirgan', 'Oxirgi chiqqan', 'Kechikish (daqiqa)', 'Qatnashgan (daqiqa)', 'Foiz, %']));

            foreach ($rows as $row) {
                $xlsx->row([
                    $row['name'],
                    implode(', ', $row['names']),
                    $row['first_joined_at']?->format('H:i'),
                    $row['last_left_at']?->format('H:i'),
                    $row['late_minutes'],
                    $row['minutes'],
                    $row['percent'],
                ]);
            }
            if ($rows->isEmpty()) {
                $xlsx->note(__('Zoom hisoboti hali olinmagan yoki darsga talabalar kirmagan.'));
            }
        });
    }

    /** @return array<string, mixed> */
    private function payload(Request $request, Lesson $lesson): array
    {
        $lesson->load(['group', 'teacher', 'slot'])->loadCount(['attendances as participants_count' => fn ($q) => $q
            ->whereHas('student', fn ($s) => $s->where('is_ignored', false))]);

        $segments = ZoomParticipation::query()->where('lesson_id', $lesson->id)->get();
        $host = $segments->where('is_host', true);
        $timeline = fn (iterable $items) => array_map(
            fn (array $pair) => [$pair[0]->toIso8601String(), $pair[1]->toIso8601String()],
            AttendanceCalculator::merge(collect($items)->map(fn (ZoomParticipation $s) => [$s->joined_at, $s->left_at])),
        );
        $byStudent = $segments->where('is_host', false)->groupBy('student_id');

        return [
            'lesson' => (new LessonResource($lesson))->resolve($request),
            'host' => $host->isEmpty() ? null : [
                'first_joined_at' => $host->min('joined_at')?->toIso8601String(),
                'names' => $host->pluck('name')->unique()->values(),
                'segments' => $timeline($host),
            ],
            'students' => $this->students($lesson)->map(fn (array $row) => [
                ...$row,
                'first_joined_at' => $row['first_joined_at']?->toIso8601String(),
                'last_left_at' => $row['last_left_at']?->toIso8601String(),
                'segments' => $timeline($byStudent->get($row['student_id'], [])),
            ])->values(),
            'can_manage_students' => (bool) $request->user()?->can('manageStudents', $lesson->group),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function students(Lesson $lesson): Collection
    {
        $lessonSeconds = ($lesson->actualMinutes() ?? 0) * 60;
        $names = ZoomParticipation::query()
            ->where('lesson_id', $lesson->id)
            ->whereNotNull('student_id')
            ->select(['student_id', 'name'])
            ->distinct()
            ->get()
            ->groupBy('student_id');

        return LessonAttendance::query()
            ->where('lesson_id', $lesson->id)
            ->with('student')
            ->get()
            ->map(fn (LessonAttendance $row) => [
                'student_id' => $row->student_id,
                'name' => $row->student->name,
                'ignored' => $row->student->is_ignored,
                'names' => $names->get($row->student_id, collect())->pluck('name')->unique()->values()->all(),
                'first_joined_at' => $row->first_joined_at,
                'last_left_at' => $row->last_left_at,
                'late_minutes' => (int) floor($row->late_seconds / 60),
                'minutes' => (int) round($row->seconds / 60),
                'percent' => $lessonSeconds > 0 ? min(100, (int) round($row->seconds * 100 / $lessonSeconds)) : null,
                'sessions' => $row->sessions,
            ])
            ->sortBy(fn (array $row) => mb_strtolower($row['name']))
            ->values();
    }
}
