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

        [$startDate, $endDate] = DashboardHelper::getDateRange($period);

        // Get FDM role and users
        $fdmRole =DB::table('roles')->where('name', 'FDM')->first();
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

        // Build query using appointments_daily_stats table
        $query = \App\Models\AppointmentsDailyStats::select('centre_id')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(CASE WHEN appointment_status_id IN (' . implode(',', array_map('intval', $arrivedStatusIds)) . ') THEN 1 ELSE 0 END) as arrived')
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->whereIn('centre_id', $validCenterIds)
            ->groupBy('centre_id');

        // Add walkin calculation only if FDM users exist
        if (!empty($fdmUsers)) {
            $fdmUserIds = implode(',', array_map('intval', $fdmUsers));
            $arrivedIds = implode(',', array_map('intval', $arrivedStatusIds));
            $query->selectRaw("SUM(CASE WHEN appointment_status_id IN ({$arrivedIds}) AND user_id IN ({$fdmUserIds}) THEN 1 ELSE 0 END) as walkin");
        } else {
            $query->selectRaw('0 as walkin');
        }

        $stats = $query->get()->keyBy('centre_id')->toArray();

        // Build result arrays
        foreach ($validCenterIds as $centreId) {
            $centreName = $locations[$centreId] ?? null;
            if ($centreName) {
                $labels[] = $centreName;
                $totalApts[] = isset($stats[$centreId]) ? (int) $stats[$centreId]['total'] : 0;
                $arrivedApts[] = isset($stats[$centreId]) ? (int) $stats[$centreId]['arrived'] : 0;
                $walkinApts[] = isset($stats[$centreId]) ? (int) $stats[$centreId]['walkin'] : 0;
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

        if ($centreId === 'All' || $centreId === 'all' || $centreId === '' || $centreId == '30' || $centreId == 30 || empty($centreId)) {
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
        $consultants = User::whereIn('id', $consultantIds)
            ->where('active', 1)
            ->orderBy('name')
            ->get();

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

            // Get package linked to this appointment
            $package = \App\Models\Packages::where('appointment_id', $appointment->id)->first();

            if (!$package) {
                continue;
            }

            // Get package bundle IDs
            $packagebundleIds = \App\Models\PackageBundles::where('package_id', $package->id)->pluck('id');

            // Check if there's at least one service added in package on same day or after invoice creation date
            $serviceAfterInvoice = \App\Models\PackageService::whereIn('package_bundle_id', $packagebundleIds)
                ->whereDate('created_at', '>=', $invoiceDate)
                ->exists();

            if (!$serviceAfterInvoice) {
                continue;
            }

            // Check if there's at least one payment on same day or after invoice creation date
            $firstPayment = \App\Models\PackageAdvances::where('package_id', $package->id)
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

            // Get all payments for conversion spend calculation (from invoice date, within report range)
            $packagesadvances = \App\Models\PackageAdvances::where('package_id', $package->id)
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
        $totalAppointmentsByDoctor = Appointments::whereIn('doctor_id', $consultantIds)
            ->whereIn('location_id', $locations)
            ->where('appointment_type_id', config('constants.appointment_type_consultancy'))
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->where(function($query) use ($arrivedStatusId, $convertedStatusId) {
                $query->where('base_appointment_status_id', $arrivedStatusId)
                    ->orWhere('base_appointment_status_id', $convertedStatusId);
            })
            ->selectRaw('doctor_id, COUNT(*) as total')
            ->groupBy('doctor_id')
            ->pluck('total', 'doctor_id')
            ->toArray();

        $sumConversionSpend = 0;

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
        if ($period !== 'all' && $period !== 'All') {
            [$startDate, $endDate] = DashboardHelper::getDateRange($period);
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

        return [
            'labels' => $labels,
            'data' => [
                'rating' => $ratings,
                'total' => $totals,
            ],
        ];
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
