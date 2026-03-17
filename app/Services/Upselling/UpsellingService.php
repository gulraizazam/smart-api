<?php

namespace App\Services\Upselling;

use App\Helpers\ACL;
use App\Models\PackageService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
     * @param string|int $centreId  Location ID or 'all'
     * @param string $startDate     Y-m-d H:i:s
     * @param string $endDate       Y-m-d H:i:s
     * @return array Collection of objects with doctor_id, doctor_name, total_upselling_amount
     */
    public function getDoctorUpsellingData($centreId, string $startDate, string $endDate): array
    {
        $userLocations = ACL::getUserCentres();

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
            ->when($centreId !== 'all', function ($q) use ($centreId) {
                $q->where('user_has_locations.location_id', $centreId);
            })
            ->distinct()
            ->pluck('id');

        // Get doctors for the location(s)
        $locationFilter = $centreId === 'all' ? $userLocations : [$centreId];
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
                $query->where('appointments.appointment_type_id', '!=', 1)
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
        $reportData = $allActiveUsers->map(function ($user) use ($upsellingData) {
            return (object) [
                'doctor_id' => $user->id,
                'doctor_name' => $user->name,
                'total_upselling_amount' => $upsellingData->get($user->id)->total_upselling_amount ?? 0,
            ];
        })->sortByDesc('total_upselling_amount')->values()->toArray();

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
                'start_date' => now()->subMonth()->startOfMonth()->format('Y-m-d 00:00:00'),
                'end_date' => now()->subMonth()->endOfMonth()->format('Y-m-d 23:59:59'),
            ],
        ];

        $p = $periods[$period] ?? $periods['thismonth'];

        return [$p['start_date'], $p['end_date']];
    }
}
