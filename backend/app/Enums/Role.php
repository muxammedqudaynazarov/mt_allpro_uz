<?php

namespace App\Enums;

/**
 * Tizim rollari. Foydalanuvchida bir nechta rol bo'lishi mumkin
 * (masalan, O'quv bo'limi xodimi ham dars o'tsa — Oʻqituvchi ham).
 */
enum Role: string
{
    /** Tizim egasi: hamma narsa, jumladan Zoom akkauntlari, foydalanuvchilar va rollar. */
    case SuperAdmin = 'super_admin';

    /** O'quv bo'limi: barcha darslar va guruhlarni kuzatadi, hisobot oladi. Dars yaratmaydi. */
    case Education = 'education';

    case Teacher = 'teacher';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Administrator',
            self::Education => 'Oʻquv boʻlimi',
            self::Teacher => 'Oʻqituvchi',
        };
    }

    /**
     * Rolga tegishli ruxsatlar. Frontend menyu va tugmalarni shu ro'yxat
     * asosida ko'rsatadi; backend esa har bir amalni Policy orqali alohida tekshiradi.
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Teacher => ['lessons.create', 'lessons.view-own'],
            self::Education => [
                'lessons.view-own', 'lessons.view-all', 'lessons.monitor',
                'groups.view', 'students.manage', 'reports.view', 'users.view', 'users.approve',
            ],
            self::SuperAdmin => [
                'lessons.create', 'lessons.view-own', 'lessons.view-all', 'lessons.monitor',
                'groups.view', 'groups.toggle', 'groups.manage', 'students.manage', 'reports.view',
                'users.view', 'users.approve', 'users.manage', 'users.roles', 'users.delete',
                'calendar.manage', 'calendar.manage-all',
                'zoom.manage', 'slots.manage', 'settings.manage', 'audit.view',
            ],
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
