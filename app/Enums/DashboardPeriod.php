<?php

namespace App\Enums;

use Carbon\Carbon;

enum DashboardPeriod: string
{
    case Today = 'today';
    case Yesterday = 'yesterday';
    case Last7Days = 'last7days';
    case Week = 'week';
    case ThisMonth = 'thismonth';
    case LastMonth = 'lastmonth';

    /**
     * Resolve from a request parameter that may use alternate names.
     * Accepts: 'month', 'thismonth', 'today', etc.
     */
    public static function fromRequest(?string $value): self
    {
        if ($value === null || $value === '') {
            return self::Today;
        }

        // Normalize 'month' alias to 'thismonth'
        if ($value === 'month') {
            return self::ThisMonth;
        }

        return self::tryFrom($value) ?? self::Today;
    }

    /**
     * Date range as [start, end] in Y-m-d format.
     */
    public function dateRange(): array
    {
        return match ($this) {
            self::Today     => [Carbon::today()->format('Y-m-d'), Carbon::today()->format('Y-m-d')],
            self::Yesterday => [Carbon::yesterday()->format('Y-m-d'), Carbon::yesterday()->format('Y-m-d')],
            self::Last7Days => [Carbon::today()->subDays(6)->format('Y-m-d'), Carbon::today()->format('Y-m-d')],
            self::Week      => [Carbon::now()->startOfWeek(Carbon::SUNDAY)->format('Y-m-d'), Carbon::today()->format('Y-m-d')],
            self::ThisMonth => [Carbon::now()->startOfMonth()->format('Y-m-d'), Carbon::today()->format('Y-m-d')],
            self::LastMonth => [Carbon::now()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d'), Carbon::now()->subMonthNoOverflow()->endOfMonth()->format('Y-m-d')],
        };
    }

    /**
     * Map to the legacy report period string used by dashboardreport class.
     * e.g. 'thisMonth', 'lastMonth', 'last7day', 'week', 'today', 'yesterday'
     */
    public function toReportPeriod(): string
    {
        return match ($this) {
            self::Today     => 'today',
            self::Yesterday => 'yesterday',
            self::Last7Days => 'last7day',
            self::Week      => 'week',
            self::ThisMonth => 'thisMonth',
            self::LastMonth => 'lastMonth',
        };
    }
}
