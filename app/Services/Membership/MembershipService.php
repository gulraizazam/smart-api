<?php

declare(strict_types=1);

namespace App\Services\Membership;

use App\Exceptions\MembershipException;
use App\Helpers\ACL;
use App\Helpers\ActivityLogger;
use App\Helpers\Filters;
use App\Models\Appointments;
use App\Models\Discounts;
use App\Models\DoctorHasLocations;
use App\Models\Locations;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\PackageBundles;
use App\Models\Patients;
use App\Models\RoleHasUsers;
use App\Models\StudentVerification;
use App\Models\User;
use App\Models\UserHasLocations;
use App\Services\Reports\Concerns\ParsesDateRange;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Rap2hpoutre\FastExcel\FastExcel;

final class MembershipService
{
    use ParsesDateRange;

    // ── Lookups ──────────────────────────────────────────

    public function getMembershipById(int $id): ?Membership
    {
        return Membership::with(['membershipType', 'patient', 'createdBy'])->find($id);
    }

    public function getMembershipByCode(string $code): ?Membership
    {
        return Membership::with(['membershipType', 'patient'])->where('code', $code)->first();
    }

    public function isCodeAvailable(string $code): bool
    {
        return ! Membership::where('code', $code)->exists();
    }

    public function isCodeAssigned(string $code): bool
    {
        return Membership::where('code', $code)->assigned()->exists();
    }

    public function getPatientActiveMembership(int $patientId): ?Membership
    {
        $cacheKey = "patient_active_membership_{$patientId}";

        return Cache::remember($cacheKey, 300, fn () => Membership::with(['membershipType'])
            ->where('patient_id', $patientId)
            ->active()
            ->notExpired()
            ->orderByDesc('assigned_at')
            ->first());
    }

    public function getPatientMembershipHistory(int $patientId): Collection
    {
        return Membership::with(['membershipType', 'createdBy'])
            ->where('patient_id', $patientId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function hasOverlappingMembership(
        int $patientId,
        string $startDate,
        string $endDate,
        ?int $excludeMembershipId = null,
    ): bool {
        $query = Membership::where('patient_id', $patientId)
            ->active()
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

    // ── Create Form Data ────────────────────────────────

    public function getCreateFormData(): array
    {
        $membershipTypes = MembershipType::parentsOnly()
            ->active()
            ->pluck('name', 'id');

        return ['membershipType' => $membershipTypes];
    }

    // ── Edit Form Data ──────────────────────────────────

    public function getEditFormData(int $id): array
    {
        $membership = $this->scopedMembershipQuery()->findOrFail($id);
        $membershipTypes = MembershipType::parentsOnly()->active()->pluck('name', 'id');

        return [
            'membership' => $membership,
            'membershipType' => $membershipTypes,
        ];
    }

    // ── CRUD ─────────────────────────────────────────────

    public function createMembership(array $data): Membership
    {
        DB::beginTransaction();
        try {
            if (! $this->isCodeAvailable($data['code'])) {
                throw new MembershipException("Membership code '{$data['code']}' already exists.");
            }

            $membershipType = MembershipType::findOrFail($data['membership_type_id']);

            if (! $membershipType->active) {
                throw new MembershipException('Cannot create membership for inactive membership type.');
            }

            $data['created_by'] = Auth::id();
            $data['account_id'] = Auth::user()->account_id;
            $data['active'] = true;

            $membership = Membership::create($data);

            DB::commit();

            Log::info('Membership code created', [
                'membership_id' => $membership->id,
                'code' => $membership->code,
                'type' => $membershipType->name,
                'created_by' => Auth::id(),
            ]);

            return $membership;
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to create membership', ['error' => $e->getMessage()]);
            throw new MembershipException('Failed to create membership: '.$e->getMessage());
        }
    }

    public function updateMembership(int $id, array $data): Membership
    {
        DB::beginTransaction();
        try {
            $membership = $this->scopedMembershipQuery()->findOrFail($id);

            $data['updated_by'] = Auth::id();
            $membership->update($data);

            if ($membership->patient_id) {
                $this->clearPatientMembershipCache($membership->patient_id);
            }

            DB::commit();

            Log::info('Membership updated', [
                'membership_id' => $membership->id,
                'updated_by' => Auth::id(),
            ]);

            return $membership->fresh(['membershipType', 'patient']);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Failed to update membership', [
                'membership_id' => $id,
                'error' => $e->getMessage(),
            ]);
            throw new MembershipException('Failed to update membership: '.$e->getMessage());
        }
    }

    public function deleteMembership(int $id): bool
    {
        $membership = $this->scopedMembershipQuery()->findOrFail($id);
        $membership->delete();

        Log::info('Membership deleted', [
            'membership_id' => $id,
            'deleted_by' => Auth::id(),
        ]);

        return true;
    }

    // ── Status Toggle ───────────────────────────────────

    public function toggleStatus(int $id, int $status): bool
    {
        $membership = $this->scopedMembershipQuery()->findOrFail($id);

        if ($status === 1) {
            $membershipType = MembershipType::find($membership->membership_type_id);
            if (! $membershipType?->active) {
                return false;
            }
        }

        $membership->update(['active' => (bool) $status]);

        if ($membership->patient_id) {
            $this->clearPatientMembershipCache($membership->patient_id);
        }

        return true;
    }

    // ── Cancel Membership ───────────────────────────────

    public function cancelMembership(int $patientId): array
    {
        $membership = Membership::where('patient_id', $patientId)->first();

        if (! $membership) {
            throw new MembershipException('Membership not found');
        }

        $isInactiveAndExpired = $membership->end_date < now()->format('Y-m-d');

        if (! $isInactiveAndExpired) {
            $this->ensureNoRestrictedServices($patientId);
        }

        $membershipCode = $membership->code;
        $isReferral = $membership->is_referral;
        $patient = Patients::find($patientId);
        $membershipType = $membership->membershipType;

        $membership->update([
            'patient_id' => null,
            'start_date' => null,
            'end_date' => null,
            'assigned_at' => null,
        ]);

        $cancelledReferrals = 0;
        if (! $isReferral) {
            $cancelledReferrals = Membership::where('parent_membership_code', $membershipCode)
                ->where('is_referral', true)
                ->update([
                    'patient_id' => null,
                    'start_date' => null,
                    'end_date' => null,
                    'assigned_at' => null,
                ]);
        }

        if ($patient) {
            ActivityLogger::logMembershipCancelled($patient, $membership, $membershipType);
        }

        $this->clearPatientMembershipCache($patientId);

        $message = 'Membership cancelled successfully';
        if ($cancelledReferrals > 0) {
            $message .= ' along with '.$cancelledReferrals.' associated referral(s)';
        }

        return ['message' => $message];
    }

    // ── Upload / Import ─────────────────────────────────

    public function uploadMemberships(UploadedFile $file): void
    {
        $collections = (new FastExcel)->import($file);

        $rows = $collections->map(function ($collection) {
            $data = [];
            foreach ($collection as $key => $value) {
                $convertedKey = strtolower(str_replace(' ', '_', trim($key)));
                $data[$convertedKey] = $value;
            }

            return $data;
        });

        foreach ($rows as $row) {
            if (empty($row['code']) || ! strlen((string) $row['code'])) {
                continue;
            }

            $membershipTypeId = MembershipType::where('name', $row['membership_type'])->value('id');

            if (! $membershipTypeId) {
                continue;
            }

            Membership::updateOrCreate(
                ['code' => $row['code']],
                [
                    'code' => $row['code'],
                    'membership_type_id' => $membershipTypeId,
                    'created_by' => Auth::id(),
                ],
            );
        }
    }

    // ── Sold By Users ───────────────────────────────────

    public function getSoldByUsers(?int $locationId): Collection
    {
        $accountId = Auth::user()->account_id;

        if (empty($locationId)) {
            return User::where('account_id', $accountId)
                ->where('active', 1)
                ->whereIn('user_type_id', [
                    config('constants.doctor_user_id'),
                    config('constants.fdm_user_id'),
                ])
                ->orderBy('name')
                ->pluck('name', 'id');
        }

        $doctorIds = DoctorHasLocations::where('location_id', $locationId)
            ->where('is_allocated', 1)
            ->pluck('user_id')
            ->toArray();

        $locationUserIds = UserHasLocations::where('location_id', $locationId)
            ->pluck('user_id')
            ->toArray();

        $fdmRole = DB::table('roles')->where('name', 'FDM')->first();
        $fdmUserIds = [];

        if ($fdmRole) {
            $roleUserIds = RoleHasUsers::where('role_id', $fdmRole->id)
                ->pluck('user_id')
                ->toArray();
            $fdmUserIds = array_intersect($locationUserIds, $roleUserIds);
        }

        $allUserIds = array_unique(array_merge($doctorIds, $fdmUserIds));

        return User::whereIn('id', $allUserIds)
            ->where('active', 1)
            ->orderBy('name')
            ->pluck('name', 'id');
    }

    // ── Student Verification Details ────────────────────

    public function getStudentVerificationDetails(int $membershipId): array
    {
        $membership = $this->scopedMembershipQuery()->with('membershipType')->findOrFail($membershipId);
        $patient = User::where('account_id', Auth::user()->account_id)->findOrFail($membership->patient_id);

        $studentVerification = StudentVerification::where('membership_id', $membershipId)
            ->orWhere(function ($query) use ($membership) {
                $query->where('patient_id', $membership->patient_id)
                    ->where('membership_type_id', $membership->membership_type_id);
            })
            ->first();

        $documents = [];
        if ($studentVerification && ! empty($studentVerification->document_paths)) {
            $documents = $studentVerification->document_paths;
        }

        $serviceUsage = $this->buildServiceUsageData($membership, $patient);

        return [
            'membership' => [
                'id' => $membership->id,
                'code' => $membership->code,
                'type' => $membership->membershipType?->name ?? 'N/A',
                'start_date' => $membership->start_date,
                'end_date' => $membership->end_date,
                'status' => $membership->active ? 'Active' : 'Expired',
            ],
            'patient' => [
                'id' => $patient->id,
                'unique_id' => 'C-'.$patient->id,
                'name' => $patient->name,
                'email' => $patient->email,
                'phone' => $patient->phone,
            ],
            'verification' => $studentVerification ? [
                'id' => $studentVerification->id,
                'status' => $studentVerification->status,
                'submitted_at' => $studentVerification->submitted_at?->format('M d, Y h:i A'),
                'reviewed_at' => $studentVerification->reviewed_at?->format('M d, Y h:i A'),
            ] : null,
            'documents' => $documents,
            'service_usage' => $serviceUsage,
        ];
    }

    /**
     * Per-membership consumption history: the services this card's member
     * discount was applied to, with the benefit (discount saved). Attribution
     * follows the memberships row — patient_id + membership_type_id → the
     * discounts tied to that type → that patient's discounted plan rows (the
     * same join buildServiceUsageData performs). Account-scoped. An unassigned
     * stock card (no patient) returns empty totals.
     *
     * @return array<string, mixed>
     */
    public function getConsumptionHistory(int $membershipId): array
    {
        $membership = $this->scopedMembershipQuery()->with('membershipType')->findOrFail($membershipId);

        $header = [
            'membership_code' => $membership->code,
            'membership_type' => $membership->membershipType?->name ?? 'N/A',
            'patient_name' => null,
        ];

        if ($membership->patient_id === null) {
            return $header + [
                'total_services' => 0,
                'total_discount_saved' => 0,
                'services' => [],
            ];
        }

        $patient = User::where('account_id', Auth::user()->account_id)->findOrFail($membership->patient_id);
        $header['patient_name'] = $patient->name;

        return $header + $this->buildServiceUsageData($membership, $patient);
    }

    // ── Datatable ───────────────────────────────────────

    public function getDatatableData(array $rawFilters, bool $applyFilter): array
    {
        $filters = getFilters($rawFilters);
        $where = $this->buildFilters($filters, $applyFilter);
        $userCentres = ACL::getUserCentres();

        return [
            'where' => $where,
            'total' => $this->countRecords($where, $filters, $applyFilter, $userCentres),
            'filters' => $filters,
            'userCentres' => $userCentres,
        ];
    }

    public function getDatatableRows(
        array $where,
        int $offset,
        int $limit,
        array $filters,
        bool $applyFilter,
        array $userCentres,
    ): Collection {
        $query = Membership::with(['membershipType', 'patient']);

        if (count($where)) {
            $query->where($where);
        }

        $this->applySpecialFilters($query, $filters, $applyFilter);
        $this->applyCentreAccess($query, $userCentres);

        if (! Gate::allows('view_inactive_machine_types')) {
            $query->where('memberships.active', true);
        }

        return $query->orderByDesc('created_at')
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    public function getDatatableFilterValues(): array
    {
        $accountId = Auth::user()->account_id;

        $users = User::getAllRecords($accountId)->pluck('name', 'id');
        $membershipTypes = MembershipType::active()->pluck('name', 'id');
        $locations = Locations::getActiveSorted(ACL::getUserCentres());
        $soldByUsers = User::where('account_id', $accountId)
            ->where('active', 1)
            ->whereIn('user_type_id', [
                config('constants.doctor_user_id'),
                config('constants.fdm_user_id'),
            ])
            ->orderBy('name')
            ->pluck('name', 'id');

        return [
            'status' => config('constants.status'),
            'users' => $users,
            'membershipType' => $membershipTypes,
            'locations' => $locations,
            'soldByUsers' => $soldByUsers,
        ];
    }

    public function getActiveFilterValues(): array
    {
        $userId = Auth::id();

        return [
            'patient_id' => Filters::get($userId, 'memberships', 'patient_id'),
            'code' => Filters::get($userId, 'memberships', 'code'),
            'membership_type_id' => Filters::get($userId, 'memberships', 'membership_type_id'),
            'status' => Filters::get($userId, 'memberships', 'status'),
            'location_id' => Filters::get($userId, 'memberships', 'location_id'),
            'sold_by' => Filters::get($userId, 'memberships', 'sold_by'),
            'assigned_at' => Filters::get($userId, 'memberships', 'assigned_at'),
            'created_by' => Filters::get($userId, 'memberships', 'created_by'),
        ];
    }

    // ── Expiring / Expired ──────────────────────────────

    public function getExpiringMemberships(int $days = 30): Collection
    {
        return Membership::with(['membershipType', 'patient'])
            ->assigned()
            ->active()
            ->whereBetween('end_date', [Carbon::today(), Carbon::today()->addDays($days)])
            ->orderBy('end_date')
            ->get();
    }

    public function getExpiredMemberships(): Collection
    {
        return Membership::with(['membershipType', 'patient'])
            ->assigned()
            ->active()
            ->expired()
            ->orderByDesc('end_date')
            ->get();
    }

    // ── Code Counts ─────────────────────────────────────

    public function getAvailableCodesCount(int $membershipTypeId): int
    {
        $cacheKey = "membership_type_{$membershipTypeId}_available_codes";

        return Cache::remember($cacheKey, 1800, fn () => Membership::where('membership_type_id', $membershipTypeId)
            ->unassigned()
            ->active()
            ->count());
    }

    public function getAssignedCodesCount(int $membershipTypeId): int
    {
        return Membership::where('membership_type_id', $membershipTypeId)
            ->assigned()
            ->count();
    }

    // ── Cache ───────────────────────────────────────────

    public function clearPatientMembershipCache(int $patientId): void
    {
        Cache::forget("patient_active_membership_{$patientId}");
    }

    public function clearMembershipTypeCache(int $membershipTypeId): void
    {
        Cache::forget("membership_type_{$membershipTypeId}_available_codes");
    }

    // ── Private Helpers ─────────────────────────────────

    private function scopedMembershipQuery(): \Illuminate\Database\Eloquent\Builder
    {
        // `memberships` has no `account_id` column, so tenant ownership is
        // derived from the membership's patient OR its creator being in the
        // caller's account. Closes the cross-tenant IDOR on the by-id
        // mutators without a schema change (coexistence-safe: additive,
        // no shared column touched, crm2 untouched).
        $accountId = Auth::user()->account_id;

        return Membership::query()->where(function ($q) use ($accountId) {
            $q->whereHas('patient', fn ($p) => $p->where('account_id', $accountId))
                ->orWhereHas('createdBy', fn ($c) => $c->where('account_id', $accountId));
        });
    }

    private function ensureNoRestrictedServices(int $patientId): void
    {
        $restrictedServiceNames = ['Gold Membership Card', 'Student Membership Card'];

        $hasRestricted = DB::table('packages')
            ->join('package_services', 'packages.id', '=', 'package_services.package_id')
            ->join('services', 'package_services.service_id', '=', 'services.id')
            ->where('packages.patient_id', $patientId)
            ->whereNull('packages.deleted_at')
            ->whereNull('services.deleted_at')
            ->whereIn('services.name', $restrictedServiceNames)
            ->exists();

        if ($hasRestricted) {
            throw new MembershipException('Membership applied on services, you can not cancel it');
        }
    }

    private function buildServiceUsageData(Membership $membership, User $patient): array
    {
        $membershipTypeDiscountIds = Discounts::where('customer_type_id', $membership->membership_type_id)
            ->pluck('id')
            ->toArray();

        if (empty($membershipTypeDiscountIds)) {
            return [
                'total_services' => 0,
                'total_discount_saved' => 0,
                'services' => [],
            ];
        }

        $usedServices = PackageBundles::whereIn('discount_id', $membershipTypeDiscountIds)
            ->whereHas('package', fn ($q) => $q->where('patient_id', $patient->id))
            ->with(['service', 'bundle', 'package', 'packageservice'])
            ->get();

        $serviceUsage = [];
        $totalDiscountAmount = 0;

        foreach ($usedServices as $service) {
            // Resolve the display name the collision-immune way. `bundle_id` is
            // an overloaded FK (service → services, bundle → bundles,
            // service_bundle → service_bundles) and the id ranges collide, so the
            // old `service ?? bundle` heuristic showed an unrelated row's name
            // (e.g. "CoolGlide - Underarms" over the plan's "CoolGlide - Face",
            // or a wrong name on a service_bundle row). catalogDisplayName() routes
            // by source_type via the typed columns — service_bundle renders as
            // "Nx <underlying service>". The legacy heuristic stays ONLY as the
            // fallback for an un-tagged row predating the typed-column backfill.
            $serviceName = $service->catalogDisplayName()
                ?? $service->service?->name
                ?? $service->bundle?->name
                ?? 'Unknown Service';
            $discountSaved = $service->service_price - $service->tax_including_price;
            $packageService = $service->packageservice->first();
            $isConsumed = $packageService ? (bool) $packageService->is_consumed : false;
            $consumedAt = $packageService?->consumed_at
                ? Carbon::parse($packageService->consumed_at)->format('d/m/y')
                : null;

            $serviceUsage[] = [
                'service_name' => $serviceName,
                'service_price' => $service->service_price,
                // `discount_amount` is the raw stored value — for a Percentage
                // discount it's the PERCENT (e.g. 35), not rupees. The actual
                // money saved is price − net; surface it explicitly so the UI
                // shows "Rs 2,098.25", not a misleading "Rs 35".
                'discount_amount' => $service->discount_price ?? 0,
                'discount_saved' => $discountSaved < 0 ? 0 : (float) $discountSaved,
                'discount_type' => $service->discount_type,
                'net_amount' => $service->tax_including_price,
                'plan_id' => $service->package_id,
                'plan_date' => $service->package?->created_at->format('M d, Y'),
                'is_consumed' => $isConsumed,
                'consumed_at' => $consumedAt,
            ];

            if ($discountSaved > 0) {
                $totalDiscountAmount += $discountSaved;
            }
        }

        return [
            'total_services' => count($serviceUsage),
            'total_discount_saved' => $totalDiscountAmount,
            'services' => $serviceUsage,
        ];
    }

    private function buildFilters(array $filters, bool $applyFilter): array
    {
        $where = [];
        $userId = Auth::id();

        // Code filter
        $this->applySimpleFilter($where, $filters, $applyFilter, 'code', 'memberships.code', 'like', $userId);

        // Membership type filter
        $this->applySimpleFilter($where, $filters, $applyFilter, 'membership_type_id', 'memberships.membership_type_id', '=', $userId);

        // Created by filter
        $this->applySimpleFilter($where, $filters, $applyFilter, 'created_by', 'memberships.created_by', '=', $userId);

        // Assigned filter
        $this->applyAssignedFilter($where, $filters, $applyFilter, $userId);

        // Status filter (active/inactive/expired)
        $this->applyStatusFilter($where, $filters, $applyFilter, $userId);

        // Location filter (handled separately in special filters)
        if (hasFilter($filters, 'location_id')) {
            Filters::put($userId, 'memberships', 'location_id', $filters['location_id']);
        } elseif ($applyFilter) {
            Filters::forget($userId, 'memberships', 'location_id');
        }

        // Sold by filter (handled separately)
        if (hasFilter($filters, 'sold_by')) {
            Filters::put($userId, 'memberships', 'sold_by', $filters['sold_by']);
        } elseif ($applyFilter) {
            Filters::forget($userId, 'memberships', 'sold_by');
        }

        // Assigned at date range
        $this->applyDateRangeFilter($where, $filters, $applyFilter, $userId);

        return $where;
    }

    private function applySimpleFilter(
        array &$where,
        array $filters,
        bool $applyFilter,
        string $filterKey,
        string $column,
        string $operator,
        int $userId,
    ): void {
        $isLike = $operator === 'like';

        if (hasFilter($filters, $filterKey)) {
            $value = $isLike ? '%'.$filters[$filterKey].'%' : $filters[$filterKey];
            $where[] = [$column, $operator, $value];
            Filters::put($userId, 'memberships', $filterKey, $filters[$filterKey]);
        } elseif ($applyFilter) {
            Filters::forget($userId, 'memberships', $filterKey);
        } else {
            $saved = Filters::get($userId, 'memberships', $filterKey);
            if ($saved !== null) {
                $value = $isLike ? '%'.$saved.'%' : $saved;
                $where[] = [$column, $operator, $value];
            }
        }
    }

    private function applyAssignedFilter(array &$where, array $filters, bool $applyFilter, int $userId): void
    {
        $assignedValue = null;

        if (hasFilter($filters, 'assigned')) {
            $assignedValue = $filters['assigned'];
            Filters::put($userId, 'memberships', 'assigned', $assignedValue);
        } elseif ($applyFilter) {
            Filters::forget($userId, 'memberships', 'assigned');
        } else {
            $assignedValue = Filters::get($userId, 'memberships', 'assigned');
        }

        if ($assignedValue !== null) {
            match ((string) $assignedValue) {
                '1' => $where[] = ['memberships.patient_id', '<>', null],
                '0' => $where[] = ['memberships.patient_id', '=', null],
                default => null,
            };
        }
    }

    private function applyStatusFilter(array &$where, array $filters, bool $applyFilter, int $userId): void
    {
        $statusValue = null;

        if (hasFilter($filters, 'status')) {
            $statusValue = $filters['status'];
            Filters::put($userId, 'memberships', 'status', $statusValue);
        } elseif ($applyFilter) {
            Filters::forget($userId, 'memberships', 'status');
        } else {
            $statusValue = Filters::get($userId, 'memberships', 'status');
        }

        if ($statusValue !== null) {
            match ($statusValue) {
                'active' => (function () use (&$where) {
                    $where[] = ['memberships.patient_id', '<>', null];
                    $where[] = ['memberships.end_date', '>=', now()->format('Y-m-d')];
                })(),
                'inactive' => $where[] = ['memberships.patient_id', '=', null],
                'expired' => $where[] = ['memberships.end_date', '<', now()->format('Y-m-d')],
                default => null,
            };
        }
    }

    private function applyDateRangeFilter(array &$where, array $filters, bool $applyFilter, int $userId): void
    {
        $dateRange = null;

        if (hasFilter($filters, 'assigned_at')) {
            $dateRange = $filters['assigned_at'];
            Filters::put($userId, 'memberships', 'assigned_at', $dateRange);
        } elseif ($applyFilter) {
            Filters::forget($userId, 'memberships', 'assigned_at');
        } else {
            $dateRange = Filters::get($userId, 'memberships', 'assigned_at');
        }

        if ($dateRange) {
            [$startDateTime, $endDateTime] = self::parseDateRangeWithTimeBounds($dateRange);
            $where[] = ['memberships.assigned_at', '>=', $startDateTime];
            $where[] = ['memberships.assigned_at', '<=', $endDateTime];
        }
    }

    private function applySpecialFilters(mixed $query, array $filters, bool $applyFilter): void
    {
        // Patient ID filter
        $patientIdFilter = $this->getPatientIdFilter($filters, $applyFilter);
        if ($patientIdFilter !== null) {
            empty($patientIdFilter)
                ? $query->where('memberships.patient_id', '=', -1)
                : $query->whereIn('memberships.patient_id', $patientIdFilter);
        }

        // Location filter
        $locationFilter = $this->getLocationFilter($filters, $applyFilter);
        if ($locationFilter !== null) {
            empty($locationFilter)
                ? $query->where('memberships.patient_id', '=', -1)
                : $query->whereIn('memberships.patient_id', $locationFilter);
        }

        // Sold by filter
        $soldByFilter = $this->getSoldByFilter($filters, $applyFilter);
        if ($soldByFilter !== null) {
            empty($soldByFilter)
                ? $query->where('memberships.id', '=', -1)
                : $query->whereIn('memberships.id', $soldByFilter);
        }
    }

    private function applyCentreAccess(mixed $query, array $userCentres): void
    {
        $isSuperAdmin = Auth::user()->hasRole('Super-Admin');

        if (! empty($userCentres)) {
            if ($isSuperAdmin) {
                $query->where(function ($q) use ($userCentres) {
                    $q->whereNull('memberships.patient_id')
                        ->orWhereExists(function ($sub) use ($userCentres) {
                            $sub->select(DB::raw(1))
                                ->from('appointments')
                                ->whereColumn('appointments.patient_id', 'memberships.patient_id')
                                ->whereIn('appointments.location_id', $userCentres);
                        });
                });
            } else {
                $query->whereNotNull('memberships.patient_id')
                    ->whereExists(function ($sub) use ($userCentres) {
                        $sub->select(DB::raw(1))
                            ->from('appointments')
                            ->whereColumn('appointments.patient_id', 'memberships.patient_id')
                            ->whereIn('appointments.location_id', $userCentres);
                    });
            }
        } elseif (! $isSuperAdmin) {
            $query->whereNotNull('memberships.patient_id');
        }
    }

    private function getPatientIdFilter(array $filters, bool $applyFilter): ?array
    {
        $userId = Auth::id();
        $patientId = null;

        if (hasFilter($filters, 'patient_id')) {
            $patientId = $filters['patient_id'];
            Filters::put($userId, 'memberships', 'patient_id', $patientId);
        } elseif ($applyFilter) {
            Filters::forget($userId, 'memberships', 'patient_id');
        } else {
            $patientId = Filters::get($userId, 'memberships', 'patient_id');
        }

        return empty($patientId) ? null : [$patientId];
    }

    private function getLocationFilter(array $filters, bool $applyFilter): ?array
    {
        $userId = Auth::id();
        $locationId = null;

        if (hasFilter($filters, 'location_id')) {
            $locationId = $filters['location_id'];
        } elseif (! $applyFilter) {
            $locationId = Filters::get($userId, 'memberships', 'location_id');
        }

        if (empty($locationId)) {
            return null;
        }

        return Appointments::where('location_id', $locationId)
            ->whereNotNull('patient_id')
            ->distinct()
            ->pluck('patient_id')
            ->toArray();
    }

    private function getSoldByFilter(array $filters, bool $applyFilter): ?array
    {
        $userId = Auth::id();
        $soldBy = null;

        if (hasFilter($filters, 'sold_by')) {
            $soldBy = $filters['sold_by'];
        } elseif (! $applyFilter) {
            $soldBy = Filters::get($userId, 'memberships', 'sold_by');
        }

        if (empty($soldBy)) {
            return null;
        }

        return DB::table('memberships')
            ->join('package_bundles', 'memberships.id', '=', 'package_bundles.membership_code_id')
            ->join('package_services', 'package_bundles.id', '=', 'package_services.package_bundle_id')
            ->where('package_services.sold_by', $soldBy)
            ->distinct()
            ->pluck('memberships.id')
            ->toArray();
    }

    private function countRecords(array $where, array $filters, bool $applyFilter, array $userCentres): int
    {
        $query = DB::table('memberships');

        if (count($where)) {
            $query->where($where);
        }

        // Apply special filters to count query
        $patientIdFilter = $this->getPatientIdFilter($filters, $applyFilter);
        if ($patientIdFilter !== null) {
            empty($patientIdFilter)
                ? $query->where('memberships.patient_id', '=', -1)
                : $query->whereIn('memberships.patient_id', $patientIdFilter);
        }

        $locationFilter = $this->getLocationFilter($filters, $applyFilter);
        if ($locationFilter !== null) {
            empty($locationFilter)
                ? $query->where('memberships.patient_id', '=', -1)
                : $query->whereIn('memberships.patient_id', $locationFilter);
        }

        $soldByFilter = $this->getSoldByFilter($filters, $applyFilter);
        if ($soldByFilter !== null) {
            empty($soldByFilter)
                ? $query->where('memberships.id', '=', -1)
                : $query->whereIn('memberships.id', $soldByFilter);
        }

        if (! Gate::allows('view_inactive_centres')) {
            $query->where('memberships.active', true);
        }

        // Centre access for count
        $isSuperAdmin = Auth::user()->hasRole('Super-Admin');

        if (! empty($userCentres)) {
            if ($isSuperAdmin) {
                $query->where(function ($q) use ($userCentres) {
                    $q->whereNull('memberships.patient_id')
                        ->orWhereExists(function ($sub) use ($userCentres) {
                            $sub->select(DB::raw(1))
                                ->from('appointments')
                                ->whereColumn('appointments.patient_id', 'memberships.patient_id')
                                ->whereIn('appointments.location_id', $userCentres);
                        });
                });
            } else {
                $query->whereNotNull('memberships.patient_id')
                    ->whereExists(function ($sub) use ($userCentres) {
                        $sub->select(DB::raw(1))
                            ->from('appointments')
                            ->whereColumn('appointments.patient_id', 'memberships.patient_id')
                            ->whereIn('appointments.location_id', $userCentres);
                    });
            }
        } elseif (! $isSuperAdmin) {
            $query->whereNotNull('memberships.patient_id');
        }

        return $query->count();
    }
}
