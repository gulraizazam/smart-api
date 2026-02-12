<?php

namespace App\Services\Dashboard;

use App\Helpers\DashboardHelper;
use App\Models\Appointments;
use App\Models\Locations;
use App\Models\User;
use App\Models\DoctorHasLocations;
use App\Models\AppointmentStatuses;
use App\Models\Feedback;
use App\Models\Services;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

/**
 * Dashboard Chart Service
 * 
 * Handles all dashboard chart data operations including:
 * - Centre wise arrival charts
 * - CSR wise arrival charts
 * - Doctor wise conversion charts
 * - Doctor wise feedback charts
 */
class DashboardChartService
{
    /**
     * Get centre wise arrival chart data
     *
     * @param string $period
     * @param string|array $centreId
     * @return array
     */
    public function getCentreWiseArrival($period, $centreId = 'All')
    {
        $labels = [];
        $totalApts = [];
        $arrivedApts = [];
        $walkinApts = [];

        $centerIds = $centreId === 'All' ? DashboardHelper::getUserCentres() : [$centreId];

        // Fetch all locations in a single query
        $locations = Locations::whereIn('id', $centerIds)
            ->where(function ($q) {
                $q->whereNotNull('ntn')->orWhereNotNull('stn');
            })
            ->pluck('name', 'id')
            ->toArray();

        $validCenterIds = array_keys($locations);

        if (empty($validCenterIds)) {
            return [
                'labels' => $labels,
                'data' => [
                    'total' => $totalApts,
                    'arrived' => $arrivedApts,
                    'walkin' => $walkinApts,
                ],
            ];
        }

        // For Centre Wise Arrival: "thismonth" means 1st of month to yesterday (excluding today)
        if ($period === 'thismonth' || $period === 'month') {
            $startDate = Carbon::now()->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::now()->subDay(1)->format('Y-m-d');
        } else {
            [$startDate, $endDate] = DashboardHelper::getDateRange($period);
        }

        // Get FDM role and users
        $fdmRole = DB::table('roles')->where('name', 'FDM')->first();
        $fdmUsers = $fdmRole ? \App\Models\RoleHasUsers::where('role_id', $fdmRole->id)->pluck('user_id')->toArray() : [];

        // Get arrived and converted status IDs
        $accountId = Auth::User()->account_id;
        $statusIds = AppointmentStatuses::where('account_id', $accountId)
            ->where(function ($q) {
                $q->where('is_arrived', 1)->orWhere('is_converted', 1);
            })
            ->pluck('id')
            ->toArray();
        
        $arrivedStatusIds = !empty($statusIds) ? $statusIds : [2, 16];

        // Fetch all records for set-based counting logic
        $allRecords = \App\Models\AppointmentsDailyStats::select('id', 'centre_id', 'appointment_id', 'appointment_status_id', 'user_id')
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->whereIn('centre_id', $validCenterIds)
            ->orderBy('centre_id')
            ->orderBy('appointment_id')
            ->orderBy('id')
            ->get();

        // Group records by centre_id and appointment_id
        $groupedByCentre = [];
        foreach ($allRecords as $record) {
            $cId = $record->centre_id;
            $appointmentId = $record->appointment_id;
            
            if (!isset($groupedByCentre[$cId])) {
                $groupedByCentre[$cId] = [];
            }
            if (!isset($groupedByCentre[$cId][$appointmentId])) {
                $groupedByCentre[$cId][$appointmentId] = [];
            }
            
            $groupedByCentre[$cId][$appointmentId][] = $record;
        }

        // Calculate set-based counts for each centre
        // Logic: Make sets of 2 records per appointment_id
        // - Each set counts as 1 in total
        // - If a set has at least one arrived/converted status, count 1 as arrived
        $stats = [];
        foreach ($groupedByCentre as $cId => $appointments) {
            $centreTotal = 0;
            $centreArrived = 0;
            $centreWalkin = 0;

            foreach ($appointments as $appointmentId => $records) {
                $recordCount = count($records);
                $setCount = ceil($recordCount / 2);

                for ($i = 0; $i < $setCount; $i++) {
                    $setStart = $i * 2;
                    $setRecords = array_slice($records, $setStart, 2);

                    // Check if this set has at least one arrived/converted status
                    $hasArrived = false;
                    $isWalkin = false;

                    foreach ($setRecords as $record) {
                        if (in_array($record->appointment_status_id, $arrivedStatusIds)) {
                            $hasArrived = true;
                            // Check if this is a walk-in (created by FDM user)
                            if (!empty($fdmUsers) && in_array($record->user_id, $fdmUsers)) {
                                $isWalkin = true;
                            }
                            break;
                        }
                    }

                    // Count every set in total
                    $centreTotal++;
                    
                    if ($hasArrived) {
                        // Arrived set: count 1 arrived
                        $centreArrived++;
                        if ($isWalkin) {
                            $centreWalkin++;
                        }
                    }
                }
            }

            $stats[$cId] = [
                'total' => $centreTotal,
                'arrived' => $centreArrived,
                'walkin' => $centreWalkin,
            ];
        }

        // Build result arrays
        foreach ($validCenterIds as $cId) {
            $centreName = $locations[$cId] ?? null;
            if ($centreName) {
                $labels[] = $centreName;
                $totalApts[] = isset($stats[$cId]) ? (int) $stats[$cId]['total'] : 0;
                $arrivedApts[] = isset($stats[$cId]) ? (int) $stats[$cId]['arrived'] : 0;
                $walkinApts[] = isset($stats[$cId]) ? (int) $stats[$cId]['walkin'] : 0;
            }
        }

        return [
            'labels' => $labels,
            'data' => [
                'total' => $totalApts,
                'arrived' => $arrivedApts,
                'walkin' => $walkinApts,
            ],
        ];
    }

    /**
     * Get doctor wise conversion chart data
     *
     * @param string $period
     * @param string|array $centreId
     * @param int|null $docId
     * @return array
     */
    public function getDoctorWiseConversion($period, $centreId = 'All', $docId = null)
    {
        $totalApts = [];
        $convertedApts = [];
        $labels = [];
        $appointmentsInfo = [];

        // Track if All Centres is selected (skip active filter in this case)
        $isAllCentres = ($centreId === 'All' || $centreId === 'all' || $centreId === '' || $centreId == '30' || $centreId == 30 || empty($centreId));
        
        if ($isAllCentres) {
            $locations = DashboardHelper::getUserCentres();
        } else {
            $locations = is_array($centreId) ? $centreId : [$centreId];
        }

        // Get consultants in single optimized query
        $consultantQuery = DoctorHasLocations::where('is_allocated', 1)
            ->whereIn('location_id', $locations);
        
        if ($docId && $docId != 0 && $docId != "all-docs") {
            $consultantQuery->where('user_id', $docId);
        }

        $consultantIds = $consultantQuery->distinct()->pluck('user_id')->toArray();

        if (empty($consultantIds)) {
            return $this->emptyConversionResponse();
        }

        // Fetch all consultants in one query
        // When All Centres: skip active filter. When specific centre: show only active
        $consultantsQuery = User::whereIn('id', $consultantIds);
        if (!$isAllCentres) {
            $consultantsQuery->where('active', 1);
        }
        $consultants = $consultantsQuery->orderBy('name')->get();

        if ($consultants->isEmpty()) {
            return $this->emptyConversionResponse();
        }

        [$startDate, $endDate] = DashboardHelper::getDateRange($period);

        // Get arrived and converted status IDs (same as conversion report)
        $arrivedStatusId = DashboardHelper::getArrivedStatusId();
        $convertedStatusId = DashboardHelper::getConvertedStatusId();

        // Use the same logic as Finanaces::LoadConversionReport
        // Get converted appointments with payments
        $convertedAppointments = Appointments::with('location:id,name')
            ->leftjoin('package_advances', 'package_advances.appointment_id', '=', 'appointments.id')
            ->where('appointments.appointment_type_id', 1)
            ->where(function($query) use ($arrivedStatusId, $convertedStatusId) {
                $query->where('appointments.base_appointment_status_id', $arrivedStatusId);
                if ($convertedStatusId) {
                    $query->orWhere('appointments.base_appointment_status_id', $convertedStatusId);
                }
            })
            ->whereIn('appointments.doctor_id', $consultantIds)
            ->whereIn('appointments.location_id', $locations)
            ->where('package_advances.cash_amount', '>', 0)
            ->select('appointments.*')
            ->where('package_advances.created_at', '>=', $startDate . ' 00:00:00')
            ->where('package_advances.created_at', '<=', $endDate . ' 23:59:59')
            ->get();

        // Process each appointment with full conversion report logic
        $validConversions = [];
        $conversionSpendByDoctor = [];
        
        foreach ($convertedAppointments as $appointment) {
            // Skip if already processed
            if (isset($validConversions[$appointment->id])) {
                continue;
            }
            
            // Get invoice creation date for this appointment
            $invoice = \App\Models\Invoices::where('appointment_id', $appointment->id)
                ->whereNull('deleted_at')
                ->orderBy('created_at', 'asc')
                ->first();

            if (!$invoice) {
                continue;
            }

            $invoiceDate = \Carbon\Carbon::parse($invoice->created_at)->format('Y-m-d');

            // Get all packages linked to this appointment
            $packages = \App\Models\Packages::where('appointment_id', $appointment->id)->get();

            if ($packages->isEmpty()) {
                continue;
            }

            // Collect all package IDs
            $packageIds = $packages->pluck('id')->toArray();

            // Get package bundle IDs for all packages
            $packagebundleIds = \App\Models\PackageBundles::whereIn('package_id', $packageIds)->pluck('id');

            // Check if there's at least one service added in package on same day or after invoice creation date
            $serviceAfterInvoice = \App\Models\PackageService::whereIn('package_bundle_id', $packagebundleIds)
                ->whereDate('created_at', '>=', $invoiceDate)
                ->exists();

            if (!$serviceAfterInvoice) {
                continue;
            }

            // Check if there's at least one payment on same day or after invoice creation date (across all packages)
            $firstPayment = \App\Models\PackageAdvances::whereIn('package_id', $packageIds)
                ->where('cash_flow', 'in')
                ->where('cash_amount', '>', 0)
                ->whereNull('deleted_at')
                ->whereDate('created_at', '>=', $invoiceDate)
                ->orderBy('created_at', 'asc')
                ->first();

            if (!$firstPayment) {
                continue;
            }

            // Check if the FIRST payment date falls within the report date range
            $firstPaymentDate = \Carbon\Carbon::parse($firstPayment->created_at)->format('Y-m-d');
            if ($firstPaymentDate < $startDate || $firstPaymentDate > $endDate) {
                continue;
            }

            // Get all payments for conversion spend calculation (from invoice date, within report range) across all packages
            $packagesadvances = \App\Models\PackageAdvances::whereIn('package_id', $packageIds)
                ->where('cash_amount', '>', 0)
                ->whereNull('deleted_at')
                ->whereDate('created_at', '>=', $invoiceDate)
                ->where('created_at', '>=', $startDate . ' 00:00:00')
                ->where('created_at', '<=', $endDate . ' 23:59:59')
                ->get();

            if (count($packagesadvances) > 0) {
                $revenue_in = 0;
                $out = 0;

                foreach ($packagesadvances as $packagesadvance) {
                    $package_advance = \App\Helpers\GeneralFunctions::genericfunctionforstaffwiserevenue($packagesadvance);
                    if ($package_advance) {
                        $revenue_in += $package_advance['revenue'] ? $package_advance['revenue'] : 0;
                        $out += $package_advance['refund_out'] ? $package_advance['refund_out'] : 0;
                    }
                }
                $actual = $revenue_in - $out;
                
                // Mark as valid conversion
                $validConversions[$appointment->id] = [
                    'doctor_id' => $appointment->doctor_id,
                    'conversion_spend' => $actual,
                ];
                
                // Accumulate by doctor
                if (!isset($conversionSpendByDoctor[$appointment->doctor_id])) {
                    $conversionSpendByDoctor[$appointment->doctor_id] = ['count' => 0, 'spend' => 0];
                }
                $conversionSpendByDoctor[$appointment->doctor_id]['count']++;
                $conversionSpendByDoctor[$appointment->doctor_id]['spend'] += $actual;
            }
        }

        // Get total appointments (arrived + converted) for each doctor
        // Match conversion report logic: when no specific doctor selected, don't filter by doctor_id
        $totalAppointmentsQuery = Appointments::whereIn('location_id', $locations)
            ->where('appointment_type_id', config('constants.appointment_type_consultancy'))
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->where(function($query) use ($arrivedStatusId, $convertedStatusId) {
                $query->where('base_appointment_status_id', $arrivedStatusId)
                    ->orWhere('base_appointment_status_id', $convertedStatusId);
            });

        // Only filter by doctor_id if a specific doctor is selected
        if ($docId && $docId != 0 && $docId != "all-docs") {
            $totalAppointmentsQuery->whereIn('doctor_id', $consultantIds);
        }

        // Get total count matching conversion report logic (simple count, no grouping)
        $grandTotalAppointments = (clone $totalAppointmentsQuery)->count();

        $totalAppointmentsByDoctor = $totalAppointmentsQuery
            ->selectRaw('doctor_id, COUNT(*) as total')
            ->groupBy('doctor_id')
            ->pluck('total', 'doctor_id')
            ->toArray();

        $sumConversionSpend = 0;

        // When no specific doctor selected, we need to include ALL doctors with appointments
        // (including unallocated ones) to match conversion report logic
        if (!$docId || $docId == 0 || $docId == "all-docs") {
            // Get all doctors who have appointments (including unallocated)
            $allDoctorIds = array_keys($totalAppointmentsByDoctor);
            // Merge with allocated consultants
            $allDoctorIds = array_unique(array_merge($allDoctorIds, $consultantIds));
            // Fetch all these doctors
            // When All Centres: skip active filter. When specific centre: show only active
            $allDoctorsQuery = User::whereIn('id', $allDoctorIds);
            if (!$isAllCentres) {
                $allDoctorsQuery->where('active', 1);
            }
            $allDoctors = $allDoctorsQuery->orderBy('name')->get();
            
            foreach ($allDoctors as $doctor) {
                $labels[] = $doctor->name;
                
                $totalAppointments = $totalAppointmentsByDoctor[$doctor->id] ?? 0;
                $convertedCount = $conversionSpendByDoctor[$doctor->id]['count'] ?? 0;
                $conversionSpendSum = $conversionSpendByDoctor[$doctor->id]['spend'] ?? 0;

                $totalApts[] = $totalAppointments;
                $convertedApts[] = $convertedCount;
                $sumConversionSpend += $conversionSpendSum;

                $appointmentsInfo[] = [
                    'doctor_id' => $doctor->id,
                    'total' => $totalAppointments,
                    'converted' => $convertedCount,
                    'conversion_spend' => $conversionSpendSum,
                ];
            }
            
            // Add appointments with NULL doctor_id to the total if any exist
            $summedByDoctor = array_sum($totalAppointmentsByDoctor);
            $nullDoctorCount = $grandTotalAppointments - $summedByDoctor;
            if ($nullDoctorCount > 0) {
                // Add these to the first doctor's count or create an "Unassigned" entry
                if (!empty($totalApts)) {
                    $totalApts[0] += $nullDoctorCount;
                    $appointmentsInfo[0]['total'] += $nullDoctorCount;
                }
            }
        } else {
            foreach ($consultants as $consultant) {
                $labels[] = $consultant->name;
                
                $totalAppointments = $totalAppointmentsByDoctor[$consultant->id] ?? 0;
                $convertedCount = $conversionSpendByDoctor[$consultant->id]['count'] ?? 0;
                $conversionSpendSum = $conversionSpendByDoctor[$consultant->id]['spend'] ?? 0;

                $totalApts[] = $totalAppointments;
                $convertedApts[] = $convertedCount;
                $sumConversionSpend += $conversionSpendSum;

                $appointmentsInfo[] = [
                    'doctor_id' => $consultant->id,
                    'total' => $totalAppointments,
                    'converted' => $convertedCount,
                    'conversion_spend' => $conversionSpendSum,
                ];
            }
        }

        return [
            'labels' => $labels,
            'data' => [
                'total_appointments' => $totalApts,
                'converted_appointments' => $convertedApts,
            ],
            'appointments_info' => $appointmentsInfo,
            'sum_val' => $sumConversionSpend,
        ];
    }

    /**
     * Get doctor wise feedback chart data
     *
     * @param string $period
     * @param string|array $centreId
     * @param int|null $docId
     * @return array
     */
    public function getDoctorWiseFeedback($period, $centreId = 'All', $docId = null)
    {
        if ($centreId === 'All' || $centreId === 'all' || $centreId === '' || $centreId == '30' || $centreId == 30 || empty($centreId)) {
            $locationIds = DashboardHelper::getUserCentres();
        } else {
            $locationIds = is_array($centreId) ? $centreId : [$centreId];
        }

        // Get doctors assigned to those locations
        $doctorQuery = DoctorHasLocations::where('is_allocated', 1)
            ->whereIn('location_id', $locationIds);

        if ($docId && $docId !== '0' && $docId !== 'all-docs') {
            $doctorQuery->where('user_id', $docId);
        }

        $doctorIds = $doctorQuery->distinct()->pluck('user_id')->toArray();

        if (empty($doctorIds)) {
            return [
                'labels' => [],
                'data' => [
                    'rating' => [],
                    'total' => [],
                ],
            ];
        }

        // Build feedback query - if period is 'all', don't apply date filter (lifetime data)
        $feedbackQuery = Feedback::whereIn('doctor_id', $doctorIds)
            ->select('doctor_id', DB::raw('AVG(rating) as avg_rating'), DB::raw('COUNT(*) as total_feedback'))
            ->groupBy('doctor_id');

        // Only apply date filter if period is not 'all' (lifetime)
        // For this chart, last7days, week, and thismonth exclude current date
        if ($period !== 'all' && $period !== 'All') {
            [$startDate, $endDate] = $this->getFeedbackDateRange($period);
            $feedbackQuery->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        }

        $feedbackData = $feedbackQuery->get()->keyBy('doctor_id');

        // Get doctor names
        $doctors = User::whereIn('id', $doctorIds)
            ->where('active', 1)
            ->pluck('name', 'id');

        // Build array with ratings for sorting
        $doctorRatings = [];
        foreach ($doctors as $doctorId => $doctorName) {
            $feedback = $feedbackData->get($doctorId);
            $doctorRatings[] = [
                'name' => $doctorName,
                'rating' => $feedback ? round($feedback->avg_rating, 2) : 0,
                'total' => $feedback ? (int)$feedback->total_feedback : 0,
            ];
        }

        // Sort by rating high to low
        usort($doctorRatings, function($a, $b) {
            return $b['rating'] <=> $a['rating'];
        });

        // Extract sorted data
        $labels = array_column($doctorRatings, 'name');
        $ratings = array_column($doctorRatings, 'rating');
        $totals = array_column($doctorRatings, 'total');

        // Calculate feedback statistics: total treatments vs feedbacks recorded
        $treatmentStatusId = 2; // Treatment appointment status
        $treatmentTypeId = 2; // Treatment appointment type
        
        // Get total treatments scheduled in the date range
        $treatmentsQuery = Appointments::whereIn('location_id', $locationIds)
            ->where('appointment_type_id', $treatmentTypeId)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->where('appointment_status_id', $treatmentStatusId);
          
        
        // Get feedbacks count for treatments in the date range
        $feedbacksCountQuery = Feedback::join('appointments', 'feedback.appointment_id', '=', 'appointments.id')
            ->whereIn('appointments.location_id', $locationIds)
            ->where('appointments.appointment_type_id', $treatmentTypeId)
            ->where('appointments.appointment_status_id', $treatmentStatusId);
        
        // Apply date filter based on period (using custom date range that excludes current date for specific filters)
        if ($period !== 'all' && $period !== 'All') {
            [$startDate, $endDate] = $this->getFeedbackDateRange($period);
            $treatmentsQuery->whereBetween('scheduled_date', [$startDate, $endDate]);
            $feedbacksCountQuery->whereBetween('appointments.scheduled_date', [$startDate, $endDate]);
        }
        
        $totalTreatments = $treatmentsQuery->count();
        $totalFeedbacks = $feedbacksCountQuery->count();
        $feedbackPercentage = $totalTreatments > 0 ? round(($totalFeedbacks / $totalTreatments) * 100, 2) : 0;

        return [
            'labels' => $labels,
            'data' => [
                'rating' => $ratings,
                'total' => $totals,
            ],
            'feedback_stats' => [
                'total_treatments' => $totalTreatments,
                'total_feedbacks' => $totalFeedbacks,
                'percentage' => $feedbackPercentage,
            ],
        ];
    }

    /**
     * Get custom date range for feedback chart that excludes current date
     * for last7days, week, and thismonth filters
     *
     * @param string $period
     * @return array [start_date, end_date] in Y-m-d format
     */
    private function getFeedbackDateRange($period)
    {
        switch ($period) {
            case 'last7days':
                // 7 days excluding today: yesterday minus 6 days to yesterday
                $start_date = Carbon::now()->subDays(7)->format('Y-m-d');
                $end_date = Carbon::now()->subDay()->format('Y-m-d');
                break;
            case 'week':
                // This week excluding today
                $start_date = Carbon::now()->startOfWeek()->format('Y-m-d');
                $end_date = Carbon::now()->subDay()->format('Y-m-d');
                break;
            case 'month':
            case 'thismonth':
                // This month excluding today
                $start_date = Carbon::now()->startOfMonth()->format('Y-m-d');
                $end_date = Carbon::now()->subDay()->format('Y-m-d');
                break;
            default:
                // For other periods (today, yesterday, lastmonth, etc.), use standard date range
                return DashboardHelper::getDateRange($period);
        }

        return [$start_date, $end_date];
    }

    /**
     * Get CSR wise arrival chart data
     *
     * @param string $period
     * @param string|array $centreId
     * @return array
     */
    public function getCSRWiseArrival($period, $centreId = 'All')
    {
        $totalApts = [];
        $arrivedApts = [];
        $labels = [];

        if ($centreId === 'All' || $centreId === '' || $centreId === '30') {
            $locationIds = DashboardHelper::getUserCentres();
        } else {
            $locationIds = is_array($centreId) ? $centreId : [$centreId];
        }

        [$startDate, $endDate] = DashboardHelper::getDateRange($period);

        // Get arrived status IDs
        $arrivedStatusIds = DashboardHelper::getArrivedAndConvertedStatusIds();

        // Get CSR users who created appointments
        $csrData = Appointments::whereIn('location_id', $locationIds)
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->whereNotNull('created_by')
            ->select(
                'created_by',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN appointment_status_id IN (' . implode(',', $arrivedStatusIds) . ') THEN 1 ELSE 0 END) as arrived')
            )
            ->groupBy('created_by')
            ->get();

        if ($csrData->isEmpty()) {
            return [
                'labels' => $labels,
                'data' => [
                    'total' => $totalApts,
                    'arrived' => $arrivedApts,
                ],
            ];
        }

        // Get user names
        $userIds = $csrData->pluck('created_by')->toArray();
        $users = User::whereIn('id', $userIds)->pluck('name', 'id');

        foreach ($csrData as $data) {
            $userName = $users->get($data->created_by, 'Unknown');
            $labels[] = $userName;
            $totalApts[] = (int)$data->total;
            $arrivedApts[] = (int)$data->arrived;
        }

        return [
            'labels' => $labels,
            'data' => [
                'total' => $totalApts,
                'arrived' => $arrivedApts,
            ],
        ];
    }

    /**
     * Get centre doctors list
     *
     * @param string|array $centreId
     * @return array
     */
    public function getCentreDoctors($centreId)
    {
        if ($centreId === 'All' || $centreId === '' || $centreId === '30') {
            $locationIds = DashboardHelper::getUserCentres();
        } else {
            $locationIds = is_array($centreId) ? $centreId : [$centreId];
        }

        $consultants = User::select('users.id', 'users.name')
            ->join('doctor_has_locations', 'users.id', '=', 'doctor_has_locations.user_id')
            ->whereIn('doctor_has_locations.location_id', $locationIds)
            ->where('doctor_has_locations.is_allocated', 1)
            ->where('users.active', 1)
            ->distinct()
            ->orderBy('users.name')
            ->get();

        return $consultants;
    }

    /**
     * Return empty conversion response structure
     *
     * @return array
     */
    private function emptyConversionResponse()
    {
        return [
            'labels' => [],
            'data' => [
                'total_appointments' => [],
                'converted_appointments' => [],
            ],
            'appointments_info' => [],
            'sum_val' => 0,
        ];
    }

    /**
     * Get appointment by status chart data
     *
     * @param string $period
     * @param int $appointmentTypeId
     * @param bool $performance
     * @return array
     */
    public function getAppointmentByStatus($period, $appointmentTypeId, $performance = false)
    {
        $colors = ['#3375de', '#c8cf19', '#cf7a19', '#cf1931', '#19cf43', '#a119cf'];
        $chartData = [['Task', 'Hours per Day']];

        $arrivedStatusId = DashboardHelper::getArrivedStatusId();
        $convertedStatusId = DashboardHelper::getConvertedStatusId();

        // Fetch appointment statuses keyed by ID
        $appointmentStatuses = AppointmentStatuses::where([
            ['account_id', '=', Auth::User()->account_id],
            ['active', '=', '1'],
            ['parent_id', '=', '0'],
        ])->where('id', '!=', $convertedStatusId)->get()->keyBy('id');

        [$startDate, $endDate] = DashboardHelper::getDateRange($period);
        $locationIds = DashboardHelper::getUserCentres();

        // Build query
        $query = Appointments::where('scheduled_date', '>=', $startDate)
            ->where('scheduled_date', '<=', $endDate)
            ->where('appointment_type_id', $appointmentTypeId)
            ->whereIn('location_id', $locationIds);

        if ($performance) {
            $query->where('created_by', Auth::User()->id);
        }

        // Get records grouped by status
        $records = $query->select('base_appointment_status_id as appointment_status_id', DB::raw('COUNT(id) AS total'))
            ->groupBy('base_appointment_status_id')
            ->get()
            ->keyBy('appointment_status_id');

        // Get converted count to add to arrived
        $convertedCount = $records->get($convertedStatusId)->total ?? 0;

        foreach ($appointmentStatuses as $statusId => $status) {
            $record = $records->get($statusId);
            if ($record) {
                $statusTotal = $record->total;
                if ($statusId == $arrivedStatusId) {
                    $statusTotal += $convertedCount;
                }
                $chartData[] = [$status->name, $statusTotal];
            }
        }

        return [
            'chartData' => $chartData,
            'colors' => $colors,
        ];
    }

    /**
     * Get appointment by type chart data
     *
     * @param string $period
     * @param bool $performance
     * @return array
     */
    public function getAppointmentByType($period, $performance = false)
    {
        $chartData = [['Task', 'Hours per Day']];
        $colors = [];
        $total = 0;

        $appointmentTypes = \App\Models\AppointmentTypes::where([
            ['account_id', '=', Auth::User()->account_id],
            ['active', '=', '1'],
        ])->get();

        if ($appointmentTypes->isEmpty()) {
            return ['chartData' => $chartData, 'colors' => $colors, 'total' => 0];
        }

        [$startDate, $endDate] = DashboardHelper::getDateRange($period);
        $locationIds = DashboardHelper::getUserCentres();

        $query = Appointments::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->whereIn('location_id', $locationIds);

        if ($performance) {
            $query->where('created_by', Auth::User()->id);
        }

        $records = $query->select('appointment_type_id', DB::raw('COUNT(id) AS total'))
            ->groupBy('appointment_type_id')
            ->get()
            ->keyBy('appointment_type_id');

        foreach ($appointmentTypes as $type) {
            $record = $records->get($type->id);
            if ($record) {
                $chartData[] = [$type->name, (int)$record->total];
                $colors[] = $type->color ?? '#3375de';
                $total += $record->total;
            }
        }

        return ['chartData' => $chartData, 'colors' => $colors, 'total' => $total];
    }

    /**
     * Get call wise arrival chart data using AppointmentsDailyStats
     *
     * @param string $period
     * @param int|null $userId
     * @return array
     */
    public function getCallWiseArrival($period, $userId = null)
    {
        $labels = [];
        $totalApts = [];
        $arrivedApts = [];

        [$startDate, $endDate] = DashboardHelper::getDateRange($period ?: 'today');

        // Get FDM users to exclude
        $fdmRole = \Spatie\Permission\Models\Role::where('name', 'FDM')->first();
        $fdmUsers = $fdmRole ? \App\Models\RoleHasUsers::where('role_id', $fdmRole->id)->pluck('user_id')->toArray() : [];

        $query = \App\Models\AppointmentsDailyStats::select(
                'user_id',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN appointment_status_id = 2 THEN 1 ELSE 0 END) as arrived')
            )
            ->whereBetween('scheduled_date', [$startDate, $endDate]);

        if ($userId && $userId !== 'All') {
            $query->where('user_id', $userId);
        } else {
            $query->whereNotIn('user_id', $fdmUsers);
        }

        $stats = $query->groupBy('user_id')->get();

        if ($stats->isEmpty()) {
            return ['labels' => $labels, 'total' => $totalApts, 'arrived' => $arrivedApts];
        }

        // Get user names in batch
        $userIds = $stats->pluck('user_id')->toArray();
        $users = \App\Models\User::whereIn('id', $userIds)->where('active', 1)->pluck('name', 'id');

        foreach ($stats as $stat) {
            $userName = $users->get($stat->user_id);
            if ($userName) {
                $labels[] = $userName;
                $totalApts[] = (int)$stat->total;
                $arrivedApts[] = (int)$stat->arrived;
            }
        }

        return ['labels' => $labels, 'total' => $totalApts, 'arrived' => $arrivedApts];
    }

    /**
     * Get CSR wise arrival chart data using AppointmentsDailyStats
     *
     * @param string $period
     * @param mixed $userId
     * @return array
     */
    public function getCSRWiseArrivalStats($period, $userId = 'All')
    {
        $labels = [];
        $totalApts = [];
        $arrivedApts = [];

        [$startDate, $endDate] = DashboardHelper::getDateRange($period ?: 'thismonth');

        // Get CSR users
        $csrUserIds = \App\Models\RoleHasUsers::whereIn('role_id', [2, 3, 24])->pluck('user_id')->toArray();
        $csrUsers = \App\Models\User::whereIn('id', $csrUserIds)->where('active', 1)->pluck('id')->toArray();

        $userIds = ($userId === 'All') ? $csrUsers : [$userId];
        $groupBy = ($userId === 'All') ? 'user_id' : 'cron_current_date';

        $stats = \App\Models\AppointmentsDailyStats::select(
                $groupBy,
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN appointment_status_id = 2 THEN 1 ELSE 0 END) as arrived')
            )
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->whereIn('user_id', $userIds)
            ->groupBy($groupBy)
            ->orderBy('user_id', 'ASC')
            ->get();

        if ($stats->isEmpty()) {
            return ['labels' => $labels, 'total' => $totalApts, 'arrived' => $arrivedApts];
        }

        if ($groupBy === 'user_id') {
            $allUserIds = $stats->pluck('user_id')->toArray();
            $users = \App\Models\User::whereIn('id', $allUserIds)->where('active', 1)->pluck('name', 'id');

            foreach ($stats as $stat) {
                $userName = $users->get($stat->user_id);
                if ($userName) {
                    $labels[] = $userName;
                    $totalApts[] = (int)$stat->total;
                    $arrivedApts[] = (int)$stat->arrived;
                }
            }
        } else {
            foreach ($stats as $stat) {
                $labels[] = $stat->cron_current_date;
                $totalApts[] = (int)$stat->total;
                $arrivedApts[] = (int)$stat->arrived;
            }
        }

        return ['labels' => $labels, 'total' => $totalApts, 'arrived' => $arrivedApts];
    }
}
