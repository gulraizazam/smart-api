<?php

declare(strict_types=1);

namespace App\Services\Conversion;

use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\Packages;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for "is this appointment still validly
 * converted?" given the current refund / payment-delete / plan-delete
 * state.
 *
 * **Policy** (set by finance, 2026-05-03):
 *   - Q1: Net-position view. A lead/appointment "Converted" status
 *     reflects current state, NOT historical fact.
 *   - Q2: Any net cash ≤ 0 across the appointment's converted plans
 *     → revert.
 *   - Q3: Average Conversion Value uses net cash (refunds subtracted).
 *   - Q4: Same revert trigger fires on payment-delete (even if the
 *     plan itself stays as an empty shell).
 *
 * **All trigger paths funnel through this service:**
 *   - `RefundService::createRefund`  (after refund row written)
 *   - `PlanService::deletePayment`   (after payment soft-deleted)
 *   - `PlanService::deletePlan`      (after plan soft-deleted)
 *
 * **All read paths funnel through this service for net cash:**
 *   - `ConversionService::getValidatedConversions` Step 6
 *   - `LeadGenderDeepDiveMetric` (revenue panel)
 *   - `WrongConversionService::isValidConversion` (refunded-ghost check)
 *
 * Reverts re-use `WrongConversionService::resetAppointment` (the
 * single revert helper that already clears `converted_at`,
 * `appointment_status_id`, all three Meta CAPI dedup flags, and
 * cascades to `leads.lead_status_id` + `leads_services.lead_status_id`).
 * Don't add a second revert path — extend that one if behaviour needs
 * to change.
 */
class ConversionStateService
{
    /**
     * Net cash for a single package: SUM(cash_in) - SUM(cash_out where
     * is_refund=1) over non-deleted, non-zero advances.
     *
     * Soft-deleted advances are excluded — that's how `deletePayment`
     * propagates into the "should revert" decision (Q4).
     */
    public static function netCashForPlan(int $packageId): float
    {
        $row = DB::table('package_advances')
            ->where('package_id', $packageId)
            ->whereNull('deleted_at')
            ->where('cash_amount', '!=', 0)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN cash_flow = 'in' THEN cash_amount ELSE 0 END), 0) AS cash_in,
                COALESCE(SUM(CASE WHEN cash_flow = 'out' AND is_refund = 1 THEN cash_amount ELSE 0 END), 0) AS cash_out_refund
            ")
            ->first();

        if (! $row) {
            return 0.0;
        }

        return (float) $row->cash_in - (float) $row->cash_out_refund;
    }

    /**
     * Net cash across every non-deleted package linked to this
     * appointment. Multi-plan patients: we sum across all of them so
     * a refund on one plan doesn't trigger a revert if another plan
     * still has positive cash on the same appointment.
     */
    public static function netCashForAppointment(int $appointmentId): float
    {
        $packageIds = Packages::where('appointment_id', $appointmentId)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->all();

        if (empty($packageIds)) {
            return 0.0;
        }

        $row = DB::table('package_advances')
            ->whereIn('package_id', $packageIds)
            ->whereNull('deleted_at')
            ->where('cash_amount', '!=', 0)
            ->selectRaw("
                COALESCE(SUM(CASE WHEN cash_flow = 'in' THEN cash_amount ELSE 0 END), 0) AS cash_in,
                COALESCE(SUM(CASE WHEN cash_flow = 'out' AND is_refund = 1 THEN cash_amount ELSE 0 END), 0) AS cash_out_refund
            ")
            ->first();

        return (float) ($row->cash_in ?? 0) - (float) ($row->cash_out_refund ?? 0);
    }

    /**
     * Should this appointment be reverted from Converted? Returns true
     * iff:
     *   - The appointment is currently Converted (`is_converted=1`
     *     status flag), AND
     *   - Net cash across all its packages is ≤ 0.
     *
     * Q2 policy: strict zero (any net ≤ 0 reverts). No tolerance band.
     */
    public static function shouldRevert(int $appointmentId): bool
    {
        $appointment = Appointments::find($appointmentId);
        if (! $appointment) {
            return false;
        }

        $apptStatus = AppointmentStatuses::find($appointment->appointment_status_id);
        $isConverted = (bool) ($apptStatus?->is_converted ?? false);
        if (! $isConverted) {
            return false;
        }

        return self::netCashForAppointment($appointmentId) <= 0;
    }

    /**
     * Trigger entry point — called from refund / deletePayment /
     * deletePlan flows AFTER they commit their write. If the
     * appointment now meets the revert criteria, hand off to
     * `WrongConversionService::resetAppointment` (the single revert
     * helper).
     *
     * Returns true if a revert actually happened.
     *
     * Failures are caught and logged — best-effort; never roll back
     * the parent transaction (refund still succeeds even if the
     * cascading revert fails).
     */
    public static function revertIfNeeded(int $appointmentId): bool
    {
        try {
            if (! self::shouldRevert($appointmentId)) {
                return false;
            }

            // Defer to the canonical revert helper. Don't duplicate
            // its lead/leads_services/Meta-flag cascade here.
            $result = (new WrongConversionService())->resetAppointment($appointmentId);
            return (bool) ($result['success'] ?? false);
        } catch (\Throwable $e) {
            \Log::error('ConversionStateService::revertIfNeeded failed: ' . $e->getMessage(), [
                'appointment_id' => $appointmentId,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return false;
        }
    }

    /**
     * Resolve the appointment id from a package id — convenience for
     * trigger sites (refund / deletePayment) that have a package
     * context but need the appointment for the revert.
     */
    public static function appointmentIdForPackage(int $packageId): ?int
    {
        $package = Packages::find($packageId);
        return $package?->appointment_id ? (int) $package->appointment_id : null;
    }
}
