<?php

declare(strict_types=1);

namespace App\Services\Plan;

use App\Exceptions\PlanException;
use App\Models\Membership;
use App\Models\MembershipType;

final class PlanMembershipService
{
    // ──────────────────────────────────────────────────
    //  Membership
    // ──────────────────────────────────────────────────

    /**
     * Get membership types available for a location/patient, including renewal types for expired memberships.
     *
     * @param int $locationId Location ID
     * @param int|null $patientId Optional patient ID to check for expired membership renewals
     * @return array Contains 'memberships' and 'expired_membership_type_id' keys
     */
    public function getMembershipTypes(int $locationId, ?int $patientId = null): array
    {
        $expiredMembershipTypeId = null;

        // Check if patient's latest membership is expired and get its type
        if ($patientId) {
            $latestMembership = Membership::where('patient_id', $patientId)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($latestMembership && $latestMembership->end_date < now()->format('Y-m-d')) {
                $expiredType = MembershipType::find($latestMembership->membership_type_id);
                if ($expiredType) {
                    $expiredMembershipTypeId = $expiredType->parent_id ?? $expiredType->id;
                }
            }
        }

        // Get all parent membership types (always show these)
        $parentMemberships = MembershipType::where('active', 1)
            ->whereNull('parent_id')
            ->select('id', 'name', 'amount as price', 'parent_id')
            ->orderBy('name', 'asc')
            ->get();

        $memberships = $parentMemberships;

        // If patient has an expired membership, add ONLY the renewal for that specific type
        if ($expiredMembershipTypeId) {
            $renewalMembership = MembershipType::where('active', 1)
                ->where('parent_id', $expiredMembershipTypeId)
                ->select('id', 'name', 'amount as price', 'parent_id')
                ->first();

            if ($renewalMembership) {
                $memberships = $parentMemberships->push($renewalMembership)->sortBy('name')->values();
            }
        }

        if ($memberships->isEmpty()) {
            return ['memberships' => [], 'expired_membership_type_id' => null];
        }

        return [
            'memberships' => $memberships,
            'expired_membership_type_id' => $expiredMembershipTypeId,
        ];
    }

    /**
     * Get membership type info (price and name).
     *
     * @param int $membershipTypeId The membership type ID
     * @return array Contains 'net_amount' and 'membership_name' keys
     */
    public function getMembershipInfo(int $membershipTypeId): array
    {
        $membership = MembershipType::where('id', $membershipTypeId)
            ->where('active', 1)
            ->first();

        if (!$membership) {
            throw new PlanException('Membership not found');
        }

        return [
            'net_amount' => (float) $membership->amount,
            'membership_name' => $membership->name,
        ];
    }

    /**
     * Search membership codes by keyword, optionally filtered by membership type.
     *
     * @param string $query Search query string
     * @param int|null $membershipTypeId Optional membership type ID to filter by
     * @return array Contains 'codes' key with matching membership codes
     */
    public function searchMembershipCodes(string $query, ?int $membershipTypeId = null): array
    {
        if (strlen($query) < 2) {
            return ['codes' => []];
        }

        $dbQuery = Membership::where('code', 'like', '%' . $query . '%')
            ->where('active', 1)
            ->whereNull('patient_id');

        if ($membershipTypeId) {
            $membershipType = MembershipType::find($membershipTypeId);

            if ($membershipType && $membershipType->parent_id) {
                $dbQuery->where(function ($q) use ($membershipTypeId, $membershipType) {
                    $q->where('membership_type_id', $membershipTypeId)
                      ->orWhere('membership_type_id', $membershipType->parent_id);
                });
            } else {
                $dbQuery->where('membership_type_id', $membershipTypeId);
            }
        }

        $codes = $dbQuery->select('id', 'code', 'patient_id', 'membership_type_id')
            ->limit(20)
            ->get()
            ->map(fn($item) => [
                    'id' => $item->id,
                    'code' => $item->code,
                    'is_assigned' => !empty($item->patient_id),
                    'patient_id' => $item->patient_id,
                    'membership_type_id' => $item->membership_type_id,
                ]);

        return ['codes' => $codes];
    }
}
