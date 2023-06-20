<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\ACL;
use App\Http\Controllers\Controller;
use App\Models\Appointments;
use App\Models\Locations;
use App\Models\PaymentModes;
use App\Models\Settings;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class AppointmentsPlansController extends Controller
{
    public $success;

    public $error;

    public $unauthorized;

    public function __construct()
    {
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create($id)
    {
        if (! Gate::allows('patients_plan_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        $appointmentinformation = Appointments::find($id);

        $locations = Locations::getActiveSorted(ACL::getUserCentres(), 'full_address');

        $patient = User::find($appointmentinformation->patient_id);

        $random_id = md5(time().rand(0001, 9999).rand(78599, 99999));
        $paymentmodes = PaymentModes::active()->where('type', '=', 'application')->pluck('name', 'id');

        $customdiscountrange = Settings::where('slug', '=', 'sys-discounts')->first();
        $range = explode(':', $customdiscountrange->data);

        return ApiHelper::apiResponse($this->success, 'Records found.', true, [
            'patient' => $patient,
            'locations' => $locations,
            'random_id' => $random_id,
            'paymentmodes' => $paymentmodes,
            'range' => $range,
            'appointmentinformation' => $appointmentinformation,
        ]);
    }
}
