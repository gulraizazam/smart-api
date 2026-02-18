<?php

namespace App\Services\Membership;

use App\Exceptions\MembershipException;
use App\Helpers\ActivityLogger;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MembershipRenewalService
{
    protected MembershipService $membershipService;
    protected MembershipAssignmentService $assignmentService;

    public function __construct(
        MembershipService $membershipService,
        MembershipAssignmentService $assignmentService
    ) {
        $this->membershipService = $membershipService;
        $this->assignmentService = $assignmentService;
    }

    /**
     * Renew membership for patient
     *
     * @param int $patientId
     * @param string $newCode
     * @param array $options
     * @return Membership
     * @throws MembershipException
     */
    public function renewMembership(int $patientId, string $newCode, array $options = []): Membership
    {
        DB::beginTransaction();
        try {
            $currentMembership = Membership::where('patient_id', $patientId)
                ->orderBy('end_date', 'desc')
                ->first();

            if (!$currentMembership) {
                throw new MembershipException("No existing membership found for this patient.");
            }

            $currentType = $currentMembership->membershipType;
            if (!$currentType) {
                throw new MembershipException("Current membership type not found.");
            }

            $parentTypeId = $currentType->parent_id ?? $currentType->id;

            $renewalType = MembershipType::where('parent_id', $parentTypeId)
                ->where('active', 1)
                ->first();

            if (!$renewalType) {
                $renewalType = $currentType;
            }

            $newMembership = Membership::where('code', $newCode)->first();
            if (!$newMembership) {
                throw new MembershipException("Renewal membership code '{$newCode}' not found.");
            }

            if ($newMembership->patient_id) {
                throw new MembershipException("Renewal membership code '{$newCode}' is already assigned.");
            }

            if ($newMembership->membership_type_id !== $renewalType->id) {
                throw new MembershipException("Renewal code must be of type '{$renewalType->name}'.");
            }

            $startDate = $options['start_date'] ?? Carbon::parse($currentMembership->end_date)->addDay()->format('Y-m-d');
            $endDate = $options['end_date'] ?? Carbon::parse($startDate)->addMonths($renewalType->period)->format('Y-m-d');

            $newMembership->update([
                'patient_id' => $patientId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'assigned_at' => now(),
                'updated_by' => Auth::id(),
            ]);

            $this->logRenewal($currentMembership, $newMembership);

            $this->membershipService->clearPatientMembershipCache($patientId);
            $this->membershipService->clearMembershipTypeCache($renewalType->id);

            $patient = User::find($patientId);
            if ($patient) {
                ActivityLogger::logMembershipRenewed($patient, $currentMembership, $newMembership, $renewalType);
            }

            DB::commit();

            Log::info('Membership renewed', [
                'patient_id' => $patientId,
                'old_membership_id' => $currentMembership->id,
                'new_membership_id' => $newMembership->id,
                'old_code' => $currentMembership->code,
                'new_code' => $newCode,
                'renewed_by' => Auth::id()
            ]);

            return $newMembership->fresh(['membershipType', 'patient']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to renew membership', [
                'patient_id' => $patientId,
                'new_code' => $newCode,
                'error' => $e->getMessage()
            ]);
            throw new MembershipException("Failed to renew membership: " . $e->getMessage());
        }
    }

    /**
     * Get renewal options for patient
     *
     * @param int $patientId
     * @return array
     */
    public function getRenewalOptions(int $patientId): array
    {
        $currentMembership = Membership::where('patient_id', $patientId)
            ->orderBy('end_date', 'desc')
            ->first();

        if (!$currentMembership) {
            return [
                'has_membership' => false,
                'renewal_available' => false,
            ];
        }

        $currentType = $currentMembership->membershipType;
        if (!$currentType) {
            return [
                'has_membership' => true,
                'renewal_available' => false,
            ];
        }

        $parentTypeId = $currentType->parent_id ?? $currentType->id;

        $renewalType = MembershipType::where('parent_id', $parentTypeId)
            ->where('active', 1)
            ->first();

        $isExpired = Carbon::parse($currentMembership->end_date)->isPast();
        $isExpiringSoon = Carbon::parse($currentMembership->end_date)->diffInDays(Carbon::today()) <= 30;

        return [
            'has_membership' => true,
            'renewal_available' => $isExpired || $isExpiringSoon,
            'current_membership' => [
                'id' => $currentMembership->id,
                'code' => $currentMembership->code,
                'type' => $currentType->name,
                'start_date' => $currentMembership->start_date,
                'end_date' => $currentMembership->end_date,
                'is_expired' => $isExpired,
                'days_until_expiry' => Carbon::parse($currentMembership->end_date)->diffInDays(Carbon::today(), false),
            ],
            'renewal_type' => $renewalType ? [
                'id' => $renewalType->id,
                'name' => $renewalType->name,
                'period' => $renewalType->period,
                'amount' => $renewalType->amount,
            ] : null,
            'suggested_start_date' => Carbon::parse($currentMembership->end_date)->addDay()->format('Y-m-d'),
        ];
    }

    /**
     * Get patients with expiring memberships
     *
     * @param int $days
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPatientsWithExpiringMemberships(int $days = 30)
    {
        $startDate = Carbon::today();
        $endDate = Carbon::today()->addDays($days);

        return Membership::with(['membershipType', 'patient'])
            ->whereNotNull('patient_id')
            ->where('active', 1)
            ->whereBetween('end_date', [$startDate, $endDate])
            ->orderBy('end_date', 'asc')
            ->get()
            ->map(function ($membership) {
                $renewalOptions = $this->getRenewalOptions($membership->patient_id);
                return [
                    'membership' => $membership,
                    'renewal_options' => $renewalOptions,
                ];
            });
    }

    /**
     * Log renewal in database
     *
     * @param Membership $oldMembership
     * @param Membership $newMembership
     * @return void
     */
    protected function logRenewal(Membership $oldMembership, Membership $newMembership): void
    {
        DB::table('membership_renewals')->insert([
            'original_membership_id' => $oldMembership->id,
            'renewed_membership_id' => $newMembership->id,
            'renewed_at' => now(),
            'renewed_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Get renewal history for patient
     *
     * @param int $patientId
     * @return array
     */
    public function getRenewalHistory(int $patientId): array
    {
        $memberships = Membership::where('patient_id', $patientId)
            ->with('membershipType')
            ->orderBy('start_date', 'asc')
            ->get();

        $renewals = DB::table('membership_renewals')
            ->whereIn('original_membership_id', $memberships->pluck('id'))
            ->orWhereIn('renewed_membership_id', $memberships->pluck('id'))
            ->get();

        return [
            'memberships' => $memberships,
            'renewals' => $renewals,
            'total_renewals' => $renewals->count(),
        ];
    }

    /**
     * Check if membership is eligible for renewal
     *
     * @param int $membershipId
     * @return bool
     */
    public function isEligibleForRenewal(int $membershipId): bool
    {
        $membership = Membership::find($membershipId);
        
        if (!$membership || !$membership->patient_id) {
            return false;
        }

        $daysUntilExpiry = Carbon::parse($membership->end_date)->diffInDays(Carbon::today(), false);

        return $daysUntilExpiry <= 30;
    }
}
