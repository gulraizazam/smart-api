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
        $status = array(
            'status' => $appointment_status
        );
        $continue_rota = array();
        $start = Carbon::parse($request->start)->format("Y-m-d");

        $today = Carbon::now()->toDateString();
        $resource_id = Resources::where(['external_id' => $request->doctor_id])->first();
        
        // If resource not found (doctor_id is null or invalid), return empty rota
        if (!$resource_id) {
            return ['status' => true, 'continue_rota' => []];
        }
        
        $resource_rota = ResourceHasRota::where([
            'resource_id' => $resource_id->id,
            'location_id' => $request->location_id,
            'active' => 1,
            'is_consultancy' => 1
        ])->get();
        foreach ($resource_rota as $resourceroata) {
            // Check if rota has a valid day record for this date (more reliable than checking parent date range)
            $hasRotaDay = ResourceHasRotaDays::where('resource_has_rota_id', $resourceroata->id)
                ->whereDate('date', $start)
                ->where('active', 1)
                ->exists();
            
            if ($hasRotaDay) {
                $continue_rota[0] = $resourceroata;
                break;
            }
            // Fallback to original date range check
            elseif (($start >= Carbon::parse($resourceroata->created_at)->format('Y-m-d')) && ($start <= $resourceroata->end)) {
                $continue_rota[0] = $resourceroata;
            }
        }
        $started_time = \Carbon\Carbon::parse($request->start)->format('Y-m-d H:i:s');
        $start_for_break_check = \Carbon\Carbon::parse($request->start)->format('H:i');
        
        \Log::info('Rota Check Debug', [
            'request_start' => $request->start,
            'started_time' => $started_time,
            'start_for_break_check' => $start_for_break_check,
            'continue_rota_count' => count($continue_rota),
        ]);
        
        if (count($continue_rota) > 0) {
            // Get ALL rota days for this date (supports multiple shifts per day)
            $all_rota_days = ResourceHasRotaDays::where([
                'resource_has_rota_id' => $continue_rota[0]->id,
                'active' => '1',
            ])->whereDate('date', $start)->get();
            
            if ($all_rota_days->isEmpty()) {
                $appointment_status = false;
                $message = 'Doctor rota is not available.';
                $status = [
                    'status' => $appointment_status,
                    'message' => $message,
                ];
            } else {
                // Check if appointment time falls within ANY of the shifts
                $isWithinAnyShift = false;
                $matchedRotaDay = null;
                $allShiftRanges = [];
                
                foreach ($all_rota_days as $rota_day) {
                    if ($rota_day->start_time) {
                        $rota_start = Carbon::parse($rota_day->start_time)->format('H:i');
                        $rota_end = Carbon::parse($rota_day->end_time)->format('H:i');
                        $allShiftRanges[] = "{$rota_start} - {$rota_end}";
                        
                        // Handle midnight end time: treat 00:00 as 24:00 for comparison
                        $rota_end_compare = ($rota_end === '00:00') ? '24:00' : $rota_end;
                        
                        // Check if appointment time is within this shift
                        if ($start_for_break_check >= $rota_start && $start_for_break_check < $rota_end_compare) {
                            $isWithinAnyShift = true;
                            $matchedRotaDay = $rota_day;
                            break;
                        }
                    }
                }
                
                if (!$isWithinAnyShift) {
                    $appointment_status = false;
                    $message = "Appointment time must be within available shifts: " . implode(', ', $allShiftRanges);
                    $status = [
                        'status' => $appointment_status,
                        'message' => $message,
                    ];
                } elseif ($matchedRotaDay && $matchedRotaDay->start_off) {
                    // Check if appointment is during break time
                    $start_break = Carbon::parse($matchedRotaDay->start_off)->format('H:i');
                    $end_break = Carbon::parse($matchedRotaDay->end_off)->format('H:i');
                    if (($start_for_break_check >= $start_break) && ($start_for_break_check < $end_break)) {
                        $appointment_status = false;
                        $message = "Appointment can't be created in break time.";
                        $status = [
                            'status' => $appointment_status,
                            'message' => $message,
                        ];
                    }
                }
                    
                // Check if appointment is during time off
                    if ($appointment_status) {
                        $timeOff = \App\Models\ResourceTimeOff::where('resource_id', $resource_id->id)
                            ->where('account_id', $resource_id->account_id)
                            ->where(function ($query) use ($request) {
                                $query->where('location_id', $request->location_id)
                                    ->orWhereNull('location_id');
                            })
                            ->whereDate('start_date', $start)
                            ->where(function ($query) use ($start_for_break_check) {
                                $query->where('start_time', '<=', $start_for_break_check . ':00')
                                    ->where('end_time', '>', $start_for_break_check . ':00');
                            })
                            ->first();
                        
                        if ($timeOff) {
                            $appointment_status = false;
                            $message = "Doctor is on " . ($timeOff->type_label ?? 'time off') . " during this time.";
                            $status = [
                                'status' => $appointment_status,
                                'message' => $message,
                            ];
                        }
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
        if (!Gate::allows('edit_after_arrived') && $start < $today && $back_date_config->data == 0) {
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

        $continue_rota_doctor = [];

        $start = Carbon::parse($request->start)->format('Y-m-d');
        $today = Carbon::now()->toDateString();

        $resource_id_doctor = Resources::where('external_id', '=', $request->doctor_id)->first();
        
        if (!$resource_id_doctor) {
            return [
                'status' => false,
                'message' => 'Doctor resource not found.',
            ];
        }

        $resource_rota_doctor = ResourceHasRota::where([
            ['resource_id', '=', $resource_id_doctor->id],
            ['location_id', '=', $request->location_id]
        ])->get();

        foreach ($resource_rota_doctor as $resourceroata) {
            if (($start >= Carbon::parse($resourceroata->created_at)->format('Y-m-d')) && ($start <= $resourceroata->end)) {
                $continue_rota_doctor[0] = $resourceroata;
            }
        }

        $started_time = \Carbon\Carbon::parse($request->start)->format('Y-m-d H:i:s');
        $start_for_break_check = \Carbon\Carbon::parse($request->start)->format('H:i');

        if (count($continue_rota_doctor) > 0) {
            // Get ALL rota days for this date (supports multiple shifts per day)
            $all_rota_days = ResourceHasRotaDays::where([
                ['resource_has_rota_id', '=', $continue_rota_doctor[0]->id],
                ['date', '=', $start],
                ['active', '=', '1'],
            ])->get();

            if ($all_rota_days->isEmpty()) {
                $appointment_status = false;
                $message = 'Doctor rota is not available for this time slot.';
                $status = [
                    'status' => $appointment_status,
                    'message' => $message,
                ];
            } else {
                // Check if appointment time falls within ANY of the shifts
                $isWithinAnyShift = false;
                $matchedRotaDay = null;
                $allShiftRanges = [];
                
                foreach ($all_rota_days as $rota_day) {
                    if ($rota_day->start_time) {
                        $rota_start = Carbon::parse($rota_day->start_time)->format('H:i');
                        $rota_end = Carbon::parse($rota_day->end_time)->format('H:i');
                        $allShiftRanges[] = "{$rota_start} - {$rota_end}";
                        
                        // Handle midnight end time: treat 00:00 as 24:00 for comparison
                        $rota_end_compare = ($rota_end === '00:00') ? '24:00' : $rota_end;
                        
                        // Check if appointment time is within this shift
                        if ($start_for_break_check >= $rota_start && $start_for_break_check < $rota_end_compare) {
                            $isWithinAnyShift = true;
                            $matchedRotaDay = $rota_day;
                            break;
                        }
                    }
                }
                
                if (!$isWithinAnyShift) {
                    $appointment_status = false;
                    $message = "Appointment time must be within available shifts: " . implode(', ', $allShiftRanges);
                    $status = [
                        'status' => $appointment_status,
                        'message' => $message,
                    ];
                } elseif ($matchedRotaDay && $matchedRotaDay->start_off) {
                    // Check if appointment is during break time
                    $start_break = Carbon::parse($matchedRotaDay->start_off)->format('H:i');
                    $end_break = Carbon::parse($matchedRotaDay->end_off)->format('H:i');

                    if (($start_for_break_check >= $start_break) && ($start_for_break_check < $end_break)) {
                        $appointment_status = false;
                        $message = 'Doctor is on break during this time.';
                        $status = [
                            'status' => $appointment_status,
                            'message' => $message,
                        ];
                    }
                }
                    
                // Check if appointment is during time off
                if ($appointment_status) {
                    $timeOff = \App\Models\ResourceTimeOff::where('resource_id', $resource_id_doctor->id)
                        ->where('account_id', $resource_id_doctor->account_id)
                        ->where(function ($query) use ($request) {
                            $query->where('location_id', $request->location_id)
                                ->orWhereNull('location_id');
                        })
                        ->whereDate('start_date', $start)
                        ->where(function ($query) use ($start_for_break_check) {
                            $query->where('start_time', '<=', $start_for_break_check . ':00')
                                ->where('end_time', '>', $start_for_break_check . ':00');
                        })
                        ->first();
                    
                    if ($timeOff) {
                        $appointment_status = false;
                        $message = "Doctor is on " . ($timeOff->type_label ?? 'time off') . " during this time.";
                        $status = [
                            'status' => $appointment_status,
                            'message' => $message,
                        ];
                    }
                }
            }
        } else {
            $appointment_status = false;
            $message = 'Doctor rota is not defined for this date.';
            $status = [
                'status' => $appointment_status,
                'message' => $message,
            ];
        }
        $back_date_config = Settings::whereSlug('sys-back-date-appointment')->select('data')->first();
        if (!Gate::allows('edit_after_arrived') && $start < $today && $back_date_config->data == 0) {
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

        $continue_rota_doctor = [];

        $start = Carbon::parse($request->start)->format('Y-m-d');
        $today = Carbon::now()->toDateString();

        $resource_id_doctor = Resources::where('external_id', '=', $request->doctor_id)->first();
        
        if (!$resource_id_doctor) {
            return [
                'status' => false,
                'message' => 'Doctor resource not found.',
            ];
        }

        $resource_rota_doctor = ResourceHasRota::where([
            ['resource_id', '=', $resource_id_doctor->id],
            ['location_id', '=', $request->location_id]
        ])->get();

        foreach ($resource_rota_doctor as $resourceroata) {
            if (($start >= $resourceroata->start) && ($start <= $resourceroata->end)) {
                $continue_rota_doctor[0] = $resourceroata;
            }
        }

        $started_time = \Carbon\Carbon::parse($request->start)->format('Y-m-d H:i:s');
        $start_for_break_check = \Carbon\Carbon::parse($request->start)->format('H:i');

        if (count($continue_rota_doctor) > 0) {
            // Get ALL rota days for this date (supports multiple shifts per day)
            $all_rota_days = ResourceHasRotaDays::where([
                ['resource_has_rota_id', '=', $continue_rota_doctor[0]->id],
                ['date', '=', $start],
                ['active', '=', '1'],
            ])->get();

            if ($all_rota_days->isEmpty()) {
                $appointment_status = false;
                $message = 'Doctor rota is not available for this time slot.';
                $status = [
                    'status' => $appointment_status,
                    'message' => $message,
                ];
            } else {
                // Check if appointment time falls within ANY of the shifts
                $isWithinAnyShift = false;
                $matchedRotaDay = null;
                $allShiftRanges = [];
                
                foreach ($all_rota_days as $rota_day) {
                    if ($rota_day->start_time) {
                        $rota_start = Carbon::parse($rota_day->start_time)->format('H:i');
                        $rota_end = Carbon::parse($rota_day->end_time)->format('H:i');
                        $allShiftRanges[] = "{$rota_start} - {$rota_end}";
                        
                        // Handle midnight end time: treat 00:00 as 24:00 for comparison
                        $rota_end_compare = ($rota_end === '00:00') ? '24:00' : $rota_end;
                        
                        // Check if appointment time is within this shift
                        if ($start_for_break_check >= $rota_start && $start_for_break_check < $rota_end_compare) {
                            $isWithinAnyShift = true;
                            $matchedRotaDay = $rota_day;
                            break;
                        }
                    }
                }
                
                if (!$isWithinAnyShift) {
                    $appointment_status = false;
                    $message = "Appointment time must be within available shifts: " . implode(', ', $allShiftRanges);
                    $status = [
                        'status' => $appointment_status,
                        'message' => $message,
                    ];
                } elseif ($matchedRotaDay && $matchedRotaDay->start_off) {
                    // Check if appointment is during break time
                    $start_break = Carbon::parse($matchedRotaDay->start_off)->format('H:i');
                    $end_break = Carbon::parse($matchedRotaDay->end_off)->format('H:i');

                    if (($start_for_break_check >= $start_break) && ($start_for_break_check < $end_break)) {
                        $appointment_status = false;
                        $message = 'Doctor is on break during this time.';
                        $status = [
                            'status' => $appointment_status,
                            'message' => $message,
                        ];
                    }
                }
                    
                // Check if appointment is during time off
                if ($appointment_status) {
                    $timeOff = \App\Models\ResourceTimeOff::where('resource_id', $resource_id_doctor->id)
                        ->where('account_id', $resource_id_doctor->account_id)
                        ->where(function ($query) use ($request) {
                            $query->where('location_id', $request->location_id)
                                ->orWhereNull('location_id');
                        })
                        ->whereDate('start_date', $start)
                        ->where(function ($query) use ($start_for_break_check) {
                            $query->where('start_time', '<=', $start_for_break_check . ':00')
                                ->where('end_time', '>', $start_for_break_check . ':00');
                        })
                        ->first();
                    
                    if ($timeOff) {
                        $appointment_status = false;
                        $message = "Doctor is on " . ($timeOff->type_label ?? 'time off') . " during this time.";
                        $status = [
                            'status' => $appointment_status,
                            'message' => $message,
                        ];
                    }
                }
            }
        } else {
            $appointment_status = false;
            $message = 'Doctor rota is not defined for this date.';
            $status = [
                'status' => $appointment_status,
                'message' => $message,
            ];
        }

        $back_date_config = Settings::whereSlug('sys-back-date-appointment')->select('data')->first();
        if (!Gate::allows('edit_after_arrived') && $start < $today && $back_date_config->data == 0) {
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
