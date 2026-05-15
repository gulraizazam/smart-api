<?php

declare(strict_types=1);

namespace App\Services\Service;

use App\Exceptions\ServiceException;
use App\Helpers\ServiceBundleHelper;
use App\Helpers\ServiceHelper;
use App\Models\Appointments;
use App\Models\AuditTrails;
use App\Models\BundleHasServices;
use App\Models\Bundles;
use App\Models\BundleServicesPriceHistory;
use App\Models\DiscountHasLocations;
use App\Models\DoctorHasLocations;
use App\Models\InvoiceStatuses;
use App\Models\Invoices;
use App\Models\PackageService;
use App\Models\ServiceHasLocations;
use App\Models\ServiceBundle;
use App\Models\ServiceBundlePriceHistory;
use App\Models\Services;
use App\Models\StaffTargetServices;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class ServiceService
{
    private const TABLE = 'services';

    private const AUDIT_FIELDS = [
        'name', 'slug', 'end_node', 'complimentory', 'active',
        'tax_treatment_type_id', 'parent_id', 'duration', 'price', 'description', 'color',
    ];

    // ──────────────────────────────────────────────
    // Read operations
    // ──────────────────────────────────────────────

    /**
     * Get services list for datatable with parent-child hierarchy.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getServicesList(
        array $filters,
        int $accountId,
        bool $canViewInactive,
    ): array {
        $parents = Services::parentsOnly()
            ->forAccount($accountId)
            ->when(! $canViewInactive, fn ($q) => $q->isActive())
            ->orderBy('sort_no', 'asc')
            ->get();

        $mergedServices = [];

        foreach ($parents as $parent) {
            $nameFilter = $filters['name'] ?? null;
            $statusFilter = $filters['status'] ?? null;
            $parentMatches = ! $nameFilter || str_contains(strtolower($parent->name), strtolower($nameFilter));

            $childQuery = Services::where('parent_id', $parent->id)
                ->when(! $canViewInactive, fn ($q) => $q->isActive())
                ->when($statusFilter !== null, fn ($q) => $q->where('active', $statusFilter));

            if (! $parentMatches) {
                $childQuery->where('name', 'like', '%' . $nameFilter . '%');
            }

            $children = $childQuery->orderBy('sort_number', 'asc')->get();

            if ($parentMatches || $children->isNotEmpty()) {
                $mergedServices[] = $parent->toArray();
                foreach ($children as $child) {
                    $mergedServices[] = $child->toArray();
                }
            }
        }

        return $mergedServices;
    }

    /**
     * Get total records count for pagination.
     */
    public function getTotalRecords(
        array $filters,
        int $accountId,
        bool $canViewInactive,
    ): int {
        return Services::forAccount($accountId)
            ->when(
                ! empty($filters['name']),
                fn ($q) => $q->where('name', 'like', '%' . $filters['name'] . '%'),
            )
            ->when(
                isset($filters['status']),
                fn ($q) => $q->where('active', $filters['status']),
            )
            ->when(! $canViewInactive, fn ($q) => $q->isActive())
            ->count();
    }

    /**
     * Get form data for create/edit modal.
     *
     * @return array<string, mixed>
     */
    public function getFormData(int $accountId, ?int $serviceId = null): array
    {
        $selectTaxTreatmentType = ServiceHelper::DEFAULT_TAX_TREATMENT_TYPE;

        if ($serviceId) {
            $service = Services::findOrFail($serviceId);
            $selectTaxTreatmentType = ($service->tax_treatment_type_id && $service->tax_treatment_type_id !== 1)
                ? $service->tax_treatment_type_id
                : ServiceHelper::DEFAULT_TAX_TREATMENT_TYPE;
        } else {
            $service = new \stdClass();
            $service->duration = null;
            $service->parent_id = null;
        }

        return [
            'parent_services'          => ServiceHelper::getParentServices($accountId),
            'service'                  => $service,
            'durations'                => ServiceHelper::getDurations(),
            'tax_treatment_types'      => ServiceHelper::getTaxTreatmentTypes(),
            'select_tax_treatment_type' => $selectTaxTreatmentType,
        ];
    }

    /**
     * Get service description/instructions by ID.
     */
    public function getServiceDescription(int $id): ?string
    {
        return Services::where('id', $id)->value('description');
    }

    /**
     * Get services for drag-and-drop sorting, grouped by parent.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getServicesForSort(int $accountId): array
    {
        $parents = Services::parentsOnly()
            ->forAccount($accountId)
            ->isActive()
            ->orderBy('sort_no', 'asc')
            ->with(['children' => fn ($q) => $q->isActive()->orderBy('sort_number', 'asc')])
            ->get();

        $grouped = [];
        foreach ($parents as $parent) {
            $grouped[] = [
                'id' => $parent->id,
                'name' => $parent->name,
                'children' => $parent->children->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'parent_id' => $c->parent_id,
                    'sort_number' => $c->sort_number,
                ])->values()->toArray(),
            ];
        }

        return $grouped;
    }

    /**
     * Get service color by ID.
     */
    public function getServiceColor(int $serviceId): string
    {
        if ($serviceId === 0) {
            return '#000000';
        }

        return Services::where('id', $serviceId)->value('color') ?? '#000000';
    }

    /**
     * Check if service has child services.
     */
    public function hasChildServices(int $id, int $accountId): bool
    {
        return Services::where('parent_id', $id)
            ->forAccount($accountId)
            ->exists();
    }

    // ──────────────────────────────────────────────
    // Write operations
    // ──────────────────────────────────────────────

    /**
     * Create a new service with associated bundle.
     */
    public function createService(array $data, int $accountId, int $userId): Services
    {
        $data = ServiceHelper::prepareServiceData($data, $accountId);

        return DB::transaction(function () use ($data, $accountId, $userId): Services {
            $service = Services::create($data);
            $service->update(['sort_no' => $service->id]);

            AuditTrails::addEventLogger(self::TABLE, 'create', $data, self::AUDIT_FIELDS, $service);

            $this->createServiceBundle($service, $data, $accountId, $userId);

            ServiceHelper::clearCache($accountId);

            return $service;
        });
    }

    /**
     * Update an existing service with color inheritance and bundle sync.
     */
    public function updateService(int $id, array $data, int $accountId, int $userId): Services
    {
        $service = Services::where('id', $id)->forAccount($accountId)->first();

        if (! $service) {
            throw ServiceException::notFound($id);
        }

        $this->validateServiceUpdate($service, $data, $id, $accountId);

        $data = ServiceHelper::prepareServiceData($data, $accountId);

        return DB::transaction(function () use ($service, $data, $id, $accountId, $userId): Services {
            $oldData = $service->toArray();
            $oldPrice = (float) ($service->price ?? 0.0);

            $this->applyColorInheritance($service, $data, $id);

            $service->update($data);

            AuditTrails::EditEventLogger(self::TABLE, 'edit', $data, self::AUDIT_FIELDS, $oldData, $id);

            $this->updateServiceBundle($service, $data, $accountId, $userId);

            if ($service->wasChanged('price')) {
                $this->cascadeServiceBundlePricing($service, $oldPrice, $accountId, $userId);
            }

            ServiceHelper::clearCache($accountId);

            return $service->fresh();
        });
    }

    /**
     * Re-price every ServiceBundle whose service price just changed,
     * and log one history row per affected bundle.
     *
     * Sold package lines are untouched — they snapshot price at sale time.
     */
    private function cascadeServiceBundlePricing(
        Services $service,
        float $oldServicePrice,
        int $accountId,
        int $userId,
    ): void {
        $newServicePrice = (float) $service->price;

        $bundles = ServiceBundle::where('service_id', $service->id)
            ->where('account_id', $accountId)
            ->get(['id', 'sessions', 'discount_percentage', 'price']);

        if ($bundles->isEmpty()) {
            return;
        }

        $now = Carbon::now();
        $history = [];

        foreach ($bundles as $bundle) {
            $history[] = [
                'service_bundle_id'   => $bundle->id,
                'service_id'          => $service->id,
                'sessions'            => $bundle->sessions,
                'discount_percentage' => $bundle->discount_percentage,
                'old_service_price'   => $oldServicePrice,
                'new_service_price'   => $newServicePrice,
                'old_bundle_price'    => (float) $bundle->price,
                'new_bundle_price'    => self::computeBundlePrice(
                    $newServicePrice,
                    (int) $bundle->sessions,
                    (float) ($bundle->discount_percentage ?? 0),
                ),
                'changed_by'          => $userId,
                'account_id'          => $accountId,
                'created_at'          => $now,
            ];
        }

        ServiceBundlePriceHistory::insert($history);

        DB::update(
            'UPDATE service_bundles
             SET price = ROUND(? * sessions * (1 - IFNULL(discount_percentage, 0) / 100)),
                 updated_by = ?,
                 updated_at = ?
             WHERE service_id = ?
               AND account_id = ?
               AND deleted_at IS NULL',
            [$newServicePrice, $userId, $now, $service->id, $accountId],
        );

        ServiceBundleHelper::clearCache();
    }

    /**
     * Preview the bundle price changes that would result from a given new service price.
     * Returns an empty array if the service has no bundles.
     *
     * @return array<int, array{service_bundle_id:int, sessions:int, discount_percentage:float, old_bundle_price:float, new_bundle_price:float}>
     */
    public function previewServiceBundleImpact(int $serviceId, float $newServicePrice, int $accountId): array
    {
        $service = Services::where('id', $serviceId)->forAccount($accountId)->first();

        if (! $service) {
            throw ServiceException::notFound($serviceId);
        }

        if ((float) $service->price === $newServicePrice) {
            return [];
        }

        $bundles = ServiceBundle::where('service_id', $serviceId)
            ->where('account_id', $accountId)
            ->get(['id', 'sessions', 'discount_percentage', 'price']);

        return $bundles->map(fn (ServiceBundle $bundle): array => [
            'service_bundle_id'   => (int) $bundle->id,
            'sessions'            => (int) $bundle->sessions,
            'discount_percentage' => (float) ($bundle->discount_percentage ?? 0),
            'old_bundle_price'    => (float) $bundle->price,
            'new_bundle_price'    => self::computeBundlePrice(
                $newServicePrice,
                (int) $bundle->sessions,
                (float) ($bundle->discount_percentage ?? 0),
            ),
        ])->values()->all();
    }

    private static function computeBundlePrice(float $servicePrice, int $sessions, float $discountPercentage): float
    {
        return (float) round($servicePrice * $sessions * (1 - $discountPercentage / 100));
    }

    /**
     * Delete a service after dependency checks.
     *
     * @return array{status: bool, message: string}
     */
    public function deleteService(int $id, int $accountId): array
    {
        $service = Services::find($id);

        if (! $service) {
            throw ServiceException::notFound($id);
        }

        $dependency = $this->checkDependencies($id, $accountId);
        if ($dependency) {
            throw ServiceException::hasDependencies($id, $dependency);
        }

        return DB::transaction(function () use ($service, $id, $accountId): array {
            $service->delete();

            AuditTrails::deleteEventLogger(self::TABLE, 'delete', self::AUDIT_FIELDS, $id);

            $this->deleteServiceBundle($id);

            ServiceHelper::clearCache($accountId);

            return [
                'status'  => true,
                'message' => 'Record has been deleted successfully.',
            ];
        });
    }

    /**
     * Activate a service and its children + associated bundle.
     */
    public function activateService(int $id, int $accountId): bool
    {
        $service = Services::find($id);

        if (! $service) {
            throw ServiceException::notFound($id);
        }

        return DB::transaction(function () use ($service, $id, $accountId): bool {
            Services::where('parent_id', $id)->update(['active' => 1]);
            $service->update(['active' => 1]);

            AuditTrails::activeEventLogger(self::TABLE, 'active', self::AUDIT_FIELDS, $id);

            $this->updateBundleStatus($id, 1);
            $this->updateServiceBundleStatus($id, 1, $accountId);

            ServiceHelper::clearCache($accountId);

            return true;
        });
    }

    /**
     * Deactivate a service (cascade to children if parent) + associated bundle.
     */
    public function deactivateService(int $id, int $accountId): bool
    {
        $service = Services::find($id);

        if (! $service) {
            throw ServiceException::notFound($id);
        }

        return DB::transaction(function () use ($service, $id, $accountId): bool {
            // Use the isParent accessor so both legacy parent_id=0 rows and
            // the normalised NULL rows trigger the cascade. The raw `=== 0`
            // check missed NULL parents and silently dropped child cascade.
            if ($service->isParent) {
                // Parent service: cascade to all children
                $childIds = Services::where('parent_id', $id)->pluck('id')->toArray();
                Services::where('parent_id', $id)->update(['active' => 0]);

                // Deactivate service bundles for all children
                foreach ($childIds as $childId) {
                    $this->updateServiceBundleStatus($childId, 0, $accountId);
                }
            }

            $service->update(['active' => 0]);

            AuditTrails::inactiveEventLogger(self::TABLE, 'inactive', self::AUDIT_FIELDS, $id);

            $this->updateBundleStatus($id, 0);
            $this->updateServiceBundleStatus($id, 0, $accountId);

            ServiceHelper::clearCache($accountId);

            return true;
        });
    }

    /**
     * Save drag-and-drop sort order for children of a given parent.
     *
     * @param  array<int, int>  $itemIds
     */
    public function saveSortOrder(array $itemIds, int $accountId, ?int $parentId = null): bool
    {
        if (empty($itemIds)) {
            return false;
        }

        DB::transaction(function () use ($itemIds, $accountId, $parentId): void {
            $ids = array_map('intval', $itemIds);
            $cases = [];
            $bindings = [];
            foreach ($ids as $sortNo => $id) {
                $cases[] = 'WHEN id = ? THEN ?';
                $bindings[] = $id;
                $bindings[] = $sortNo;
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = 'UPDATE services SET sort_number = CASE ' . implode(' ', $cases) . ' END WHERE id IN (' . $placeholders . ')';
            $bindings = array_merge($bindings, $ids);
            if ($parentId) {
                $sql .= ' AND parent_id = ?';
                $bindings[] = $parentId;
            }

            DB::update($sql, $bindings);

            ServiceHelper::clearCache($accountId);
        });

        return true;
    }

    // ──────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────

    /**
     * Validate constraints before updating a service.
     */
    private function validateServiceUpdate(Services $service, array $data, int $id, int $accountId): void
    {
        // Prevent changing child → parent when appointments exist
        if ($service->parent_id > 0 && ($data['parent_id'] ?? 0) == 0) {
            if (Appointments::where('service_id', $id)->exists()) {
                throw ServiceException::hasAppointments($id);
            }
        }

        // Prevent parent/end_node changes when children exist. Parent services
        // store parent_id as NULL in the DB but the form submits integer 0 for
        // "no parent" — normalise both sides to int before comparing so a
        // parent edit doesn't incorrectly trip `parentChangeNotAllowed`.
        if ($this->hasChildServices($id, $accountId)) {
            $currentParentId = (int) ($service->parent_id ?? 0);
            $submittedParentId = (int) ($data['parent_id'] ?? $currentParentId);
            $parentChanged = $currentParentId !== $submittedParentId;

            $currentEndNode = (int) ($service->end_node ?? 0);
            $submittedEndNode = (int) ($data['end_node'] ?? $currentEndNode);
            $endNodeChanged = $currentEndNode !== $submittedEndNode;

            if ($parentChanged || $endNodeChanged) {
                throw ServiceException::parentChangeNotAllowed($id);
            }
        }
    }

    /**
     * Apply color inheritance rules (parent → children, child ← parent).
     */
    private function applyColorInheritance(Services $service, array &$data, int $id): void
    {
        if (($data['parent_id'] ?? 0) == 0) {
            Services::where('parent_id', $id)->update(['color' => $data['color'] ?? $service->color]);
        } else {
            $parentColor = ServiceHelper::getParentColor((int) $data['parent_id']);
            if ($parentColor) {
                $data['color'] = $parentColor;
            }
        }
    }

    /**
     * Check for dependencies that prevent deletion.
     */
    private function checkDependencies(int $id, int $accountId): ?string
    {
        $checks = [
            'child services'      => fn () => Services::where('parent_id', $id)->forAccount($accountId)->exists(),
            'packages'            => fn () => PackageService::where('service_id', $id)->exists(),
            'discounts'           => fn () => DiscountHasLocations::where('service_id', $id)->exists(),
            'doctor allocations'  => fn () => DoctorHasLocations::where('service_id', $id)->where('is_allocated', 1)->exists(),
            'location assignments' => fn () => ServiceHasLocations::where('service_id', $id)->exists(),
            'appointments'        => fn () => Appointments::where('service_id', $id)->exists(),
            'staff targets'       => fn () => StaffTargetServices::where('service_id', $id)->exists(),
            'paid invoices'       => fn () => $this->hasPaidInvoices($id),
        ];

        foreach ($checks as $dependency => $check) {
            if ($check()) {
                return $dependency;
            }
        }

        return null;
    }

    /**
     * Check if service has paid invoices.
     */
    private function hasPaidInvoices(int $serviceId): bool
    {
        $paidStatus = InvoiceStatuses::where('slug', 'paid')->first();

        if (! $paidStatus) {
            return false;
        }

        return Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
            ->where('invoice_details.service_id', $serviceId)
            ->where('invoices.invoice_status_id', $paidStatus->id)
            ->exists();
    }

    // ──────────────────────────────────────────────
    // Bundle management (private)
    // ──────────────────────────────────────────────

    private function createServiceBundle(Services $service, array $data, int $accountId, int $userId): void
    {
        $price = (float) ($service->price ?? 0.0);

        $bundle = Bundles::create([
            'name'                  => $service->name,
            'price'                 => $price,
            'services_price'        => $price,
            'type'                  => 'single',
            'total_services'        => 1,
            'account_id'            => $accountId,
            'tax_treatment_type_id' => $data['tax_treatment_type_id'] ?? ServiceHelper::DEFAULT_TAX_TREATMENT_TYPE,
        ]);

        BundleHasServices::create([
            'bundle_id'        => $bundle->id,
            'service_id'       => $service->id,
            'service_price'    => $price,
            'calculated_price' => $price,
            'end_node'         => $service->end_node,
        ]);

        BundleServicesPriceHistory::createRecord([
            'bundle_id'      => $bundle->id,
            'bundle_price'   => $price,
            'service_id'     => $service->id,
            'service_price'  => $price,
            'effective_from' => Carbon::now()->format('Y-m-d'),
            'created_by'     => $userId,
            'updated_by'     => $userId,
        ], $accountId);
    }

    private function updateServiceBundle(Services $service, array $data, int $accountId, int $userId): void
    {
        $bundleId = $this->findSingleBundleId($service->id, $accountId);

        if (! $bundleId) {
            return;
        }

        $price = (float) ($service->price ?? 0.0);

        // Deactivate previous price history
        BundleServicesPriceHistory::where('bundle_id', $bundleId)
            ->whereNull('effective_to')
            ->update([
                'effective_to' => Carbon::now()->format('Y-m-d'),
                'active'       => 0,
                'updated_by'   => $userId,
            ]);

        Bundles::where('id', $bundleId)->update([
            'name'                  => $service->name,
            'price'                 => $price,
            'services_price'        => $price,
            'tax_treatment_type_id' => $data['tax_treatment_type_id'] ?? ServiceHelper::DEFAULT_TAX_TREATMENT_TYPE,
        ]);

        BundleHasServices::where('bundle_id', $bundleId)->update([
            'service_price'    => $price,
            'calculated_price' => $price,
            'end_node'         => $service->end_node,
        ]);

        BundleServicesPriceHistory::createRecord([
            'bundle_id'      => $bundleId,
            'bundle_price'   => $price,
            'service_id'     => $service->id,
            'service_price'  => $price,
            'effective_from' => Carbon::now()->format('Y-m-d'),
            'created_by'     => $userId,
            'updated_by'     => $userId,
        ], $accountId);
    }

    private function deleteServiceBundle(int $serviceId): void
    {
        $bundleId = $this->findSingleBundleId($serviceId);

        if (! $bundleId) {
            return;
        }

        Bundles::where('id', $bundleId)->delete();
        BundleHasServices::where('bundle_id', $bundleId)->delete();
    }

    private function updateBundleStatus(int $serviceId, int $status): void
    {
        $bundleId = $this->findSingleBundleId($serviceId);

        if ($bundleId) {
            Bundles::where('id', $bundleId)->update(['active' => $status]);
        }
    }

    /**
     * Auto-enable/disable service bundles when a service is activated/deactivated.
     */
    private function updateServiceBundleStatus(int $serviceId, int $status, int $accountId): void
    {
        ServiceBundle::where('service_id', $serviceId)
            ->where('account_id', $accountId)
            ->update(['active' => $status]);
    }

    /**
     * Find the single-type bundle ID linked to a service.
     */
    private function findSingleBundleId(int $serviceId, ?int $accountId = null): ?int
    {
        $query = Bundles::join('bundle_has_services', 'bundle_has_services.bundle_id', '=', 'bundles.id')
            ->where('bundles.type', 'single')
            ->where('bundle_has_services.service_id', $serviceId);

        if ($accountId !== null) {
            $query->where('bundles.account_id', $accountId);
        }

        return $query->value('bundles.id');
    }
}
