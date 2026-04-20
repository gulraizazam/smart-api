<?php

declare(strict_types=1);

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
            self::Today => [Carbon::today()->format('Y-m-d'), Carbon::today()->format('Y-m-d')],
            self::Yesterday => [Carbon::yesterday()->format('Y-m-d'), Carbon::yesterday()->format('Y-m-d')],
            self::Last7Days => [Carbon::today()->subDays(6)->format('Y-m-d'), Carbon::today()->format('Y-m-d')],
            self::Week => [Carbon::now()->startOfWeek(Carbon::SUNDAY)->format('Y-m-d'), Carbon::today()->format('Y-m-d')],
            self::ThisMonth => [Carbon::now()->startOfMonth()->format('Y-m-d'), Carbon::today()->format('Y-m-d')],
            self::LastMonth => [Carbon::now()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d'), Carbon::now()->subMonthNoOverflow()->endOfMonth()->format('Y-m-d')],
        };
    }

    /**
     * Same as {@see dateRange()}, but clamps the end of the window to
     * yesterday. Used by dashboard widgets that must exclude partial
     * "today" data (e.g. doctor feedback, where the current day's ratings
     * may be incomplete until the day closes).
     *
     * Semantics preserved from the legacy `DashboardChartService::getFeedbackDateRange`:
     *  - Last7Days returns a full 7-day window ending yesterday (today-7 → yesterday),
     *    not a 6-day window — the period shifts back as a whole.
     *  - Week starts on Monday here (Carbon's default), matching the
     *    long-standing feedback-chart behaviour even though {@see dateRange()}
     *    uses Sunday. That inconsistency predates this helper; don't fix in
     *    passing.
     *  - ThisMonth starts on the 1st of the current month (unchanged) and
     *    ends yesterday.
     *  - LastMonth already ends before today; returned as-is.
     *  - Today/Yesterday collapse to [yesterday, yesterday] so the range
     *    remains a valid SQL BETWEEN.
     *
     * @return array{0: string, 1: string}
     */
    public function dateRangeExcludingToday(): array
    {
        $yesterday = Carbon::yesterday()->format('Y-m-d');

        return match ($this) {
            self::Today, self::Yesterday => [$yesterday, $yesterday],
            self::Last7Days => [Carbon::today()->subDays(7)->format('Y-m-d'), $yesterday],
            self::Week => [Carbon::now()->startOfWeek()->format('Y-m-d'), $yesterday],
            self::ThisMonth => [Carbon::now()->startOfMonth()->format('Y-m-d'), $yesterday],
            self::LastMonth => $this->dateRange(),
        };
    }

    /**
     * Map to the legacy report period string used by dashboardreport class.
     * e.g. 'thisMonth', 'lastMonth', 'last7day', 'week', 'today', 'yesterday'
     */
    public function toReportPeriod(): string
    {
        return match ($this) {
            self::Today => 'today',
            self::Yesterday => 'yesterday',
            self::Last7Days => 'last7day',
            self::Week => 'week',
            self::ThisMonth => 'thisMonth',
            self::LastMonth => 'lastMonth',
        };
    }
}
