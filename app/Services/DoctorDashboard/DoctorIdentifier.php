<?php

namespace App\Services\DoctorDashboard;

use App\Helpers\DoctorDashboardHelper;
use App\Models\DoctorHasLocations;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class DoctorIdentifier
{
    /**
     * Get the currently logged-in doctor's user ID.
     *
     * @return int
     */
    public function getCurrentDoctorId(): int
    {
        return auth()->id();
    }

    /**
     * Check if a user has a doctor role.
     *
     * @param int $userId
     * @return bool
     */
    public function isDoctor(int $userId): bool
    {
        $roleIds = DoctorDashboardHelper::getDoctorRoleIds();

        return DB::table('model_has_roles')
            ->where('model_id', $userId)
            ->where('model_type', 'App\\Models\\User')
            ->whereIn('role_id', $roleIds)
            ->exists();
    }

    /**
     * Get all location IDs where a doctor is actively allocated.
     *
     * @param int $doctorId
     * @return array
     */
    public function getDoctorLocationIds(int $doctorId): array
    {
        return DoctorHasLocations::where('user_id', $doctorId)
            ->where('is_allocated', 1)
            ->pluck('location_id')
            ->unique()
            ->toArray();
    }

    /**
     * Get all active doctor user IDs across all locations (for benchmarks).
     * Filtered to users who have a doctor role AND is_allocated=1.
     *
     * @param int $accountId
     * @return array
     */
    public function getAllActiveDoctorIds(int $accountId): array
    {
        $cacheKey = "all_active_doctor_ids_{$accountId}";

        return Cache::remember($cacheKey, DoctorDashboardHelper::CACHE_TTL, function () use ($accountId) {
            $roleIds = DoctorDashboardHelper::getDoctorRoleIds();

            return DB::table('users')
                ->join('model_has_roles', function ($join) {
                    $join->on('users.id', '=', 'model_has_roles.model_id')
                        ->where('model_has_roles.model_type', '=', 'App\\Models\\User');
                })
                ->join('doctor_has_locations', function ($join) {
                    $join->on('users.id', '=', 'doctor_has_locations.user_id')
                        ->where('doctor_has_locations.is_allocated', '=', 1);
                })
                ->where('users.account_id', $accountId)
                ->where('users.active', 1)
                ->whereIn('model_has_roles.role_id', $roleIds)
                ->distinct()
                ->pluck('users.id')
                ->toArray();
        });
    }

    /**
     * Get doctor display info (name, locations).
     *
     * @param int $doctorId
     * @return array
     */
    public function getDoctorInfo(int $doctorId): array
    {
        $doctor = User::select('id', 'name', 'email', 'active', 'image_src')
            ->find($doctorId);

        if (!$doctor) {
            return [];
        }

        $locations = DB::table('doctor_has_locations')
            ->join('locations', 'locations.id', '=', 'doctor_has_locations.location_id')
            ->where('doctor_has_locations.user_id', $doctorId)
            ->where('doctor_has_locations.is_allocated', 1)
            ->select('locations.id', 'locations.name')
            ->distinct()
            ->get();

        return [
            'id' => $doctor->id,
            'name' => $doctor->name,
            'email' => $doctor->email,
            'active' => $doctor->active,
            'image_src' => $doctor->image_src,
            'locations' => $locations->toArray(),
            'location_ids' => $locations->pluck('id')->toArray(),
        ];
    }

    /**
     * Get the doctor's most active branch this month (highest revenue).
     * Used for goal progress bar target selection.
     *
     * @param int $doctorId
     * @param string $startDate
     * @param string $endDate
     * @param int $accountId
     * @return int|null Location ID of most active branch
     */
    public function getMostActiveBranch(int $doctorId, string $startDate, string $endDate, int $accountId): ?int
    {
        $locationIds = $this->getDoctorLocationIds($doctorId);

        if (empty($locationIds)) {
            return null;
        }

        // Find the branch where this doctor generated the most revenue this month
        // Revenue = package_advances from patients where this doctor is last consultant, grouped by location
        $result = DB::table('package_advances as pa')
            ->join('packages as p', 'pa.package_id', '=', 'p.id')
            ->join('appointments as a', function ($join) use ($doctorId) {
                $join->on('p.patient_id', '=', 'a.patient_id')
                    ->where('a.appointment_type_id', '=', 1)
                    ->where('a.doctor_id', '=', $doctorId);
            })
            ->whereIn('a.location_id', $locationIds)
            ->where('pa.cash_amount', '>', 0)
            ->whereBetween('pa.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select('a.location_id', DB::raw('SUM(pa.cash_amount) as total_revenue'))
            ->groupBy('a.location_id')
            ->orderByDesc('total_revenue')
            ->first();

        if ($result) {
            return (int) $result->location_id;
        }

        // Fallback: return first allocated location
        return $locationIds[0] ?? null;
    }
}
