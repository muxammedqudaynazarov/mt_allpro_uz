<?php

namespace App\Http\Controllers\Api;

use App\Enums\LessonStatus;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
