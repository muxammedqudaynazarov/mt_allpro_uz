<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LessonResource;
use App\Models\Lesson;
use App\Models\LessonSeries;
use App\Models\LessonSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Kalendar: oraliqdagi darslar va hali yaratilmagan haftalik darslar (rejadagi).
 * O'qituvchi — o'zinikini, O'quv bo'limi va Administrator — hammasini ko'radi.
 */
class CalendarController extends Controller
{
    private const MAX_DAYS = 42;

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'teacher_id' => ['nullable', 'integer'],
            'group_id' => ['nullable', 'integer'],
        ]);
        $user = $request->user();
        $from = Carbon::parse($data['from'])->startOfDay();
        $to = Carbon::parse($data['to'])->endOfDay();
        if ($from->diffInDays($to) > self::MAX_DAYS) {
            $to = $from->copy()->addDays(self::MAX_DAYS)->endOfDay();
        }
        $teacherId = $data['teacher_id'] ?? null;
        $groupId = $data['group_id'] ?? null;

        $lessons = Lesson::query()
            ->visibleTo($user)
            ->whereBetween('starts_at', [$from, $to])
            ->when($teacherId, fn ($q) => $q->where('user_id', $teacherId))
            ->when($groupId, fn ($q) => $q->where('group_id', $groupId))
            ->with(['group', 'teacher', 'slot'])
            ->withParticipantsCount()
            ->orderBy('starts_at')
            ->get();

        $made = $lessons->whereNotNull('lesson_series_id')->map(fn (Lesson $lesson) => $lesson->lesson_series_id.'|'.$lesson->starts_at->toDateString())->flip();
        $now = now();
        $planned = [];
        $series = LessonSeries::query()
            ->visibleTo($user)
            ->active()
            ->when($teacherId, fn ($q) => $q->where('user_id', $teacherId))
            ->when($groupId, fn ($q) => $q->where('group_id', $groupId))
            ->with(['group', 'teacher', 'slot', 'host', 'skips'])
            ->get();

        foreach ($series as $item) {
            $skipped = $item->skips->map(fn ($skip) => $skip->date->toDateString())->flip();
            foreach ($item->datesBetween($from->copy()->max($now->copy()->startOfDay()), $to) as $date) {
                $key = $item->id.'|'.$date->toDateString();
                if ($made->has($key)) {
                    continue;
                }
                $starts = $item->slot->startOn($date);
                $ends = $item->slot->endOn($date);
                if ($ends->lte($now)) {
                    continue;
                }
                $planned[] = [
                    'series_id' => $item->id,
                    'date' => $date->toDateString(),
                    'starts_at' => $starts->toIso8601String(),
                    'ends_at' => $ends->toIso8601String(),
                    'group' => ['id' => $item->group->id, 'name' => $item->group->name, 'course' => $item->group->course],
                    'teacher' => ['id' => $item->teacher->id, 'name' => $item->teacher->displayName()],
                    'slot' => ['id' => $item->slot->id, 'name' => $item->slot->label()],
                    'topic' => $item->topic,
                    'skipped' => $skipped->has($date->toDateString()),
                    'error' => $date->isToday() && $item->last_error_on?->isToday() ? $item->last_error : null,
                    'can_cancel' => $user->can('cancel', $item),
                ];
            }
        }

        $slots = LessonSlot::query()->where('is_active', true)->orderBy('sort')->orderBy('starts_at')->get()->map(fn (LessonSlot $slot) => [
            'id' => $slot->id,
            'name' => $slot->label(),
            'starts_at' => $slot->starts_at,
            'ends_at' => $slot->endsAtLabel(),
        ]);

        return response()->json(['data' => [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'lead_minutes' => LessonSeries::LEAD_MINUTES,
            'can_manage' => $user->canManageCalendar(),
            'slots' => $slots,
            'lessons' => LessonResource::collection($lessons)->resolve($request),
            'planned' => collect($planned)->sortBy('starts_at')->values(),
        ]]);
    }
}
