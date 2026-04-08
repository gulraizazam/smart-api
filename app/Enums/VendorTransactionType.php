<?php

declare(strict_types=1);

namespace App\Enums;

enum VendorTransactionType: string
{
    case Purchase = 'purchase';
    case Payment = 'payment';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Purchase',
            self::Payment => 'Payment',
        };
    }
}
