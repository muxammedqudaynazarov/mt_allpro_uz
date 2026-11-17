<?php

namespace App\Enums;

use App\Models\Setting;

/**
 * O'zlashtirish taxmini — faqat Zoom'dagi qatnashuv asosida (baho emas). Chegaralar
 * (foizda) Administrator sozlamalaridan olinadi: talaba eng yuqori qaysi chegaraga
 * yetgan bo'lsa, o'sha daraja. Hech biriga yetmasa — "Past".
 */
enum Assessment: string
{
    case Past = 'past';
    case Sust = 'sust';
    case Qoniqarli = 'qoniqarli';
    case Ortacha = 'ortacha';
    case Yaxshi = 'yaxshi';
    case Alo = 'alo';

    /** Asos: umumiy qatnashgan vaqt foizi yoki qatnashgan darslar foizi. */
    public const BASIS_TIME = 'time';

    public const BASIS_LESSONS = 'lessons';

    /** Standart mezon (foiz, shu darajaga kirish uchun eng kami). */
    public const DEFAULTS = [
        'basis' => self::BASIS_TIME,
        // Talaba darsda kamida shuncha foiz bo'lsa — "qatnashgan" hisoblanadi.
        'presence_percent' => 50,
        'thresholds' => ['sust' => 30, 'qoniqarli' => 50, 'ortacha' => 65, 'yaxshi' => 80, 'alo' => 90],
    ];

    public function label(): string
    {
        return match ($this) {
            self::Past => __('Past'),
            self::Sust => __('Sust'),
            self::Qoniqarli => __('Qoniqarli'),
            self::Ortacha => __('Oʻrtacha'),
            self::Yaxshi => __('Yaxshi'),
            self::Alo => __('Aʼlo'),
        };
    }

    /** @return array{basis: string, presence_percent: int, thresholds: array<string, int>} */
    public static function criteria(): array
    {
        $saved = (array) Setting::get('assessment', []);

        return [
            'basis' => in_array($saved['basis'] ?? null, [self::BASIS_TIME, self::BASIS_LESSONS], true) ? $saved['basis'] : self::DEFAULTS['basis'],
            'presence_percent' => (int) ($saved['presence_percent'] ?? self::DEFAULTS['presence_percent']),
            'thresholds' => array_map('intval', array_replace(self::DEFAULTS['thresholds'], (array) ($saved['thresholds'] ?? []))),
        ];
    }

    public static function for(float $percent, ?array $thresholds = null): self
    {
        $thresholds ??= self::criteria()['thresholds'];
        foreach ([self::Alo, self::Yaxshi, self::Ortacha, self::Qoniqarli, self::Sust] as $level) {
            if ($percent >= $thresholds[$level->value]) {
                return $level;
            }
        }

        return self::Past;
    }
}
