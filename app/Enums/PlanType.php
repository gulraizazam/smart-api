<?php

declare(strict_types=1);

namespace App\Enums;

enum PlanType: string
{
    case Plan = 'plan';
    case Bundle = 'bundle';
    case Membership = 'membership';

    public function label(): string
    {
        return match ($this) {
            self::Plan => 'Plan',
            self::Bundle => 'Bundle',
            self::Membership => 'Membership',
        };
    }
}
