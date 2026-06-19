<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Appointments;

use App\Models\Leads;
use App\Models\Patients;
use App\Models\Services;
use App\Models\Locations;
use App\Models\Invoices;
use App\Models\Appointments;
use App\Models\InvoiceStatuses;
use App\Models\AppointmentStatuses;
use App\Models\AppointmentTypes;
use App\Models\LeadStatuses;
use App\Models\LeadsServices;
use App\Models\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use App\Helpers\Filters;
use App\Helpers\ActivityLogger;
use App\Jobs\IndexSingleAppointmentJob;

class AppointmentStatusController extends AppointmentBaseController
{
    /**
     * Load all Appointment Statuses.
     */
    public function loadAppointmentStatuses(Request $request): \Illuminate\Http\JsonResponse
    {
        if ($request->appointment_status_id) {
            $appointment_statuses = AppointmentStatuses::getActiveSorted($request->appointment_status_id, Auth::user()->account_id);
            $appointment_status = AppointmentStatuses::find($request->appointment_status_id);
            if ($appointment_status) {
                $appointment_status = $appointment_status->toArray();
            }

            return $this->successResponse('Record found', [
                'dropdown' => !empty($appointment_statuses) ? $appointment_statuses : null,
                'count' => count($appointment_statuses),
                'appointment_status' => $appointment_status,
            ]);
        }

        return $this->errorResponse('Record found', 404, [
            'dropdown' => null,
            'count' => 0,
            'appointment_status' => null,
        ]);
    }

    public function loadAppointmentStatusData(Request $request): \Illuminate\Http\JsonResponse
    {
        if ($request->appointment_status_id && $request->base_appointment_status_id) {
            $appointment_status = AppointmentStatuses::find($request->appointment_status_id);
            if ($appointment_status) {
                $appointment_status = $appointment_status->toArray();
            }
            $base_appointment_status = AppointmentStatuses::find($request->base_appointment_status_id);
            if ($base_appointment_status) {
                $base_appointment_status = $base_appointment_status->toArray();
            }

            return $this->successResponse('Record Found', [
                'appointment_status' => !empty($appointment_status) ? $appointment_status : null,
                'base_appointment_status' => !empty($base_appointment_status) ? $base_appointment_status : null,
            ]);
        }

        return $this->errorResponse('Record Found', 404, [
            'appointment_status' => null,
            'base_appointment_status' => null,
        ]);
    }

    /**
     * Load all Appointment Statuses.
     */
    public function showAppointmentStatuses(Request $request): \Illuminate\Http\JsonResponse
    {
        $appointment = Appointments::find($request->id);
        if (! $appointment) {
            return $this->errorResponse('No record found', 200);
        }
        // Fix: was hardcoded to account_id=1 — would return an empty
        // map for every other tenant and silently break the SPA's
        // is_comment lookup. Now scopes to the caller's account.
        $base_appointments = AppointmentStatuses::where(['account_id' => Auth::user()->account_id])->select('id', 'parent_id', 'is_comment')->get()->keyBy('id');

        // Base status dropdown for the SPA's status-change dialog.
        //
        // Hides system-managed (auto-only) statuses — `is_arrived`,
        // `is_converted`, `is_unscheduled` — because the operator
        // cannot set them manually:
        //   • Arrived       → fired when the consultancy/treatment
        //                     invoice is paid (or a session is
        //                     consumed against a plan).
        //   • Converted     → fired on the first package payment.
        //   • Un-Scheduled  → derived from a missing scheduled
        //                     date/time, recovered automatically.
        //
        // The legacy `AppointmentStatuses::getBaseActiveSorted` filter
        // achieved the same intent with a `name != 'Arrived'` substring
        // match — brittle to renames and missed Un-Scheduled. The
        // flag-based predicates here are the robust replacement.
        // `is_cancelled` rows stay (Cancelled is a manual choice).
        $base_appointment_statuses = AppointmentStatuses::where('account_id', Auth::user()->account_id)
            ->where('active', 1)
            ->where(function ($q) {
                $q->whereNull('parent_id')->orWhere('parent_id', 0);
            })
            ->where(function ($q) {
                $q->where('is_arrived', '!=', 1)->orWhereNull('is_arrived');
            })
            ->where(function ($q) {
                $q->where('is_converted', '!=', 1)->orWhereNull('is_converted');
            })
            ->where(function ($q) {
                $q->where('is_unscheduled', '!=', 1)->orWhereNull('is_unscheduled');
            })
            ->orderBy('sort_no', 'asc')
            ->pluck('name', 'id');

        // Root statuses store parent_id as NULL. If the current status has a
        // parent, return that parent's children so the child-status dropdown
        // can show siblings. Otherwise return an empty array (the front-end
        // uses an empty response to hide the child-status section).
        $appointment_statuses = [];
        $currentStatusParentId = $appointment->appointment_status->parent_id ?? null;
        if (isset($appointment->appointment_status) && !empty($currentStatusParentId)) {
            $appointment_statuses = AppointmentStatuses::getActiveSorted($currentStatusParentId, Auth::user()->account_id);
        }

        return $this->successResponse('Record found', [
            'appointment' => $appointment,
            'base_appointment_statuses' => $base_appointment_statuses,
            'appointment_statuses' => $appointment_statuses,
            'base_appointments' => $base_appointments,
            'appointment_status_not_show' => config('constants.appointment_status_not_show'),
            'cancellation_reason_other_reason' => config('constants.cancellation_reason_other_reason'),
        ]);
    }

    /**
     * Update Appointment Status
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeAppointmentStatuses(Request $request): \Illuminate\Http\JsonResponse
    {
        if (! \Illuminate\Support\Facades\Gate::allows('appointments_manage')) {
            return $this->errorResponse('You are not authorized to perform this action.', 403);
        }

        $data = $request->all();
        $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();
        $appointment = Appointments::byAccount(auth()->user()->account_id)->find($request->id);
        if (! $appointment) {
            return $this->errorResponse('Appointment not found', 200);
        }

        // Store old status for activity logging
        $oldStatusId = $appointment->base_appointment_status_id;
        $oldStatus = AppointmentStatuses::find($oldStatusId);
        $appointment_type = AppointmentTypes::where('slug', '=', 'consultancy')->first();
        $appointment_type_2 = AppointmentTypes::where('slug', '=', 'treatment')->first();
        $counterglobal = Settings::where('slug', '=', 'sys-appointmentrescheduledcounter')->first();
        $invoiceexit = Invoices::where([
            ['invoice_status_id', '=', $invoicestatus->id],
            ['appointment_id', '=', $data['id']],
        ])->get();
        if ($data['base_appointment_status_id'] == Config::get('constants.appointment_status_arrived')) {
            if (empty($invoiceexit)) {
                return $this->errorResponse('Kindly pay invoice first!', 200);
            }
        }
        if ($data['base_appointment_status_id'] != Config::get('constants.appointment_status_arrived')) {
            if (count($invoiceexit) == 1) {
                return $this->errorResponse('Invoice paid, you not able to change status!', 200);
            }
        }
        if ($appointment_type->id == $appointment->appointment_type_id) {
            if ($appointment->base_appointment_status_id == Config::get('constants.appointment_status_not_interested')) {
                if ($data['base_appointment_status_id'] != Config::get('constants.appointment_status_not_interested')) {
                    $data['counter'] = 0;
                }
            }
        }
        // Set Allow Message Flag
        if (isset($data['base_appointment_status_id'])) {
            $appointment_status = AppointmentStatuses::getData((int) $data['base_appointment_status_id']);
            $data['appointment_status_allow_message'] = $appointment_status->allow_message;
        }
        if (! isset($data['appointment_status_id']) || $data['appointment_status_id'] == '') {
            $data['appointment_status_id'] = $data['base_appointment_status_id'];
        }
        // Set Comments
        if (isset($data['reason']) && ! $data['reason']) {
            $data['reason'] = null;
        }
        // Converted By
        // $data['converted_by'] = Auth::user()->id;
        $data['updated_by'] = Auth::user()->id;
        $data['updated_at'] = Filters::getCurrentTimeStamp();
        // No-show counter handling: when the operator marks an
        // appointment "Didn't show up" (`appointment_status_not_show`)
        // for the Nth time (where N matches the
        // `sys-appointmentrescheduledcounter` setting), automatically
        // promote the row to "Not Interested" — operators stop
        // chasing patients who never come. Below the threshold we just
        // bump the counter and keep the No-show status.
        //
        // Pre-fix this block was duplicated (two if/update pairs back-to
        // back); the second pass re-evaluated against stale
        // `$appointment->counter` and silently fired a second update
        // that rewrote the activity log entry. Collapsed to one pass.
        if ($appointment_type->id == $appointment->appointment_type_id
            && $data['base_appointment_status_id'] == Config::get('constants.appointment_status_not_show')) {
            if ($appointment->counter == $counterglobal->data) {
                $data['base_appointment_status_id'] = Config::get('constants.appointment_status_not_interested');
                $appointment_childstatus_not_interested = AppointmentStatuses::where('parent_id', '=', Config::get('constants.appointment_status_not_interested'))->first();
                if ($appointment_childstatus_not_interested) {
                    $data['appointment_status_id'] = $appointment_childstatus_not_interested->id;
                } else {
                    $data['appointment_status_id'] = Config::get('constants.appointment_status_not_interested');
                }
            } else {
                $data['counter'] = $appointment->counter + 1;
            }
        }
        $appointment->update($data);
        $appointment_status_name = AppointmentStatuses::where('id', '=', $data['base_appointment_status_id'])->first();

        /** When appointment status will be 'No Show' then lead status will be automatically changed to 'Open' */
        if ($data['base_appointment_status_id'] == 3) {
            $openStatus = LeadStatuses::where(['account_id' => Auth::user()->account_id, 'is_default' => 1])->first();
            if ($openStatus && $appointment->lead_id) {
                $lead = Leads::findOrFail($appointment->lead_id);
                $lead->lead_status_id = $openStatus->id;
                $lead->save();
                // Update lead_services status to Open (No Show)
                LeadsServices::where([
                    'lead_id' => $appointment->lead_id,
                    'service_id' => $appointment->service_id,
                ])->update(['lead_status_id' => $openStatus->id]);
            }
        }
        if ($data['base_appointment_status_id'] == 1) {
            $bookedStatus = LeadStatuses::where(['account_id' => Auth::user()->account_id, 'is_booked' => 1])->first();
            if ($bookedStatus && $appointment->lead_id) {
                $lead = Leads::findOrFail($appointment->lead_id);
                $lead->lead_status_id = $bookedStatus->id;
                $lead->save();
                // Update lead_services status to Booked
                LeadsServices::where([
                    'lead_id' => $appointment->lead_id,
                    'service_id' => $appointment->service_id,
                ])->update(['lead_status_id' => $bookedStatus->id]);
            }
        }
        if ($data['base_appointment_status_id'] == 14) {
            $arrivedStatus = LeadStatuses::where(['account_id' => Auth::user()->account_id, 'is_arrived' => 1])->first();
            if ($arrivedStatus && $appointment->lead_id) {
                Leads::where(['id' => $appointment->lead_id])->update(['lead_status_id' => $arrivedStatus->id]);
                // Update lead_services status to Arrived
                LeadsServices::where([
                    'lead_id' => $appointment->lead_id,
                    'service_id' => $appointment->service_id,
                ])->update(['lead_status_id' => $arrivedStatus->id]);

                // Send Meta CAPI event for arrived status
                // $leadRecord = Leads::find($appointment->lead_id);
                // if ($leadRecord) {
                //     try {
                //         $metaService = new MetaConversionApiService();
                //         $metaService->sendLeadStatus(
                //             $leadRecord->phone,
                //             'arrived',
                //             $leadRecord->meta_lead_id,
                //             $leadRecord->email
                //         );
                //     } catch (\Exception $e) {
                //         \Log::error('Meta CAPI arrived event failed: ' . $e->getMessage());
                //     }
                // }
            }
        }

        /**
         * Dispatch Elastic Search Index
         */
        IndexSingleAppointmentJob::dispatch([
                'account_id' => Auth::user()->account_id,
                'appointment_id' => $appointment->id,
            ]);

        // Log appointment status change activity
        $newStatus = AppointmentStatuses::find($data['base_appointment_status_id']);
        if ($oldStatus && $newStatus && $oldStatusId != $data['base_appointment_status_id']) {
            $patient = Patients::find($appointment->patient_id);
            $location = Locations::with('city')->find($appointment->location_id);
            $service = Services::find($appointment->service_id);
            ActivityLogger::logAppointmentStatusChange($appointment, $patient, $oldStatus, $newStatus, $location, $service);
        }

        return $this->successResponse('Status has been change successfully!', ['appontment_type_id' => $request->appointment_type_id]);
    }
}
