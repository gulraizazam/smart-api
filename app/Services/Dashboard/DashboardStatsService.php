<?php

namespace App\Services\Dashboard;

use App\Helpers\DashboardHelper;
use App\Models\Appointments;
use App\Models\Leads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;

/**
 * Dashboard Stats Service
 * 
 * Handles all dashboard statistics and counts including:
 * - Consultancy counts
 * - Treatment counts
 * - Lead counts
 * - Appointment breakdowns by status/type
 */
class DashboardStatsService
{
    /**
     * Get consultancy statistics
     *
     * @param string $start_date
     * @param string $end_date
     * @param array|null $userCentres
     * @param array|null $statusIds
     * @return array
     */
    public function getConsultancies($start_date, $end_date, $userCentres = null, $statusIds = null)
    {
        $data = [
            'all_consultancies' => null,
            'done_consultancies' => null,
        ];

        if (!Gate::allows('dashboard_states')) {
            return $data;
        }

        $userCentres = $userCentres ?? DashboardHelper::getUserCentres();
        $statusIds = $statusIds ?? DashboardHelper::getArrivedAndConvertedStatusIds();

        // Get both counts in single query using conditional aggregation
        $counts = Appointments::where('appointment_type_id', config('constants.appointment_type_consultancy'))
            ->whereBetween('scheduled_date', [$start_date, $end_date])
            ->whereIn('location_id', $userCentres)
            ->selectRaw('COUNT(*) as all_count, SUM(CASE WHEN appointment_status_id IN (' . implode(',', $statusIds) . ') THEN 1 ELSE 0 END) as done_count')
            ->first();

        $data['all_consultancies'] = $counts->all_count ?? 0;
        $data['done_consultancies'] = $counts->done_count ?? 0;

        return $data;
    }

    /**
     * Get treatment statistics
     *
     * @param string $start_date
     * @param string $end_date
     * @param array|null $userCentres
     * @return array
     */
    public function getTreatments($start_date, $end_date, $userCentres = null)
    {
        $data = [
            'all_treatments' => null,
            'done_treatments' => null,
        ];

        if (!Gate::allows('dashboard_states')) {
            return $data;
        }

        $userCentres = $userCentres ?? DashboardHelper::getUserCentres();
        $arrivedStatusId = DashboardHelper::getArrivedStatusId();

        // Get both counts in single query using conditional aggregation
        $counts = Appointments::where('appointment_type_id', config('constants.appointment_type_service'))
            ->whereBetween('scheduled_date', [$start_date, $end_date])
            ->whereIn('location_id', $userCentres)
            ->selectRaw('COUNT(*) as all_count, SUM(CASE WHEN appointment_status_id = ? THEN 1 ELSE 0 END) as done_count', [$arrivedStatusId])
            ->first();

        $data['all_treatments'] = $counts->all_count ?? 0;
        $data['done_treatments'] = $counts->done_count ?? 0;

        return $data;
    }

    /**
     * Get lead statistics
     *
     * @param string $start_date
     * @param string $end_date
     * @return array
     */
    public function getLeads($start_date, $end_date)
    {
        $data = [
            'leads' => 0,
            'totalLeads' => 0,
        ];

        if (!Gate::allows('dashboard_states')) {
            return $data;
        }

        $userCities = DashboardHelper::getUserCities();

        $where = [
            ['leads.created_at', '>=', $start_date . ' 00:00:00'],
            ['leads.created_at', '<=', $end_date . ' 23:59:59'],
        ];

        $query = Leads::join('users', 'users.id', '=', 'leads.patient_id')
            ->where('users.user_type_id', '=', Config::get('constants.patient_id'))
            ->where(function ($query) use ($userCities) {
                $query->where('leads.active', 1);
                $query->whereIn('leads.city_id', $userCities);
                $query->orWhereNull('leads.city_id');
            });

        $query->where($where);

        $data['leads'] = $query->count();

        $data['totalLeads'] = Leads::join('users', 'users.id', '=', 'leads.patient_id')
            ->where('users.user_type_id', '=', Config::get('constants.patient_id'))
            ->where(function ($query) use ($userCities) {
                $query->where('leads.active', 1);
                $query->whereIn('leads.city_id', $userCities);
                $query->orWhereNull('leads.city_id');
            })->count();

        return $data;
    }

    /**
     * Get all dashboard stats combined
     *
     * @param string $start_date
     * @param string $end_date
     * @param array|null $userCentres
     * @return array
     */
    public function getAllStats($start_date, $end_date, $userCentres = null)
    {
        $userCentres = $userCentres ?? DashboardHelper::getUserCentres();
        
        $consultancies = $this->getConsultancies($start_date, $end_date, $userCentres);
        $treatments = $this->getTreatments($start_date, $end_date, $userCentres);
        $leads = $this->getLeads($start_date, $end_date);

        return array_merge($consultancies, $treatments, $leads);
    }
}
