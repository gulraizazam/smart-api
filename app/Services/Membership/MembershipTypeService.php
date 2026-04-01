<?php

declare(strict_types=1);

namespace App\Services\Membership;

use App\Exceptions\MembershipException;
use App\Helpers\Filters;
use App\Models\Discounts;
use App\Models\Membership;
use App\Models\MembershipType;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

final class MembershipTypeService
{
    // ── Lookups ──────────────────────────────────────────

    public function getMembershipTypeById(int $id): ?MembershipType
    {
        return MembershipType::with(['parent', 'children', 'discounts'])->find($id);
    }

    public function getActiveMembershipTypes(bool $parentsOnly = false): Collection
    {
        $cacheKey = $parentsOnly ? 'membership_types_active_parents' : 'membership_types_active_all';

        return Cache::remember($cacheKey, 3600, function () use ($parentsOnly) {
            $query = MembershipType::active();

            if ($parentsOnly) {
                $query->parentsOnly();
            }

            return $query->orderBy('name')->get();
        });
    }

    public function getMembershipTypesForPatient(?int $patientId = null): Collection
    {
        $parentTypes = $this->getActiveMembershipTypes(parentsOnly: true);

        if (!$patientId) {
            return $parentTypes;
        }

        $latestMembership = Membership::where('patient_id', $patientId)
            ->orderByDesc('created_at')
            ->first();

        if (!$latestMembership || $latestMembership->end_date >= now()->toDateString()) {
            return $parentTypes;
        }

        $expiredType = MembershipType::find($latestMembership->membership_type_id);
        if (!$expiredType) {
            return $parentTypes;
        }

        $expiredMembershipTypeId = $expiredType->parent_id ?? $expiredType->id;

        $renewalType = MembershipType::active()
            ->where('parent_id', $expiredMembershipTypeId)
            ->first();

        if ($renewalType) {
            return $parentTypes->push($renewalType)->sortBy('name')->values();
        }

        return $parentTypes;
    }

    // ── Edit Form Data ──────────────────────────────────

    public function getEditFormData(int $id): array
    {
        $membershipType = MembershipType::with('discounts')->findOrFail($id);

        $parentMemberships = MembershipType::parentsOnly()
            ->active()
            ->where('id', '!=', $id)
            ->pluck('name', 'id');

        $today = Carbon::now()->toDateString();
        $activeDiscounts = Discounts::where('active', 1)
            ->where('discount_type', '!=', 'voucher')
            ->whereDate('start', '<=', $today)
            ->whereDate('end', '>=', $today)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $assignedDiscountIds = $membershipType->discounts->pluck('id')->toArray();

        return [
            'membershipType'     => $membershipType,
            'parentMemberships'  => $parentMemberships,
            'activeDiscounts'    => $activeDiscounts,
            'assignedDiscountIds' => $assignedDiscountIds,
        ];
    }

    // ── Active Types (with renewal logic) ───────────────

    public function getActiveTypesForPatient(?int $patientId): array
    {
        $expiredMembershipTypeId = null;

        if ($patientId) {
            $latestMembership = Membership::where('patient_id', $patientId)
                ->orderByDesc('created_at')
                ->first();

            if ($latestMembership?->end_date < now()->format('Y-m-d')) {
                $expiredType = MembershipType::find($latestMembership->membership_type_id);
                if ($expiredType) {
                    $expiredMembershipTypeId = $expiredType->parent_id ?? $expiredType->id;
                }
            }
        }

        $parentTypes = MembershipType::active()
            ->parentsOnly()
            ->select('id', 'name', 'amount', 'period', 'parent_id')
            ->orderBy('name')
            ->get();

        $membershipTypes = $parentTypes;

        if ($expiredMembershipTypeId) {
            $renewalType = MembershipType::active()
                ->where('parent_id', $expiredMembershipTypeId)
                ->select('id', 'name', 'amount', 'period', 'parent_id')
                ->first();

            if ($renewalType) {
                $membershipTypes = $parentTypes->push($renewalType)->sortBy('name')->values();
            }
        }

        return [
            'membership_types'          => $membershipTypes,
            'expired_membership_type_id' => $expiredMembershipTypeId,
        ];
    }

    // ── CRUD ─────────────────────────────────────────────

    public function createMembershipType(array $data): MembershipType
    {
        DB::beginTransaction();
        try {
            $data['created_by'] = Auth::id();
            $data['account_id'] = Auth::user()->account_id;
            $data['parent_id']  = !empty($data['parent_id']) ? $data['parent_id'] : null;

            $membershipType = MembershipType::create($data);

            $this->clearCache();
            DB::commit();

            Log::info('Membership type created', [
                'membership_type_id' => $membershipType->id,
                'name'               => $membershipType->name,
                'created_by'         => Auth::id(),
            ]);

            return $membershipType;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to create membership type', ['error' => $e->getMessage()]);
            throw new MembershipException('Failed to create membership type: ' . $e->getMessage());
        }
    }

    public function updateMembershipType(int $id, array $data): MembershipType
    {
        DB::beginTransaction();
        try {
            $membershipType = MembershipType::findOrFail($id);

            $membershipType->update([
                'name'       => $data['name'],
                'period'     => $data['period'],
                'amount'     => $data['amount'],
                'parent_id'  => !empty($data['parent_id']) ? $data['parent_id'] : null,
                'updated_by' => Auth::id(),
                'account_id' => Auth::user()->account_id,
            ]);

            if (isset($data['discount_ids']) && is_array($data['discount_ids'])) {
                $membershipType->discounts()->sync($data['discount_ids']);
            }

            $this->clearCache();
            DB::commit();

            Log::info('Membership type updated', [
                'membership_type_id' => $id,
                'updated_by'         => Auth::id(),
            ]);

            return $membershipType->fresh(['parent', 'children', 'discounts']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to update membership type', [
                'membership_type_id' => $id,
                'error'              => $e->getMessage(),
            ]);
            throw new MembershipException('Failed to update membership type: ' . $e->getMessage());
        }
    }

    public function deleteMembershipType(int $id): string
    {
        DB::beginTransaction();
        try {
            $membershipType = MembershipType::findOrFail($id);

            $hasMemberships = Membership::where('membership_type_id', $id)->exists();

            if ($hasMemberships) {
                $membershipType->update(['active' => false]);
                Membership::where('membership_type_id', $id)->update(['active' => false]);

                $this->clearCache();
                DB::commit();

                return 'deactivated';
            }

            $membershipType->delete();
            $this->clearCache();
            DB::commit();

            return 'deleted';
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to delete membership type', [
                'membership_type_id' => $id,
                'error'              => $e->getMessage(),
            ]);
            throw new MembershipException('Failed to delete membership type: ' . $e->getMessage());
        }
    }

    // ── Status Toggle ───────────────────────────────────

    public function toggleStatus(int $id, int $status): bool
    {
        $membershipType = MembershipType::find($id);
        if (!$membershipType) {
            return false;
        }

        $membershipType->update(['active' => (bool) $status]);
        Membership::where('membership_type_id', $id)->update(['active' => (bool) $status]);

        $this->clearCache();

        return true;
    }

    // ── Datatable ───────────────────────────────────────

    public function getDatatableData(array $rawFilters, bool $applyFilter): array
    {
        $where = $this->buildFilters($rawFilters, $applyFilter);

        return [
            'where' => $where,
            'total' => $this->countRecords($where),
        ];
    }

    public function getDatatableRows(array $where, int $offset, int $limit): Collection
    {
        $query = MembershipType::with(['parent', 'children']);

        if (count($where)) {
            $query->where($where);
        }

        if (!Gate::allows('view_inactive_machine_types')) {
            $query->where('membership_types.active', true);
        }

        return $query->orderByDesc('created_at')
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    // ── Stats ───────────────────────────────────────────

    public function getMembershipTypeStats(int $id): array
    {
        $total     = Membership::where('membership_type_id', $id)->count();
        $available = Membership::where('membership_type_id', $id)->unassigned()->active()->count();
        $assigned  = Membership::where('membership_type_id', $id)->assigned()->count();
        $active    = Membership::where('membership_type_id', $id)->assigned()->active()->notExpired()->count();

        return [
            'total'     => $total,
            'available' => $available,
            'assigned'  => $assigned,
            'active'    => $active,
            'expired'   => $assigned - $active,
        ];
    }

    // ── Private Helpers ─────────────────────────────────

    private function buildFilters(array $rawFilters, bool $applyFilter): array
    {
        $filters = getFilters($rawFilters);
        $where   = [];
        $userId  = Auth::id();

        // Name filter
        $this->applyFilter(
            $where,
            $filters,
            $applyFilter,
            'name',
            'membership_types.name',
            'like',
            'membership_types',
            $userId,
        );

        // Status filter
        $this->applyFilter(
            $where,
            $filters,
            $applyFilter,
            'status',
            'membership_types.active',
            '=',
            'membership_types',
            $userId,
        );

        return $where;
    }

    private function applyFilter(
        array &$where,
        array $filters,
        bool $applyFilter,
        string $filterKey,
        string $column,
        string $operator,
        string $filterGroup,
        int $userId,
    ): void {
        $isLike = $operator === 'like';

        if (hasFilter($filters, $filterKey)) {
            $value = $isLike ? '%' . $filters[$filterKey] . '%' : $filters[$filterKey];
            $where[] = [$column, $operator, $value];
            Filters::put($userId, $filterGroup, $filterKey, $filters[$filterKey]);
        } elseif ($applyFilter) {
            Filters::forget($userId, $filterGroup, $filterKey);
        } else {
            $saved = Filters::get($userId, $filterGroup, $filterKey);
            if ($saved !== null) {
                $value = $isLike ? '%' . $saved . '%' : $saved;
                $where[] = [$column, $operator, $value];
            }
        }
    }

    private function countRecords(array $where): int
    {
        $query = DB::table('membership_types');

        if (count($where)) {
            $query->where($where);
        }

        if (!Gate::allows('view_inactive_centres')) {
            $query->where('membership_types.active', true);
        }

        return $query->count();
    }

    private function clearCache(): void
    {
        Cache::forget('membership_types_active_parents');
        Cache::forget('membership_types_active_all');
    }
}
