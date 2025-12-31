<?php

namespace App\Reports;

use App\Models\Locations;
use App\Models\PackageAdvances;
use Auth;
use Carbon\Carbon;
use Config;

class dashboardreport
{
    /*
     * Collection by centre widgets calculation
     */

    public static function CollectionByRevenueWidgets($location_informations, $account_id, $where, $request)
    {
        $total = 0;
        $report_data = [];
        $report_data[] = [
            'Task',
            'Hours per Day',
        ];

        if (empty($location_informations)) {
            return [$report_data, $total];
        }

        // Fetch all locations with city relationship in a single query
        $locations = Locations::with('city')->whereIn('id', $location_informations)->get()->keyBy('id');

        // Build date conditions based on $where parameter
        $query = PackageAdvances::with('paymentmode')
            ->where('account_id', $account_id)
            ->whereIn('location_id', $location_informations);

        // Use date range queries instead of whereDate for better index usage
        switch ($where) {
            case 'today':
                $query->where('created_at', '>=', Carbon::today())
                      ->where('created_at', '<', Carbon::tomorrow());
                break;
            case 'yesterday':
                $query->where('created_at', '>=', Carbon::yesterday())
                      ->where('created_at', '<', Carbon::today());
                break;
            case 'last7day':
                $query->where('created_at', '>=', Carbon::now()->subDays(6)->startOfDay())
                      ->where('created_at', '<', Carbon::tomorrow());
                break;
            case 'week':
                $query->where('created_at', '>=', Carbon::now()->startOfWeek())
                      ->where('created_at', '<', Carbon::now()->endOfWeek()->addDay());
                break;
            case 'thisMonth':
                $query->where('created_at', '>=', Carbon::now()->startOfMonth())
                      ->where('created_at', '<', Carbon::now()->endOfMonth()->addDay());
                break;
            case 'lastMonth':
                $query->where('created_at', '>=', Carbon::now()->subMonth()->startOfMonth())
                      ->where('created_at', '<', Carbon::now()->subMonth()->endOfMonth()->addDay());
                break;
        }

        // Fetch all records in a single query
        $allPackageAdvances = $query->get();

        // Group by location_id for processing
        $groupedByLocation = $allPackageAdvances->groupBy('location_id');

        foreach ($location_informations as $location_id) {
            $packagesadvances = $groupedByLocation->get($location_id, collect());
            $location_single_info = $locations->get($location_id);

            if (!$location_single_info) {
                continue;
            }

            $total_revenue_cash_in = 0;
            $total_revenue_card_in = 0;
            $total_refund_out = 0;

            foreach ($packagesadvances as $packagesadvance) {
                if (
                    ($packagesadvance->cash_flow == 'in' &&
                    $packagesadvance->is_adjustment == '0' &&
                    $packagesadvance->is_tax == '0' &&
                    $packagesadvance->is_cancel == '0') ||
                    ($packagesadvance->cash_flow == 'out' &&
                    $packagesadvance->is_adjustment == '0' &&
                    $packagesadvance->is_tax == '0' &&
                    $packagesadvance->is_cancel == '0' &&
                    $packagesadvance->is_refund == 1)
                ) {
                    if ($packagesadvance->cash_amount != 0) {
                        if ($packagesadvance->cash_flow == 'in') {
                            $paymentModeName = $packagesadvance->paymentmode->name ?? '';
                            if ($paymentModeName == 'Cash') {
                                $total_revenue_cash_in += $packagesadvance->cash_amount;
                            } elseif ($paymentModeName == 'Card') {
                                $total_revenue_card_in += $packagesadvance->cash_amount;
                            } elseif ($paymentModeName == 'Bank/Wire Transfer') {
                                $total_revenue_card_in += $packagesadvance->cash_amount;
                            }
                        } else {
                            $total_refund_out += $packagesadvance->cash_amount;
                        }
                    }
                }
            }

            $total_revenue = $total_revenue_cash_in + $total_revenue_card_in;
            $In_hand_balance = $total_revenue - $total_refund_out;

            if ($In_hand_balance > 0) {
                $cityName = $location_single_info->city->name ?? '';
                $report_data[$location_id] = [
                    $cityName . ' - ' . $location_single_info->name,
                    $In_hand_balance,
                ];
                $total += $In_hand_balance;
            }
        }

        return [
            $report_data,
            $total,
        ];
    }

    public static function MyCollectionByRevenueWidgets($location_information, $account_id, $where, $request)
    {
        if (auth()->id() === 1) {
            return self::CollectionByRevenueWidgets($location_information, $account_id, $where, $request);
        }

        $total = 0;
        $report_data = [];
        $report_data[] = [
            'Task',
            'Hours per Day',
        ];

        if (empty($location_information)) {
            return [$report_data, $total];
        }

        // Get location IDs from the associative array
        $locationIds = array_keys($location_information);

        // Fetch all locations with city relationship in a single query
        $locations = Locations::with('city')->whereIn('id', $locationIds)->get()->keyBy('id');

        // Build date conditions based on $where parameter
        $query = PackageAdvances::with('paymentmode')
            ->where('account_id', $account_id)
            ->whereIn('location_id', $locationIds)
            ->where('created_by', Auth::User()->id);

        // Use date range queries instead of whereDate for better index usage
        switch ($where) {
            case 'today':
                $query->where('created_at', '>=', Carbon::today())
                      ->where('created_at', '<', Carbon::tomorrow());
                break;
            case 'yesterday':
                $query->where('created_at', '>=', Carbon::yesterday())
                      ->where('created_at', '<', Carbon::today());
                break;
            case 'last7day':
                $query->where('created_at', '>=', Carbon::now()->subDays(6)->startOfDay())
                      ->where('created_at', '<', Carbon::tomorrow());
                break;
            case 'thisMonth':
                $query->where('created_at', '>=', Carbon::now()->startOfMonth())
                      ->where('created_at', '<', Carbon::now()->endOfMonth()->addDay());
                break;
            case 'lastMonth':
                $query->where('created_at', '>=', Carbon::now()->subMonth()->startOfMonth())
                      ->where('created_at', '<', Carbon::now()->subMonth()->endOfMonth()->addDay());
                break;
        }

        // Fetch all records in a single query
        $allPackageAdvances = $query->get();

        // Group by location_id for processing
        $groupedByLocation = $allPackageAdvances->groupBy('location_id');

        foreach ($locationIds as $location_id) {
            $packagesadvances = $groupedByLocation->get($location_id, collect());
            $location_single_info = $locations->get($location_id);

            if (!$location_single_info) {
                continue;
            }

            $total_revenue_cash_in = 0;
            $total_revenue_card_in = 0;
            $total_refund_out = 0;

            foreach ($packagesadvances as $packagesadvance) {
                if (
                    $packagesadvance->cash_flow == 'in' &&
                    $packagesadvance->is_adjustment == '0' &&
                    $packagesadvance->is_tax == '0' &&
                    $packagesadvance->is_cancel == '0'
                ) {
                    if ($packagesadvance->cash_amount != 0) {
                        $paymentModeName = $packagesadvance->paymentmode->name ?? '';
                        if ($paymentModeName == 'Cash') {
                            $total_revenue_cash_in += $packagesadvance->cash_amount;
                        } elseif ($paymentModeName == 'Card') {
                            $total_revenue_card_in += $packagesadvance->cash_amount;
                        } elseif ($paymentModeName == 'Bank/Wire Transfer') {
                            $total_revenue_card_in += $packagesadvance->cash_amount;
                        }
                    }
                }
            }

            $total_revenue = $total_revenue_cash_in + $total_revenue_card_in;
            $In_hand_balance = $total_revenue - $total_refund_out;

            if ($In_hand_balance > 0) {
                $cityName = $location_single_info->city->name ?? '';
                $report_data[] = [
                    $cityName . ' - ' . $location_single_info->name,
                    $In_hand_balance,
                ];
                $total += $In_hand_balance;
            }
        }

        return [
            $report_data,
            $total,
        ];
    }

    public static function collectionbycenter($location_informations, $account_id, $where, $request)
    {
        $total = 0;
        
        if (empty($location_informations)) {
            return [$total];
        }

        // Build date conditions based on $where parameter
        $query = PackageAdvances::with('paymentmode')
            ->where('account_id', $account_id)
            ->whereIn('location_id', $location_informations)
            ->whereNull('deleted_at');

        switch ($where) {
            case 'today':
                $query->whereDate('created_at', '=', Carbon::now()->format('Y-m-d'));
                break;
            case 'yesterday':
                $query->whereDate('created_at', '=', Carbon::now()->subDay(1)->format('Y-m-d'));
                break;
            case 'last7days':
                $query->whereDate('created_at', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                      ->whereDate('created_at', '<=', Carbon::now()->format('Y-m-d'));
                break;
            case 'week':
                $query->whereDate('created_at', '>=', Carbon::now()->startOfWeek())
                      ->whereDate('created_at', '<=', Carbon::now()->endOfWeek());
                break;
            case 'thisMonth':
                $query->whereDate('created_at', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                      ->whereDate('created_at', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'));
                break;
            case 'lastmonth':
                $query->whereDate('created_at', '>=', Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d'))
                      ->whereDate('created_at', '<=', Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d'));
                break;
        }

        // Fetch all records in a single query
        $allPackageAdvances = $query->get();

        // Group by location_id for processing
        $groupedByLocation = $allPackageAdvances->groupBy('location_id');

        foreach ($location_informations as $location_id) {
            $packagesadvances = $groupedByLocation->get($location_id, collect());
            
            $total_revenue_cash_in = 0;
            $total_revenue_card_in = 0;
            $total_refund_out = 0;

            foreach ($packagesadvances as $packagesadvance) {
                if (
                    ($packagesadvance->cash_flow == 'in' &&
                    $packagesadvance->is_adjustment == '0' &&
                    $packagesadvance->is_tax == '0' &&
                    $packagesadvance->is_cancel == '0') ||
                    ($packagesadvance->cash_flow == 'out' &&
                    $packagesadvance->is_adjustment == '0' &&
                    $packagesadvance->is_tax == '0' &&
                    $packagesadvance->is_cancel == '0' &&
                    $packagesadvance->is_refund == 1)
                ) {
                    if ($packagesadvance->cash_amount != 0) {
                        if ($packagesadvance->cash_flow == 'in') {
                            $paymentModeName = $packagesadvance->paymentmode->name ?? '';
                            if ($paymentModeName == 'Cash') {
                                $total_revenue_cash_in += $packagesadvance->cash_amount;
                            } elseif ($paymentModeName == 'Card') {
                                $total_revenue_card_in += $packagesadvance->cash_amount;
                            } elseif ($paymentModeName == 'Bank/Wire Transfer') {
                                $total_revenue_card_in += $packagesadvance->cash_amount;
                            }
                        } else {
                            $total_refund_out += $packagesadvance->cash_amount;
                        }
                    }
                }
            }

            $total_revenue = $total_revenue_cash_in + $total_revenue_card_in;
            $In_hand_balance = $total_revenue - $total_refund_out;
            $total += $In_hand_balance;
        }

        return [$total];
    }
}
