<?php

declare(strict_types=1);

namespace App\Services\Reports\Concerns;

use Carbon\Carbon;

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
    protected static function parseDateRange(?string $dateRange): array
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

    /**
     * Same as {@see parseDateRange()}, but each side is suffixed with a
     * day-boundary time (start → 00:00:00, end → 23:59:59) so the result
     * can be used directly in `whereBetween('created_at', [...])`-style
     * clauses without further manipulation.
     *
     * @return array{0: ?string, 1: ?string}
     */
    protected static function parseDateRangeWithTimeBounds(?string $dateRange): array
    {
        [$start, $end] = self::parseDateRange($dateRange);

        return [
            $start === null ? null : $start.' 00:00:00',
            $end === null ? null : $end.' 23:59:59',
        ];
    }

    /**
     * Legacy datatable-filter variant: start preserves the input's
     * time-of-day (via strtotime), end is clamped to 23:59:00 on the input's
     * date. The 23:59:00 (not :59) clamp is load-bearing for byte-parity
     * with ~20 hand-rolled copies across models/services. Callers that
     * want full-second inclusion should use {@see parseDateRangeWithTimeBounds()}.
     *
     * @return array{0: ?string, 1: ?string}
     */
    protected static function parseDateRangeForFilter(?string $dateRange): array
    {
        if (! $dateRange) {
            return [null, null];
        }

        $parts = explode(' - ', $dateRange);
        $startTs = strtotime($parts[0]);
        $endTs = strtotime($parts[1] ?? $parts[0]);

        return [
            date('Y-m-d H:i:s', $startTs),
            date('Y-m-d 23:59:00', $endTs),
        ];
    }

    /**
     * Carbon-instance variant: returns `[startOfDay, endOfDay]` as Carbon
     * objects for callers using Eloquent where clauses that accept
     * `DateTimeInterface`. `null` input yields `[null, null]`.
     *
     * @return array{0: ?Carbon, 1: ?Carbon}
     */
    protected static function parseDateRangeAsCarbonDay(?string $dateRange): array
    {
        if (! $dateRange) {
            return [null, null];
        }

        $parts = explode(' - ', $dateRange);

        return [
            Carbon::parse($parts[0])->startOfDay(),
            Carbon::parse($parts[1] ?? $parts[0])->endOfDay(),
        ];
    }

    /**
     * Clamp the end of a [start, end] Y-m-d tuple so the window never
     * includes today. Intended for feedback / rating reports where the
     * current day's data is partial until the day closes — the report
     * should show only completed days. Start is left untouched.
     *
     * Passing [null, null] (the "no filter" tuple returned by
     * {@see parseDateRange()}) returns it unchanged.
     *
     * @param  array{0: ?string, 1: ?string}  $range
     * @return array{0: ?string, 1: ?string}
     */
    protected static function excludeTodayFromRange(array $range): array
    {
        [$start, $end] = $range;

        if ($end === null) {
            return $range;
        }

        $today = date('Y-m-d');

        return [
            $start,
            $end >= $today ? date('Y-m-d', strtotime('yesterday')) : $end,
        ];
    }
}
