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

    public static function CollectionByRevenueWidgets($locationIds, $accountId, $filter, $request)
{
    $reportData = [
        ['Task', 'Hours per Day'],
    ];

    // Define date range mappings
    $dateRanges = [
        'today' => [Carbon::today(), Carbon::today()],
        'yesterday' => [Carbon::yesterday(), Carbon::yesterday()],
        'last7day' => [Carbon::now()->subDays(6), Carbon::now()],
        'week' => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()],
        'thisMonth' => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()],
        'lastMonth' => [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()],
    ];

    [$startDate, $endDate] = $dateRanges[$filter] ?? $dateRanges['today'];

    // Preload all locations with city relationship to avoid N+1 queries
    $locations = Locations::with('city')
        ->whereIn('id', $locationIds)
        ->get()
        ->keyBy('id');

    $total = 0;

    foreach ($locationIds as $locationId) {
        $location = $locations[$locationId] ?? null;
        if (!$location) continue;

        $records = PackageAdvances::whereBetween('created_at', [$startDate, $endDate])
            ->where('account_id', $accountId)
            ->where('location_id', $locationId)
            ->get();

        $cashIn = $cardIn = $bankIn = $refundOut = 0;

        foreach ($records as $record) {
            if (
                $record->is_adjustment == '0' &&
                $record->is_tax == '0' &&
                $record->is_cancel == '0' &&
                (
                    ($record->cash_flow == 'in') ||
                    ($record->cash_flow == 'out' && $record->is_refund == 1)
                )
            ) {
                $amount = $record->cash_amount;

                if ($record->cash_flow == 'in') {
                    switch ($record->paymentmode->name) {
                        case 'Cash':
                            $cashIn += $amount;
                            break;
                        case 'Card':
                            $cardIn += $amount;
                            break;
                        case 'Bank/Wire Transfer':
                            $bankIn += $amount;
                            break;
                    }
                } elseif ($record->cash_flow == 'out') {
                    $refundOut += $amount;
                }
            }
        }

        $totalRevenue = $cashIn + $cardIn + $bankIn;
        $inHandBalance = $totalRevenue - $refundOut;

        if ($inHandBalance > 0) {
            $reportData[] = [
                $location->city->name . ' - ' . $location->name,
                $inHandBalance,
            ];
            $total += $inHandBalance;
        }
    }

    return [$reportData, $total];
}

    public static function MyCollectionByRevenueWidgets($location_information, $account_id, $where, $request)
    {

        if (auth()->id() === 1) {
            return self::CollectionByRevenueWidgets($location_information, $account_id, $where, $request);
        }

        $total = 0;
        $report_data = [];
        $wherecondtion = [];

        $wherecondtion[] = [
            'created_by',
            '=',
            Auth::User()->id,
        ];
        $counter = 0;

        $report_data[] = [
            'Task',
            'Hours per Day',
        ];

        foreach ($location_information as $key => $location_infomation) {
            if ($where == 'today') {
                $packagesadvances = PackageAdvances::whereDate('created_at', '=', Carbon::now()->format('Y-m-d'))
                    ->where([
                        ['account_id', '=', $account_id],
                        ['location_id', '=', $key],
                    ])->where($wherecondtion)->get();
            }
            if ($where == 'yesterday') {
                $packagesadvances = PackageAdvances::whereDate('created_at', '=', Carbon::now()->subDay(1)->format('Y-m-d'))
                    ->where([
                        ['account_id', '=', $account_id],
                        ['location_id', '=', $key],
                    ])->where($wherecondtion)->get();
            }
            if ($where == 'last7day') {
                $packagesadvances = PackageAdvances::whereDate('created_at', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                    ->whereDate('created_at', '<=', Carbon::now()->format('Y-m-d'))
                    ->where([
                        ['account_id', '=', $account_id],
                        ['location_id', '=', $key],
                    ])->where($wherecondtion)->get();
            }
            if ($where == 'thisMonth') {
                $packagesadvances = PackageAdvances::whereDate('created_at', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                    ->whereDate('created_at', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                    ->where([
                        ['account_id', '=', $account_id],
                        ['location_id', '=', $key],
                    ])->where($wherecondtion)->get();
            }
            if ($where == 'lastMonth') {
                $packagesadvances = PackageAdvances::whereDate('created_at', '>=', Carbon::now()->subMonth()->StartOfMonth()->format('Y-m-d'))
                    ->whereDate('created_at', '<=', Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d'))
                    ->where([
                        ['account_id', '=', $account_id],
                        ['location_id', '=', $key],
                    ])->get();
            }
            $location_single_info = Locations::find($key);

            if ($packagesadvances) {
                $balance = 0;
                $total_balance = 0;
                $total_revenue_cash_in = 0;
                $total_revenue_card_in = 0;
                $total_refund_out = 0;

                foreach ($packagesadvances as $packagesadvance) {
                    if (
                        (
                            $packagesadvance->cash_flow == 'in' &&
                            $packagesadvance->is_adjustment == '0' &&
                            $packagesadvance->is_tax == '0' &&
                            $packagesadvance->is_cancel == '0'
                        )
                    ) {
                        switch ($packagesadvance->cash_flow) {
                            case 'in':
                                $balance = $balance + $packagesadvance->cash_amount;
                                break;
                            case 'out':
                                $balance = $balance - $packagesadvance->cash_amount;
                                break;
                            default:
                                break;
                        }
                        $total_balance = $balance;
                        if ($packagesadvance->cash_amount != 0) {
                            if ($packagesadvance->package_id) {
                                $transtype = Config::get('constants.trans_type.advance_in');
                            }
                            if ($packagesadvance->invoice_id && $packagesadvance->cash_flow == 'in') {
                                $transtype = Config::get('constants.trans_type.advance_in');
                            }
                            if ($packagesadvance->is_adjustment == '1') {
                                $transtype = Config::get('constants.trans_type.adjustment');
                            }
                            if ($packagesadvance->is_cancel == '1') {
                                $transtype = Config::get('constants.trans_type.invoice_cancel');
                            }
                            if ($packagesadvance->invoice_id && $packagesadvance->cash_flow == 'out') {
                                $transtype = Config::get('constants.trans_type.invoice_create');
                            }
                            if ($packagesadvance->is_refund == '1') {
                                $transtype = Config::get('constants.trans_type.refund_in');
                            }
                            if ($packagesadvance->is_tax == '1') {
                                $transtype = Config::get('constants.trans_type.tax_out');
                            }
                            if ($packagesadvance->cash_flow == 'in') {
                                if ($packagesadvance->paymentmode->name == 'Cash') {
                                    $revenue_cash_in = $packagesadvance->cash_amount;
                                    $revenue_card_in = '';
                                    $revenue_bank_in = '';
                                    $refund_out = '';
                                }
                                if ($packagesadvance->paymentmode->name == 'Card') {
                                    $revenue_cash_in = '';
                                    $revenue_card_in = $packagesadvance->cash_amount;
                                    $revenue_bank_in = '';
                                    $refund_out = '';
                                }
                                if ($packagesadvance->paymentmode->name == 'Bank/Wire Transfer') {
                                    $revenue_cash_in = '';
                                    $revenue_card_in = '';
                                    $revenue_bank_in = $packagesadvance->cash_amount;
                                    $refund_out = '';
                                }
                            } else {
                                $revenue_cash_in = '';
                                $revenue_card_in = '';
                                $revenue_bank_in = '';
                                $refund_out = $packagesadvance->cash_amount;
                            }

                            if ($revenue_cash_in) {
                                $total_revenue_cash_in += $revenue_cash_in;
                            }
                            if ($revenue_card_in) {
                                $total_revenue_card_in += $revenue_card_in;
                            }
                            if ($revenue_bank_in) {
                                $total_revenue_card_in += $revenue_bank_in;
                            }
                            if ($refund_out) {
                                $total_refund_out += $refund_out;
                            }
                        }
                    }
                }
            }
            $total_revenue = $total_revenue_cash_in + $total_revenue_card_in;
            $In_hand_balance = $total_revenue - $total_refund_out;

            if ($In_hand_balance > 0) {
                $report_data[] = [
                    $location_single_info->city->name.' - '.$location_single_info->name,
                    $In_hand_balance,
                ];

                $total += $In_hand_balance;
            }

            $counter++;
        }

        return [
            $report_data,
            $total,
        ];
    }

    public static function collectionbycenter($location_informations, $account_id, $where, $request)
    {
        $total = 0;
        $wherecondtion = [];
        foreach ($location_informations as $key => $location_infomation) {
            if ($where == 'today') {
                $packagesadvances = PackageAdvances::whereDate('created_at', '=', Carbon::now()->format('Y-m-d'))
                    ->where([
                        ['account_id', '=', $account_id],
                        ['location_id', '=', $location_infomation],
                    ])->get();
            }
            if ($where == 'yesterday') {
                $packagesadvances = PackageAdvances::whereDate('created_at', '=', Carbon::now()->subDay(1)->format('Y-m-d'))
                    ->where([
                        ['account_id', '=', $account_id],
                        ['location_id', '=', $location_infomation],
                    ])->get();
            }
            if ($where == 'last7days') {
                $packagesadvances = PackageAdvances::whereDate('created_at', '>=', Carbon::now()->subDay(6)->format('Y-m-d'))
                    ->whereDate('created_at', '<=', Carbon::now()->format('Y-m-d'))
                    ->where([
                        ['account_id', '=', $account_id],
                        ['location_id', '=', $location_infomation],
                    ])->get();
            }
            if ($where == 'week') {
                $packagesadvances = PackageAdvances::whereDate('created_at', '>=', Carbon::now()->startOfWeek())
                    ->whereDate('created_at', '<=', Carbon::now()->endOfWeek())
                    ->where([
                        ['account_id', '=', $account_id],
                        ['location_id', '=', $location_infomation],
                    ])->get();
            }
            if ($where == 'thisMonth') {
                $packagesadvances = PackageAdvances::whereDate('created_at', '>=', Carbon::now()->startOfMonth()->format('Y-m-d'))
                    ->whereDate('created_at', '<=', Carbon::now()->endOfMonth()->format('Y-m-d'))
                    ->where([
                        ['account_id', '=', $account_id],
                        ['location_id', '=', $location_infomation],
                    ])->get();
            }
            if ($where == 'lastmonth') {
                $packagesadvances = PackageAdvances::whereDate('created_at', '>=', Carbon::now()->subMonth()->startOfMonth()->format('Y-m-d'))
                    ->whereDate('created_at', '<=', Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d'))
                    ->where([
                        ['account_id', '=', $account_id],
                        ['location_id', '=', $location_infomation],
                    ])->get();
            }
            $location_single_info = Locations::find($location_infomation);
            if ($packagesadvances) {
                $balance = 0;
                $total_balance = 0;
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
                        $packagesadvance->is_refund == 1
                        )
                    ) {
                        switch ($packagesadvance->cash_flow) {
                            case 'in':
                                $balance = $balance + $packagesadvance->cash_amount;

                                break;
                            case 'out':
                                $balance = $balance - $packagesadvance->cash_amount;

                                break;
                            default:
                                break;
                        }
                        $total_balance = $balance;
                        if ($packagesadvance->cash_amount != 0) {
                            if ($packagesadvance->package_id) {
                                $transtype = Config::get('constants.trans_type.advance_in');
                            }
                            if ($packagesadvance->invoice_id && $packagesadvance->cash_flow == 'in') {
                                $transtype = Config::get('constants.trans_type.advance_in');
                            }
                            if ($packagesadvance->is_adjustment == '1') {
                                $transtype = Config::get('constants.trans_type.adjustment');
                            }
                            if ($packagesadvance->is_cancel == '1') {
                                $transtype = Config::get('constants.trans_type.invoice_cancel');
                            }
                            if ($packagesadvance->invoice_id && $packagesadvance->cash_flow == 'out') {
                                $transtype = Config::get('constants.trans_type.invoice_create');
                            }
                            if ($packagesadvance->is_refund == '1') {
                                $transtype = Config::get('constants.trans_type.refund_in');
                            }
                            if ($packagesadvance->is_tax == '1') {
                                $transtype = Config::get('constants.trans_type.tax_out');
                            }
                            if ($packagesadvance->cash_flow == 'in') {
                                if ($packagesadvance->paymentmode->name == 'Cash') {
                                    $revenue_cash_in = $packagesadvance->cash_amount;
                                    $revenue_card_in = '';
                                    $revenue_bank_in = '';
                                    $refund_out = '';
                                }
                                if ($packagesadvance->paymentmode->name == 'Card') {
                                    $revenue_cash_in = '';
                                    $revenue_card_in = $packagesadvance->cash_amount;
                                    $revenue_bank_in = '';
                                    $refund_out = '';
                                }
                                if ($packagesadvance->paymentmode->name == 'Bank/Wire Transfer') {
                                    $revenue_cash_in = '';
                                    $revenue_card_in = '';
                                    $revenue_bank_in = $packagesadvance->cash_amount;
                                    $refund_out = '';
                                }
                            } else {
                                $revenue_cash_in = '';
                                $revenue_card_in = '';
                                $revenue_bank_in = '';
                                $refund_out = $packagesadvance->cash_amount;
                            }

                            if ($revenue_cash_in) {
                                $total_revenue_cash_in += $revenue_cash_in;
                            }
                            if ($revenue_card_in) {
                                $total_revenue_card_in += $revenue_card_in;
                            }
                            if ($revenue_bank_in) {
                                $total_revenue_card_in += $revenue_bank_in;
                            }
                            if ($refund_out) {
                                $total_refund_out += $refund_out;
                            }
                        }
                    }
                }
            }
            $total_revenue = $total_revenue_cash_in + $total_revenue_card_in;
            $In_hand_balance = $total_revenue - $total_refund_out;
            $total += $In_hand_balance;
        }

        return [
            $total,
        ];
    }
}
