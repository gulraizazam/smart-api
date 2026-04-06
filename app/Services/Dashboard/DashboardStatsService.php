<?php

declare(strict_types=1);
namespace App\Services\Dashboard;

use App\Helpers\DashboardHelper;
use App\Models\Appointments;
use App\Models\Leads;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DashboardStatsService
{
    /**
     * Get consultancies AND treatments in a single query (was 2 separate queries).
     * Returns combined array with all_consultancies, done_consultancies, all_treatments, done_treatments.
     */
    public function getAppointmentStats(
        string $startDate,
        string $endDate,
        array $userCentres,
    ): array {
        if (!Gate::allows('dashboard_states')) {
            return [
                'all_consultancies' => null,
                'done_consultancies' => null,
                'all_treatments' => null,
                'done_treatments' => null,
            ];
        }

        $consultancyTypeId = (int) config('constants.appointment_type_consultancy');
        $treatmentTypeId = (int) config('constants.appointment_type_service');
        $arrivedStatusIds = DashboardHelper::getArrivedAndConvertedStatusIds();
        $arrivedStatusId = DashboardHelper::getArrivedStatusId();

        $statusPlaceholders = implode(',', array_fill(0, count($arrivedStatusIds), '?'));
        $bindings = [...$arrivedStatusIds, $arrivedStatusId];

        $results = Appointments::query()
            ->whereIn('appointment_type_id', [$consultancyTypeId, $treatmentTypeId])
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->whereIn('location_id', $userCentres)
            ->select(
                'appointment_type_id',
                DB::raw('COUNT(*) as all_count'),
                DB::raw("SUM(CASE WHEN appointment_status_id IN ({$statusPlaceholders}) THEN 1 ELSE 0 END) as done_arrived_converted"),
                DB::raw('SUM(CASE WHEN appointment_status_id = ? THEN 1 ELSE 0 END) as done_arrived_only'),
            )
            ->addBinding($bindings, 'select')
            ->groupBy('appointment_type_id')
            ->get()
            ->keyBy('appointment_type_id');

        $consultancy = $results->get($consultancyTypeId);
        $treatment = $results->get($treatmentTypeId);

        return [
            'all_consultancies'  => $consultancy?->all_count ?? 0,
            'done_consultancies' => $consultancy?->done_arrived_converted ?? 0,
            'all_treatments'     => $treatment?->all_count ?? 0,
            'done_treatments'    => $treatment?->done_arrived_only ?? 0,
        ];
    }

    /**
     * Get consultancies only (backward-compatible).
     */
    public function getConsultancies(
        string $startDate,
        string $endDate,
        ?array $userCentres = null,
        ?array $statusIds = null,
    ): array {
        if (!Gate::allows('dashboard_states')) {
            return ['all_consultancies' => null, 'done_consultancies' => null];
        }

        $userCentres ??= DashboardHelper::getUserCentres();
        $statusIds ??= DashboardHelper::getArrivedAndConvertedStatusIds();

        $counts = Appointments::query()
            ->where('appointment_type_id', config('constants.appointment_type_consultancy'))
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->whereIn('location_id', $userCentres)
            ->selectRaw(
                'COUNT(*) as all_count, SUM(CASE WHEN appointment_status_id IN ('. implode(',', array_map('intval', $statusIds)) .') THEN 1 ELSE 0 END) as done_count'
            )
            ->first();

        return [
            'all_consultancies'  => $counts?->all_count ?? 0,
            'done_consultancies' => $counts?->done_count ?? 0,
        ];
    }

    /**
     * Get treatments only (backward-compatible).
     */
    public function getTreatments(
        string $startDate,
        string $endDate,
        ?array $userCentres = null,
    ): array {
        if (!Gate::allows('dashboard_states')) {
            return ['all_treatments' => null, 'done_treatments' => null];
        }

        $userCentres ??= DashboardHelper::getUserCentres();
        $arrivedStatusId = DashboardHelper::getArrivedStatusId();

        $counts = Appointments::query()
            ->where('appointment_type_id', config('constants.appointment_type_service'))
            ->whereBetween('scheduled_date', [$startDate, $endDate])
            ->whereIn('location_id', $userCentres)
            ->selectRaw(
                'COUNT(*) as all_count, SUM(CASE WHEN appointment_status_id = ? THEN 1 ELSE 0 END) as done_count',
                [$arrivedStatusId],
            )
            ->first();

        return [
            'all_treatments'  => $counts?->all_count ?? 0,
            'done_treatments' => $counts?->done_count ?? 0,
        ];
    }

    public function getLeads(string $startDate, string $endDate): array
    {
        if (!Gate::allows('dashboard_states')) {
            return ['leads' => 0, 'totalLeads' => 0];
        }

        $userCities = DashboardHelper::getUserCities();
        $patientTypeId = Config::get('constants.patient_id');

        $baseQuery = fn () => Leads::query()
            ->join('users', 'users.id', '=', 'leads.patient_id')
            ->where('users.user_type_id', $patientTypeId)
            ->where(fn ($q) => $q
                ->where('leads.active', 1)
                ->whereIn('leads.city_id', $userCities)
                ->orWhereNull('leads.city_id')
            );

        return [
            'leads' => $baseQuery()
                ->where('leads.created_at', '>=', $startDate . ' 00:00:00')
                ->where('leads.created_at', '<=', $endDate . ' 23:59:59')
                ->count(),
            'totalLeads' => $baseQuery()->count(),
        ];
    }

    public function getAllStats(
        string $startDate,
        string $endDate,
        ?array $userCentres = null,
    ): array {
        $userCentres ??= DashboardHelper::getUserCentres();

        return array_merge(
            $this->getConsultancies($startDate, $endDate, $userCentres),
            $this->getTreatments($startDate, $endDate, $userCentres),
            $this->getLeads($startDate, $endDate),
        );
    }
}
