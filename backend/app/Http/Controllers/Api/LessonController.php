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
use App\Services\Zoom\ZoomClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LessonController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $lessons = $this->filtered($request)
            ->with(['group', 'teacher', 'slot', 'zoomAccount'])
            ->orderByDesc('starts_at')
            ->paginate(min(100, max(5, (int) $request->integer('per_page', 20))))
            ->withQueryString();

        return LessonResource::collection($lessons);
    }

    /** Dars yaratish formasi uchun: faol guruhlar (band/bo'sh) va bugungi paralar. */
    public function options(Request $request): JsonResponse
    {
        $slotId = $request->query('slot_id');
        $slot = $slotId && $slotId !== 'now' ? LessonSlot::where('is_active', true)->find($slotId) : null;
        $now = now();

        try {
            [$from, $to] = CreateLesson::window($slot, $now);
        } catch (ValidationException) {
            [$from, $to] = [$slot->startOn($now), $slot->endOn($now)];
        }

        $busy = Lesson::query()->overlapping($from, $to)->get(['group_id', 'zoom_account_id', 'zoom_host_email', 'ends_at']);

        $groups = Group::query()->bookable()->orderBy('course')->orderBy('name')->get()->map(function (Group $group) use ($busy) {
            $conflicts = $busy->filter(fn (Lesson $lesson) => $lesson->group_id === $group->id
                || ($lesson->zoom_account_id === $group->zoom_account_id && $lesson->zoom_host_email === mb_strtolower($group->zoom_user_email)));

            return [
                'id' => $group->id,
                'name' => $group->name,
                'course' => $group->course,
                'busy' => $conflicts->isNotEmpty(),
                'busy_until' => $conflicts->max('ends_at')?->toIso8601String(),
            ];
        })->values();

        $slots = LessonSlot::where('is_active', true)->orderBy('sort')->orderBy('starts_at')->get()->map(fn (LessonSlot $item) => [
            'id' => $item->id,
            'name' => $item->name,
            'starts_at' => $item->starts_at,
            'ends_at' => $item->endsAtLabel(),
            'duration_minutes' => $item->duration_minutes,
            'available' => $item->endOn($now)->greaterThan($now->copy()->addMinutes(5)),
        ])->values();

        return response()->json(['data' => [
            'now' => $now->toIso8601String(),
            'default_duration' => (int) config('lessons.default_duration'),
            'groups' => $groups,
            'slots' => $slots,
        ]]);
    }

    public function store(Request $request, CreateLesson $action): JsonResponse
    {
        Gate::authorize('create', Lesson::class);

        $data = $request->validate([
            'group_id' => ['required', 'integer', Rule::exists('groups', 'id')->whereNull('deleted_at')],
            'slot_id' => ['nullable', 'integer', Rule::exists('lesson_slots', 'id')->where('is_active', true)],
            'topic' => ['nullable', 'string', 'max:200'],
        ], [
            'group_id.required' => __('Guruhni tanlang.'),
            'group_id.exists' => __('Tanlangan guruh topilmadi.'),
            'slot_id.exists' => __('Tanlangan dars vaqti topilmadi.'),
            'topic.max' => __('Mavzu 200 belgidan oshmasligi kerak.'),
        ]);

        $lesson = $action->handle(
            $request->user(),
            Group::with('zoomAccount')->findOrFail($data['group_id']),
            isset($data['slot_id']) ? LessonSlot::findOrFail($data['slot_id']) : null,
            $data['topic'] ?? null,
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

    /** Hisobot: filtrlangan darslar CSV (Excel'da ochiladi). */
    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        $query = $this->filtered($request)->with(['group', 'teacher', 'slot'])->orderBy('starts_at');
        $filename = __('darslar').'-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excel UTF-8 ni to'g'ri ochishi uchun BOM
            fputcsv($out, array_map(fn (string $title) => __($title), ['Sana', 'Boshlanish', 'Tugash', 'Guruh', 'Kurs', 'Oʻqituvchi', 'Mavzu', 'Holat', 'Davomiyligi (daqiqa)']), ';');
            $query->chunk(500, function ($lessons) use ($out) {
                foreach ($lessons as $lesson) {
                    fputcsv($out, [
                        $lesson->starts_at->format('d.m.Y'),
                        $lesson->starts_at->format('H:i'),
                        ($lesson->ended_at && $lesson->ended_at->lessThan($lesson->ends_at) ? $lesson->ended_at : $lesson->ends_at)->format('H:i'),
                        $lesson->group->name,
                        $lesson->group->course,
                        $lesson->teacher->name,
                        self::csvSafe($lesson->topic),
                        match ($lesson->effectiveStatus()) {
                            LessonStatus::Scheduled => __('Rejalashtirilgan'),
                            LessonStatus::Live => __('Efirda'),
                            LessonStatus::Finished => __('Yakunlangan'),
                            LessonStatus::Cancelled => __('Bekor qilingan'),
                        },
                        $lesson->status === LessonStatus::Cancelled ? 0 : $lesson->durationMinutes(),
                    ], ';');
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
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

    /** CSV injection'dan himoya: formulaga o'xshash qiymatlar oldiga apostrof qo'yiladi. */
    private static function csvSafe(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
