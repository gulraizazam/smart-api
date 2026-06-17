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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class AppointmentsPlansController extends Controller
{
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(int $id): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('plans.create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        // Round 4 IDOR sweep — tenant-scope the appointment lookup so a guessed
        // cross-tenant id can't be used to seed a plan-create flow against
        // another tenant's appointment + patient.
        $appointmentinformation = Appointments::where('id', $id)
            ->where('account_id', Auth::user()->account_id)
            ->first();
        if (! $appointmentinformation) {
            return $this->errorResponse('Appointment not found.', 404);
        }

        $locations = Locations::getActiveSorted(ACL::getUserCentres(), 'full_address');

        // Patient is derived from a tenant-checked appointment, so the
        // patient_id is implicitly trusted (it could not have been guessed).
        $patient = User::find($appointmentinformation->patient_id);

        // Round 4 Crypto-H1 — Str::random uses random_bytes() (CSPRNG).
        // The previous md5(time + bounded rand) seed had only ~26 bits of
        // entropy and was guessable, letting an attacker collide on the
        // random_id used to key this user's pending plan rows.
        $random_id = Str::random(32);
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
