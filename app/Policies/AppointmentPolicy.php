<?php

declare(strict_types=1);
namespace App\Policies;

use App\Models\User;

/**
 * Authorization policy for Appointments.
 *
 * All methods delegate to Spatie permission strings so the logic stays
 * in one place. The Gate::before() Super-Admin bypass in AppServiceProvider
 * means Super-Admin users automatically pass every check here.
 */
class AppointmentPolicy
{
    /**
     * View any appointment (consultancy or treatment list).
     */
    public function viewAny(User $user): bool
    {
        return $user->can('consultations.list.view') || $user->can('treatments.list.view');
    }

    /**
     * View a single appointment record.
     */
    public function view(User $user): bool
    {
        return $user->can('appointments_manage') || $user->can('appointments_view');
    }

    /**
     * Create a new appointment.
     */
    public function create(User $user): bool
    {
        return $user->can('appointments_manage');
    }

    /**
     * Update an existing appointment.
     */
    public function update(User $user): bool
    {
        return $user->can('appointments_manage');
    }

    /**
     * Delete / cancel an appointment.
     */
    public function delete(User $user): bool
    {
        return $user->can('appointments_destroy');
    }

    /**
     * Manage treatment-type appointments.
     */
    public function manageTreatments(User $user): bool
    {
        return $user->can('treatments.list.view');
    }

    /**
     * View treatment appointments list.
     */
    public function viewTreatments(User $user): bool
    {
        return $user->can('treatments.list.view');
    }

    /**
     * Issue / view invoices for an appointment.
     */
    public function invoice(User $user): bool
    {
        return $user->can('appointments_invoice');
    }

    /**
     * View patient contact details (phone number).
     */
    public function viewContact(User $user): bool
    {
        return $user->can('contact');
    }

    /**
     * Export appointment data.
     */
    public function export(User $user): bool
    {
        return $user->can('appointments_manage') || $user->can('appointments_export');
    }

    /**
     * Update appointment status (arrived, converted, no-show, etc.).
     */
    public function updateStatus(User $user): bool
    {
        return $user->can('appointments_manage') || $user->can('appointments_status_update');
    }

    /**
     * Manage schedules / time-slots. Used by AppointmentsController::schedule
     * (calendar drag-drop reschedule, shared between consultations and
     * treatments). Either module's dotted reschedule perm grants the move;
     * legacy `appointments_manage` kept as a transitional fallback for
     * older role configurations. The dangling `schedule_manage` ref was
     * removed in the business-working-days audit (that perm never existed
     * in DB; its real successor is `business_working_days.edit` which is
     * a separate concern).
     */
    public function manageSchedule(User $user): bool
    {
        return $user->can('appointments_manage')
            || $user->can('consultations.reschedule')
            || $user->can('treatments.reschedule');
    }
}
