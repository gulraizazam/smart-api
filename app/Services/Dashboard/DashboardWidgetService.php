<?php

declare(strict_types=1);
namespace App\Services\Dashboard;

use App\Helpers\DashboardHelper;
use App\Models\Activity;
use App\Models\Locations;
use App\Models\RoleHasUsers;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DashboardWidgetService
{
    /**
     * Get paginated activities for infinite scroll.
     */
    public function getActivities(int $page = 1, int $perPage = 10): array
    {
        if (!Gate::allows('dashboard_recent_activities')) {
            return ['data' => [], 'has_more' => false, 'total' => 0, 'current_page' => $page];
        }

        $centres = DashboardHelper::getUserCentres();
        $todayStart = Carbon::today();
        $todayEnd = Carbon::tomorrow();

        $baseQuery = Activity::whereIn('centre_id', $centres)
            ->whereIn('action', ['received', 'consumed', 'refunded'])
            ->where('created_at', '>=', $todayStart)
            ->where('created_at', '<', $todayEnd);

        $totalCount = (clone $baseQuery)->count();

        $activities = (clone $baseQuery)
            ->with([
                'plan' => fn($q) => $q->select('id', 'name'),
                'centre' => fn($q) => $q->select('id', 'name'),
            ])
            ->latest()
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        // Batch-load user names to avoid N+1
        $createdByIds = $activities->pluck('created_by')
            ->filter(fn($id) => is_numeric($id) && $id > 0)
            ->unique()
            ->values()
            ->toArray();

        $users = User::whereIn('id', $createdByIds)->pluck('name', 'id');

        $activities->each(function ($activity) use ($users) {
            $createdBy = $activity->created_by;
            $activity->created_by_name = match (true) {
                is_numeric($createdBy) && isset($users[$createdBy]) => $users[$createdBy],
                !is_numeric($createdBy) && (bool) $createdBy => $createdBy,
                default => 'N/A',
            };
        });

        return [
            'data' => $activities,
            'has_more' => ($page * $perPage) < $totalCount,
            'total' => $totalCount,
            'current_page' => $page,
        ];
    }

    /**
     * Get dashboard config (centres, roles, permissions).
     */
    public function getConfig(): array
    {
        $userCentres = DashboardHelper::getUserCentres();
        $centresExclude = ['All South Region', 'All Central Region', 'All Centres'];

        $centres = Locations::whereIn('id', $userCentres)
            ->whereNotIn('name', $centresExclude)
            ->where('active', 1)
            ->select('id', 'name')
            ->get();

        $user = Auth::user();
        $adminRoles = ['Administrator', 'Super-Admin', 'Head of Operations', 'Finance', 'HRM'];
        $csrRoles = ['CSR Supervisor', 'Social Lead', 'CSR'];

        $isAdmin = collect($adminRoles)->contains(fn($role) => $user->hasRole($role));
        $isCSRRole = collect($csrRoles)->contains(fn($role) => $user->hasRole($role));

        $csrUsers = collect();
        if ($isCSRRole) {
            $csrUserIds = RoleHasUsers::whereIn('role_id', [2, 3, 24])->pluck('user_id');
            $csrUsers = User::whereIn('id', $csrUserIds)
                ->where('active', 1)
                ->select('id', 'name')
                ->get();
        }

        return [
            'centres' => $centres,
            'firstCentre' => $centres->first(),
            'isAdmin' => $isAdmin,
            'isCSRRole' => $isCSRRole,
            'hasMultipleCentres' => $isAdmin || $centres->count() > 1,
            'csrUsers' => $csrUsers,
            'permissions' => [
                'dashboard_recent_activities' => Gate::allows('dashboard_recent_activities'),
                'dashboard_unattended_report' => Gate::allows('dashboard_unattended_report'),
                'dashboard_overdue_treatments' => Gate::allows('dashboard_overdue_treatments'),
                'dashboard_staff_wise_arrival' => Gate::allows('dashboard_staff_wise_arrival'),
                'dashboard_doctor_wise_conversion' => Gate::allows('dashboard_doctor_wise_conversion'),
                'dashboard_doctor_wise_feedback' => Gate::allows('dashboard_doctor_wise_feedback'),
                'dashboard_upselling_report' => Gate::allows('dashboard_upselling_report'),
            ],
        ];
    }

    /**
     * Get unattended payments with pagination.
     * Patients where first payment is 7+ days old, no treatment booked, balance >= 100.
     */
    public function getUnattendedPayments(int $page = 1, int $perPage = 10): array
    {
        $centerIds = DashboardHelper::getUserCentres();

        if (empty($centerIds)) {
            return ['patient_data' => [], 'current_page' => $page, 'has_more' => false];
        }

        $offset = ($page - 1) * $perPage;
        $centerIdsStr = implode(',', array_map('intval', $centerIds));
        $sevenDaysAgo = Carbon::now()->subDays(7)->format('Y-m-d H:i:s');
        $threeMonthsAgo = Carbon::now()->subMonths(3)->format('Y-m-d');

        $sql = "
            SELECT
                u.id as patient_id,
                u.name,
                bal.cash_in,
                bal.cash_out,
                bal.conversion_date
            FROM users u
            INNER JOIN (
                SELECT DISTINCT patient_id
                FROM appointments
                WHERE appointment_type_id = 1
                    AND base_appointment_status_id = 2
                    AND location_id IN ({$centerIdsStr})
                    AND scheduled_date >= ?
            ) apt ON u.id = apt.patient_id
            INNER JOIN (
                SELECT
                    patient_id,
                    COALESCE(SUM(CASE WHEN cash_flow = 'in' AND is_cancel = 0 AND is_tax = 0 AND is_adjustment = 0 AND is_refund = 0 THEN cash_amount ELSE 0 END), 0) as cash_in,
                    COALESCE(SUM(CASE WHEN cash_flow = 'out' AND is_cancel = 0 AND is_adjustment = 0 AND is_refund = 0 THEN cash_amount ELSE 0 END), 0) as cash_out,
                    MIN(CASE WHEN cash_flow = 'in' AND cash_amount > 0 AND is_tax = 0 THEN created_at END) as conversion_date
                FROM package_advances
                GROUP BY patient_id
            ) bal ON u.id = bal.patient_id
            WHERE u.user_type_id = 3 AND u.active = 1
                AND bal.conversion_date IS NOT NULL
                AND bal.conversion_date <= ?
                AND (bal.cash_in - bal.cash_out) >= 100
                AND NOT EXISTS (
                    SELECT 1 FROM appointments t
                    WHERE t.patient_id = u.id
                    AND t.appointment_type_id = 2
                    AND t.location_id IN ({$centerIdsStr})
                )
            ORDER BY bal.conversion_date DESC
            LIMIT ? OFFSET ?
        ";

        $patients = DB::select($sql, [$threeMonthsAgo, $sevenDaysAgo, $perPage + 1, $offset]);

        $hasMore = count($patients) > $perPage;
        if ($hasMore) {
            array_pop($patients);
        }

        $patientData = array_map(fn($p) => [
            'patient_id' => $p->patient_id,
            'name' => $p->name,
            'is_treatment' => 0,
            'balance' => (float) ($p->cash_in - $p->cash_out),
            'created_at' => $p->conversion_date ? Carbon::parse($p->conversion_date)->format('Y-m-d') : null,
        ], $patients);

        return [
            'patient_data' => $patientData,
            'current_page' => $page,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Get overdue treatments with pagination.
     * Patients with last treatment >= 31 days ago, no future treatments, balance > 100.
     */
    public function getOverdueTreatments(int $page = 1, int $perPage = 10): array
    {
        $centerIds = DashboardHelper::getUserCentres();

        if (empty($centerIds)) {
            return ['patient_data' => [], 'current_page' => $page, 'has_more' => false];
        }

        $offset = ($page - 1) * $perPage;
        $centerIdsStr = implode(',', array_map('intval', $centerIds));
        $thirtyOneDaysAgo = Carbon::now()->subDays(31)->format('Y-m-d');
        $today = Carbon::now()->format('Y-m-d');

        $sql = "
            SELECT
                u.id as patient_id,
                u.name,
                apt.last_arrived,
                bal.cash_in,
                bal.cash_out
            FROM users u
            INNER JOIN (
                SELECT patient_id, MAX(scheduled_date) as last_arrived
                FROM appointments
                WHERE appointment_type_id = 2
                    AND base_appointment_status_id = 2
                    AND location_id IN ({$centerIdsStr})
                GROUP BY patient_id
                HAVING MAX(scheduled_date) <= ?
            ) apt ON u.id = apt.patient_id
            INNER JOIN (
                SELECT
                    patient_id,
                    COALESCE(SUM(CASE WHEN cash_flow = 'in' AND is_cancel = 0 AND is_tax = 0 AND is_adjustment = 0 AND is_refund = 0 THEN cash_amount ELSE 0 END), 0) as cash_in,
                    COALESCE(SUM(CASE WHEN cash_flow = 'out' AND is_cancel = 0 AND is_adjustment = 0 AND is_refund = 0 THEN cash_amount ELSE 0 END), 0) as cash_out
                FROM package_advances
                GROUP BY patient_id
                HAVING (cash_in - cash_out) > 100
            ) bal ON u.id = bal.patient_id
            WHERE u.user_type_id = 3 AND u.active = 1
                AND NOT EXISTS (
                    SELECT 1 FROM appointments f
                    WHERE f.patient_id = u.id
                    AND f.appointment_type_id = 2
                    AND f.scheduled_date >= ?
                    AND f.location_id IN ({$centerIdsStr})
                )
            ORDER BY apt.last_arrived DESC
            LIMIT ? OFFSET ?
        ";

        $patients = DB::select($sql, [$thirtyOneDaysAgo, $today, $perPage + 1, $offset]);

        $hasMore = count($patients) > $perPage;
        if ($hasMore) {
            array_pop($patients);
        }

        $patientData = array_map(fn($p) => [
            'patient_id' => $p->patient_id,
            'name' => $p->name,
            'balance' => (float) ($p->cash_in - $p->cash_out),
            'scheduled_date' => $p->last_arrived,
        ], $patients);

        return [
            'patient_data' => $patientData,
            'current_page' => $page,
            'has_more' => $hasMore,
        ];
    }
}
