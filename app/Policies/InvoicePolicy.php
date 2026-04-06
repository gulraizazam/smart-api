<?php

declare(strict_types=1);
namespace App\Policies;

use App\Models\User;

/**
 * Authorization policy for Invoices.
 *
 * Invoices may be generated from appointments or patient plans.
 * Both permission paths are covered here.
 */
class InvoicePolicy
{
    /**
     * View the invoices list.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('invoices_manage') || $user->can('appointments_invoice') || $user->can('patient_invoices');
    }

    /**
     * View a single invoice.
     */
    public function view(User $user): bool
    {
        return $user->can('invoices_manage') || $user->can('appointments_invoice') || $user->can('patient_invoices');
    }

    /**
     * Create / generate a new invoice.
     */
    public function create(User $user): bool
    {
        return $user->can('invoices_manage') || $user->can('appointments_invoice');
    }

    /**
     * Update / edit an existing invoice.
     */
    public function update(User $user): bool
    {
        return $user->can('invoices_manage');
    }

    /**
     * Delete / void an invoice.
     */
    public function delete(User $user): bool
    {
        return $user->can('invoices_manage') || $user->can('invoices_destroy');
    }

    /**
     * Download / print an invoice PDF.
     */
    public function download(User $user): bool
    {
        return $user->can('invoices_manage') || $user->can('appointments_invoice') || $user->can('patient_invoices');
    }

    /**
     * Issue a refund against an invoice.
     */
    public function refund(User $user): bool
    {
        return $user->can('refunds_manage') || $user->can('patient_refund');
    }

    /**
     * Export invoice data to spreadsheet.
     */
    public function export(User $user): bool
    {
        return $user->can('invoices_manage') || $user->can('invoices_export');
    }
}
