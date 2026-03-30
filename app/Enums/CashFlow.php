<?php

declare(strict_types=1);

namespace App\Enums;

enum CashFlow: string
{
    case In = 'in';
    case Out = 'out';

    public function label(): string
    {
        return match ($this) {
            self::In => 'Cash In',
            self::Out => 'Cash Out',
        };
    }
}
