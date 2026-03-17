<?php

namespace App\Services\DoctorDashboard;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class MembershipCalculator
{
    /**
     * Count Gold memberships sold by a doctor in a date range.
     *
     * Gold Membership = membership_types where name LIKE '%Gold%' + their renewals (parent_id chain)
     * Attribution: package_services.sold_by = doctor's user_id
     * Only packages with plan_type = 'membership'
     *
     * @param int $doctorId
     * @param string $startDate Y-m-d
     * @param string $endDate Y-m-d
     * @param int $accountId
     * @return array
     */
    public function calculate(int $doctorId, string $startDate, string $endDate, int $accountId): array
    {
        $goldTypeIds = $this->getGoldMembershipTypeIds($accountId);

        if (empty($goldTypeIds)) {
            return $this->emptyResult();
        }

        // Count Gold memberships sold by this doctor.
        // Join via the correct FK: package_services.package_bundle_id → package_bundles.id
        // Do NOT filter on packages.plan_type — it's unreliable for old records (defaults to 'plan').
        // Instead filter on package_bundles.membership_type_id IN goldTypeIds.
        // Count DISTINCT pb.id (each bundle row = one membership sold).
        $count = DB::table('package_services as ps')
            ->join('package_bundles as pb', 'ps.package_bundle_id', '=', 'pb.id')
            ->where('ps.sold_by', $doctorId)
            ->whereIn('pb.membership_type_id', $goldTypeIds)
            ->whereBetween('ps.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->whereNull('pb.deleted_at')
            ->distinct()
            ->count('pb.id');

        return [
            'gold_memberships_sold' => $count,
        ];
    }

    /**
     * Get Gold membership type IDs including renewals (cached).
     *
     * @param int $accountId
     * @return array
     */
    public function getGoldMembershipTypeIds(int $accountId): array
    {
        return Cache::remember("gold_membership_type_ids_{$accountId}", 3600, function () {
            // Find parent Gold membership types
            $goldParentIds = DB::table('membership_types')
                ->where('name', 'LIKE', '%Gold%')
                ->whereNull('parent_id')
                ->pluck('id')
                ->toArray();

            if (empty($goldParentIds)) {
                // Try without parent_id filter in case Gold is named differently
                $goldParentIds = DB::table('membership_types')
                    ->where('name', 'LIKE', '%Gold%')
                    ->pluck('id')
                    ->toArray();
            }

            if (empty($goldParentIds)) {
                return [];
            }

            // Get renewal types (children) of Gold membership types
            $renewalIds = DB::table('membership_types')
                ->whereIn('parent_id', $goldParentIds)
                ->pluck('id')
                ->toArray();

            return array_merge($goldParentIds, $renewalIds);
        });
    }

    /**
     * Count memberships for multiple doctors (benchmark).
     *
     * @param array $doctorIds
     * @param string $startDate
     * @param string $endDate
     * @param int $accountId
     * @return array [doctorId => count]
     */
    public function calculateForDoctors(array $doctorIds, string $startDate, string $endDate, int $accountId): array
    {
        $goldTypeIds = $this->getGoldMembershipTypeIds($accountId);

        if (empty($goldTypeIds) || empty($doctorIds)) {
            return [];
        }

        return DB::table('package_services as ps')
            ->join('package_bundles as pb', 'ps.package_bundle_id', '=', 'pb.id')
            ->whereIn('ps.sold_by', $doctorIds)
            ->whereIn('pb.membership_type_id', $goldTypeIds)
            ->whereBetween('ps.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->whereNull('pb.deleted_at')
            ->select('ps.sold_by as doctor_id', DB::raw('COUNT(DISTINCT pb.id) as cnt'))
            ->groupBy('ps.sold_by')
            ->pluck('cnt', 'doctor_id')
            ->map(fn($v) => (int) $v)
            ->toArray();
    }

    /**
     * @return array
     */
    private function emptyResult(): array
    {
        return [
            'gold_memberships_sold' => 0,
        ];
    }
}
