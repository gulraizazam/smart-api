<?php

declare(strict_types=1);

namespace App\Services\Reports\Concerns;

trait ParsesDateRange
{
    /**
     * Parse a "Y-m-d - Y-m-d" (or "m/d/Y - m/d/Y" — any strtotime-compatible)
     * range into a two-element [start, end] tuple of Y-m-d strings.
     *
     * Returns [null, null] if the input is empty/null. If the input contains
     * only one date (no " - " separator), both elements fall back to the
     * same date — callers that reject single-date input should validate
     * upstream.
     */
    protected function parseDateRange(?string $dateRange): array
    {
        if (! $dateRange) {
            return [null, null];
        }

        $parts = explode(' - ', $dateRange);

        return [
            date('Y-m-d', strtotime($parts[0])),
            date('Y-m-d', strtotime($parts[1] ?? $parts[0])),
        ];
    }
}
