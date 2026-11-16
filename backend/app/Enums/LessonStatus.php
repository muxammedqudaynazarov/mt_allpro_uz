<?php

namespace App\Enums;

enum LessonStatus: string
{
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Finished = 'finished';
    case Cancelled = 'cancelled';

    public function isOpen(): bool
    {
        return $this === self::Scheduled || $this === self::Live;
    }

    /** @return list<string> */
    public static function openValues(): array
    {
        return [self::Scheduled->value, self::Live->value];
    }
}
