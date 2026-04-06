<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

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
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create($id)
    {
        if (! Gate::allows('patients_plan_create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $appointmentinformation = Appointments::find($id);

        $locations = Locations::getActiveSorted(ACL::getUserCentres(), 'full_address');

        $patient = User::find($appointmentinformation->patient_id);

        $random_id = md5(time().rand(0001, 9999).rand(78599, 99999));
        $paymentmodes = PaymentModes::active()->where('type', '=', 'application')->pluck('name', 'id');

        $customdiscountrange = Settings::where('slug', '=', 'sys-discounts')->first();
        $range = explode(':', $customdiscountrange->data);

        return $this->successResponse('Records found.', [
            'patient' => $patient,
            'locations' => $locations,
            'random_id' => $random_id,
            'paymentmodes' => $paymentmodes,
            'range' => $range,
            'appointmentinformation' => $appointmentinformation,
        ], 200);
    }
}
