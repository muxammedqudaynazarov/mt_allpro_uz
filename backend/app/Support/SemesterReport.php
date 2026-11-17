<?php

namespace App\Support;

use App\Enums\Assessment;
use App\Enums\LessonStatus;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * Semestr yakuni bo'yicha guruh hisoboti (faqat Zoom ma'lumotlari asosida):
 * o'tkazilgan darslar, umumiy Zoom vaqti, har bir talabaning jami qatnashgan vaqti,
 * foizlar, dars boshiga o'rtacha qatnashuv va o'zlashtirish taxmini.
 * Bitta dars o'tishi bilan hisoblanadi.
 */
final class SemesterReport
{
    /** @return array<string, mixed> */
    public static function build(Semester $semester, Group $group, ?array $criteria = null): array
    {
        $criteria ??= Assessment::criteria();
        $lessons = self::lessons($semester, $group);
        $held = $lessons->filter(fn (Lesson $lesson) => $lesson->zoom_state === Lesson::ZOOM_ENDED && $lesson->attendance_synced_at && ($lesson->actualMinutes() ?? 0) > 0)->values();
        $students = Student::query()->where('group_id', $group->id)->where('is_ignored', false)->orderBy('name')->get();

        $attendance = LessonAttendance::query()
            ->whereIn('lesson_id', $held->modelKeys())
            ->whereIn('student_id', $students->modelKeys())
            ->get()
            ->groupBy('student_id');

        $lessonSeconds = $held->mapWithKeys(fn (Lesson $lesson) => [$lesson->id => $lesson->actualMinutes() * 60]);
        $totalSeconds = (int) $lessonSeconds->sum();
        $presence = $criteria['presence_percent'] / 100;
        $isPresent = fn (LessonAttendance $row) => $lessonSeconds->has($row->lesson_id) && $row->seconds >= $lessonSeconds[$row->lesson_id] * $presence;

        $rows = $students->map(function (Student $student) use ($attendance, $held, $totalSeconds, $isPresent, $criteria) {
            $items = $attendance->get($student->id, collect());
            $present = $items->filter($isPresent);
            $seconds = (int) $items->sum('seconds');
            $percentTime = $totalSeconds > 0 ? min(100, (int) round($seconds * 100 / $totalSeconds)) : null;
            $percentLessons = $held->isNotEmpty() ? (int) round($present->count() * 100 / $held->count()) : null;
            $basis = $criteria['basis'] === Assessment::BASIS_LESSONS ? $percentLessons : $percentTime;
            $level = $basis === null ? null : Assessment::for($basis, $criteria['thresholds']);
            $byLesson = $items->keyBy('lesson_id');

            return [
                'id' => $student->id,
                'name' => $student->name,
                'attended' => $present->count(),
                'joined' => $items->count(),
                'minutes' => intdiv($seconds, 60),
                'percent_time' => $percentTime,
                'percent_lessons' => $percentLessons,
                'avg_late_minutes' => $items->isEmpty() ? null : (int) round($items->avg('late_seconds') / 60),
                'level' => $level?->value,
                'level_label' => $level?->label(),
                // Har bir dars bo'yicha qatnashgan daqiqalar (lessons tartibida).
                'per_lesson' => $held->map(fn (Lesson $lesson) => $byLesson->has($lesson->id) ? intdiv($byLesson[$lesson->id]->seconds, 60) : 0)->all(),
            ];
        })->values();

        $byLesson = $attendance->flatten(1)->groupBy('lesson_id');
        $perLessonPresent = $held->map(fn (Lesson $lesson) => $byLesson->get($lesson->id, collect())->filter($isPresent)->count());
        $count = $students->count();

        return [
            'semester' => self::semester($semester),
            'group' => ['id' => $group->id, 'name' => $group->name, 'course' => $group->course],
            'criteria' => $criteria,
            'totals' => [
                'held' => $held->count(),
                'missed' => $lessons->where('zoom_state', Lesson::ZOOM_MISSED)->count(),
                'pending' => $lessons->filter(fn (Lesson $lesson) => $lesson->zoom_state === Lesson::ZOOM_ENDED && ! $lesson->attendance_synced_at)->count(),
                'zoom_minutes' => intdiv($totalSeconds, 60),
                'avg_lesson_minutes' => $held->isEmpty() ? null : (int) round($totalSeconds / 60 / $held->count()),
                'students' => $count,
                'avg_students_per_lesson' => $held->isEmpty() ? null : round($perLessonPresent->avg(), 1),
                'avg_attendance_percent' => $held->isEmpty() || $count === 0 ? null : (int) round($perLessonPresent->avg() * 100 / $count),
            ],
            'levels' => collect(Assessment::cases())->mapWithKeys(fn (Assessment $level) => [$level->value => $rows->where('level', $level->value)->count()]),
            'lessons' => $held->map(fn (Lesson $lesson, int $index) => [
                'id' => $lesson->id,
                'starts_at' => $lesson->starts_at->toIso8601String(),
                'teacher' => $lesson->teacher->displayName(),
                'minutes' => $lesson->actualMinutes(),
                'present' => $perLessonPresent[$index],
            ])->values(),
            'students' => $rows,
        ];
    }

    /**
     * Semestrdagi barcha guruhlar (kamida bitta darsi bor) qisqacha.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public static function overview(Semester $semester): Collection
    {
        $criteria = Assessment::criteria();
        $groupIds = Lesson::query()
            ->whereBetween('starts_at', [$semester->starts_on->copy()->startOfDay(), $semester->ends_on->copy()->endOfDay()])
            ->where('status', '!=', LessonStatus::Cancelled->value)
            ->distinct()
            ->pluck('group_id');

        return Group::withTrashed()->whereIn('id', $groupIds)->orderBy('course')->orderBy('name')->get()
            ->map(function (Group $group) use ($semester, $criteria) {
                $report = self::build($semester, $group, $criteria);

                return ['group' => $report['group'], 'totals' => $report['totals'], 'levels' => $report['levels']];
            })
            ->values();
    }

    /** @return array<string, mixed> */
    public static function semester(Semester $semester): array
    {
        return [
            'id' => $semester->id,
            'name' => $semester->fullLabel(),
            'type' => $semester->type,
            'academic_year' => $semester->academicYear ? ['id' => $semester->academicYear->id, 'name' => $semester->academicYear->name, 'status' => $semester->academicYear->status] : null,
            'starts_on' => $semester->starts_on->toDateString(),
            'ends_on' => $semester->ends_on->toDateString(),
        ];
    }

    /** @return Collection<int, Lesson> */
    private static function lessons(Semester $semester, Group $group): Collection
    {
        return Lesson::query()
            ->where('group_id', $group->id)
            ->where('status', '!=', LessonStatus::Cancelled->value)
            ->whereBetween('starts_at', [$semester->starts_on->copy()->startOfDay(), $semester->ends_on->copy()->endOfDay()])
            ->with('teacher')
            ->orderBy('starts_at')
            ->get();
    }
}
