<?php

namespace App\Enums;

enum UserStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Tasdiq kutmoqda',
            self::Active => 'Faol',
            self::Blocked => 'Bloklangan',
        };
    }
}
