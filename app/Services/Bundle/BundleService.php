<?php

declare(strict_types=1);

namespace App\Services\Bundle;

use App\Exceptions\BundleException;
use App\Helpers\BundleHelper;
use App\Models\AuditTrails;
use App\Models\BundleHasServices;
use App\Models\Bundles;
use App\Models\BundleServicesPriceHistory;
use App\Models\Services;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class BundleService
{
    private const TABLE = 'bundles';

    private const FILLABLE = [
        'name', 'price', 'services_price', 'type', 'start', 'end',
        'apply_discount', 'total_services', 'active', 'tax_treatment_type_id',
    ];

    // ── Datatable ───────────────────────────────────────

    /**
     * Get total records count for datatable pagination.
     */
    public function getTotalRecords(array $filters, int $accountId, bool $canViewInactive): int
    {
        return $this->buildBaseQuery($filters, $accountId, $canViewInactive)->count();
    }

    /**
     * Get paginated bundle records for datatable.
     *
     * @return Collection<int, Bundles>
     */
    public function getBundlesList(array $filters, int $accountId, bool $canViewInactive, int $offset = 0, int $limit = 100): Collection
    {
        return $this->buildBaseQuery($filters, $accountId, $canViewInactive)
            ->orderBy('sort_number', 'asc')
            ->offset($offset)
            ->limit($limit)
            ->get();
    }

    // ── CRUD ────────────────────────────────────────────

    /**
     * Create a new bundle with its services.
     */
    public function createBundle(array $data): Bundles
    {
        $accountId = Auth::user()->account_id;

        $this->validateDateRange($data['start'] ?? null, $data['end'] ?? null);

        DB::beginTransaction();

        try {
            $bundleData = $this->prepareBundleData($data, $accountId);
            $bundle = Bundles::create($bundleData);

            AuditTrails::addEventLogger(self::TABLE, 'create', $bundleData, self::FILLABLE, $bundle);

            if ($this->hasServices($data)) {
                $this->createBundleServices($bundle, $data, $accountId);
            }

            DB::commit();
            BundleHelper::clearCache();

            return $bundle;
        } catch (BundleException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            throw BundleException::operationFailed('Failed to create bundle: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing bundle and its services.
     */
    public function updateBundle(int $id, array $data): Bundles
    {
        $accountId = Auth::user()->account_id;

        $this->validateDateRange($data['start'] ?? null, $data['end'] ?? null);

        $bundle = $this->findBundleOrFail($id, $accountId);

        DB::beginTransaction();

        try {
            $oldData = $bundle->toArray();
            $updateData = $this->prepareBundleData($data, $accountId);

            $bundle->update($updateData);

            AuditTrails::EditEventLogger(self::TABLE, 'edit', $updateData, self::FILLABLE, $oldData, $id);

            // Replace bundle services: delete old, deactivate price history, create new
            BundleHasServices::where('bundle_id', $bundle->id)->delete();

            BundleServicesPriceHistory::where('bundle_id', $bundle->id)
                ->whereNull('effective_to')
                ->update([
                    'effective_to' => Carbon::now()->format('Y-m-d'),
                    'active'       => 0,
                    'updated_by'   => Auth::id(),
                ]);

            if ($this->hasServices($data)) {
                $this->createBundleServices($bundle, $data, $accountId);
            }

            DB::commit();
            BundleHelper::clearCache();

            return $bundle->fresh();
        } catch (BundleException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            throw BundleException::operationFailed('Failed to update bundle: ' . $e->getMessage());
        }
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

        $relationships = BundleHasServices::where('bundle_id', $bundle->id)
            ->select('service_id', 'service_price', 'calculated_price', 'end_node')
            ->get();

        $bundleServices = $relationships->isNotEmpty()
            ? Services::whereIn('id', $relationships->pluck('service_id'))
                ->where('account_id', $accountId)
                ->get()
                ->keyBy('id')
            : collect();

        return [
            'bundle'              => $bundle,
            'services'            => BundleHelper::getServices(),
            'bundle_services'     => $bundleServices,
            'relationships'       => $relationships,
            'tax_treatment_types' => BundleHelper::getTaxTreatmentTypes(),
        ];
    }

    /**
     * Delete a bundle.
     */
    public function deleteBundle(int $id): void
    {
        $accountId = Auth::user()->account_id;
        $bundle = $this->findBundleOrFail($id, $accountId);

        if (BundleHelper::hasChildRecords($id, $accountId)) {
            throw BundleException::hasChildRecords();
        }

        DB::beginTransaction();

        try {
            $bundle->delete();
            BundleHasServices::where('bundle_id', $id)->delete();
            AuditTrails::deleteEventLogger(self::TABLE, 'delete', self::FILLABLE, $id);

            DB::commit();
            BundleHelper::clearCache();
        } catch (\Exception $e) {
            DB::rollBack();
            throw BundleException::operationFailed('Failed to delete bundle: ' . $e->getMessage());
        }
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
                $bundle = Bundles::where('id', $id)
                    ->where('account_id', $accountId)
                    ->first();

                if (! $bundle || BundleHelper::hasChildRecords($id, $accountId)) {
                    continue;
                }

                $bundle->delete();
                BundleHasServices::where('bundle_id', $id)->delete();
                AuditTrails::deleteEventLogger(self::TABLE, 'delete', self::FILLABLE, $id);
            } catch (\Exception) {
                continue;
            }
        }

        BundleHelper::clearCache();
    }

    // ── Status ──────────────────────────────────────────

    /**
     * Update bundle status (active/inactive).
     */
    public function updateStatus(int $id, int $status): Bundles
    {
        $accountId = Auth::user()->account_id;
        $bundle = $this->findBundleOrFail($id, $accountId);

        $bundle->update(['active' => $status]);

        match ($status) {
            0       => AuditTrails::InactiveEventLogger(self::TABLE, 'inactive', self::FILLABLE, $id),
            default => AuditTrails::activeEventLogger(self::TABLE, 'active', self::FILLABLE, $id),
        };

        BundleHelper::clearCache();

        return $bundle;
    }

    // ── Detail ──────────────────────────────────────────

    /**
     * Get bundle details with services.
     *
     * @return array<string, mixed>
     */
    public function getBundleDetails(int $id): array
    {
        $accountId = Auth::user()->account_id;
        $bundle = $this->findBundleOrFail($id, $accountId);

        $relationships = BundleHasServices::where('bundle_id', $bundle->id)
            ->select('service_id', 'service_price', 'calculated_price', 'end_node')
            ->get();

        $bundleServices = $relationships->isNotEmpty()
            ? Services::whereIn('id', $relationships->pluck('service_id'))
                ->where('account_id', $accountId)
                ->get()
                ->keyBy('id')
            : collect();

        return [
            'bundle'          => $bundle,
            'bundle_services' => $bundleServices,
            'relationships'   => $relationships,
        ];
    }

    // ── Sort ───────────────────────────────────────────

    /**
     * Get active bundles for sorting.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBundlesForSort(int $accountId): array
    {
        return Bundles::where('account_id', $accountId)
            ->where('active', 1)
            ->where('type', '!=', 'single')
            ->orderBy('sort_number', 'asc')
            ->get(['id', 'name', 'sort_number'])
            ->toArray();
    }

    /**
     * Save bundle sort order.
     *
     * @param  array<int, int>  $itemIds
     */
    public function saveSortOrder(array $itemIds, int $accountId): bool
    {
        if (empty($itemIds)) {
            return false;
        }

        DB::transaction(function () use ($itemIds, $accountId): void {
            foreach ($itemIds as $position => $itemId) {
                Bundles::where('id', (int) $itemId)
                    ->where('account_id', $accountId)
                    ->update(['sort_number' => $position]);
            }

            BundleHelper::clearCache();
        });

        return true;
    }

    // ── Private helpers ─────────────────────────────────

    /**
     * Build the base query for datatable with filters applied.
     */
    private function buildBaseQuery(array $filters, int $accountId, bool $canViewInactive): Builder
    {
        $query = Bundles::query()
            ->where('type', '!=', 'single')
            ->where('account_id', $accountId);

        if (! $canViewInactive) {
            $query->where('active', 1);
        }

        // Name filter
        if (! empty($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        // Price filter
        if (isset($filters['price']) && $filters['price'] !== '') {
            $query->where('price', '=', $filters['price']);
        }

        // Total services filter
        if (isset($filters['total_services']) && $filters['total_services'] !== '') {
            $query->where('total_services', '=', $filters['total_services']);
        }

        // Date range filters
        $this->applyDateFilters($query, $filters);

        // Status filter
        if (isset($filters['status']) && in_array($filters['status'], [0, 1, '0', '1'], true)) {
            $query->where('active', '=', $filters['status']);
        }

        return $query;
    }

    /**
     * Apply date range filters to query.
     */
    private function applyDateFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['created_from'])) {
            $createdFrom = Carbon::createFromFormat('m/d/Y', $filters['created_from'])->startOfDay()->toDateTimeString();
            $query->where('created_at', '>=', $createdFrom);
        }

        if (! empty($filters['created_to'])) {
            $createdTo = Carbon::createFromFormat('m/d/Y', $filters['created_to'])->endOfDay()->toDateTimeString();
            $query->where('created_at', '<=', $createdTo);
        }

        if (! empty($filters['startdate'])) {
            $query->where('start', '>=', $filters['startdate']);
        }

        if (! empty($filters['enddate'])) {
            $query->where('end', '<=', $filters['enddate']);
        }
    }

    /**
     * Find a bundle by ID and account, or throw.
     */
    private function findBundleOrFail(int $id, int $accountId): Bundles
    {
        $bundle = Bundles::where('id', $id)
            ->where('account_id', $accountId)
            ->first();

        if (! $bundle) {
            throw BundleException::notFound();
        }

        return $bundle;
    }

    /**
     * Prepare bundle data array for create/update.
     *
     * @return array<string, mixed>
     */
    private function prepareBundleData(array $data, int $accountId): array
    {
        $bundleData = [
            'name'                 => $data['name'],
            'price'                => $data['price'],
            // Persist the input mode the operator used to set `price`
            // ('discount' = entered % off, 'net' = typed final price).
            // The DB only stores the final number, so without this the
            // edit form had to guess and consistently surfaced the wrong
            // mode for net-priced bundles.
            'pricing_mode'         => $data['pricing_mode'] ?? null,
            'start'                => $data['start'] ?? null,
            'end'                  => $data['end'] ?? null,
            'apply_discount'       => $data['apply_discount'] ?? 0,
            'tax_treatment_type_id' => $data['tax_treatment_type_id'] ?? BundleHelper::DEFAULT_TAX_TREATMENT_TYPE,
            'account_id'           => $accountId,
            'type'                 => 'multiple',
        ];

        if ($this->hasServices($data)) {
            $bundleData['total_services'] = count($data['service_id']);
            $bundleData['services_price'] = BundleHelper::calculateTotalServicesPrice(
                $data['service_id'],
                $data['service_price']
            );
        }

        return $bundleData;
    }

    /**
     * Create bundle services and price history records.
     */
    private function createBundleServices(Bundles $bundle, array $data, int $accountId): void
    {
        // Batch fetch services to avoid N+1
        $services = Services::whereIn('id', $data['service_id'])
            ->where('account_id', $accountId)
            ->get()
            ->keyBy('id');

        // Build services calculation array
        $servicesCalculation = [];
        foreach ($data['service_id'] as $key => $serviceId) {
            if ($services->has($serviceId)) {
                $servicesCalculation[$key] = [
                    'service_id'      => $serviceId,
                    'service_price'   => (float) $data['service_price'][$key],
                    'calculated_price' => 0.00,
                ];
            }
        }

        // Calculate proportional prices
        $calculatedServices = BundleHelper::calculatePrices(
            $servicesCalculation,
            (float) $bundle->services_price,
            (float) $bundle->price
        );

        $userId = Auth::id();
        $now = Carbon::now()->format('Y-m-d');

        foreach ($data['service_id'] as $key => $serviceId) {
            if (! $services->has($serviceId)) {
                continue;
            }

            $service = $services->get($serviceId);

            BundleHasServices::create([
                'bundle_id'       => $bundle->id,
                'service_id'      => $serviceId,
                'service_price'   => $calculatedServices[$key]['service_price'],
                'calculated_price' => $calculatedServices[$key]['calculated_price'],
                'end_node'        => $service->end_node,
            ]);

            BundleServicesPriceHistory::create([
                'bundle_id'      => $bundle->id,
                'bundle_price'   => $bundle->price,
                'service_id'     => $serviceId,
                'service_price'  => $calculatedServices[$key]['calculated_price'],
                'effective_from' => $now,
                'created_by'     => $userId,
                'updated_by'     => $userId,
                'account_id'     => $accountId,
            ]);
        }
    }

    /**
     * Validate date range.
     */
    private function validateDateRange(?string $start, ?string $end): void
    {
        if (! BundleHelper::isValidDateRange($start, $end)) {
            throw BundleException::invalidDateRange();
        }
    }

    /**
     * Check if data contains valid services.
     */
    private function hasServices(array $data): bool
    {
        return ! empty($data['service_id']) && is_array($data['service_id']);
    }
}
