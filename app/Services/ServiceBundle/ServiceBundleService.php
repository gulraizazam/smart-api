<?php

declare(strict_types=1);

namespace App\Services\ServiceBundle;

use App\Exceptions\ServiceBundleException;
use App\Helpers\ServiceBundleHelper;
use App\Models\AuditTrails;
use App\Models\ServiceBundle;
use App\Models\Services;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class ServiceBundleService
{
    private const TABLE = 'service_bundles';

    private const FILLABLE = [
        'service_id', 'sessions', 'price', 'discount_percentage',
        'sort_number', 'active',
    ];

    // ── Datatable ───────────────────────────────────────

    /**
     * Get service bundles grouped by category (parent service), matching the
     * services listing pattern: [category_row, bundle, bundle, category_row, bundle, …].
     *
     * Within each category, bundles are ordered by their service's sort_number
     * so the sequence automatically mirrors the services list.
     */
    public function getBundlesList(array $filters, int $accountId, bool $canViewInactive): array
    {
        $query = $this->buildBaseQuery($filters, $accountId, $canViewInactive)
            ->with([
                'service:id,name,price,parent_id,sort_number',
                'service.parent:id,name,sort_no',
            ]);

        $bundles = $query->get();

        // Group bundles by parent (category) ID
        $grouped = [];
        foreach ($bundles as $bundle) {
            $parentId = $bundle->service->parent_id ?? 0;
            if (!isset($grouped[$parentId])) {
                $grouped[$parentId] = [
                    'parent'   => $bundle->service->parent ?? null,
                    'children' => [],
                ];
            }
            $grouped[$parentId]['children'][] = $bundle;
        }

        // Sort categories by parent service's sort_no (then by name as fallback)
        uasort($grouped, function ($a, $b) {
            $sortA = $a['parent']?->sort_no ?? PHP_INT_MAX;
            $sortB = $b['parent']?->sort_no ?? PHP_INT_MAX;
            if ($sortA === $sortB) {
                return strcmp($a['parent']?->name ?? '', $b['parent']?->name ?? '');
            }
            return $sortA <=> $sortB;
        });

        // Sort bundles within each category by service sort_number
        foreach ($grouped as &$group) {
            usort($group['children'], function ($a, $b) {
                $sortA = $a->service->sort_number ?? PHP_INT_MAX;
                $sortB = $b->service->sort_number ?? PHP_INT_MAX;
                return $sortA <=> $sortB;
            });
        }
        unset($group);

        // Flatten into interleaved list: category header row, then its bundles
        $result = [];
        foreach ($grouped as $parentId => $group) {
            // Category header row
            $result[] = [
                'is_category'  => true,
                'id'           => $parentId,
                'name'         => $group['parent']?->name ?? 'Uncategorized',
                'bundle_count' => count($group['children']),
            ];

            // Bundle rows
            foreach ($group['children'] as $bundle) {
                $result[] = $bundle;
            }
        }

        return $result;
    }

    // ── CRUD ────────────────────────────────────────────

    /**
     * Create a new service bundle.
     */
    public function createBundle(array $data): ServiceBundle
    {
        $accountId = Auth::user()->account_id;

        $service = $this->findActiveServiceOrFail((int) $data['service_id'], $accountId);

        $bundleData = $this->prepareBundleData($data, $service, $accountId);

        $bundle = ServiceBundle::create($bundleData);

        AuditTrails::addEventLogger(self::TABLE, 'create', $bundleData, self::FILLABLE, $bundle);

        ServiceBundleHelper::clearCache();

        return $bundle;
    }

    /**
     * Bulk create service bundles for multiple services.
     *
     * @param  array<int, int>  $serviceIds
     */
    public function bulkCreate(array $serviceIds, int $sessions, float $discountPercentage): int
    {
        $accountId = Auth::user()->account_id;
        $userId = Auth::id();
        $created = 0;

        $services = Services::whereIn('id', $serviceIds)
            ->where('account_id', $accountId)
            ->where('active', 1)
            ->where('end_node', 1)
            ->get()
            ->keyBy('id');

        DB::beginTransaction();

        try {
            foreach ($serviceIds as $serviceId) {
                $service = $services->get((int) $serviceId);

                if (! $service) {
                    continue;
                }

                $unitPrice = (float) $service->price;
                $totalPrice = $unitPrice * $sessions;
                $bundlePrice = round($totalPrice * (1 - $discountPercentage / 100), 2);

                ServiceBundle::create([
                    'service_id'          => $service->id,
                    'sessions'            => $sessions,
                    'price'               => $bundlePrice,
                    'discount_percentage' => $discountPercentage,
                    'active'              => 1,
                    'account_id'          => $accountId,
                    'created_by'          => $userId,
                    'updated_by'          => $userId,
                ]);

                $created++;
            }

            DB::commit();
            ServiceBundleHelper::clearCache();

            return $created;
        } catch (\Exception $e) {
            DB::rollBack();
            throw ServiceBundleException::operationFailed('Failed to bulk create bundles: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing service bundle.
     */
    public function updateBundle(int $id, array $data): ServiceBundle
    {
        $accountId = Auth::user()->account_id;
        $bundle = $this->findBundleOrFail($id, $accountId);

        $service = $this->findActiveServiceOrFail((int) $data['service_id'], $accountId);

        $oldData = $bundle->toArray();
        $updateData = $this->prepareBundleData($data, $service, $accountId);

        $bundle->update($updateData);

        AuditTrails::EditEventLogger(self::TABLE, 'edit', $updateData, self::FILLABLE, $oldData, $id);

        ServiceBundleHelper::clearCache();

        return $bundle->fresh();
    }

    /**
     * Get bundle data for editing.
     *
     * @return array<string, mixed>
     */
    public function getBundleForEdit(int $id): array
    {
        $accountId = Auth::user()->account_id;
        $bundle = $this->findBundleOrFail($id, $accountId);
        $bundle->load(['service:id,name,price,parent_id', 'service.parent:id,name']);

        return [
            'bundle'   => $bundle,
            'services' => ServiceBundleHelper::getServicesForDropdown(),
        ];
    }

    /**
     * Delete a service bundle.
     */
    public function deleteBundle(int $id): void
    {
        $accountId = Auth::user()->account_id;
        $bundle = $this->findBundleOrFail($id, $accountId);

        if (ServiceBundleHelper::hasChildRecords($id)) {
            throw ServiceBundleException::hasChildRecords();
        }

        $bundle->delete();
        AuditTrails::deleteEventLogger(self::TABLE, 'delete', self::FILLABLE, $id);

        ServiceBundleHelper::clearCache();
    }

    /**
     * Bulk delete bundles by IDs.
     *
     * @param  array<int, int>  $ids
     */
    public function bulkDelete(array $ids, int $accountId): void
    {
        foreach ($ids as $id) {
            $id = (int) $id;

            if ($id <= 0) {
                continue;
            }

            try {
                $bundle = ServiceBundle::where('id', $id)
                    ->where('account_id', $accountId)
                    ->first();

                if (! $bundle || ServiceBundleHelper::hasChildRecords($id)) {
                    continue;
                }

                $bundle->delete();
                AuditTrails::deleteEventLogger(self::TABLE, 'delete', self::FILLABLE, $id);
            } catch (\Exception) {
                continue;
            }
        }

        ServiceBundleHelper::clearCache();
    }

    // ── Status ──────────────────────────────────────────

    /**
     * Update bundle status (active/inactive).
     */
    public function updateStatus(int $id, int $status): ServiceBundle
    {
        $accountId = Auth::user()->account_id;
        $bundle = $this->findBundleOrFail($id, $accountId);

        $bundle->update(['active' => $status]);

        match ($status) {
            0       => AuditTrails::InactiveEventLogger(self::TABLE, 'inactive', self::FILLABLE, $id),
            default => AuditTrails::activeEventLogger(self::TABLE, 'active', self::FILLABLE, $id),
        };

        ServiceBundleHelper::clearCache();

        return $bundle;
    }

    // ── Detail ──────────────────────────────────────────

    /**
     * Get bundle details.
     *
     * @return array<string, mixed>
     */
    public function getBundleDetails(int $id): array
    {
        $accountId = Auth::user()->account_id;
        $bundle = $this->findBundleOrFail($id, $accountId);
        $bundle->load(['service:id,name,price,parent_id', 'service.parent:id,name']);

        return [
            'bundle' => $bundle,
        ];
    }

    // ── Sort ───────────────────────────────────────────

    /**
     * Get categories that have active bundles, for category-level sorting.
     * Each category shows the count of bundles and a preview of bundle names.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCategoriesForSort(int $accountId): array
    {
        $bundles = ServiceBundle::where('account_id', $accountId)
            ->where('active', 1)
            ->with(['service:id,name,parent_id,sort_number', 'service.parent:id,name,sort_no'])
            ->get();

        $grouped = [];

        foreach ($bundles as $bundle) {
            $parentId = $bundle->service->parent_id ?? 0;
            $parentName = $bundle->service->parent->name ?? 'Uncategorized';
            $parentSortNo = $bundle->service->parent->sort_no ?? PHP_INT_MAX;

            if (!isset($grouped[$parentId])) {
                $grouped[$parentId] = [
                    'id'       => $parentId,
                    'name'     => $parentName,
                    'sort_no'  => $parentSortNo,
                    'count'    => 0,
                    'bundles'  => [],
                ];
            }

            $grouped[$parentId]['count']++;
            $grouped[$parentId]['bundles'][] = $bundle->sessions . 'x ' . ($bundle->service->name ?? '');
        }

        // Sort by parent sort_no
        usort($grouped, fn ($a, $b) => $a['sort_no'] <=> $b['sort_no']);

        // Remove internal sort_no from output
        return array_map(function ($cat) {
            unset($cat['sort_no']);
            return $cat;
        }, array_values($grouped));
    }

    /**
     * Save category sort order. Updates the parent service's sort_no
     * so that category order stays consistent with services.
     *
     * @param  array<int, int>  $categoryIds  Parent service IDs in desired order
     */
    public function saveCategorySortOrder(array $categoryIds, int $accountId): bool
    {
        if (empty($categoryIds)) {
            return false;
        }

        DB::transaction(function () use ($categoryIds, $accountId): void {
            foreach ($categoryIds as $position => $parentId) {
                Services::where('id', (int) $parentId)
                    ->where('account_id', $accountId)
                    ->whereNull('parent_id')
                    ->update(['sort_no' => $position]);
            }

            ServiceBundleHelper::clearCache();
        });

        return true;
    }

    // ── Private helpers ─────────────────────────────────

    /**
     * Build the base query for datatable with filters applied.
     */
    private function buildBaseQuery(array $filters, int $accountId, bool $canViewInactive): Builder
    {
        $query = ServiceBundle::query()
            ->where('account_id', $accountId);

        if (! $canViewInactive) {
            $query->where('active', 1);
        }

        // Join service for name/category filtering
        if (! empty($filters['name']) || ! empty($filters['category'])) {
            $query->whereHas('service', function (Builder $q) use ($filters): void {
                if (! empty($filters['name'])) {
                    $q->where('name', 'like', '%' . $filters['name'] . '%');
                }
                if (! empty($filters['category'])) {
                    $q->where('parent_id', '=', (int) $filters['category']);
                }
            });
        }

        // Sessions filter
        if (isset($filters['sessions']) && $filters['sessions'] !== '') {
            $query->where('sessions', '=', (int) $filters['sessions']);
        }

        // Status filter
        if (isset($filters['status']) && in_array($filters['status'], [0, 1, '0', '1'], true)) {
            $query->where('active', '=', $filters['status']);
        }

        return $query;
    }

    /**
     * Find a bundle by ID and account, or throw.
     */
    private function findBundleOrFail(int $id, int $accountId): ServiceBundle
    {
        $bundle = ServiceBundle::where('id', $id)
            ->where('account_id', $accountId)
            ->first();

        if (! $bundle) {
            throw ServiceBundleException::notFound();
        }

        return $bundle;
    }

    /**
     * Find an active end-node service, or throw.
     */
    private function findActiveServiceOrFail(int $serviceId, int $accountId): Services
    {
        $service = Services::where('id', $serviceId)
            ->where('account_id', $accountId)
            ->where('active', 1)
            ->where('end_node', 1)
            ->first();

        if (! $service) {
            throw ServiceBundleException::serviceInactive();
        }

        return $service;
    }

    /**
     * Prepare bundle data array for create/update.
     *
     * @return array<string, mixed>
     */
    private function prepareBundleData(array $data, Services $service, int $accountId): array
    {
        $sessions = (int) $data['sessions'];
        $price = (float) $data['price'];
        $discountPercentage = isset($data['discount_percentage'])
            ? (float) $data['discount_percentage']
            : null;

        return [
            'service_id'          => $service->id,
            'sessions'            => $sessions,
            'price'               => $price,
            'discount_percentage' => $discountPercentage,
            'active'              => 1,
            'account_id'          => $accountId,
            'created_by'          => Auth::id(),
            'updated_by'          => Auth::id(),
        ];
    }
}
