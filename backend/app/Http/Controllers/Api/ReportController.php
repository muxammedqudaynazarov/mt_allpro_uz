<?php

namespace App\Http\Controllers\Api;

use App\Enums\LessonStatus;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $from = Carbon::parse($data['from'])->startOfDay();
        $to = Carbon::parse($data['to'])->endOfDay();
        abort_if($from->diffInDays($to) > 400, 422, 'Hisobot davri 400 kundan oshmasligi kerak.');

        $lessons = Lesson::query()
            ->visibleTo($request->user())
            ->where('status', '!=', LessonStatus::Cancelled->value)
            ->where('starts_at', '<=', now())
            ->whereBetween('starts_at', [$from, $to])
            ->with(['group', 'teacher'])
            ->get();

        $byTeacher = $lessons->groupBy('user_id')->map(fn ($items) => [
            'teacher' => ['id' => $items->first()->teacher->id, 'name' => $items->first()->teacher->name],
            'lessons' => $items->count(),
            'minutes' => $items->sum(fn (Lesson $lesson) => $lesson->durationMinutes()),
        ])->sortByDesc('lessons')->values();

        $byGroup = $lessons->groupBy('group_id')->map(fn ($items) => [
            'group' => ['id' => $items->first()->group->id, 'name' => $items->first()->group->name, 'course' => $items->first()->group->course],
            'lessons' => $items->count(),
            'minutes' => $items->sum(fn (Lesson $lesson) => $lesson->durationMinutes()),
        ])->sortByDesc('lessons')->values();

        return response()->json(['data' => [
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'totals' => [
                'lessons' => $lessons->count(),
                'minutes' => $lessons->sum(fn (Lesson $lesson) => $lesson->durationMinutes()),
                'teachers' => $byTeacher->count(),
                'groups' => $byGroup->count(),
            ],
            'by_teacher' => $byTeacher,
            'by_group' => $byGroup,
        ]]);
    }
}
