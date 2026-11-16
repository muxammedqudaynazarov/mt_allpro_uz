<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Teacher = 'teacher';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Bosh administrator',
            self::Admin => 'Administrator',
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
            self::Admin => ['lessons.create', 'lessons.view-own', 'lessons.view-group', 'users.manage', 'groups.view', 'groups.toggle', 'reports.view'],
            self::SuperAdmin => [
                'lessons.create', 'lessons.view-own', 'lessons.view-group', 'lessons.view-all',
                'users.manage', 'users.roles', 'users.delete',
                'groups.view', 'groups.toggle', 'groups.manage',
                'zoom.manage', 'slots.manage', 'reports.view', 'audit.view',
            ],
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
