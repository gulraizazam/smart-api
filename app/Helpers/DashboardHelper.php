<?php

namespace App\Helpers;

use App\Enums\DashboardPeriod;
use App\Models\AppointmentStatuses;
use App\Models\InvoiceStatuses;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardHelper
{
    /**
     * Get date range from a period enum or legacy string.
     *
     * @return array{0: string, 1: string} [start_date, end_date] in Y-m-d format
     */
    public static function getDateRange(DashboardPeriod|string|null $period = null): array
    {
        $enum = $period instanceof DashboardPeriod
            ? $period
            : DashboardPeriod::fromRequest($period);

        return $enum->dateRange();
    }

    /**
     * Get date range from request (checks both 'type' and 'period' params).
     *
     * @return array{0: string, 1: string}
     */
    public static function getDateRangeFromRequest(Request $request): array
    {
        $period = $request->type ?? $request->period;

        return self::getDateRange($period);
    }

    /**
     * Get user centres (cached per-request via once()).
     */
    public static function getUserCentres(): array
    {
        return once(fn (): array => auth()->id() == 1 ? [] : ACL::getUserCentres());
    }

    /**
     * Get paid invoice status (cached per-request).
     */
    public static function getPaidInvoiceStatus(): ?InvoiceStatuses
    {
        return once(fn () => InvoiceStatuses::where('slug', 'paid')->first());
    }

    public static function getPaidInvoiceStatusId(): ?int
    {
        return self::getPaidInvoiceStatus()?->id;
    }

    /**
     * Get appointment status IDs for current account (cached per-request).
     *
     * @return array{arrived: int, converted: int}
     */
    public static function getAppointmentStatusIds(): array
    {
        return once(function (): array {
            $accountId = Auth::user()?->account_id;

            $statuses = AppointmentStatuses::where('account_id', $accountId)
                ->where(fn ($q) => $q->where('is_arrived', 1)->orWhere('is_converted', 1))
                ->get();

            return [
                'arrived'   => $statuses->firstWhere('is_arrived', 1)?->id ?? config('constants.appointment_status_arrived', 2),
                'converted' => $statuses->firstWhere('is_converted', 1)?->id ?? 16,
            ];
        });
    }

    public static function getArrivedStatusId(): int
    {
        return self::getAppointmentStatusIds()['arrived'];
    }

    public static function getConvertedStatusId(): int
    {
        return self::getAppointmentStatusIds()['converted'];
    }

    /** @return array{0: int, 1: int} */
    public static function getArrivedAndConvertedStatusIds(): array
    {
        $ids = self::getAppointmentStatusIds();

        return [$ids['arrived'], $ids['converted']];
    }

    /**
     * Map request type string to legacy report period string.
     */
    public static function mapPeriod(?string $type): string
    {
        return DashboardPeriod::fromRequest($type)->toReportPeriod();
    }

    public static function getTimezone(): string
    {
        return 'Asia/Karachi';
    }

    /**
     * @return array{today: string, startWeek: string, month: string, currentTime: string}
     */
    public static function getDateTimeInfo(): array
    {
        return once(function (): array {
            $now = Carbon::now()->timezone(self::getTimezone());

            return [
                'today'       => $now->format('Y-m-d'),
                'startWeek'   => $now->copy()->startOfWeek()->format('Y-m-d'),
                'month'       => $now->format('Y-m-d'),
                'currentTime' => $now->format('H:i:s'),
            ];
        });
    }

    public static function getUserCities(): array
    {
        return once(fn (): array => ACL::getUserCities());
    }

    /**
     * Clear once() caches — useful for testing.
     */
    public static function clearCache(): void
    {
        // once() caches are automatically cleared between requests.
        // For tests, use $this->forgetOnce() or recreate the app.
    }
}
