<?php

namespace App\Http\Controllers\Api;

use App\Enums\LessonStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\LessonResource;
use App\Models\Lesson;
use App\Models\ZoomAccount;
use App\Services\Zoom\LiveMonitor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Darslar monitoringi (O'quv bo'limi va Administrator): hozir efirdagi, bugun
 * kutilayotgan va bugun yakunlangan darslar; har bir efirdagi darsning qatnashchilari.
 */
class MonitorController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $now = now();
        $with = ['group', 'teacher', 'slot', 'zoomAccount'];
        // Hozir darsdagi qatnashchilar (hostsiz).
        $presentCount = ['liveParticipants as present_count' => fn (Builder $q) => $q->whereNull('left_at')->where('is_host', false)];

        // Zoom'da efirda bo'lsa, jadvaldagi vaqt tugagan bo'lsa ham (o'qituvchi uzaytirgan) — efirda.
        // Kuzatuv oynasidan (12 soat) eski "started" holat — tugash hodisasi yo'qolgan, efir emas.
        $live = Lesson::query()
            ->with($with)
            ->withCount($presentCount)
            ->where(fn (Builder $q) => $q->live()->orWhere(fn (Builder $q) => $q
                ->where('zoom_state', Lesson::ZOOM_STARTED)
                ->where('starts_at', '>=', $now->copy()->subHours(12))))
            ->where('status', '!=', LessonStatus::Cancelled->value)
            ->orderBy('starts_at')
            ->get();

        $upcoming = Lesson::query()->with($with)->upcoming()->whereDate('starts_at', $now->toDateString())->orderBy('starts_at')->get();

        $finished = Lesson::query()
            ->with($with)
            ->withParticipantsCount()
            ->finished()
            ->whereNotIn('id', $live->modelKeys())
            ->whereDate('starts_at', $now->toDateString())
            ->orderByDesc('starts_at')
            ->get();

        return response()->json(['data' => [
            'checked_at' => $now->toIso8601String(),
            'webhooks' => ZoomAccount::query()->where('is_active', true)->whereNotNull('webhook_received_at')->exists(),
            'live' => LessonResource::collection($live)->resolve($request),
            'upcoming' => LessonResource::collection($upcoming)->resolve($request),
            'finished' => LessonResource::collection($finished)->resolve($request),
        ]]);
    }

    /** Darsdagi qatnashchilar (frontend har 30 soniyada so'raydi). Dars o'qituvchisi ham ko'ra oladi. */
    public function participants(Lesson $lesson, LiveMonitor $monitor): JsonResponse
    {
        Gate::authorize('view', $lesson);

        return response()->json(['data' => $monitor->snapshot($lesson->loadMissing('zoomAccount'))]);
    }
}
