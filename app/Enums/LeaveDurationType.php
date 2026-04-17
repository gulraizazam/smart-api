<?php

declare(strict_types=1);

namespace App\Enums;

enum LeaveDurationType: string
{
    case Full = 'full';
    case Half = 'half';
    case Short = 'short';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Full => 'Full Day',
            self::Half => 'Half Day',
            self::Short => 'Short Leave',
            self::Custom => 'By Hours',
        };
    }

    public function isSingleDay(): bool
    {
        return $this !== self::Full;
    }

    // Fraction of a working day this leave represents.
    // Half = 0.5 and Short = 0.25 of the employee's shift (so 4h on an 8h shift, 3h on a 6h shift).
    // Custom is caller-supplied (hours / shift) and has no fixed value.
    public function dayValue(): float
    {
        return match ($this) {
            self::Full => 1.0,
            self::Half => 0.5,
            self::Short => 0.25,
            self::Custom => throw new \LogicException('Custom duration requires custom_hours and shift_hours to compute.'),
        };
    }
}
