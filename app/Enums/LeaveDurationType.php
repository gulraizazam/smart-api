<?php

declare(strict_types=1);

namespace App\Enums;

enum LeaveDurationType: string
{
    case Full = 'full';
    case Half = 'half';
    case Short = 'short';

    public function label(): string
    {
        return match ($this) {
            self::Full => 'Full Day',
            self::Half => 'Half Day',
            self::Short => 'Short Leave',
        };
    }

    public function dayValue(): float
    {
        return match ($this) {
            self::Full => 1.0,
            self::Half => 0.5,
            self::Short => 0.25,
        };
    }
}
