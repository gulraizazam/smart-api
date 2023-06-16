<?php

namespace App\Helpers\Widgets;

use App\Models\ResourceHasRota;
use App\Models\ResourceHasRotaDays;
use App\Models\Resources;
use App\Models\Settings;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;

class AppointmentCheckesWidget
{
    /*
     * Check the consultancy can book or not
     * @param: $request
     * @return: (mixed) $result
     */
    public static function AppointmentConsultancyCheckes($request)
    {
        $appointment_status = true;
        $status = [
            'status' => $appointment_status,
        ];
        $continue_rota = [];
        $start = Carbon::parse($request->start)->format('Y-m-d');

        $today = Carbon::now()->toDateString();
        $resource_id = Resources::where('external_id', '=', $request->doctor_id)->first();
        $resource_rota = ResourceHasRota::where([
            'resource_id' => $resource_id->id,
            'location_id' => $request->location_id,
            'active' => 1,
        ])->get();
        foreach ($resource_rota as $resourceroata) {
            if (($start >= $resourceroata->start) && ($start <= $resourceroata->end)) {
                $continue_rota[0] = $resourceroata;
            }
        }
        $started_time = \Carbon\Carbon::parse($request->start)->format('Y-m-d H:i:s');
        $start_for_break_check = \Carbon\Carbon::parse($request->start)->format('H:i');
        if (count($continue_rota) > 0) {
            $resource_has_rota_days = ResourceHasRotaDays::where([
                'resource_has_rota_id' => $continue_rota[0]->id,
                'date' => $start,
                'active' => '1',
            ])->first();
            if (! $resource_has_rota_days) {
                $appointment_status = false;
                $message = 'Doctor rota is not available.';
                $status = [
                    'status' => $appointment_status,
                    'message' => $message,
                ];
            } else {
                if ($resource_has_rota_days->start_time) {
                    if ($resource_has_rota_days->start_off) {
                        $start_break = Carbon::parse($resource_has_rota_days->start_off)->format('H:i');
                        $end_break = Carbon::parse($resource_has_rota_days->end_off)->format('H:i');
                        if (($start_for_break_check >= $start_break) && ($start_for_break_check < $end_break)) {
                            $appointment_status = false;
                            $message = "Appointment can't be created in break time.";
                            $status = [
                                'status' => $appointment_status,
                                'message' => $message,
                            ];
                        }
                    }
                } else {
                    $appointment_status = false;
                    $message = 'Doctor rota is not available.';
                    $status = [
                        'status' => $appointment_status,
                        'message' => $message,
                    ];
                }
            }
        } else {
            $appointment_status = false;
            $message = 'Doctor Rota Not Define';
            $status = [
                'status' => $appointment_status,
                'message' => $message,
            ];
        }
        $back_date_config = Settings::whereSlug('sys-back-date-appointment')->select('data')->first();
        if (! Gate::allows('edit_after_arrived') && $start < $today && $back_date_config->data == 0) {
            $appointment_status = false;
            $message = 'Sorry! You cannot schedule the appointment in back date.';
            $status = [
                'status' => $appointment_status,
                'message' => $message,
            ];
        }

        return $status;
    }

    /*
     * Check the treatment can book or not
     * @param: $request
     * @return: (mixed) $result
     */
    public static function AppointmentAppointmentCheckesfromcalender($request)
    {
        $appointment_status = true;
        $status = [
            'status' => $appointment_status,
        ];

        $continue_rota_machine = [];
        $continue_rota_doctor = [];

        $start = Carbon::parse($request->start)->format('Y-m-d');
        $today = Carbon::now()->toDateString();

        $resource_id_doctor = Resources::where('external_id', '=', $request->doctor_id)->first();

        $resource_rota_doctor = ResourceHasRota::where([
            ['resource_id', '=', $resource_id_doctor->id],
            ['location_id', '=', $request->location_id],
        ])->get();

        $resource_rota_machine = ResourceHasRota::where('resource_id', '=', $request->machine_id)->get();

        foreach ($resource_rota_doctor as $resourceroata) {
            if (($start >= $resourceroata->start) && ($start <= $resourceroata->end)) {
                $continue_rota_doctor[0] = $resourceroata;
            }
        }

        foreach ($resource_rota_machine as $resourceroata_machine) {
            if (($start >= $resourceroata_machine->start) && ($start <= $resourceroata_machine->end)) {
                $continue_rota_machine[0] = $resourceroata_machine;
            }
        }
        $started_time = \Carbon\Carbon::parse($request->start)->format('Y-m-d H:i:s');

        $start_for_break_check = \Carbon\Carbon::parse($request->start)->format('H:i');

        if (count($continue_rota_doctor) > 0 && count($continue_rota_machine) > 0) {

            $resource_has_rota_days_doctor = ResourceHasRotaDays::where([
                ['resource_has_rota_id', '=', $continue_rota_doctor[0]->id],
                ['date', '=', $start],
                ['active', '=', '1'],
                ['resource_has_rota_days.start_timestamp', '<=', $started_time],
                ['resource_has_rota_days.end_timestamp', '>', $started_time],
            ])->first();

            $resource_has_rota_days_machine = ResourceHasRotaDays::where([
                ['resource_has_rota_id', '=', $continue_rota_machine[0]->id],
                ['date', '=', $start],
                ['active', '=', '1'],
                ['resource_has_rota_days.start_timestamp', '<=', $started_time],
                ['resource_has_rota_days.end_timestamp', '>', $started_time],
            ])->first();

            if (! $resource_has_rota_days_doctor || ! $resource_has_rota_days_machine) {
                $appointment_status = false;
                $message = 'Doctor or Machine rota is not available.';
                $status = [
                    'status' => $appointment_status,
                    'message' => $message,
                ];
            } else {
                if (! $resource_has_rota_days_doctor->start_time || ! $resource_has_rota_days_machine->start_time) {
                    $appointment_status = false;
                    $message = 'Doctor or Machine rota is not available.';
                    $status = [
                        'status' => $appointment_status,
                        'message' => $message,
                    ];
                } else {
                    if ($resource_has_rota_days_doctor->start_time) {
                        if ($resource_has_rota_days_doctor->start_off) {

                            $start_break = Carbon::parse($resource_has_rota_days_doctor->start_off)->format('H:i');
                            $end_break = Carbon::parse($resource_has_rota_days_doctor->end_off)->format('H:i');

                            if (($start_for_break_check >= $start_break) && ($start_for_break_check < $end_break)) {
                                $appointment_status = false;
                                $message = 'Doctor or Machine rota is not available.';
                                $status = [
                                    'status' => $appointment_status,
                                    'message' => $message,
                                ];
                            }
                        }
                    } else {
                        $appointment_status = false;
                        $message = 'Doctor rota is not available.';
                        $status = [
                            'status' => $appointment_status,
                            'message' => $message,
                        ];
                    }
                }
            }
        } else {
            $appointment_status = false;
            $message = 'Doctor or Machine rota is not available.';
            $status = [
                'status' => $appointment_status,
                'message' => $message,
            ];
        }
        $back_date_config = Settings::whereSlug('sys-back-date-appointment')->select('data')->first();
        if (! Gate::allows('edit_after_arrived') && $start < $today && $back_date_config->data == 0) {
            $appointment_status = false;
            $message = 'Sorry! You cannot schedule the appointment in back date.';
            $status = [
                'status' => $appointment_status,
                'message' => $message,
            ];
        }

        return $status;
    }

    /*
    * Check the treatment can book or not
    * @param: $request
    * @return: (mixed) $result
    */
    public static function AppointmentAppointmentCheckesfromcard($request)
    {
        $appointment_status = true;
        $status = [
            'status' => $appointment_status,
        ];

        $continue_rota_machine = [];
        $continue_rota_doctor = [];

        $start = Carbon::parse($request->start)->format('Y-m-d');
        $today = Carbon::now()->toDateString();

        $resource_id_doctor = Resources::where('external_id', '=', $request->doctor_id)->first();
        $resource_rota_doctor = ResourceHasRota::where([
            ['resource_id', '=', $resource_id_doctor->id],
            ['location_id', '=', $request->location_id],
        ])->get();

        $resource_rota_machine = ResourceHasRota::where('resource_id', '=', $request->resourceId)->get();

        foreach ($resource_rota_doctor as $resourceroata) {
            if (($start >= $resourceroata->start) && ($start <= $resourceroata->end)) {
                $continue_rota_doctor[0] = $resourceroata;
            }
        }

        foreach ($resource_rota_machine as $resourceroata_machine) {
            if (($start >= $resourceroata_machine->start) && ($start <= $resourceroata_machine->end)) {
                $continue_rota_machine[0] = $resourceroata_machine;
            }
        }

        $started_time = \Carbon\Carbon::parse($request->start)->format('Y-m-d H:i:s');

        $start_for_break_check = \Carbon\Carbon::parse($request->start)->format('h:i:A');

        if (count($continue_rota_doctor) > 0 && count($continue_rota_machine) > 0) {

            $resource_has_rota_days_doctor = ResourceHasRotaDays::where([
                ['resource_has_rota_id', '=', $continue_rota_doctor[0]->id],
                ['date', '=', $start],
                ['active', '=', '1'],
                ['resource_has_rota_days.start_timestamp', '<=', $started_time],
                ['resource_has_rota_days.end_timestamp', '>', $started_time],
            ])->first();
            $resource_has_rota_days_machine = ResourceHasRotaDays::where([
                ['resource_has_rota_id', '=', $continue_rota_doctor[0]->id],
                ['date', '=', $start],
                ['active', '=', '1'],
                ['resource_has_rota_days.start_timestamp', '<=', $started_time],
                ['resource_has_rota_days.end_timestamp', '>', $started_time],
            ])->first();
            if (! $resource_has_rota_days_doctor || ! $resource_has_rota_days_machine) {
                $appointment_status = false;
                $message = 'Doctor or Machine rota is not available.';
                $status = [
                    'status' => $appointment_status,
                    'message' => $message,
                ];
            } else {
                if (! $resource_has_rota_days_doctor->start_time || ! $resource_has_rota_days_machine->start_time) {
                    $appointment_status = false;
                    $message = 'Doctor or Machine rota is not available.';
                    $status = [
                        'status' => $appointment_status,
                        'message' => $message,
                    ];
                } else {
                    if ($resource_has_rota_days_doctor->start_time) {
                        if ($resource_has_rota_days_doctor->start_off) {
                            if (($start_for_break_check >= $resource_has_rota_days_doctor->start_off) && ($start_for_break_check <= $resource_has_rota_days_doctor->end_off)) {
                                $appointment_status = false;
                                $message = 'Doctor or Machine rota is not available.';
                                $status = [
                                    'status' => $appointment_status,
                                    'message' => $message,
                                ];
                            }
                        }
                    } else {
                        $appointment_status = false;
                        $message = 'Doctor rota is not available.';
                        $status = [
                            'status' => $appointment_status,
                            'message' => $message,
                        ];
                    }
                }
            }

        } else {
            $appointment_status = false;
            $message = 'Doctor or Machine rota is not available.';
            $status = [
                'status' => $appointment_status,
                'message' => $message,
            ];
        }

        $back_date_config = Settings::whereSlug('sys-back-date-appointment')->select('data')->first();
        if (! Gate::allows('edit_after_arrived') && $start < $today && $back_date_config->data == 0) {
            $appointment_status = false;
            $message = 'Sorry! You cannot schedule the appointment in back date.';
            $status = [
                'status' => $appointment_status,
                'message' => $message,
            ];
        }

        return $status;
    }
}
