<?php

namespace App\Reports;

use App\Helpers\ACL;
use App\Helpers\GeneralFunctions;
use App\Models\Appointmentimage;
use App\Models\AppointmentStatuses;
use App\Models\InvoiceStatuses;
use App\Models\Measurement;
use App\Models\Medical;
use App\Models\Patients;
use App\Models\Services;
use DB;
use Illuminate\Support\Facades\Auth;

class Appointments
{
    /**
     * Generate General Report
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function getGeneralReport($data, $account_id)
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

        if (isset($data['scheduled_date']) && $data['scheduled_date']) {
            $scheduled_date = explode(' - ', $data['scheduled_date']);
            $start_scheduled_date = date('Y-m-d', strtotime($scheduled_date[0]));
            $end_scheduled_date = date('Y-m-d', strtotime($scheduled_date[1]));
        } else {
            $start_scheduled_date = null;
            $end_scheduled_date = null;
        }

        if (isset($data['patient_id']) && $data['patient_id']) {
            $where['appointments.patient_id'] = $data['patient_id'];
        }

        if (isset($data['doctor_id']) && $data['doctor_id']) {
            $where['appointments.doctor_id'] = $data['doctor_id'];
        }

        if (isset($data['city_id']) && $data['city_id']) {
            $where['appointments.city_id'] = $data['city_id'];
        }

        if (isset($data['region_id']) && $data['region_id']) {
            $where['appointments.region_id'] = $data['region_id'];
        }

        if (isset($data['location_id']) && $data['location_id']) {
            $where['appointments.location_id'] = $data['location_id'];
        }

        if (isset($data['service_id']) && $data['service_id']) {
            $where['appointments.service_id'] = $data['service_id'];
        }

        if (isset($data['appointment_status_id']) && $data['appointment_status_id']) {
            $where['appointments.base_appointment_status_id'] = $data['appointment_status_id'];
        }

        if (isset($data['appointment_type_id']) && $data['appointment_type_id']) {
            $where['appointments.appointment_type_id'] = $data['appointment_type_id'];
        }

        if (isset($data['consultancy_type']) && $data['consultancy_type']) {
            $where['appointments.consultancy_type'] = $data['consultancy_type'];
        }

        if (isset($data['user_id']) && $data['user_id']) {
            $where['appointments.created_by'] = $data['user_id'];
        }

        if (isset($data['up_user_id']) && $data['up_user_id']) {
            $where['appointments.converted_by'] = $data['up_user_id'];
        }

        if (isset($data['re_user_id']) && $data['re_user_id']) {
            $where['appointments.updated_by'] = $data['re_user_id'];
        }

        if (isset($data['referred_by']) && $data['referred_by']) {
            $where['users.referred_by'] = $data['referred_by'];
        }

        $where['appointments.account_id'] = $account_id;

        return \App\Models\Appointments::join('users', 'users.id', '=', 'appointments.patient_id')
            ->whereDate('appointments.'.$data['date_range_by'], '>=', $start_date)
            ->whereDate('appointments.'.$data['date_range_by'], '<=', $end_date)
            ->where($where)
            ->whereIn('appointments.location_id', ACL::getUserCentres())
            ->select('appointments.*', 'users.referred_by')
            ->get();
    }

    /**
     * Generate Staff Appointment Report
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function getStaffAppointmentScheduleReport($data, $filters = [])
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

        if (isset($data['scheduled_date']) && $data['scheduled_date']) {
            $scheduled_date = explode(' - ', $data['scheduled_date']);
            $start_scheduled_date = date('Y-m-d', strtotime($scheduled_date[0]));
            $end_scheduled_date = date('Y-m-d', strtotime($scheduled_date[1]));
        } else {
            $start_scheduled_date = null;
            $end_scheduled_date = null;
        }

        if (isset($data['patient_id']) && $data['patient_id']) {
            $where['patient_id'] = $data['patient_id'];
        }

        if (isset($data['doctor_id']) && $data['doctor_id']) {
            $where['doctor_id'] = $data['doctor_id'];
        }

        if (isset($data['city_id']) && $data['city_id']) {
            $where['city_id'] = $data['city_id'];
        }

        if (isset($data['region_id']) && $data['region_id']) {
            $where['region_id'] = $data['region_id'];
        }

        if (isset($data['location_id']) && $data['location_id']) {
            $where['location_id'] = $data['location_id'];
        }

        if (isset($data['service_id']) && $data['service_id']) {
            $where['service_id'] = $data['service_id'];
        }

        if (isset($data['appointment_status_id']) && $data['appointment_status_id']) {
            $where['base_appointment_status_id'] = $data['appointment_status_id'];
        }

        if (isset($data['appointment_type_id']) && $data['appointment_type_id']) {
            $where['appointment_type_id'] = $data['appointment_type_id'];
        }

        if (isset($data['consultancy_type']) && $data['consultancy_type']) {
            $where['appointments.consultancy_type'] = $data['consultancy_type'];
        }

        if (isset($data['user_id']) && $data['user_id']) {
            $where['created_by'] = $data['user_id'];
        }

        if (isset($data['up_user_id']) && $data['up_user_id']) {
            $where['converted_by'] = $data['up_user_id'];
        }

        if (isset($data['re_user_id']) && $data['re_user_id']) {
            $where['updated_by'] = $data['re_user_id'];
        }

        if (count($where)) {
            $recods = \App\Models\Appointments::whereDate($data['date_range_by'], '>=', $start_date)
                ->whereDate($data['date_range_by'], '<=', $end_date)
                ->where($where)
                ->whereIn('location_id', ACL::getUserCentres())
                ->get();
        } else {
            $recods = \App\Models\Appointments::whereDate($data['date_range_by'], '>=', $start_date)
                ->whereDate($data['date_range_by'], '<=', $end_date)
                ->whereIn('location_id', ACL::getUserCentres())
                ->get();
        }
        $data = [];
        $created_byArray = [];

        if ($recods) {
            foreach ($recods as $recod) {
                if (! in_array($recod->created_by, $created_byArray)) {
                    $created_byArray[] = $recod->created_by;
                    $data[$recod->created_by] = [
                        'id' => $recod->created_by,
                        'name' => (array_key_exists($recod->created_by, $filters['users'])) ? $filters['users'][$recod->created_by]->name : '',
                    ];
                    $data[$recod->created_by]['records'][$recod->id] = $recod;
                } else {
                    $data[$recod->created_by]['records'][$recod->id] = $recod;
                }
            }
        }

        return $data;
    }

    /**
     * Generate Staff (Referred By) Appointment Report
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function getStaffReferredByAppointmentScheduleReport($data, $filters, $account_id)
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

        if (isset($data['scheduled_date']) && $data['scheduled_date']) {
            $scheduled_date = explode(' - ', $data['scheduled_date']);
            $start_scheduled_date = date('Y-m-d', strtotime($scheduled_date[0]));
            $end_scheduled_date = date('Y-m-d', strtotime($scheduled_date[1]));
        } else {
            $start_scheduled_date = null;
            $end_scheduled_date = null;
        }

        if (isset($data['patient_id']) && $data['patient_id']) {
            $where['appointments.patient_id'] = $data['patient_id'];
        }

        if (isset($data['doctor_id']) && $data['doctor_id']) {
            $where['appointments.doctor_id'] = $data['doctor_id'];
        }

        if (isset($data['city_id']) && $data['city_id']) {
            $where['appointments.city_id'] = $data['city_id'];
        }

        if (isset($data['region_id']) && $data['region_id']) {
            $where['appointments.region_id'] = $data['region_id'];
        }

        if (isset($data['location_id']) && $data['location_id']) {
            $where['appointments.location_id'] = $data['location_id'];
        }

        if (isset($data['service_id']) && $data['service_id']) {
            $where['appointments.service_id'] = $data['service_id'];
        }

        if (isset($data['appointment_status_id']) && $data['appointment_status_id']) {
            $where['appointments.base_appointment_status_id'] = $data['appointment_status_id'];
        }

        if (isset($data['appointment_type_id']) && $data['appointment_type_id']) {
            $where['appointments.appointment_type_id'] = $data['appointment_type_id'];
        }

        if (isset($data['consultancy_type']) && $data['consultancy_type']) {
            $where['appointments.consultancy_type'] = $data['consultancy_type'];
        }

        if (isset($data['user_id']) && $data['user_id']) {
            $where['appointments.created_by'] = $data['user_id'];
        }

        if (isset($data['up_user_id']) && $data['up_user_id']) {
            $where['appointments.converted_by'] = $data['up_user_id'];
        }

        if (isset($data['re_user_id']) && $data['re_user_id']) {
            $where['appointments.updated_by'] = $data['re_user_id'];
        }

        if (isset($data['referred_by']) && $data['referred_by']) {
            $where['users.referred_by'] = $data['referred_by'];
        }

        $where['appointments.account_id'] = $account_id;

        $recods = \App\Models\Appointments::join('users', 'users.id', '=', 'appointments.patient_id')
            ->whereDate('appointments.'.$data['date_range_by'], '>=', $start_date)
            ->whereDate('appointments.'.$data['date_range_by'], '<=', $end_date)
            ->where($where)
            ->whereNotNull('users.referred_by')
            ->whereIn('appointments.location_id', ACL::getUserCentres())
            ->select('appointments.*', 'users.referred_by')
            ->get();

        $data = [];
        $created_byArray = [];

        if ($recods) {
            foreach ($recods as $recod) {
                if (! in_array($recod->referred_by, $created_byArray)) {
                    $created_byArray[] = $recod->referred_by;
                    $data[$recod->referred_by] = [
                        'id' => $recod->created_by,
                        'name' => (array_key_exists($recod->referred_by, $filters['users'])) ? $filters['users'][$recod->referred_by]->name : '',
                    ];
                    $data[$recod->referred_by]['records'][$recod->id] = $recod;
                } else {
                    $data[$recod->referred_by]['records'][$recod->id] = $recod;
                }
            }
        }

        return $data;
    }

    /**
     * Generate Employee Appointment Summary Report
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function getEmployeeAppointmentSummaryReport($data, $filters, $account_id)
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

        if (isset($data['scheduled_date']) && $data['scheduled_date']) {
            $scheduled_date = explode(' - ', $data['scheduled_date']);
            $start_scheduled_date = date('Y-m-d', strtotime($scheduled_date[0]));
            $end_scheduled_date = date('Y-m-d', strtotime($scheduled_date[1]));
        } else {
            $start_scheduled_date = null;
            $end_scheduled_date = null;
        }

        if (isset($data['patient_id']) && $data['patient_id']) {
            $where['appointments.patient_id'] = $data['patient_id'];
        }

        if (isset($data['doctor_id']) && $data['doctor_id']) {
            $where['appointments.doctor_id'] = $data['doctor_id'];
        }

        if (isset($data['city_id']) && $data['city_id']) {
            $where['appointments.city_id'] = $data['city_id'];
        }

        if (isset($data['region_id']) && $data['region_id']) {
            $where['appointments.region_id'] = $data['region_id'];
        }

        if (isset($data['location_id']) && $data['location_id']) {
            $where['appointments.location_id'] = $data['location_id'];
        }

        if (isset($data['service_id']) && $data['service_id']) {
            $where['appointments.service_id'] = $data['service_id'];
        }

        if (isset($data['appointment_status_id']) && $data['appointment_status_id']) {
            $where['appointments.base_appointment_status_id'] = $data['appointment_status_id'];
        }

        if (isset($data['appointment_type_id']) && $data['appointment_type_id']) {
            $where['appointments.appointment_type_id'] = $data['appointment_type_id'];
        }

        if (isset($data['consultancy_type']) && $data['consultancy_type']) {
            $where['appointments.consultancy_type'] = $data['consultancy_type'];
        }

        if (isset($data['user_id']) && $data['user_id']) {
            $where['appointments.created_by'] = $data['user_id'];
        }

        if (isset($data['referred_by']) && $data['referred_by']) {
            $where['users.referred_by'] = $data['referred_by'];
        }

        $where['appointments.account_id'] = $account_id;

        $recods = \App\Models\Appointments::join('users', 'users.id', '=', 'appointments.patient_id')
            ->whereDate('appointments.'.$data['date_range_by'], '>=', $start_date)
            ->whereDate('appointments.'.$data['date_range_by'], '<=', $end_date)
            ->whereIn('appointments.location_id', ACL::getUserCentres())
            ->where($where)
            ->select('appointments.*', 'users.referred_by')
            ->get();

        $data = [];
        $created_byArray = [];

        if ($recods) {
            foreach ($recods as $recod) {
                if (! in_array($recod->created_by, $created_byArray)) {
                    $created_byArray[] = $recod->created_by;
                    $data[$recod->created_by] = [
                        'id' => $recod->created_by,
                        'name' => (array_key_exists($recod->created_by, $filters['users'])) ? $filters['users'][$recod->created_by]->name : '',
                    ];
                    $data[$recod->created_by]['records'][$recod->id] = $recod;
                } else {
                    $data[$recod->created_by]['records'][$recod->id] = $recod;
                }
            }
        }

        return $data;
    }

    /**
     * Generate Appointment Summary by Status Report
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function getAppointmentSummaryByServiceReport($data, $filters, $account_id)
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
            $where['appointments.patient_id'] = $data['patient_id'];
        }

        if (isset($data['doctor_id']) && $data['doctor_id']) {
            $where['appointments.doctor_id'] = $data['doctor_id'];
        }

        if (isset($data['city_id']) && $data['city_id']) {
            $where['appointments.city_id'] = $data['city_id'];
        }

        if (isset($data['region_id']) && $data['region_id']) {
            $where['appointments.region_id'] = $data['region_id'];
        }

        if (isset($data['location_id']) && $data['location_id']) {
            $where['appointments.location_id'] = $data['location_id'];
        }

        if (isset($data['service_id']) && $data['service_id']) {
            $where['appointments.service_id'] = $data['service_id'];
        }

        if (isset($data['appointment_status_id']) && $data['appointment_status_id']) {
            $where['appointments.base_appointment_status_id'] = $data['appointment_status_id'];
        }

        if (isset($data['appointment_type_id']) && $data['appointment_type_id']) {
            $where['appointments.appointment_type_id'] = $data['appointment_type_id'];
        }

        if (isset($data['consultancy_type']) && $data['consultancy_type']) {
            $where['appointments.consultancy_type'] = $data['consultancy_type'];
        }

        if (isset($data['user_id']) && $data['user_id']) {
            $where['appointments.created_by'] = $data['user_id'];
        }

        if (isset($data['referred_by']) && $data['referred_by']) {
            $where['users.referred_by'] = $data['referred_by'];
        }

        $where['appointments.account_id'] = $account_id;

        $recods = \App\Models\Appointments::join('users', 'users.id', '=', 'appointments.patient_id')
            ->whereDate('appointments.'.$data['date_range_by_first'], '>=', $start_date)
            ->whereDate('appointments.'.$data['date_range_by_first'], '<=', $end_date)
            ->where($where)
            ->whereIn('appointments.location_id', ACL::getUserCentres())
            ->select('appointments.region_id', 'appointments.location_id', 'appointments.service_id', DB::raw('COUNT(appointments.id) as total_appointments'))
            ->groupBy('appointments.region_id', 'appointments.location_id', 'appointments.service_id')
            ->get();

        $data = [];

        if ($recods->count()) {
            foreach ($recods as $recod) {

                if (! array_key_exists($recod->region_id, $data)) {
                    $data[$recod->region_id] = [
                        'id' => $recod->region_id,
                        'name' => $filters['regions'][$recod->region_id]->name,
                        'centres' => [],
                    ];
                }

                if (! array_key_exists($recod->location_id, $data[$recod->region_id]['centres'])) {
                    $data[$recod->region_id]['centres'][$recod->location_id] = [
                        'id' => $recod->region_id,
                        'name' => $filters['locations'][$recod->location_id]->name,
                        'services' => [],
                    ];
                }

                if (! array_key_exists($recod->service_id, $data[$recod->region_id]['centres'][$recod->location_id]['services'])) {
                    $data[$recod->region_id]['centres'][$recod->location_id]['services'][$recod->service_id] = [
                        'id' => $recod->service_id,
                        'name' => $filters['services'][$recod->service_id]->name,
                        'total_appointments' => $recod->total_appointments,
                    ];
                }

            }
        }

        return $data;
    }

    /**
     * Generate Appointment Summary by Status Report
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function getAppointmentSummaryByStatusReport($data, $filters, $account_id)
    {
        $where = [];
        $ids = [];

        if (isset($data['date_range']) && $data['date_range']) {
            $date_range = explode(' - ', $data['date_range']);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if (isset($data['patient_id']) && $data['patient_id']) {
            $where['appointments.patient_id'] = $data['patient_id'];
        }
        if (isset($data['doctor_id']) && $data['doctor_id']) {
            $where['appointments.doctor_id'] = $data['doctor_id'];
        }
        if (isset($data['city_id']) && $data['city_id']) {
            $where['appointments.city_id'] = $data['city_id'];
        }
        if (isset($data['region_id']) && $data['region_id']) {
            $where['appointments.region_id'] = $data['region_id'];
        }
        if (isset($data['location_id']) && $data['location_id']) {
            $where['appointments.location_id'] = $data['location_id'];
        }
        if (isset($data['appointment_status_id']) && $data['appointment_status_id']) {
            $where['appointments.base_appointment_status_id'] = $data['appointment_status_id'];
        }
        if (isset($data['user_id']) && $data['user_id']) {
            $where['appointments.created_by'] = $data['user_id'];
        }
        if (isset($data['referred_by']) && $data['referred_by']) {
            $where['users.referred_by'] = $data['referred_by'];
        }
        if (isset($data['consultancy_type']) && $data['consultancy_type']) {
            $where['appointments.consultancy_type'] = $data['consultancy_type'];
        }
        if (isset($data['appointment_type_id']) && $data['appointment_type_id']) {
            if ($data['appointment_type_id'] == config('constants.appointment_type_service')) {
                if (isset($data['service_id']) && $data['service_id']) {
                    $service_info = Services::find($data['service_id']);
                    if (! $service_info->parent_id && ! $service_info->end_node && $service_info->active == '1') {
                        $services = \App\Models\Appointments::getNodeServices($data['service_id'], Auth::User()->account_id, true, true);
                        if (count($services) > 1) {
                            foreach ($services as $key => $service) {
                                $ids[] = $key;
                            }
                            array_shift($ids);
                        } else {
                            $where['appointments.service_id'] = $data['service_id'];
                        }
                        $where['appointments.appointment_type_id'] = $data['appointment_type_id'];
                    } else {
                        $where['appointments.appointment_type_id'] = $data['appointment_type_id'];
                        if (isset($data['service_id']) && $data['service_id']) {
                            $where['appointments.service_id'] = $data['service_id'];
                        }
                    }
                } else {
                    $where['appointments.appointment_type_id'] = $data['appointment_type_id'];
                    if (isset($data['service_id']) && $data['service_id']) {
                        $where['appointments.service_id'] = $data['service_id'];
                    }
                }
            } else {
                $where['appointments.appointment_type_id'] = $data['appointment_type_id'];
                if (isset($data['service_id']) && $data['service_id']) {
                    $where['appointments.service_id'] = $data['service_id'];
                }
            }
        } else {
            if (isset($data['service_id']) && $data['service_id']) {
                $where['appointments.service_id'] = $data['service_id'];
            }
        }
        $where['appointments.account_id'] = $account_id;
        if (count($ids)) {
            $recods = \App\Models\Appointments::join('users', 'users.id', '=', 'appointments.patient_id')
                ->whereDate('appointments.'.$data['date_range_by'], '>=', $start_date)
                ->whereDate('appointments.'.$data['date_range_by'], '<=', $end_date)
                ->where($where)
                ->whereIn('appointments.service_id', $ids)
                ->whereIn('appointments.location_id', ACL::getUserCentres())
                ->select('appointments.region_id', 'appointments.location_id', 'appointments.base_appointment_status_id as appointment_status_id', DB::raw('COUNT(appointments.id) as total_appointments'))
                ->groupBy('appointments.region_id', 'appointments.location_id', 'appointments.base_appointment_status_id')
                ->get();
        } else {
            $recods = \App\Models\Appointments::join('users', 'users.id', '=', 'appointments.patient_id')
                ->whereDate('appointments.'.$data['date_range_by'], '>=', $start_date)
                ->whereDate('appointments.'.$data['date_range_by'], '<=', $end_date)
                ->where($where)
                ->whereIn('appointments.location_id', ACL::getUserCentres())
                ->select('appointments.region_id', 'appointments.location_id', 'appointments.base_appointment_status_id as appointment_status_id', DB::raw('COUNT(appointments.id) as total_appointments'))
                ->groupBy('appointments.region_id', 'appointments.location_id', 'appointments.base_appointment_status_id')
                ->get();
        }

        $report = [];

        $appointment_statuses = AppointmentStatuses::getAllParentRecords($account_id)->pluck('id')->toArray();

        if ($recods->count()) {
            foreach ($recods as $recod) {

                if (! array_key_exists($recod->region_id, $report)) {
                    $report[$recod->region_id] = [
                        'id' => $recod->region_id,
                        'name' => $filters['regions'][$recod->region_id]->name,
                        'centres' => [],
                    ];
                }

                if (! array_key_exists($recod->location_id, $report[$recod->region_id]['centres'])) {
                    $report[$recod->region_id]['centres'][$recod->location_id] = [
                        'id' => $recod->region_id,
                        'name' => $filters['locations'][$recod->location_id]->name,
                        'appointment_statuses' => [],
                    ];
                }

                if (! array_key_exists($recod->appointment_status_id, $report[$recod->region_id]['centres'][$recod->location_id]['appointment_statuses'])) {
                    $report[$recod->region_id]['centres'][$recod->location_id]['appointment_statuses'][$recod->appointment_status_id] = [
                        'id' => $recod->appointment_status_id,
                        'name' => $filters['appointment_statuses'][$recod->appointment_status_id]->name,
                        'total_appointments' => 0,
                    ];
                }
                if (array_key_exists($recod->appointment_status_id, $report[$recod->region_id]['centres'][$recod->location_id]['appointment_statuses'])) {
                    $report[$recod->region_id]['centres'][$recod->location_id]['appointment_statuses'][$recod->appointment_status_id]['total_appointments'] = $recod->total_appointments;
                }

            }
        }
        $ids = [];
        if (! isset($data['appointment_status_id']) && $data['appointment_status_id'] == '') {
            foreach ($report as $region_id => $regions_data) {
                foreach ($regions_data['centres'] as $location_id => $centre_data) {
                    foreach ($centre_data['appointment_statuses'] as $status_id => $status_data) {

                        $ids[] = $status_id;
                    }
                    $remaning_statuses = array_diff($appointment_statuses, $ids);

                    foreach ($remaning_statuses as $status) {

                        $app_status = AppointmentStatuses::find($status);

                        $report[$region_id]['centres'][$location_id]['appointment_statuses'][$app_status->id] = [
                            'id' => $app_status->id,
                            'name' => $app_status->name,
                            'total_appointments' => 0,
                        ];

                    }
                }
            }
        }

        return $report;
    }

    /**
     * Generate General Report
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function getClientByAppointmentStatusReport($data, $filters, $account_id)
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

        if (isset($data['scheduled_date']) && $data['scheduled_date']) {
            $scheduled_date = explode(' - ', $data['scheduled_date']);
            $start_scheduled_date = date('Y-m-d', strtotime($scheduled_date[0]));
            $end_scheduled_date = date('Y-m-d', strtotime($scheduled_date[1]));
        } else {
            $start_scheduled_date = null;
            $end_scheduled_date = null;
        }

        if (isset($data['patient_id']) && $data['patient_id']) {
            $where['appointments.patient_id'] = $data['patient_id'];
        }

        if (isset($data['doctor_id']) && $data['doctor_id']) {
            $where['appointments.doctor_id'] = $data['doctor_id'];
        }

        if (isset($data['city_id']) && $data['city_id']) {
            $where['appointments.city_id'] = $data['city_id'];
        }

        if (isset($data['region_id']) && $data['region_id']) {
            $where['appointments.region_id'] = $data['region_id'];
        }

        if (isset($data['location_id']) && $data['location_id']) {
            $where['appointments.location_id'] = $data['location_id'];
        }

        if (isset($data['service_id']) && $data['service_id']) {
            $where['appointments.service_id'] = $data['service_id'];
        }

        if (isset($data['appointment_status_id']) && $data['appointment_status_id']) {
            $where['appointments.base_appointment_status_id'] = $data['appointment_status_id'];
        }

        if (isset($data['appointment_type_id']) && $data['appointment_type_id']) {
            $where['appointments.appointment_type_id'] = $data['appointment_type_id'];
        }

        if (isset($data['consultancy_type']) && $data['consultancy_type']) {
            $where['appointments.consultancy_type'] = $data['consultancy_type'];
        }

        if (isset($data['user_id']) && $data['user_id']) {
            $where['appointments.created_by'] = $data['user_id'];
        }

        if (isset($data['referred_by']) && $data['referred_by']) {
            $where['users.referred_by'] = $data['referred_by'];
        }

        $where['appointments.account_id'] = $account_id;

        $records = \App\Models\Appointments::join('users', 'users.id', '=', 'appointments.patient_id')
            ->whereDate('appointments.'.$data['date_range_by'], '>=', $start_date)
            ->whereDate('appointments.'.$data['date_range_by'], '<=', $end_date)
            ->where($where)
            ->whereIn('appointments.location_id', ACL::getUserCentres())
            ->select('appointments.id', 'appointments.patient_id', 'appointments.scheduled_date', 'appointments.doctor_id', 'appointments.region_id', 'appointments.city_id', 'appointments.location_id', 'appointments.appointment_status_id', 'appointments.appointment_type_id', 'appointments.created_at', 'appointments.created_by', 'users.referred_by', 'appointments.consultancy_type', DB::raw('DATE(appointments.created_at) as created_date'))
            ->get();

        $report = [];

        $dates = array_reverse(self::date_range($start_date, $end_date, '+1 day', 'Y-m-d'));

        if ($records->count()) {
            /*
             * Prepare Dates data for mapping
             */
            $date_structured = [];
            foreach ($dates as $single_date) {
                $date_structured[$single_date] = [
                    'date' => $single_date,
                    'appointments' => [],
                ];
            }

            /*
             * Fetch Patients records
             */
            $patients_array = [];
            foreach ($records as $recod) {
                $patients_array[] = $recod->patient_id;
            }
            $patients = Patients::whereIn('id', array_unique($patients_array))->select('id', 'name', 'email', 'phone')->get()->keyBy('id');

            foreach ($records as $recod) {

                if (! array_key_exists($recod->region_id, $report)) {
                    $report[$recod->region_id] = [
                        'id' => $recod->region_id,
                        'name' => $filters['regions'][$recod->region_id]->name,
                        'centres' => [],
                    ];
                }

                if (! array_key_exists($recod->location_id, $report[$recod->region_id]['centres'])) {
                    $report[$recod->region_id]['centres'][$recod->location_id] = [
                        'id' => $recod->region_id,
                        'name' => $filters['locations'][$recod->location_id]->name,
                        'dates' => $date_structured,
                    ];
                }

                if (
                    array_key_exists($recod->region_id, $report) &&
                    array_key_exists($recod->location_id, $report[$recod->region_id]['centres']) &&
                    array_key_exists($recod->created_date, $report[$recod->region_id]['centres'][$recod->location_id]['dates'])
                ) {

                    if ($recod->consultancy_type == 'in_person') {
                        $consultancy_type = 'In Person';
                    } elseif ($recod->consultancy_type == 'virtual') {
                        $consultancy_type = 'Virtual';
                    } else {
                        $consultancy_type = '';
                    }

                    $report[$recod->region_id]['centres'][$recod->location_id]['dates'][$recod->created_date]['appointments'][$recod->id] = [
                        'id' => $recod->id,
                        'patient_id' => $patients[$recod->patient_id]->id,
                        'name' => $patients[$recod->patient_id]->name,
                        'phone' => GeneralFunctions::prepareNumber($patients[$recod->patient_id]->phone),
                        'email' => ($patients[$recod->patient_id]->email) ? $patients[$recod->patient_id]->email : '-',
                        'scheduled_date' => $recod->scheduled_date,
                        'doctor_name' => $filters['doctors'][$recod->doctor_id]->name,
                        'appointment_type_name' => $filters['appointment_types'][$recod->appointment_type_id]->name,
                        'consultancy_type' => $consultancy_type,
                        'appointment_status_name' => $filters['appointment_statuses'][$recod->appointment_status_id]->name,
                        'created_by_name' => $filters['users'][$recod->created_by]->name,
                        'referred_by_name' => array_key_exists($recod->referred_by, $filters['users']) ? $filters['users'][$recod->referred_by]->name : '',
                    ];
                }
            }
        }

        return $report;
    }

    /**
     * Creating date collection between two dates
     *
     * <code>
     * <?php
     * # Example 1
     * date_range("2014-01-01", "2014-01-20", "+1 day", "m/d/Y");
     *
     * # Example 2. you can use even time
     * date_range("01:00:00", "23:00:00", "+1 hour", "H:i:s");
     * </code>
     *
     * @author Ali OYGUR <alioygur@gmail.com>
     *
     * @param string since any date, time or datetime format
     * @param string until any date, time or datetime format
     * @param string step
     * @param string date of output format
     * @param string make format as key
     * @return array
     */
    private static function date_range($first, $last, $step = '+1 day', $output_format = 'd/m/Y', $index_format = false)
    {

        $dates = [];
        $current = strtotime($first);
        $last = strtotime($last);

        while ($current <= $last) {

            if ($index_format) {
                $dates[date($output_format, $current)] = date($output_format, $current);
            } else {
                $dates[] = date($output_format, $current);
            }
            $current = strtotime($step, $current);
        }

        return $dates;
    }

    /**
     * Generate Compliance Report
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function complianceReport($data, $account_id)
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
            $where['appointments.patient_id'] = $data['patient_id'];
        }

        if (isset($data['doctor_id']) && $data['doctor_id']) {
            $where['appointments.doctor_id'] = $data['doctor_id'];
        }

        if (isset($data['city_id']) && $data['city_id']) {
            $where['appointments.city_id'] = $data['city_id'];
        }

        if (isset($data['region_id']) && $data['region_id']) {
            $where['appointments.region_id'] = $data['region_id'];
        }

        if (isset($data['location_id']) && $data['location_id']) {
            $where['appointments.location_id'] = $data['location_id'];
        }

        if (isset($data['service_id']) && $data['service_id']) {
            $where['appointments.service_id'] = $data['service_id'];
        }

        if (isset($data['appointment_status_id']) && $data['appointment_status_id']) {
            $where['appointments.base_appointment_status_id'] = $data['appointment_status_id'];
        }

        if (isset($data['appointment_type_id']) && $data['appointment_type_id']) {
            $where['appointments.appointment_type_id'] = $data['appointment_type_id'];
        }

        if (isset($data['consultancy_type']) && $data['consultancy_type']) {
            $where['appointments.consultancy_type'] = $data['consultancy_type'];
        }

        if (isset($data['user_id']) && $data['user_id']) {
            $where['appointments.created_by'] = $data['user_id'];
        }

        if (isset($data['up_user_id']) && $data['up_user_id']) {
            $where['appointments.converted_by'] = $data['up_user_id'];
        }

        if (isset($data['re_user_id']) && $data['re_user_id']) {
            $where['appointments.updated_by'] = $data['re_user_id'];
        }

        if (isset($data['referred_by']) && $data['referred_by']) {
            $where['users.referred_by'] = $data['referred_by'];
        }

        $where['appointments.account_id'] = $account_id;

        $appointments = \App\Models\Appointments::join('users', 'users.id', '=', 'appointments.patient_id')
            ->whereDate('appointments.'.$data['date_range_by'], '>=', $start_date)
            ->whereDate('appointments.'.$data['date_range_by'], '<=', $end_date)
            ->where($where)
            ->whereIn('appointments.location_id', ACL::getUserCentres())
            ->select('appointments.*', 'users.referred_by')
            ->get();

        $reportData = [];

        $invoice = InvoiceStatuses::whereSlug('paid')->first();

        foreach ($appointments as $appointment) {

            if ($appointment->consultancy_type == 'in_person') {
                $consultancy_type = 'In Person';
            } elseif ($appointment->consultancy_type == 'virtual') {
                $consultancy_type = 'Virtual';
            } else {
                $consultancy_type = '';
            }

            $reportData[$appointment->id] = [
                'id' => $appointment->patient_id,
                'client' => $appointment->name,
                'client_id' => $appointment->patient->id,
                'phone' => $appointment->patient->phone,
                'email' => $appointment->patient->email,
                'scheduled_date' => $appointment->scheduled_date,
                'scheduled_time' => $appointment->scheduled_time,
                'doctor' => $appointment->doctor->name,
                'city' => $appointment->city->name,
                'centre' => $appointment->location->name,
                'service' => $appointment->service->name,
                'status' => $appointment->appointment_status_base->name,
                'type' => ($appointment->appointment_type_id === 1) ? config('constants.Consultancy') : config('constants.Service'),
                'consultancy_type' => $consultancy_type,
                'created_at' => $appointment->created_at,
                'created_by' => $appointment->user->name,
                'updated_by' => $appointment->user_updated_by->name,
                'converted_by' => $appointment->user_converted_by->name,
                'referred_by' => ($appointment->patient->referred_by != null) ? $appointment->patient->referredBy->name : '',
            ];

            if (\App\Models\Invoices::where('appointment_id', '=', $appointment->id)->where('invoice_status_id', '=', $invoice->id)->exists()) {
                $reportData[$appointment->id]['invoice'] = 'Yes';
            } else {
                $reportData[$appointment->id]['invoice'] = 'No';
            }

            if ($appointment->appointment_type_id === 1) {
                if (Medical::where('appointment_id', '=', $appointment->id)->exists()) {
                    $reportData[$appointment->id]['medical_form'] = 'Yes';
                } else {
                    $reportData[$appointment->id]['medical_form'] = 'No';
                }
            } else {
                if (Appointmentimage::where('appointment_id', '=', $appointment->id)->where('type', '=', 'Before Appointment')->exists()) {
                    $reportData[$appointment->id]['images_before'] = 'Yes';
                } else {
                    $reportData[$appointment->id]['images_before'] = 'No';
                }

                if (Appointmentimage::where('appointment_id', '=', $appointment->id)->where('type', '=', 'After Appointment')->exists()) {
                    $reportData[$appointment->id]['images_after'] = 'Yes';
                } else {
                    $reportData[$appointment->id]['images_after'] = 'No';
                }

                if (Measurement::where('appointment_id', '=', $appointment->id)->where('type', '=', 'Before Appointment')->exists()) {
                    $reportData[$appointment->id]['measurement_before'] = 'Yes';
                } else {
                    $reportData[$appointment->id]['measurement_before'] = 'No';
                }

                if (Measurement::where('appointment_id', '=', $appointment->id)->where('type', '=', 'After Appointment')->exists()) {
                    $reportData[$appointment->id]['measurement_after'] = 'Yes';
                } else {
                    $reportData[$appointment->id]['measurement_after'] = 'No';
                }

            }
        }

        return $reportData;
    }

    /**
     * Rescheduled Count Report
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function rescheduledcount($data, $account_id)
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

        if (isset($data['scheduled_date']) && $data['scheduled_date']) {
            $scheduled_date = explode(' - ', $data['scheduled_date']);
            $start_scheduled_date = date('Y-m-d', strtotime($scheduled_date[0]));
            $end_scheduled_date = date('Y-m-d', strtotime($scheduled_date[1]));
        } else {
            $start_scheduled_date = null;
            $end_scheduled_date = null;
        }

        if (isset($data['patient_id']) && $data['patient_id']) {
            $where['appointments.patient_id'] = $data['patient_id'];
        }

        if (isset($data['doctor_id']) && $data['doctor_id']) {
            $where['appointments.doctor_id'] = $data['doctor_id'];
        }

        if (isset($data['city_id']) && $data['city_id']) {
            $where['appointments.city_id'] = $data['city_id'];
        }

        if (isset($data['region_id']) && $data['region_id']) {
            $where['appointments.region_id'] = $data['region_id'];
        }

        if (isset($data['location_id']) && $data['location_id']) {
            $where['appointments.location_id'] = $data['location_id'];
        }

        if (isset($data['service_id']) && $data['service_id']) {
            $where['appointments.service_id'] = $data['service_id'];
        }

        if (isset($data['appointment_status_id']) && $data['appointment_status_id']) {
            $where['appointments.base_appointment_status_id'] = $data['appointment_status_id'];
        }

        if (isset($data['appointment_type_id']) && $data['appointment_type_id']) {
            $where['appointments.appointment_type_id'] = $data['appointment_type_id'];
        }

        if (isset($data['consultancy_type']) && $data['consultancy_type']) {
            $where['appointments.consultancy_type'] = $data['consultancy_type'];
        }

        if (isset($data['user_id']) && $data['user_id']) {
            $where['appointments.created_by'] = $data['user_id'];
        }

        if (isset($data['up_user_id']) && $data['up_user_id']) {
            $where['appointments.converted_by'] = $data['up_user_id'];
        }

        if (isset($data['re_user_id']) && $data['re_user_id']) {
            $where['appointments.updated_by'] = $data['re_user_id'];
        }

        if (isset($data['referred_by']) && $data['referred_by']) {
            $where['users.referred_by'] = $data['referred_by'];
        }

        $where['appointments.account_id'] = $account_id;

        return \App\Models\Appointments::join('users', 'users.id', '=', 'appointments.patient_id')
            ->whereDate('appointments.'.$data['date_range_by_first'], '>=', $start_date)
            ->whereDate('appointments.'.$data['date_range_by_first'], '<=', $end_date)
            ->where($where)
            ->whereIn('appointments.location_id', ACL::getUserCentres())
            ->select('appointments.*', 'users.referred_by')
            ->get();
    }
}
