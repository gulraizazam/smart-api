<?php

declare(strict_types=1);

namespace App\Enums;

enum InvoiceType: string
{
    case Exempt = 'exempt';
    case Taxable = 'taxable';

    public function label(): string
    {
        return match ($this) {
            self::Exempt => 'Exempt',
            self::Taxable => 'Taxable',
        };
    }
}
