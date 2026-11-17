<?php

namespace App\Support;

use App\Models\Lesson;
use App\Models\LessonAttendance;
use App\Models\ZoomParticipation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Darsning xom kirish-chiqish yozuvlaridan (zoom_participations) har bir talabaning
 * yakuniy qatnashuvini hisoblaydi.
 */
class AttendanceCalculator
{
    /**
     * Kirish-chiqishlarni birlashtiradi: ustma-ust tushgan vaqt (masalan, telefon va
     * kompyuterdan bir vaqtda) bitta oraliq bo'ladi.
     *
     * @param  iterable<array{0: Carbon, 1: Carbon}>  $intervals
     * @return list<array{0: Carbon, 1: Carbon}>
     */
    public static function merge(iterable $intervals): array
    {
        $list = collect($intervals)
            ->map(fn (array $pair) => [$pair[0], $pair[1]->greaterThan($pair[0]) ? $pair[1] : $pair[0]])
            ->sortBy(fn (array $pair) => $pair[0]->getTimestamp())
            ->values();

        $merged = [];
        foreach ($list as [$from, $to]) {
            $last = array_key_last($merged);
            if ($last !== null && $from->lessThanOrEqualTo($merged[$last][1])) {
                $merged[$last][1] = $to->greaterThan($merged[$last][1]) ? $to : $merged[$last][1];
            } else {
                $merged[] = [$from, $to];
            }
        }

        return $merged;
    }

    /** @param  iterable<array{0: Carbon, 1: Carbon}>  $intervals */
    public static function uniqueSeconds(iterable $intervals): int
    {
        return (int) array_sum(array_map(fn (array $pair) => $pair[1]->getTimestamp() - $pair[0]->getTimestamp(), self::merge($intervals)));
    }

    public function rebuild(Lesson $lesson): void
    {
        $reference = $lesson->zoom_started_at;

        $rows = ZoomParticipation::query()
            ->where('lesson_id', $lesson->id)
            ->where('is_host', false)
            ->whereNotNull('student_id')
            ->get()
            ->groupBy('student_id')
            ->map(function (Collection $segments, int $studentId) use ($lesson, $reference) {
                $first = $segments->min('joined_at');
                $late = $reference ? max(0, $reference->diffInSeconds($first, false)) : 0;

                return [
                    'lesson_id' => $lesson->id,
                    'student_id' => $studentId,
                    'first_joined_at' => $first,
                    'last_left_at' => $segments->max('left_at'),
                    'seconds' => self::uniqueSeconds($segments->map(fn (ZoomParticipation $s) => [$s->joined_at, $s->left_at])),
                    'late_seconds' => (int) round($late),
                    'sessions' => $segments->count(),
                ];
            })
            ->values();

        LessonAttendance::query()->where('lesson_id', $lesson->id)->delete();
        foreach ($rows->chunk(200) as $chunk) {
            LessonAttendance::query()->insert($chunk->all());
        }
    }
}
