<?php

declare(strict_types=1);

namespace App\Enums;

enum InterviewType: string
{
    case CEO = 'ceo';
    case Director = 'director';
    case HR = 'hr';

    public function label(): string
    {
        return match ($this) {
            self::CEO => 'CEO',
            self::Director => 'Director',
            self::HR => 'HR',
        };
    }
}
