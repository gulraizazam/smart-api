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
        $goldTypeIds     = $this->getGoldMembershipTypeIds($accountId);
        $goldServiceIds  = $this->getGoldMembershipServiceIds($accountId);

        if (empty($goldTypeIds) && empty($goldServiceIds)) {
            return $this->emptyResult();
        }

        $start = $startDate . ' 00:00:00';
        $end   = $endDate   . ' 23:59:59';

        // NEW flow: package_bundles.membership_type_id is populated
        // One bundle row = one membership sold → COUNT(DISTINCT pb.id)
        $newCount = 0;
        if (!empty($goldTypeIds)) {
            $newCount = DB::table('package_services as ps')
                ->join('package_bundles as pb', 'ps.package_bundle_id', '=', 'pb.id')
                ->where('ps.sold_by', $doctorId)
                ->whereIn('pb.membership_type_id', $goldTypeIds)
                ->whereBetween('ps.created_at', [$start, $end])
                ->whereNull('pb.deleted_at')
                ->distinct('pb.id')
                ->count('pb.id');
        }

        // OLD flow: membership sold as a service inside a plan
        // One package = one membership sold → COUNT(DISTINCT ps.package_id)
        $oldCount = 0;
        if (!empty($goldServiceIds)) {
            $oldCount = DB::table('package_services as ps')
                ->join('package_bundles as pb', 'ps.package_bundle_id', '=', 'pb.id')
                ->where('ps.sold_by', $doctorId)
                ->whereIn('ps.service_id', $goldServiceIds)
                ->whereNull('pb.membership_type_id')
                ->whereBetween('ps.created_at', [$start, $end])
                ->whereNull('pb.deleted_at')
                ->distinct('ps.package_id')
                ->count('ps.package_id');
        }

        return [
            'gold_memberships_sold' => $newCount + $oldCount,
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
        $goldTypeIds    = $this->getGoldMembershipTypeIds($accountId);
        $goldServiceIds = $this->getGoldMembershipServiceIds($accountId);

        if ((empty($goldTypeIds) && empty($goldServiceIds)) || empty($doctorIds)) {
            return [];
        }
        $start = $startDate . ' 00:00:00';
        $end   = $endDate   . ' 23:59:59';

        $results = [];

        // NEW flow: one bundle = one membership → COUNT(DISTINCT pb.id)
        if (!empty($goldTypeIds)) {
            $rows = DB::table('package_services as ps')
                ->join('package_bundles as pb', 'ps.package_bundle_id', '=', 'pb.id')
                ->whereIn('ps.sold_by', $doctorIds)
                ->whereIn('pb.membership_type_id', $goldTypeIds)
                ->whereBetween('ps.created_at', [$start, $end])
                ->whereNull('pb.deleted_at')
                ->select('ps.sold_by as doctor_id', DB::raw('COUNT(DISTINCT pb.id) as cnt'))
                ->groupBy('ps.sold_by')
                ->pluck('cnt', 'doctor_id')
                ->toArray();
            foreach ($rows as $docId => $cnt) {
                $results[$docId] = ($results[$docId] ?? 0) + (int) $cnt;
            }
        }

        // OLD flow: one package = one membership → COUNT(DISTINCT ps.package_id)
        if (!empty($goldServiceIds)) {
            $rows = DB::table('package_services as ps')
                ->join('package_bundles as pb', 'ps.package_bundle_id', '=', 'pb.id')
                ->whereIn('ps.sold_by', $doctorIds)
                ->whereIn('ps.service_id', $goldServiceIds)
                ->whereNull('pb.membership_type_id')
                ->whereBetween('ps.created_at', [$start, $end])
                ->whereNull('pb.deleted_at')
                ->select('ps.sold_by as doctor_id', DB::raw('COUNT(DISTINCT ps.package_id) as cnt'))
                ->groupBy('ps.sold_by')
                ->pluck('cnt', 'doctor_id')
                ->toArray();
            foreach ($rows as $docId => $cnt) {
                $results[$docId] = ($results[$docId] ?? 0) + (int) $cnt;
            }
        }

        return $results;
    }

    /**
     * Get service IDs that represent Gold Membership (old plan flow where membership
     * was added as a service in a plan rather than via membership_type_id).
     *
     * @param int $accountId
     * @return array
     */
    public function getGoldMembershipServiceIds(int $accountId): array
    {
        return Cache::remember("gold_membership_service_ids_{$accountId}", 3600, function () use ($accountId) {
            return DB::table('services')
                ->where('account_id', $accountId)
                ->where('name', 'LIKE', '%Gold%')
                ->whereNull('deleted_at')
                ->pluck('id')
                ->toArray();
        });
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
