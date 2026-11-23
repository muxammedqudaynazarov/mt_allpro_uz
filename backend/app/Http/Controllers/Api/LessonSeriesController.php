<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateSeries;
use App\Actions\EndLesson;
use App\Http\Controllers\Controller;
use App\Http\Resources\LessonSeriesResource;
use App\Models\Lesson;
use App\Models\LessonSeries;
use App\Models\LessonSeriesSkip;
use App\Models\LessonSlot;
use App\Models\User;
use App\Models\ZoomHost;
use App\Support\Audit;
use App\Support\LessonGroupsInput;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Har haftalik takroriy darslar. */
class LessonSeriesController extends Controller
{
    private const WITH = ['group', 'groups', 'teacher', 'slot', 'host', 'skips'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate(['all' => ['nullable', 'boolean'], 'teacher_id' => ['nullable', 'integer'], 'group_id' => ['nullable', 'integer']]);

        $series = LessonSeries::query()
            ->visibleTo($request->user())
            ->when(! ($data['all'] ?? false), fn ($q) => $q->active())
            ->when($data['teacher_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($data['group_id'] ?? null, fn ($q, $id) => $q->forGroup((int) $id))
            ->with(self::WITH)
            ->orderBy('weekday')
            ->get()
            ->sortBy(fn (LessonSeries $item) => sprintf('%d-%s', $item->weekday, $item->slot->starts_at))
            ->values();

        return LessonSeriesResource::collection($series);
    }

    public function store(Request $request, CreateSeries $action): LessonSeriesResource
    {
        Gate::authorize('create', LessonSeries::class);
        $data = $request->validate([
            'lesson_slot_id' => ['required', 'integer', Rule::exists('lesson_slots', 'id')->where('is_active', true)],
            'weekday' => ['required', 'integer', 'between:1,7'],
            'zoom_host_id' => ['nullable', 'integer', Rule::exists('zoom_hosts', 'id')->where('is_active', true)],
            'topic' => ['nullable', 'string', 'max:200'],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d'],
            'teacher_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'is_lecture' => ['nullable', 'boolean'],
        ], [
            'lesson_slot_id.required' => __('Juftlikni tanlang.'),
            'weekday.required' => __('Hafta kunini tanlang.'),
        ]);

        [$groups, $lecture] = LessonGroupsInput::from($request);
        $actor = $request->user();
        // Administrator boshqa o'qituvchi nomidan ham qo'sha oladi.
        $teacher = isset($data['teacher_id']) && $actor->isSuperAdmin() ? User::findOrFail($data['teacher_id']) : $actor;

        $series = $action->handle(
            $teacher,
            $groups,
            LessonSlot::findOrFail($data['lesson_slot_id']),
            (int) $data['weekday'],
            isset($data['zoom_host_id']) ? ZoomHost::find($data['zoom_host_id']) : null,
            $data['topic'] ?? null,
            isset($data['starts_on']) ? Carbon::parse($data['starts_on']) : today(),
            isset($data['ends_on']) ? Carbon::parse($data['ends_on']) : null,
            $actor,
            $lecture,
        );

        return new LessonSeriesResource($series->load(self::WITH));
    }

    /** Mavzu, afzal host va tugash sanasini o'zgartirish. */
    public function update(Request $request, LessonSeries $series): LessonSeriesResource
    {
        Gate::authorize('update', $series);
        $data = $request->validate([
            'zoom_host_id' => ['nullable', 'integer', Rule::exists('zoom_hosts', 'id')],
            'topic' => ['nullable', 'string', 'max:200'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:'.$series->starts_on->toDateString()],
        ]);
        $series->fill($data)->save();
        Audit::log('series.updated', 'Haftalik dars oʻzgartirildi: :group', $series, $series->getChanges(), replace: ['group' => $series->groupsLabel()]);

        return new LessonSeriesResource($series->load(self::WITH));
    }

    /** Butun jadvalni to'xtatish (o'qituvchi yoki Administrator). Yaratilgan darslar tarixda qoladi. */
    public function cancel(Request $request, LessonSeries $series): LessonSeriesResource
    {
        Gate::authorize('cancel', $series);
        if ($series->is_active) {
            $series->forceFill(['is_active' => false, 'cancelled_at' => now(), 'cancelled_by_id' => $request->user()->id])->save();
            Audit::log('series.cancelled', 'Haftalik dars bekor qilindi: :group', $series, replace: ['group' => $series->groupsLabel()]);
        }

        return new LessonSeriesResource($series->load(self::WITH));
    }

    public function destroy(LessonSeries $series): Response
    {
        Gate::authorize('delete', $series);
        Lesson::query()->where('lesson_series_id', $series->id)->update(['lesson_series_id' => null]);
        $series->delete();
        Audit::log('series.deleted', 'Haftalik dars oʻchirildi: :group', $series, replace: ['group' => $series->groupsLabel()]);

        return response()->noContent();
    }

    /**
     * Bitta sanani bekor qilish. Shu kungi uchrashuv allaqachon yaratilgan bo'lsa —
     * dars ham bekor qilinadi (boshlanmagan bo'lsa).
     */
    public function skip(Request $request, LessonSeries $series, EndLesson $end): LessonSeriesResource
    {
        Gate::authorize('cancel', $series);
        $date = $this->date($request, $series);

        $lesson = Lesson::query()->where('lesson_series_id', $series->id)->whereDate('starts_at', $date)->with(['group', 'zoomAccount'])->first();
        if ($lesson && $lesson->status->isOpen()) {
            if ($lesson->zoom_state === Lesson::ZOOM_STARTED || $lesson->starts_at->isPast()) {
                throw ValidationException::withMessages(['date' => __('Dars allaqachon boshlangan — uni darslar sahifasidan yakunlang.')]);
            }
            $end->handle($lesson, $request->user());
        }

        LessonSeriesSkip::firstOrCreate(['lesson_series_id' => $series->id, 'date' => $date->toDateString()], ['created_by_id' => $request->user()->id]);
        Audit::log('series.skipped', 'Haftalik dars bir kunga bekor qilindi: :group, :date', $series, replace: ['group' => $series->groupsLabel(), 'date' => $date->format('d.m.Y')]);

        return new LessonSeriesResource($series->load(self::WITH));
    }

    /** Bekor qilingan sanani qaytarish (uchrashuv hali yaratilmagan bo'lsa). */
    public function unskip(Request $request, LessonSeries $series): LessonSeriesResource
    {
        Gate::authorize('cancel', $series);
        $date = $this->date($request, $series);
        if (Lesson::query()->where('lesson_series_id', $series->id)->whereDate('starts_at', $date)->exists()) {
            throw ValidationException::withMessages(['date' => __('Bu kungi dars bekor qilingan. Kerak boʻlsa, darsni qoʻlda yarating.')]);
        }
        $series->skips()->whereDate('date', $date)->delete();

        return new LessonSeriesResource($series->load(self::WITH));
    }

    private function date(Request $request, LessonSeries $series): Carbon
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
        $date = Carbon::parse($data['date'])->startOfDay();
        if ($date->lt(today()) || ! $series->occursOn($date)) {
            throw ValidationException::withMessages(['date' => __('Bu sanada haftalik dars yoʻq.')]);
        }

        return $date;
    }
}
