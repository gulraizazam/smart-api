<?php

declare(strict_types=1);

namespace App\Reports\Finance;

use App\Helpers\ACL;
use App\Models\Appointments;
use App\Models\AppointmentTypes;
use App\Models\Locations;
use App\Services\Reports\Concerns\ParsesDateRange;

class FinancePerformanceReport
{
    use ParsesDateRange;

    /**
     * Centre performance stats by revenue
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function centerperformancestatsbyrevenue($data, $filters = [])
    {
        $where = [];
        [$start_date, $end_date] = self::parseDateRange($data['date_range'] ?? null);
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
            $recods = Appointments::whereDate('created_at', '>=', $start_date)
                ->whereDate('created_at', '<=', $end_date)
                ->where($where)
                ->whereIn('location_id', ACL::getUserCentres())
                ->get();
        } else {
            $recods = Appointments::whereDate('created_at', '>=', $start_date)
                ->whereDate('created_at', '<=', $end_date)
                ->whereIn('location_id', ACL::getUserCentres())
                ->get();
        }
        $data = [];
        $created_byArray = [];

        if ($recods) {
            foreach ($recods as $recod) {
                if (! in_array($recod->location_id, $created_byArray, true)) {
                    $created_byArray[] = $recod->location_id;
                    $locationinfo = Locations::where('id', '=', $recod->location_id)->first();
                    $data[$recod->location_id] = [
                        'id' => $recod->location_id,
                        'name' => $locationinfo->name,
                        'region' => (array_key_exists($locationinfo->region_id, $filters['regions'])) ? $filters['regions'][$recod->region_id]->name : '',
                        'city' => (array_key_exists($locationinfo->city_id, $filters['cities'])) ? $filters['cities'][$recod->city_id]->name : '',
                    ];
                    $data[$recod->location_id]['records'][$recod->id] = $recod;
                } else {
                    $data[$recod->location_id]['records'][$recod->id] = $recod;
                }
            }
        }

        return $data;
    }

    /**
     * Centre performance stats by service type
     *
     * @param  (mixed)  $request
     * @return (mixed)
     */
    public static function centerperformancestatsbyservices($data, $filters = [])
    {
        $where = [];
        [$start_date, $end_date] = self::parseDateRange($data['date_range'] ?? null);
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
            $recods = Appointments::whereDate('created_at', '>=', $start_date)
                ->whereDate('created_at', '<=', $end_date)
                ->whereIn('location_id', ACL::getUserCentres())
                ->where($where)
                ->get();
        } else {
            $recods = Appointments::whereDate('created_at', '>=', $start_date)
                ->whereDate('created_at', '<=', $end_date)
                ->whereIn('location_id', ACL::getUserCentres())
                ->get();
        }
        $data = [];
        $created_byArray = [];

        if ($recods) {
            foreach ($recods as $recod) {
                if (! in_array($recod->appointment_type_id, $created_byArray, true)) {
                    $created_byArray[] = $recod->appointment_type_id;
                    $appointmenttype = AppointmentTypes::find($recod->appointment_type_id);
                    $data[$recod->appointment_type_id] = [
                        'name' => $appointmenttype->name,
                    ];
                    $data[$recod->appointment_type_id]['records'][$recod->id] = $recod;
                } else {
                    $data[$recod->appointment_type_id]['records'][$recod->id] = $recod;
                }
            }
        }

        return $data;
    }
}
