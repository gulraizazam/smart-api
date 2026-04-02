<?php

declare(strict_types=1);

namespace App\Services\Reports\Concerns;

trait ParsesDateRange
{
    protected function parseDateRange(?string $dateRange): array
    {
        if ($dateRange) {
            $parts = explode(' - ', $dateRange);

            return [
                date('Y-m-d', strtotime($parts[0])),
                date('Y-m-d', strtotime($parts[1])),
            ];
        }

        return [null, null];
    }
}
