<?php

namespace App\Reports;

use App\Models\InvoiceStatuses;
use App\Models\Locations;
use App\Models\RoleHasUsers;
use App\Models\Services;
use App\User;
use DB;
use Auth;
use App\Helpers\NodesTree;
use App\Models\Appointments;
use Illuminate\Support\Facades\Config;
use App\Helpers\GeneralFunctions;

class Incentive
{
    /**
     * Report for calculating Incentive
     * @param (mixed) $request
     * @return (mixed)
     */
    public static function reportsforcalculatingincentives($data)
    {
        $where = array();

        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if (isset($data['location_id']) && $data['location_id']) {
            if($data['search_type'] == 'doctor_id'){
                $users = User::join('doctor_has_locations', 'users.id', '=', 'doctor_has_locations.user_id')
                    ->where('doctor_has_locations.location_id', '=', $data['location_id'])
                    ->select('users.*')
                    ->get()->toArray();
            } else {
                $users = User::join('user_has_locations', 'users.id', '=', 'user_has_locations.user_id')
                    ->where('user_has_locations.location_id', '=', $data['location_id'])
                    ->select('users.*')
                    ->get()->toArray();
            }
        }


        $userrolearray = array();

        if (isset($data['role_id']) && $data['role_id']) {
            foreach ($users as $user) {
                $userrole = DB::table('role_has_users')->where([
                    ['role_id', '=', $data['role_id']],
                    ['user_id', '=', $user['id']]
                ])->get();
                if (count($userrole) > 0) {
                    $userrolearray[] = $user;
                }
            }
        }

        $userarrayf = array();

        if(isset($data['user_id']) && $data['user_id']){
            foreach ($userrolearray as $user){
                if($user['id'] == $data['user_id'])
                {
                    $userarrayf[] = $user;
                }
            }
        } else {
            $userarrayf = $userrolearray;
        }

        $invoicestatus = InvoiceStatuses::where('slug','=','paid')->first();

        $userreportarry = array();

        foreach ($userarrayf as $user){
            $user_appointment = Appointments::join('invoices','appointments.id','=','invoices.appointment_id')
                ->join('invoice_details','invoices.id','=','invoice_details.invoice_id')
                ->whereDate('appointments.created_at','>=', $start_date)
                ->whereDate('appointments.created_at','<=', $end_date)
                ->where('appointments.location_id','=',$data['location_id'])
                ->where('invoices.invoice_status_id','=',$invoicestatus->id)
                ->where('appointments.'.$data['search_type'],'=',$user['id'])
                ->sum('invoice_details.net_amount');

            $commission = ($user['commission']/100) * $user_appointment;

            $role = DB::table('roles')->where('id','=',$data['role_id'])->first();

            $location = Locations::where('id','=',$data['location_id'])->first();

            $userreportarry[$user['id']] = array(
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'phone' => GeneralFunctions::prepareNumber4Call($user['phone']),
                'gender' => ($user['gender'] == '1') ? 'Male' : 'Female',
                'Role' => $role->name,
                'Location' => $location->name,
                'City' => $location->city->name,
                'Region' => $location->region->name,
                'TotalRevenue' => $user_appointment,
                'commission' => $user['commission'],
                'Incentive' => $commission
            );
        }
        return $userreportarry;
    }

    /**
     * Report for calculating Incentive
     * @param (mixed) $request
     * @return (mixed)
     */
    public static function reportsforcalculatingincentivesdetail($data)
    {
        $where = array();

        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if (isset($data['location_id']) && $data['location_id']) {
            if($data['search_type'] == 'doctor_id'){
                $users = User::join('doctor_has_locations', 'users.id', '=', 'doctor_has_locations.user_id')
                    ->where('doctor_has_locations.location_id', '=', $data['location_id'])
                    ->select('users.*')
                    ->get()->toArray();
            } else {
                $users = User::join('user_has_locations', 'users.id', '=', 'user_has_locations.user_id')
                    ->where('user_has_locations.location_id', '=', $data['location_id'])
                    ->select('users.*')
                    ->get()->toArray();
            }

        }

        $userrolearray = array();

        if (isset($data['role_id']) && $data['role_id']) {
            foreach ($users as $user) {
                $userrole = DB::table('role_has_users')->where([
                    ['role_id', '=', $data['role_id']],
                    ['user_id', '=', $user['id']]
                ])->get();
                if (count($userrole) > 0) {
                    $userrolearray[] = $user;
                }
            }
        }

        $userarrayf = array();

        if(isset($data['user_id']) && $data['user_id']){
            foreach ($userrolearray as $user){
                if($user['id'] == $data['user_id'])
                {
                    $userarrayf[] = $user;
                }
            }
        } else {
            $userarrayf = $userrolearray;
        }

        $invoicestatus = InvoiceStatuses::where('slug','=','paid')->first();

        $userreportarry = array();

        foreach ($userarrayf as $user){

            $user_appointment = Appointments::join('invoices','appointments.id','=','invoices.appointment_id')
                ->join('invoice_details','invoices.id','=','invoice_details.invoice_id')
                ->whereDate('appointments.created_at','>=', $start_date)
                ->whereDate('appointments.created_at','<=', $end_date)
                ->where('appointments.location_id','=',$data['location_id'])
                ->where('invoices.invoice_status_id','=',$invoicestatus->id)
                ->where('appointments.'.$data['search_type'],'=',$user['id'])
                ->sum('invoice_details.net_amount');

            $user_appointment_info = Appointments::join('invoices','appointments.id','=','invoices.appointment_id')
                ->join('invoice_details','invoices.id','=','invoice_details.invoice_id')
                ->whereDate('appointments.created_at','>=', $start_date)
                ->whereDate('appointments.created_at','<=', $end_date)
                ->where('appointments.location_id','=',$data['location_id'])
                ->where('invoices.invoice_status_id','=',$invoicestatus->id)
                ->where('appointments.'.$data['search_type'],'=',$user['id'])
                ->select('invoice_details.*', 'invoices.*')->get()->toArray();

            $commission = ($user['commission']/100) * $user_appointment;

            $role = DB::table('roles')->where('id','=',$data['role_id'])->first();

            $location = Locations::where('id','=',$data['location_id'])->first();

            $userreportarry[$user['id']] = array(
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'phone' => GeneralFunctions::prepareNumber4Call($user['phone']),
                'gender' => ($user['gender'] == '1') ? 'Male' : 'Female',
                'Role' => $role->name,
                'Location' => $location->name,
                'City' => $location->city->name,
                'Region' => $location->region->name,
                'TotalRevenue' => $user_appointment,
                'commission' => $user['commission'],
                'Incentive' => $commission,
                'detail' => $user_appointment_info

            );
        }
        return $userreportarry;
    }

    /**
     * Revenue Generated by Operator Application User
     * @param (mixed) $request
     * @return (mixed)
     */
    public static function revenuegeneratedbyoperatorsapplicationuser($data)
    {
        $where = array();

        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if (isset($data['location_id']) && $data['location_id']) {
            $users = User::join('user_has_locations', 'users.id', '=', 'user_has_locations.user_id')
                ->where('user_has_locations.location_id', '=', $data['location_id'])
                ->select('users.*')
                ->get()->toArray();
        }

        $userarrayf = array();

        if(isset($data['application_user_id']) && $data['application_user_id']){
            foreach ($users as $user){
                if($user['id'] == $data['application_user_id'])
                {
                    $userarrayf[] = $user;
                }
            }
        } else {
            $userarrayf = $users;
        }

        $invoicestatus = InvoiceStatuses::where('slug','=','paid')->first();

        $userreportarry = array();

        foreach ($userarrayf as $user){

            $user_appointment = Appointments::join('invoices','appointments.id','=','invoices.appointment_id')
                ->join('invoice_details','invoices.id','=','invoice_details.invoice_id')
                ->whereDate('appointments.created_at','>=', $start_date)
                ->whereDate('appointments.created_at','<=', $end_date)
                ->where('appointments.location_id','=',$data['location_id'])
                ->where('invoices.invoice_status_id','=',$invoicestatus->id)
                ->where('appointments.'.$data['search_type'],'=',$user['id'])
                ->sum('invoice_details.net_amount');

            $commission = ($user['commission']/100) * $user_appointment;
            $location = Locations::where('id','=',$data['location_id'])->first();

            $userreportarry[$user['id']] = array(
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'phone' => GeneralFunctions::prepareNumber4Call($user['phone']),
                'gender' => ($user['gender'] == '1') ? 'Male' : 'Female',
                'Location' => $location->name,
                'City' => $location->city->name,
                'Region' => $location->region->name,
                'TotalRevenue' => $user_appointment,
                'commission' => $user['commission'],
                'Incentive' => $commission
            );
        }
        return $userreportarry;
    }

    /**
     * Revenue Generated by Consultant Doctor
     * @param (mixed) $request
     * @return (mixed)
     */
    public static function revenuegeneratedbyconsultantspractitioner($data)
    {
        $where = array();

        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if (isset($data['location_id']) && $data['location_id']) {
            $users = User::join('doctor_has_locations', 'users.id', '=', 'doctor_has_locations.user_id')
                ->where('doctor_has_locations.location_id', '=', $data['location_id'])
                ->select('users.*')
                ->get()->toArray();
        }

        $userarrayf = array();

        if(isset($data['practitioner_id']) && $data['practitioner_id']){
            foreach ($users as $user){
                if($user['id'] == $data['practitioner_id'])
                {
                    $userarrayf[] = $user;
                }
            }
        } else {
            $userarrayf = $users;
        }

        $invoicestatus = InvoiceStatuses::where('slug','=','paid')->first();

        $userreportarry = array();

        foreach ($userarrayf as $user){

            $user_appointment = Appointments::join('invoices','appointments.id','=','invoices.appointment_id')
                ->join('invoice_details','invoices.id','=','invoice_details.invoice_id')
                ->whereDate('appointments.created_at','>=', $start_date)
                ->whereDate('appointments.created_at','<=', $end_date)
                ->where('appointments.location_id','=',$data['location_id'])
                ->where('invoices.invoice_status_id','=',$invoicestatus->id)
                ->where('appointments.'.$data['search_type'],'=',$user['id'])
                ->sum('invoice_details.net_amount');

            $commission = ($user['commission']/100) * $user_appointment;
            $location = Locations::where('id','=',$data['location_id'])->first();

            $userreportarry[$user['id']] = array(
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'phone' => GeneralFunctions::prepareNumber4Call($user['phone']),
                'gender' => ($user['gender'] == '1') ? 'Male' : 'Female',
                'Location' => $location->name,
                'City' => $location->city->name,
                'Region' => $location->region->name,
                'TotalRevenue' => $user_appointment,
                'commission' => $user['commission'],
                'Incentive' => $commission
            );
        }
        return $userreportarry;
    }
}
