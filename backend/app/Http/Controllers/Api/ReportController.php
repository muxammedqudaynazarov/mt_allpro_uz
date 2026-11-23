<?php

namespace App\Http\Controllers\Api;

use App\Enums\Assessment;
use App\Enums\LessonStatus;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Models\Semester;
use App\Support\GroupRef;
use App\Support\SemesterReport;
use App\Support\Xlsx;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    /** Shundan ko'p kechiksa — "kechikib boshlangan" hisoblanadi. */
    public const LATE_START_MINUTES = 5;

    public function summary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $from = Carbon::parse($data['from'])->startOfDay();
        $to = Carbon::parse($data['to'])->endOfDay();
        abort_if($from->diffInDays($to) > 400, 422, __('Hisobot davri 400 kundan oshmasligi kerak.'));

        $lessons = Lesson::query()
            ->visibleTo($request->user())
            ->where('status', '!=', LessonStatus::Cancelled->value)
            ->where('starts_at', '<=', now())
            ->whereBetween('starts_at', [$from, $to])
            ->with(['group', 'groups', 'teacher'])
            ->withParticipantsCount()
            ->get();

        $byTeacher = $lessons->groupBy('user_id')->map(fn ($items) => [
            'teacher' => ['id' => $items->first()->teacher->id, 'name' => $items->first()->teacher->displayName()],
            'lessons' => $items->count(),
            'lectures' => $items->where('is_lecture', true)->count(),
            'minutes' => $items->sum(fn (Lesson $lesson) => $lesson->durationMinutes()),
            ...self::zoomStats($items),
        ])->sortByDesc('lessons')->values();

        // Ma'ruza har bir guruhida hisoblanadi; qatnashchilar — faqat shu guruh talabalari.
        $lectureIds = $lessons->where('is_lecture', true)->modelKeys();
        $lectureParticipants = $lectureIds ? LessonAttendance::query()
            ->join('students', 'students.id', '=', 'lesson_attendances.student_id')
            ->whereIn('lesson_attendances.lesson_id', $lectureIds)
            ->where('students.is_ignored', false)
            ->groupBy('lesson_attendances.lesson_id', 'students.group_id')
            ->get(['lesson_attendances.lesson_id', 'students.group_id', DB::raw('count(*) as total')])
            ->mapWithKeys(fn ($row) => [$row->lesson_id.'|'.$row->group_id => (int) $row->total]) : collect();

        $byGroup = $lessons
            ->flatMap(fn (Lesson $lesson) => $lesson->groupList()->map(fn (Group $group) => ['group' => $group, 'lesson' => $lesson]))
            ->groupBy(fn (array $pair) => $pair['group']->id)
            ->map(function ($pairs) use ($lectureParticipants) {
                $group = $pairs->first()['group'];
                $items = $pairs->pluck('lesson');

                return [
                    'group' => GroupRef::make($group),
                    'lessons' => $items->count(),
                    'lectures' => $items->where('is_lecture', true)->count(),
                    'minutes' => $items->sum(fn (Lesson $lesson) => $lesson->durationMinutes()),
                    ...self::zoomStats($items, fn (Lesson $lesson) => $lesson->is_lecture ? (int) ($lectureParticipants[$lesson->id.'|'.$group->id] ?? 0) : (int) $lesson->participants_count),
                ];
            })->sortByDesc('lessons')->values();

        return response()->json(['data' => [
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'totals' => [
                'lessons' => $lessons->count(),
                'lectures' => $lessons->where('is_lecture', true)->count(),
                'minutes' => $lessons->sum(fn (Lesson $lesson) => $lesson->durationMinutes()),
                'teachers' => $byTeacher->count(),
                'groups' => $byGroup->count(),
                ...self::zoomStats($lessons),
            ],
            'by_teacher' => $byTeacher,
            'by_group' => $byGroup,
        ]]);
    }

    /**
     * Semestr yakuni: guruh berilsa — talabalar bo'yicha batafsil, aks holda guruhlar ro'yxati.
     * Semestr ko'rsatilmasa — joriy semestr.
     */
    public function semester(Request $request): JsonResponse
    {
        [$semester, $group] = $this->semesterScope($request);
        if (! $semester) {
            return response()->json(['data' => ['semester' => null]]);
        }

        return response()->json(['data' => $group
            ? SemesterReport::build($semester, $group)
            : ['semester' => SemesterReport::semester($semester), 'criteria' => Assessment::criteria(), 'groups' => SemesterReport::overview($semester)],
        ]);
    }

    public function semesterExport(Request $request): BinaryFileResponse
    {
        [$semester, $group] = $this->semesterScope($request);
        abort_unless($semester, 404, __('Semestr topilmadi.'));

        $name = Str::slug(($group?->name ?? __('semestr')).' '.$semester->fullLabel()).'.xlsx';

        return Xlsx::download($name, function (Xlsx $xlsx) use ($semester, $group) {
            if ($group) {
                $report = SemesterReport::build($semester, $group);
                $this->groupSheet($xlsx, $report, __('Yakuniy'));
                $this->matrixSheet($xlsx, $report);

                return;
            }

            $overview = SemesterReport::overview($semester);
            $xlsx->sheet(__('Guruhlar'), [30, 7, 12, 12, 14, 14, 16, 9, 9, 11, 10, 9, 9], freezeRow: 4)
                ->title(__('Semestr yakuni').': '.$semester->fullLabel())
                ->note($semester->starts_on->format('d.m.Y').' – '.$semester->ends_on->format('d.m.Y'))
                ->blank()
                ->header([
                    __('Guruh'), __('Kurs'), __('Oʻtkazilgan darslar'), __('Shundan maʼruza'), __('Zoom vaqti (soat)'), __('Talabalar'), __('Oʻrtacha qatnashuv, %'),
                    ...array_map(fn (Assessment $level) => $level->label(), array_reverse(Assessment::cases())),
                ]);
            foreach ($overview as $row) {
                $xlsx->row([
                    $row['group']['name'], $row['group']['course'], $row['totals']['held'], $row['totals']['held_lectures'], round($row['totals']['zoom_minutes'] / 60, 1),
                    $row['totals']['students'], $row['totals']['avg_attendance_percent'],
                    ...array_map(fn (Assessment $level) => $row['levels'][$level->value], array_reverse(Assessment::cases())),
                ]);
            }

            $used = [];
            foreach ($overview as $row) {
                $sheet = mb_substr($row['group']['name'], 0, 28);
                $sheet = isset($used[$sheet]) ? $sheet.' '.(++$used[$sheet]) : $sheet;
                $used[$sheet] ??= 1;
                $this->groupSheet($xlsx, SemesterReport::build($semester, Group::withTrashed()->findOrFail($row['group']['id'])), $sheet);
            }
        });
    }

    /** @return array{0: ?Semester, 1: ?Group} */
    private function semesterScope(Request $request): array
    {
        $data = $request->validate([
            'semester_id' => ['nullable', 'integer', 'exists:semesters,id'],
            'group_id' => ['nullable', 'integer'],
        ]);
        $semester = isset($data['semester_id']) ? Semester::find($data['semester_id']) : Semester::current();
        $group = isset($data['group_id']) ? Group::withTrashed()->findOrFail($data['group_id']) : null;

        return [$semester, $group];
    }

    private function groupSheet(Xlsx $xlsx, array $report, string $name): void
    {
        $t = $report['totals'];
        $c = $report['criteria'];
        $basis = $c['basis'] === Assessment::BASIS_LESSONS ? __('qatnashgan darslar foizi') : __('qatnashgan vaqt foizi');
        $split = $t['held_lectures'] > 0;
        $xlsx->sheet($name, [5, 34, 14, ...($split ? [12, 10, 12, 10] : []), 12, 12, 12, 12, 14, 18], freezeRow: $split ? 15 : 14)
            ->title($report['group']['name'].', '.$report['semester']['name'])
            ->meta(__('Davr'), Carbon::parse($report['semester']['starts_on'])->format('d.m.Y').' – '.Carbon::parse($report['semester']['ends_on'])->format('d.m.Y'))
            ->meta(__('Oʻtkazilgan darslar'), $t['held']);
        if ($split) {
            $xlsx->meta(__('Shundan maʼruza / amaliy'), $t['held_lectures'].' / '.$t['held_practice']);
        }
        $xlsx->meta(__('Oʻtkazilmagan darslar'), $t['missed'])
            ->meta(__('Zoom’dagi umumiy vaqt (soat)'), round($t['zoom_minutes'] / 60, 1))
            ->meta(__('Darsning oʻrtacha davomiyligi (daqiqa)'), $t['avg_lesson_minutes'])
            ->meta(__('Talabalar'), $t['students'])
            ->meta(__('Dars boshiga oʻrtacha qatnashgan talabalar'), $t['avg_students_per_lesson'])
            ->meta(__('Oʻrtacha qatnashuv, %'), $t['avg_attendance_percent'])
            ->note(__('Oʻzlashtirish taxmini faqat Zoom’dagi qatnashuv asosida (:basis). Talaba darsda kamida :presence% boʻlsa — qatnashgan hisoblanadi.', ['basis' => $basis, 'presence' => $c['presence_percent']]))
            ->note(__('Chegaralar: Sust :sust%, Qoniqarli :qoniqarli%, Oʻrtacha :ortacha%, Yaxshi :yaxshi%, Aʼlo :alo%.', $c['thresholds']))
            ->blank()
            ->header([
                '№', __('Talaba'), __('Qatnashgan darslar'),
                ...($split ? [__('Maʼruza: qatnashgan'), __('Maʼruza, %'), __('Amaliy: qatnashgan'), __('Amaliy, %')] : []),
                __('Jami vaqt (soat)'), __('Vaqt foizi, %'), __('Darslar foizi, %'),
                __('Oʻrtacha kechikish (daqiqa)'), __('Kirgan darslar'), __('Oʻzlashtirish taxmini'),
            ]);
        foreach ($report['students'] as $index => $row) {
            $xlsx->row([
                $index + 1, $row['name'], $row['attended'].' / '.$t['held'],
                ...($split ? [
                    $row['lectures']['attended'].' / '.$t['held_lectures'], $row['lectures']['percent'],
                    $t['held_practice'] > 0 ? $row['practice']['attended'].' / '.$t['held_practice'] : null, $row['practice']['percent'],
                ] : []),
                round($row['minutes'] / 60, 1), $row['percent_time'], $row['percent_lessons'],
                $row['avg_late_minutes'], $row['joined'], $row['level_label'],
            ]);
        }
        if (! $report['students']->count()) {
            $xlsx->note(__('Guruhda hali Zoom hisobotidan aniqlangan talabalar yoʻq.'));
        }
    }

    /** Talaba × dars: har bir darsda qatnashgan daqiqalar. */
    private function matrixSheet(Xlsx $xlsx, array $report): void
    {
        $lessons = $report['lessons'];
        $lectures = $lessons->contains('is_lecture', true);
        $xlsx->sheet(__('Darslar boʻyicha'), [34, ...array_fill(0, max(1, $lessons->count()), 9)], freezeRow: $lectures ? 4 : 3)
            ->title(__('Har bir darsda qatnashgan vaqt (daqiqa)'));
        if ($lectures) {
            $xlsx->note(__('(M) — maʼruza (bir nechta guruh bilan birga).'));
        }
        $xlsx->blank()
            ->header([__('Talaba'), ...$lessons->map(fn (array $lesson) => Carbon::parse($lesson['starts_at'])->format('d.m H:i').($lesson['is_lecture'] ? ' '.__('(M)') : ''))->all()]);
        $xlsx->row([__('Dars davomiyligi'), ...$lessons->pluck('minutes')->all()], bold: true);
        foreach ($report['students'] as $row) {
            $xlsx->row([$row['name'], ...$row['per_lesson']]);
        }
    }

    /**
     * Zoom kuzatuvi bo'yicha ko'rsatkichlar: o'tkazilgan/o'tkazilmagan darslar,
     * hostning o'rtacha kechikishi, haqiqiy davomiylik va o'rtacha qatnashchilar.
     *
     * @param  Collection<int, Lesson>  $lessons
     * @param  (\Closure(Lesson): int)|null  $participants  darsdagi qatnashchilar (ma'ruzada — guruh bo'yicha)
     * @return array<string, int|null>
     */
    private static function zoomStats(Collection $lessons, ?\Closure $participants = null): array
    {
        $held = $lessons->where('zoom_state', Lesson::ZOOM_ENDED);
        $delays = $held->map(fn (Lesson $lesson) => $lesson->startDelayMinutes())->filter(fn ($value) => $value !== null);
        $synced = $held->filter(fn (Lesson $lesson) => $lesson->attendance_synced_at !== null);

        return [
            'held' => $held->count(),
            'missed' => $lessons->where('zoom_state', Lesson::ZOOM_MISSED)->count(),
            'late_starts' => $delays->filter(fn (int $delay) => $delay > self::LATE_START_MINUTES)->count(),
            'avg_start_delay' => $delays->isEmpty() ? null : (int) round($delays->avg()),
            'actual_minutes' => (int) $held->sum(fn (Lesson $lesson) => $lesson->actualMinutes() ?? 0),
            'avg_participants' => $synced->isEmpty() ? null : (int) round($participants ? $synced->avg($participants) : $synced->avg('participants_count')),
        ];
    }
}
