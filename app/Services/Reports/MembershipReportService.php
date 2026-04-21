<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Packages;
use App\Models\Services;
use Illuminate\Support\Collection;

class MembershipReportService
{
    /**
     * Generate the membership report data.
     *
     * Finds packages that contain membership card services (Gold/Student),
     * then maps each to a user row with membership details.
     */
    /**
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

        $packages = Packages::with([
            'user:id,name',
            'packageservice.service:id,name',
            'location:id,name',
            'user.membership.membershipType:id,name',
        ])
            ->whereHas('packageservice', fn ($q) => $q->whereIn('service_id', $serviceIds))
            ->when(!empty($locationIds), fn ($q) => $q->whereIn('packages.location_id', $locationIds))
            ->when($membershipTypeId !== null, function ($q) use ($membershipTypeId) {
                if ($membershipTypeId === 'no_membership') {
                    $q->whereDoesntHave('user.membership');
                } else {
                    $q->whereHas('user.membership', fn ($mq) => $mq->where('membership_type_id', $membershipTypeId));
                }
            })
            ->when($startDate && $endDate, fn ($q) => $q->whereHas('user.membership', fn ($mq) => $mq->whereBetween('assigned_at', [$startDate, $endDate])))
            ->get();

        return $packages->map(function ($package) use ($serviceIds) {
            $user = $package->user;
            $matchingService = $package->packageservice->whereIn('service_id', $serviceIds)->first();
            $membership = $user?->membership;

            return [
                'user_id' => $user?->id,
                'user_name' => $user?->name ?? 'N/A',
                'location' => $package->location?->name ?? 'N/A',
                'service_name' => $matchingService?->service?->name ?? 'N/A',
                'service_status' => $matchingService?->is_consumed ? 'Consumed' : 'Not Consumed',
                'membership_code' => $membership?->code ?? 'No membership',
                'membership_type' => $membership?->membershipType?->name ?? 'No membership',
                'membership_type_id' => $membership?->membershipType?->id ?? 0,
                'assigned_at' => $membership?->assigned_at,
            ];
        });
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
            ->toArray();
    }
}
