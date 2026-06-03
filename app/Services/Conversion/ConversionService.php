<?php

declare(strict_types=1);

namespace App\Services\Conversion;

use App\Helpers\GeneralFunctions;
use App\Models\Appointments;
use App\Models\Invoices;
use App\Models\PackageAdvances;
use App\Models\PackageBundles;
use App\Models\Packages;
use App\Models\PackageService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shared Conversion Service
 *
 * Single source of truth for conversion calculations used by:
 * - Dashboard API (/api/dashboard/doctor-wise-conversion)
 * - Reports (/admin/reports/load_conversion_report)
 * - Doctor Dashboard KPI (/api/doctor-dashboard/kpis)
 *
 * A consultation is "converted" when ALL of these are true:
 * 1. Appointment is a consultation (appointment_type_id = 1)
 * 2. Appointment status is arrived or converted
 * 3. An invoice exists for this appointment
 * 4. At least one service was added on or after invoice creation date
 * 5. At least one payment (cash_flow='in', cash_amount>0) exists on or after invoice date
 * 6. The FIRST such payment date falls within the report date range
 *
 * NOTE: The consultation's scheduled_date does NOT need to be within the report range.
 * A consultation from a prior month is counted as converted if its first payment falls
 * within the report range. This means converted count can exceed total arrived count.
 *
 * Conversion spend = sum of (revenue_in - refund_out) via genericfunctionforstaffwiserevenue
 * for all payments from invoice date within the report date range.
 */
class ConversionService
{
    /**
     * Columns `GeneralFunctions::genericfunctionforstaffwiserevenue()` reads
     * off each advance when computing conversion spend. The select() in
     * getValidatedConversions() is pinned to this list so the heavy branch
     * leaderboard no longer hydrates every PackageAdvances column (the full
     * model load spiked to ~221MB on a busy month and 500'd at the 128MB
     * limit). `patient_id` is the FK behind the `user` relation the helper
     * dereferences (`$advance->user->name`/`->phone`); dropping any key here
     * silently corrupts revenue/refund math — pinned by
     * tests/Feature/Conversion/ConversionServiceSpendColumnsTest.php.
     */
    public const SPEND_ADVANCE_COLUMNS = [
        'id', 'package_id', 'patient_id', 'invoice_id', 'payment_mode_id',
        'created_at', 'deleted_at', 'cash_flow', 'cash_amount',
        'is_adjustment', 'is_tax', 'is_cancel', 'is_refund',
    ];

    /**
     * Get validated conversions for given appointments.
     *
     * This is the CORE method that all endpoints must use.
     * Returns per-appointment conversion data including spend.
     *
     * @param  Collection  $convertedAppointments  Appointment models (pre-filtered by status, type, payment existence)
     * @param  string  $startDate  Y-m-d
     * @param  string  $endDate  Y-m-d
     * @param  bool  $includeDetails  Whether to include patient/doctor/service detail info (for reports)
     * @param  bool  $canViewContact  Whether to mask phone numbers
     * @return array ['conversions' => [...], 'by_doctor' => [...], 'by_service' => [...], 'total_spend' => float]
     */
    public function getValidatedConversions(
        Collection $convertedAppointments,
        string $startDate,
        string $endDate,
        bool $includeDetails = false,
        bool $canViewContact = true
    ): array {
        if ($convertedAppointments->isEmpty()) {
            return $this->emptyResult();
        }

        $appointmentIds = $convertedAppointments->pluck('id')->unique()->toArray();

        // Bulk fetch all related data to avoid N+1
        $invoices = Invoices::whereIn('appointment_id', $appointmentIds)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'asc')
            ->get()
            ->groupBy('appointment_id')
            ->map(fn ($group) => $group->first());

        // Get ALL packages per appointment (not keyBy which loses duplicates)
        $allPackages = Packages::whereIn('appointment_id', $appointmentIds)->get()->groupBy('appointment_id');
        $allPackageIds = $allPackages->flatten()->pluck('id')->toArray();

        // Bulk fetch package bundles
        $packageBundles = PackageBundles::whereIn('package_id', $allPackageIds)->get()->groupBy('package_id');

        // Bulk fetch package services existence (min created_at per bundle)
        $allBundleIds = $packageBundles->flatten()->pluck('id')->toArray();
        $packageServicesExist = [];
        if (! empty($allBundleIds)) {
            $packageServicesExist = PackageService::whereIn('package_bundle_id', $allBundleIds)
                ->select('package_bundle_id', DB::raw('MIN(created_at) as min_created_at'))
                ->groupBy('package_bundle_id')
                ->get()
                ->keyBy('package_bundle_id');
        }

        // Bulk fetch all payments per package (cash_flow='in', cash_amount>0, not deleted)
        // ordered by created_at so we can find the first payment on/after invoice date per appointment
        // Only created_at is read off these (to find the first payment on/after
        // the invoice date); package_id drives the groupBy. This set is never
        // passed to the spend helper, so it needs neither the flag columns nor
        // the user relation — keep the projection tight.
        $allPaymentsByPackage = PackageAdvances::query()
            ->select(['id', 'package_id', 'created_at', 'deleted_at'])
            ->whereIn('package_id', $allPackageIds)
            ->where('cash_flow', 'in')
            ->where('cash_amount', '>', 0)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'asc')
            ->get()
            ->groupBy('package_id');

        // Bulk fetch all package advances for conversion spend (within date range, cash_amount > 0)
        // These rows feed the spend helper, so the projection is pinned to the
        // columns it reads (SPEND_ADVANCE_COLUMNS) instead of the full model.
        // Eager-load `user` here too: the helper dereferences $advance->user
        // per qualifying advance, which was a per-row N+1 on the leaderboard.
        $allPackageAdvances = PackageAdvances::query()
            ->select(self::SPEND_ADVANCE_COLUMNS)
            ->with('user')
            ->whereIn('package_id', $allPackageIds)
            ->where('cash_amount', '>', 0)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $startDate.' 00:00:00')
            ->where('created_at', '<=', $endDate.' 23:59:59')
            ->get()
            ->groupBy('package_id');

        $processedAppointments = [];
        $conversions = [];
        $byDoctor = [];
        $byBranchDoctor = []; // [location_id => [doctor_id => {count, spend}]]
        $byService = [];
        $totalSpend = 0;

        foreach ($convertedAppointments as $appointment) {
            if (isset($processedAppointments[$appointment->id])) {
                continue;
            }
            $processedAppointments[$appointment->id] = true;

            // Build detail info if requested (for reports table)
            $detailInfo = null;
            if ($includeDetails) {
                $phoneNumber = $canViewContact ? ($appointment->patient->phone ?? '') : '***********';
                $detailInfo = [
                    'patient_id' => $appointment->patient_id,
                    'appointment_id' => $appointment->id,
                    'doctor_id' => $appointment->doctor_id,
                    'doctor' => $appointment->doctor->name ?? '',
                    'client' => $appointment->patient->name ?? '',
                    'phone' => $phoneNumber,
                    'service' => $appointment->service->name ?? '',
                    'service_id' => $appointment->service->id ?? 0,
                    'region' => $appointment->region->name ?? '',
                    'city' => $appointment->city->name ?? '',
                    'centre' => $appointment->location->name ?? '',
                    'doi' => Carbon::parse($appointment->created_at)->format('M d Y'),
                    'converted' => '',
                    'conversion_spend' => '',
                    'conversion_date' => '',
                ];
            }

            // Step 1: Get invoice
            $invoice = $invoices->get($appointment->id);
            if (! $invoice) {
                if ($includeDetails) {
                    $conversions[$appointment->id] = $detailInfo;
                }

                continue;
            }

            $invoiceDate = Carbon::parse($invoice->created_at)->format('Y-m-d');

            // Step 2: Get ALL packages for this appointment
            $packages = $allPackages->get($appointment->id, collect());
            if ($packages->isEmpty()) {
                if ($includeDetails) {
                    $conversions[$appointment->id] = $detailInfo;
                }

                continue;
            }

            $packageIds = $packages->pluck('id')->toArray();

            // Step 3: Check package services exist after invoice date
            $hasServiceAfterInvoice = false;
            foreach ($packageIds as $pkgId) {
                $bundleIds = $packageBundles->get($pkgId, collect())->pluck('id')->toArray();
                foreach ($bundleIds as $bundleId) {
                    $serviceInfo = $packageServicesExist[$bundleId] ?? null;
                    if ($serviceInfo && Carbon::parse($serviceInfo->min_created_at)->format('Y-m-d') >= $invoiceDate) {
                        $hasServiceAfterInvoice = true;
                        break 2;
                    }
                }
            }
            if (! $hasServiceAfterInvoice) {
                if ($includeDetails) {
                    $conversions[$appointment->id] = $detailInfo;
                }

                continue;
            }

            // Step 4: Find first payment across all packages (on or after invoice date)
            $earliestPayment = null;
            foreach ($packageIds as $pkgId) {
                $payments = $allPaymentsByPackage->get($pkgId, collect());
                // Find the first payment on or after invoice date (payments are ordered by created_at asc)
                foreach ($payments as $fp) {
                    $fpDate = Carbon::parse($fp->created_at)->format('Y-m-d');
                    if ($fpDate >= $invoiceDate) {
                        if (! $earliestPayment || $fp->created_at < $earliestPayment->created_at) {
                            $earliestPayment = $fp;
                        }
                        break; // first qualifying payment for this package found
                    }
                }
            }

            if (! $earliestPayment) {
                if ($includeDetails) {
                    $conversions[$appointment->id] = $detailInfo;
                }

                continue;
            }

            // Step 5: Check first payment date falls within report range
            $firstPaymentDate = Carbon::parse($earliestPayment->created_at)->format('Y-m-d');
            if ($firstPaymentDate < $startDate || $firstPaymentDate > $endDate) {
                if ($includeDetails) {
                    $conversions[$appointment->id] = $detailInfo;
                }

                continue;
            }

            // Step 6: Calculate conversion spend from all advances (from invoice date, within range)
            $revenue_in = 0;
            $out = 0;
            $hasAdvances = false;

            foreach ($packageIds as $pkgId) {
                $advances = $allPackageAdvances->get($pkgId, collect())
                    ->filter(fn ($pa) => Carbon::parse($pa->created_at)->format('Y-m-d') >= $invoiceDate);

                foreach ($advances as $advance) {
                    $hasAdvances = true;
                    $package_advance = GeneralFunctions::genericfunctionforstaffwiserevenue($advance);
                    if ($package_advance) {
                        $revenue_in += (float) ($package_advance['revenue'] ?? 0);
                        $out += (float) ($package_advance['refund_out'] ?? 0);
                    }
                }
            }

            if (! $hasAdvances) {
                if ($includeDetails) {
                    $conversions[$appointment->id] = $detailInfo;
                }

                continue;
            }

            $actual = $revenue_in - $out;

            // Finance policy Q1=B + Q2=strict (2026-05-03): a
            // conversion with net cash ≤ 0 is no longer counted as a
            // conversion. Without this guard, a fully-refunded
            // conversion stayed in the count with $0 spend, dragging
            // Average Conversion Value toward zero.
            //
            // The trigger paths (RefundService, deletePayment,
            // deletePlan) call `ConversionStateService::revertIfNeeded`
            // synchronously after their writes, which clears
            // `appointments.converted_at` + `appointment_status_id`
            // and reverts the lead — so refunded rows would normally
            // not even reach this report's candidate set. This
            // defence-in-depth guard catches anything the triggers
            // missed (legacy data, manual SQL, etc).
            if ($actual <= 0) {
                if ($includeDetails) {
                    $conversions[$appointment->id] = $detailInfo;
                }
                continue;
            }

            $totalSpend += $actual;

            // Track by doctor
            $doctorId = $appointment->doctor_id;
            if (! isset($byDoctor[$doctorId])) {
                $byDoctor[$doctorId] = ['count' => 0, 'spend' => 0];
            }
            $byDoctor[$doctorId]['count']++;
            $byDoctor[$doctorId]['spend'] += $actual;

            // Track by (branch, doctor) for cross-branch leaderboard rollups.
            $branchId = (int) ($appointment->location_id ?? 0);
            if ($branchId > 0) {
                if (! isset($byBranchDoctor[$branchId][$doctorId])) {
                    $byBranchDoctor[$branchId][$doctorId] = ['count' => 0, 'spend' => 0];
                }
                $byBranchDoctor[$branchId][$doctorId]['count']++;
                $byBranchDoctor[$branchId][$doctorId]['spend'] += $actual;
            }

            // Track by service
            $serviceId = $appointment->service_id ?? ($appointment->service->id ?? 0);
            $serviceName = $appointment->service->name ?? '';
            if (! isset($byService[$serviceId])) {
                $byService[$serviceId] = ['name' => $serviceName, 'count' => 0, 'spend' => 0];
            }
            $byService[$serviceId]['count']++;
            $byService[$serviceId]['spend'] += $actual;

            // Store conversion
            if ($includeDetails) {
                $detailInfo['converted'] = 'Yes';
                $detailInfo['conversion_spend'] = $actual;
                $detailInfo['conversion_date'] = $earliestPayment->created_at;
                $conversions[$appointment->id] = $detailInfo;
            } else {
                $conversions[$appointment->id] = [
                    'doctor_id' => $doctorId,
                    'conversion_spend' => $actual,
                ];
            }
        }

        return [
            'conversions' => $conversions,
            'by_doctor' => $byDoctor,
            'by_branch_doctor' => $byBranchDoctor,
            'by_service' => $byService,
            'total_spend' => $totalSpend,
        ];
    }

    /**
     * Bucket validated conversions by first-payment month across a multi-
     * month window. Single bulk pass — avoids the Nx-month × M-doctor blow-up
     * you get calling calculateForDoctor() once per month.
     *
     * Each bucket's spend matches what the Conversion Report would show if
     * run for just that month: spend per conversion is truncated to the end
     * of the conversion's first-payment month (advances after that don't
     * count in the month that "claimed" the conversion).
     *
     * @param  list<int>  $doctorIds
     * @param  list<int>  $locations
     * @return array<string, array{converted: int, spend: float}> keyed by Y-m
     */
    public function monthlySeriesForScope(
        int $accountId,
        array $doctorIds,
        array $locations,
        string $startDate,
        string $endDate,
    ): array {
        if ($doctorIds === [] || $locations === []) {
            return [];
        }

        $arrivedStatusId = $this->getArrivedStatusId($accountId);
        $convertedStatusId = $this->getConvertedStatusId($accountId);
        if (! $arrivedStatusId) {
            return [];
        }
        $statusIds = array_filter([$arrivedStatusId, $convertedStatusId]);

        // Distinct candidate appointment IDs — consultations with ≥1 payment
        // in the window. Raw query to avoid Eloquent hydration.
        $appointmentIds = DB::table('appointments')
            ->join('package_advances', 'package_advances.appointment_id', '=', 'appointments.id')
            ->where('appointments.appointment_type_id', 1)
            ->whereIn('appointments.appointment_status_id', $statusIds)
            ->whereIn('appointments.doctor_id', $doctorIds)
            ->whereIn('appointments.location_id', $locations)
            ->where('appointments.scheduled_date', '<=', $endDate)
            ->where('package_advances.cash_amount', '>', 0)
            ->whereBetween('package_advances.created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->distinct()
            ->pluck('appointments.id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($appointmentIds === []) {
            return [];
        }

        // Earliest invoice per appointment.
        $invoices = DB::table('invoices')
            ->whereIn('appointment_id', $appointmentIds)
            ->whereNull('deleted_at')
            ->select('appointment_id', DB::raw('MIN(created_at) as created_at'))
            ->groupBy('appointment_id')
            ->pluck('created_at', 'appointment_id')
            ->all();

        $packageRows = DB::table('packages')
            ->whereIn('appointment_id', $appointmentIds)
            ->select('id', 'appointment_id')
            ->get();

        $packagesByAppointment = [];
        $allPackageIds = [];
        foreach ($packageRows as $pkg) {
            $packagesByAppointment[(int) $pkg->appointment_id][] = (int) $pkg->id;
            $allPackageIds[] = (int) $pkg->id;
        }
        if ($allPackageIds === []) {
            return [];
        }

        // Per-package earliest bundle→service created_at. One join query.
        $earliestServicePerPackage = DB::table('package_bundles as pb')
            ->join('package_services as ps', 'ps.package_bundle_id', '=', 'pb.id')
            ->whereIn('pb.package_id', $allPackageIds)
            ->select('pb.package_id', DB::raw('MIN(ps.created_at) as min_created_at'))
            ->groupBy('pb.package_id')
            ->pluck('min_created_at', 'package_id')
            ->all();

        // All qualifying payments in window — narrow columns, no relations.
        $advanceRows = DB::table('package_advances')
            ->whereIn('package_id', $allPackageIds)
            ->where('cash_amount', '>', 0)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->select('package_id', 'created_at', 'cash_amount', 'cash_flow', 'is_adjustment', 'is_tax', 'is_cancel', 'is_refund')
            ->get();

        $advancesByPackage = [];
        foreach ($advanceRows as $row) {
            $advancesByPackage[(int) $row->package_id][] = $row;
        }

        $buckets = [];

        foreach ($appointmentIds as $appointmentId) {
            $invoiceCreatedAt = $invoices[$appointmentId] ?? null;
            if (! $invoiceCreatedAt) {
                continue;
            }
            $invoiceDate = substr((string) $invoiceCreatedAt, 0, 10);

            $packageIds = $packagesByAppointment[$appointmentId] ?? [];
            if ($packageIds === []) {
                continue;
            }

            // Step 3: package service exists on/after invoice date (any package).
            $hasServiceAfterInvoice = false;
            foreach ($packageIds as $pkgId) {
                $minServiceAt = $earliestServicePerPackage[$pkgId] ?? null;
                if ($minServiceAt !== null && substr((string) $minServiceAt, 0, 10) >= $invoiceDate) {
                    $hasServiceAfterInvoice = true;
                    break;
                }
            }
            if (! $hasServiceAfterInvoice) {
                continue;
            }

            // Step 4: earliest payment on/after invoice date, cash_flow='in'.
            $earliestPaymentAt = null;
            foreach ($packageIds as $pkgId) {
                foreach ($advancesByPackage[$pkgId] ?? [] as $row) {
                    if ($row->cash_flow !== 'in') {
                        continue;
                    }
                    $rowDate = substr((string) $row->created_at, 0, 10);
                    if ($rowDate < $invoiceDate) {
                        continue;
                    }
                    if ($earliestPaymentAt === null || $row->created_at < $earliestPaymentAt) {
                        $earliestPaymentAt = $row->created_at;
                    }
                }
            }
            if ($earliestPaymentAt === null) {
                continue;
            }

            $fpDate = substr((string) $earliestPaymentAt, 0, 10);
            if ($fpDate < $startDate || $fpDate > $endDate) {
                continue;
            }

            $bucketKey = substr($fpDate, 0, 7);
            $fpMonthEnd = Carbon::parse($earliestPaymentAt)->endOfMonth()->format('Y-m-d');

            // Step 6: sum spend from invoice date through end of first-payment
            // month. Inline revenue/refund classification — no helper call,
            // no relation load.
            $revenueIn = 0.0;
            $refundOut = 0.0;
            $hasAdvances = false;
            foreach ($packageIds as $pkgId) {
                foreach ($advancesByPackage[$pkgId] ?? [] as $row) {
                    $rowDate = substr((string) $row->created_at, 0, 10);
                    if ($rowDate < $invoiceDate || $rowDate > $fpMonthEnd) {
                        continue;
                    }
                    $hasAdvances = true;
                    $amount = (float) $row->cash_amount;
                    if ($row->cash_flow === 'in'
                        && (int) $row->is_adjustment === 0
                        && (int) $row->is_tax === 0
                        && (int) $row->is_cancel === 0
                    ) {
                        $revenueIn += $amount;
                    } elseif ($row->cash_flow === 'out' && (int) $row->is_refund === 1) {
                        $refundOut += $amount;
                    }
                }
            }
            if (! $hasAdvances) {
                continue;
            }

            $actual = $revenueIn - $refundOut;

            // Finance policy Q1=B (2026-05-03): drop net-negative
            // conversions from bucket counts and spend. Without this
            // an Avg Conversion Value sparkline shows historical months
            // dragged toward zero by later refunds. See sibling
            // guard in `getValidatedConversions` Step 6.
            if ($actual <= 0) {
                continue;
            }

            if (! isset($buckets[$bucketKey])) {
                $buckets[$bucketKey] = ['converted' => 0, 'spend' => 0.0];
            }
            $buckets[$bucketKey]['converted']++;
            $buckets[$bucketKey]['spend'] += $actual;
        }

        return $buckets;
    }

    /**
     * Fetch candidate converted appointments (pre-filter).
     *
     * Gets all arrived/converted consultations that have at least one payment
     * in the date range. This is the initial candidate set before full validation.
     *
     * @param  array  $consultantIds  Doctor IDs
     * @param  array  $locations  Location IDs
     * @param  string  $startDate  Y-m-d
     * @param  string  $endDate  Y-m-d
     * @param  array  $extraWhere  Additional where conditions
     * @param  array  $eagerLoad  Relations to eager load
     */
    /**
     * Fast variant of fetchCandidateAppointments — drives from
     * `package_advances` (already date-filtered) and looks up matching
     * appointments. The legacy method scans appointments first, which
     * means iterating ~90K type=1 rows to find ~350 with in-range
     * payments. Reversing that drops the candidate fetch from ~1.1s to
     * ~40ms while returning the exact same set.
     *
     * Internal — used only by the bulk leaderboard path. Public
     * `fetchCandidateAppointments` and the reports flow keep the
     * legacy join.
     *
     * @param  list<int>  $consultantIds
     * @param  list<int>  $locations
     * @return Collection<int, \App\Models\Appointments>
     */
    private function fetchCandidateAppointmentsBulk(
        array $consultantIds,
        array $locations,
        int $arrivedStatusId,
        ?int $convertedStatusId,
        string $startDate,
        string $endDate,
        int $accountId,
    ): Collection {
        $statusIds = array_filter([$arrivedStatusId, $convertedStatusId]);

        $apptIds = DB::table('package_advances as pa')
            ->join('appointments as a', 'a.id', '=', 'pa.appointment_id')
            ->where('pa.account_id', $accountId)
            ->where('pa.cash_amount', '>', 0)
            ->whereBetween('pa.created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59'])
            ->whereNotNull('pa.appointment_id')
            ->where('a.appointment_type_id', 1)
            ->whereIn('a.appointment_status_id', $statusIds)
            ->whereIn('a.doctor_id', $consultantIds)
            ->whereIn('a.location_id', $locations)
            ->where('a.scheduled_date', '<=', $endDate)
            ->whereNull('a.deleted_at')
            ->distinct()
            ->pluck('a.id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($apptIds === []) {
            return collect();
        }

        // Eager-load every relation that getValidatedConversions reads
        // per appointment. Without these, the validation loop fires
        // ~N×3 lazy-load N+1 queries (doctor, service, location).
        return Appointments::with([
            'doctor:id,name',
            'patient:id,name',
            'service:id,name',
            'location:id,name',
        ])
            ->whereIn('id', $apptIds)
            ->get();
    }

    public function fetchCandidateAppointments(
        array $consultantIds,
        array $locations,
        int $arrivedStatusId,
        ?int $convertedStatusId,
        string $startDate,
        string $endDate,
        array $extraWhere = [],
        array $eagerLoad = ['location:id,name']
    ): Collection {
        // Build status IDs from dynamic lookup (avoid hardcoded [2, 16])
        $statusIds = array_filter([$arrivedStatusId, $convertedStatusId]);

        $query = Appointments::with($eagerLoad)
            ->leftJoin('package_advances', 'package_advances.appointment_id', '=', 'appointments.id')
            ->where('appointments.appointment_type_id', 1)
            ->whereIn('appointments.appointment_status_id', $statusIds)
            ->whereIn('appointments.doctor_id', $consultantIds)
            ->whereIn('appointments.location_id', $locations)
            ->where('appointments.scheduled_date', '<=', $endDate)
            ->where('package_advances.cash_amount', '>', 0)
            ->where('package_advances.created_at', '>=', $startDate.' 00:00:00')
            ->where('package_advances.created_at', '<=', $endDate.' 23:59:59')
            ->select('appointments.*');

        foreach ($extraWhere as $condition) {
            $query->where(...$condition);
        }

        return $query->distinct()->get();
    }

    /**
     * Get total arrived appointments count (for conversion rate denominator).
     *
     * @param  array|null  $doctorIds  If null, don't filter by doctor
     */
    public function getTotalArrivedCount(
        array $locations,
        int $arrivedStatusId,
        ?int $convertedStatusId,
        string $startDate,
        string $endDate,
        ?array $doctorIds = null,
        array $extraWhere = []
    ): int {
        // Build status IDs from dynamic lookup (avoid hardcoded [2, 16])
        $statusIds = array_filter([$arrivedStatusId, $convertedStatusId]);

        $query = Appointments::whereIn('location_id', $locations)
            ->where('appointment_type_id', 1)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->whereIn('appointment_status_id', $statusIds);

        if ($doctorIds !== null) {
            $query->whereIn('doctor_id', $doctorIds);
        }

        foreach ($extraWhere as $condition) {
            $query->where(...$condition);
        }

        return $query->count();
    }

    /**
     * Get total arrived appointments grouped by doctor.
     *
     * @return array [doctor_id => count]
     */
    public function getTotalArrivedByDoctor(
        array $locations,
        int $arrivedStatusId,
        ?int $convertedStatusId,
        string $startDate,
        string $endDate,
        ?array $doctorIds = null,
        array $extraWhere = []
    ): array {
        $statusIds = array_filter([$arrivedStatusId, $convertedStatusId]);

        $query = Appointments::whereIn('location_id', $locations)
            ->where('appointment_type_id', 1)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->whereIn('appointment_status_id', $statusIds);

        if ($doctorIds !== null) {
            $query->whereIn('doctor_id', $doctorIds);
        }

        foreach ($extraWhere as $condition) {
            $query->where(...$condition);
        }

        return $query->selectRaw('doctor_id, COUNT(*) as total')
            ->groupBy('doctor_id')
            ->pluck('total', 'doctor_id')
            ->toArray();
    }

    /**
     * Get total arrived appointments grouped by service.
     */
    public function getTotalArrivedByService(
        array $consultantIds,
        array $locations,
        int $arrivedStatusId,
        ?int $convertedStatusId,
        string $startDate,
        string $endDate,
        array $extraWhere = []
    ): Collection {
        $statusIds = array_filter([$arrivedStatusId, $convertedStatusId]);

        $query = Appointments::join('services', 'appointments.service_id', '=', 'services.id')
            ->where('appointments.appointment_type_id', 1)
            ->whereIn('appointments.appointment_status_id', $statusIds)
            ->whereIn('appointments.doctor_id', $consultantIds)
            ->whereIn('appointments.location_id', $locations)
            ->whereBetween('appointments.scheduled_date', [$startDate, $endDate]);

        foreach ($extraWhere as $condition) {
            $query->where(...$condition);
        }

        return $query->select('appointments.service_id', 'services.name', DB::raw('COUNT(*) as arrived'))
            ->groupBy('appointments.service_id', 'services.name')
            ->get();
    }

    /**
     * Calculate conversion for a single doctor (used by Doctor Dashboard KPI).
     * Uses the same validated logic as the other endpoints.
     *
     * @param  string  $startDate  Y-m-d
     * @param  string  $endDate  Y-m-d
     * @return array ['total_arrived' => int, 'total_converted' => int, 'conversion_rate' => float, 'total_spend' => float, 'avg_client_value' => float]
     */
    public function calculateForDoctor(int $doctorId, string $startDate, string $endDate, int $accountId): array
    {
        $arrivedStatusId = $this->getArrivedStatusId($accountId);
        $convertedStatusId = $this->getConvertedStatusId($accountId);

        if (! $arrivedStatusId) {
            return $this->emptyDoctorResult();
        }

        $statusIds = array_filter([$arrivedStatusId, $convertedStatusId]);

        // Get all locations where this doctor is allocated
        $locations = DB::table('doctor_has_locations')
            ->where('user_id', $doctorId)
            ->where('is_allocated', 1)
            ->pluck('location_id')
            ->toArray();

        if (empty($locations)) {
            // Fallback: use all locations from doctor's appointments
            $locations = DB::table('appointments')
                ->where('doctor_id', $doctorId)
                ->whereBetween('scheduled_date', [$startDate, $endDate])
                ->whereNull('deleted_at')
                ->distinct()
                ->pluck('location_id')
                ->toArray();
        }

        // Total arrived consultations (filtered by allocated locations to match conversion report)
        $totalArrived = DB::table('appointments')
            ->where('doctor_id', $doctorId)
            ->where('appointment_type_id', 1)
            ->whereIn('appointment_status_id', $statusIds)
            ->whereIn('location_id', $locations)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->whereNull('deleted_at')
            ->count();

        // Fetch candidate appointments using shared logic
        $candidates = $this->fetchCandidateAppointments(
            [$doctorId],
            $locations,
            $arrivedStatusId,
            $convertedStatusId,
            $startDate,
            $endDate
        );

        // Validate conversions using shared logic
        $result = $this->getValidatedConversions($candidates, $startDate, $endDate);

        $totalConverted = $result['by_doctor'][$doctorId]['count'] ?? 0;
        $totalSpend = $result['by_doctor'][$doctorId]['spend'] ?? 0;

        $conversionRate = $totalArrived > 0 ? round(($totalConverted / $totalArrived) * 100, 1) : 0;
        $avgClientValue = $totalConverted > 0 ? round($totalSpend / $totalConverted, 2) : 0;

        return [
            'total_arrived' => $totalArrived,
            'total_converted' => $totalConverted,
            'conversion_rate' => $conversionRate,
            'total_spend' => $totalSpend,
            'avg_client_value' => $avgClientValue,
        ];
    }

    /**
     * Bulk per-doctor validated conversions at a single branch. One call
     * runs the same pipeline as `LoadConversionReport` (allocated-doctor
     * scope → fetchCandidateAppointments → getValidatedConversions) and
     * returns per-doctor `count` + `spend`. Preferred entry point for
     * leaderboard-style widgets that would otherwise fan out to
     * `calculateForDoctor` per consultant.
     *
     * Matches `/admin/reports/conversion` byte-for-byte on the same scope.
     *
     * @return array<int, array{count:int, spend:float}>  keyed by doctor_id
     */
    /**
     * Bulk variant — one validation pass covers every branch in scope.
     * Returns `branch_id -> doctor_id -> {count, spend}` so a leaderboard
     * can iterate branches without fan-out. The single-branch
     * `byDoctorsAtBranch` stays as the modal/drill-down entry point.
     *
     * @param  list<int>  $branchIds
     * @return array<int, array<int, array{count:int, spend:float}>>
     */
    public function byBranchAndDoctor(array $branchIds, string $startDate, string $endDate, int $accountId): array
    {
        if ($branchIds === []) {
            return [];
        }

        $arrivedStatusId = $this->getArrivedStatusId($accountId);
        $convertedStatusId = $this->getConvertedStatusId($accountId);
        if (! $arrivedStatusId) {
            return [];
        }

        // Allocated consultants ACROSS ALL branches in one query.
        $consultants = DB::table('doctor_has_locations')
            ->whereIn('location_id', $branchIds)
            ->where('is_allocated', 1)
            ->distinct()
            ->pluck('user_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        if ($consultants === []) {
            return [];
        }

        $candidates = $this->fetchCandidateAppointmentsBulk(
            $consultants,
            $branchIds,
            $arrivedStatusId,
            $convertedStatusId,
            $startDate,
            $endDate,
            $accountId,
        );

        $result = $this->getValidatedConversions($candidates, $startDate, $endDate);
        $byBranchDoctor = $result['by_branch_doctor'] ?? [];

        $out = [];
        foreach ($byBranchDoctor as $branchId => $doctors) {
            foreach ($doctors as $docId => $row) {
                $out[(int) $branchId][(int) $docId] = [
                    'count' => (int) ($row['count'] ?? 0),
                    'spend' => (float) ($row['spend'] ?? 0),
                ];
            }
        }

        return $out;
    }

    public function byDoctorsAtBranch(int $branchId, string $startDate, string $endDate, int $accountId): array
    {
        $arrivedStatusId = $this->getArrivedStatusId($accountId);
        $convertedStatusId = $this->getConvertedStatusId($accountId);

        if (! $arrivedStatusId) {
            return [];
        }

        // Allocated consultants at this branch — same rule as LoadConversionReport.
        $consultants = DB::table('doctor_has_locations')
            ->where('location_id', $branchId)
            ->where('is_allocated', 1)
            ->distinct()
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($consultants === []) {
            return [];
        }

        $candidates = $this->fetchCandidateAppointments(
            $consultants,
            [$branchId],
            $arrivedStatusId,
            $convertedStatusId,
            $startDate,
            $endDate,
            [],
            ['doctor:id,name', 'patient:id,name']
        );

        $result = $this->getValidatedConversions($candidates, $startDate, $endDate);
        $byDoctor = [];
        foreach ($result['by_doctor'] as $docId => $row) {
            $byDoctor[(int) $docId] = [
                'count' => (int) ($row['count'] ?? 0),
                'spend' => (float) ($row['spend'] ?? 0),
            ];
        }

        return $byDoctor;
    }

    /**
     * Get arrived status ID by account (with caching).
     */
    private function getArrivedStatusId(int $accountId): ?int
    {
        $status = DB::table('appointment_statuses')
            ->where('account_id', $accountId)
            ->where('is_arrived', 1)
            ->first();

        return $status ? (int) $status->id : null;
    }

    /**
     * Get converted status ID by account (with caching).
     */
    private function getConvertedStatusId(int $accountId): ?int
    {
        $status = DB::table('appointment_statuses')
            ->where('account_id', $accountId)
            ->where('is_converted', 1)
            ->first();

        return $status ? (int) $status->id : null;
    }

    private function emptyResult(): array
    {
        return [
            'conversions' => [],
            'by_doctor' => [],
            'by_service' => [],
            'total_spend' => 0,
        ];
    }

    private function emptyDoctorResult(): array
    {
        return [
            'total_arrived' => 0,
            'total_converted' => 0,
            'conversion_rate' => 0,
            'total_spend' => 0,
            'avg_client_value' => 0,
        ];
    }
}
