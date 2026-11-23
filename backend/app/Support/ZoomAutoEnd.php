<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Zoom'ni avtomatik yakunlash (Administrator sozlamasi, foizda): ba'zi OTMlarda dars Zoom'da
 * juftlikning bir qismi (masalan, 40 daqiqa), qolgani Moodle'da topshiriq.
 * 0 — uzilmaydi; 10% — 80 daqiqalik juftlikda 8 daqiqa; 50% — 40 daqiqa; 100% — juftlik oxirida.
 */
final class ZoomAutoEnd
{
    public static function percent(): int
    {
        $value = (array) Setting::get('zoom', []);

        return max(0, min(100, (int) ($value['auto_end_percent'] ?? 0)));
    }

    public static function enabled(): bool
    {
        return self::percent() > 0;
    }

    /** Juftlikning Zoom qismi, daqiqa (o'chiq bo'lsa — butun juftlik). */
    public static function minutes(int $slotMinutes): int
    {
        $percent = self::percent();

        return $percent > 0 ? max(1, (int) round($slotMinutes * $percent / 100)) : $slotMinutes;
    }
}
