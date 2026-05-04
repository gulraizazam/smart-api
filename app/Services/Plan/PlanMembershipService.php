<?php

declare(strict_types=1);

namespace App\Services\Plan;

use App\Exceptions\PlanException;
use App\Models\Membership;
use App\Models\MembershipType;
use Illuminate\Support\Facades\DB;

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
        // Always-visible base set: every active parent membership type.
        $parentMemberships = MembershipType::where('active', 1)
            ->whereNull('parent_id')
            ->select('id', 'name', 'amount as price', 'parent_id')
            ->orderBy('name', 'asc')
            ->get();

        $memberships = $parentMemberships;
        $expiredMembershipTypeId = null;

        if ($patientId) {
            // Two corrections vs. the legacy logic:
            //
            //   1. EXPIRED requires a real past `end_date`. The previous
            //      "$latestMembership->end_date < now()" check evaluated
            //      true when `end_date` was NULL — which is the state
            //      every never-activated card lives in (unpaid student
            //      memberships, half-finished assignments). Treating
            //      those as expired surfaced renewal pricing for cards
            //      that never even started, letting the operator hand
            //      out a discount without the patient ever having paid
            //      the regular price. `whereNotNull` + strict `<` closes
            //      that.
            //
            //   2. Look at EVERY expired parent type, not just "the
            //      most recent membership row". A patient who held a
            //      Gold (now expired) and later a Student (also expired)
            //      should see both renewals — the legacy code surfaced
            //      only the latest type's renewal and silently
            //      suppressed the other. `DISTINCT
            //      COALESCE(parent_id, id)` collapses every expired row
            //      to its parent-type id; the IN query then returns
            //      every renewal child for those parents.
            $expiredParentTypeIds = DB::table('memberships')
                ->join('membership_types', 'membership_types.id', '=', 'memberships.membership_type_id')
                ->where('memberships.patient_id', $patientId)
                ->whereNotNull('memberships.end_date')
                ->where('memberships.end_date', '<', now()->format('Y-m-d'))
                ->select(DB::raw('DISTINCT COALESCE(membership_types.parent_id, membership_types.id) AS parent_type_id'))
                ->pluck('parent_type_id')
                ->all();

            if (! empty($expiredParentTypeIds)) {
                $renewalMemberships = MembershipType::where('active', 1)
                    ->whereIn('parent_id', $expiredParentTypeIds)
                    ->select('id', 'name', 'amount as price', 'parent_id')
                    ->get();

                if ($renewalMemberships->isNotEmpty()) {
                    $memberships = $parentMemberships
                        ->concat($renewalMemberships)
                        ->sortBy('name')
                        ->values();
                }

                // Legacy callers (e.g. legacy create-membership.js)
                // read this flat field and pre-select the renewal that
                // matches their state. "Most recent expired type" is
                // the closest single-value semantic.
                $expiredMembershipTypeId = DB::table('memberships')
                    ->join('membership_types', 'membership_types.id', '=', 'memberships.membership_type_id')
                    ->where('memberships.patient_id', $patientId)
                    ->whereNotNull('memberships.end_date')
                    ->where('memberships.end_date', '<', now()->format('Y-m-d'))
                    ->orderByDesc('memberships.created_at')
                    ->value(DB::raw('COALESCE(membership_types.parent_id, membership_types.id)'));
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
