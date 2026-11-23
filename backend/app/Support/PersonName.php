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

    /**
     * Qisqa shakl: "FAMILIYA I.Sh." — familiya katta harflarda, ism va otasining ismi bosh harflari
     * (Sh, Ch, O‘, G‘ — ikki belgili). Masalan: Qudaynazarov Muxammed S… → "QUDAYNAZAROV M.S.".
     */
    public static function short(?string $lastName, ?string $firstName, ?string $middleName = null): string
    {
        $initials = implode('', array_filter(array_map(fn (?string $part) => self::initial($part), [$firstName, $middleName])));

        return trim(self::upper(trim((string) $lastName)).' '.$initials);
    }

    private static function initial(?string $part): ?string
    {
        $word = trim((string) $part);
        if ($word === '') {
            return null;
        }
        // O‘ / G‘ (turli apostroflar bilan) va Sh / Ch.
        if (preg_match('/^([OoGg])[‘’ʻʼ\'`]/u', $word, $m)) {
            return mb_strtoupper($m[1]).'‘.';
        }
        if (preg_match('/^(sh|ch)/iu', $word, $m)) {
            return mb_strtoupper(mb_substr($m[1], 0, 1)).mb_strtolower(mb_substr($m[1], 1)).'.';
        }

        return self::upper(mb_substr($word, 0, 1)).'.';
    }
}
