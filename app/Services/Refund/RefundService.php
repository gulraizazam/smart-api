<?php

declare(strict_types=1);

namespace App\Services\Refund;

use App\Enums\CashFlow;
use App\Helpers\ACL;
use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Helpers\Invoice_Plan_Refund_Sms_Functions;
use App\Models\Activity;
use App\Models\Appointments;
use App\Models\AuditTrails;
use App\Models\InvoiceDetails;
use App\Models\Invoices;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\PackageBundles;
use App\Models\Packages;
use App\Models\PackageService;
use App\Models\PaymentModes;
use App\Models\Services;
use App\Models\Settings;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class RefundService
{
    private const FILTER_KEY_GLOBAL = 'plansrefunds';
    private const FILTER_KEY_PATIENT = 'patientrefunds';

    // ── Datatable: Global Refunds ─────────────────────────

    /**
     * @return array{total: int, rows: list<array<string, mixed>>, filter_values: array}
     */
    public function getGlobalDatatableData(array $filters, bool $applyFilter): array
    {
        $accountId = (int) Auth::user()->account_id;

        $total = PackageAdvances::getTotalRefundedRecords(
            request(),
            $accountId,
            false,
            $applyFilter,
            self::FILTER_KEY_GLOBAL,
        );

        $filterValues = $this->resolveFilterValues(self::FILTER_KEY_GLOBAL);

        return [
            'total' => $total,
            'account_id' => $accountId,
            'filter_values' => $filterValues,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildGlobalRefundRows(
        int $offset,
        int $limit,
        bool $applyFilter,
    ): array {
        $accountId = (int) Auth::user()->account_id;

        $packages = PackageAdvances::getRefundedRecords(
            request(),
            $offset,
            $limit,
            $accountId,
            false,
            $applyFilter,
            self::FILTER_KEY_GLOBAL,
        );

        $rows = [];
        $packageIds = $packages->pluck('package_id')->unique()->toArray();

        if (empty($packageIds)) {
            return [];
        }

        // Batch-load all aggregated data to prevent N+1
        $aggregates = $this->batchLoadPackageAggregates($packageIds);
        $bundleTotals = PackageBundles::whereIn('package_id', $packageIds)
            ->groupBy('package_id')
            ->selectRaw('package_id, SUM(tax_including_price) as total')
            ->pluck('total', 'package_id');

        $canViewContact = Gate::allows('contact');

        foreach ($packages as $package) {
            $pkgId = $package->package_id;
            $agg = $aggregates[$pkgId] ?? $this->emptyAggregates();

            if ($agg['refunded_amount'] == 0) {
                continue;
            }

            $latestRefund = PackageAdvances::where([
                'package_id' => $pkgId,
                'cash_flow' => CashFlow::Out->value,
                'is_cancel' => '0',
                'is_refund' => '1',
            ])->latest()->first();

            $rows[] = [
                'id' => $pkgId,
                'patient_id' => $package->user ? GeneralFunctions::patientSearchStringAdd($package->user->id) : '-',
                'name' => $package->user?->name ?? '-',
                'phone' => $package->user
                    ? ($canViewContact ? GeneralFunctions::prepareNumber4Call($package->user->phone) : '***********')
                    : '-',
                'package_id' => $pkgId,
                'location_id' => $this->formatLocation($package),
                'total' => number_format((float) ($bundleTotals[$pkgId] ?? 0)),
                'cash_receive' => number_format($agg['cash_receive']),
                'settle_amount' => number_format($agg['settle_amount_with_tax']),
                'refunded' => $agg['refunded_amount'],
                'case_setteled' => $agg['is_case_settled'] ? 'Yes' : 'No',
                'created_at' => $latestRefund
                    ? Carbon::parse($latestRefund->created_at)->format('F j,Y h:i A')
                    : Carbon::parse($package->created_at)->format('F j,Y h:i A'),
            ];
        }

        return $rows;
    }

    // ── Datatable: Patient Refunds ────────────────────────

    /**
     * @return array{total: int, filter_values: array}
     */
    public function getPatientDatatableData(int $patientId, bool $applyFilter): array
    {
        $accountId = (int) Auth::user()->account_id;

        $total = PackageAdvances::getTotalPatientRefundedRecords(
            request(),
            $accountId,
            $patientId,
            $applyFilter,
            self::FILTER_KEY_PATIENT,
        );

        $filterValues = $this->resolveFilterValues(self::FILTER_KEY_PATIENT);

        return [
            'total' => $total,
            'filter_values' => $filterValues,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildPatientRefundRows(
        int $patientId,
        int $offset,
        int $limit,
        bool $applyFilter,
    ): array {
        $accountId = (int) Auth::user()->account_id;

        $packages = PackageAdvances::getPatientRefundedRecords(
            request(),
            $offset,
            $limit,
            $accountId,
            $patientId,
            $applyFilter,
            self::FILTER_KEY_PATIENT,
        );

        $rows = [];
        $packageIds = $packages->pluck('package_id')->unique()->toArray();

        if (empty($packageIds)) {
            return [];
        }

        $aggregates = $this->batchLoadPackageAggregates($packageIds);
        $packageInfos = Packages::whereIn('id', $packageIds)->pluck('total_price', 'id');

        foreach ($packages as $package) {
            $pkgId = $package->package_id;
            $agg = $aggregates[$pkgId] ?? $this->emptyAggregates();

            if ($agg['refunded_amount'] == 0) {
                continue;
            }

            $latestRefund = PackageAdvances::where([
                'package_id' => $pkgId,
                'cash_flow' => CashFlow::Out->value,
                'is_cancel' => '0',
                'is_refund' => '1',
            ])->latest()->first();

            $rows[] = [
                'id' => $pkgId,
                'name' => $package->user?->name ?? '-',
                'plan_id' => $pkgId,
                'total' => number_format((float) ($packageInfos[$pkgId] ?? 0)),
                'cash_in' => number_format($agg['cash_receive']),
                'cash_out' => number_format($agg['cash_out']),
                'refunded_amount' => number_format($agg['refunded_amount']),
                'case_setteled' => $agg['is_case_settled'] ? 'Yes' : 'No',
                'created_at' => $latestRefund
                    ? Carbon::parse($latestRefund->created_at)->format('F j,Y h:i A')
                    : Carbon::parse($package->created_at)->format('F j,Y h:i A'),
                'location' => $this->formatLocation($package),
            ];
        }

        return $rows;
    }

    // ── Refund Calculation ────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function calculateRefund(int $packageId): array
    {
        $packageInfo = Packages::findOrFail($packageId);

        $lastPaymentIn = PackageAdvances::where([
            ['cash_flow', '=', CashFlow::In->value],
            ['is_setteled', '=', '0'],
            ['cash_amount', '>', 0],
            ['package_id', '=', $packageId],
        ])->orderByDesc('created_at')->first();

        if (!$lastPaymentIn) {
            return [
                'error' => true,
                'message' => 'No balance found against this plan',
                'package_id' => $packageId,
            ];
        }

        $dateBackend = Carbon::parse($lastPaymentIn->created_at)->format('Y-m-d');
        $bundleInfo = PackageBundles::where('package_id', $packageId)->first();
        $taxPercentage = (float) ($bundleInfo->tax_percenatage ?? 0);

        $alreadyRefunded = $this->sumPackageAdvances($packageId, CashFlow::Out, isRefund: true, isTax: false);
        $alreadySettled = $this->sumPackageAdvances($packageId, CashFlow::Out, isSettled: true, isTax: false);
        $amountToRefund = $alreadyRefunded + $alreadySettled;

        $docCharges = Settings::where('slug', 'sys-documentationcharges')->value('data') ?? 0;

        $cashReceived = PackageAdvances::where([
            ['package_id', '=', $packageId],
            ['cash_flow', '=', CashFlow::In->value],
            ['is_cancel', '=', '0'],
            ['is_setteled', '=', '0'],
        ])->sum('cash_amount');

        $refundableAmount = 0;
        $adjustmentAmount = 0;
        $returnTaxAmount = 0;
        $consumeTax = 0; // Tax on consumed amount is currently disabled
        $deductions = 0.0;

        if ($cashReceived > 0) {
            $consumedOriginalPrice = PackageService::where([
                ['package_id', '=', $packageId],
                ['is_consumed', '=', '1'],
            ])->sum('price');

            $deductions = $consumedOriginalPrice + $consumeTax + (float) $docCharges;
            $refundableAmount = (int) ceil(($cashReceived - $deductions) - $amountToRefund);
        }

        if ($refundableAmount > 0) {
            $consumedWithTax = PackageService::where([
                ['package_id', '=', $packageId],
                ['is_consumed', '=', '1'],
            ])->sum('tax_including_price');

            $consumedWithoutTax = PackageService::where([
                ['package_id', '=', $packageId],
                ['is_consumed', '=', '1'],
            ])->sum('tax_exclusive_price');

            $givenTaxAmount = $consumedWithTax - $consumedWithoutTax;
            $returnTaxAmount = (int) ceil($consumeTax - $givenTaxAmount);

            $calAdjustmentFinal = $consumedWithTax + ($cashReceived - $deductions);
            $adjustmentAmount = (int) ceil(($cashReceived - $calAdjustmentFinal) - $returnTaxAmount);
        }

        $refundableAmount = max($refundableAmount, 0);

        $existingAdjustment = PackageAdvances::where([
            ['package_id', '=', $packageId],
            ['cash_flow', '=', CashFlow::Out->value],
            ['is_adjustment', '=', '1'],
        ])->sum('cash_amount');

        $paymentModes = PaymentModes::where('name', '!=', 'Settle Amount')
            ->get()
            ->pluck('name', 'id');

        return [
            'error' => false,
            'id' => $packageId,
            'refundable_amount' => $refundableAmount,
            'cash_amount' => $cashReceived,
            'is_adjustment_amount' => $adjustmentAmount,
            'documentationcharges' => ['data' => $docCharges],
            'document' => $existingAdjustment == 0,
            'return_tax_amount' => $returnTaxAmount,
            'date_backend' => $dateBackend,
            'paymentmodes' => $paymentModes,
        ];
    }

    // ── Create Refund ─────────────────────────────────────

    /**
     * @return array{success: bool, message: string}
     */
    public function createRefund(array $data, int $accountId): array
    {
        $packageId = (int) $data['package_id'];

        // Verify balance exists
        $hasBalance = PackageAdvances::where([
            ['cash_flow', '=', CashFlow::In->value],
            ['is_setteled', '=', '0'],
            ['cash_amount', '>', 0],
            ['package_id', '=', $packageId],
        ])->exists();

        if (!$hasBalance) {
            return ['success' => false, 'message' => 'No balance available'];
        }

        return DB::transaction(function () use ($data, $accountId, $packageId): array {
            // Check if already settled
            $isSettled = PackageAdvances::where([
                ['cash_flow', '=', CashFlow::Out->value],
                ['cash_amount', '>', 0],
                ['is_setteled', '=', '1'],
                ['package_id', '=', $packageId],
            ])->exists();

            if ($isSettled) {
                return ['success' => false, 'message' => 'Plan is already settled. You cannot refund amount against this plan.'];
            }

            // Validate refund amount vs received
            $cashReceived = PackageAdvances::where([
                ['package_id', '=', $packageId],
                ['cash_flow', '=', CashFlow::In->value],
                ['is_cancel', '=', '0'],
                ['is_setteled', '=', '0'],
            ])->sum('cash_amount');

            $refundAmount = (float) $data['refund_amount'];

            if ($refundAmount > $cashReceived) {
                return ['success' => false, 'message' => 'You cannot refund amount more than amount received.'];
            }

            // Compute consumed amounts for settlement calculations
            $consumedWithTax = $this->getConsumedAmountWithTax($packageId);

            // Resolve back-date timestamp
            $customCreatedAt = $this->resolveBackdateTimestamp(
                $data['created_at'],
                $data['date_backend'] ?? null,
                $packageId,
            );

            $packageInfo = Packages::findOrFail($packageId);

            // Create refund record in package_advances
            $record = PackageAdvances::create([
                'cash_flow' => CashFlow::Out->value,
                'cash_amount' => $refundAmount,
                'is_refund' => '1',
                'patient_id' => $packageInfo->patient_id,
                'payment_mode_id' => $data['payment_mode_id'] ?? null,
                'account_id' => $accountId,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
                'refund_note' => $data['refund_note'],
                'package_id' => $packageId,
                'location_id' => $packageInfo->location_id,
                'appointment_id' => $packageInfo->appointment_id,
                'created_at' => $customCreatedAt,
                'updated_at' => $customCreatedAt,
            ]);

            // Log activity
            $this->logRefundActivity($record, $packageInfo, $data, $customCreatedAt);

            // Send SMS notification
            if ($record->cash_amount > 0) {
                try {
                    Invoice_Plan_Refund_Sms_Functions::RefundCashReceived_SMS($record);
                } catch (\Throwable $e) {
                    Log::warning('Refund SMS failed', ['error' => $e->getMessage()]);
                }
            }

            // Audit trail
            AuditTrails::addEventLogger(
                'package_advances',
                'create',
                $record->toArray(),
                (new PackageAdvances())->getFillable(),
                $record,
            );

            // Mark package as refunded if first refund (includes audit trail)
            if ($packageInfo->is_refund === 0 || $packageInfo->is_refund === '0') {
                Packages::updateRecordRefunds($packageId);
            }

            // Regenerate plan name
            $this->regeneratePlanName($packageInfo);

            // Handle case settlement
            if (($data['case_setteled'] ?? '0') === '1') {
                $this->handleCaseSettlement(
                    $packageId,
                    $packageInfo,
                    $cashReceived,
                    $consumedWithTax,
                    $customCreatedAt,
                    $accountId,
                );
            }

            return ['success' => true, 'message' => 'Record has been created successfully.'];
        });
    }

    // ── Patient Ledger Detail ─────────────────────────────

    /**
     * @return array{patient: User, advances: \Illuminate\Database\Eloquent\Collection}
     */
    public function getPatientLedger(int $patientId): array
    {
        $patient = User::findOrFail($patientId);

        $advances = PackageAdvances::where('patient_id', $patientId)
            ->where('cash_amount', '!=', 0)
            ->orderByDesc('created_at')
            ->get();

        return [
            'patient' => $patient,
            'advances' => $advances,
        ];
    }

    // ── Permissions ───────────────────────────────────────

    public function globalPermissions(): array
    {
        return [
            'create' => Gate::allows('refunds_create'),
            'delete' => Gate::allows('refunds_destroy'),
            'active' => Gate::allows('refunds_active'),
            'inactive' => Gate::allows('refunds_inactive'),
            'refund' => Gate::allows('refunds_refund'),
            'edit' => Gate::allows('refunds_edit'),
        ];
    }

    public function patientPermissions(): array
    {
        return [
            'refund' => Gate::allows('patients_refund_refund'),
        ];
    }

    // ── Private Helpers ───────────────────────────────────

    /**
     * @return array<int, array<string, mixed>>
     */
    private function batchLoadPackageAggregates(array $packageIds): array
    {
        $results = DB::table('package_advances')
            ->whereIn('package_id', $packageIds)
            ->whereNull('deleted_at')
            ->where('is_cancel', '0')
            ->groupBy('package_id')
            ->select([
                'package_id',
                DB::raw("SUM(CASE WHEN cash_flow = 'in' AND is_setteled = 0 THEN cash_amount ELSE 0 END) as cash_receive"),
                DB::raw("SUM(CASE WHEN cash_flow = 'out' AND is_refund = 1 THEN cash_amount ELSE 0 END) as refunded_amount"),
                DB::raw("SUM(CASE WHEN cash_flow = 'out' AND is_refund = 0 AND is_tax = 0 AND is_adjustment = 0 AND is_setteled = 0 THEN cash_amount ELSE 0 END) as settle_amount"),
                DB::raw("SUM(CASE WHEN cash_flow = 'out' AND is_refund = 0 AND is_tax = 1 AND is_adjustment = 0 AND is_setteled = 0 THEN cash_amount ELSE 0 END) as settle_tax_amount"),
                DB::raw("SUM(CASE WHEN cash_flow = 'out' AND is_refund = 0 AND is_setteled = 1 THEN cash_amount ELSE 0 END) as refund_settle_amount"),
                DB::raw("SUM(CASE WHEN cash_flow = 'out' AND is_refund = 0 THEN cash_amount ELSE 0 END) as cash_out"),
                DB::raw("MAX(CASE WHEN cash_flow = 'out' AND is_setteled = 1 THEN 1 ELSE 0 END) as is_case_settled"),
            ])
            ->get();

        $aggregates = [];
        foreach ($results as $row) {
            $settleTotal = (float) $row->settle_amount + (float) $row->settle_tax_amount + (float) $row->refund_settle_amount;
            $aggregates[$row->package_id] = [
                'cash_receive' => (float) $row->cash_receive,
                'refunded_amount' => (float) $row->refunded_amount,
                'settle_amount_with_tax' => $settleTotal,
                'cash_out' => (float) $row->cash_out,
                'is_case_settled' => (bool) $row->is_case_settled,
            ];
        }

        return $aggregates;
    }

    private function emptyAggregates(): array
    {
        return [
            'cash_receive' => 0.0,
            'refunded_amount' => 0.0,
            'settle_amount_with_tax' => 0.0,
            'cash_out' => 0.0,
            'is_case_settled' => false,
        ];
    }

    private function sumPackageAdvances(
        int $packageId,
        CashFlow $flow,
        ?bool $isRefund = null,
        ?bool $isTax = null,
        ?bool $isSettled = null,
    ): float {
        $query = PackageAdvances::where('package_id', $packageId)
            ->where('cash_flow', $flow->value);

        if ($isRefund !== null) {
            $query->where('is_refund', $isRefund ? '1' : '0');
        }
        if ($isTax !== null) {
            $query->where('is_tax', $isTax ? '1' : '0');
        }
        if ($isSettled !== null) {
            $query->where('is_setteled', $isSettled ? '1' : '0');
        }

        return (float) $query->sum('cash_amount');
    }

    private function getConsumedAmountWithTax(int $packageId): float
    {
        $consumed = PackageAdvances::where([
            ['package_id', '=', $packageId],
            ['cash_flow', '=', CashFlow::Out->value],
            ['is_refund', '=', '0'],
            ['is_tax', '=', '0'],
        ])->sum('cash_amount');

        $consumedTax = PackageAdvances::where([
            ['package_id', '=', $packageId],
            ['cash_flow', '=', CashFlow::Out->value],
            ['is_refund', '=', '0'],
            ['is_tax', '=', '1'],
        ])->sum('cash_amount');

        return (float) ($consumed + $consumedTax);
    }

    private function resolveBackdateTimestamp(
        string $createdDate,
        ?string $dateBackend,
        int $packageId,
    ): string {
        $lastIn = PackageAdvances::where([
            ['cash_flow', '=', CashFlow::In->value],
            ['is_setteled', '=', '0'],
            ['cash_amount', '>', 0],
            ['package_id', '=', $packageId],
        ])->orderByDesc('created_at')->first();

        $currentTime = Carbon::now()->format('H:i:s');

        if (!$dateBackend || $createdDate > $dateBackend) {
            return $createdDate . ' ' . $currentTime;
        }

        if ($createdDate === $dateBackend && $lastIn) {
            $proposed = $createdDate . ' ' . $currentTime;
            $lastInDate = (string) $lastIn->created_at;

            return $proposed > $lastInDate
                ? $proposed
                : Carbon::parse($lastInDate)->addMinutes(2)->toDateTimeString();
        }

        // Back-date entry
        return $createdDate . ' ' . $currentTime;
    }

    private function logRefundActivity(
        PackageAdvances $record,
        Packages $packageInfo,
        array $data,
        string $customCreatedAt,
    ): void {
        $patient = User::find($packageInfo->patient_id);
        $location = Locations::find($packageInfo->location_id);

        $creatorName = Auth::user()->name ?? 'System';
        $patientName = $patient->name ?? 'Unknown';
        $locationName = $location->name ?? '';
        $refundAmount = (float) $data['refund_amount'];
        $refundDate = date('M j, Y', strtotime($data['created_at']));
        $caseSettled = ($data['case_setteled'] ?? '0') === '1';

        $description = '<span class="highlight">' . e($creatorName) . '</span> refunded '
            . '<span class="highlight-green">Rs. ' . number_format($refundAmount) . '</span> to '
            . '<span class="highlight-orange">' . e($patientName) . '</span> for '
            . '<span class="highlight-purple">Plan #' . sprintf('%05d', $data['package_id']) . '</span>'
            . ($locationName ? ' in <span class="highlight">' . e($locationName) . '</span>' : '')
            . ' on <span class="highlight-purple">' . $refundDate . '</span>';

        if ($caseSettled) {
            $description .= ' - <span class="highlight-green">Case Settled</span>';
        }

        $activity = new Activity();
        $activity->timestamps = false;
        $activity->action = 'refunded';
        $activity->activity_type = 'refund_made';
        $activity->description = $description;
        $activity->patient = $patientName;
        $activity->patient_id = $patient?->id;
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
    }

    private function handleCaseSettlement(
        int $packageId,
        Packages $packageInfo,
        float $cashReceived,
        float $consumedWithTax,
        string $customCreatedAt,
        int $accountId,
    ): void {
        $refundedAfter = $this->sumPackageAdvances($packageId, CashFlow::Out, isRefund: true, isTax: false);
        $amountAfterRefund = $consumedWithTax + $refundedAfter;
        $amountLeft = $cashReceived - $amountAfterRefund;

        $appointment = Appointments::find($packageInfo->appointment_id);

        // Create settlement advance record
        PackageAdvances::create([
            'cash_flow' => CashFlow::Out->value,
            'cash_amount' => $amountLeft,
            'is_adjustment' => '0',
            'is_setteled' => 1,
            'patient_id' => $packageInfo->patient_id,
            'payment_mode_id' => config('constants.settlement_payment_mode_id', 5),
            'account_id' => $accountId,
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
            'package_id' => $packageId,
            'location_id' => $packageInfo->location_id,
            'appointment_id' => $packageInfo->appointment_id,
            'created_at' => $customCreatedAt,
            'updated_at' => $customCreatedAt,
        ]);

        // Create settlement invoice if remaining amount > 0
        if ($amountLeft > 0) {
            $settlementService = Services::where('name', 'Refund Settelment')->first();

            $invoice = Invoices::create([
                'total_price' => $amountLeft,
                'account_id' => $accountId,
                'patient_id' => $packageInfo->patient_id,
                'appointment_id' => $packageInfo->appointment_id,
                'invoice_status_id' => 3,
                'created_by' => Auth::id(),
                'location_id' => $packageInfo->location_id,
                'doctor_id' => $appointment?->doctor_id,
                'active' => 1,
                'is_exclusive' => 0,
                'is_settlement' => 1,
                'package_id' => $packageId,
            ]);

            if ($settlementService) {
                InvoiceDetails::create([
                    'qty' => 1,
                    'service_id' => $settlementService->id,
                    'invoice_id' => $invoice->id,
                    'is_settlement' => 1,
                ]);
            }
        }
    }

    private function regeneratePlanName(Packages $package): void
    {
        if ($package->plan_type === 'membership') {
            $names = PackageBundles::where('package_bundles.package_id', $package->id)
                ->join('membership_types', 'package_bundles.membership_type_id', '=', 'membership_types.id')
                ->orderBy('package_bundles.id')
                ->limit(2)
                ->pluck('membership_types.name')
                ->toArray();

            if (!empty($names)) {
                Packages::where('id', $package->id)->update(['plan_name' => implode(', ', $names)]);
            }

            return;
        }

        $totalBundleCount = PackageBundles::where('package_id', $package->id)->count();
        $joinTable = $package->plan_type === 'plan' ? 'services' : 'bundles';

        $names = PackageBundles::where('package_bundles.package_id', $package->id)
            ->join($joinTable, 'package_bundles.bundle_id', '=', "{$joinTable}.id")
            ->orderBy('package_bundles.id')
            ->limit(2)
            ->pluck("{$joinTable}.name")
            ->toArray();

        $planName = !empty($names) ? implode(', ', $names) : '-';

        if ($package->plan_type === 'plan' && $totalBundleCount > 2) {
            $planName .= '...';
        }

        Packages::where('id', $package->id)->update(['plan_name' => $planName]);
    }

    private function resolveFilterValues(string $filterKey): array
    {
        $userId = Auth::id();

        $patient = [];
        if ($patientId = Filters::get($userId, $filterKey, 'patient_id')) {
            $patient = User::find($patientId)?->toArray() ?? [];
        }

        $package = [];
        if ($packageId = Filters::get($userId, $filterKey, 'package_id')) {
            $package = Packages::find($packageId)?->toArray() ?? [];
        }

        $locations = Locations::getActiveSorted(ACL::getUserCentres(), 'full_address');

        return [
            'patient' => $patient,
            'package' => $package,
            'locations' => $locations,
        ];
    }

    private function formatLocation(object $record): string
    {
        $city = $record->location?->city?->name ?? '';
        $loc = $record->location?->name ?? '';

        return $city ? "{$city}-{$loc}" : $loc;
    }
}
