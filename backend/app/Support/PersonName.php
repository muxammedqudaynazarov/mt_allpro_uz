<?php

namespace App\Support;

/**
 * Odam ismlari (xodim, talaba, Zoom'dagi ism) hamma joyda katta harflarda ko'rsatiladi:
 * sayt, Excel, Telegram, amallar tarixi.
 *
 * Qoraqalpoq lotin alifbosida (2016) "ı" ning bosh harfi — "Í" ("I" esa "i" niki).
 * mb_strtoupper buni bilmaydi (ı -> I), shuning uchun "ı" oldindan almashtiriladi.
 */
final class PersonName
{
    public static function upper(?string $name): ?string
    {
        return $name === null ? null : mb_strtoupper(strtr($name, ['ı' => 'Í']), 'UTF-8');
    }
}
