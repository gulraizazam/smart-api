<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Appointments;

use App\Enums\AppointmentType;
use App\Http\Controllers\Controller;
use App\Helpers\Widgets\AppointmentCheckesWidget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

/**
 * Base controller for appointment domain controllers.
 * Holds protected helper methods shared across multiple domain controllers.
 */
abstract class AppointmentBaseController extends Controller
{
    /**
     * Check rota availability for an appointment.
     */
    protected function checkRota($appointment, $request): array
    {

        $object = new \stdClass();
        // Always prefer scheduled_date and scheduled_time if available (from form)
        // Otherwise fall back to start (from calendar click)
        if ($request->has('scheduled_date') && $request->has('scheduled_time')) {

            $object->start = $request->scheduled_date.'T'.\Illuminate\Support\Carbon::parse($request->scheduled_time)->format('H:i:s');
        } elseif ($request->scheduled_date && $request->scheduled_time) {
            $object->start = $request->scheduled_date.'T'.\Illuminate\Support\Carbon::parse($request->scheduled_time)->format('H:i:s');

            } else {

            $object->start = $request->start;
        }

        $object->city_id = $request->city_id ?? '';
        $object->doctor_id = $request->doctor_id;
        $object->location_id = $request->location_id;
        $object->appointment_type = $appointment->appointment_type_id === AppointmentType::Consultancy->value ? 'consulting' : 'treatment';
        if ($appointment->appointment_type_id == Config::get('constants.appointment_type_consultancy')) {

        $rota = AppointmentCheckesWidget::AppointmentConsultancyCheckes($object);
        } else {

            $object->machine_id = $appointment->resource_id;

            $rota = AppointmentCheckesWidget::AppointmentAppointmentCheckesfromcalender($object);
        }

        return $rota;
    }
}
