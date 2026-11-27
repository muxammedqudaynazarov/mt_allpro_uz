<?php

namespace App\Http\Controllers\Api;

use App\Actions\SyncLessonAttendance;
use App\Enums\Assessment;
use App\Exceptions\ExternalServiceException;
use App\Http\Controllers\Controller;
use App\Http\Resources\LessonResource;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Models\StudentAlias;
use App\Models\ZoomParticipation;
use App\Support\AttendanceCalculator;
use App\Support\Audit;
use App\Support\GroupRef;
use App\Support\PersonName;
use App\Support\StudentName;
use App\Support\StudentResolver;
use App\Support\Xlsx;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

    /**
     * Ma'ruza: guruhi aniqlanmagan qatnashchini guruhga biriktirish. Talaba shu guruhda topiladi
     * yoki yaratiladi va uning Zoom ID/ismi eslab qolinadi — keyingi darslarda avtomatik taniladi.
     */
    public function assign(Request $request, Lesson $lesson, AttendanceCalculator $calculator): JsonResponse
    {
        Gate::authorize('view', $lesson);
        abort_unless($lesson->is_lecture, 422, __('Faqat maʼruza qatnashchilari guruhga biriktiriladi.'));
        $data = $request->validate([
            'key' => ['required', 'string', 'max:255'],
            'group_id' => ['required', 'integer', Rule::in($lesson->groupIds())],
        ], [
            'group_id.required' => __('Guruhni tanlang.'),
            'group_id.in' => __('Bu guruh maʼruzada yoʻq.'),
        ]);

        $segments = ZoomParticipation::query()->where('lesson_id', $lesson->id)->where('is_host', false)->whereNull('student_id')->get()
            ->filter(fn (ZoomParticipation $segment) => self::participantKey($segment) === $data['key'])
            ->values();
        abort_if($segments->isEmpty(), 404, __('Qatnashchi topilmadi — u allaqachon biriktirilgan boʻlishi mumkin.'));

        $groupId = (int) $data['group_id'];
        $student = SyncLessonAttendance::withGroupLocks([$groupId], fn () => DB::transaction(function () use ($lesson, $segments, $groupId, $calculator) {
            $first = $segments->first();
            $student = (new StudentResolver)->resolve($groupId, $first->name, null, $first->zoom_user_id);
            // Qatnashchi boshqa ism bilan ham kirgan bo'lsa — ular ham eslab qolinadi.
            $now = now();
            StudentAlias::query()->insertOrIgnore($segments->pluck('name')->map(fn (string $name) => StudentName::key($name))->filter()->unique()->map(fn (string $key) => [
                'student_id' => $student->id, 'group_id' => $groupId, 'type' => StudentAlias::NAME, 'value' => $key, 'created_at' => $now, 'updated_at' => $now,
            ])->values()->all());
            ZoomParticipation::query()->whereIn('id', $segments->modelKeys())->update(['student_id' => $student->id]);
            $calculator->rebuild($lesson);

            return $student;
        }));

        $group = $lesson->groupList()->firstWhere('id', $groupId);
        Audit::log('attendance.assigned', 'Maʼruza qatnashchisi guruhga biriktirildi: :name → :group', $lesson, ['student_id' => $student->id], replace: ['name' => $student->displayName(), 'group' => $group?->name]);

        return response()->json(['data' => $this->payload($request, $lesson->refresh())]);
    }

    /** Guruhi aniqlanmagan qatnashchining kaliti: Zoom ID, bo'lmasa — ism. */
    public static function participantKey(ZoomParticipation $segment): string
    {
        return $segment->zoom_user_id ? 'z:'.$segment->zoom_user_id : 'n:'.StudentName::key($segment->name);
    }

    /** Dars hisoboti .xlsx: dars ma'lumotlari va har bir talabaning qatnashuvi. */
    public function export(Request $request, Lesson $lesson): BinaryFileResponse
    {
        Gate::authorize('view', $lesson);
        $lesson->load(['group', 'groups', 'teacher', 'slot']);
        $lecture = $lesson->is_lecture;
        $groupNames = $lesson->groupList()->pluck('name', 'id');
        $rows = $this->students($lesson)->where('ignored', false)
            ->sortBy(fn (array $row) => ($lecture ? $groupNames->keys()->search($row['group_id']).'|' : '').mb_strtolower($row['name']))
            ->values();
        $unassigned = $lecture ? $this->unassigned($lesson) : collect();
        $host = ZoomParticipation::query()->where('lesson_id', $lesson->id)->where('is_host', true)->min('joined_at');
        $filename = Str::slug($lesson->groupsLabel()).'-'.$lesson->starts_at->format('Y-m-d-Hi').'.xlsx';

        return Xlsx::download($filename, function (Xlsx $xlsx) use ($lesson, $rows, $host, $lecture, $groupNames, $unassigned) {
            $columns = ['Talaba', ...($lecture ? ['Guruh'] : []), 'Zoom’dagi ismlar', 'Birinchi kirgan', 'Oxirgi chiqqan', 'Kechikish (daqiqa)', 'Qatnashgan (daqiqa)', 'Foiz, %'];
            $xlsx->sheet(__('Davomat'), [34, ...($lecture ? [16] : []), 30, 14, 14, 14, 10, 10], freezeRow: $lecture ? 14 : 13)
                ->title(($lecture ? __('Maʼruza hisoboti') : __('Dars hisoboti')).': '.$lesson->groupsLabel())
                ->meta(__('Sana'), $lesson->starts_at->format('d.m.Y'))
                ->meta(__('Juftlik'), $lesson->slot?->label() ?? '—')
                ->meta(__('Oʻqituvchi'), $lesson->teacher->displayName())
                ->meta(__('Mavzu'), $lesson->topic)
                ->meta(__('Jadval boʻyicha'), $lesson->starts_at->format('H:i').'–'.$lesson->ends_at->format('H:i'))
                ->meta(__('Zoom’da boshlandi'), $lesson->zoom_started_at?->format('H:i') ?? ($lesson->zoom_state === Lesson::ZOOM_MISSED ? __('Oʻtkazilmagan') : '—'))
                ->meta(__('Host kechikishi (daqiqa)'), $lesson->startDelayMinutes())
                ->meta(__('Haqiqiy davomiylik (daqiqa)'), $lesson->actualMinutes())
                ->meta(__('Qatnashgan talabalar'), $rows->count())
                ->meta(__('Host birinchi kirgan'), $host ? Carbon::parse($host)->format('H:i') : '—');
            if ($lecture) {
                $xlsx->meta(__('Guruhi aniqlanmagan qatnashchilar'), $unassigned->count());
            }
            $xlsx->blank()->header(array_map(fn (string $title) => __($title), $columns));

            foreach ($rows as $row) {
                $xlsx->row([
                    $row['name'],
                    ...($lecture ? [$groupNames->get($row['group_id'], '—')] : []),
                    implode(', ', $row['names']),
                    $row['first_joined_at']?->format('H:i'),
                    $row['last_left_at']?->format('H:i'),
                    $row['late_minutes'],
                    $row['minutes'],
                    $row['percent'],
                ]);
            }
            foreach ($unassigned as $row) {
                $xlsx->row([
                    $row['name'],
                    __('Aniqlanmagan'),
                    implode(', ', $row['names']),
                    $row['first_joined_at']?->format('H:i'),
                    $row['last_left_at']?->format('H:i'),
                    null,
                    $row['minutes'],
                    $row['percent'],
                ]);
            }
            if ($rows->isEmpty() && $unassigned->isEmpty()) {
                $xlsx->note(__('Zoom hisoboti hali olinmagan yoki darsga talabalar kirmagan.'));
            }
        });
    }

    /** @return array<string, mixed> */
    private function payload(Request $request, Lesson $lesson): array
    {
        $lesson->load(['group', 'groups', 'teacher', 'slot'])->loadCount(['attendances as participants_count' => fn ($q) => $q
            ->whereHas('student', fn ($s) => $s->where('is_ignored', false))]);

        $segments = ZoomParticipation::query()->where('lesson_id', $lesson->id)->get();
        $host = $segments->where('is_host', true);
        $timeline = fn (iterable $items) => array_map(
            fn (array $pair) => [$pair[0]->toIso8601String(), $pair[1]->toIso8601String()],
            AttendanceCalculator::merge(collect($items)->map(fn (ZoomParticipation $s) => [$s->joined_at, $s->left_at])),
        );
        $byStudent = $segments->where('is_host', false)->groupBy('student_id');
        // "Talaba emas" deb belgilanganlarni faqat talabalarni boshqaradiganlar ko'radi (o'qituvchi — yo'q).
        $canManage = (bool) $request->user()?->can('manageStudents', $lesson->group);

        return [
            'lesson' => (new LessonResource($lesson))->resolve($request),
            'groups' => GroupRef::list($lesson->groupList()),
            'host' => $host->isEmpty() ? null : [
                'first_joined_at' => $host->min('joined_at')?->toIso8601String(),
                'names' => $host->pluck('name')->map(fn (string $name) => PersonName::upper($name))->unique()->values(),
                'segments' => $timeline($host),
            ],
            'students' => $this->students($lesson)->when(! $canManage, fn ($rows) => $rows->where('ignored', false))->map(fn (array $row) => [
                ...$row,
                'first_joined_at' => $row['first_joined_at']?->toIso8601String(),
                'last_left_at' => $row['last_left_at']?->toIso8601String(),
                'segments' => $timeline($byStudent->get($row['student_id'], [])),
            ])->values(),
            // Ma'ruza: guruhi aniqlanmagan qatnashchilar (o'qituvchi guruhga biriktiradi).
            'unassigned' => $lesson->is_lecture ? $this->unassigned($lesson)->map(fn (array $row) => [
                ...$row,
                'first_joined_at' => $row['first_joined_at']?->toIso8601String(),
                'last_left_at' => $row['last_left_at']?->toIso8601String(),
                'segments' => $timeline($row['segments']),
            ])->values() : [],
            'can_assign' => $lesson->is_lecture,
            // Shundan kam qatnashgan talaba darsda bo'lgan hisoblanmaydi (Umumiy sozlamalar).
            'presence_percent' => Assessment::criteria()['presence_percent'],
            'can_manage_students' => $canManage,
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
                'group_id' => $row->student->group_id,
                'name' => $row->student->displayName(),
                'ignored' => $row->student->is_ignored,
                'names' => $names->get($row->student_id, collect())->pluck('name')->map(fn (string $name) => PersonName::upper($name))->unique()->values()->all(),
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

    /** @return Collection<int, array<string, mixed>> */
    private function unassigned(Lesson $lesson): Collection
    {
        $lessonSeconds = ($lesson->actualMinutes() ?? 0) * 60;

        return ZoomParticipation::query()
            ->where('lesson_id', $lesson->id)
            ->where('is_host', false)
            ->whereNull('student_id')
            ->get()
            ->groupBy(fn (ZoomParticipation $segment) => self::participantKey($segment))
            ->map(function (Collection $segments, string $key) use ($lessonSeconds) {
                $seconds = AttendanceCalculator::uniqueSeconds($segments->map(fn (ZoomParticipation $s) => [$s->joined_at, $s->left_at]));
                $names = $segments->pluck('name')->map(fn (string $name) => PersonName::upper($name))->unique()->values();

                return [
                    'key' => $key,
                    'name' => $names->first(),
                    'names' => $names->all(),
                    'first_joined_at' => $segments->min('joined_at'),
                    'last_left_at' => $segments->max('left_at'),
                    'minutes' => (int) round($seconds / 60),
                    'percent' => $lessonSeconds > 0 ? min(100, (int) round($seconds * 100 / $lessonSeconds)) : null,
                    'sessions' => $segments->count(),
                    'segments' => $segments,
                ];
            })
            ->sortBy(fn (array $row) => mb_strtolower($row['name']))
            ->values();
    }
}
