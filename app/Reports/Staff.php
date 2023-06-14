<?php
/**
 * Created by PhpStorm.
 * User: abdullah@redsignal.biz
 * Date: 11/15/2018
 * Time: 2:49 PM
 */

namespace App\Reports;

use App\Helpers\ACL;
use App\Helpers\GeneralFunctions;
use App\Models\Appointments;
use App\Models\AppointmentTypes;
use App\Models\Locations;
use App\Models\Regions;
use App\Models\Telecomprovidernumber;
use App\User;

class Staff
{
    public static function staffReports($data)
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
        if (isset($data['user_id']) && $data['user_id']) {
            $where[] = [
                'users.id',
                '=',
                $data['user_id'],
            ];
        }
        if (isset($data['email']) && $data['email']) {
            $where[] = [
                'users.email',
                'like',
                '%'.$data['email'].'%',
            ];
        }
        if (isset($data['gender_id']) && $data['gender_id']) {
            $where[] = [
                'users.gender',
                '=',
                $data['gender_id'],
            ];
        }
        if (isset($data['phone']) && $data['phone']) {
            $where[] = [
                'users.phone',
                'like',
                '%'.GeneralFunctions::cleanNumber($data['phone']).'%',
            ];
        }
        $usersList = [1, 2, 5]; // 'administrator_id' => 1,'application_user_id' => 2,'practitioner_id' => 5,'asthatic_operator_id' => 5,

        $users = User::where('id', '>', 1)->whereIn('user_type_id', $usersList);

        $resultQuery = $users->with(['user_has_locations', 'doctorhaslocation', 'doctorhaslocation.location']);

        if (isset($data['region_id']) && $data['region_id']) {
            $regionObj = Regions::where('id', $data['region_id'])->with(['locations' => function ($query) {
            }])->first(['id', 'name'])->toArray();
            $locationsList = [];
            foreach ($regionObj['locations'] as $region) {
                $locationsList[] = $region['id'];
            }
            $resultQuery = $users->whereHas('doctorhaslocation', function ($q) use ($locationsList) {
                $q->whereIn('location_id', $locationsList);
            });
        }
        if (isset($data['location_id']) && $data['location_id']) {
            $locationId = $data['location_id'];
            $resultQuery = $users->whereHas('doctorhaslocation', function ($q) use ($locationId) {
                $q->where('location_id', $locationId);
            });
        }
        if (isset($data['telecomprovider_id']) && $data['telecomprovider_id']) {
            //$telecomprovider = Telecomprovidernumber::whereIn('id',$data['telecomprovider_id'])->get();
            $telecomprovider = Telecomprovidernumber::whereIn('id', $data['telecomprovider_id'])->get();

            $newPrefix = [];
            foreach ($telecomprovider as $provider) {
                $newPrefix[] = ltrim($provider['pre_fix'], '0');
            }
            $y = 0;
            foreach ($newPrefix as $prefix) {
                $y++;
                if ($y == 1) {
                    $resultQuery->where('users.phone', 'like', $prefix.'%');
                } else {
                    $resultQuery->orWhere('users.phone', 'like', $prefix.'%');
                }
            }
        }
        if (count($where)) {
            $resultQuery->where($where);
        }
        $completeQuery = $resultQuery->select('*', 'users.id as lead_id', 'users.created_at as lead_created_at', 'users.id as UserId')->get();

        return $completeQuery;
    }

    /**
     * Centre performance stats by revenue
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function centerStaffPerformanceStatsByRevenue($data, $filters = [])
    {
        //dd($filters);
        //dd($data);
        $where = [];
        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if (isset($data['patient_id']) && $data['patient_id']) {
            $where[] = [
                'patient_id',
                '=',
                $data['patient_id'],
            ];
        }
        if (isset($data['appointment_type_id']) && $data['appointment_type_id']) {
            $where[] = [
                'appointment_type_id',
                '=',
                $data['appointment_type_id'],
            ];
        }
        if (isset($data['location_id']) && $data['location_id']) {
            $where[] = [
                'location_id',
                '=',
                $data['location_id'],
            ];
        }
        if (isset($data['service_id']) && $data['service_id']) {
            $where[] = [
                'service_id',
                '=',
                $data['service_id'],
            ];
        }
        if (isset($data['user_id']) && $data['user_id']) {
            $where[] = [
                'created_by',
                '=',
                $data['user_id'],
            ];
        }
        if (count($where)) {
            $records = Appointments::whereDate('created_at', '>=', $start_date)
                ->whereDate('created_at', '<=', $end_date)
                ->where($where)
                ->whereIn('location_id', ACL::getUserCentres())
                ->get();
        } else {
            $records = Appointments::whereDate('created_at', '>=', $start_date)
                ->whereDate('created_at', '<=', $end_date)
                ->whereIn('location_id', ACL::getUserCentres())
                ->with('doctor')
                ->get();
        }
        $data = [];
        $created_byArray = [];

        if ($records) {
            foreach ($records as $record) {
                if (! in_array($record->location_id, $created_byArray)) {
                    $created_byArray[] = $record->location_id;
                    $locationinfo = Locations::where('id', '=', $record->location_id)->first();
                    $data[$record->location_id] = [
                        'id' => $record->location_id,
                        'name' => $locationinfo->name,
                        'region' => (array_key_exists($locationinfo->region_id, $filters['regions'])) ? $filters['regions'][$record->region_id]->name : '',
                        'city' => (array_key_exists($locationinfo->city_id, $filters['cities'])) ? $filters['cities'][$record->city_id]->name : '',
                    ];
                    if (! isset($data[$record->location_id]['records'][$record->doctor_id]['doctor_id'])) {
                        $data[$record->location_id]['records'][$record->doctor_id]['patient_id'] = $record->patient_id;
                        $data[$record->location_id]['records'][$record->doctor_id]['doctor_id'] = $record->doctor->id;
                        $data[$record->location_id]['records'][$record->doctor_id]['name'] = $record->doctor->name;
                        $data[$record->location_id]['records'][$record->doctor_id]['email'] = $record->doctor->email;
                        $data[$record->location_id]['records'][$record->doctor_id]['centre'] = $locationinfo->name;
                        $data[$record->location_id]['records'][$record->doctor_id]['region'] = (array_key_exists($locationinfo->region_id, $filters['regions'])) ? $filters['regions'][$record->region_id]->name : '';
                        $data[$record->location_id]['records'][$record->doctor_id]['city'] = (array_key_exists($locationinfo->city_id, $filters['cities'])) ? $filters['cities'][$record->city_id]->name : '';
                    }
                    //$data[$record->location_id]['records'][$record->id] = $record;
                    $data[$record->location_id]['records'][$record->doctor_id]['appointments'][$record->id] = $record;
                } else {
                    if (! isset($data[$record->location_id]['records'][$record->doctor_id]['doctor_id'])) {
                        $data[$record->location_id]['records'][$record->doctor_id]['patient_id'] = $record->patient_id;
                        $data[$record->location_id]['records'][$record->doctor_id]['doctor_id'] = $record->doctor->id;
                        $data[$record->location_id]['records'][$record->doctor_id]['name'] = $record->doctor->name;
                        $data[$record->location_id]['records'][$record->doctor_id]['email'] = $record->doctor->email;
                        $data[$record->location_id]['records'][$record->doctor_id]['centre'] = $locationinfo->name;
                        $data[$record->location_id]['records'][$record->doctor_id]['region'] = (array_key_exists($locationinfo->region_id, $filters['regions'])) ? $filters['regions'][$record->region_id]->name : '';
                        $data[$record->location_id]['records'][$record->doctor_id]['city'] = (array_key_exists($locationinfo->city_id, $filters['cities'])) ? $filters['cities'][$record->city_id]->name : '';
                    }
                    //$data[$record->location_id]['records'][$record->id] = $record;
                    $data[$record->location_id]['records'][$record->doctor_id]['appointments'][$record->id] = $record;
                }
            }
        }
        //        dd($data);
        return $data;
    }

    /**
     * Centre performance stats by service type
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function centerPerformanceStatsByServices($data, $filters = [])
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
        if (isset($data['patient_id']) && $data['patient_id']) {
            $where[] = [
                'patient_id',
                '=',
                $data['patient_id'],
            ];
        }
        if (isset($data['appointment_type_id']) && $data['appointment_type_id']) {
            $where[] = [
                'appointment_type_id',
                '=',
                $data['appointment_type_id'],
            ];
        }
        if (isset($data['location_id']) && $data['location_id']) {
            $where[] = [
                'location_id',
                '=',
                $data['location_id'],
            ];
        }
        if (isset($data['service_id']) && $data['service_id']) {
            $where[] = [
                'service_id',
                '=',
                $data['service_id'],
            ];
        }
        if (isset($data['user_id']) && $data['user_id']) {
            $where[] = [
                'created_by',
                '=',
                $data['user_id'],
            ];
        }
        if (count($where)) {
            $records = Appointments::whereDate('created_at', '>=', $start_date)
                ->whereDate('created_at', '<=', $end_date)
                ->where($where)
                ->whereIn('location_id', ACL::getUserCentres())
                ->with('doctor')
                ->get();
        } else {
            $records = Appointments::whereDate('created_at', '>=', $start_date)
                ->whereDate('created_at', '<=', $end_date)
                ->whereIn('location_id', ACL::getUserCentres())
                ->with('doctor')
                ->get();
        }
        $data = [];
        $created_byArray = [];

        if ($records) {
            foreach ($records as $record) {
                //dd($record);
                $created_byArray = [];
                $locationinfo = Locations::where('id', '=', $record->location_id)->first();
                if (! in_array($record->appointment_type_id, $created_byArray)) {
                    $created_byArray[] = $record->appointment_type_id;
                    $appointmenttype = AppointmentTypes::find($record->appointment_type_id);
                    $data[$record->appointment_type_id] = [
                        'name' => $appointmenttype->name,
                    ];
                    //$data[$record->appointment_type_id]['records'][$record->id] = $record;
                    $data[$record->appointment_type_id]['records'][$record->doctor->id]['doctor_id'] = $record->doctor->id;
                    $data[$record->appointment_type_id]['records'][$record->doctor_id]['name'] = $record->doctor->name;
                    $data[$record->appointment_type_id]['records'][$record->doctor_id]['email'] = $record->doctor->email;
                    $data[$record->appointment_type_id]['records'][$record->doctor_id]['centre'] = $locationinfo->name;
                    $data[$record->appointment_type_id]['records'][$record->doctor_id]['region'] = (array_key_exists($locationinfo->region_id, $filters['regions'])) ? $filters['regions'][$record->region_id]->name : '';
                    $data[$record->appointment_type_id]['records'][$record->doctor_id]['city'] = (array_key_exists($locationinfo->city_id, $filters['cities'])) ? $filters['cities'][$record->city_id]->name : '';
                    $data[$record->appointment_type_id]['records'][$record->doctor->id]['appointments'][$record->id] = $record;
                } else {
                    //$data[$record->appointment_type_id]['records'][$record->doctor->id][$record->id] = $record;
                    $data[$record->appointment_type_id]['records'][$record->doctor->id]['doctor_id'] = $record->doctor->id;
                    $data[$record->appointment_type_id]['records'][$record->doctor_id]['name'] = $record->doctor->name;
                    $data[$record->appointment_type_id]['records'][$record->doctor_id]['email'] = $record->doctor->email;
                    $data[$record->appointment_type_id]['records'][$record->doctor_id]['centre'] = $locationinfo->name;
                    $data[$record->appointment_type_id]['records'][$record->doctor_id]['region'] = (array_key_exists($locationinfo->region_id, $filters['regions'])) ? $filters['regions'][$record->region_id]->name : '';
                    $data[$record->appointment_type_id]['records'][$record->doctor_id]['city'] = (array_key_exists($locationinfo->city_id, $filters['cities'])) ? $filters['cities'][$record->city_id]->name : '';
                    $data[$record->appointment_type_id]['records'][$record->doctor->id]['appointments'][$record->id] = $record;
                }
            }
        }

        return $data;
    }
}
