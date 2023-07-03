<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\HelperModule\ApiHelper;
use App\Models\Appointments;
use App\Models\Patients;
use App\Helpers\ACL;
use App\Models\PackageAdvances;
use Carbon\Carbon;
use App\Helpers\GeneralFunctions;
use Illuminate\Support\Facades\Config;
use App\Models\RoleHasUsers;
use config\constants;
use Illuminate\Support\Facades\DB;
use function PHPUnit\Framework\isEmpty;

class DashboardPatientFollowUpOutStandingBalanceController extends Controller
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

    public function patientFollowUpOutStandingBalance(Request $request)
    {
        DB::enableQueryLog();
        $period = $request->period == '' ? 'thismonth' : $request->period;
        $center_id = $request->centre_id == 'All' ? ACL::getUserCentres() : [$request->centre_id];
        $periods = GeneralFunctions::GetPeriods();

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
        $patient_ids = array_map(function ($appointment) {
            return $appointment->patient_id;
        }, $appointments);

        $patient_data = [];
        $plans_check = PackageAdvances::where([
            'is_cancel' => '0',
        ])
            ->whereIn('patient_id', $patient_ids)
            ->whereIn('location_id', $center_id)
            ->select('patient_id', 'appointment_id')
            ->selectRaw('SUM(CASE WHEN cash_flow = "in" THEN cash_amount ELSE 0 END) as cash_in_sum')
            ->selectRaw('SUM(CASE WHEN cash_flow = "out" THEN cash_amount ELSE 0 END) as cash_out_sum')
            ->selectRaw('(SUM(CASE WHEN cash_flow = "in" THEN cash_amount ELSE 0 END) - SUM(CASE WHEN cash_flow = "out" THEN cash_amount ELSE 0 END)) as total')
            ->groupBy('patient_id')
            ->having('total', '>', 0)
            ->orderBy('id', 'DESC')
            ->get();
        $not_treatment = [];
        $is_treatment = [];
        $future_treatment = [];

        foreach ($plans_check as $data) {
            $treatments = Appointments::where([
                'appointment_type_id' => Config::get('constants.appointment_type_service'),
                'patient_id' => $data->patient_id,
            ])
                ->whereIn('location_id', ACL::getUserCentres())
                ->orderBy('created_at', 'DESC')
                ->get();
            $previous_treatments = collect($treatments)->where(['base_appointment_status_id' => 2])->Where('scheduled_date', '<=', Carbon::now()->subDays(30))->all();
            dd($treatments, $treatments->Where('scheduled_date', '<=', Carbon::now()->subDays(30)));
            $patient = Patients::where(['id' => $data->patient_id, 'user_type_id' => 3, 'active' => 1])->first();
            $data['patient_id'] = $patient->id;
            $data['name'] = $patient->name;
            $data['phone'] = $patient->phone;
            if (count($previous_treatments) > 0) {
                $check_future_treatments = collect($treatments)->Where('scheduled_date', '>', Carbon::now()->subDays(30));
                if ($check_future_treatments->isEmpty()) {
                    $data['is_treatment'] = 1;
                    $data['out_standing_balance'] = $data['total'];
                    array_push($is_treatment, $data);
                } else {
                    $data['is_treatment'] = 2;
                    $data['out_standing_balance'] = $data['total'];
                    array_push($is_treatment, $data);
                }
            } else {
                $data['is_treatment'] = 0;
                $data['out_standing_balance'] = $data['total'];
                array_push($not_treatment, $data);
            }
        }
        $patient_data = array_merge($is_treatment, $not_treatment);
        return ApiHelper::apiResponse($this->success, 'patient data', true, [
            'patient_data' => $patient_data
        ]);
    }
}
