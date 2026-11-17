<?php

namespace App\Actions;

use App\Models\Group;
use App\Models\LessonSeries;
use App\Models\LessonSlot;
use App\Models\User;
use App\Models\ZoomHost;
use App\Support\Audit;
use App\Support\LessonDays;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** Har haftalik darsni ro'yxatga oladi (Zoom uchrashuvi dars kuni yaratiladi). */
class CreateSeries
{
    public function handle(User $teacher, Group $group, LessonSlot $slot, int $weekday, ?ZoomHost $host, ?string $topic, Carbon $startsOn, ?Carbon $endsOn, User $actor): LessonSeries
    {
        if (LessonDays::isClosedWeekday($weekday)) {
            throw ValidationException::withMessages(['weekday' => LessonDays::closedMessage($weekday)]);
        }
        if (! $group->is_active) {
            throw ValidationException::withMessages(['group_id' => __('Bu guruh nofaol — unga dars yaratib boʻlmaydi.')]);
        }
        if ($startsOn->lt(today())) {
            throw ValidationException::withMessages(['starts_on' => __('Boshlanish sanasi bugundan oldin boʻlishi mumkin emas.')]);
        }
        if ($endsOn && $endsOn->lt($startsOn)) {
            throw ValidationException::withMessages(['ends_on' => __('Tugash sanasi boshlanishidan oldin boʻlishi mumkin emas.')]);
        }

        $same = LessonSeries::query()->active()->where('weekday', $weekday)->where('lesson_slot_id', $slot->id)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $startsOn))
            ->when($endsOn, fn ($q) => $q->whereDate('starts_on', '<=', $endsOn));
        if ((clone $same)->where('group_id', $group->id)->exists()) {
            throw ValidationException::withMessages(['group_id' => __('Bu guruhda shu kuni va juftlikda haftalik dars allaqachon bor.')]);
        }
        if ((clone $same)->where('user_id', $teacher->id)->exists()) {
            throw ValidationException::withMessages(['lesson_slot_id' => __('Oʻqituvchida shu kuni va juftlikda boshqa haftalik dars bor.')]);
        }

        $series = LessonSeries::create([
            'user_id' => $teacher->id,
            'group_id' => $group->id,
            'lesson_slot_id' => $slot->id,
            'zoom_host_id' => $host?->id,
            'weekday' => $weekday,
            'topic' => filled($topic) ? trim($topic) : null,
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => $endsOn?->toDateString(),
        ]);

        Audit::log('series.created', 'Haftalik dars qoʻshildi: :group, :day :time', $series, ['teacher_id' => $teacher->id], $actor, [
            'group' => $group->name,
            'day' => self::weekdayName($weekday),
            'time' => $slot->starts_at,
        ]);

        return $series;
    }

    public static function weekdayName(int $weekday): string
    {
        return __(['', 'dushanba', 'seshanba', 'chorshanba', 'payshanba', 'juma', 'shanba', 'yakshanba'][$weekday] ?? '');
    }
}
