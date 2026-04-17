<?php

declare(strict_types=1);

namespace App\Enums;

enum ResourceType: string
{
    case Doctor = 'doctor';
    case Therapist = 'therapist';
    case Consultant = 'consultant';

    public function label(): string
    {
        return match ($this) {
            self::Doctor => 'Doctor',
            self::Therapist => 'Therapist',
            self::Consultant => 'Consultant',
        };
    }
}
