<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateLesson;
use App\Actions\CreateSeries;
use App\Actions\EndLesson;
use App\Enums\LessonStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\LessonResource;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\LessonSeries;
use App\Models\LessonSlot;
use App\Models\Semester;
use App\Models\ZoomHost;
use App\Services\Zoom\ZoomClient;
use App\Support\HostAvailability;
use App\Support\HostDisplayName;
use App\Support\LessonDays;
use App\Support\LessonGroupsInput;
use App\Support\Xlsx;
use App\Support\ZoomAutoEnd;
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
            ->with(['group', 'groups', 'teacher', 'slot', 'zoomAccount'])
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

        // Ma'ruzadagi barcha guruhlar ham band hisoblanadi.
        $groupLessons = Lesson::query()->overlapping($from, $to)
            ->join('lesson_group', 'lesson_group.lesson_id', '=', 'lessons.id')
            ->get(['lesson_group.group_id', 'lessons.ends_at'])
            ->groupBy('group_id');
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
        $closed = LessonDays::isClosed($day);
        $slots = LessonSlot::where('is_active', true)->orderBy('sort')->orderBy('starts_at')->get()->map(fn (LessonSlot $item) => [
            'id' => $item->id,
            'name' => $item->label(),
            'starts_at' => $item->starts_at,
            'ends_at' => $item->endsAtLabel(),
            'duration_minutes' => $item->duration_minutes,
            // Boshlangan juftlik tanlanmaydi (11:30 dan keyin 11:30–12:50 yopiq).
            'available' => ! $closed && (! $isToday || $item->startOn($now)->greaterThan($now)),
        ])->values();

        return response()->json(['data' => [
            'now' => $now->toIso8601String(),
            'date' => $day->toDateString(),
            'closed' => $closed,
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'default_duration' => (int) config('lessons.default_duration'),
            // Zoom avtomatik yakunlanadi (Umumiy sozlamalar): from–to shu qismni ko'rsatadi.
            'auto_end' => ZoomAutoEnd::enabled(),
            'groups' => $groups,
            'hosts' => $hosts,
            'slots' => $slots,
        ]]);
    }

    public function store(Request $request, CreateLesson $action, CreateSeries $createSeries): JsonResponse
    {
        Gate::authorize('create', Lesson::class);

        $data = $request->validate([
            // Faqat jadvaldagi juftlikka: oldindan rejalashtirilmagan ("hozir") dars yaratilmaydi.
            'slot_id' => ['required', 'integer', Rule::exists('lesson_slots', 'id')->where('is_active', true)],
            'host_id' => ['nullable', 'integer', Rule::exists('zoom_hosts', 'id')],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'topic' => ['nullable', 'string', 'max:200'],
            // Har hafta shu kuni, shu juftlikda takrorlash.
            'repeat_weekly' => ['nullable', 'boolean'],
            // Ma'ruza: bir uchrashuvda bir nechta guruh (group_ids).
            'is_lecture' => ['nullable', 'boolean'],
        ], [
            'slot_id.required' => __('Dars vaqtini (juftlikni) tanlang.'),
            'slot_id.exists' => __('Tanlangan dars vaqti topilmadi.'),
            'host_id.exists' => __('Tanlangan Zoom host topilmadi.'),
            'topic.max' => __('Mavzu 200 belgidan oshmasligi kerak.'),
        ]);

        $date = isset($data['date']) ? Carbon::parse($data['date'])->startOfDay() : null;
        if ($date && ! $date->isToday() && ! $request->user()->canManageCalendar()) {
            abort(403, __('Sizga kalendarni boshqarish (boshqa kunlarga dars rejalashtirish) taqiqlangan.'));
        }

        [$groups, $lecture] = LessonGroupsInput::from($request);
        $slot = LessonSlot::findOrFail($data['slot_id']);
        $host = isset($data['host_id']) ? ZoomHost::with('zoomAccount')->findOrFail($data['host_id']) : null;

        $series = null;
        if ($request->boolean('repeat_weekly')) {
            Gate::authorize('create', LessonSeries::class);
            $day = $date ?? today();
            $series = $createSeries->handle($request->user(), $groups, $slot, $day->isoWeekday(), $host, $data['topic'] ?? null, $day, null, $request->user(), $lecture);
        }

        try {
            $lesson = $action->handle($request->user(), $groups, $slot, $data['topic'] ?? null, $host, $date, $series?->id, lecture: $lecture);
        } catch (\Throwable $e) {
            // Birinchi dars yaratilmasa, jadval ham saqlanmaydi.
            $series?->delete();
            throw $e;
        }

        return (new LessonResource($lesson->load(['group', 'groups', 'teacher', 'slot'])))->response()->setStatusCode(201);
    }

    /** Zoom'dan yangi host havolasini olib beradi (saqlangan ZAK 2 soatda eskiradi). */
    public function start(Request $request, Lesson $lesson, ZoomClient $zoom, HostDisplayName $hostName): JsonResponse
    {
        Gate::authorize('start', $lesson);
        // Host uchrashuvga o'qituvchi nomi bilan kiradi ("QUDAYNAZAROV M.S.").
        $hostName->apply($lesson);

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

        return new LessonResource($action->handle($lesson->load(['group', 'groups', 'zoomAccount']), $request->user())->load(['teacher', 'slot']));
    }

    /** Hisobot: filtrlangan darslar ro'yxati .xlsx. */
    public function export(Request $request): BinaryFileResponse
    {
        abort_unless($request->user()->canViewAll(), 403);

        $query = $this->filtered($request)->with(['group', 'groups', 'teacher', 'slot'])->withParticipantsCount()->orderBy('starts_at');
        $header = array_map(fn (string $title) => __($title), [
            'Sana', 'Boshlanish', 'Tugash', 'Turi', 'Guruh', 'Kurs', 'Oʻqituvchi', 'Zoom host', 'Mavzu', 'Holat', 'Davomiyligi (daqiqa)',
            'Zoom’da boshlandi', 'Kechikish (daqiqa)', 'Haqiqiy davomiylik (daqiqa)', 'Qatnashgan talabalar',
        ]);

        return Xlsx::download(__('darslar').'-'.now()->format('Y-m-d-His').'.xlsx', function (Xlsx $xlsx) use ($query, $header) {
            $xlsx->sheet(__('Darslar'), [11, 10, 9, 10, 22, 6, 30, 26, 30, 14, 12, 12, 12, 12, 12], freezeRow: 1)->header($header);
            $query->chunk(500, function ($lessons) use ($xlsx) {
                foreach ($lessons as $lesson) {
                    $xlsx->row([
                        $lesson->starts_at->format('d.m.Y'),
                        $lesson->starts_at->format('H:i'),
                        ($lesson->ended_at && $lesson->ended_at->lessThan($lesson->ends_at) ? $lesson->ended_at : $lesson->ends_at)->format('H:i'),
                        $lesson->is_lecture ? __('Maʼruza') : __('Amaliy'),
                        $lesson->groupsLabel(),
                        $lesson->groupList()->pluck('course')->unique()->implode(', '),
                        $lesson->teacher->displayName(),
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
            // Arxiv: o'quv yili yoki semestr bo'yicha.
            'semester_id' => ['nullable', 'integer'],
            'academic_year_id' => ['nullable', 'integer'],
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
            $query->forGroup((int) $data['group_id']);
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
        if (! empty($data['semester_id'])) {
            $query->where('semester_id', $data['semester_id']);
        } elseif (! empty($data['academic_year_id'])) {
            $query->whereIn('semester_id', Semester::query()->where('academic_year_id', $data['academic_year_id'])->select('id'));
        }

        return $query;
    }
}
