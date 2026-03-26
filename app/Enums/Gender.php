<?php

namespace App\Enums;

enum Gender: int
{
    case Male = 1;
    case Female = 2;

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Male',
            self::Female => 'Female',
        };
    }

    public static function fromLabel(string $label): self
    {
        return match (strtolower(trim($label))) {
            'male', 'm' => self::Male,
            'female', 'f' => self::Female,
            default => self::Male,
        };
    }
}
