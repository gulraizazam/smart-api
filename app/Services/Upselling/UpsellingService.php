<?php

declare(strict_types=1);

namespace App\Services\Upselling;

use App\Helpers\ACL;
use App\Models\PackageService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\Upselling\Rollup\UpsellCollectedBuilder;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use App\Enums\AppointmentType;

/**
 * Centralized Upselling Service
 *
 * Single source of truth for doctor upselling calculations.
 * Used by both the dashboard chart and the upselling report.
 *
 * Logic: SUM(package_services.tax_including_price) grouped by sold_by,
 * excluding self-consultation sales (appointment_type_id == 1 AND doctor_id == sold_by).
 */
class UpsellingService
{
    /**
     * Get doctor upselling data for a centre and date range.
     *
     * @param string|int|array $centreId  Location ID, array of IDs, or 'all'
     * @param string $startDate     Y-m-d H:i:s
     * @param string $endDate       Y-m-d H:i:s
     * @return array Collection of objects with doctor_id, doctor_name, total_upselling_amount
     */
    public function getDoctorUpsellingData(string|int|array $centreId, string $startDate, string $endDate): array
    {
        $userLocations = ACL::getUserCentres();

        $isAll = $centreId === 'all';
        $locationFilter = $isAll ? $userLocations : (is_array($centreId) ? array_map('intval', $centreId) : [(int) $centreId]);

        // Get users with doctor/consultant roles
        $roleHasUsers = User::select('users.id')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->whereIn('roles.name', ['Aesthetic Doctor', 'Lifestyle Consultant'])
            ->where('model_has_roles.model_type', 'App\\Models\\User')
            ->pluck('id');

        // Get FDM users with location filter
        $fdmUserIds = User::select('users.id')
            ->join('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->join('user_has_locations', 'users.id', '=', 'user_has_locations.user_id')
            ->where('roles.name', 'FDM')
            ->where('model_has_roles.model_type', 'App\\Models\\User')
            ->when(!$isAll, fn ($q) => $q->whereIn('user_has_locations.location_id', $locationFilter))
            ->distinct()
            ->pluck('id');

        // Get doctors for the location(s)
        $doctorIds = DB::table('doctor_has_locations')
            ->whereIn('location_id', $locationFilter)
            ->whereIn('user_id', $roleHasUsers)
            ->distinct()
            ->pluck('user_id');

        $allSellerIds = $doctorIds->merge($fdmUserIds)->unique();

        if ($allSellerIds->isEmpty()) {
            return [];
        }

        // Get all active users keyed by ID
        $allActiveUsers = User::whereIn('id', $allSellerIds)
            ->where('active', 1)
            ->select('id', 'name')
            ->get()
            ->keyBy('id');

        // Core upselling query: SUM tax_including_price grouped by sold_by
        // Exclude self-consultation sales
        $upsellingData = PackageService::query()
            ->join('packages', 'package_services.package_id', '=', 'packages.id')
            ->join('appointments', 'packages.appointment_id', '=', 'appointments.id')
            ->whereIn('package_services.sold_by', $allSellerIds)
            ->whereBetween('package_services.created_at', [$startDate, $endDate])
            ->whereNotNull('package_services.sold_by')
            ->whereIn('packages.location_id', $locationFilter)
            // Exclude self-consultation sales
            ->where(function ($query) {
                $query->where('appointments.appointment_type_id', '!=', AppointmentType::Consultancy->value)
                    ->orWhereColumn('appointments.doctor_id', '!=', 'package_services.sold_by');
            })
            ->groupBy('package_services.sold_by')
            ->select(
                'package_services.sold_by',
                DB::raw('SUM(package_services.tax_including_price) as total_upselling_amount')
            )
            ->get()
            ->keyBy('sold_by');

        // Combine all users with their upselling data
        $reportData = $allActiveUsers->map(fn($user) => (object) [
                'doctor_id' => $user->id,
                'doctor_name' => $user->name,
                'total_upselling_amount' => $upsellingData->get($user->id)->total_upselling_amount ?? 0,
            ])->sortByDesc('total_upselling_amount')->values()->toArray();

        return $reportData;
    }

    /**
     * Resolve start/end dates from a dashboard period string.
     *
     * @param string $period  today|yesterday|last7days|week|thismonth|lastmonth
     * @return array [start_date, end_date] with H:i:s
     */
    public static function resolvePeriodDates(string $period): array
    {
        $periods = [
            'today' => [
                'start_date' => now()->format('Y-m-d 00:00:00'),
                'end_date' => now()->format('Y-m-d 23:59:59'),
            ],
            'yesterday' => [
                'start_date' => now()->subDay(1)->format('Y-m-d 00:00:00'),
                'end_date' => now()->subDay(1)->format('Y-m-d 23:59:59'),
            ],
            'last7days' => [
                'start_date' => now()->subDay(6)->format('Y-m-d 00:00:00'),
                'end_date' => now()->subDay(1)->format('Y-m-d 23:59:59'),
            ],
            'week' => [
                'start_date' => now()->startOfWeek()->format('Y-m-d 00:00:00'),
                'end_date' => now()->subDay(1)->format('Y-m-d 23:59:59'),
            ],
            'thismonth' => [
                'start_date' => now()->startOfMonth()->format('Y-m-d 00:00:00'),
                'end_date' => now()->subDay(1)->format('Y-m-d 23:59:59'),
            ],
            'lastmonth' => [
                'start_date' => now()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d 00:00:00'),
                'end_date' => now()->subMonthNoOverflow()->endOfMonth()->format('Y-m-d 23:59:59'),
            ],
        ];

        $p = $periods[$period] ?? $periods['thismonth'];

        return [$p['start_date'], $p['end_date']];
    }

    /**
     * Get detailed package services for a specific doctor's upselling.
     *
     * @param int[] $locationIds
     */
    public function getDoctorUpsellingDetailData(int $doctorId, array $locationIds, string $startDate, string $endDate): array
    {
        // Get doctor name
        $doctorName = User::find($doctorId)?->name ?? 'Unknown Doctor';

        // Get all package services for this doctor in the period
        $packageServices = PackageService::query()
            ->join('packages', 'package_services.package_id', '=', 'packages.id')
            ->join('appointments', 'packages.appointment_id', '=', 'appointments.id')
            ->join('services', 'package_services.service_id', '=', 'services.id')
            ->where('package_services.sold_by', $doctorId)
            ->whereIn('packages.location_id', $locationIds)
            ->whereBetween('package_services.created_at', [$startDate, $endDate])
            ->whereNotNull('sold_by')
            ->where(function($query) {
                $query->where('appointments.appointment_type_id', '!=', AppointmentType::Consultancy->value)
                      ->orWhereColumn('appointments.doctor_id', '!=', 'package_services.sold_by');
            })
            ->select(
                'package_services.id',
                'package_services.package_id',
                'package_services.sold_by',
                'package_services.service_id',
                'package_services.tax_including_price',
                'package_services.created_at',
                'services.name as service_name',
                'appointments.patient_id',
                'appointments.name as patient_name',
                'appointments.scheduled_date'
            )
            ->orderBy('package_services.created_at', 'desc')
            ->get();

        $totalAmount = $packageServices->sum('tax_including_price');
        $uniqueUpsellings = $packageServices->pluck('package_id')->unique()->count();

        return [
            'packageServices' => $packageServices,
            'doctorName' => $doctorName,
            'totalAmount' => $totalAmount,
            'uniqueUpsellings' => $uniqueUpsellings,
        ];
    }

    /**
     * Get consultant breakdown data for a specific doctor.
     *
     * @param int[] $locationIds
     */
    public function getDoctorConsultantBreakdownData(int $doctorId, array $locationIds, string $startDate, string $endDate): array
    {
        // Get the doctor information
        $doctor = User::find($doctorId);

        if (!$doctor) {
            return ['error' => 'Doctor not found.'];
        }

        // Get all services sold by this doctor with consultant information
        $servicesWithConsultants = PackageService::query()
            ->join('packages', 'package_services.package_id', '=', 'packages.id')
            ->join('appointments', 'packages.appointment_id', '=', 'appointments.id')
            ->join('users as consultants', 'appointments.doctor_id', '=', 'consultants.id')
            ->where('package_services.sold_by', $doctorId)
            ->whereBetween('package_services.created_at', [$startDate, $endDate])
            ->whereNotNull('sold_by')
            ->whereIn('packages.location_id', $locationIds)
            // Exclude self-consultation sales
            ->where(function($query) use ($doctorId) {
                $query->where('appointments.appointment_type_id', '!=', AppointmentType::Consultancy->value)
                    ->orWhere('appointments.doctor_id', '!=', $doctorId);
            })
            ->select(
                'appointments.doctor_id as consultant_id',
                'consultants.name as consultant_name',
                DB::raw('SUM(package_services.tax_including_price) as total_amount'),
                DB::raw('COUNT(package_services.id) as service_count')
            )
            ->groupBy('appointments.doctor_id', 'consultants.name')
            ->orderByDesc('total_amount')
            ->get();

        // Calculate total upselling
        $totalUpselling = $servicesWithConsultants->sum('total_amount');

        // Add percentage to each consultant
        $breakdownData = $servicesWithConsultants->map(function($item) use ($totalUpselling) {
            $item->percentage = $totalUpselling > 0 ? ($item->total_amount / $totalUpselling) * 100 : 0;
            return $item;
        });

        // Get location names (may be multiple when multiple centres were selected)
        $locations = DB::table('locations')->whereIn('id', $locationIds)->get();
        $location = (object) [
            'id' => $locations->pluck('id')->implode(','),
            'name' => $locations->pluck('name')->implode(', '),
        ];

        return [
            'doctor' => $doctor,
            'breakdownData' => $breakdownData,
            'totalUpselling' => $totalUpselling,
            'location' => $location,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];
    }

    /**
     * Get consultant revenue report data.
     *
     * @param int|int[] $locationId  Single location ID (legacy) or array of IDs
     */
    public function getConsultantRevenueReportData(int|array $locationId, string $startDate, string $endDate): array
    {
        $locationIds = is_array($locationId) ? array_map('intval', $locationId) : [(int) $locationId];

        // Step 1: Get only Consultant and Lifestyle Consultant users who are active
        $consultantUserIds = User::whereHas('roles', function($query) {
            $query->where('name', 'Consultant')->orWhere('name', 'Lifestyle Consultant');
        })->where('active', 1)->pluck('id');

        // Step 2: Get consultants assigned to the specific location(s)
        $consultantIds = DB::table('doctor_has_locations')
            ->whereIn('location_id', $locationIds)
            ->whereIn('user_id', $consultantUserIds)
            ->distinct()
            ->pluck('user_id');

        if ($consultantIds->isEmpty()) {
            return ['empty' => true, 'message' => 'No consultants found for the selected location.'];
        }

        // Get all potential sellers (doctors + FDMs)
        $roleHasUsers = User::whereHas('roles', function($query) {
            $query->where('name', 'Aesthetic Doctor')->orWhere('name','Lifestyle Consultant');
        })->pluck('id');

        $fdmUserIds = User::whereHas('roles', function ($q) {
                $q->where('name', 'FDM');
            })
            ->whereHas('user_has_locations', function ($q) use ($locationIds) {
                $q->whereIn('location_id', $locationIds);
            })
            ->pluck('id');

        $allSellerIds = $roleHasUsers->merge($fdmUserIds)->unique();

        // Get package services with consultant information
        $packageServices = PackageService::query()
            ->join('packages', 'package_services.package_id', '=', 'packages.id')
            ->join('appointments', 'packages.appointment_id', '=', 'appointments.id')
            ->whereIn('package_services.sold_by', $allSellerIds)
            ->whereIn('appointments.doctor_id', $consultantIds)
            ->whereBetween('package_services.created_at', [$startDate, $endDate])
            ->whereNotNull('sold_by')
            ->whereIn('packages.location_id', $locationIds)
            // Exclude self-consultation sales
            ->where(function($query) {
                $query->where('appointments.appointment_type_id', '!=', AppointmentType::Consultancy->value)
                    ->orWhereColumn('appointments.doctor_id', '!=', 'package_services.sold_by');
            })
            ->select(
                'package_services.package_id',
                'appointments.doctor_id as consultant_id',
                'package_services.tax_including_price',
                'package_services.is_consumed',
                'package_services.consumed_at'
            )
            ->get();

        // Group services by package_id
        $servicesByPackage = $packageServices->groupBy('package_id');

        // Initialize consultant revenue tracking
        $consultantRevenue = [];
        foreach ($consultantIds as $consultantId) {
            $consultantRevenue[(int)$consultantId] = [
                'total_consultation_revenue' => 0,
                'total_consumed_amount' => 0,
            ];
        }

        // Process each package
        foreach ($servicesByPackage as $packageId => $services) {
            // Get total payments for this package in the date range
            $totalPayments = DB::table('package_advances')
                ->where('package_id', $packageId)
                ->where('cash_flow', 'in')
                ->where('is_refund', 0)
                ->where('is_adjustment', 0)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->sum('cash_amount');

            if ($totalPayments <= 0) {
                continue;
            }

            // Calculate total service amount for this package
            $totalServiceAmount = $services->sum('tax_including_price');

            if ($totalServiceAmount <= 0) {
                continue;
            }

            // Cap payments at total service amount
            $actualRevenue = min($totalPayments, $totalServiceAmount);

            // Distribute revenue to consultants proportionally
            foreach ($services as $service) {
                $consultantId = (int)$service->consultant_id;

                if (!isset($consultantRevenue[$consultantId])) {
                    continue;
                }

                $serviceAmount = $service->tax_including_price;

                if ($serviceAmount <= 0) {
                    continue;
                }

                // Calculate proportional share
                $serviceShare = ($serviceAmount / $totalServiceAmount) * $actualRevenue;

                // Add to total consultation revenue
                $consultantRevenue[$consultantId]['total_consultation_revenue'] += $serviceShare;

                // Add to consumed amount if service is consumed in date range
                if ($service->is_consumed == 1 &&
                    $service->consumed_at >= $startDate &&
                    $service->consumed_at <= $endDate) {
                    $consultantRevenue[$consultantId]['total_consumed_amount'] += $serviceShare;
                }
            }
        }

        // Get consultant names and prepare report data
        $consultants = User::whereIn('id', $consultantIds)
            ->select('id', 'name')
            ->get()
            ->keyBy('id');

        $reportData = collect();
        foreach ($consultantRevenue as $consultantId => $revenue) {
            $consultant = $consultants->get($consultantId);

            if ($consultant) {
                $reportData->push((object)[
                    'consultant_id' => $consultantId,
                    'consultant_name' => $consultant->name,
                    'total_consultation_revenue' => $revenue['total_consultation_revenue'],
                    'total_consumed_amount' => $revenue['total_consumed_amount'],
                ]);
            }
        }

        // Sort by revenue descending
        $reportData = $reportData->sortByDesc('total_consultation_revenue')->values();

        return [
            'empty' => false,
            'reportData' => $reportData,
            'consultant_ids' => $consultantIds->toArray(),
            'all_seller_ids' => $allSellerIds->toArray(),
        ];
    }

    /**
     * Get consultant revenue detail data.
     */
    public function getConsultantRevenueDetailData(int $consultantId, array $filters): array
    {
        $locationIds = (array) ($filters['location_ids'] ?? [$filters['location_id'] ?? 0]);

        $reportQuery = PackageService::query()
            ->join('users', 'package_services.sold_by', '=', 'users.id')
            ->join('packages', 'package_services.package_id', '=', 'packages.id')
            ->join('appointments', 'packages.appointment_id', '=', 'appointments.id')
            ->join('services', 'package_services.service_id', '=', 'services.id')
            ->where('package_services.sold_by', $consultantId)
            ->whereIn('package_services.sold_by', $filters['consultant_ids'])
            ->whereIn('packages.location_id', $locationIds)
            ->whereBetween('package_services.created_at', [$filters['start_date'], $filters['end_date']])
            ->whereNotNull('sold_by');

        $detailData = $reportQuery
            ->select(
                'users.name as consultant_name',
                'package_services.package_id',
                'services.name as service_name',
                'package_services.tax_including_price',
                'package_services.created_at',
                'appointments.patient_id',
                'appointments.name as patient_name',
                'appointments.scheduled_date',
                DB::raw("
                    CASE
                        WHEN (appointments.appointment_type_id = 1 AND appointments.doctor_id = package_services.sold_by)
                        THEN package_services.tax_including_price
                        ELSE 0
                    END as actual_amount
                ")
            )
            ->where(DB::raw("
                CASE
                    WHEN (appointments.appointment_type_id = 1 AND appointments.doctor_id = package_services.sold_by)
                    THEN package_services.tax_including_price
                    ELSE 0
                END
            "), '>', 0)
            ->orderBy('package_services.created_at', 'desc')
            ->get();

        $consultantName = $detailData->first()->consultant_name ?? 'Unknown Consultant';
        $totalAmount = $detailData->sum('actual_amount');

        // Count unique consultations (appointments) instead of service records
        $uniqueConsultations = $detailData->unique('package_id')->count();

        return [
            'detailData' => $detailData,
            'consultantName' => $consultantName,
            'totalAmount' => $totalAmount,
            'uniqueConsultations' => $uniqueConsultations,
        ];
    }

    /**
     * Get doctor consultant breakdown data (variant 1 - payment-based).
     */
    public function getDoctorConsultantBreakdown1Data(int $sellerId, array $filters): array
    {
        // Get seller information
        $seller = User::find($sellerId);
        if (!$seller) {
            return ['error' => 'Seller not found.'];
        }

        // Get all package services for this seller
        $packageServices = PackageService::query()
            ->join('packages', 'package_services.package_id', '=', 'packages.id')
            ->join('appointments', 'packages.appointment_id', '=', 'appointments.id')
            ->where('package_services.sold_by', $sellerId)
            ->whereIn('packages.location_id', (array) ($filters['location_ids'] ?? [$filters['location_id'] ?? 0]))
            ->whereBetween('package_services.created_at', [$filters['start_date'], $filters['end_date']])
            ->whereNotNull('sold_by')
            ->select(
                'package_services.id',
                'package_services.package_id',
                'package_services.sold_by',
                'package_services.tax_including_price',
                'package_services.created_at',
                'appointments.appointment_type_id',
                'appointments.doctor_id as appointment_doctor_id'
            )
            ->orderBy('package_services.created_at')
            ->get();

        // Initialize consultant tracking
        $consultantData = [];

        // Process services by package
        $servicesByPackage = $packageServices->groupBy('package_id');

        foreach ($servicesByPackage as $packageId => $services) {
            $sortedServices = $services->sortBy([
                ['created_at', 'asc'],
                ['id', 'asc']
            ])->values()->all();

            $totalServices = count($sortedServices);

            for ($i = 0; $i < $totalServices; $i++) {
                $service = $sortedServices[$i];

                // Skip self-consultation sales
                if ($service->appointment_type_id === AppointmentType::Consultancy->value && $service->appointment_doctor_id == $service->sold_by) {
                    continue;
                }

                $serviceAmount = $service->tax_including_price;

                if ($serviceAmount <= 0) {
                    continue;
                }

                $serviceCreatedAt = Carbon::parse($service->created_at);
                $consultantId = $service->appointment_doctor_id;

                // Find next service
                $nextService = null;
                if ($i < $totalServices - 1) {
                    $nextService = $sortedServices[$i + 1];
                }

                // Get payments for this service
                $paymentsQuery = DB::table('package_advances')
                    ->where('package_id', $packageId)
                    ->where('cash_flow', 'in')
                    ->where('is_refund', 0)
                    ->where('is_adjustment', 0)
                    ->whereDate('created_at', $serviceCreatedAt->toDateString())
                    ->where(function($q) use ($service) {
                        $q->where('created_at', '>', $service->created_at)
                          ->orWhere(function($q2) use ($service) {
                              $q2->where('created_at', '=', $service->created_at)
                                 ->where('id', '>', $service->id);
                          });
                    });

                if ($nextService && $nextService->created_at !== null) {
                    $nextServiceTime = Carbon::parse($nextService->created_at);
                    if ($nextServiceTime->toDateString() === $serviceCreatedAt->toDateString()) {
                        $paymentsQuery->where(function($q) use ($nextService) {
                            $q->where('created_at', '<', $nextService->created_at)
                              ->orWhere(function($q2) use ($nextService) {
                                  $q2->where('created_at', '=', $nextService->created_at)
                                     ->where('id', '<', $nextService->id);
                              });
                        });
                    }
                }

                $paymentsReceived = $paymentsQuery->sum('cash_amount');

                if ($paymentsReceived > 0) {
                    $upsellingAmount = min($paymentsReceived, $serviceAmount);

                    // Initialize consultant data if not exists
                    if (!isset($consultantData[$consultantId])) {
                        $consultantData[$consultantId] = [
                            'consultant_id' => $consultantId,
                            'consultant_name' => null, // Will be populated later
                            'total_amount' => 0,
                            'packages' => [],
                        ];
                    }

                    // Add to consultant's total
                    $consultantData[$consultantId]['total_amount'] += $upsellingAmount;
                    $consultantData[$consultantId]['packages'][$packageId] = true;
                }
            }
        }

        // Get consultant names
        $consultantIds = array_keys($consultantData);
        if (!empty($consultantIds)) {
            $consultants = User::whereIn('id', $consultantIds)
                ->select('id', 'name')
                ->get()
                ->keyBy('id');

            foreach ($consultantData as $consultantId => $data) {
                if (isset($consultants[$consultantId])) {
                    $consultantData[$consultantId]['consultant_name'] = $consultants[$consultantId]->name;
                } else {
                    $consultantData[$consultantId]['consultant_name'] = 'Unknown Consultant';
                }

                // Count unique packages
                $consultantData[$consultantId]['total_packages'] = count($consultantData[$consultantId]['packages']);
                unset($consultantData[$consultantId]['packages']); // Remove packages array, only keep count
            }
        }

        // Convert to collection and sort by total amount
        $consultantBreakdown = collect($consultantData)
            ->map(fn ($data) => (object) $data)
            ->sortByDesc('total_amount')
            ->values();

        // Calculate totals
        $totalSoldAmount = $consultantBreakdown->sum('total_amount');
        $totalPackages = $consultantBreakdown->sum('total_packages');
        $totalConsultants = $consultantBreakdown->count();

        $sellerName = $seller->name;

        return [
            'consultantBreakdown' => $consultantBreakdown,
            'sellerName' => $sellerName,
            'sellerId' => $sellerId,
            'totalSoldAmount' => $totalSoldAmount,
            'totalPackages' => $totalPackages,
            'totalConsultants' => $totalConsultants,
        ];
    }

    /**
     * Get consultant seller detail data.
     */
    public function getConsultantSellerDetailData(int $consultantId, int $sellerId, array $filters): array
    {
        // Get consultant and seller information
        $consultant = User::find($consultantId);
        $seller = User::find($sellerId);

        if (!$consultant || !$seller) {
            return ['error' => 'Consultant or seller not found.'];
        }

        // First, let's check what column exists in appointments table
        $appointmentColumns = Schema::getColumnListing('appointments');

        $doctorColumn = null;
        if (in_array('doctor_id', $appointmentColumns, true)) {
            $doctorColumn = 'doctor_id';
        } elseif (in_array('user_id', $appointmentColumns, true)) {
            $doctorColumn = 'user_id';
        } elseif (in_array('consultant_id', $appointmentColumns, true)) {
            $doctorColumn = 'consultant_id';
        } elseif (in_array('assigned_doctor_id', $appointmentColumns, true)) {
            $doctorColumn = 'assigned_doctor_id';
        }

        if (!$doctorColumn) {
            return ['error' => 'Unable to identify doctor column in appointments table.'];
        }

        $reportQuery = PackageService::query()
            ->join('packages', 'package_services.package_id', '=', 'packages.id')
            ->join('appointments', 'packages.appointment_id', '=', 'appointments.id')
            ->join('users as appointment_doctors', "appointments.{$doctorColumn}", '=', 'appointment_doctors.id')
            ->join('users as sellers', 'package_services.sold_by', '=', 'sellers.id')
            ->join('services', 'package_services.service_id', '=', 'services.id')
            ->where('package_services.sold_by', $sellerId)
            ->where("appointments.{$doctorColumn}", $consultantId)
            ->whereIn('package_services.sold_by', $filters['all_seller_ids'])
            ->whereIn('packages.location_id', (array) ($filters['location_ids'] ?? [$filters['location_id'] ?? 0]))
            ->whereBetween('package_services.created_at', [$filters['start_date'], $filters['end_date']])
            ->whereNotNull('sold_by');

        $detailData = $reportQuery
            ->select(
                'appointment_doctors.name as consultant_name',
                'sellers.name as seller_name',
                "appointments.{$doctorColumn} as consultant_id",
                'package_services.sold_by as seller_id',
                'package_services.package_id',
                'services.name as service_name',
                'package_services.tax_including_price',
                'package_services.created_at',
                'appointments.patient_id',
                'appointments.name as patient_name',
                'appointments.scheduled_date',
                'package_services.is_consumed',
                'package_services.consumed_at',
                DB::raw("
                    CASE
                        WHEN NOT (appointments.appointment_type_id = 1 AND appointments.{$doctorColumn} = package_services.sold_by)
                        THEN package_services.tax_including_price
                        ELSE 0
                    END as actual_amount
                "),
                DB::raw("
                    CASE
                        WHEN NOT (appointments.appointment_type_id = 1 AND appointments.{$doctorColumn} = package_services.sold_by)
                        AND package_services.is_consumed = 1
                        AND package_services.consumed_at BETWEEN ? AND ?
                        THEN package_services.tax_including_price
                        ELSE 0
                    END as consumed_amount
                ")
            )
            ->addBinding([$filters['start_date'], $filters['end_date']], 'select')
            ->where(DB::raw("
                CASE
                    WHEN NOT (appointments.appointment_type_id = 1 AND appointments.{$doctorColumn} = package_services.sold_by)
                    THEN package_services.tax_including_price
                    ELSE 0
                END
            "), '>', 0)
            ->orderBy('package_services.created_at', 'desc')
            ->get();

        $consultantName = $consultant->name;
        $sellerName = $seller->name;
        $totalAmount = $detailData->sum('actual_amount');
        $totalConsumedAmount = $detailData->sum('consumed_amount');

        // Count unique packages
        $uniquePackages = $detailData->unique('package_id')->count();
        $uniquePatients = $detailData->unique('patient_id')->count();

        return [
            'detailData' => $detailData,
            'consultantName' => $consultantName,
            'sellerName' => $sellerName,
            'totalAmount' => $totalAmount,
            'totalConsumedAmount' => $totalConsumedAmount,
            'uniquePackages' => $uniquePackages,
            'uniquePatients' => $uniquePatients,
            'consultantId' => $consultantId,
            'sellerId' => $sellerId,
        ];
    }

    /**
     * Get doctor upselling data for a specific centre (used by Excel export).
     */
    public function getDoctorUpsellingDataForCentre(int $centreId, string $startDate, string $endDate): array
    {
        try {
            return $this->getDoctorUpsellingData($centreId, $startDate, $endDate);
        } catch (\Exception $e) {
            \Log::error('Get Centre Data Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get all centre upselling data for Excel export.
     */
    public function getAllCentreUpsellingDataForExcel(string $period): array
    {
        // Hardcoded centre IDs
        $centreIds = [
            2 => 'CUTERA DHA Karachi',
            3 => 'CUTERA Bahadurabad Karachi',

            46 => 'CUTERA Johar Town',
            47 => 'CUTERA Johar Karachi',
            48 => 'CUTERA DHA Lahore',
            49 => 'CUTERA Gulberg Lahore',
            50 => 'CUTERA Faisalabad',
            51 => 'CUTERA F-7 Islamabad',

            53 => 'CUTERA Saddar Rawalpindi',
            54 => 'CUTERA I-8 Islamabad',
            55 => 'CUTERA Hyderabad',
            56 => 'CUTERA Sialkot'
        ];

        // Define date ranges
        $periods = [
            'yesterday' => [
                'start_date' => Carbon::now()->subDay(1)->format('Y-m-d 00:00:00'),
                'end_date' => Carbon::now()->subDay(1)->format('Y-m-d 23:59:59'),
                'label' => 'Yesterday'
            ],
            'last7days' => [
                'start_date' => Carbon::now()->subDay(6)->format('Y-m-d 00:00:00'),
                'end_date' => Carbon::now()->subDay(1)->format('Y-m-d 23:59:59'),
                'label' => 'Last 7 Days'
            ],
            'week' => [
                'start_date' => Carbon::now()->startOfWeek()->format('Y-m-d 00:00:00'),
                'end_date' => Carbon::now()->subDay(1)->format('Y-m-d 23:59:59'),
                'label' => 'This Week'
            ],
            'thismonth' => [
                'start_date' => Carbon::now()->startOfMonth()->format('Y-m-d 00:00:00'),
                'end_date' => Carbon::now()->subDay(1)->format('Y-m-d 23:59:59'),
                'label' => 'This Month'
            ],
            'lastmonth' => [
                'start_date' => Carbon::now()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d 00:00:00'),
                'end_date' => Carbon::now()->subMonthNoOverflow()->endOfMonth()->format('Y-m-d 23:59:59'),
                'label' => 'Last Month'
            ],
            'august2025' => [
                'start_date' => '2025-08-01 00:00:00',
                'end_date' => '2025-08-31 23:59:59',
                'label' => 'August 2025'
            ],
            'september2025' => [
                'start_date' => '2025-09-01 00:00:00',
                'end_date' => '2025-09-30 23:59:59',
                'label' => 'September 2025'
            ],
        ];

        $currentPeriod = $periods[$period];
        $allCentreData = [];

        // Get data for each centre
        foreach ($centreIds as $centreId => $centreName) {
            $centreData = $this->getDoctorUpsellingDataForCentre($centreId, $currentPeriod['start_date'], $currentPeriod['end_date']);

            // Include centre even if no data (will show empty sheet)
            $allCentreData[] = [
                'centre_name' => $centreName,
                'centre_id' => $centreId,
                'data' => $centreData
            ];
        }

        return [
            'allCentreData' => $allCentreData,
            'currentPeriod' => $currentPeriod,
        ];
    }

    /**
     * Generate Excel file from centre upselling data.
     *
     * @return string Path to the temporary file
     */
    public function generateExcelFile(array $allCentreData, string $fileName, array $periodInfo): string
    {
        // Check if PhpSpreadsheet is available
        if (!class_exists('\PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            throw new \Exception('PhpSpreadsheet is not installed. Run: composer require phpoffice/phpspreadsheet');
        }

        $spreadsheet = new Spreadsheet();

        // Remove default worksheet
        $spreadsheet->removeSheetByIndex(0);

        $sheetIndex = 0;
        foreach ($allCentreData as $centreInfo) {
            // Create worksheet for each centre
            $worksheet = $spreadsheet->createSheet($sheetIndex);

            // Clean sheet name (Excel has restrictions)
            $sheetName = substr(str_replace(['/', '*', '?', ':', '[', ']'], '', $centreInfo['centre_name']), 0, 31);
            $worksheet->setTitle($sheetName);

            // Set headers
            $worksheet->setCellValue('A1', 'Doctor Upselling Report - ' . $centreInfo['centre_name']);
            $worksheet->setCellValue('A2', 'Period: ' . $periodInfo['label']);
            $worksheet->setCellValue('A3', 'Date Range: ' . $periodInfo['start_date'] . ' to ' . $periodInfo['end_date']);

            // Style the header
            $worksheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
            $worksheet->getStyle('A2:A3')->getFont()->setBold(true);

            // Table headers
            $worksheet->setCellValue('A5', 'Doctor ID');
            $worksheet->setCellValue('B5', 'Doctor Name');
            $worksheet->setCellValue('C5', 'Total Upselling Amount');

            // Style table headers
            $worksheet->getStyle('A5:C5')->getFont()->setBold(true);
            $worksheet->getStyle('A5:C5')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('E2EFDA');

            // Add data
            $row = 6;
            $totalAmount = 0;

            if (!empty($centreInfo['data'])) {
                foreach ($centreInfo['data'] as $doctorData) {
                    $worksheet->setCellValue('A' . $row, $doctorData['doctor_id']);
                    $worksheet->setCellValue('B' . $row, $doctorData['doctor_name']);
                    $worksheet->setCellValue('C' . $row, $doctorData['total_upselling_amount']);

                    // Format currency
                    $worksheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0.00');

                    $totalAmount += $doctorData['total_upselling_amount'];
                    $row++;
                }
            } else {
                // Show "No data found" if centre has no data
                $worksheet->setCellValue('A6', 'No data found for this centre');
                $worksheet->mergeCells('A6:C6');
                $worksheet->getStyle('A6')->getFont()->setItalic(true);
                $row = 7;
            }

            // Add total row
            $worksheet->setCellValue('A' . $row, '');
            $worksheet->setCellValue('B' . $row, 'TOTAL');
            $worksheet->setCellValue('C' . $row, $totalAmount);
            $worksheet->getStyle('B' . $row . ':C' . $row)->getFont()->setBold(true);
            $worksheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('#,##0.00');

            // Auto-size columns
            $worksheet->getColumnDimension('A')->setAutoSize(true);
            $worksheet->getColumnDimension('B')->setAutoSize(true);
            $worksheet->getColumnDimension('C')->setAutoSize(true);

            // Add borders to data table
            $tableRange = 'A5:C' . $row;
            $worksheet->getStyle($tableRange)->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN);

            $sheetIndex++;
        }

        // Set first sheet as active
        if (!empty($allCentreData)) {
            $spreadsheet->setActiveSheetIndex(0);
        }

        // Generate file
        $writer = new Xlsx($spreadsheet);

        // Create temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'doctor_upselling_');
        $writer->save($tempFile);

        return $tempFile;
    }

    /**
     * Get doctor payment-based upselling data.
     */
    public function getDoctorPaymentBasedUpsellingData(string|int $centreId, string $period): array
    {
        // Define date ranges
        $periods = [
            'yesterday' => [
                'start_date' => Carbon::now()->subDay(1)->format('Y-m-d 00:00:00'),
                'end_date' => Carbon::now()->subDay(1)->format('Y-m-d 23:59:59'),
            ],
            'last7days' => [
                'start_date' => Carbon::now()->subDay(6)->format('Y-m-d 00:00:00'),
                'end_date' => Carbon::now()->subDay(1)->format('Y-m-d 23:59:59'),
            ],
            'week' => [
                'start_date' => Carbon::now()->startOfWeek()->format('Y-m-d 00:00:00'),
                'end_date' => Carbon::now()->subDay(1)->format('Y-m-d 23:59:59'),
            ],
            'thismonth' => [
                'start_date' => Carbon::now()->startOfMonth()->format('Y-m-d 00:00:00'),
                'end_date' => Carbon::now()->subDay(1)->format('Y-m-d 23:59:59'),
            ],
            'lastmonth' => [
                'start_date' => Carbon::now()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d 00:00:00'),
                'end_date' => Carbon::now()->subMonthNoOverflow()->endOfMonth()->format('Y-m-d 23:59:59'),
            ],
        ];

        $startDate = $periods[$period]['start_date'];
        $endDate = $periods[$period]['end_date'];

        // Get users with specific roles
        $roleHasUsers = User::whereHas('roles', function($query) {
            $query->where('name', 'Aesthetic Doctor')->orWhere('name','Lifestyle Consultant');
        })->pluck('id');

        $fdmUserIds = User::whereHas('roles', function ($q) {
                $q->where('name', 'FDM');
            })
            ->whereHas('user_has_locations', function ($q) use ($centreId) {
                if ($centreId !== 'all') {
                    $q->where('location_id', $centreId);
                }
            })
            ->pluck('id');

        // Get doctors for the location(s)
        if ($centreId === 'all') {
            // Get all locations user has access to
            $userLocations = ACL::getUserCentres();
            $doctorIds = DB::table('doctor_has_locations')
                ->whereIn('location_id', $userLocations)
                ->whereIn('user_id', $roleHasUsers)
                ->distinct()
                ->pluck('user_id');
        } else {
            $doctorIds = DB::table('doctor_has_locations')
                ->where('location_id', $centreId)
                ->whereIn('user_id', $roleHasUsers)
                ->distinct()
                ->pluck('user_id');
        }

        $allSellerIds = $doctorIds->merge($fdmUserIds)->unique();

        if ($allSellerIds->isEmpty()) {
            return ['empty' => true, 'message' => 'No doctors found for the selected location.', 'data' => []];
        }

        // Get all active users (doctors, consultants, FDMs) for the location
        $allActiveUsers = User::whereIn('id', $allSellerIds)
            ->where('active', 1)
            ->select('id', 'name')
            ->get()
            ->keyBy('id');

        // Get package services created in the date range
        $packageServicesQuery = PackageService::query()
            ->join('packages', 'package_services.package_id', '=', 'packages.id')
            ->join('appointments', 'packages.appointment_id', '=', 'appointments.id')
            ->whereIn('package_services.sold_by', $allSellerIds)
            ->whereBetween('package_services.created_at', [$startDate, $endDate])
            ->whereNotNull('sold_by');

        // Apply location filter
        if ($centreId !== 'all') {
            $packageServicesQuery->where('packages.location_id', $centreId);
        } else {
            $userLocations = ACL::getUserCentres();
            $packageServicesQuery->whereIn('packages.location_id', $userLocations);
        }

        $packageServices = $packageServicesQuery
            ->select(
                'package_services.id',
                'package_services.package_id',
                'package_services.sold_by',
                'package_services.tax_including_price',
                'package_services.created_at',
                'appointments.appointment_type_id',
                'appointments.doctor_id as appointment_doctor_id'
            )
            ->orderBy('package_services.created_at')
            ->get();

        // Initialize upselling amounts for each doctor
        $doctorUpsellingAmounts = [];
        foreach ($allSellerIds as $sellerId) {
            $doctorUpsellingAmounts[$sellerId] = 0;
        }

        // Group services by package_id for processing
        $servicesByPackage = $packageServices->groupBy('package_id');

        foreach ($servicesByPackage as $packageId => $services) {
            // Filter out any services with null created_at BEFORE grouping
            $servicesWithTimestamps = $services->filter(fn($service) => $service->created_at !== null && $service->sold_by !== null);

            if ($servicesWithTimestamps->isEmpty()) {
                continue;
            }

            // Sort all services by created_at, then by id to maintain consistent order
            $sortedServices = $servicesWithTimestamps->sortBy([
                ['created_at', 'asc'],
                ['id', 'asc']
            ])->values();

            foreach ($sortedServices as $index => $service) {
                // Skip self-consultation sales
                if ($service->appointment_type_id === AppointmentType::Consultancy->value && $service->appointment_doctor_id == $service->sold_by) {
                    continue;
                }

                $soldById = (int)$service->sold_by;

                if (!isset($doctorUpsellingAmounts[$soldById])) {
                    continue;
                }

                $serviceCreatedAt = Carbon::parse($service->created_at);
                $serviceAmount = $service->tax_including_price;

                // Find the next service (regardless of same timestamp or not)
                $nextService = null;
                if ($index < count($sortedServices) - 1) {
                    $nextService = $sortedServices[$index + 1];
                }

                // Build payment query - get payments after this service on same day
                $paymentsQuery = DB::table('package_advances')
                    ->where('package_id', $packageId)
                    ->where('cash_flow', 'in')
                    ->where('is_refund', 0)
                    ->where('is_adjustment', 0)
                    ->whereDate('created_at', $serviceCreatedAt->toDateString())
                    ->where('created_at', '>', $service->created_at)
                    ->whereBetween('created_at', [$startDate, $endDate]);

                // If there's a next service on the SAME DAY, limit payments to before that service
                if ($nextService) {
                    $nextServiceTime = Carbon::parse($nextService->created_at);
                    if ($nextServiceTime->toDateString() === $serviceCreatedAt->toDateString()) {
                        $paymentsQuery->where('created_at', '<=', $nextService->created_at);
                    }
                }

                $paymentsForThisService = $paymentsQuery->sum('cash_amount');

                // Calculate upselling amount
                if ($paymentsForThisService > 0) {
                    if ($paymentsForThisService >= $serviceAmount) {
                        // Payment covers full service amount
                        $upsellingAmount = $serviceAmount;
                    } else {
                        // Payment is less than service amount
                        $upsellingAmount = $paymentsForThisService;
                    }

                    $doctorUpsellingAmounts[$soldById] += $upsellingAmount;
                }
            }
        }

        // Prepare the final report data
        $reportData = $allActiveUsers->map(fn($user) => (object)[
                'doctor_id' => $user->id,
                'doctor_name' => $user->name,
                'total_upselling_amount' => $doctorUpsellingAmounts[$user->id] ?? 0,
            ])->sortByDesc('total_upselling_amount')->values();

        return ['empty' => false, 'data' => $reportData];
    }

    /**
     * Get doctor revenue report data.
     *
     * @param int|int[] $locationId
     */
    public function getDoctorRevenueReportData(int|array $locationId, string $startDate, string $endDate): array
    {
        $locationIds = is_array($locationId) ? array_map('intval', $locationId) : [(int) $locationId];

        // Get all doctors assigned to this location (including inactive)
        $doctorUserIds = User::whereHas('roles', function($query) {
            $query->whereIn('name', ['Aesthetic Doctor', 'Consultant', 'Lifestyle Consultant']);
        })->pluck('id');

        $doctorIds = DB::table('doctor_has_locations')
            ->whereIn('location_id', $locationIds)
            ->whereIn('user_id', $doctorUserIds)
            ->distinct()
            ->pluck('user_id');

        if ($doctorIds->isEmpty()) {
            return ['empty' => true, 'message' => 'No doctors found for the selected location.'];
        }

        // Get revenue from package_advances where cash_flow='in' and cash_amount > 0
        $revenueData = DB::table('package_advances')
            ->join('packages', 'package_advances.package_id', '=', 'packages.id')
            ->leftJoin('appointments', 'packages.appointment_id', '=', 'appointments.id')
            ->where('package_advances.cash_flow', 'in')
            ->where('package_advances.cash_amount', '!=', 0)
            ->where('package_advances.is_adjustment', 0)
            ->where('package_advances.is_tax', 0)
            ->where('package_advances.is_cancel', 0)
            ->whereIn('package_advances.location_id', $locationIds)
            ->whereBetween('package_advances.created_at', [$startDate, $endDate])
            ->whereNull('package_advances.deleted_at')
            ->groupBy('appointments.doctor_id')
            ->select(
                'appointments.doctor_id',
                DB::raw('SUM(package_advances.cash_amount) as total_revenue')
            )
            ->get()
            ->keyBy('doctor_id');

        // Get refunds (cash_flow='out' with is_refund=1) to subtract from revenue
        $refundData = DB::table('package_advances')
            ->join('packages', 'package_advances.package_id', '=', 'packages.id')
            ->leftJoin('appointments', 'packages.appointment_id', '=', 'appointments.id')
            ->where('package_advances.cash_flow', 'out')
            ->where('package_advances.is_refund', 1)
            ->where('package_advances.is_tax', 0)
            ->whereIn('package_advances.location_id', $locationIds)
            ->whereBetween('package_advances.created_at', [$startDate, $endDate])
            ->whereNull('package_advances.deleted_at')
            ->groupBy('appointments.doctor_id')
            ->select(
                'appointments.doctor_id',
                DB::raw('SUM(package_advances.cash_amount) as total_refund')
            )
            ->get()
            ->keyBy('doctor_id');

        // Get all doctor IDs from revenue data (including NULL for unassigned)
        $allDoctorIds = $revenueData->keys()->merge($refundData->keys())->unique();

        // Get doctor names
        $doctors = User::whereIn('id', $allDoctorIds->filter())
            ->select('id', 'name')
            ->get()
            ->keyBy('id');

        // Prepare report data (revenue - refunds)
        $reportData = collect();
        foreach ($allDoctorIds as $doctorId) {
            $revenue = $revenueData->get($doctorId);
            $refund = $refundData->get($doctorId);

            $totalRevenue = ($revenue->total_revenue ?? 0) - ($refund->total_refund ?? 0);

            if ($totalRevenue != 0) {
                if ($doctorId === null || $doctorId === '') {
                    // Unassigned payments (no doctor linked)
                    $reportData->push((object)[
                        'doctor_id' => 0,
                        'doctor_name' => 'Unassigned (No Doctor)',
                        'total_revenue' => $totalRevenue,
                    ]);
                } else {
                    $doctor = $doctors->get($doctorId);
                    if ($doctor) {
                        $reportData->push((object)[
                            'doctor_id' => $doctorId,
                            'doctor_name' => $doctor->name,
                            'total_revenue' => $totalRevenue,
                        ]);
                    } else {
                        // Doctor exists in revenue but not in users table (deleted?)
                        $reportData->push((object)[
                            'doctor_id' => $doctorId,
                            'doctor_name' => 'Unknown Doctor (ID: ' . $doctorId . ')',
                            'total_revenue' => $totalRevenue,
                        ]);
                    }
                }
            }
        }

        // Sort by revenue descending
        $reportData = $reportData->sortByDesc('total_revenue')->values();

        return [
            'empty' => false,
            'reportData' => $reportData,
            'doctor_ids' => $doctorIds->toArray(),
        ];
    }

    /**
     * Get doctor revenue detail data.
     */
    public function getDoctorRevenueDetailData(int $doctorId, array $filters): array
    {
        $locationIds = (array) ($filters['location_ids'] ?? [$filters['location_id'] ?? 0]);

        // Get doctor name
        if ($doctorId == 0) {
            $doctorName = 'Unassigned (No Doctor)';
        } else {
            $doctor = User::find($doctorId);
            $doctorName = $doctor->name ?? 'Unknown Doctor (ID: ' . $doctorId . ')';
        }

        // Build base query for payments
        $paymentsQuery = DB::table('package_advances')
            ->join('packages', 'package_advances.package_id', '=', 'packages.id')
            ->leftJoin('appointments', 'packages.appointment_id', '=', 'appointments.id')
            ->join('users as patients', 'packages.patient_id', '=', 'patients.id')
            ->leftJoin('payment_modes', 'package_advances.payment_mode_id', '=', 'payment_modes.id')
            ->where('package_advances.cash_flow', 'in')
            ->where('package_advances.cash_amount', '!=', 0)
            ->where('package_advances.is_adjustment', 0)
            ->where('package_advances.is_tax', 0)
            ->where('package_advances.is_cancel', 0)
            ->whereIn('package_advances.location_id', $locationIds)
            ->whereBetween('package_advances.created_at', [$filters['start_date'], $filters['end_date']])
            ->whereNull('package_advances.deleted_at');

        // Filter by doctor_id (0 means unassigned/NULL)
        if ($doctorId == 0) {
            $paymentsQuery->whereNull('appointments.doctor_id');
        } else {
            $paymentsQuery->where('appointments.doctor_id', $doctorId);
        }

        $payments = $paymentsQuery->select(
                'package_advances.id',
                'package_advances.cash_amount',
                'package_advances.created_at',
                'package_advances.cash_flow',
                'packages.id as package_id',
                'packages.name as package_name',
                'patients.id as patient_id',
                'patients.name as patient_name',
                'payment_modes.name as payment_mode'
            )
            ->orderBy('package_advances.created_at', 'desc')
            ->get();

        // Build base query for refunds
        $refundsQuery = DB::table('package_advances')
            ->join('packages', 'package_advances.package_id', '=', 'packages.id')
            ->leftJoin('appointments', 'packages.appointment_id', '=', 'appointments.id')
            ->join('users as patients', 'packages.patient_id', '=', 'patients.id')
            ->leftJoin('payment_modes', 'package_advances.payment_mode_id', '=', 'payment_modes.id')
            ->where('package_advances.cash_flow', 'out')
            ->where('package_advances.is_refund', 1)
            ->where('package_advances.is_tax', 0)
            ->whereIn('package_advances.location_id', $locationIds)
            ->whereBetween('package_advances.created_at', [$filters['start_date'], $filters['end_date']])
            ->whereNull('package_advances.deleted_at');

        // Filter by doctor_id (0 means unassigned/NULL)
        if ($doctorId == 0) {
            $refundsQuery->whereNull('appointments.doctor_id');
        } else {
            $refundsQuery->where('appointments.doctor_id', $doctorId);
        }

        $refunds = $refundsQuery->select(
                'package_advances.id',
                'package_advances.cash_amount',
                'package_advances.created_at',
                'package_advances.cash_flow',
                'packages.id as package_id',
                'packages.name as package_name',
                'patients.id as patient_id',
                'patients.name as patient_name',
                'payment_modes.name as payment_mode'
            )
            ->orderBy('package_advances.created_at', 'desc')
            ->get();

        // Merge payments and refunds
        $allTransactions = $payments->merge($refunds)->sortByDesc('created_at')->values();

        $totalRevenue = $payments->sum('cash_amount') - $refunds->sum('cash_amount');
        $totalPayments = $payments->count();
        $totalRefunds = $refunds->sum('cash_amount');
        $uniquePackages = $payments->pluck('package_id')->unique()->count();

        return [
            'allTransactions' => $allTransactions,
            'doctorName' => $doctorName,
            'totalRevenue' => $totalRevenue,
            'totalPayments' => $totalPayments,
            'totalRefunds' => $totalRefunds,
            'uniquePackages' => $uniquePackages,
        ];
    }

    // ──────────────────────────────────────────────────
    //  Upsell COLLECTED (cash basis for monthly incentives)
    //  Reads the precomputed `upsell_collected_monthly` rollup — NOT the heavy
    //  live allocation (that runs nightly via UpsellCollectedBuilder). Full
    //  model: memory `project_upsell_collected_incentive`.
    // ──────────────────────────────────────────────────

    /**
     * Per-doctor "collected" for a month, summed across the given centres.
     * `$month` is any date in the target month (normalised to the 1st).
     *
     * @param  int[]  $locationIds
     * @return array<int, array{doctor_id:int, doctor_name:string, collected_amount:float}>
     */
    public function getDoctorCollectedData(array $locationIds, string $month): array
    {
        $accountId = (int) (Auth::user()->account_id ?? 1);
        $monthDate = Carbon::parse($month)->startOfMonth()->toDateString();

        $query = DB::table('upsell_collected_monthly as u')
            ->join('users as us', 'u.doctor_id', '=', 'us.id')
            ->where('u.account_id', $accountId)
            ->where('u.month', $monthDate);

        if ($locationIds !== []) {
            $query->whereIn('u.location_id', $locationIds);
        }

        return $query
            ->groupBy('u.doctor_id', 'us.name')
            ->selectRaw('u.doctor_id, us.name as doctor_name, ROUND(SUM(u.collected_amount), 2) as collected_amount')
            ->get()
            ->map(fn ($r): array => [
                'doctor_id' => (int) $r->doctor_id,
                'doctor_name' => (string) $r->doctor_name,
                'collected_amount' => (float) $r->collected_amount,
            ])
            ->all();
    }

    /**
     * Per-line drill-down of one doctor's collected for a month — recomputed on
     * demand (one doctor × one month is cheap) via the shared builder engine.
     *
     * @param  int[]  $locationIds
     * @return array{rows: array<int, array<string, mixed>>, total: float}
     */
    public function getDoctorCollectedLedger(int $doctorId, array $locationIds, string $month): array
    {
        $accountId = (int) (Auth::user()->account_id ?? 1);

        $records = (new UpsellCollectedBuilder())->monthLineCredits($accountId, $month, $doctorId);

        if ($locationIds !== []) {
            $records = array_values(array_filter(
                $records,
                fn ($r): bool => $r['location_id'] !== null && in_array($r['location_id'], $locationIds, true),
            ));
        }

        if ($records === []) {
            return ['rows' => [], 'total' => 0.0];
        }

        $serviceNames = DB::table('services')
            ->whereIn('id', array_values(array_unique(array_column($records, 'service_id'))))
            ->pluck('name', 'id');
        $patientNames = DB::table('packages as p')
            ->join('users as u', 'p.patient_id', '=', 'u.id')
            ->whereIn('p.id', array_values(array_unique(array_column($records, 'package_id'))))
            ->pluck('u.name', 'p.id');

        $rows = array_map(fn ($r): array => [
            'package_id' => $r['package_id'],
            'line_id' => $r['line_id'],
            'service_id' => $r['service_id'],
            'service_name' => (string) ($serviceNames[$r['service_id']] ?? ''),
            'patient_name' => (string) ($patientNames[$r['package_id']] ?? ''),
            'sold_at' => $r['sale_date'],
            'line_value' => $r['value'],
            'collected_in_month' => $r['collected'],
            'is_consumed' => $r['is_consumed'],
            'consumed_at' => $r['consumed_at'],
        ], $records);

        usort($rows, fn ($a, $b): int => $b['collected_in_month'] <=> $a['collected_in_month']);

        return [
            'rows' => $rows,
            'total' => round(array_sum(array_column($records, 'collected')), 2),
        ];
    }
}
