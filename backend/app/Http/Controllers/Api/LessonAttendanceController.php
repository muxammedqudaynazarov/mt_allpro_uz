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
use App\Support\Csv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function export(Request $request, Lesson $lesson): StreamedResponse
    {
        Gate::authorize('view', $lesson);
        $rows = $this->students($lesson);
        $header = array_map(fn (string $title) => __($title), ['Talaba', 'Zoom’dagi ismlar', 'Birinchi kirgan', 'Kechikish (daqiqa)', 'Qatnashgan (daqiqa)', 'Foiz', 'Kirishlar soni']);
        $filename = __('davomat').'-'.$lesson->starts_at->format('Y-m-d-Hi').'.csv';

        return Csv::download($filename, $header, function (\Closure $write) use ($rows) {
            foreach ($rows->where('ignored', false) as $row) {
                $write([
                    Csv::safe($row['name']),
                    Csv::safe(implode(', ', $row['names'])),
                    $row['first_joined_at']?->format('H:i'),
                    $row['late_minutes'],
                    $row['minutes'],
                    $row['percent'] !== null ? $row['percent'].'%' : '',
                    $row['sessions'],
                ]);
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
