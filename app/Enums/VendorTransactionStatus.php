<?php

declare(strict_types=1);

namespace App\Enums;

enum VendorTransactionStatus: string
{
    case Ordered = 'ordered';
    case Delivered = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::Ordered => 'Ordered',
            self::Delivered => 'Delivered',
        };
    }
}
