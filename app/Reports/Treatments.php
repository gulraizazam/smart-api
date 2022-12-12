<?php

namespace App\Reports;

use App\Models\InvoiceStatuses;
use App\User;
use Config;
use App\Helpers\ACL;
use App\Models\Appointments;

class Treatments
{

    /**
     * Generate General Report
     * @param (mixed) $request
     * @return (mixed)
     */
    public static function clientcompletedtreatment($data) {

        $where = array();

        if(isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if(isset($data['patient_id']) && $data['patient_id']) {
            $where[] = array(
                'patient_id',
                '=',
                $data['patient_id']
            );
        }
        if(isset($data['city_id']) && $data['city_id']) {
            $where[] = array(
                'city_id',
                '=',
                $data['city_id']
            );
        }
        if(isset($data['region_id']) && $data['region_id']) {
            $where[] = array(
                'region_id',
                '=',
                $data['region_id']
            );
        }

        if (isset($data['location_id']) && $data['location_id']){
            $where[] = array(
                'location_id',
                '=',
                $data['location_id']
            );
        }

        $invoicestatus = InvoiceStatuses::where('slug','=','paid')->first();

        return Appointments::whereHas('hasInvoices', function ($query) use ($invoicestatus){
            $query->where('invoice_status_id',$invoicestatus->id);
        })
        ->whereDate('created_at','>=', $start_date)
        ->whereDate('created_at', '<=', $end_date)
        ->where($where)
        ->orderBy('created_at','asc')
        ->get();
    }

    public static function ClientWithNocompletedtreatment($data){
        $where = array();

        if(isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if(isset($data['patient_id']) && $data['patient_id']) {
            $where[] = array(
                'patient_id',
                '=',
                $data['patient_id']
            );
        }
        if(isset($data['city_id']) && $data['city_id']) {
            $where[] = array(
                'city_id',
                '=',
                $data['city_id']
            );
        }
        if(isset($data['region_id']) && $data['region_id']) {
            $where[] = array(
                'region_id',
                '=',
                $data['region_id']
            );
        }

        if (isset($data['location_id']) && $data['location_id']){
            $where[] = array(
                'location_id',
                '=',
                $data['location_id']
            );
        }

        $invoicestatus = InvoiceStatuses::where('slug','!=','paid')->pluck('id');

        return Appointments::whereDoesntHave('hasInvoices', function ($query) use ($invoicestatus){
            $query->whereIn('invoice_status_id',$invoicestatus);
        })
        ->whereDate('created_at','>=', $start_date)
        ->whereDate('created_at', '<=', $end_date)
        ->where($where)
        ->orderBy('created_at','desc')
        ->get();
    }


    public static function clientwithtreatmentsinparticularmonth($data)
    {
        $where = array();

        if(isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if(isset($data['patient_id']) && $data['patient_id']) {
            $where[] = array(
                'patient_id',
                '=',
                $data['patient_id']
            );
        }
        if(isset($data['city_id']) && $data['city_id']) {
            $where[] = array(
                'city_id',
                '=',
                $data['city_id']
            );
        }
        if(isset($data['region_id']) && $data['region_id']) {
            $where[] = array(
                'region_id',
                '=',
                $data['region_id']
            );
        }

        if (isset($data['location_id']) && $data['location_id']){
            $where[] = array(
                'location_id',
                '=',
                $data['location_id']
            );
        }

        return Appointments::whereDate('created_at','>=', $start_date)
        ->whereDate('created_at', '<=', $end_date)
        ->where($where)
        ->orderBy('created_at','desc')
        ->get();
    }

    /**
     * client With Birthday + X days Report
     * @param (mixed) $request
     * @return (mixed)
     */
    public static function clientswithbirthday($data){
        $where = array();
        if(isset($data['patient_id']) && $data['patient_id']) {
            $where[] = array(
                'users.id',
                '=',
                $data['patient_id']
            );
        }
        if(isset($data['city_id']) && $data['city_id']) {
            $where[] = array(
                'leads.city_id',
                '=',
                $data['city_id']
            );
        }
        if(isset($data['region_id']) && $data['region_id']) {
            $where[] = array(
                'leads.region_id',
                '=',
                $data['region_id']
            );
        }
        if(count($where)) {
            return User::leftjoin('leads','users.id','=','leads.patient_id')
                ->where('users.user_type_id','=',Config::get('constants.patient_id'))
                ->where($where)
                ->where(function ($query) {
                    $query->whereIn('leads.city_id', ACL::getUserCities());
                    $query->orWhereNull('leads.city_id');
                })
                ->select('*', 'leads.created_by as lead_created_by', 'leads.id as lead_id', 'leads.created_at as lead_created_at', 'users.id as PatientId')
                ->groupby('users.id')
                ->get();
        } else {
            return User::leftjoin('leads','users.id','=','leads.patient_id')
                ->where('users.user_type_id','=',Config::get('constants.patient_id'))
                ->where(function ($query) {
                    $query->whereIn('leads.city_id', ACL::getUserCities());
                    $query->orWhereNull('leads.city_id');
                })
                ->select('*', 'leads.created_by as lead_created_by', 'leads.id as lead_id', 'leads.created_at as lead_created_at', 'users.id as PatientId')
                ->groupby('users.id')
                ->get();
        }
    }
}
