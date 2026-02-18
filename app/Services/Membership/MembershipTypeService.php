<?php

namespace App\Services\Membership;

use App\Exceptions\MembershipException;
use App\Models\Membership;
use App\Models\MembershipType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MembershipTypeService
{
    /**
     * Get membership type by ID
     *
     * @param int $id
     * @return MembershipType|null
     */
    public function getMembershipTypeById(int $id): ?MembershipType
    {
        return MembershipType::with(['parent', 'children', 'discounts'])->find($id);
    }

    /**
     * Get all active membership types
     *
     * @param bool $parentsOnly
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveMembershipTypes(bool $parentsOnly = false)
    {
        $cacheKey = $parentsOnly ? 'membership_types_active_parents' : 'membership_types_active_all';
        
        return Cache::remember($cacheKey, 3600, function () use ($parentsOnly) {
            $query = MembershipType::where('active', 1);
            
            if ($parentsOnly) {
                $query->whereNull('parent_id');
            }
            
            return $query->orderBy('name')->get();
        });
    }

    /**
     * Get membership types for patient (includes renewal if applicable)
     *
     * @param int|null $patientId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getMembershipTypesForPatient(?int $patientId = null)
    {
        $parentTypes = $this->getActiveMembershipTypes(true);
        
        if (!$patientId) {
            return $parentTypes;
        }

        $latestMembership = Membership::where('patient_id', $patientId)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$latestMembership || $latestMembership->end_date >= now()->format('Y-m-d')) {
            return $parentTypes;
        }

        $expiredType = MembershipType::find($latestMembership->membership_type_id);
        if (!$expiredType) {
            return $parentTypes;
        }

        $expiredMembershipTypeId = $expiredType->parent_id ?? $expiredType->id;

        $renewalType = MembershipType::where('active', 1)
            ->where('parent_id', $expiredMembershipTypeId)
            ->first();

        if ($renewalType) {
            return $parentTypes->push($renewalType)->sortBy('name')->values();
        }

        return $parentTypes;
    }

    /**
     * Create membership type
     *
     * @param array $data
     * @return MembershipType
     * @throws MembershipException
     */
    public function createMembershipType(array $data): MembershipType
    {
        DB::beginTransaction();
        try {
            if (MembershipType::where('name', $data['name'])->exists()) {
                throw new MembershipException("Membership type with name '{$data['name']}' already exists.");
            }

            if (isset($data['parent_id']) && $data['parent_id']) {
                $parent = MembershipType::find($data['parent_id']);
                if (!$parent) {
                    throw new MembershipException("Parent membership type not found.");
                }
                if (!$parent->active) {
                    throw new MembershipException("Cannot create renewal for inactive parent membership type.");
                }
            }

            $data['created_by'] = Auth::id();
            $data['active'] = 1;

            $membershipType = MembershipType::create($data);

            if (isset($data['discount_ids']) && is_array($data['discount_ids'])) {
                $membershipType->discounts()->sync($data['discount_ids']);
            }

            $this->clearCache();
            DB::commit();

            Log::info('Membership type created', [
                'membership_type_id' => $membershipType->id,
                'name' => $membershipType->name,
                'created_by' => Auth::id()
            ]);

            return $membershipType;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create membership type', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw new MembershipException("Failed to create membership type: " . $e->getMessage());
        }
    }

    /**
     * Update membership type
     *
     * @param int $id
     * @param array $data
     * @return MembershipType
     * @throws MembershipException
     */
    public function updateMembershipType(int $id, array $data): MembershipType
    {
        DB::beginTransaction();
        try {
            $membershipType = MembershipType::find($id);
            if (!$membershipType) {
                throw new MembershipException("Membership type not found.");
            }

            if (isset($data['name']) && $data['name'] !== $membershipType->name) {
                if (MembershipType::where('name', $data['name'])->where('id', '!=', $id)->exists()) {
                    throw new MembershipException("Membership type with name '{$data['name']}' already exists.");
                }
            }

            if (isset($data['parent_id']) && $data['parent_id']) {
                if ($data['parent_id'] == $id) {
                    throw new MembershipException("Membership type cannot be its own parent.");
                }
                
                $parent = MembershipType::find($data['parent_id']);
                if (!$parent) {
                    throw new MembershipException("Parent membership type not found.");
                }
            }

            $data['updated_by'] = Auth::id();
            $membershipType->update($data);

            if (isset($data['discount_ids']) && is_array($data['discount_ids'])) {
                $membershipType->discounts()->sync($data['discount_ids']);
            }

            $this->clearCache();
            DB::commit();

            Log::info('Membership type updated', [
                'membership_type_id' => $id,
                'updated_by' => Auth::id()
            ]);

            return $membershipType->fresh(['parent', 'children', 'discounts']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update membership type', [
                'membership_type_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw new MembershipException("Failed to update membership type: " . $e->getMessage());
        }
    }

    /**
     * Delete membership type
     *
     * @param int $id
     * @return bool
     * @throws MembershipException
     */
    public function deleteMembershipType(int $id): bool
    {
        DB::beginTransaction();
        try {
            $membershipType = MembershipType::find($id);
            if (!$membershipType) {
                throw new MembershipException("Membership type not found.");
            }

            $membershipCount = Membership::where('membership_type_id', $id)->count();
            
            if ($membershipCount > 0) {
                $membershipType->update(['active' => 0, 'updated_by' => Auth::id()]);
                Membership::where('membership_type_id', $id)->update(['active' => 0]);
                
                Log::info('Membership type deactivated (has memberships)', [
                    'membership_type_id' => $id,
                    'membership_count' => $membershipCount
                ]);
                
                $this->clearCache();
                DB::commit();
                return true;
            }

            $membershipType->delete();

            $this->clearCache();
            DB::commit();

            Log::info('Membership type deleted', [
                'membership_type_id' => $id,
                'deleted_by' => Auth::id()
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete membership type', [
                'membership_type_id' => $id,
                'error' => $e->getMessage()
            ]);
            throw new MembershipException("Failed to delete membership type: " . $e->getMessage());
        }
    }

    /**
     * Activate membership type
     *
     * @param int $id
     * @return MembershipType
     * @throws MembershipException
     */
    public function activateMembershipType(int $id): MembershipType
    {
        $membershipType = MembershipType::find($id);
        if (!$membershipType) {
            throw new MembershipException("Membership type not found.");
        }

        $membershipType->update(['active' => 1, 'updated_by' => Auth::id()]);
        Membership::where('membership_type_id', $id)->update(['active' => 1]);

        $this->clearCache();

        Log::info('Membership type activated', [
            'membership_type_id' => $id,
            'activated_by' => Auth::id()
        ]);

        return $membershipType->fresh();
    }

    /**
     * Deactivate membership type
     *
     * @param int $id
     * @return MembershipType
     * @throws MembershipException
     */
    public function deactivateMembershipType(int $id): MembershipType
    {
        $membershipType = MembershipType::find($id);
        if (!$membershipType) {
            throw new MembershipException("Membership type not found.");
        }

        $membershipType->update(['active' => 0, 'updated_by' => Auth::id()]);
        Membership::where('membership_type_id', $id)->update(['active' => 0]);

        $this->clearCache();

        Log::info('Membership type deactivated', [
            'membership_type_id' => $id,
            'deactivated_by' => Auth::id()
        ]);

        return $membershipType->fresh();
    }

    /**
     * Get membership type statistics
     *
     * @param int $id
     * @return array
     */
    public function getMembershipTypeStats(int $id): array
    {
        $total = Membership::where('membership_type_id', $id)->count();
        $available = Membership::where('membership_type_id', $id)
            ->whereNull('patient_id')
            ->where('active', 1)
            ->count();
        $assigned = Membership::where('membership_type_id', $id)
            ->whereNotNull('patient_id')
            ->count();
        $active = Membership::where('membership_type_id', $id)
            ->whereNotNull('patient_id')
            ->where('active', 1)
            ->where('end_date', '>=', now())
            ->count();

        return [
            'total' => $total,
            'available' => $available,
            'assigned' => $assigned,
            'active' => $active,
            'expired' => $assigned - $active,
        ];
    }

    /**
     * Clear all membership type caches
     *
     * @return void
     */
    protected function clearCache(): void
    {
        Cache::forget('membership_types_active_parents');
        Cache::forget('membership_types_active_all');
    }
}
