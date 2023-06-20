<?php

namespace App\Reports;

use App\Helpers\ACL;
use App\Helpers\Widgets\LocationsWidget;
use App\Models\InvoiceStatuses;
use App\Models\Locations;
use App\Models\Regions;
use App\User;
use Auth;
use Carbon\Carbon;
use DB;

class Revenue
{
    /**
     * Revenue Breakup Report
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function RevenueBreakup($data, $account_id)
    {
        $where = [];
        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        $start = Carbon::parse($start_date);
        $end = Carbon::parse($end_date);
        $days = $end->diffInDays($start);

        if (isset($data['role_id']) && $data['role_id']) {
            $where[] = [
                'role_id',
                '=',
                $data['role_id'],
            ];
        }
        if (isset($data['user_id']) && $data['user_id']) {
            $where[] = [
                'user_id',
                '=',
                $data['user_id'],
            ];
        }

        $invoicepaid = InvoiceStatuses::where('slug', '=', 'paid')->first();

        if (isset($data['region_id']) && $data['region_id']) {
            $regions = Regions::where(['active' => 1, 'slug' => 'custom', 'id' => $data['region_id']])->where('account_id', '=', Auth::User()->account_id)->pluck('name', 'id');
        } else {
            $regions = Regions::getActiveSorted(ACL::getUserRegions());
        }

        $revenuebreakup = [];

        foreach ($regions as $key => $region) {

            $revenuebreakup[$key] = [
                'id' => $key,
                'name' => $region,
                'centers' => [],
            ];
            $whereLocation = [];
            if (isset($data['location_id']) && $data['location_id']) {
                $whereLocation[] = [
                    'id',
                    '=',
                    $data['location_id'],
                ];
            }

            $centersinfo = Locations::where([
                ['region_id', '=', $key],
                [$whereLocation],
                ['slug', '=', 'custom'],
            ])->get();

            if (count($centersinfo) > 0) {
                foreach ($centersinfo as $location) {
                    $revenuebreakup[$key]['centers'][$location->id] = [
                        'id' => $location->id,
                        'name' => $location->name,
                        'date' => [],
                    ];
                    for ($i = 0; $i <= $days; $i++) {
                        $revenuebreakup[$key]['centers'][$location->id]['date'][$i] = [
                            'Date' => $start->format('Y-m-d'),
                            'service' => [],
                        ];

                        $servicesinfo = LocationsWidget::loadEndServiceByLocation($location->id, Auth::User()->account_id);
                        $checkedsum = 0;
                        foreach ($servicesinfo as $service) {

                            /*Need to Finilize the users id*/
                            $users = DB::table('role_has_users')->where($where)->select('user_id')->get()->toArray();
                            $userids = [];
                            foreach ($users as $user) {
                                $userids[] = $user->user_id;
                            }
                            /*End to finilize the user id*/

                            if (count($userids) > 0) {
                                $revenueservicesum = \App\Models\Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                                    ->whereDate('invoices.created_at', '=', $start->format('Y-m-d'))
                                    ->where([
                                        ['invoices.location_id', '=', $location->id],
                                        ['invoice_details.service_id', $service],
                                        ['invoice_status_id', '=', $invoicepaid->id],
                                        ['invoices.account_id', '=', $account_id],
                                    ])
                                    ->select('invoice_details.net_amount')
                                    ->sum('invoice_details.net_amount');

                            } else {
                                $revenueservicesum = 0;
                            }
                            $userids = [];
                            $checkedsum += $revenueservicesum;
                            if ($revenueservicesum != 0) {
                                $revenuebreakup[$key]['centers'][$location->id]['date'][$i]['service'][$service] = [
                                    'service_id' => $service,
                                    'total' => $revenueservicesum,
                                ];
                            }
                        }
                        if ($checkedsum == 0) {
                            unset($revenuebreakup[$key]['centers'][$location->id]['date'][$i]);
                        }
                        $start = $start->addDay(1);
                    }
                    $start = Carbon::parse($start_date);
                }
            }
        }

        return $revenuebreakup;
    }
}
