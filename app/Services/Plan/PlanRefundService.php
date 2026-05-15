<?php

declare(strict_types=1);

namespace App\Services\Plan;

use App\Enums\ActivityLogTier;
use App\Helpers\Filters;
use App\Models\Activity;
use App\Models\Appointments;
use App\Models\InvoiceDetails;
use App\Models\Invoices;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\PackageBundles;
use App\Models\PackageService;
use App\Models\Packages;
use App\Models\PaymentModes;
use App\Models\Services;
use App\Models\Settings;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class PlanRefundService
{
    // ──────────────────────────────────────────────────
    //  Refund
    // ──────────────────────────────────────────────────

    /**
     * Get refund form data for a package.
     */
    public function getRefundFormData(int $packageId): array
    {
        $returnTaxAmount = '';

        $packageInformation = Packages::find($packageId);
        $patient = User::whereId($packageInformation->patient_id)->first();

        /* calculation for back date refund entry */
        $packageAdvanceLastIn = PackageAdvances::where([
            ['cash_flow', '=', 'in'],
            ['is_setteled', '=', '0'],
            ['cash_amount', '>', 0],
            ['package_id', '=', $packageInformation->id],
        ])->orderBy('created_at', 'desc')->first();

        $dateBackend = date('Y-m-d', strtotime((string) $packageAdvanceLastIn->created_at));
        $bundleInformation = PackageBundles::where('package_id', '=', $packageId)->first();
        $taxPercentage = $bundleInformation->tax_percentage ?? '';
        $isAdjustmentAmount = 0;

        $packageIsRefundedAmount = PackageAdvances::where([
            ['package_id', '=', $packageId],
            ['cash_flow', '=', 'out'],
            ['is_refund', '=', '1'],
            ['is_tax', '=', '0'],
        ])->sum('cash_amount');

        $packageIsSetteled = PackageAdvances::where([
            ['package_id', '=', $packageId],
            ['cash_flow', '=', 'out'],
            ['is_setteled', '=', '1'],
            ['is_tax', '=', '0'],
        ])->sum('cash_amount');

        $amountToRefund = $packageIsRefundedAmount + $packageIsSetteled;

        /* Document charges */
        $documentationcharges = Settings::where('slug', '=', 'sys-documentationcharges')->first();

        $packageCashReceive = PackageAdvances::where([
            ['package_id', '=', $packageId],
            ['cash_flow', '=', 'in'],
            ['is_cancel', '=', '0'],
            ['is_setteled', '=', '0'],
        ])->sum('cash_amount');

        $packageRefundedAmount = PackageAdvances::where([
            ['package_id', '=', $packageId],
            ['cash_flow', '=', 'out'],
            ['is_cancel', '=', '0'],
            ['is_refund', '=', '1'],
            ['cash_amount', '>', '0'],
        ])->latest()->first();

        $latestPackageRefundedAmount = PackageAdvances::where([
            ['package_id', '=', $packageId],
            ['cash_flow', '=', 'out'],
            ['is_cancel', '=', '0'],
            ['is_refund', '=', '1'],
        ])->latest()->first();

        $packageSetteledAmount = PackageAdvances::where([
            ['package_id', '=', $packageId],
            ['cash_flow', '=', 'out'],
            ['is_cancel', '=', '0'],
            ['is_setteled', '=', '1'],
        ])->sum('cash_amount');

        $refundableAmount = 0;
        $cosumeAmountTax = 0;

        if ($packageCashReceive) {
            $packageServiceOriginalPriceConsumed = PackageService::where([
                ['package_id', '=', $packageId],
                ['is_consumed', '=', '1'],
            ])->sum('price');

            $cosumeAmountTax = 0;

            $refund1 = $packageServiceOriginalPriceConsumed + $cosumeAmountTax + $documentationcharges->data;
            $refundableAmount = ceil(($packageCashReceive - $refund1) - $amountToRefund);
        }

        if ($refundableAmount > 0) {
            $packageServicePriceConsumedTax = PackageService::where([
                ['package_id', '=', $packageId],
                ['is_consumed', '=', '1'],
            ])->sum('tax_including_price');

            $packageServicePriceConsumedWithoutTax = PackageService::where([
                ['package_id', '=', $packageId],
                ['is_consumed', '=', '1'],
            ])->sum('tax_exclusive_price');

            $givenTaxAmount = $packageServicePriceConsumedTax - $packageServicePriceConsumedWithoutTax;

            $returnTaxAmount = ($cosumeAmountTax - $givenTaxAmount);
            $calAdjustmentFinal = $packageServicePriceConsumedTax + ($packageCashReceive - $refund1);
            $isAdjustmentAmount = ceil(($packageCashReceive - $calAdjustmentFinal) - $returnTaxAmount);
            $returnTaxAmount = ceil($returnTaxAmount);
        }

        if ($refundableAmount < 0) {
            $refundableAmount = 0;
        }

        $packageIsAdjustmentAmount = PackageAdvances::where([
            'package_id' => $packageId,
            'cash_flow' => 'out',
            'is_adjustment' => '1',
        ])->sum('cash_amount');

        $document = $packageIsAdjustmentAmount == 0;

        $paymentmodes = PaymentModes::where('name', '!=', 'Settle Amount')->pluck('name', 'id');

        return [
            'success' => true,
            'message' => 'Record found',
            'data' => [
                'id' => $packageId,
                'refundable_amount' => $refundableAmount,
                'cash_amount' => $packageCashReceive,
                'is_adjustment_amount' => $isAdjustmentAmount,
                'documentationcharges' => $documentationcharges,
                'document' => $document,
                'return_tax_amount' => $returnTaxAmount,
                'date_backend' => $dateBackend,
                'paymentmodes' => $paymentmodes,
                'refunded_amount' => $packageRefundedAmount->cash_amount,
                'record_id' => $packageRefundedAmount->id,
                'package_setteled_amount' => $packageSetteledAmount,
                'patient_name' => $patient->name,
                'patient_id' => $patient->id,
                'plan' => $packageInformation->name,
                'created_date' => $latestPackageRefundedAmount && $latestPackageRefundedAmount->created_at ? Carbon::parse($latestPackageRefundedAmount->created_at)->format('Y-m-d') : date('Y-m-d'),
                'refund_note' => $latestPackageRefundedAmount->refund_note ?? '',
                'payment_method_id' => $latestPackageRefundedAmount->payment_mode_id ?? 1,
            ],
        ];
    }

    /**
     * Process a refund update for a package.
     *
     * Wrapped in a single `DB::transaction` so the refund-edit
     * writes (settlement records, plan_invoices cleanup, activity log)
     * and the conversion-state cascade fired at the end (via
     * `ConversionStateService::revertIfNeeded`) are atomic. Pre-fix
     * this method ran without a transaction; a revert failure could
     * silently fail while the refund-edit committed.
     */
    public function processRefund(array $data): array
    {
        // Cap the refund amount at "total paid in minus other refunds"
        // for this package. Before this, the caller could pass any
        // `refund_amount` and the service would happily store it,
        // letting an operator refund more cash than was ever received.
        // The pool observer would then drain the till by the inflated
        // amount.
        //
        // The check uses the SAME filters the getRefundFormData widget
        // uses to display the "available to refund" number (line 74-79),
        // so the UI and the server agree on the same bound.
        $requested = (float) ($data['refund_amount'] ?? 0);
        if ($requested <= 0) {
            return ['success' => false, 'message' => 'Refund amount must be positive.', 'data' => []];
        }

        $totalPaidIn = (float) PackageAdvances::where([
            ['package_id', '=', $data['package_id']],
            ['cash_flow', '=', 'in'],
            ['is_cancel', '=', '0'],
        ])->sum('cash_amount');

        // Existing refund rows on this package, excluding the row we
        // are about to update (`record_id` — the same row's old amount
        // shouldn't double-count against the cap).
        $existingRefunds = (float) PackageAdvances::where([
            ['package_id', '=', $data['package_id']],
            ['cash_flow', '=', 'out'],
            ['is_refund', '=', '1'],
            ['is_cancel', '=', '0'],
            ['is_tax', '=', '0'],
        ])
            ->when(! empty($data['record_id']), fn ($q) => $q->where('id', '!=', $data['record_id']))
            ->sum('cash_amount');

        $available = $totalPaidIn - $existingRefunds;
        if ($requested > $available) {
            return [
                'success' => false,
                'message' => sprintf(
                    'Refund amount (%s) exceeds available balance (%s) — total paid in: %s, already refunded: %s.',
                    number_format($requested, 2),
                    number_format($available, 2),
                    number_format($totalPaidIn, 2),
                    number_format($existingRefunds, 2),
                ),
                'data' => [],
            ];
        }

        return DB::transaction(function () use ($data): array {
        $latestRefund = PackageAdvances::where([
            ['package_id', '=', $data['package_id']],
            ['is_refund', '=', 1],
            ['cash_amount', '>', 0],
            ['is_tax', '=', 0],
        ])->latest()->first();

        // Check if case was previously settled (for activity logging)
        $wasPreviouslySettled = PackageAdvances::where([
            ['package_id', '=', $data['package_id']],
            ['cash_flow', '=', 'out'],
            ['is_setteled', '=', 1],
        ])->exists();

        if ($data['case_setteled'] == '1') {
            $packageCashReceive = PackageAdvances::where([
                ['package_id', '=', $data['package_id']],
                ['cash_flow', '=', 'in'],
                ['is_cancel', '=', '0'],
            ])->sum('cash_amount');

            $packageIsRefundedAmount = PackageAdvances::where([
                ['package_id', '=', $data['package_id']],
                ['cash_flow', '=', 'out'],
                ['is_refund', '=', '1'],
                ['is_tax', '=', '0'],
                ['is_setteled', '=', '0'],
            ])->sum('cash_amount');

            $packageIsConsumedAmount = PackageAdvances::where([
                ['package_id', '=', $data['package_id']],
                ['cash_flow', '=', 'out'],
                ['is_refund', '=', '0'],
                ['is_tax', '=', '0'],
                ['is_setteled', '=', '0'],
                ['is_adjustment', '=', '0'],
            ])->sum('cash_amount');

            $packageIsConsumedTaxAmount = PackageAdvances::where([
                ['package_id', '=', $data['package_id']],
                ['cash_flow', '=', 'out'],
                ['is_refund', '=', '0'],
                ['is_tax', '=', '1'],
                ['is_setteled', '=', '0'],
            ])->sum('cash_amount');

            $consumedAmountWithTax = $packageIsConsumedAmount + $packageIsConsumedTaxAmount;

            $packageIsRefundedAmount = PackageAdvances::where([
                ['package_id', '=', $data['package_id']],
                ['cash_flow', '=', 'out'],
                ['is_refund', '=', '1'],
                ['is_tax', '=', '0'],
            ])->sum('cash_amount');

            $amountAfterRefund = $consumedAmountWithTax + $packageIsRefundedAmount;
            $amountLeft = $packageCashReceive - $amountAfterRefund;
            $packageinformation = Packages::find($data['package_id']);
            $findDoc = Appointments::where('id', $packageinformation->appointment_id)->first();

            if ($amountLeft > 0) {
                $dataAdjustment = [
                    'cash_flow' => 'out',
                    'cash_amount' => $amountLeft,
                    'is_adjustment' => '0',
                    'is_setteled' => 1,
                    'patient_id' => $packageinformation->patient_id,
                    'payment_mode_id' => $data['payment_mode_id'],
                    'account_id' => Auth::user()->account_id,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                    'package_id' => $data['package_id'],
                    'location_id' => $packageinformation->location_id,
                    'appointment_id' => $packageinformation->appointment_id,
                    'created_at' => $data['created_at'] . ' ' . Carbon::now()->toTimeString(),
                    'updated_at' => $data['created_at'] . ' ' . Carbon::now()->toTimeString(),
                ];

                PackageAdvances::create($dataAdjustment);
                $services = Services::where('name', 'Refund Settelment')->first();

                $dataInvoice = [
                    'total_price' => $amountLeft,
                    'account_id' => Auth::user()->account_id,
                    'patient_id' => $packageinformation->patient_id,
                    'appointment_id' => $packageinformation->appointment_id,
                    'invoice_status_id' => 3,
                    'created_by' => Auth::id(),
                    'location_id' => $packageinformation->location_id,
                    'doctor_id' => $findDoc?->doctor_id ?? Auth::id(),
                    'active' => 1,
                    'is_exclusive' => 0,
                    'is_settlement' => 1,
                    'package_id' => $data['package_id'],
                ];
                $createInvoice = Invoices::create($dataInvoice);

                $dataInvoiceDetail = [
                    'qty' => 1,
                    'service_id' => $services->id,
                    'package_id' => $data['package_id'],
                    'invoice_id' => $createInvoice->id,
                    'service_price' => $amountLeft,
                    'net_amount' => $amountLeft,
                    'is_settlement' => 1,
                ];
                InvoiceDetails::create($dataInvoiceDetail);
            } else {
                $latestRefund->where('id', $data['record_id'])->update(['is_setteled' => 1]);
            }
        } else {
            // Handle unchecked case - remove settlement status
            $latestRefund->where('id', $data['record_id'])->update(['is_setteled' => 0]);

            // Delete settlement records for this package
            PackageAdvances::where([
                ['package_id', '=', $data['package_id']],
                ['cash_flow', '=', 'out'],
                ['is_setteled', '=', 1],
            ])->delete();

            $findInvoice = Invoices::where('package_id', $data['package_id'])->where('is_settlement', 1)->first();
            if ($findInvoice) {
                $findInvoiceDetails = InvoiceDetails::where('invoice_id', $findInvoice->id)->where('is_settlement', 1)->first();
                if ($findInvoiceDetails) {
                    $findInvoiceDetails->delete();
                }
                $findInvoice->delete();
            }
        }

        $latestRefund->where('id', $data['record_id'])->update([
            'created_at' => $data['created_at'] . ' ' . Carbon::now()->toTimeString(),
            'cash_amount' => $data['refund_amount'],
            'payment_mode_id' => $data['payment_mode_id'],
            'refund_note' => $data['refund_note'],
        ]);

        // Log refund update activity
        $packageInfo = Packages::find($data['package_id']);
        $patient = User::find($packageInfo->patient_id);
        $location = Locations::find($packageInfo->location_id);

        $creatorName = Auth::user()->name ?? 'System';
        $patientName = $patient->name ?? 'Unknown';
        $locationName = $location->name ?? '';
        $refundAmount = (float) $data['refund_amount'];
        $refundDate = $data['created_at'] ? date('M j, Y', strtotime($data['created_at'])) : date('M j, Y');
        $caseSetteled = $data['case_setteled'] == '1';

        $description = '<span class="highlight">' . $creatorName . '</span> updated refund <span class="highlight-green">Rs. ' . number_format($refundAmount) . '</span> for <span class="highlight-orange">' . $patientName . '</span> in <span class="highlight-purple">Plan #' . sprintf('%05d', $data['package_id']) . '</span>' . ($locationName ? ' at <span class="highlight">' . $locationName . '</span>' : '') . ' on <span class="highlight-purple">' . $refundDate . '</span>';

        if ($caseSetteled) {
            $description .= ' - <span class="highlight-green">Case Settled</span>';
        } elseif ($wasPreviouslySettled && !$caseSetteled) {
            $description .= ' - <span class="highlight-orange">Case Unsettled</span>';
        }

        $activity = new Activity();
        $activity->timestamps = false;
        $activity->action = 'refund_updated';
        $activity->activity_type = 'refund_updated';
        $activity->log_tier = ActivityLogTier::PhiAudit->value;
        $activity->description = $description;
        $activity->patient = $patientName;
        $activity->patient_id = $patient->id ?? null;
        $activity->appointment_type = 'Plan';
        $activity->created_by = Auth::id();
        $activity->plan_id = $data['package_id'];
        $activity->amount = $refundAmount;
        $activity->location = $locationName;
        $activity->centre_id = $packageInfo->location_id;
        $activity->account_id = Auth::user()->account_id;
        $activity->created_at = Filters::getCurrentTimeStamp();
        $activity->updated_at = Filters::getCurrentTimeStamp();
        $activity->save();

        // Conversion-state cascade — single source of truth.
        // If the updated refund pushes net cash ≤ 0, revert the
        // associated appointment from Converted (cascades to lead +
        // leads_services + Meta CAPI flags via WrongConversionService).
        $appointmentId = \App\Services\Conversion\ConversionStateService::appointmentIdForPackage((int) $data['package_id']);
        if ($appointmentId !== null) {
            \App\Services\Conversion\ConversionStateService::revertIfNeeded($appointmentId);
        }

        return ['success' => true, 'message' => 'Record updated', 'data' => []];
        });
    }
}
