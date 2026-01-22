<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use App\Models\InvoiceStatuses;
use App\Models\AppointmentStatuses;

/**
 * Dashboard Helper Class
 * 
 * Provides common functions and cached values for dashboard-related controllers.
 * All methods are static and values are cached within a single request.
 */
class DashboardHelper
{
    /**
     * Cache storage for request-scoped values
     */
    private static $cache = [];

    /**
     * Get date range based on period/type parameter
     * 
     * @param string|null $period Period identifier (today, yesterday, last7days, week, thismonth, lastmonth, etc.)
     * @return array [start_date, end_date] in Y-m-d format
     */
    public static function getDateRange($period = null)
    {
        $cacheKey = 'date_range_' . ($period ?? 'default');
        
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        switch ($period) {
            case 'today':
                $start_date = Carbon::now()->format('Y-m-d');
                $end_date = Carbon::now()->format('Y-m-d');
                break;
            case 'yesterday':
                $start_date = Carbon::now()->subDay(1)->format('Y-m-d');
                $end_date = Carbon::now()->subDay(1)->format('Y-m-d');
                break;
            case 'last7days':
                $start_date = Carbon::now()->subDay(6)->format('Y-m-d');
                $end_date = Carbon::now()->format('Y-m-d');
                break;
            case 'week':
                $start_date = Carbon::now()->startOfWeek(Carbon::SUNDAY)->format('Y-m-d');
                $end_date = Carbon::now()->format('Y-m-d');
                break;
            case 'month':
            case 'thismonth':
                $start_date = Carbon::now()->startOfMonth()->format('Y-m-d');
                $end_date = Carbon::now()->format('Y-m-d');
                break;
            case 'lastmonth':
                $start_date = Carbon::now()->startOfMonth()->subMonth()->format('Y-m-d');
                $end_date = Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d');
                break;
            default:
                $start_date = Carbon::now()->format('Y-m-d');
                $end_date = Carbon::now()->format('Y-m-d');
                break;
        }

        self::$cache[$cacheKey] = [$start_date, $end_date];
        return self::$cache[$cacheKey];
    }

    /**
     * Get date range from request object
     * Checks both 'type' and 'period' parameters
     * 
     * @param \Illuminate\Http\Request $request
     * @return array [start_date, end_date] in Y-m-d format
     */
    public static function getDateRangeFromRequest($request)
    {
        $period = $request->type ?? $request->period ?? null;
        return self::getDateRange($period);
    }

    /**
     * Get user centres (cached)
     * 
     * @return array|Collection
     */
    public static function getUserCentres()
    {
        if (isset(self::$cache['user_centres'])) {
            return self::$cache['user_centres'];
        }

        if (auth()->id() == 1) {
            self::$cache['user_centres'] = [];
        } else {
            self::$cache['user_centres'] = ACL::getUserCentres();
        }

        return self::$cache['user_centres'];
    }

    /**
     * Get paid invoice status (cached)
     * 
     * @return InvoiceStatuses|null
     */
    public static function getPaidInvoiceStatus()
    {
        if (isset(self::$cache['paid_invoice_status'])) {
            return self::$cache['paid_invoice_status'];
        }

        self::$cache['paid_invoice_status'] = InvoiceStatuses::where('slug', 'paid')->first();
        return self::$cache['paid_invoice_status'];
    }

    /**
     * Get paid invoice status ID (cached)
     * 
     * @return int|null
     */
    public static function getPaidInvoiceStatusId()
    {
        $status = self::getPaidInvoiceStatus();
        return $status ? $status->id : null;
    }

    /**
     * Get appointment status IDs (arrived and converted) for current account (cached)
     * 
     * @return array ['arrived' => id, 'converted' => id]
     */
    public static function getAppointmentStatusIds()
    {
        $accountId = Auth::User()->account_id ?? null;
        $cacheKey = 'appointment_status_ids_' . $accountId;

        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        $statuses = AppointmentStatuses::where('account_id', $accountId)
            ->where(function($q) {
                $q->where('is_arrived', 1)->orWhere('is_converted', 1);
            })->get();

        self::$cache[$cacheKey] = [
            'arrived' => $statuses->firstWhere('is_arrived', 1)->id ?? config('constants.appointment_status_arrived', 2),
            'converted' => $statuses->firstWhere('is_converted', 1)->id ?? 16,
        ];

        return self::$cache[$cacheKey];
    }

    /**
     * Get arrived appointment status ID (cached)
     * 
     * @return int
     */
    public static function getArrivedStatusId()
    {
        return self::getAppointmentStatusIds()['arrived'];
    }

    /**
     * Get converted appointment status ID (cached)
     * 
     * @return int
     */
    public static function getConvertedStatusId()
    {
        return self::getAppointmentStatusIds()['converted'];
    }

    /**
     * Get both arrived and converted status IDs as array (for whereIn queries)
     * 
     * @return array
     */
    public static function getArrivedAndConvertedStatusIds()
    {
        $ids = self::getAppointmentStatusIds();
        return [$ids['arrived'], $ids['converted']];
    }

    /**
     * Get period map for collection/revenue reports
     * Maps request type to internal period name
     * 
     * @return array
     */
    public static function getPeriodMap()
    {
        return [
            'today' => 'today',
            'yesterday' => 'yesterday',
            'last7days' => 'last7days',
            'week' => 'week',
            'month' => 'thisMonth',
            'thismonth' => 'thisMonth',
            'lastmonth' => 'lastmonth',
        ];
    }

    /**
     * Map request type to period name
     * 
     * @param string|null $type
     * @return string
     */
    public static function mapPeriod($type)
    {
        $map = self::getPeriodMap();
        return $map[$type] ?? 'today';
    }

    /**
     * Get current timezone (Asia/Karachi)
     * 
     * @return string
     */
    public static function getTimezone()
    {
        return 'Asia/Karachi';
    }

    /**
     * Get current date/time info
     * 
     * @return array
     */
    public static function getDateTimeInfo()
    {
        if (isset(self::$cache['datetime_info'])) {
            return self::$cache['datetime_info'];
        }

        $now = Carbon::now()->timezone(self::getTimezone());
        
        self::$cache['datetime_info'] = [
            'today' => $now->format('Y-m-d'),
            'startWeek' => $now->copy()->startOfWeek()->format('Y-m-d'),
            'month' => $now->format('Y-m-d'),
            'currentTime' => $now->format('H:i:s'),
        ];

        return self::$cache['datetime_info'];
    }

    /**
     * Get user cities (cached)
     * 
     * @return array|Collection
     */
    public static function getUserCities()
    {
        if (isset(self::$cache['user_cities'])) {
            return self::$cache['user_cities'];
        }

        self::$cache['user_cities'] = ACL::getUserCities();
        return self::$cache['user_cities'];
    }

    /**
     * Clear all cached values (useful for testing)
     */
    public static function clearCache()
    {
        self::$cache = [];
    }
}
