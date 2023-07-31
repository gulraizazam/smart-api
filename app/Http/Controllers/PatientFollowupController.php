<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Helpers\ACL;
use App\Models\Patients;
use Illuminate\Support\Str;
use App\Models\Appointments;
use Illuminate\Http\Request;
use App\Exports\ExportFollowUp;
use App\HelperModule\ApiHelper;
use App\Models\PackageAdvances;
use Barryvdh\DomPDF\Facade\Pdf;
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
        $where = [];
        $where[] = [
            'package_advances.created_at',
            '>=',
            Carbon::now()->subDays(30)->format('Y-m-d'),
        ];
        $where[] = [
            'package_advances.created_at',
            '<=',
            Carbon::now()->format('Y-m-d'),
        ];


        $center_id =  ACL::getUserCentres();
        $appointments = Appointments::select('appointments.id', 'appointments.patient_id')
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


        $cashReceivedAmounts = PackageAdvances::select('patient_id', DB::raw('SUM(cash_amount) AS cash_receive'))
            ->where([
                'cash_flow' => 'in',
                'is_cancel' => '0',
                'is_tax' => '0',
                'is_adjustment' => '0',
                'is_refund' => '0',
            ])
            ->whereIn('patient_id', $appointments)
            ->groupBy('patient_id')
            ->pluck('cash_receive', 'patient_id');

        $settleAmounts = PackageAdvances::select('patient_id', DB::raw('SUM(cash_amount) AS settle_amount'))
            ->where([
                'cash_flow' => 'out',
                'is_cancel' => '0',
                'is_tax' => '0',
                'is_adjustment' => '0',

            ])
            ->whereIn('patient_id', $appointments)
            ->groupBy('patient_id')
            ->pluck('settle_amount', 'patient_id');

        $settleTaxAmounts = PackageAdvances::select('patient_id', DB::raw('SUM(cash_amount) AS settle_tax_amount'))
            ->where([
                'cash_flow' => 'out',
                'is_cancel' => '0',
                'is_tax' => '1',
                'is_adjustment' => '0',

            ])
            ->whereIn('patient_id', $appointments)
            ->groupBy('patient_id')
            ->pluck('settle_tax_amount', 'patient_id');

        $plans_check = PackageAdvances::select('package_advances.id', 'package_advances.patient_id', 'package_advances.created_at', 'package_advances.location_id')
            ->whereIn('package_advances.patient_id', $appointments)
            ->whereIn('package_advances.location_id', $center_id)
            ->where($where)
            ->where('cash_flow','in')
            ->groupBy('package_advances.patient_id')

            ->orderBy('package_advances.patient_id', 'DESC')
            ->get();
        $plans_check = $plans_check->map(function ($item) use ($cashReceivedAmounts, $settleAmounts, $settleTaxAmounts) {
            $item->cash_receive = $cashReceivedAmounts[$item->patient_id] ?? null;
            $item->settle_amount = $settleAmounts[$item->patient_id] ?? null;
            $item->settle_tax_amount = $settleTaxAmounts[$item->patient_id] ?? null;
            return $item;
        });

        $not_treatment = [];
        $is_treatment = [];
        $patient_data = [];
        $plan_check_no_treatment = collect($plans_check)->where('cash_receive', '>', 0)
            ->where('created_at', '<', Carbon::now()->subDays(3))
            ->pluck('patient_id')->toArray();
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
            $data['created_at'] = Carbon::parse($data['created_at'])->toDateString();
            if (count($treatments) > 0) {
                $has_treatment_with_status_2 = collect($treatments)->contains('base_appointment_status_id', 2);
                $check_treatments = collect($treatments)->sortByDesc('id')->first();
                $future_treatments = collect($treatments)->Where('scheduled_date', '>', Carbon::now()->format('Y-m-d'));

                if (!$has_treatment_with_status_2 && $check_treatments->scheduled_date <= Carbon::now()->subDays(2)->format('Y-m-d') && $future_treatments->isEmpty() && ($data['cash_receive'] - $data['settle_amount_with_tax']) > 1) {
                    $data['is_treatment'] = 1;
                    array_push($is_treatment, $data);


                }
            } else {
                if (in_array($data['patient_id'], $plan_check_no_treatment) && ($data['cash_receive'] - $data['settle_amount_with_tax']) > 1) {
                    $data['is_treatment'] = 0;
                    array_push($not_treatment, $data);
                }
            }

        }
        $patient_data = array_merge($is_treatment, $not_treatment);
       // $sorted_patient_data = collect($patient_data)->sortByDesc('created_at');
        return ApiHelper::apiResponse($this->success, 'patient data', true, [
            'patient_data' => $patient_data
        ]);
    }
    public function patientFollowUpDownload(Request $request)
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);
        $where = [];
        $where[] = [
            'package_advances.created_at',
            '>=',
            Carbon::now()->subMonths(3)->toDateString(),
        ];
        $where[] = [
            'package_advances.created_at',
            '<=',
            Carbon::now()->toDateString(),
        ];
        
        $center_id = ACL::getUserCentres();
        $appointments = Appointments::select('appointments.id', 'appointments.patient_id')
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


        $cashReceivedAmounts = PackageAdvances::select('patient_id', DB::raw('SUM(cash_amount) AS cash_receive'))
            ->where([
                'cash_flow' => 'in',
                'is_cancel' => '0',
                'is_tax' => '0',
                'is_adjustment' => '0',
                'is_refund' => '0',
            ])
            ->whereIn('patient_id', $appointments)
            ->groupBy('patient_id')
            ->pluck('cash_receive', 'patient_id');

        $settleAmounts = PackageAdvances::select('patient_id', DB::raw('SUM(cash_amount) AS settle_amount'))
            ->where([
                'cash_flow' => 'out',
                'is_cancel' => '0',
                'is_tax' => '0',
                'is_adjustment' => '0',

            ])
            ->whereIn('patient_id', $appointments)
            ->groupBy('patient_id')
            ->pluck('settle_amount', 'patient_id');

        $settleTaxAmounts = PackageAdvances::select('patient_id', DB::raw('SUM(cash_amount) AS settle_tax_amount'))
            ->where([
                'cash_flow' => 'out',
                'is_cancel' => '0',
                'is_tax' => '1',
                'is_adjustment' => '0',

            ])
            ->whereIn('patient_id', $appointments)
            ->groupBy('patient_id')
            ->pluck('settle_tax_amount', 'patient_id');

        $plans_check = PackageAdvances::select('package_advances.id', 'package_advances.patient_id', 'package_advances.created_at', 'package_advances.location_id')
            ->whereIn('package_advances.patient_id', $appointments)
            ->whereIn('package_advances.location_id', $center_id)
            ->where($where)
            ->groupBy('package_advances.patient_id')
            ->orderBy('package_advances.patient_id', 'DESC')
            ->get();
        $plans_check = $plans_check->map(function ($item) use ($cashReceivedAmounts, $settleAmounts, $settleTaxAmounts) {
            $item->cash_receive = $cashReceivedAmounts[$item->patient_id] ?? null;
            $item->settle_amount = $settleAmounts[$item->patient_id] ?? null;
            $item->settle_tax_amount = $settleTaxAmounts[$item->patient_id] ?? null;
            return $item;
        });

        $not_treatment = [];
        $is_treatment = [];
        $patient_data = [];
        $plan_check_no_treatment = collect($plans_check)->where('cash_receive', '>', 0)
            ->where('created_at', '<', Carbon::now()->subDays(3))
            ->pluck('patient_id')->toArray();
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
                $has_treatment_with_status_2 = collect($treatments)->contains('base_appointment_status_id', 2);
                $check_treatments = collect($treatments)->sortByDesc('id')->first();
                $future_treatments = collect($treatments)->Where('scheduled_date', '>', Carbon::now()->format('Y-m-d'));

                if (!$has_treatment_with_status_2 && $check_treatments->scheduled_date <= Carbon::now()->subDays(2)->format('Y-m-d') && $future_treatments->isEmpty() && ($data['cash_receive'] - $data['settle_amount_with_tax']) > 1) {
                    $data['is_treatment'] = 1;
                    array_push($is_treatment, $data);


                }
            } else {
                if (in_array($data['patient_id'], $plan_check_no_treatment) && ($data['cash_receive'] - $data['settle_amount_with_tax']) > 1) {
                    $data['is_treatment'] = 0;
                    array_push($not_treatment, $data);
                }
            }
        }
        $patient_data = array_merge($is_treatment, $not_treatment);
        $customPaper = [0, 0, 720, 1440];
        $pdf = PDF::loadView('admin.reports.followup-pdf', compact('patient_data'))->setPaper($customPaper, 'portrait');

        return $pdf->download('followup.pdf');
        
    }
    
    public function patientMonthlyFollowUpDownload(Request $request)
    {
        $where = [];
        $where[] = [
            'appointments.scheduled_date',
            '>=',
            Carbon::now()->subMonths(3)->toDateString(),
        ];
        
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
            ->limit(3000)
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
                ->where($where)
                ->get();
            $patient = Patients::where(['id' => $data['patient_id'], 'user_type_id' => 3, 'active' => 1])->first();
            $data['patient_id'] = $patient->id;
            $data['name'] = $patient->name;
            $data['phone'] = $patient->phone;
            $data['settle_amount_with_tax'] = $data['settle_amount'] + $data['settle_tax_amount'];

            if (count($treatments) > 0) {
                $has_treatment_with_status_2 = collect($treatments)->contains('base_appointment_status_id', 2);
                $check_treatments = collect($treatments)->sortByDesc('id')->first();
                $future_treatments = collect($treatments)->Where('scheduled_date', '>=', Carbon::now()->format('Y-m-d'));
                if ($has_treatment_with_status_2 && $check_treatments->base_appointment_status_id != 1 && $check_treatments->scheduled_date <= Carbon::now()->subDays(31)->format('Y-m-d') && $future_treatments->isEmpty()) {
                    if (in_array($data['patient_id'], $plan_check_amount) && ($data['cash_receive'] - $data['settle_amount_with_tax']) > 1) {
                        $data['is_treatment'] = 1;
                        $data['scheduled_date'] = $check_treatments->scheduled_date ;
                        array_push($patient_data, $data);
                    }
                }
            }
           
        }
        $customPaper = [0, 0, 720, 1440];
        $pdf = PDF::loadView('admin.reports.monthlyfollowup-pdf', compact('patient_data'))->setPaper($customPaper, 'portrait');

        return $pdf->download('monthlyfollowup.pdf');
    }
    
    public function patientFollowUpOneMonth(Request $request)
    {

        $where = [];
        $where[] = [
            'appointments.scheduled_date',
            '>=',
            Carbon::now()->subMonths(3)->format('Y-m-d'),
        ];
       
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
                ->where($where)
                ->get();
            $patient = Patients::where(['id' => $data['patient_id'], 'user_type_id' => 3, 'active' => 1])->first();
            $data['patient_id'] = $patient->id;
            $data['name'] = Str::limit($patient->name,16,$end="...");
            $data['phone'] = $patient->phone;
            $data['settle_amount_with_tax'] = $data['settle_amount'] + $data['settle_tax_amount'];

            if (count($treatments) > 0) {
                $has_treatment_with_status_2 = collect($treatments)->contains('base_appointment_status_id', 2);
                $check_treatments = collect($treatments)->sortByDesc('id')->first();
                $future_treatments = collect($treatments)->Where('scheduled_date', '>=', Carbon::now()->format('Y-m-d'));
                if ($has_treatment_with_status_2 && $check_treatments->base_appointment_status_id != 1 && $check_treatments->scheduled_date <= Carbon::now()->subDays(31)->format('Y-m-d') && $future_treatments->isEmpty()) {
                    if (in_array($data['patient_id'], $plan_check_amount) && ($data['cash_receive'] - $data['settle_amount_with_tax']) > 1) {
                        $data['is_treatment'] = 1;
                        $data['scheduled_date'] = $check_treatments->scheduled_date ;
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
