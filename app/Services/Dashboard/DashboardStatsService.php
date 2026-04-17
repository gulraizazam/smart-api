<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Helpers\DashboardHelper;
use App\Models\Leads;
use App\Services\Dashboard\Support\AppointmentCountsQuery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;

/**
 * Home-page stats service. Since the rule definitions for consultations
 * and treatments are shared with the Management Dashboard, this class now
 * delegates the actual counting to Dashboard\Support\AppointmentCountsQuery
 * (single source of truth). Response shapes are preserved byte-identical so
 * the /admin/home frontend is unaffected.
 *
 * Once /admin/home is retired, this class and its callers can be deleted
 * straight through — the helpers continue serving the Management Dashboard.
 */
class DashboardStatsService
{
    /**
     * Get consultancies AND treatments. Now two helper calls instead of one
     * combined query — the marginal extra round-trip is deliberate: the
     * combined-query version required two parallel SUM expressions over
     * different status sets, which isn't generalisable. We keep response
     * shape identical.
     */
    public function getAppointmentStats(
        string $startDate,
        string $endDate,
        array $userCentres,
    ): array {
        if (! Gate::allows('dashboard_states')) {
            return [
                'all_consultancies' => null,
                'done_consultancies' => null,
                'all_treatments' => null,
                'done_treatments' => null,
            ];
        }

        $consultancy = $this->countConsultancies($startDate, $endDate, $userCentres);
        $treatment = $this->countTreatments($startDate, $endDate, $userCentres);

        return [
            'all_consultancies' => $consultancy['all_count'],
            'done_consultancies' => $consultancy['done_count'],
            'all_treatments' => $treatment['all_count'],
            'done_treatments' => $treatment['done_count'],
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
        if (! Gate::allows('dashboard_states')) {
            return ['all_consultancies' => null, 'done_consultancies' => null];
        }

        $counts = $this->countConsultancies(
            $startDate,
            $endDate,
            $userCentres ?? DashboardHelper::getUserCentres(),
            $statusIds,
        );

        return [
            'all_consultancies' => $counts['all_count'],
            'done_consultancies' => $counts['done_count'],
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
        if (! Gate::allows('dashboard_states')) {
            return ['all_treatments' => null, 'done_treatments' => null];
        }

        $counts = $this->countTreatments(
            $startDate,
            $endDate,
            $userCentres ?? DashboardHelper::getUserCentres(),
        );

        return [
            'all_treatments' => $counts['all_count'],
            'done_treatments' => $counts['done_count'],
        ];
    }

    public function getLeads(string $startDate, string $endDate): array
    {
        if (! Gate::allows('dashboard_states')) {
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
                ->where('leads.created_at', '>=', $startDate.' 00:00:00')
                ->where('leads.created_at', '<=', $endDate.' 23:59:59')
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

    /**
     * @param  list<int>|null  $qualifyingStatusIds
     * @return array{all_count: int, done_count: int}
     */
    private function countConsultancies(
        string $startDate,
        string $endDate,
        array $userCentres,
        ?array $qualifyingStatusIds = null,
    ): array {
        return AppointmentCountsQuery::forType(
            accountId: (int) (Auth::user()->account_id ?? 0),
            locationIds: array_values(array_map('intval', $userCentres)),
            appointmentTypeId: (int) Config::get('constants.appointment_type_consultancy'),
            qualifyingStatusIds: $qualifyingStatusIds ?? DashboardHelper::getArrivedAndConvertedStatusIds(),
            startDate: $startDate,
            endDate: $endDate,
        );
    }

    /**
     * @return array{all_count: int, done_count: int}
     */
    private function countTreatments(
        string $startDate,
        string $endDate,
        array $userCentres,
    ): array {
        return AppointmentCountsQuery::forType(
            accountId: (int) (Auth::user()->account_id ?? 0),
            locationIds: array_values(array_map('intval', $userCentres)),
            appointmentTypeId: (int) Config::get('constants.appointment_type_service'),
            qualifyingStatusIds: [DashboardHelper::getArrivedStatusId()],
            startDate: $startDate,
            endDate: $endDate,
        );
    }
}
