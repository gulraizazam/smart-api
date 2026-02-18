<?php

namespace App\Services\Membership;

use App\Exceptions\MembershipException;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MembershipService
{
    /**
     * Get membership by ID
     *
     * @param int $id
     * @return Membership|null
     */
    public function getMembershipById(int $id): ?Membership
    {
        return Membership::with(['membershipType', 'patient', 'createdBy'])->find($id);
    }

    /**
     * Get membership by code
     *
     * @param string $code
     * @return Membership|null
     */
    public function getMembershipByCode(string $code): ?Membership
    {
        return Membership::with(['membershipType', 'patient'])->where('code', $code)->first();
    }

    /**
     * Check if membership code is available
     *
     * @param string $code
     * @return bool
     */
    public function isCodeAvailable(string $code): bool
    {
        return !Membership::where('code', $code)->exists();
    }

    /**
     * Check if membership code is assigned
     *
     * @param string $code
     * @return bool
     */
    public function isCodeAssigned(string $code): bool
    {
        return Membership::where('code', $code)->whereNotNull('patient_id')->exists();
    }

    /**
     * Get patient's active membership
     *
     * @param int $patientId
     * @return Membership|null
     */
    public function getPatientActiveMembership(int $patientId): ?Membership
    {
        $cacheKey = "patient_active_membership_{$patientId}";
        
        return Cache::remember($cacheKey, 300, function () use ($patientId) {
            // Always pick the latest assigned membership (by assigned_at timestamp)
            return Membership::with(['membershipType'])
                ->where('patient_id', $patientId)
                ->where('active', 1)
                ->where('end_date', '>=', Carbon::today())
                ->orderBy('assigned_at', 'desc')
                ->first();
        });
    }

    /**
     * Get patient's membership history
     *
     * @param int $patientId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPatientMembershipHistory(int $patientId)
    {
        return Membership::with(['membershipType', 'createdBy'])
            ->where('patient_id', $patientId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Check if patient has overlapping membership
     *
     * @param int $patientId
     * @param string $startDate
     * @param string $endDate
     * @param int|null $excludeMembershipId
     * @return bool
     */
    public function hasOverlappingMembership(
        int $patientId,
        string $startDate,
        string $endDate,
        ?int $excludeMembershipId = null
    ): bool {
        $query = Membership::where('patient_id', $patientId)
            ->where('active', 1)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('start_date', [$startDate, $endDate])
                    ->orWhereBetween('end_date', [$startDate, $endDate])
                    ->orWhere(function ($q2) use ($startDate, $endDate) {
                        $q2->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                    });
            });

        if ($excludeMembershipId) {
            $query->where('id', '!=', $excludeMembershipId);
        }

        return $query->exists();
    }

    /**
     * Create membership code
     *
     * @param array $data
     * @return Membership
     * @throws MembershipException
     */
    public function createMembership(array $data): Membership
    {
        DB::beginTransaction();
        try {
            if (!$this->isCodeAvailable($data['code'])) {
                throw new MembershipException("Membership code '{$data['code']}' already exists.");
            }

            $membershipType = MembershipType::find($data['membership_type_id']);
            if (!$membershipType) {
                throw new MembershipException("Membership type not found.");
            }

            if (!$membershipType->active) {
                throw new MembershipException("Cannot create membership for inactive membership type.");
            }

            $data['created_by'] = Auth::id();
            $data['active'] = 1;

            $membership = Membership::create($data);

            DB::commit();

            Log::info('Membership code created', [
                'membership_id' => $membership->id,
                'code' => $membership->code,
                'type' => $membershipType->name,
                'created_by' => Auth::id()
            ]);

            return $membership;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create membership', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw new MembershipException("Failed to create membership: " . $e->getMessage());
        }
    }

    /**
     * Update membership
     *
     * @param int $id
     * @param array $data
     * @return Membership
     * @throws MembershipException
     */
    public function updateMembership(int $id, array $data): Membership
    {
        DB::beginTransaction();
        try {
            $membership = Membership::find($id);
            if (!$membership) {
                throw new MembershipException("Membership not found.");
            }

            if (isset($data['code']) && $data['code'] !== $membership->code) {
                if (!$this->isCodeAvailable($data['code'])) {
                    throw new MembershipException("Membership code '{$data['code']}' already exists.");
                }
            }

            $data['updated_by'] = Auth::id();
            $membership->update($data);

            if ($membership->patient_id) {
                $this->clearPatientMembershipCache($membership->patient_id);
            }

            DB::commit();

            Log::info('Membership updated', [
                'membership_id' => $membership->id,
                'updated_by' => Auth::id()
            ]);

            return $membership->fresh(['membershipType', 'patient']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update membership', [
                'membership_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw new MembershipException("Failed to update membership: " . $e->getMessage());
        }
    }

    /**
     * Delete membership
     *
     * @param int $id
     * @return bool
     * @throws MembershipException
     */
    public function deleteMembership(int $id): bool
    {
        DB::beginTransaction();
        try {
            $membership = Membership::find($id);
            if (!$membership) {
                throw new MembershipException("Membership not found.");
            }

            if ($membership->patient_id) {
                throw new MembershipException("Cannot delete assigned membership. Please cancel it first.");
            }

            $patientId = $membership->patient_id;
            $membership->delete();

            if ($patientId) {
                $this->clearPatientMembershipCache($patientId);
            }

            DB::commit();

            Log::info('Membership deleted', [
                'membership_id' => $id,
                'deleted_by' => Auth::id()
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete membership', [
                'membership_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw new MembershipException("Failed to delete membership: " . $e->getMessage());
        }
    }

    /**
     * Activate membership
     *
     * @param int $id
     * @return Membership
     * @throws MembershipException
     */
    public function activateMembership(int $id): Membership
    {
        $membership = Membership::find($id);
        if (!$membership) {
            throw new MembershipException("Membership not found.");
        }

        $membershipType = $membership->membershipType;
        if (!$membershipType || !$membershipType->active) {
            throw new MembershipException("Cannot activate membership with inactive membership type.");
        }

        $membership->update(['active' => 1, 'updated_by' => Auth::id()]);

        if ($membership->patient_id) {
            $this->clearPatientMembershipCache($membership->patient_id);
        }

        Log::info('Membership activated', [
            'membership_id' => $id,
            'activated_by' => Auth::id()
        ]);

        return $membership->fresh();
    }

    /**
     * Deactivate membership
     *
     * @param int $id
     * @return Membership
     * @throws MembershipException
     */
    public function deactivateMembership(int $id): Membership
    {
        $membership = Membership::find($id);
        if (!$membership) {
            throw new MembershipException("Membership not found.");
        }

        $membership->update(['active' => 0, 'updated_by' => Auth::id()]);

        if ($membership->patient_id) {
            $this->clearPatientMembershipCache($membership->patient_id);
        }

        Log::info('Membership deactivated', [
            'membership_id' => $id,
            'deactivated_by' => Auth::id()
        ]);

        return $membership->fresh();
    }

    /**
     * Get available codes count for membership type
     *
     * @param int $membershipTypeId
     * @return int
     */
    public function getAvailableCodesCount(int $membershipTypeId): int
    {
        $cacheKey = "membership_type_{$membershipTypeId}_available_codes";
        
        return Cache::remember($cacheKey, 1800, function () use ($membershipTypeId) {
            return Membership::where('membership_type_id', $membershipTypeId)
                ->whereNull('patient_id')
                ->where('active', 1)
                ->count();
        });
    }

    /**
     * Get assigned codes count for membership type
     *
     * @param int $membershipTypeId
     * @return int
     */
    public function getAssignedCodesCount(int $membershipTypeId): int
    {
        return Membership::where('membership_type_id', $membershipTypeId)
            ->whereNotNull('patient_id')
            ->count();
    }

    /**
     * Get expiring memberships
     *
     * @param int $days Number of days to look ahead
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getExpiringMemberships(int $days = 30)
    {
        $startDate = Carbon::today();
        $endDate = Carbon::today()->addDays($days);

        return Membership::with(['membershipType', 'patient'])
            ->whereNotNull('patient_id')
            ->where('active', 1)
            ->whereBetween('end_date', [$startDate, $endDate])
            ->orderBy('end_date', 'asc')
            ->get();
    }

    /**
     * Get expired memberships
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getExpiredMemberships()
    {
        return Membership::with(['membershipType', 'patient'])
            ->whereNotNull('patient_id')
            ->where('active', 1)
            ->where('end_date', '<', Carbon::today())
            ->orderBy('end_date', 'desc')
            ->get();
    }

    /**
     * Clear patient membership cache
     *
     * @param int $patientId
     * @return void
     */
    protected function clearPatientMembershipCache(int $patientId): void
    {
        Cache::forget("patient_active_membership_{$patientId}");
    }

    /**
     * Clear membership type cache
     *
     * @param int $membershipTypeId
     * @return void
     */
    public function clearMembershipTypeCache(int $membershipTypeId): void
    {
        Cache::forget("membership_type_{$membershipTypeId}_available_codes");
    }
}
