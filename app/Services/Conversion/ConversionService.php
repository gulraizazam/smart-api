<?php

namespace App\Services\Conversion;

use App\Helpers\DoctorDashboardHelper;
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
 * Conversion spend = sum of (revenue_in - refund_out) via genericfunctionforstaffwiserevenue
 * for all payments from invoice date within the report date range.
 */
class ConversionService
{
    /**
     * Get validated conversions for given appointments.
     * 
     * This is the CORE method that all endpoints must use.
     * Returns per-appointment conversion data including spend.
     *
     * @param Collection $convertedAppointments Appointment models (pre-filtered by status, type, payment existence)
     * @param string $startDate Y-m-d
     * @param string $endDate Y-m-d
     * @param bool $includeDetails Whether to include patient/doctor/service detail info (for reports)
     * @param bool $canViewContact Whether to mask phone numbers
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
            ->map(fn($group) => $group->first());

        // Get ALL packages per appointment (not keyBy which loses duplicates)
        $allPackages = Packages::whereIn('appointment_id', $appointmentIds)->get()->groupBy('appointment_id');
        $allPackageIds = $allPackages->flatten()->pluck('id')->toArray();

        // Bulk fetch package bundles
        $packageBundles = PackageBundles::whereIn('package_id', $allPackageIds)->get()->groupBy('package_id');

        // Bulk fetch package services existence (min created_at per bundle)
        $allBundleIds = $packageBundles->flatten()->pluck('id')->toArray();
        $packageServicesExist = [];
        if (!empty($allBundleIds)) {
            $packageServicesExist = PackageService::whereIn('package_bundle_id', $allBundleIds)
                ->select('package_bundle_id', DB::raw('MIN(created_at) as min_created_at'))
                ->groupBy('package_bundle_id')
                ->get()
                ->keyBy('package_bundle_id');
        }

        // Bulk fetch first payments per package (cash_flow='in', cash_amount>0, not deleted)
        $firstPayments = PackageAdvances::whereIn('package_id', $allPackageIds)
            ->where('cash_flow', 'in')
            ->where('cash_amount', '>', 0)
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'asc')
            ->get()
            ->groupBy('package_id')
            ->map(fn($group) => $group->first());

        // Bulk fetch all package advances for conversion spend (within date range, cash_amount > 0)
        $allPackageAdvances = PackageAdvances::whereIn('package_id', $allPackageIds)
            ->where('cash_amount', '>', 0)
            ->whereNull('deleted_at')
            ->where('created_at', '>=', $startDate . ' 00:00:00')
            ->where('created_at', '<=', $endDate . ' 23:59:59')
            ->get()
            ->groupBy('package_id');

        $processedAppointments = [];
        $conversions = [];
        $byDoctor = [];
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
            if (!$invoice) {
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
            if (!$hasServiceAfterInvoice) {
                if ($includeDetails) {
                    $conversions[$appointment->id] = $detailInfo;
                }
                continue;
            }

            // Step 4: Find first payment across all packages (on or after invoice date)
            $earliestPayment = null;
            foreach ($packageIds as $pkgId) {
                $fp = $firstPayments->get($pkgId);
                if ($fp) {
                    $fpDate = Carbon::parse($fp->created_at)->format('Y-m-d');
                    if ($fpDate >= $invoiceDate) {
                        if (!$earliestPayment || $fp->created_at < $earliestPayment->created_at) {
                            $earliestPayment = $fp;
                        }
                    }
                }
            }

            if (!$earliestPayment) {
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
                    ->filter(fn($pa) => Carbon::parse($pa->created_at)->format('Y-m-d') >= $invoiceDate);

                foreach ($advances as $advance) {
                    $hasAdvances = true;
                    $package_advance = GeneralFunctions::genericfunctionforstaffwiserevenue($advance);
                    if ($package_advance) {
                        $revenue_in += (float) ($package_advance['revenue'] ?? 0);
                        $out += (float) ($package_advance['refund_out'] ?? 0);
                    }
                }
            }

            if (!$hasAdvances) {
                if ($includeDetails) {
                    $conversions[$appointment->id] = $detailInfo;
                }
                continue;
            }

            $actual = $revenue_in - $out;
            $totalSpend += $actual;

            // Track by doctor
            $doctorId = $appointment->doctor_id;
            if (!isset($byDoctor[$doctorId])) {
                $byDoctor[$doctorId] = ['count' => 0, 'spend' => 0];
            }
            $byDoctor[$doctorId]['count']++;
            $byDoctor[$doctorId]['spend'] += $actual;

            // Track by service
            $serviceId = $appointment->service_id ?? ($appointment->service->id ?? 0);
            $serviceName = $appointment->service->name ?? '';
            if (!isset($byService[$serviceId])) {
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
            'by_service' => $byService,
            'total_spend' => $totalSpend,
        ];
    }

    /**
     * Fetch candidate converted appointments (pre-filter).
     * 
     * Gets all arrived/converted consultations that have at least one payment
     * in the date range. This is the initial candidate set before full validation.
     *
     * @param array $consultantIds Doctor IDs
     * @param array $locations Location IDs
     * @param int $arrivedStatusId
     * @param int|null $convertedStatusId
     * @param string $startDate Y-m-d
     * @param string $endDate Y-m-d
     * @param array $extraWhere Additional where conditions
     * @param array $eagerLoad Relations to eager load
     * @return Collection
     */
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
        $query = Appointments::with($eagerLoad)
            ->leftJoin('package_advances', 'package_advances.appointment_id', '=', 'appointments.id')
            ->where('appointments.appointment_type_id', 1)
            ->whereIn('appointments.appointment_status_id', DoctorDashboardHelper::getConsultationStatusIds())
            ->whereIn('appointments.doctor_id', $consultantIds)
            ->whereIn('appointments.location_id', $locations)
            ->where('package_advances.cash_amount', '>', 0)
            ->where('package_advances.created_at', '>=', $startDate . ' 00:00:00')
            ->where('package_advances.created_at', '<=', $endDate . ' 23:59:59')
            ->select('appointments.*');

        foreach ($extraWhere as $condition) {
            $query->where(...$condition);
        }

        return $query->distinct()->get();
    }

    /**
     * Get total arrived appointments count (for conversion rate denominator).
     *
     * @param array $locations
     * @param int $arrivedStatusId
     * @param int|null $convertedStatusId
     * @param string $startDate
     * @param string $endDate
     * @param array|null $doctorIds If null, don't filter by doctor
     * @param array $extraWhere
     * @return int
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
        $query = Appointments::whereIn('location_id', $locations)
            ->where('appointment_type_id', 1)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->whereIn('appointment_status_id', DoctorDashboardHelper::getConsultationStatusIds());

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
     * @param array $locations
     * @param int $arrivedStatusId
     * @param int|null $convertedStatusId
     * @param string $startDate
     * @param string $endDate
     * @param array|null $doctorIds
     * @param array $extraWhere
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
        $query = Appointments::whereIn('location_id', $locations)
            ->where('appointment_type_id', 1)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->whereIn('appointment_status_id', DoctorDashboardHelper::getConsultationStatusIds());

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
     *
     * @param array $consultantIds
     * @param array $locations
     * @param int $arrivedStatusId
     * @param int|null $convertedStatusId
     * @param string $startDate
     * @param string $endDate
     * @param array $extraWhere
     * @return Collection
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
        $query = Appointments::join('services', 'appointments.service_id', '=', 'services.id')
            ->where('appointments.appointment_type_id', 1)
            ->whereIn('appointments.appointment_status_id', DoctorDashboardHelper::getConsultationStatusIds())
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
     * @param int $doctorId
     * @param string $startDate Y-m-d
     * @param string $endDate Y-m-d
     * @param int $accountId
     * @return array ['total_arrived' => int, 'total_converted' => int, 'conversion_rate' => float, 'total_spend' => float, 'avg_client_value' => float]
     */
    public function calculateForDoctor(int $doctorId, string $startDate, string $endDate, int $accountId): array
    {
        $arrivedStatusId = $this->getArrivedStatusId($accountId);
        $convertedStatusId = $this->getConvertedStatusId($accountId);

        if (!$arrivedStatusId) {
            return $this->emptyDoctorResult();
        }

        $consultationStatusIds = DoctorDashboardHelper::getConsultationStatusIds();

        // Total arrived consultations
        $totalArrived = DB::table('appointments')
            ->where('doctor_id', $doctorId)
            ->where('appointment_type_id', 1)
            ->whereIn('appointment_status_id', $consultationStatusIds)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->count();

        if ($totalArrived === 0) {
            return $this->emptyDoctorResult();
        }

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
                ->distinct()
                ->pluck('location_id')
                ->toArray();
        }

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

    /**
     * @return array
     */
    private function emptyResult(): array
    {
        return [
            'conversions' => [],
            'by_doctor' => [],
            'by_service' => [],
            'total_spend' => 0,
        ];
    }

    /**
     * @return array
     */
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
