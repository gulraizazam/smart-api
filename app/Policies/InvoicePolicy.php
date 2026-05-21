<?php

declare(strict_types=1);
namespace App\Policies;

use App\Models\User;

/**
 * Authorization policy for Invoices.
 *
 * Invoices may be generated from appointments or patient plans. Three
 * permission paths are kept in play:
 *
 *   - `invoices.*`              global module (Finance > Invoices)
 *   - `consultations.invoice.*` appointment-side (Consultations module)
 *   - `patient_invoices`        patient-card scope (Patients audit)
 *
 * Combining them here is intentional — anyone who can act on an
 * appointment's invoice should also see / download it from the global
 * invoices page (and vice versa).
 */
class InvoicePolicy
{
    /**
     * View the invoices list.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('invoices.list.view')
            || $user->can('consultations.invoice.view')
            || $user->can('patient_invoices');
    }

    /**
     * View a single invoice.
     */
    public function view(User $user): bool
    {
        return $user->can('invoices.detail.view')
            || $user->can('consultations.invoice.view')
            || $user->can('patient_invoices');
    }

    /**
     * Create / generate a new invoice.
     */
    public function create(User $user): bool
    {
        return $user->can('invoices.create')
            || $user->can('consultations.invoice.create');
    }

    /**
     * Update / edit an existing invoice.
     */
    public function update(User $user): bool
    {
        return $user->can('invoices.edit');
    }

    /**
     * Delete / void an invoice.
     */
    public function delete(User $user): bool
    {
        return $user->can('invoices.destroy')
            || $user->can('invoices.cancel');
    }

    /**
     * Download / print an invoice PDF.
     */
    public function download(User $user): bool
    {
        return $user->can('invoices.print')
            || $user->can('consultations.invoice.view')
            || $user->can('patient_invoices');
    }

    /**
     * Issue a refund against an invoice.
     */
    public function refund(User $user): bool
    {
        return $user->can('refunds.refund') || $user->can('patient_refund');
    }

    /**
     * Export invoice data to spreadsheet.
     */
    public function export(User $user): bool
    {
        return $user->can('invoices.export');
    }
}
