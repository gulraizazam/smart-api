<?php

declare(strict_types=1);
namespace App\Policies;

use App\Models\User;

/**
 * Authorization policy for Patient (User) records.
 *
 * Covers patient management, financial data, plans, invoices, and forms.
 */
class PatientPolicy
{
    /**
     * View the patient list.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('patients.list.view');
    }

    /**
     * View a single patient profile.
     */
    public function view(User $user): bool
    {
        return $user->can('patients.card.view');
    }

    /**
     * Create a new patient. Direct creation is currently disabled — patients
     * are created as a side-effect of booking — but the policy retains the
     * gate so future direct-create paths can opt in. `patients.edit` is the
     * pragmatic reuse since creation requires the same write privilege.
     */
    public function create(User $user): bool
    {
        return $user->can('patients.edit');
    }

    /**
     * Update a patient record.
     */
    public function update(User $user): bool
    {
        return $user->can('patients.edit');
    }

    /**
     * Delete / deactivate a patient.
     */
    public function delete(User $user): bool
    {
        return $user->can('patients.delete');
    }

    /**
     * View patient financial summary (advances, balances).
     */
    public function viewFinance(User $user): bool
    {
        return $user->can('patient_finance');
    }

    /**
     * View / manage patient plans and packages.
     */
    public function viewPlans(User $user): bool
    {
        return $user->can('patient_plans') || $user->can('plans.create');
    }

    /**
     * Create a new plan for a patient.
     */
    public function createPlan(User $user): bool
    {
        return $user->can('plans.create');
    }

    /**
     * View patient invoices.
     */
    public function viewInvoices(User $user): bool
    {
        return $user->can('patient_invoices') || $user->can('appointments_invoice');
    }

    /**
     * View / manage patient documents.
     */
    public function manageDocuments(User $user): bool
    {
        return $user->can('patients.documents.view');
    }

    /**
     * View patient custom form feedbacks.
     */
    public function viewCustomForms(User $user): bool
    {
        return $user->can('custom_form_feedbacks_manage') || $user->can('patients_customform_edit');
    }

    /**
     * Submit a custom form feedback for a patient.
     */
    public function submitCustomForm(User $user): bool
    {
        return $user->can('custom_form_feedbacks_manage') || $user->can('patients_customform_create');
    }

    /**
     * Edit an existing custom form feedback.
     */
    public function editCustomForm(User $user): bool
    {
        return $user->can('custom_form_feedbacks_edit') || $user->can('patients_customform_edit');
    }

    /**
     * View patient contact information (phone/email).
     */
    public function viewContact(User $user): bool
    {
        return $user->can('patients.list.view_contact');
    }

    /**
     * Issue a refund for a patient payment.
     */
    public function refund(User $user): bool
    {
        return $user->can('patient_refund') || $user->can('refunds.refund');
    }

    /**
     * Export patient data.
     */
    public function export(User $user): bool
    {
        return $user->can('patients.list.view') || $user->can('patients_export');
    }
}
