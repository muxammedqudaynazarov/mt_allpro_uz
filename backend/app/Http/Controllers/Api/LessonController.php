<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateLesson;
use App\Actions\EndLesson;
use App\Enums\LessonStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\LessonResource;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\LessonSlot;
use App\Models\ZoomHost;
use App\Services\Zoom\ZoomClient;
use App\Support\Xlsx;
use App\Support\HostAvailability;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LessonController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $lessons = $this->filtered($request)
            ->with(['group', 'teacher', 'slot', 'zoomAccount'])
            ->withParticipantsCount()
            ->orderByDesc('starts_at')
            ->paginate(min(100, max(5, (int) $request->integer('per_page', 20))))
            ->withQueryString();

        return LessonResource::collection($lessons);
    }

    /**
     * Dars yaratish formasi uchun: tanlangan vaqtda guruhlar (band/bo'sh), juftliklar
     * va Zoom hostlar (band/bo'sh, tavsiya etilgani birinchi).
     */
    public function options(Request $request): JsonResponse
    {
        $data = $request->validate([
            'slot_id' => ['nullable', 'string'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'group_id' => ['nullable', 'integer'],
        ]);
        $slot = ($data['slot_id'] ?? 'now') !== 'now' ? LessonSlot::where('is_active', true)->find($data['slot_id']) : null;
        $now = now();
        $date = isset($data['date']) ? Carbon::parse($data['date'])->startOfDay() : null;
        $day = $date ?? $now->copy()->startOfDay();

        try {
            [$from, $to] = CreateLesson::window($slot, $now, $date);
        } catch (ValidationException) {
            [$from, $to] = $slot ? [$slot->startOn($day), $slot->endOn($day)] : [$now, $now->copy()->addMinutes((int) config('lessons.default_duration', 80))];
        }

        $groupLessons = Lesson::query()->overlapping($from, $to)->get(['group_id', 'ends_at'])->groupBy('group_id');
        $groups = Group::query()->where('is_active', true)->orderBy('course')->orderBy('name')->get()->map(fn (Group $group) => [
            'id' => $group->id,
            'name' => $group->name,
            'course' => $group->course,
            'busy' => $groupLessons->has($group->id),
            'busy_until' => $groupLessons->get($group->id)?->max('ends_at')?->toIso8601String(),
        ])->values();

        $selected = isset($data['group_id']) ? Group::find($data['group_id']) : null;
        $hosts = HostAvailability::options($from, $to, $selected)->map(fn (array $option) => HostAvailability::present($option))->values();

        $isToday = $day->isSameDay($now);
        $slots = LessonSlot::where('is_active', true)->orderBy('sort')->orderBy('starts_at')->get()->map(fn (LessonSlot $item) => [
            'id' => $item->id,
            'name' => $item->label(),
            'starts_at' => $item->starts_at,
            'ends_at' => $item->endsAtLabel(),
            'duration_minutes' => $item->duration_minutes,
            'available' => ! $isToday || $item->endOn($now)->greaterThan($now->copy()->addMinutes(5)),
        ])->values();

        return response()->json(['data' => [
            'now' => $now->toIso8601String(),
            'date' => $day->toDateString(),
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'default_duration' => (int) config('lessons.default_duration'),
            'groups' => $groups,
            'hosts' => $hosts,
            'slots' => $slots,
        ]]);
    }

    public function store(Request $request, CreateLesson $action): JsonResponse
    {
        Gate::authorize('create', Lesson::class);

        $data = $request->validate([
            'group_id' => ['required', 'integer', Rule::exists('groups', 'id')->whereNull('deleted_at')],
            'slot_id' => ['nullable', 'integer', Rule::exists('lesson_slots', 'id')->where('is_active', true)],
            'host_id' => ['nullable', 'integer', Rule::exists('zoom_hosts', 'id')],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'topic' => ['nullable', 'string', 'max:200'],
        ], [
            'group_id.required' => __('Guruhni tanlang.'),
            'group_id.exists' => __('Tanlangan guruh topilmadi.'),
            'slot_id.exists' => __('Tanlangan dars vaqti topilmadi.'),
            'host_id.exists' => __('Tanlangan Zoom host topilmadi.'),
            'topic.max' => __('Mavzu 200 belgidan oshmasligi kerak.'),
        ]);

        $date = isset($data['date']) ? Carbon::parse($data['date'])->startOfDay() : null;
        if ($date && ! $date->isToday() && ! $request->user()->canManageCalendar()) {
            abort(403, __('Sizga kalendarni boshqarish (boshqa kunlarga dars rejalashtirish) taqiqlangan.'));
        }

        $lesson = $action->handle(
            $request->user(),
            Group::findOrFail($data['group_id']),
            isset($data['slot_id']) ? LessonSlot::findOrFail($data['slot_id']) : null,
            $data['topic'] ?? null,
            isset($data['host_id']) ? ZoomHost::with('zoomAccount')->findOrFail($data['host_id']) : null,
            $date,
        );

        return (new LessonResource($lesson->load(['group', 'teacher', 'slot'])))->response()->setStatusCode(201);
    }

    /** Zoom'dan yangi host havolasini olib beradi (saqlangan ZAK 2 soatda eskiradi). */
    public function start(Request $request, Lesson $lesson, ZoomClient $zoom): JsonResponse
    {
        Gate::authorize('start', $lesson);

        $url = $lesson->zoomAccount && $lesson->zoom_meeting_id
            ? ($zoom->freshStartUrl($lesson->zoomAccount, $lesson->zoom_meeting_id) ?? $lesson->start_url)
            : $lesson->start_url;

        abort_unless($url, 410, __('Bu dars uchun host havolasi mavjud emas.'));

        if ($lesson->status === LessonStatus::Scheduled && $lesson->starts_at->isPast()) {
            $lesson->forceFill(['status' => LessonStatus::Live])->save();
        }

        return response()->json(['data' => ['url' => $url]]);
    }

    public function end(Request $request, Lesson $lesson, EndLesson $action): LessonResource
    {
        Gate::authorize('end', $lesson);

        return new LessonResource($action->handle($lesson->load(['group', 'zoomAccount']), $request->user())->load(['teacher', 'slot']));
    }

    /** Hisobot: filtrlangan darslar ro'yxati .xlsx. */
    public function export(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->canViewAll(), 403);

        $query = $this->filtered($request)->with(['group', 'teacher', 'slot'])->withParticipantsCount()->orderBy('starts_at');
        $header = array_map(fn (string $title) => __($title), [
            'Sana', 'Boshlanish', 'Tugash', 'Guruh', 'Kurs', 'Oʻqituvchi', 'Zoom host', 'Mavzu', 'Holat', 'Davomiyligi (daqiqa)',
            'Zoom’da boshlandi', 'Kechikish (daqiqa)', 'Haqiqiy davomiylik (daqiqa)', 'Qatnashgan talabalar',
        ]);

        return Xlsx::download(__('darslar').'-'.now()->format('Y-m-d-His').'.xlsx', function (Xlsx $xlsx) use ($query, $header) {
            $xlsx->sheet(__('Darslar'), [11, 10, 9, 16, 6, 30, 26, 30, 14, 12, 12, 12, 12, 12], freezeRow: 1)->header($header);
            $query->chunk(500, function ($lessons) use ($xlsx) {
                foreach ($lessons as $lesson) {
                    $xlsx->row([
                        $lesson->starts_at->format('d.m.Y'),
                        $lesson->starts_at->format('H:i'),
                        ($lesson->ended_at && $lesson->ended_at->lessThan($lesson->ends_at) ? $lesson->ended_at : $lesson->ends_at)->format('H:i'),
                        $lesson->group->name,
                        $lesson->group->course,
                        $lesson->teacher->name,
                        $lesson->zoom_host_email,
                        $lesson->topic,
                        match ($lesson->effectiveStatus()) {
                            LessonStatus::Scheduled => __('Rejalashtirilgan'),
                            LessonStatus::Live => __('Efirda'),
                            LessonStatus::Finished => __('Yakunlangan'),
                            LessonStatus::Cancelled => __('Bekor qilingan'),
                        },
                        $lesson->status === LessonStatus::Cancelled ? 0 : $lesson->durationMinutes(),
                        $lesson->zoom_started_at?->format('H:i') ?? ($lesson->zoom_state === Lesson::ZOOM_MISSED ? __('Oʻtkazilmagan') : null),
                        $lesson->startDelayMinutes(),
                        $lesson->actualMinutes(),
                        $lesson->attendance_synced_at ? (int) $lesson->participants_count : null,
                    ]);
                }
            });
        });
    }

    private function filtered(Request $request): Builder
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['live', 'scheduled', 'finished', 'cancelled'])],
            'group_id' => ['nullable', 'integer'],
            'teacher_id' => ['nullable', 'integer'],
            'mine' => ['nullable', 'boolean'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $user = $request->user();
        $query = Lesson::query()->visibleTo($user);

        match ($data['status'] ?? null) {
            'live' => $query->live(),
            'scheduled' => $query->upcoming(),
            'finished' => $query->finished(),
            'cancelled' => $query->where('status', LessonStatus::Cancelled->value),
            default => null,
        };

        if (! empty($data['group_id'])) {
            $query->where('group_id', $data['group_id']);
        }
        if (! empty($data['teacher_id'])) {
            $query->where('user_id', $data['teacher_id']);
        }
        if (! empty($data['mine'])) {
            $query->where('user_id', $user->id);
        }
        if (! empty($data['from'])) {
            $query->where('starts_at', '>=', Carbon::parse($data['from'])->startOfDay());
        }
        if (! empty($data['to'])) {
            $query->where('starts_at', '<=', Carbon::parse($data['to'])->endOfDay());
        }

        return $query;
    }
}
