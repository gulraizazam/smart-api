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
        $whereAppointment = [];
        $where[] = [
            'package_advances.created_at',
            '>=',
            Carbon::now()->subDays(30)->format('Y-m-d'),
        ];
        $whereAppointment[] = [
            'appointments.scheduled_date',
            '>=',
            Carbon::now()->subMonths(3)->format('Y-m-d'),
        ];
        // $where[] = [
        //     'package_advances.created_at',
        //     '<=',
        //     Carbon::now()->format('Y-m-d'),
        // ];


        $center_id =  ACL::getUserCentres();
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
            ->where($whereAppointment)
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
            ->whereIn('patient_id', $patient_ids)
            ->groupBy('patient_id')
            ->pluck('cash_receive', 'patient_id');

        $cash_setteled_amounts = PackageAdvances::select('patient_id', DB::raw('SUM(cash_amount) AS cash_setteled_receive'))
            ->where([
                'cash_flow' => 'out',
                'is_cancel' => '0',
                'is_tax' => '0',
                'is_adjustment' => '0',
                'is_setteled' => '1',

            ])
            ->whereIn('patient_id', $patient_ids)
            ->groupBy('patient_id')
            ->pluck('cash_setteled_receive', 'patient_id');
        $settleAmounts = PackageAdvances::select('patient_id', DB::raw('SUM(cash_amount) AS settle_amount'))
            ->where([
                'cash_flow' => 'out',
                'is_cancel' => '0',
                'is_tax' => '0',
                'is_adjustment' => '0',
                'is_refund' => '0',
            ])
            ->whereIn('patient_id', $patient_ids)
            ->groupBy('patient_id')
            ->pluck('settle_amount', 'patient_id');
        $settle__adjustment_amounts = PackageAdvances::select('patient_id', DB::raw('SUM(cash_amount) AS settle_adjust_amount'))
            ->where([
                'cash_flow' => 'out',
                'is_cancel' => '0',
                'is_tax' => '0',
                'is_adjustment' => '1',
                'is_refund' => '0',
            ])
            ->whereIn('patient_id', $patient_ids)
            ->groupBy('patient_id')
            ->pluck('settle_adjust_amount', 'patient_id');
        $refunded_amounts = PackageAdvances::select('patient_id', DB::raw('SUM(cash_amount) AS refunded_amount'))
            ->where([
                'cash_flow' => 'out',
                'is_cancel' => '0',
                'is_tax' => '0',
                'is_adjustment' => '0',
                'is_refund' => '1',
                'is_setteled' => '0',
            ])
            ->whereIn('patient_id', $patient_ids)
            ->groupBy('patient_id')
            ->pluck('refunded_amount', 'patient_id');

        $settleTaxAmounts = PackageAdvances::select('patient_id', DB::raw('SUM(cash_amount) AS settle_tax_amount'))
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
            ->where($where)
            ->where('cash_flow', 'in')
            ->groupBy('patient_id')

            ->groupBy('patient_id')
            ->orderBy('patient_id', 'DESC')
            ->get();


        $plans_check = $plans_check->map(function ($item) use ($cashReceivedAmounts, $settleAmounts, $settleTaxAmounts, $cash_setteled_amounts, $settle__adjustment_amounts, $refunded_amounts) {
            $item->cash_receive = $cashReceivedAmounts[$item->patient_id] ?? null;
            $item->settle_amount = $settleAmounts[$item->patient_id] ?? null;
            $item->settle_tax_amount = $settleTaxAmounts[$item->patient_id] ?? null;
            $item->cash_setteled_amounts = $cash_setteled_amounts[$item->patient_id] ?? null;
            $item->settle__adjustment_amounts = $settle__adjustment_amounts[$item->patient_id] ?? null;
            $item->refunded_amounts = $refunded_amounts[$item->patient_id] ?? null;
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
            $conversion_date = PackageAdvances::where([
                ['patient_id', '=', $data['patient_id']],
                ['cash_amount', '>', 0],
                ['cash_flow', '=', 'in'],
                ['is_setteled', '=', 0],
                ['is_tax', '=', 0],

            ])->first();
            $patient = Patients::where(['id' => $data['patient_id'], 'user_type_id' => 3, 'active' => 1])->first();
            if ($patient) {
                $data['patient_id'] = $patient->id;
                $data['name'] = $patient->name;
                $data['phone'] = $patient->phone;
                $data['settle_amount_with_tax'] = ($data['settle_amount'] + $data['settle_tax_amount']  + $data['settle__adjustment_amounts']);
                $data['created_at'] = $conversion_date ? Carbon::parse($conversion_date->created_at)->format('Y-m-d') : Carbon::parse($data['created_at'])->format('Y-m-d');
                if (count($treatments) > 0) {
                    $has_treatment_with_status_2 = collect($treatments)->contains('base_appointment_status_id', 2);
                    $check_treatments = collect($treatments)->sortByDesc('id')->first();
                    $future_treatments = collect($treatments)->Where('scheduled_date', '>', Carbon::now()->format('Y-m-d'));
                    if (!$has_treatment_with_status_2 && $check_treatments->scheduled_date <= Carbon::now()->subDays(2)->format('Y-m-d') && $future_treatments->isEmpty() && $data['cash_setteled_amounts'] == null && ($data['cash_receive'] - $data['settle_amount_with_tax']) > 1) {
                        $data['is_treatment'] = 1;
                        array_push($is_treatment, $data);
                    }
                } else {
                    if (in_array($data['patient_id'], $plan_check_no_treatment) && $data['cash_setteled_amounts'] == null && ($data['cash_receive'] - $data['settle_amount_with_tax']) > 450) {
                        $data['is_treatment'] = 0;
                        array_push($not_treatment, $data);
                    }
                }
            }
        }
        $patient_data = array_merge($is_treatment, $not_treatment);
        usort($patient_data, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
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
        $cash_setteled_amounts = PackageAdvances::select('patient_id', DB::raw('SUM(cash_amount) AS cash_receive'))
            ->where([
                'cash_flow' => 'out',
                'is_cancel' => '0',
                'is_tax' => '0',
                'is_adjustment' => '0',
                'is_setteled' => '1',

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
                'is_refund' => '0',
            ])
            ->whereIn('patient_id', $appointments)
            ->groupBy('patient_id')
            ->pluck('settle_amount', 'patient_id');
        $settle__adjustment_amounts = PackageAdvances::select('patient_id', DB::raw('SUM(cash_amount) AS settle_amount'))
            ->where([
                'cash_flow' => 'out',
                'is_cancel' => '0',
                'is_tax' => '0',
                'is_adjustment' => '1',
                'is_refund' => '0',
            ])
            ->whereIn('patient_id', $appointments)
            ->groupBy('patient_id')
            ->pluck('settle_amount', 'patient_id');
        $refunded_amounts = PackageAdvances::select('patient_id', DB::raw('SUM(cash_amount) AS refunded_amount'))
            ->where([
                'cash_flow' => 'out',
                'is_cancel' => '0',
                'is_tax' => '0',
                'is_adjustment' => '0',
                'is_refund' => '1',
            ])
            ->whereIn('patient_id', $appointments)
            ->groupBy('patient_id')
            ->pluck('refunded_amount', 'patient_id');


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
        $plans_check = $plans_check->map(function ($item) use ($cashReceivedAmounts, $settleAmounts, $settleTaxAmounts, $cash_setteled_amounts, $settle__adjustment_amounts, $refunded_amounts) {
            $item->cash_receive = $cashReceivedAmounts[$item->patient_id] ?? null;
            $item->settle_amount = $settleAmounts[$item->patient_id] ?? null;
            $item->settle_tax_amount = $settleTaxAmounts[$item->patient_id] ?? null;
            $item->cash_setteled_amounts = $cash_setteled_amounts[$item->patient_id] ?? null;
            $item->settle__adjustment_amounts = $settle__adjustment_amounts[$item->patient_id] ?? null;
            $item->refunded_amounts = $refunded_amounts[$item->patient_id] ?? null;
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
            if ($patient) {
                $data['patient_id'] = $patient->id;
                $data['name'] = $patient->name;
                $data['phone'] = $patient->phone;
                $data['settle_amount_with_tax'] = $data['settle_amount'] + $data['settle_tax_amount'] + $data['refunded_amount'] + $data['settle__adjustment_amounts'];
            }

            if (count($treatments) > 0) {
                $has_treatment_with_status_2 = collect($treatments)->contains('base_appointment_status_id', 2);
                $check_treatments = collect($treatments)->sortByDesc('id')->first();
                $future_treatments = collect($treatments)->Where('scheduled_date', '>', Carbon::now()->format('Y-m-d'));

                if (!$has_treatment_with_status_2 && $check_treatments->scheduled_date <= Carbon::now()->subDays(2)->format('Y-m-d') && $future_treatments->isEmpty() && $data['cash_setteled_amounts'] == null && ($data['cash_receive'] - $data['settle_amount_with_tax']) > 450) {
                    $data['is_treatment'] = 1;
                    array_push($is_treatment, $data);
                }
            } else {
                if (in_array($data['patient_id'], $plan_check_no_treatment) && $data['cash_setteled_amounts'] == null && ($data['cash_receive'] - $data['settle_amount_with_tax']) > 450) {
                    $data['is_treatment'] = 0;
                    array_push($not_treatment, $data);
                }
            }
        }
        $patient_data = array_merge($is_treatment, $not_treatment);
        usort($patient_data, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
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
                'is_setteled' => '0',
            ])
            ->whereIn('patient_id', $patient_ids)
            ->groupBy('patient_id')
            ->pluck('cash_receive', 'patient_id');

        $cash_setteled_amounts = PackageAdvances::select('patient_id', DB::raw('SUM(cash_amount) AS cash_receive'))
            ->where([
                'cash_flow' => 'out',
                'is_cancel' => '0',
                'is_tax' => '0',
                'is_adjustment' => '0',
                'is_setteled' => '1',

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
                'is_refund' => '0',
            ])
            ->whereIn('patient_id', $patient_ids)
            ->groupBy('patient_id')
            ->pluck('settle_amount', 'patient_id');
        $settle__adjustment_amounts = PackageAdvances::select('patient_id', DB::raw('SUM(cash_amount) AS settle_amount'))
            ->where([
                'cash_flow' => 'out',
                'is_cancel' => '0',
                'is_tax' => '0',
                'is_adjustment' => '1',
                'is_refund' => '0',
            ])
            ->whereIn('patient_id', $patient_ids)
            ->groupBy('patient_id')
            ->pluck('settle_amount', 'patient_id');
        $refunded_amounts = PackageAdvances::select('patient_id', DB::raw('SUM(cash_amount) AS refunded_amount'))
            ->where([
                'cash_flow' => 'out',
                'is_cancel' => '0',
                'is_tax' => '0',
                'is_adjustment' => '0',
                'is_refund' => '1',
            ])
            ->whereIn('patient_id', $patient_ids)
            ->groupBy('patient_id')
            ->pluck('refunded_amount', 'patient_id');

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
        $plans_check = $plans_check->map(function ($item) use ($cash_received_amounts, $settle_amounts, $settle_tax_amounts, $cash_setteled_amounts, $refunded_amounts, $settle__adjustment_amounts) {
            $item->cash_receive = $cash_received_amounts[$item->patient_id] ?? null;
            $item->settle_amount = $settle_amounts[$item->patient_id] ?? null;
            $item->settle_tax_amount = $settle_tax_amounts[$item->patient_id] ?? null;
            $item->cash_setteled_amounts = $cash_setteled_amounts[$item->patient_id] ?? null;
            $item->refunded_amount = $refunded_amounts[$item->patient_id] ?? null;
            $item->settle__adjustment_amounts = $settle__adjustment_amounts[$item->patient_id] ?? null;
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
            if ($patient) {
                $data['patient_id'] = $patient->id;
                $data['name'] = $patient->name;
                $data['phone'] = $patient->phone;
            }

            $data['settle_amount_with_tax'] = $data['settle_amount'] + $data['settle_tax_amount'] + $data['refunded_amount'] + $data['settle__adjustment_amounts'];


            if (count($treatments) > 0) {
                $has_treatment_with_status_2 = collect($treatments)->contains('base_appointment_status_id', 2);
                $check_treatments = collect($treatments)->sortByDesc('id')->first();
                $future_treatments = collect($treatments)->Where('scheduled_date', '>=', Carbon::now()->format('Y-m-d'));
                if ($has_treatment_with_status_2 && $check_treatments->base_appointment_status_id != 1 && $check_treatments->scheduled_date <= Carbon::now()->subDays(31)->format('Y-m-d') && $future_treatments->isEmpty()) {
                    if (in_array($data['patient_id'], $plan_check_amount) && $data['cash_setteled_amounts'] == null && ($data['cash_receive'] - $data['settle_amount_with_tax']) > 450) {
                        $data['is_treatment'] = 1;
                        $data['scheduled_date'] = $check_treatments->scheduled_date;
                        array_push($patient_data, $data);
                    }
                }
            }
        }
        usort($patient_data, function ($a, $b) {
            return strtotime($b['scheduled_date']) - strtotime($a['scheduled_date']);
        });
        $customPaper = [0, 0, 720, 1440];
        $pdf = PDF::loadView('admin.reports.monthlyfollowup-pdf', compact('patient_data'))->setPaper($customPaper, 'portrait');

        return $pdf->download('monthlyfollowup.pdf');
    }

    public function patientFollowUpOneMonth(Request $request)
    {
        $center_id = $request->location_id ? [$request->location_id] : ACL::getUserCentres();
        $threeMonthsAgo = Carbon::now()->subMonths(3)->format('Y-m-d');
        $sevenDaysAgo = Carbon::now()->subDays(7);
        $thirtyOneDaysAgo = Carbon::now()->subDays(31)->format('Y-m-d');
        $today = Carbon::now()->format('Y-m-d');
        
        // Get patient IDs with parameterized query (fixes SQL injection)
        $centerIdPlaceholders = implode(',', array_fill(0, count($center_id), '?'));
        $patient_ids = Appointments::select('appointments.id', 'appointments.patient_id')
            ->join(DB::raw("(
                SELECT appointment.patient_id, MAX(appointment.created_at) AS created_at
                FROM appointments appointment
                WHERE appointment.appointment_type_id = 1
                    AND appointment.base_appointment_status_id = 2
                    AND appointment.location_id IN ({$centerIdPlaceholders})
                GROUP BY appointment.patient_id
            ) latest_appointments"), function ($join) {
                $join->on('appointments.patient_id', '=', 'latest_appointments.patient_id')
                    ->on('appointments.created_at', '=', 'latest_appointments.created_at');
            })
            ->addBinding($center_id, 'join')
            ->where('appointments.scheduled_date', '>=', $threeMonthsAgo)
            ->orderByDesc('appointments.id')
            ->pluck('patient_id')
            ->toArray();

        if (empty($patient_ids)) {
            return ApiHelper::apiResponse($this->success, 'patient data', true, ['patient_data' => []]);
        }

        // Combine 6 PackageAdvances queries into ONE using conditional aggregation
        $packageAmounts = PackageAdvances::select('patient_id')
            ->selectRaw("SUM(CASE WHEN cash_flow = 'in' AND is_cancel = '0' AND is_tax = '0' AND is_adjustment = '0' AND is_refund = '0' THEN cash_amount ELSE 0 END) as cash_receive")
            ->selectRaw("SUM(CASE WHEN cash_flow = 'out' AND is_cancel = '0' AND is_tax = '0' AND is_adjustment = '0' AND is_setteled = '1' THEN cash_amount ELSE 0 END) as cash_setteled_amounts")
            ->selectRaw("SUM(CASE WHEN cash_flow = 'out' AND is_cancel = '0' AND is_tax = '0' AND is_adjustment = '0' AND is_refund = '0' THEN cash_amount ELSE 0 END) as settle_amount")
            ->selectRaw("SUM(CASE WHEN cash_flow = 'out' AND is_cancel = '0' AND is_tax = '0' AND is_adjustment = '1' AND is_refund = '0' THEN cash_amount ELSE 0 END) as settle_adjustment_amounts")
            ->selectRaw("SUM(CASE WHEN cash_flow = 'out' AND is_cancel = '0' AND is_tax = '0' AND is_adjustment = '0' AND is_refund = '1' THEN cash_amount ELSE 0 END) as refunded_amount")
            ->selectRaw("SUM(CASE WHEN cash_flow = 'out' AND is_cancel = '0' AND is_tax = '1' AND is_adjustment = '0' THEN cash_amount ELSE 0 END) as settle_tax_amount")
            ->whereIn('patient_id', $patient_ids)
            ->groupBy('patient_id')
            ->get()
            ->keyBy('patient_id');

        // Get plans with earliest created_at per patient
        $plans_check = PackageAdvances::select('patient_id', DB::raw('MIN(id) as id'), DB::raw('MIN(created_at) as created_at'), DB::raw('MIN(location_id) as location_id'))
            ->whereIn('patient_id', $patient_ids)
            ->whereIn('location_id', $center_id)
            ->groupBy('patient_id')
            ->get();

        // Filter patients with cash_receive > 0 and created_at < 7 days ago
        $plan_check_amount = $plans_check->filter(function ($item) use ($packageAmounts, $sevenDaysAgo) {
            $amounts = $packageAmounts->get($item->patient_id);
            return $amounts && $amounts->cash_receive > 0 && Carbon::parse($item->created_at)->lt($sevenDaysAgo);
        })->pluck('patient_id')->toArray();

        // Get all patient IDs we need
        $patientIdsToFetch = $plans_check->pluck('patient_id')->toArray();

        // Fetch all patients in ONE query (fixes N+1)
        $patients = Patients::whereIn('id', $patientIdsToFetch)
            ->where(['user_type_id' => 3, 'active' => 1])
            ->get()
            ->keyBy('id');

        // Fetch all treatments in ONE query (fixes N+1)
        $allTreatments = Appointments::where('appointment_type_id', Config::get('constants.appointment_type_service'))
            ->whereIn('patient_id', $patientIdsToFetch)
            ->whereIn('location_id', ACL::getUserCentres())
            ->get()
            ->groupBy('patient_id');

        $patient_data = [];
        
        foreach ($plans_check as $data) {
            $patient = $patients->get($data->patient_id);
            if (!$patient) {
                continue;
            }

            $amounts = $packageAmounts->get($data->patient_id);
            $cash_receive = $amounts->cash_receive ?? 0;
            $settle_amount = $amounts->settle_amount ?? 0;
            $settle_tax_amount = $amounts->settle_tax_amount ?? 0;
            $refunded_amount = $amounts->refunded_amount ?? 0;
            $settle_adjustment_amounts = $amounts->settle_adjustment_amounts ?? 0;
            $cash_setteled_amounts = $amounts->cash_setteled_amounts ?? 0;
            
            $settle_amount_with_tax = $settle_amount + $settle_tax_amount + $refunded_amount + $settle_adjustment_amounts;

            $treatments = $allTreatments->get($data->patient_id, collect());
            
            if ($treatments->isEmpty()) {
                continue;
            }

            $has_treatment_with_status_2 = $treatments->contains('base_appointment_status_id', 2);
            $check_treatments = $treatments->sortByDesc('id')->first();
            $future_treatments = $treatments->where('scheduled_date', '>=', $today);

            if ($has_treatment_with_status_2 
                && $check_treatments->base_appointment_status_id != 1 
                && $check_treatments->scheduled_date <= $thirtyOneDaysAgo 
                && $future_treatments->isEmpty()
            ) {
                if (in_array($data->patient_id, $plan_check_amount) 
                    && $cash_setteled_amounts == 0 
                    && ($cash_receive - $settle_amount_with_tax) > 450
                ) {
                    $patient_data[] = [
                        'patient_id' => $patient->id,
                        'name' => Str::limit($patient->name, 16, '...'),
                        'phone' => $patient->phone,
                        'cash_receive' => $cash_receive,
                        'settle_amount_with_tax' => $settle_amount_with_tax,
                        'is_treatment' => 1,
                        'scheduled_date' => $check_treatments->scheduled_date,
                        'created_at' => $data->created_at,
                        'location_id' => $data->location_id,
                    ];
                }
            }
        }

        // Sort by scheduled_date descending
        usort($patient_data, fn($a, $b) => strtotime($b['scheduled_date']) - strtotime($a['scheduled_date']));

        return ApiHelper::apiResponse($this->success, 'patient data', true, [
            'patient_data' => $patient_data
        ]);
    }
}
