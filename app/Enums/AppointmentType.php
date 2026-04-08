<?php

declare(strict_types=1);

namespace App\Enums;

enum AppointmentType: int
{
    case Consultancy = 1;
    case Treatment = 2;

    public function label(): string
    {
        return match ($this) {
            self::Consultancy => 'Consultancy',
            self::Treatment => 'Treatment',
        };
    }
}
