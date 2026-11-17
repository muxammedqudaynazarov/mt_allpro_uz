<?php

namespace App\Support;

use App\Actions\CreateSeries;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/** Dam olish kunlari (config/lessons.php closed_weekdays): bu kunlarga dars yaratilmaydi va rejalashtirilmaydi. */
final class LessonDays
{
    /** @return list<int> ISO hafta kunlari: 1 — dushanba ... 7 — yakshanba */
    public static function closed(): array
    {
        return array_values(array_map('intval', (array) config('lessons.closed_weekdays', [7])));
    }

    public static function isClosedWeekday(int $weekday): bool
    {
        return in_array($weekday, self::closed(), true);
    }

    public static function isClosed(Carbon $day): bool
    {
        return self::isClosedWeekday($day->isoWeekday());
    }

    public static function closedMessage(int $weekday): string
    {
        return __('Dam olish kuni (:day) — bu kunga dars yaratib boʻlmaydi.', ['day' => Str::ucfirst(CreateSeries::weekdayName($weekday))]);
    }
}
