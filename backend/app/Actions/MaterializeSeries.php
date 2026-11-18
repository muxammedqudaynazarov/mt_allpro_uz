<?php

namespace App\Actions;

use App\Enums\UserStatus;
use App\Exceptions\ExternalServiceException;
use App\Models\Lesson;
use App\Models\LessonSeries;
use App\Support\LessonDays;
use App\Support\Notifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * Takroriy darsning bugungi uchrashuvini yaratadi (scheduler har daqiqada chaqiradi):
 * dars kuni, boshlanishidan LessonSeries::LEAD_MINUTES oldin. Bo'sh host bo'lmasa,
 * dars tugashiga 10 daqiqa qolguncha har daqiqada qayta uriniladi; o'qituvchiga
 * xato haqida bir kunda bir marta xabar beriladi.
 */
class MaterializeSeries
{
    private const STOP_BEFORE_END_MINUTES = 10;

    public function __construct(private readonly CreateLesson $create, private readonly Notifier $notifier) {}

    /** @return array{created: int, failed: int} */
    public function run(?Carbon $now = null): array
    {
        $now ??= now();
        $today = $now->copy()->startOfDay();
        $created = $failed = 0;
        if (LessonDays::isClosed($today)) {
            return ['created' => 0, 'failed' => 0];
        }

        $series = LessonSeries::query()
            ->active()
            ->where('weekday', $now->isoWeekday())
            ->whereDate('starts_on', '<=', $today)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today))
            ->whereDoesntHave('skips', fn ($q) => $q->whereDate('date', $today))
            ->with(['teacher', 'group', 'slot', 'host.zoomAccount'])
            ->get();

        foreach ($series as $item) {
            if (! $item->slot || ! $item->slot->is_active) {
                continue;
            }
            $start = $item->slot->startOn($today);
            $end = $item->slot->endOn($today);
            if ($now->lt($start->copy()->subMinutes(LessonSeries::LEAD_MINUTES)) || $now->gte($end->copy()->subMinutes(self::STOP_BEFORE_END_MINUTES))) {
                continue;
            }
            // Bekor qilingan dars ham hisobga olinadi: o'qituvchi bekor qilgan darsni tizim qayta yaratmaydi.
            if (Lesson::query()->where('lesson_series_id', $item->id)->whereDate('starts_at', $today)->exists()) {
                continue;
            }

            // Bir jadval uchun bir vaqtda bitta urinish (scheduler ustma-ust ishlasa ham).
            $lock = Cache::lock('series-materialize:'.$item->id.':'.$today->toDateString(), 120);
            if (! $lock->get()) {
                continue;
            }
            try {
                $this->materialize($item, $today, $start, $end);
                $created++;
            } catch (ValidationException|ExternalServiceException $e) {
                $failed++;
                $this->failed($item, $today, $e instanceof ValidationException ? collect($e->errors())->flatten()->first() : $e->getMessage());
            } finally {
                $lock->release();
            }
        }

        return ['created' => $created, 'failed' => $failed];
    }

    private function materialize(LessonSeries $series, Carbon $today, Carbon $start, Carbon $end): void
    {
        if ($series->teacher->status !== UserStatus::Active) {
            throw ValidationException::withMessages(['teacher' => __('Oʻqituvchi hisobi faol emas.')]);
        }

        $preferred = $series->host;
        $host = $preferred && $preferred->is_active && $preferred->zoomAccount?->is_active && ! CreateLesson::hostConflict($preferred, $start, $end)
            ? $preferred
            : null;

        $this->create->handle($series->teacher, $series->group, $series->slot, $series->topic, $host, $today, $series->id, allowStartedSlot: true);

        if ($series->last_error) {
            $series->forceFill(['last_error' => null, 'last_error_on' => null])->save();
        }
    }

    private function failed(LessonSeries $series, Carbon $today, string $reason): void
    {
        $firstToday = ! $series->last_error_on || ! $series->last_error_on->isSameDay($today);
        $series->forceFill(['last_error' => mb_substr($reason, 0, 255), 'last_error_on' => $today])->save();
        if ($firstToday) {
            $this->notifier->seriesFailed($series, $today, $reason);
        }
    }
}
