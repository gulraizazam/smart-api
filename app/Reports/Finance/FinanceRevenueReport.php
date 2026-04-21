<?php

declare(strict_types=1);

namespace App\Reports\Finance;

use App\Helpers\ACL;
use App\Helpers\GeneralFunctions;
use App\Models\Appointments;
use App\Models\InvoiceStatuses;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\Resources;
use App\Services\Reports\Concerns\ParsesDateRange;
use Carbon\Carbon;
use Config;

class FinanceRevenueReport
{
    use ParsesDateRange;

    /**
     * @deprecated Use App\Services\Reports\Revenue\GeneralRevenueDetailReport::generate() instead.
     */
    public static function generalrevenuereportdetail($data, $account_id)
    {
        $where = [];

        [$start_date, $end_date] = self::parseDateRange($data['date_range'] ?? null);
        $gender = $data['gender_id'];
        $where[] = [
            'account_id',
            '=',
            $account_id,
        ];
        $userCentres = ACL::getUserCentres();
        // Filter out empty values (from "All" option) in location_id_com
        $locationIds = array_filter($data['location_id_com'], fn ($val) => $val !== '' && $val !== null);
        // If no specific locations selected (only "All" was picked), use all user centres
        if (empty($locationIds)) {
            $locationIds = is_array($userCentres) ? $userCentres : [$userCentres];
        }
        // Preload all locations with city/region to prevent N+1
        $locationsMap = Locations::with(['city', 'region'])->whereIn('id', $locationIds)->get()->keyBy('id');

        $report_data = [];
        foreach ($locationIds as $location) {
            $query = PackageAdvances::with(['user:id,name,gender,phone', 'paymentmode:id,name'])
                ->whereDate('created_at', '>=', $start_date)
                ->whereDate('created_at', '<=', $end_date)
                ->where('location_id', '=', $location)
                ->where($where)
                ->orderBy('created_at', 'asc');

            // Add gender filter condition
            if ($gender !== 'all') {
                $query->whereHas('user', function ($q) use ($gender) {
                    $q->where('gender', $gender);
                });
            }

            $packagesadvances = $query->get();

            $location_single_info = $locationsMap[$location] ?? null;

            if (! $location_single_info) {
                continue;
            }

            if ($packagesadvances) {
                $balance = 0;
                $total_balance = 0;
                $report_data[$location_single_info->id] = [
                    'id' => $location_single_info->id,
                    'name' => $location_single_info->name,
                    'city' => $location_single_info->city->name,
                    'region' => $location_single_info->region->name,
                    'revenue_data' => [],
                ];

                foreach ($packagesadvances as $packagesadvance) {
                    if (
                        ($packagesadvance->cash_flow == 'in' &&
                            $packagesadvance->is_adjustment == '0' &&
                            $packagesadvance->is_tax == '0' &&
                            $packagesadvance->is_cancel == '0'
                        )
                        ||
                        ($packagesadvance->cash_flow == 'out' &&
                            $packagesadvance->is_refund == '1' &&
                            $packagesadvance->is_tax == '0'
                        )

                    ) {
                        $balance += match ($packagesadvance->cash_flow) {
                            'in' => $packagesadvance->cash_amount,
                            'out' => -$packagesadvance->cash_amount,
                            default => 0,
                        };
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
                                    $revenue_card_in = 0;
                                    $revenue_bank_in = 0;
                                    $refund_out = 0;
                                }
                                if ($packagesadvance->paymentmode->name == 'Card') {
                                    $revenue_cash_in = 0;
                                    $revenue_card_in = $packagesadvance->cash_amount;
                                    $revenue_bank_in = 0;
                                    $refund_out = 0;
                                }
                                if ($packagesadvance->paymentmode->name == 'Bank/Wire Transfer' || $packagesadvance->paymentmode->name == 'Bank') {
                                    $revenue_cash_in = 0;
                                    $revenue_card_in = 0;
                                    $revenue_bank_in = $packagesadvance->cash_amount;
                                    $refund_out = 0;
                                }
                            } else {
                                $revenue_cash_in = 0;
                                $revenue_card_in = 0;
                                $revenue_bank_in = 0;
                                $refund_out = $packagesadvance->cash_amount;
                            }
                            if ($packagesadvance->cash_flow == 'out') {

                                if ($packagesadvance->paymentmode->name == 'Cash') {
                                    $refund_cash_in = $packagesadvance->cash_amount;
                                    $refund_card_in = 0;
                                    $refund_bank_in = 0;
                                    $refund_out = 0;
                                }
                                if ($packagesadvance->paymentmode->name == 'Card') {
                                    $refund_cash_in = 0;
                                    $refund_card_in = $packagesadvance->cash_amount;
                                    $refund_bank_in = 0;
                                    $refund_out = 0;
                                }
                                if ($packagesadvance->paymentmode->name == 'Bank/Wire Transfer' || $packagesadvance->paymentmode->name == 'Bank') {
                                    $refund_cash_in = 0;
                                    $refund_card_in = 0;
                                    $refund_bank_in = $packagesadvance->cash_amount;
                                    $refund_out = 0;
                                }
                            } else {
                                $refund_cash_in = 0;
                                $refund_card_in = 0;
                                $refund_bank_in = 0;
                                $refund_out = $packagesadvance->cash_amount;
                            }
                            $genderLabel = $packagesadvance->user->gender == 1 ? 'Male' : 'Female';
                            $report_data[$location_single_info->id]['revenue_data'][$packagesadvance->id] = [
                                'patient_id' => $packagesadvance->patient_id,
                                'patient' => $packagesadvance->user->name,
                                'gender' => $genderLabel,
                                'phone' => GeneralFunctions::prepareNumber4Call($packagesadvance->user->phone),
                                'transtype' => $transtype,
                                'payment_mode_id' => $packagesadvance->payment_mode_id,
                                'payment_mode' => $packagesadvance->paymentmode->name ?? 'Cash',
                                'cash_flow' => $packagesadvance->cash_flow,
                                'revenue_cash_in' => $revenue_cash_in,
                                'revenue_card_in' => $revenue_card_in,
                                'revenue_bank_in' => $revenue_bank_in,
                                'refund_cash_in' => $refund_cash_in,
                                'refund_card_in' => $refund_card_in,
                                'refund_bank_in' => $refund_bank_in,
                                'refund_out' => $refund_cash_in + $refund_card_in + $refund_bank_in,
                                'Balance' => $balance,
                                'created_at' => Carbon::parse($packagesadvance->created_at)->format('F j,Y h:i A'),
                            ];
                        }
                    }
                }
            }
        }

        return $report_data;
    }

    /**
     * @deprecated Use App\Services\Reports\Revenue\GeneralRevenueSummaryReport::generate() instead.
     */
    public static function generalrevenuereportsummary($data, $account_id)
    {
        [$start_date, $end_date] = self::parseDateRange($data['date_range'] ?? null);

        // Filter by selected locations first, then by region if provided
        $userCentres = ACL::getUserCentres();

        // Filter out empty values (from "All" option) in location_id
        $locationIds = (! empty($data['location_id']) && is_array($data['location_id']))
            ? array_filter($data['location_id'], fn ($val) => $val !== '' && $val !== null)
            : [];

        if (! empty($locationIds)) {
            // Filter user centres to only selected locations
            $selectedLocations = array_intersect($locationIds, is_array($userCentres) ? $userCentres : [$userCentres]);
            if (empty($selectedLocations)) {
                $selectedLocations = $locationIds;
            }
            if (isset($data['region_id']) && $data['region_id']) {
                $location_information = Locations::generalrevenuegetActiveSorted($selectedLocations, $data['region_id']);
            } else {
                $location_information = Locations::getActiveSorted($selectedLocations);
            }
        } elseif (isset($data['region_id']) && $data['region_id']) {
            $location_information = Locations::generalrevenuegetActiveSorted($userCentres, $data['region_id']);
        } else {
            $location_information = Locations::getActiveSorted($userCentres);
        }

        $report_data = [];

        foreach ($location_information as $key => $location_infomation) {

            $packagesadvances = PackageAdvances::with(['user:id,gender', 'paymentmode:id,name'])
                ->whereDate('created_at', '>=', $start_date)
                ->whereDate('created_at', '<=', $end_date)
                ->where([
                    ['account_id', '=', $account_id],
                    ['location_id', '=', $key],
                ])->orderBy('created_at', 'asc')->get();

            $location_single_info = Locations::with(['city', 'region'])->find($key);

            if ($packagesadvances) {
                $balance = 0;
                $total_balance = 0;
                $total_revenue_cash_in = 0;
                $total_revenue_card_in = 0;
                $total_revenue_bank_in = 0;
                $total_refund_out = 0;

                // Gender-wise totals
                $male_total = 0;
                $female_total = 0;
                $unknown_gender_total = 0;

                foreach ($packagesadvances as $packagesadvance) {
                    if (
                        ($packagesadvance->cash_flow == 'in' &&
                            $packagesadvance->is_adjustment == '0' &&
                            $packagesadvance->is_tax == '0' &&
                            $packagesadvance->is_cancel == '0'
                        )
                        ||
                        ($packagesadvance->cash_flow == 'out' &&
                            $packagesadvance->is_refund == '1' &&
                            $packagesadvance->is_tax == '0'
                        )
                    ) {
                        $balance += match ($packagesadvance->cash_flow) {
                            'in' => $packagesadvance->cash_amount,
                            'out' => -$packagesadvance->cash_amount,
                            default => 0,
                        };
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
                                $total_revenue_bank_in += $revenue_bank_in;
                            }
                            if ($refund_out) {
                                $total_refund_out += $refund_out;
                            }

                            // Add gender-wise calculation
                            if ($packagesadvance->cash_flow == 'in') {
                                $amount = $packagesadvance->cash_amount;

                                if ($packagesadvance->user && isset($packagesadvance->user->gender)) {
                                    if ($packagesadvance->user->gender == 1) {
                                        $male_total += $amount;
                                    } elseif ($packagesadvance->user->gender == 2) {
                                        $female_total += $amount;
                                    } else {
                                        $unknown_gender_total += $amount;
                                    }
                                } else {
                                    $unknown_gender_total += $amount;
                                }
                            }
                        }
                    }
                }
            }

            $report_data[$location_single_info->id] = [
                'id' => $location_single_info->id,
                'name' => $location_single_info->name,
                'city' => $location_single_info->city->name,
                'region' => $location_single_info->region->name,
                'revenue_cash_in' => $total_revenue_cash_in,
                'revenue_card_in' => $total_revenue_card_in,
                'revenue_bank_in' => $total_revenue_bank_in,
                'refund_out' => $total_refund_out,
                'in_hand' => ($total_revenue_cash_in + $total_revenue_card_in + $total_revenue_bank_in) - $total_refund_out,
                // Gender-wise breakdown
                'male_revenue' => $male_total,
                'female_revenue' => $female_total,
                'unknown_gender_revenue' => $unknown_gender_total,
            ];
        }

        return $report_data;
    }

    /**
     * Machine Wise Revenue Report
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function machinewiseinvoicerevenuereport($data, $account_id)
    {
        [$start_date, $end_date] = self::parseDateRange($data['date_range'] ?? null);

        $where = [];

        if (isset($data['region_id']) && $data['region_id']) {
            /*
             * If region selected
             * case1: If location is selected
             * case2: If location is not selected
             */
            if ((isset($data['location_id']) && $data['location_id'])) {
                /* Case 1: */
                $Locations = Locations::generalrevenuegetActiveSorted($data['location_id'], $data['region_id']);
                if ($Locations->count()) {
                    foreach ($Locations as $key => $location) {
                        $where[] = $key;
                    }
                }
            } else {
                $Locations = Locations::generalrevenuegetActiveSorted(ACL::getUserCentres(), $data['region_id']);
                if ($Locations->count()) {
                    foreach ($Locations as $key => $location) {
                        $where[] = $key;
                    }
                }
            }
        } else {
            if ((isset($data['location_id']) && $data['location_id'])) {
                /* Case 1: */
                $where[] = $data['location_id'];
            } else {
                $Locations = Locations::getActiveSorted(ACL::getUserCentres());
                if ($Locations->count()) {
                    foreach ($Locations as $key => $location) {
                        $where[] = $key;
                    }
                }
            }
        }

        $location_info = Locations::whereIn('id', $where)->pluck('name', 'id');
        $locationsMap2 = Locations::with(['city', 'region'])->whereIn('id', $where)->get()->keyBy('id');

        $report_data = [];

        foreach ($location_info as $key => $location) {

            $loc_inform = $locationsMap2[$key] ?? Locations::with(['city', 'region'])->find($key);

            $report_data[$loc_inform->id] = [
                'id' => $key,
                'name' => $location,
                'region' => $loc_inform->region->name,
                'city' => $loc_inform->city->name,
                'machine' => [],
            ];

            $invoice_paid = InvoiceStatuses::where('slug', '=', 'paid')->first();

            /* Find resouce location wise */
            $resource_location = Resources::where('location_id', '=', $loc_inform->id)->get();
            $count = 0;

            if (! empty($resource_location)) {

                foreach ($resource_location as $recource) {

                    $report_data[$loc_inform->id]['machine'][$recource->id] = [
                        'id' => $recource->id,
                        'name' => $recource->name,
                        'machine_array' => [],
                    ];

                    $appointment_info = Appointments::join('invoices', 'appointments.id', '=', 'invoices.appointment_id')
                        ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                        ->where([
                            ['appointments.resource_id', '=', $recource->id],
                            ['invoices.invoice_status_id', '=', $invoice_paid->id],
                        ])->whereDate('invoices.created_at', '>=', $start_date)
                        ->whereDate('invoices.created_at', '<=', $end_date)
                        ->select('invoices.created_at as Invoice_created_at', 'invoices.id as Invoice_id', 'invoice_details.*', 'appointments.name', 'appointments.id as appointmentid')
                        ->get();

                    if (! empty($appointment_info)) {
                        $count++;
                        foreach ($appointment_info as $appointment) {
                            $report_data[$loc_inform->id]['machine'][$recource->id]['machine_array'][$appointment->appointmentid] = [
                                'id' => $appointment->Invoice_id,
                                'client' => $appointment->name,
                                'service_price' => $appointment->service_price,
                                'discount_name' => $appointment->discount_name,
                                'discount_type' => $appointment->discount_type,
                                'discount_price' => $appointment->discount_price,
                                'amount' => $appointment->tax_exclusive_serviceprice,
                                'tax_value' => $appointment->tax_price,
                                'net_amount' => $appointment->tax_including_price,
                                'is_exclusive' => $appointment->is_exclusive,
                                'created_at' => $appointment->Invoice_created_at,
                            ];
                        }
                    } else {
                        unset($report_data[$loc_inform->id]['machine'][$recource->id]);
                    }
                }
            }
            if ($count == 0) {
                unset($report_data[$loc_inform->id]);
            }
        }

        return $report_data;
    }
}
