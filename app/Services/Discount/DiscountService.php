<?php

declare(strict_types=1);

namespace App\Services\Discount;

use App\Helpers\Filters;
use App\Helpers\NodesTree;
use App\Helpers\Widgets\LocationsWidget;
use App\Helpers\Widgets\ServiceWidget;
use App\Models\AuditTrails;
use App\Models\BaseDiscountService;
use App\Models\Discount;
use App\Models\DiscountHasLocations;
use App\Models\GetDiscountService;
use App\Models\Locations;
use App\Models\MembershipType;
use App\Models\Services;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

final class DiscountService
{
    private const FILTER_KEY = 'discounts';

    private const AUDIT_TABLE = 'discounts';

    private const AUDIT_FILLABLE = [
        'name', 'type', 'amount', 'discount_type',
        'pre_days', 'post_days', 'start', 'end',
        'active', 'slug', 'customer_type_id',
    ];

    // ── Create Operations ────────────────────────────────

    public function createSimpleDiscount(array $data): Discount
    {
        $data['amount'] = $data['amount'] ?? '0';

        $discount = Discount::create($data);

        if (!empty($data['roles'])) {
            $discount->roles()->sync($data['roles']);
        }

        AuditTrails::addEventLogger(
            self::AUDIT_TABLE,
            'create',
            $data,
            self::AUDIT_FILLABLE,
            $discount,
        );

        return $discount;
    }

    public function createConfigurableDiscount(array $data): Discount
    {
        return DB::transaction(function () use ($data): Discount {
            $discount = Discount::create([
                'slug'             => $data['slug'] ?? 'default',
                'name'             => $data['name'],
                'type'             => $data['type'],
                'amount'           => '0',
                'discount_type'    => $data['discount_type'] ?? 'Treatment',
                'start'            => $data['start'],
                'end'              => $data['end'],
                'active'           => $data['active'] ?? 0,
                'account_id'       => $data['account_id'],
                'customer_type_id' => $data['customer_type_id'] ?? null,
            ]);

            $this->buildBaseServices($discount->id, $data);
            $this->buildGetServices($discount->id, $data);

            if (!empty($data['roles'])) {
                $discount->roles()->sync($data['roles']);
            }

            AuditTrails::addEventLogger(
                self::AUDIT_TABLE,
                'create',
                $data,
                self::AUDIT_FILLABLE,
                $discount,
            );

            return $discount;
        });
    }

    // ── Update Operations ────────────────────────────────

    public function updateSimpleDiscount(array $data, int $id): Discount
    {
        $discount = Discount::where('account_id', Auth::user()->account_id)->findOrFail($id);
        $oldData  = $discount->toArray();

        $discount->update($data);

        if (isset($data['roles'])) {
            $discount->roles()->sync($data['roles']);
        }

        AuditTrails::EditEventLogger(
            self::AUDIT_TABLE,
            'edit',
            $data,
            self::AUDIT_FILLABLE,
            $oldData,
            $id,
        );

        return $discount->fresh();
    }

    public function updateConfigurableDiscount(array $data, int $id): Discount
    {
        return DB::transaction(function () use ($data, $id): Discount {
            // Tenant guard: 404 a cross-account id before any mutation or
            // the cascade-delete of base/get services below.
            Discount::where('account_id', Auth::user()->account_id)->findOrFail($id);

            Discount::where('id', $id)->update([
                'name'             => $data['name'],
                'discount_type'    => $data['discount_type'] ?? 'Treatment',
                'slug'             => $data['slug'] ?? 'default',
                'type'             => $data['type'],
                'amount'           => $data['amount'] ?? 0,
                'start'            => $data['start'],
                'end'              => $data['end'],
                'active'           => $data['active'] ?? 0,
                'customer_type_id' => $data['customer_type_id'] ?? null,
            ]);

            BaseDiscountService::where('discount_id', $id)->delete();
            GetDiscountService::where('discount_id', $id)->delete();

            $this->buildBaseServices($id, $data, true);
            $this->buildGetServices($id, $data, true);

            $discount = Discount::findOrFail($id);

            if (isset($data['roles'])) {
                $discount->roles()->sync($data['roles']);
            }

            AuditTrails::EditEventLogger(
                self::AUDIT_TABLE,
                'edit',
                $data,
                self::AUDIT_FILLABLE,
                $discount->toArray(),
                $id,
            );

            return $discount;
        });
    }

    // ── Status Operations ────────────────────────────────

    public function toggleStatus(int $id, int $status): bool
    {
        $discount = Discount::getData($id);

        if (!$discount) {
            return false;
        }

        $discount->update(['active' => $status]);

        [$method, $action] = match ($status) {
            1       => ['activeEventLogger', 'active'],
            default => ['InactiveEventLogger', 'inactive'],
        };

        AuditTrails::$method(self::AUDIT_TABLE, $action, self::AUDIT_FILLABLE, $id);

        return true;
    }

    // ── Delete Operations ────────────────────────────────

    public function deleteDiscount(int $id): string
    {
        $discount = Discount::getData($id);

        if (!$discount) {
            return 'Resource not found.';
        }

        if ($discount->hasChildren()) {
            return 'Child records exist, unable to delete resource.';
        }

        $discount->delete();

        AuditTrails::deleteEventLogger(self::AUDIT_TABLE, 'delete', self::AUDIT_FILLABLE, $id);

        return 'Record has been deleted successfully.';
    }

    // ── Datatable ────────────────────────────────────────

    /**
     * @return array{total: int, filter_values: array<string, mixed>}
     */
    public function getDatatableData(array $filters, bool $applyFilter): array
    {
        $where = $this->buildFilterConditions($filters, $applyFilter);
        $canViewInactive = Gate::allows('discounts.list.view_inactive');

        $query = Discount::query()->excludeVouchers();

        if (count($where)) {
            $query->where($where);
            if (!$canViewInactive) {
                $query->active();
            }
        }

        $this->applyTypeGroupFilter($query, $filters, $applyFilter);

        $total = $query->count();

        $filterValues = $this->getFilterValues();

        return [
            'total'         => $total,
            'where'         => $where,
            'filter_values' => $filterValues,
        ];
    }

    /**
     * @return array<int, Discount>
     */
    public function getDatatableRows(
        array $where,
        int $offset,
        int $limit,
        ?string $startDate = null,
        ?string $endDate = null,
        array $filters = [],
    ): Collection {
        $canViewInactive = Gate::allows('discounts.list.view_inactive');

        $query = Discount::query()->excludeVouchers();

        if ($startDate) {
            $query->whereDate('start', '>=', $startDate);
        }
        if ($endDate) {
            $query->whereDate('end', '<=', $endDate);
        }

        if (count($where)) {
            $query->where($where);
            if (!$canViewInactive) {
                $query->active();
            }
        }

        // type_group can't be expressed through the `$where` array form
        // (it's a whereIn over multiple DB enum values), so apply it
        // directly. Persistence already happened in buildFilterConditions.
        $this->applyTypeGroupFilter($query, $filters, false);

        return $query->limit($limit)
            ->offset($offset)
            ->orderByDesc('created_at')
            ->get();
    }

    // ── Create / Edit Form Data ──────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function getCreateFormData(): array
    {
        $accountId = Auth::user()->account_id;

        return [
            'discount_types'  => config('constants.discount_types'),
            'discount_groups' => config('constants.discount_groups'),
            'amount_types'    => config('constants.amount_types'),
            'roles'           => Role::pluck('name', 'id')->toArray(),
            'locations'       => LocationsWidget::generateDropDownArray($accountId),
            'customer_types'  => MembershipType::parentsOnly()
                ->where('active', 1)
                ->pluck('name', 'id')
                ->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getEditFormData(int $id): ?array
    {
        $discount = Discount::getData($id);

        if (!$discount) {
            return null;
        }

        $accountId = Auth::user()->account_id;
        $services  = ServiceWidget::generateServiceArrayDiscount($id, $accountId);
        $locations = Locations::getActiveSorted();

        $discountArray = $discount->toArray();
        if ($discountArray['start']) {
            $discountArray['start'] = $discount->dateFormat($discountArray['start'], 'Y-m-d');
        }
        if ($discountArray['end']) {
            $discountArray['end'] = $discount->dateFormat($discountArray['end'], 'Y-m-d');
        }

        return [
            'discount'               => $discountArray,
            'locations'              => $locations,
            'services'               => $services,
            'base_discount_services' => BaseDiscountService::where('discount_id', $id)->get(),
            'get_discount_services'  => GetDiscountService::where('discount_id', $id)->get(),
            'roles'                  => Role::pluck('name', 'id')->toArray(),
            'selected_roles'         => $discount->roles()->pluck('roles.id')->toArray(),
            'customer_types'         => MembershipType::parentsOnly()
                ->where('active', 1)
                ->pluck('name', 'id')
                ->toArray(),
        ];
    }

    // ── Allocation Operations ────────────────────────────

    /**
     * @return array<string, mixed>
     */
    public function getAllocationData(int $discountId): array
    {
        $discount  = Discount::findOrFail($discountId);
        $accountId = Auth::user()->account_id;

        $location = LocationsWidget::generateDropDownArray($accountId);
        $allocations = DiscountHasLocations::with(['service', 'location.city'])
            ->where('discount_id', $discount->id)
            ->get();

        $responseData = [
            'discount'              => $discount,
            'location'              => $location,
            'discount_has_location' => $allocations,
        ];

        if ($discount->isConfigurable()) {
            $responseData['configurable_services'] = $this->getConfigurableServicesSummary($discountId);
        }

        return $responseData;
    }

    /**
     * @return array<string, mixed>
     */
    public function getServicesForLocation(int $discountId, int $locationId): array
    {
        $discount  = Discount::findOrFail($discountId);
        $accountId = Auth::user()->account_id;

        if ($discount->isConfigurable()) {
            $services = BaseDiscountService::join('services', 'services.id', 'base_discount_services.service_id')
                ->select('services.name', 'services.id')
                ->where('discount_id', $discountId)
                ->take(1)
                ->get()
                ->toArray();
        } elseif ($discount->discount_type === config('constants.Service')) {
            $request     = new \Illuminate\Http\Request(['id' => $locationId]);
            $services    = ServiceWidget::generateServiceArrayArray($request, $accountId);
        } else {
            $request  = new \Illuminate\Http\Request(['id' => $locationId]);
            $services = ServiceWidget::generateServiceArrayConsultancy($request, $accountId);
        }

        return [
            'services'      => $services,
            'locaiton_id_1' => $locationId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getDiscountServicesForWidget(int $discountId): array
    {
        $accountId = Auth::user()->account_id;

        return [
            'services' => ServiceWidget::generateServiceArrayDiscount($discountId, $accountId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getServicesForConfigurable(): array
    {
        $accountId = Auth::user()->account_id;

        return [
            'services' => ServiceWidget::generateServiceArrayDiscount(null, $accountId),
        ];
    }

    /**
     * @return array{records: array, removed_ids: array, message: string, success: bool}
     */
    public function saveAllocations(array $data): array
    {
        $locationId  = (int) $data['location_id'];
        $serviceIds  = $data['service_ids'];
        $discountId  = (int) $data['discount_id'];
        $allocType   = $data['allocation_type'];
        $allocAmount = $data['allocation_amount'];
        $allocSlug   = $data['allocation_slug'] ?? 'default';

        // Check "All Centres" + "All Services" already allocated
        $allCentresId  = Locations::where('slug', 'all')->value('id');
        $allServicesId = Services::where('slug', 'all')->value('id');

        if ($allCentresId && $allServicesId) {
            $globalExists = DiscountHasLocations::where([
                ['location_id', '=', $allCentresId],
                ['service_id', '=', $allServicesId],
                ['discount_id', '=', $discountId],
            ])->exists();

            if ($globalExists) {
                return [
                    'success' => false,
                    'message' => 'Cannot add more allocations. "All Centres" with "All Services" is already allocated for this discount.',
                    'records' => [],
                    'removed_ids' => [],
                ];
            }
        }

        // Filter children when parent is also selected
        $serviceIds = $this->filterRedundantChildren($serviceIds);

        $isAllServices = $allServicesId && in_array($allServicesId, $serviceIds, true);

        // Check parent/child conflicts
        $conflictMessage = $this->checkAllocationConflicts(
            $locationId,
            $discountId,
            $serviceIds,
            $allServicesId,
            $isAllServices,
        );

        if ($conflictMessage) {
            return [
                'success'     => false,
                'message'     => $conflictMessage,
                'records'     => [],
                'removed_ids' => [],
            ];
        }

        // If "All Services", remove existing individual allocations
        $removedIds = [];
        if ($isAllServices) {
            $existing = DiscountHasLocations::where([
                ['location_id', '=', $locationId],
                ['discount_id', '=', $discountId],
                ['service_id', '!=', $allServicesId],
            ])->get();

            $removedIds = $existing->pluck('id')->toArray();

            DiscountHasLocations::where([
                ['location_id', '=', $locationId],
                ['discount_id', '=', $discountId],
                ['service_id', '!=', $allServicesId],
            ])->delete();
        }

        $createdIds     = [];
        $duplicateCount = 0;

        foreach ($serviceIds as $serviceId) {
            $exists = DiscountHasLocations::where([
                ['location_id', '=', $locationId],
                ['service_id', '=', $serviceId],
                ['discount_id', '=', $discountId],
            ])->exists();

            if ($exists) {
                $duplicateCount++;
                continue;
            }

            $record = DiscountHasLocations::create([
                'discount_id' => $discountId,
                'location_id' => $locationId,
                'service_id'  => $serviceId,
                'type'        => $allocType,
                'amount'      => $allocAmount,
                'slug'        => $allocSlug,
            ]);

            $createdIds[] = $record->id;
        }

        if (empty($createdIds)) {
            return [
                'success'     => false,
                'message'     => 'All selected services already exist for this location.',
                'records'     => [],
                'removed_ids' => [],
            ];
        }

        // Eager load relationships in one query to avoid N+1
        $createdRecords = DiscountHasLocations::with(['location.city', 'service'])
            ->whereIn('id', $createdIds)
            ->get()
            ->map(fn (DiscountHasLocations $record) => [
                'id'            => $record->id,
                'location_name' => $record->location->city->name . '-' . $record->location->name,
                'service_name'  => $record->service->name,
                'type'          => $record->type,
                'amount'        => $record->amount,
                'slug'          => $record->slug,
            ])
            ->toArray();

        $message = count($createdRecords) . ' record(s) saved successfully.';
        if ($duplicateCount > 0) {
            $message .= ' ' . $duplicateCount . ' duplicate(s) skipped.';
        }

        return [
            'success'     => true,
            'message'     => $message,
            'records'     => $createdRecords,
            'removed_ids' => $removedIds,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function allocateConfigurable(int $discountId, int $locationId): array
    {
        $discount = Discount::findOrFail($discountId);

        if (!$discount->isConfigurable()) {
            return [
                'success' => false,
                'message' => 'This method is only for configurable discounts.',
            ];
        }

        $location = Locations::findOrFail($locationId);

        $existingAllocation = DiscountHasLocations::where([
            'discount_id' => $discountId,
            'location_id' => $locationId,
        ])->exists();

        if ($existingAllocation) {
            return [
                'success' => false,
                'message' => 'This discount is already allocated to this centre.',
            ];
        }

        $baseService = BaseDiscountService::where('discount_id', $discountId)->first();
        $serviceId   = $baseService?->service_id
            ?? Services::where('slug', 'all')->value('id');

        $record = DiscountHasLocations::create([
            'discount_id' => $discountId,
            'location_id' => $locationId,
            'service_id'  => $serviceId,
            'type'        => null,
            'amount'      => null,
            'slug'        => 'configurable',
        ]);

        $servicesDisplay = $this->buildConfigurableDisplayString($discountId);

        return [
            'success' => true,
            'message' => 'Discount allocated to centre successfully.',
            'record'  => [
                'id'            => $record->id,
                'location_name' => $location->city->name . ' - ' . $location->name,
                'service_name'  => $servicesDisplay,
            ],
        ];
    }

    public function deleteAllocation(int $allocationId): void
    {
        DiscountHasLocations::findOrFail($allocationId)->delete();
    }

    /**
     * @return int Number of deleted allocations
     */
    public function deleteAllocationGroup(array $ids): int
    {
        return DiscountHasLocations::whereIn('id', $ids)->delete();
    }

    // ── Active Discounts (for reports) ───────────────────

    public function getActiveDiscounts(int $accountId): Collection
    {
        return Discount::forAccount($accountId)
            ->currentlyValid()
            ->active()
            ->get();
    }

    public function getActiveDiscountsForReport(int $accountId): Collection
    {
        return Discount::forAccount($accountId)
            ->active()
            ->get();
    }

    // ── Private Helpers ──────────────────────────────────

    private function buildBaseServices(int $discountId, array $data, bool $isEdit = false): void
    {
        $prefix       = $isEdit ? 'edit_' : '';
        $buyMode      = $data[$prefix . 'buy_mode'] ?? 'service';
        $sessionCount = (int) ($data[$prefix . 'sessions_buy'] ?? 0);
        $rawService   = $data[$prefix . 'base_service'] ?? null;

        if ($buyMode === 'category') {
            $categoryIds = is_array($rawService) ? $rawService : [$rawService];
            foreach ($categoryIds as $categoryId) {
                $category = Services::find($categoryId);
                if (!$category) {
                    continue;
                }
                for ($i = 0; $i < $sessionCount; $i++) {
                    BaseDiscountService::create([
                        'discount_id'   => $discountId,
                        'service_id'    => $categoryId,
                        'service_price' => 0,
                        'sessions'      => $sessionCount,
                        'is_category'   => 1,
                        'bundle_id'     => null,
                    ]);
                }
            }
        } else {
            $baseService = Services::find($rawService);
            if (!$baseService) {
                return;
            }
            for ($i = 0; $i < $sessionCount; $i++) {
                BaseDiscountService::create([
                    'discount_id'   => $discountId,
                    'service_id'    => $rawService,
                    'service_price' => $baseService->price,
                    'sessions'      => $sessionCount,
                    'is_category'   => 0,
                    'bundle_id'     => null,
                ]);
            }
        }
    }

    private function buildGetServices(int $discountId, array $data, bool $isEdit = false): void
    {
        $prefix     = $isEdit ? 'edit_' : '';
        $sessions   = $data[$prefix . 'sessions'] ?? [];
        $rawService = $data[$prefix . 'base_service'] ?? null;

        $firstBaseServiceId = is_array($rawService) ? ($rawService[0] ?? null) : $rawService;

        foreach ($sessions as $key => $sessionValue) {
            if (empty($sessionValue)) {
                continue;
            }

            $sameServiceKey = $isEdit ? 'edit_same_service' : 'same_service';
            $discTypeKey    = $isEdit ? 'edit_disc_type' : 'disc_type';
            $serviceNameKey = $isEdit ? 'edit_services_name' : 'services_name';

            $isSameService  = isset($data[$sameServiceKey][$key]) && $data[$sameServiceKey][$key] == '1';
            $discountType   = $data[$discTypeKey][$key] ?? 'complimentory';
            $discountAmount = isset($data['configurable_amount'][$key])
                ? (float) $data['configurable_amount'][$key]
                : 0;

            if ($isSameService) {
                for ($i = 0; $i < (int) $sessionValue; $i++) {
                    GetDiscountService::create([
                        'discount_id'     => $discountId,
                        'service_id'      => $firstBaseServiceId,
                        'same_service'    => 1,
                        'service_price'   => 0,
                        'base_service_id' => $firstBaseServiceId,
                        'sessions'        => 1,
                        'discount_type'   => $discountType,
                        'discount_amount' => $discountAmount,
                        'bundle_id'       => null,
                    ]);
                }
            } else {
                $serviceNameId = $data[$serviceNameKey][$key] ?? null;
                if (empty($serviceNameId)) {
                    continue;
                }
                $service = Services::find($serviceNameId);
                if (!$service) {
                    continue;
                }

                for ($i = 0; $i < (int) $sessionValue; $i++) {
                    GetDiscountService::create([
                        'discount_id'     => $discountId,
                        'service_id'      => $serviceNameId,
                        'same_service'    => 0,
                        'service_price'   => $service->price,
                        'base_service_id' => $firstBaseServiceId,
                        'sessions'        => 1,
                        'discount_type'   => $discountType,
                        'discount_amount' => $discountAmount,
                        'bundle_id'       => null,
                    ]);
                }
            }
        }
    }

    /**
     * @return array{base_services: Collection, get_services: Collection}
     */
    private function getConfigurableServicesSummary(int $discountId): array
    {
        $baseServices = BaseDiscountService::join('services', 'services.id', 'base_discount_services.service_id')
            ->where('discount_id', $discountId)
            ->select('services.id', 'services.name', 'base_discount_services.is_category', DB::raw('COUNT(*) as sessions'))
            ->groupBy('services.id', 'services.name', 'base_discount_services.is_category')
            ->get();

        $getServices = GetDiscountService::join('services', 'services.id', 'get_discount_services.service_id')
            ->where('discount_id', $discountId)
            ->select(
                'services.id',
                'services.name',
                'get_discount_services.discount_type',
                'get_discount_services.discount_amount',
                'get_discount_services.same_service',
                DB::raw('COUNT(*) as sessions'),
            )
            ->groupBy(
                'services.id',
                'services.name',
                'get_discount_services.discount_type',
                'get_discount_services.discount_amount',
                'get_discount_services.same_service',
            )
            ->get();

        return [
            'base_services' => $baseServices,
            'get_services'  => $getServices,
        ];
    }

    private function buildConfigurableDisplayString(int $discountId): string
    {
        $summary  = $this->getConfigurableServicesSummary($discountId);
        $buyParts = [];
        $getParts = [];

        foreach ($summary['base_services'] as $svc) {
            $buyParts[] = 'Buy ' . $svc->sessions . ' ' . $svc->name;
        }

        foreach ($summary['get_services'] as $svc) {
            $discountText = $svc->discount_type === 'complimentory'
                ? 'Free'
                : $svc->discount_amount . '% Off';
            $getParts[] = 'Get ' . $svc->sessions . ' ' . $svc->name . ' (' . $discountText . ')';
        }

        return implode(', ', $buyParts) . ' → ' . implode(', ', $getParts);
    }

    /**
     * @return array<int, int>
     */
    private function filterRedundantChildren(array $serviceIds): array
    {
        $selectedParentIds = Services::whereIn('id', $serviceIds)
            ->whereNotNull('parent_id')
            ->pluck('parent_id')
            ->toArray();

        $parentsInSelection = array_intersect($serviceIds, $selectedParentIds);

        if (!empty($parentsInSelection)) {
            $childrenToRemove = Services::whereIn('parent_id', $parentsInSelection)
                ->whereIn('id', $serviceIds)
                ->pluck('id')
                ->toArray();

            $serviceIds = array_values(array_diff($serviceIds, $childrenToRemove));
        }

        return $serviceIds;
    }

    private function checkAllocationConflicts(
        int $locationId,
        int $discountId,
        array $serviceIds,
        ?int $allServicesId,
        bool $isAllServices,
    ): ?string {
        // Check if "All Services" already exists
        if ($allServicesId) {
            $allServicesExists = DiscountHasLocations::where([
                ['location_id', '=', $locationId],
                ['service_id', '=', $allServicesId],
                ['discount_id', '=', $discountId],
            ])->exists();

            if ($allServicesExists && !$isAllServices) {
                return 'Cannot add individual service. "All Services" is already allocated for this location.';
            }
        }

        // Check if child's parent is already allocated
        $childServices = Services::whereIn('id', $serviceIds)
            ->whereNotNull('parent_id')
            ->get();

        if ($childServices->isNotEmpty()) {
            $parentIds = $childServices->pluck('parent_id')->unique()->toArray();

            $existingParents = DiscountHasLocations::where('location_id', $locationId)
                ->where('discount_id', $discountId)
                ->whereIn('service_id', $parentIds)
                ->with('service')
                ->get();

            if ($existingParents->isNotEmpty()) {
                $parentNames = $existingParents->map(fn($alloc) => $alloc->service->name)->implode(', ');
                return 'Cannot add child service. Parent category "' . $parentNames . '" is already allocated for this location.';
            }
        }

        return null;
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function buildFilterConditions(array $filters, bool $applyFilter): array
    {
        $where    = [];
        $userId   = Auth::id();
        $filename = self::FILTER_KEY;

        // Account filter (always applied)
        $accountId = Auth::user()->account_id;
        if ($accountId) {
            $where[] = ['account_id', '=', $accountId];
            Filters::put($userId, $filename, 'account_id', $accountId);
        } else {
            $this->applyFilterField($where, $filters, $applyFilter, $filename, 'account_id', '=');
        }

        // Simple text/like filters
        $likeFilters = ['name', 'type', 'amount'];
        foreach ($likeFilters as $field) {
            $this->applyFilterField($where, $filters, $applyFilter, $filename, $field, 'like');
        }

        // Exact match filters
        if (hasFilter($filters, 'discount_type')) {
            $where[] = ['discount_type', '=', $filters['discount_type']];
            Filters::put($userId, $filename, 'discount_type', $filters['discount_type']);
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, 'discount_type');
        } elseif (Filters::get($userId, $filename, 'discount_type')) {
            $where[] = ['discount_type', '=', Filters::get($userId, $filename, 'discount_type')];
        }

        // Date range filters
        $this->applyDateRangeFilter($where, $filters, $applyFilter, $filename, 'created_from', 'created_at', '>=', ' 00:00:00');
        $this->applyDateRangeFilter($where, $filters, $applyFilter, $filename, 'created_to', 'created_at', '<=', ' 23:59:59');
        $this->applyDateRangeFilter($where, $filters, $applyFilter, $filename, 'startdate', 'start', '>=');
        $this->applyDateRangeFilter($where, $filters, $applyFilter, $filename, 'enddate', 'end', '<=');

        // Status filter
        if (hasFilter($filters, 'status')) {
            $where[] = ['active', '=', $filters['status']];
            Filters::put($userId, $filename, 'status', $filters['status']);
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, 'status');
        } else {
            $statusVal = Filters::get($userId, $filename, 'status');
            if ($statusVal === 0 || $statusVal === 1 || $statusVal === '0' || $statusVal === '1') {
                $where[] = ['active', '=', $statusVal];
            }
        }

        // type_group sticky-filter persistence — actual whereIn is applied
        // by applyTypeGroupFilter() because the array-where form can't
        // express IN over multiple values.
        if (hasFilter($filters, 'type_group')) {
            Filters::put($userId, $filename, 'type_group', $filters['type_group']);
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, 'type_group');
        }

        return $where;
    }

    /**
     * Apply the SPA's two-mode "type" grouping (Simple = Fixed/Percentage,
     * Configurable = Configurable) to the query. Sticky-filter persistence
     * lives in buildFilterConditions; this just translates the resolved
     * value into a whereIn.
     */
    private function applyTypeGroupFilter(
        \Illuminate\Database\Eloquent\Builder $query,
        array $filters,
        bool $applyFilter,
    ): void {
        $userId   = Auth::id();
        $filename = self::FILTER_KEY;

        $value = null;
        if (hasFilter($filters, 'type_group')) {
            $value = $filters['type_group'];
        } elseif (!$applyFilter) {
            $value = Filters::get($userId, $filename, 'type_group');
        }

        if ($value === 'simple') {
            $query->whereIn('type', ['Fixed', 'Percentage']);
        } elseif ($value === 'configurable') {
            $query->whereIn('type', ['Configurable']);
        }
    }

    private function applyFilterField(
        array &$where,
        array $filters,
        bool $applyFilter,
        string $filename,
        string $field,
        string $operator,
    ): void {
        $userId = Auth::id();

        if (hasFilter($filters, $field)) {
            $value = $operator === 'like' ? '%' . $filters[$field] . '%' : $filters[$field];
            $where[] = [$field, $operator, $value];
            Filters::put($userId, $filename, $field, $filters[$field]);
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, $field);
        } elseif ($stored = Filters::get($userId, $filename, $field)) {
            $value = $operator === 'like' ? '%' . $stored . '%' : $stored;
            $where[] = [$field, $operator, $value];
        }
    }

    private function applyDateRangeFilter(
        array &$where,
        array $filters,
        bool $applyFilter,
        string $filename,
        string $filterKey,
        string $column,
        string $operator,
        string $suffix = '',
    ): void {
        $userId = Auth::id();

        if (hasFilter($filters, $filterKey)) {
            $where[] = [$column, $operator, $filters[$filterKey] . $suffix];
            Filters::put($userId, $filename, $filterKey, $filters[$filterKey] . $suffix);
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, $filterKey);
        } elseif ($stored = Filters::get($userId, $filename, $filterKey)) {
            $where[] = [$column, $operator, $stored];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getFilterValues(): array
    {
        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::user()->account_id);
        $parentGroups->toList($parentGroups, -1);

        return [
            'services'  => $parentGroups->nodeList,
            'locations'  => Locations::getlocation(),
            'status'     => config('constants.status'),
        ];
    }
}
