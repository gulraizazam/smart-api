<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Helpers\ACL;
use App\Models\Patients;
use App\Models\Appointments;
use Illuminate\Http\Request;
use App\HelperModule\ApiHelper;
use App\Models\PackageAdvances;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class PatientFollowupController extends Controller
{
    public $success;
    public $error;
    public $unauthorized;

    public function __construct()
    {
        $this->middleware('auth');
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }
    public function patientFollowUp(Request $request)
    {
        $center_id = ACL::getUserCentres();
        $appointments = DB::select("
                select appointments.id, appointments.patient_id from appointments,
                (
                    select a.patient_id, max(a.created_at) as created_at from appointments a
                        WHERE a.appointment_type_id = 1
                        AND a.base_appointment_status_id = 2
                        AND a.location_id IN (" . implode(',', ACL::getUserCentres()) . ")
                        group by a.patient_id
                ) max_appointments
                where appointments.patient_id = max_appointments.patient_id
                and appointments.created_at = max_appointments.created_at
                ORDER by appointments.id DESC
            ");
        $appointmentIds = array_map(function ($appointment) {
            return $appointment->id;
        }, $appointments);

        $plans_check = DB::table('package_advances')
            ->select(
                'package_advances.id',
                'package_advances.patient_id',
                'package_advances.created_at',
                'cash_received_amount_query.cash_receive',
                'settle_amount_query.settle_amount',
                'settle_tax_amount_query.settle_tax_amount'
            )
            ->leftJoin(DB::raw('(SELECT patient_id, SUM(cash_amount) AS cash_receive
                        FROM package_advances
                        WHERE cash_flow = "in"
                          AND is_cancel = 0
                          AND is_tax = 0
                          AND is_adjustment = 0
                          AND is_refund = 0
                        GROUP BY patient_id) AS cash_received_amount_query'), 'package_advances.patient_id', '=', 'cash_received_amount_query.patient_id')
            ->leftJoin(DB::raw('(SELECT patient_id, SUM(cash_amount) AS settle_amount
                        FROM package_advances
                        WHERE cash_flow = "out"
                          AND is_cancel = 0
                          AND is_tax = 0
                          AND is_adjustment = 0
                          AND is_refund = 0
                        GROUP BY patient_id) AS settle_amount_query'), 'package_advances.patient_id', '=', 'settle_amount_query.patient_id')
            ->leftJoin(DB::raw('(SELECT patient_id, SUM(cash_amount) AS settle_tax_amount
                        FROM package_advances
                        WHERE cash_flow = "out"
                          AND is_cancel = 0
                          AND is_tax = 1
                          AND is_adjustment = 0
                          AND is_refund = 0
                        GROUP BY patient_id) AS settle_tax_amount_query'), 'package_advances.patient_id', '=', 'settle_tax_amount_query.patient_id')
            ->where([
                ['package_advances.cash_flow', '=', 'in'],
                ['package_advances.is_cancel', '=', '0'],
            ])
            ->whereIn('package_advances.appointment_id', $appointmentIds)
            ->whereIn('package_advances.location_id', $center_id)
            ->groupBy('package_advances.patient_id')
            ->orderBy('package_advances.patient_id', 'DESC')
            ->limit(500)
            ->get();
        $plans_check_array = json_decode(json_encode($plans_check), true);
        $not_treatment = [];
        $is_treatment = [];
        $patient_data = [];
        $plan_check_no_treatment = collect($plans_check_array)->where('cash_receive', '>', 0)->where('created_at', '<', Carbon::now()->subDays(7))->pluck('patient_id')->toArray();

        foreach ($plans_check_array as $data) {
            $treatments = Appointments::where([
                'appointment_type_id' => Config::get('constants.appointment_type_service'),
                'patient_id' => $data['patient_id'],
            ])
                ->where('base_appointment_status_id', '!=', 2)
                ->whereIn('location_id', ACL::getUserCentres())
                ->orderBy('created_at', 'DESC')
                ->get();

            $patient = Patients::where(['id' => $data['patient_id'], 'user_type_id' => 3, 'active' => 1])->first();
            $data['patient_id'] = $patient->id;
            $data['name'] = $patient->name;
            $data['phone'] = $patient->phone;

            $data['settle_amount_with_tax'] = $data['settle_amount'] + $data['settle_tax_amount'];

            if (count($treatments) > 0) {
                $check_treatments = collect($treatments)->Where('scheduled_date', '<', Carbon::now()->subDays(1)->format('Y-m-d'));
                $future_treatments = collect($treatments)->Where('scheduled_date', '>', Carbon::now()->format('Y-m-d'));
                if (count($check_treatments) > 0 && $future_treatments->isEmpty() && ($data['cash_receive'] - $data['settle_amount_with_tax']) > 0) {
                    $data['is_treatment'] = 1;
                    array_push($is_treatment, $data);
                }
            } else {
                if (in_array($data['patient_id'], $plan_check_no_treatment) && ($data['cash_receive'] - $data['settle_amount_with_tax']) > 0) {
                    $data['is_treatment'] = 0;
                    array_push($not_treatment, $data);
                }
            }
        }
        $patient_data = array_merge(array_slice($is_treatment, 0, 10), array_slice($not_treatment, 0, 10));

        return ApiHelper::apiResponse($this->success, 'patient data', true, [
            'patient_data' => $patient_data
        ]);
    }

    public function patientFollowUpOneMonth(Request $request)
    {
        $center_id = $request->location_id ? [$request->location_id] : ACL::getUserCentres();
        $patient_ids = Appointments::select('appointments.id', 'appointments.patient_id')
            ->join(DB::raw('(
                SELECT appointment.patient_id, MAX(appointment.created_at) AS created_at
                FROM appointments appointment
                WHERE appointment.appointment_type_id = 1
                    AND appointment.base_appointment_status_id = 2
                    AND appointment.location_id IN (' . implode(',', $center_id) . ')
                GROUP BY appointment.patient_id
            ) latest_appointments'), function ($join) {
                $join->on('appointments.patient_id', '=', 'latest_appointments.patient_id')
                    ->on('appointments.created_at', '=', 'latest_appointments.created_at');
            })
            ->orderByDesc('appointments.id')
            ->pluck('patient_id');

        $cash_received_amounts = PackageAdvances::select('patient_id', DB::raw('SUM(cash_amount) AS cash_receive'))
            ->where([
                'cash_flow' => 'in',
                'is_cancel' => '0',
                'is_tax' => '0',
                'is_adjustment' => '0',
                'is_refund' => '0',
            ])
            ->whereIn('patient_id', $patient_ids)
            ->groupBy('patient_id')
            ->pluck('cash_receive', 'patient_id');

        $settle_amounts = PackageAdvances::select('patient_id', DB::raw('SUM(cash_amount) AS settle_amount'))
            ->where([
                'cash_flow' => 'out',
                'is_cancel' => '0',
                'is_tax' => '0',
                'is_adjustment' => '0',
            ])
            ->whereIn('patient_id', $patient_ids)
            ->groupBy('patient_id')
            ->pluck('settle_amount', 'patient_id');

        $settle_tax_amounts = PackageAdvances::select('patient_id', DB::raw('SUM(cash_amount) AS settle_tax_amount'))
            ->where([
                'cash_flow' => 'out',
                'is_cancel' => '0',
                'is_tax' => '1',
                'is_adjustment' => '0',
            ])
            ->whereIn('patient_id', $patient_ids)
            ->groupBy('patient_id')
            ->pluck('settle_tax_amount', 'patient_id');

        $plans_check = PackageAdvances::select('id', 'patient_id', 'created_at', 'location_id')
            ->whereIn('patient_id', $patient_ids)
            ->whereIn('location_id', $center_id)
            ->groupBy('patient_id')
            ->orderBy('patient_id', 'DESC')
            ->take(5000)
            ->get();
        $plans_check = $plans_check->map(function ($item) use ($cash_received_amounts, $settle_amounts, $settle_tax_amounts) {
            $item->cash_receive = $cash_received_amounts[$item->patient_id] ?? null;
            $item->settle_amount = $settle_amounts[$item->patient_id] ?? null;
            $item->settle_tax_amount = $settle_tax_amounts[$item->patient_id] ?? null;
            return $item;
        });
        $patient_data = [];
        $plan_check_amount = collect($plans_check)->where('cash_receive', '>', 0)->where('created_at', '<', Carbon::now()->subDays(7))->pluck('patient_id')->toArray();
        foreach ($plans_check as $data) {
            $treatments = Appointments::where([
                'appointment_type_id' => Config::get('constants.appointment_type_service'),
                'patient_id' => $data['patient_id'],
            ])
                ->whereIn('location_id', ACL::getUserCentres())
                ->get();
            $patient = Patients::where(['id' => $data['patient_id'], 'user_type_id' => 3, 'active' => 1])->first();
            $data['patient_id'] = $patient->id;
            $data['name'] = $patient->name;
            $data['phone'] = $patient->phone;

            $data['settle_amount_with_tax'] = $data['settle_amount'] + $data['settle_tax_amount'];

            if (count($treatments) > 0) {
                $check_treatments = collect($treatments)->sortByDesc('id')->first();
                $future_treatments = collect($treatments)->Where('scheduled_date', '>=', Carbon::now()->format('Y-m-d'));
                if ($check_treatments->base_appointment_status_id == 2 && $check_treatments->scheduled_date <= Carbon::now()->subDays(31)->format('Y-m-d') && $future_treatments->isEmpty()) {
                    if (in_array($data['patient_id'], $plan_check_amount) && ($data['cash_receive'] - $data['settle_amount_with_tax']) > 0) {
                        $data['is_treatment'] = 1;
                        array_push($patient_data, $data);
                    }
                }
            }
        }
        return ApiHelper::apiResponse($this->success, 'patient data', true, [
            'patient_data' => $patient_data
        ]);
    }
}

