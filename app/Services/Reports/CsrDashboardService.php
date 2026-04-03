<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Helpers\ACL;
use App\Models\Appointments;
use App\Models\Locations;
use App\Models\RoleHasUsers;
use App\Models\Settings;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CsrDashboardService
{
    private const CSR_ROLE_IDS = [2];
    private const DASHBOARD_DAYS = 5;
    private const DEFAULT_CSR_TARGET = 10;

    /**
     * Generate the complete CSR dashboard data.
     *
     * Returns location stats, CSR stats, date range, and totals
     * for the next 5 days of consultation appointments.
     */
    public function generate(): array
    {
        $today = Carbon::today();
        $endDate = Carbon::today()->addDays(self::DASHBOARD_DAYS - 1);
        $accountId = Auth::user()->account_id;
        $userCentres = ACL::getUserCentres();
        $consultationTypeId = config('constants.appointment_type_consultancy', 1);

        $dateRange = $this->buildDateRange($today);
        $locations = Locations::getActiveSorted($userCentres);

        $appointments = $this->getAppointments($userCentres, $accountId, $consultationTypeId, $today, $endDate);
        $locationStats = $this->buildLocationStats($locations, $dateRange, $appointments);
        $totalByDate = $this->calculateTotalsByDate($dateRange, $locationStats);

        $csrStats = $this->buildCsrStats(
            $userCentres, $accountId, $consultationTypeId, $today, $endDate, $dateRange
        );

        $csrTarget = $this->getCsrTarget($accountId);

        return [
            'locationStats' => $locationStats,
            'dateRange' => $dateRange,
            'totalAppointments' => $appointments->count(),
            'totalByDate' => $totalByDate,
            'csrStats' => $csrStats,
            'today' => $today,
            'csrTarget' => $csrTarget,
        ];
    }

    /**
     * Build the 5-day date range array.
     */
    private function buildDateRange(Carbon $today): array
    {
        $dateRange = [];

        for ($i = 0; $i < self::DASHBOARD_DAYS; $i++) {
            $date = $today->copy()->addDays($i);
            $dateRange[$date->format('Y-m-d')] = [
                'date' => $date->format('Y-m-d'),
                'display' => $date->format('D, M d'),
                'is_today' => $i === 0,
            ];
        }

        return $dateRange;
    }

    /**
     * Get consultation appointments for the date range.
     */
    private function getAppointments(
        array $userCentres,
        int $accountId,
        int $consultationTypeId,
        Carbon $today,
        Carbon $endDate,
    ): \Illuminate\Support\Collection {
        return Appointments::with(['patient:id,name', 'doctor:id,name', 'location:id,name', 'appointment_status_base', 'user:id,name'])
            ->whereIn('location_id', $userCentres)
            ->where('account_id', $accountId)
            ->where('appointment_type_id', $consultationTypeId)
            ->whereDate('scheduled_date', '>=', $today)
            ->whereDate('scheduled_date', '<=', $endDate)
            ->orderBy('location_id')
            ->orderBy('scheduled_date')
            ->orderBy('scheduled_time')
            ->get();
    }

    /**
     * Build location-wise consultation stats.
     */
    private function buildLocationStats(
        mixed $locations,
        array $dateRange,
        \Illuminate\Support\Collection $appointments,
    ): array {
        $locationStats = [];

        foreach ($locations as $locationId => $locationName) {
            if ($locationId) {
                $locationStats[$locationId] = [
                    'name' => $locationName,
                    'total' => 0,
                    'dates' => array_fill_keys(array_keys($dateRange), 0),
                ];
            }
        }

        foreach ($appointments as $appointment) {
            $locationId = $appointment->location_id;
            $dateKey = Carbon::parse($appointment->scheduled_date)->format('Y-m-d');

            if (isset($locationStats[$locationId])) {
                $locationStats[$locationId]['total']++;
                if (isset($locationStats[$locationId]['dates'][$dateKey])) {
                    $locationStats[$locationId]['dates'][$dateKey]++;
                }
            }
        }

        return $locationStats;
    }

    /**
     * Calculate totals per date across all locations.
     */
    private function calculateTotalsByDate(array $dateRange, array $locationStats): array
    {
        $totalByDate = [];

        foreach ($dateRange as $dateKey => $dateInfo) {
            $totalByDate[$dateKey] = 0;
            foreach ($locationStats as $stats) {
                $totalByDate[$dateKey] += $stats['dates'][$dateKey];
            }
        }

        return $totalByDate;
    }

    /**
     * Build CSR-wise consultation stats (new created + rescheduled).
     */
    private function buildCsrStats(
        array $userCentres,
        int $accountId,
        int $consultationTypeId,
        Carbon $today,
        Carbon $endDate,
        array $dateRange,
    ): array {
        $csrUserIds = RoleHasUsers::whereIn('role_id', self::CSR_ROLE_IDS)
            ->pluck('user_id')
            ->toArray();

        $users = User::getAllRecords($accountId)->getDictionary();

        $csrStats = [];
        foreach ($csrUserIds as $csrId) {
            if (isset($users[$csrId]) && $users[$csrId]->active == 1) {
                $csrStats[$csrId] = [
                    'name' => $users[$csrId]->name,
                    'new_created' => array_fill_keys(array_keys($dateRange), 0),
                    'rescheduled' => array_fill_keys(array_keys($dateRange), 0),
                    'total_new' => 0,
                    'total_rescheduled' => 0,
                ];
            }
        }

        $baseQuery = fn () => Appointments::whereIn('location_id', $userCentres)
            ->where('account_id', $accountId)
            ->where('appointment_type_id', $consultationTypeId)
            ->whereDate('scheduled_date', '>=', $today)
            ->whereDate('scheduled_date', '<=', $endDate);

        $newConsultations = $baseQuery()
            ->whereNotNull('created_by')
            ->whereIn('created_by', $csrUserIds)
            ->select('created_by', 'scheduled_date', DB::raw('COUNT(*) as count'))
            ->groupBy('created_by', 'scheduled_date')
            ->get();

        $rescheduledConsultations = $baseQuery()
            ->whereNotNull('converted_by')
            ->whereIn('converted_by', $csrUserIds)
            ->select('converted_by', 'scheduled_date', DB::raw('COUNT(*) as count'))
            ->groupBy('converted_by', 'scheduled_date')
            ->get();

        foreach ($newConsultations as $record) {
            $csrId = $record->created_by;
            $dateKey = Carbon::parse($record->scheduled_date)->format('Y-m-d');

            if (isset($csrStats[$csrId]['new_created'][$dateKey])) {
                $csrStats[$csrId]['new_created'][$dateKey] += $record->count;
                $csrStats[$csrId]['total_new'] += $record->count;
            }
        }

        foreach ($rescheduledConsultations as $record) {
            $csrId = $record->converted_by;
            $dateKey = Carbon::parse($record->scheduled_date)->format('Y-m-d');

            if (isset($csrStats[$csrId]['rescheduled'][$dateKey])) {
                $csrStats[$csrId]['rescheduled'][$dateKey] += $record->count;
                $csrStats[$csrId]['total_rescheduled'] += $record->count;
            }
        }

        uasort($csrStats, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));

        return $csrStats;
    }

    /**
     * Get the CSR daily target from settings.
     */
    private function getCsrTarget(int $accountId): int
    {
        $setting = Settings::getBySlug('sys-csr-target', $accountId);

        return $setting ? (int) $setting->data : self::DEFAULT_CSR_TARGET;
    }
}
