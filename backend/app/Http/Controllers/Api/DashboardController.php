<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\LessonResource;
use App\Http\Resources\LessonSlotResource;
use App\Models\Lesson;
use App\Models\LessonSlot;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $scope = fn () => Lesson::query()->visibleTo($user)->with(['group', 'teacher', 'slot', 'zoomAccount']);

        $live = $scope()->live()->orderBy('starts_at')->get();
        $today = $scope()
            ->whereBetween('starts_at', [now()->startOfDay(), now()->endOfDay()])
            ->where('status', '!=', 'cancelled')
            ->orderBy('starts_at')
            ->get();

        return response()->json(['data' => [
            'stats' => [
                'live_now' => $live->count(),
                'today_total' => $today->count(),
                'month_total' => Lesson::query()->visibleTo($user)
                    ->where('status', '!=', 'cancelled')
                    ->whereBetween('starts_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->count(),
                'pending_users' => $user->isAdmin()
                    ? User::query()->visibleTo($user)->where('status', UserStatus::Pending->value)->count()
                    : null,
            ],
            'live' => LessonResource::collection($live)->resolve($request),
            'today' => LessonResource::collection($today)->resolve($request),
            'slots' => LessonSlotResource::collection(LessonSlot::where('is_active', true)->orderBy('sort')->orderBy('starts_at')->get())->resolve($request),
        ]]);
    }
}
