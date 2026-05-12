<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Services;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MembershipReportService
{
    /**
     * Generate the membership report data.
     *
     * Finds packages that contain membership card services (Gold/Student),
     * then maps each to a user row with membership details.
     *
     * Implementation note — this used to use Eloquent `with()` plus
     * `whereHas('user.membership', …)`. On real datasets that produced
     * multi-second hangs because the deep correlated subqueries were
     * unindexed. Rewriting as a single explicit JOIN with a derived
     * "latest membership per patient" subquery brings cold-cache
     * execution from minutes down to milliseconds while keeping the
     * row contract identical (the calling controller still gets a
     * Collection of arrays with the same keys).
     *
     * @param int[]|null $locationIds
     */
    public function generate(
        ?array $locationIds,
        ?string $membershipTypeId,
        ?string $startDate,
        ?string $endDate,
    ): Collection {
        $serviceIds = $this->getMembershipServiceIds();

        if (empty($serviceIds)) {
            return collect();
        }

        // Latest membership per patient — picks max(id) per patient_id, then
        // joins back to memberships to fetch the row. Equivalent to the old
        // `HasOne(...)->orderByDesc('id')` selection but expressible in SQL
        // as a tiny derived table the optimizer plans well.
        $latestMembershipSql = '(
            SELECT m1.id, m1.patient_id, m1.code, m1.assigned_at, m1.membership_type_id
            FROM memberships m1
            INNER JOIN (
                SELECT patient_id, MAX(id) AS max_id
                FROM memberships
                GROUP BY patient_id
            ) m2 ON m1.id = m2.max_id
        ) AS memberships';

        $query = DB::table('packages')
            ->select(
                'packages.id as package_id',
                'packages.patient_id as user_id',
                'users.name as user_name',
                'locations.name as location_name',
                'package_services.service_id',
                'package_services.is_consumed',
                'services.name as service_name',
                'memberships.code as membership_code',
                'memberships.assigned_at as membership_assigned_at',
                'memberships.membership_type_id',
                'membership_types.name as membership_type_name',
            )
            // Inner-join package_services with the service-id filter inline
            // so MariaDB filters at join-time (instead of via EXISTS subquery).
            ->join('package_services', function ($join) use ($serviceIds) {
                $join->on('package_services.package_id', '=', 'packages.id')
                    ->whereIn('package_services.service_id', $serviceIds);
            })
            ->join('services', 'services.id', '=', 'package_services.service_id')
            ->leftJoin('users', 'users.id', '=', 'packages.patient_id')
            ->leftJoin('locations', 'locations.id', '=', 'packages.location_id')
            ->leftJoin(DB::raw($latestMembershipSql), 'memberships.patient_id', '=', 'packages.patient_id')
            ->leftJoin('membership_types', 'membership_types.id', '=', 'memberships.membership_type_id')
            ->when(! empty($locationIds), fn ($q) => $q->whereIn('packages.location_id', $locationIds))
            ->when(
                $membershipTypeId === 'no_membership',
                fn ($q) => $q->whereNull('memberships.id')
            )
            ->when(
                $membershipTypeId !== null && $membershipTypeId !== 'no_membership',
                fn ($q) => $q->where('memberships.membership_type_id', $membershipTypeId)
            )
            ->when(
                $startDate && $endDate,
                fn ($q) => $q->whereBetween('memberships.assigned_at', [$startDate, $endDate])
            );

        return collect($query->get())->map(fn ($row) => [
            'user_id' => $row->user_id !== null ? (int) $row->user_id : null,
            'user_name' => $row->user_name ?? 'N/A',
            'location' => $row->location_name ?? 'N/A',
            'service_name' => $row->service_name ?? 'N/A',
            'service_status' => (int) ($row->is_consumed ?? 0) === 1 ? 'Consumed' : 'Not Consumed',
            'membership_code' => $row->membership_code ?? 'No membership',
            'membership_type' => $row->membership_type_name ?? 'No membership',
            'membership_type_id' => $row->membership_type_id !== null ? (int) $row->membership_type_id : 0,
            'assigned_at' => $row->membership_assigned_at,
        ]);
    }

    /**
     * Get service IDs for membership card services.
     *
     * @return array<int>
     */
    private function getMembershipServiceIds(): array
    {
        return Services::where('name', 'like', '%Gold Membership Card%')
            ->orWhere('name', 'like', '%Student Membership Card%')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }
}
