<?php

namespace App\Http\Controllers\Api;

use App\Enums\Assessment;
use App\Enums\LessonStatus;
use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\Semester;
use App\Support\SemesterReport;
use App\Support\Xlsx;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
            ->with(['group', 'teacher'])
            ->withParticipantsCount()
            ->get();

        $byTeacher = $lessons->groupBy('user_id')->map(fn ($items) => [
            'teacher' => ['id' => $items->first()->teacher->id, 'name' => $items->first()->teacher->name],
            'lessons' => $items->count(),
            'minutes' => $items->sum(fn (Lesson $lesson) => $lesson->durationMinutes()),
            ...self::zoomStats($items),
        ])->sortByDesc('lessons')->values();

        $byGroup = $lessons->groupBy('group_id')->map(fn ($items) => [
            'group' => ['id' => $items->first()->group->id, 'name' => $items->first()->group->name, 'course' => $items->first()->group->course],
            'lessons' => $items->count(),
            'minutes' => $items->sum(fn (Lesson $lesson) => $lesson->durationMinutes()),
            ...self::zoomStats($items),
        ])->sortByDesc('lessons')->values();

        return response()->json(['data' => [
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'totals' => [
                'lessons' => $lessons->count(),
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

        $name = Str::slug(($group?->name ?? __('semestr')).' '.$semester->name).'.xlsx';

        return Xlsx::download($name, function (Xlsx $xlsx) use ($semester, $group) {
            if ($group) {
                $report = SemesterReport::build($semester, $group);
                $this->groupSheet($xlsx, $report, __('Yakuniy'));
                $this->matrixSheet($xlsx, $report);

                return;
            }

            $overview = SemesterReport::overview($semester);
            $xlsx->sheet(__('Guruhlar'), [30, 7, 12, 14, 14, 16, 9, 9, 11, 10, 9, 9], freezeRow: 4)
                ->title(__('Semestr yakuni').': '.$semester->name)
                ->note($semester->starts_on->format('d.m.Y').' – '.$semester->ends_on->format('d.m.Y'))
                ->blank()
                ->header([
                    __('Guruh'), __('Kurs'), __('Oʻtkazilgan darslar'), __('Zoom vaqti (soat)'), __('Talabalar'), __('Oʻrtacha qatnashuv, %'),
                    ...array_map(fn (Assessment $level) => $level->label(), array_reverse(Assessment::cases())),
                ]);
            foreach ($overview as $row) {
                $xlsx->row([
                    $row['group']['name'], $row['group']['course'], $row['totals']['held'], round($row['totals']['zoom_minutes'] / 60, 1),
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
        $xlsx->sheet($name, [5, 34, 14, 12, 12, 12, 12, 14, 18], freezeRow: 14)
            ->title($report['group']['name'].', '.$report['semester']['name'])
            ->meta(__('Davr'), Carbon::parse($report['semester']['starts_on'])->format('d.m.Y').' – '.Carbon::parse($report['semester']['ends_on'])->format('d.m.Y'))
            ->meta(__('Oʻtkazilgan darslar'), $t['held'])
            ->meta(__('Oʻtkazilmagan darslar'), $t['missed'])
            ->meta(__('Zoom’dagi umumiy vaqt (soat)'), round($t['zoom_minutes'] / 60, 1))
            ->meta(__('Darsning oʻrtacha davomiyligi (daqiqa)'), $t['avg_lesson_minutes'])
            ->meta(__('Talabalar'), $t['students'])
            ->meta(__('Dars boshiga oʻrtacha qatnashgan talabalar'), $t['avg_students_per_lesson'])
            ->meta(__('Oʻrtacha qatnashuv, %'), $t['avg_attendance_percent'])
            ->note(__('Oʻzlashtirish taxmini faqat Zoom’dagi qatnashuv asosida (:basis). Talaba darsda kamida :presence% boʻlsa — qatnashgan hisoblanadi.', ['basis' => $basis, 'presence' => $c['presence_percent']]))
            ->note(__('Chegaralar: Sust :sust%, Qoniqarli :qoniqarli%, Oʻrtacha :ortacha%, Yaxshi :yaxshi%, Aʼlo :alo%.', $c['thresholds']))
            ->blank()
            ->header([
                '№', __('Talaba'), __('Qatnashgan darslar'), __('Jami vaqt (soat)'), __('Vaqt foizi, %'), __('Darslar foizi, %'),
                __('Oʻrtacha kechikish (daqiqa)'), __('Kirgan darslar'), __('Oʻzlashtirish taxmini'),
            ]);
        foreach ($report['students'] as $index => $row) {
            $xlsx->row([
                $index + 1, $row['name'], $row['attended'].' / '.$t['held'], round($row['minutes'] / 60, 1), $row['percent_time'], $row['percent_lessons'],
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
        $xlsx->sheet(__('Darslar boʻyicha'), [34, ...array_fill(0, max(1, $lessons->count()), 9)], freezeRow: 3)
            ->title(__('Har bir darsda qatnashgan vaqt (daqiqa)'))
            ->blank()
            ->header([__('Talaba'), ...$lessons->map(fn (array $lesson) => Carbon::parse($lesson['starts_at'])->format('d.m H:i'))->all()]);
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
     * @return array<string, int|null>
     */
    private static function zoomStats(Collection $lessons): array
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
            'avg_participants' => $synced->isEmpty() ? null : (int) round($synced->avg('participants_count')),
        ];
    }
}
