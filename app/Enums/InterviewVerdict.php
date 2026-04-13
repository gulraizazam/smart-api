<?php

declare(strict_types=1);

namespace App\Enums;

enum InterviewVerdict: string
{
    case StrongHire = 'strong_hire';
    case Hire = 'hire';
    case NoHire = 'no_hire';
    case StrongNoHire = 'strong_no_hire';

    public function label(): string
    {
        return match ($this) {
            self::StrongHire => 'Strong Hire',
            self::Hire => 'Hire',
            self::NoHire => 'No Hire',
            self::StrongNoHire => 'Strong No Hire',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::StrongHire => 'label-success',
            self::Hire => 'label-light-success',
            self::NoHire => 'label-light-danger',
            self::StrongNoHire => 'label-danger',
        };
    }
}
