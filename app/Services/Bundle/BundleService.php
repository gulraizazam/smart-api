<?php

namespace App\Services\Bundle;

use App\Models\Bundles;
use App\Models\Services;
use App\Models\BundleHasServices;
use App\Models\BundleServicesPriceHistory;
use App\Models\AuditTrails;
use App\Helpers\BundleHelper;
use App\Helpers\Filters;
use App\Exceptions\BundleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Carbon;

class BundleService
{
    protected static $_table = 'bundles';
    protected static $_fillable = ['name', 'price', 'services_price', 'type', 'start', 'end', 'apply_discount', 'total_services', 'active', 'tax_treatment_type_id', 'bundle_type'];

    /**
     * Get paginated bundles for datatable
     */
    public function getDatatableRecords(Request $request): array
    {
        $accountId = Auth::user()->account_id;
        $filters = getFilters($request->all());
        $applyFilter = checkFilters($filters, 'bundles');

        $bulkDeletePerformed = false;

        // Handle bulk delete
        if (hasFilter($filters, 'delete')) {
            $this->bulkDelete(explode(',', $filters['delete']), $accountId);
            $bulkDeletePerformed = true;
        }

        // Get total records
        $totalRecords = $this->getTotalRecords($request, $accountId, $applyFilter);

        [$displayLength, $displayStart, $pages, $page] = getPaginationElement($request, $totalRecords);
        [$orderBy, $order] = getSortBy($request);

        // Get records
        $bundles = $this->getRecords($request, $displayStart, $displayLength, $accountId, $applyFilter);

        // Format records
        $formattedBundles = $bundles->map(function ($bundle) {
            return BundleHelper::formatForDatatable($bundle);
        });

        $result = [
            'data' => $formattedBundles,
            'permissions' => [
                'edit' => Gate::allows('packages_edit'),
                'delete' => Gate::allows('packages_destroy'),
                'active' => Gate::allows('packages_active'),
                'inactive' => Gate::allows('packages_inactive'),
                'details' => Gate::allows('packages_manage'),
            ],
            'active_filters' => Filters::all(Auth::user()->id, 'bundles'),
            'filter_values' => BundleHelper::getFilterValues(),
            'meta' => [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $displayLength,
                'total' => $totalRecords,
                'sort' => $order,
            ],
        ];

        // Only add status and message when bulk delete was performed
        if ($bulkDeletePerformed) {
            $result['status'] = true;
            $result['message'] = 'Records has been deleted successfully!';
        }

        return $result;
    }

    /**
     * Get total records count
     */
    protected function getTotalRecords(Request $request, int $accountId, bool $applyFilter): int
    {
        $where = $this->buildFilters($request, $accountId, $applyFilter);
        $query = Bundles::where($where);

        if (!Gate::allows('view_inactive_packages')) {
            $query->where('active', 1);
        }

        return $query->count();
    }

    /**
     * Get records with pagination
     */
    protected function getRecords(Request $request, int $offset, int $limit, int $accountId, bool $applyFilter)
    {
        $where = $this->buildFilters($request, $accountId, $applyFilter);
        $query = Bundles::where($where);

        if (!Gate::allows('view_inactive_packages')) {
            $query->where('active', 1);
        }

        return $query->limit($limit)
            ->offset($offset)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Build filter conditions
     */
    protected function buildFilters(Request $request, int $accountId, bool $applyFilter): array
    {
        $where = [];
        $filters = getFilters($request->all());
        $userId = Auth::user()->id;

        // Exclude single type bundles
        $where[] = ['type', '!=', 'single'];

        // Account filter
        $where[] = ['account_id', '=', $accountId];
        Filters::put($userId, 'bundles', 'account_id', $accountId);

        // Name filter
        if (hasFilter($filters, 'name')) {
            $where[] = ['name', 'like', '%' . $filters['name'] . '%'];
            Filters::put($userId, 'bundles', 'name', $filters['name']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, 'bundles', 'name');
            } elseif ($savedName = Filters::get($userId, 'bundles', 'name')) {
                $where[] = ['name', 'like', '%' . $savedName . '%'];
            }
        }

        // Price filter
        if (hasFilter($filters, 'price')) {
            $where[] = ['price', '=', $filters['price']];
            Filters::put($userId, 'bundles', 'price', $filters['price']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, 'bundles', 'price');
            } elseif ($savedPrice = Filters::get($userId, 'bundles', 'price')) {
                $where[] = ['price', '=', $savedPrice];
            }
        }

        // Total services filter
        if (hasFilter($filters, 'total_services')) {
            $where[] = ['total_services', '=', $filters['total_services']];
            Filters::put($userId, 'bundles', 'total_services', $filters['total_services']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, 'bundles', 'total_services');
            } elseif ($savedTotal = Filters::get($userId, 'bundles', 'total_services')) {
                $where[] = ['total_services', '=', $savedTotal];
            }
        }

        // Apply discount filter
        if (hasFilter($filters, 'apply_discount') || (isset($filters['apply_discount']) && in_array($filters['apply_discount'], [0, 1, '0', '1']))) {
            $where[] = ['apply_discount', '=', $filters['apply_discount']];
            Filters::put($userId, 'bundles', 'apply_discount', $filters['apply_discount']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, 'bundles', 'apply_discount');
            } elseif (Filters::get($userId, 'bundles', 'apply_discount') !== null) {
                $where[] = ['apply_discount', '=', Filters::get($userId, 'bundles', 'apply_discount')];
            }
        }

        // Date range filters
        $this->applyDateFilters($where, $filters, $userId, $applyFilter);

        // Status filter
        if (hasFilter($filters, 'status')) {
            $where[] = ['active', '=', $filters['status']];
            Filters::put($userId, 'bundles', 'status', $filters['status']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, 'bundles', 'status');
            } elseif (in_array(Filters::get($userId, 'bundles', 'status'), [0, 1, '0', '1'], true)) {
                $where[] = ['active', '=', Filters::get($userId, 'bundles', 'status')];
            }
        }

        return $where;
    }

    /**
     * Apply date filters
     */
    protected function applyDateFilters(array &$where, array $filters, int $userId, bool $applyFilter): void
    {
        // Created from
        if (hasFilter($filters, 'created_from')) {
            $createdFrom = Carbon::createFromFormat('m/d/Y', $filters['created_from'])->startOfDay()->toDateTimeString();
            $where[] = ['created_at', '>=', $createdFrom];
            Filters::put($userId, 'bundles', 'created_from', $filters['created_from']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, 'bundles', 'created_from');
            } elseif ($saved = Filters::get($userId, 'bundles', 'created_from')) {
                $createdFrom = Carbon::createFromFormat('m/d/Y', $saved)->startOfDay()->toDateTimeString();
                $where[] = ['created_at', '>=', $createdFrom];
            }
        }

        // Created to
        if (hasFilter($filters, 'created_to')) {
            $createdTo = Carbon::createFromFormat('m/d/Y', $filters['created_to'])->endOfDay()->toDateTimeString();
            $where[] = ['created_at', '<=', $createdTo];
            Filters::put($userId, 'bundles', 'created_to', $filters['created_to']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, 'bundles', 'created_to');
            } elseif ($saved = Filters::get($userId, 'bundles', 'created_to')) {
                $createdTo = Carbon::createFromFormat('m/d/Y', $saved)->endOfDay()->toDateTimeString();
                $where[] = ['created_at', '<=', $createdTo];
            }
        }

        // Start date
        if (hasFilter($filters, 'startdate')) {
            $where[] = ['start', '>=', $filters['startdate']];
            Filters::put($userId, 'bundles', 'start', $filters['startdate']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, 'bundles', 'start');
            } elseif ($saved = Filters::get($userId, 'bundles', 'start')) {
                $where[] = ['start', '>=', $saved];
            }
        }

        // End date
        if (hasFilter($filters, 'enddate')) {
            $where[] = ['end', '<=', $filters['enddate']];
            Filters::put($userId, 'bundles', 'end', $filters['enddate']);
        } else {
            if ($applyFilter) {
                Filters::forget($userId, 'bundles', 'end');
            } elseif (Filters::get($userId, 'bundles', 'end') !== null) {
                $where[] = ['end', '<=', Filters::get($userId, 'bundles', 'end')];
            }
        }
    }

    /**
     * Create a simple bundle
     */
    public function createBundle(array $data): Bundles
    {
        $accountId = Auth::user()->account_id;

        // Validate date range
        if (!BundleHelper::isValidDateRange($data['start'] ?? null, $data['end'] ?? null)) {
            throw BundleException::invalidDateRange();
        }

        DB::beginTransaction();

        try {
            // Prepare bundle data
            $bundleData = [
                'name' => $data['name'],
                'price' => $data['price'],
                'start' => $data['start'] ?? null,
                'end' => $data['end'] ?? null,
                'apply_discount' => $data['apply_discount'] ?? 0,
                'tax_treatment_type_id' => $data['tax_treatment_type_id'] ?? BundleHelper::DEFAULT_TAX_TREATMENT_TYPE,
                'account_id' => $accountId,
                'type' => 'multiple',
            ];

            // Calculate services price and count
            if (!empty($data['service_id']) && is_array($data['service_id'])) {
                $bundleData['total_services'] = count($data['service_id']);
                $bundleData['services_price'] = BundleHelper::calculateTotalServicesPrice(
                    $data['service_id'],
                    $data['service_price']
                );
            }

            // Create bundle
            $bundle = Bundles::create($bundleData);

            // Log audit trail
            AuditTrails::addEventLogger(self::$_table, 'create', $bundleData, self::$_fillable, $bundle);

            // Create bundle services
            if (!empty($data['service_id']) && is_array($data['service_id'])) {
                $this->createBundleServices($bundle, $data, $accountId);
            }

            DB::commit();
            BundleHelper::clearCache();

            return $bundle;

        } catch (\Exception $e) {
            DB::rollBack();
            throw BundleException::operationFailed('Failed to create bundle: ' . $e->getMessage());
        }
    }

    /**
     * Create bundle services and price history
     */
    protected function createBundleServices(Bundles $bundle, array $data, int $accountId): void
    {
        // Batch fetch services
        $services = Services::whereIn('id', $data['service_id'])
            ->where('account_id', $accountId)
            ->get()
            ->keyBy('id');

        // Build services calculation array
        $servicesCalculation = [];
        foreach ($data['service_id'] as $key => $serviceId) {
            if ($services->has($serviceId)) {
                $servicesCalculation[$key] = [
                    'service_id' => $serviceId,
                    'service_price' => $data['service_price'][$key],
                    'calculated_price' => 0.00,
                ];
            }
        }

        // Calculate proportional prices
        $calculatedServices = BundleHelper::calculatePrices(
            $servicesCalculation,
            $bundle->services_price,
            $bundle->price
        );

        // Create bundle has services and price history
        foreach ($data['service_id'] as $key => $serviceId) {
            if ($services->has($serviceId)) {
                $service = $services->get($serviceId);

                BundleHasServices::create([
                    'bundle_id' => $bundle->id,
                    'service_id' => $serviceId,
                    'service_price' => $calculatedServices[$key]['service_price'],
                    'calculated_price' => $calculatedServices[$key]['calculated_price'],
                    'end_node' => $service->end_node,
                ]);

                BundleServicesPriceHistory::create([
                    'bundle_id' => $bundle->id,
                    'bundle_price' => $bundle->price,
                    'service_id' => $serviceId,
                    'service_price' => $calculatedServices[$key]['calculated_price'],
                    'effective_from' => Carbon::now()->format('Y-m-d'),
                    'created_by' => Auth::user()->id,
                    'updated_by' => Auth::user()->id,
                    'account_id' => $accountId,
                ]);
            }
        }
    }

    /**
     * Create a configurable bundle
     */
    public function createConfigurableBundle(array $data): Bundles
    {
        $accountId = Auth::user()->account_id;

        // Validate date range
        if (!BundleHelper::isValidDateRange($data['start'] ?? null, $data['end'] ?? null)) {
            throw BundleException::invalidDateRange();
        }

        DB::beginTransaction();

        try {
            // Get base service
            $baseService = Services::find($data['base_service']);
            if (!$baseService) {
                throw BundleException::serviceNotFound('Base service not found');
            }

            // Batch fetch all get services
            $getServiceIds = $data['services_name'] ?? [];
            $getServices = Services::whereIn('id', $getServiceIds)->get()->keyBy('id');

            // Calculate total services price
            $servicesPrice = 0.00;
            foreach ($getServiceIds as $serviceId) {
                if ($getServices->has($serviceId)) {
                    $servicesPrice += $getServices->get($serviceId)->price;
                }
            }

            $totalBaseServicesPrice = $baseService->price * (int) $data['sessions_buy'];
            $totalServicesPrice = $totalBaseServicesPrice + $servicesPrice;

            // Create bundle
            $bundle = Bundles::create([
                'name' => $data['name'],
                'services_price' => $servicesPrice,
                'price' => $totalServicesPrice,
                'start' => $data['start'],
                'end' => $data['end'],
                'apply_discount' => 0,
                'account_id' => $accountId,
                'type' => 'multiple',
                'bundle_type' => 'configurable',
                'base_service_session' => $data['sessions_buy'],
                'total_services' => count($getServiceIds) + 1,
            ]);

            // Log audit trail
            AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $bundle);

            // Create base service sessions
            $packageTotalPrice = 0;
            $packageServicesPrice = 0;

            for ($i = 0; $i < (int) $data['sessions_buy']; $i++) {
                BundleHasServices::create([
                    'bundle_id' => $bundle->id,
                    'service_id' => $data['base_service'],
                    'service_price' => $baseService->price,
                    'calculated_price' => $baseService->price,
                    'end_node' => 1,
                    'base_service' => 1,
                ]);

                BundleServicesPriceHistory::create([
                    'bundle_id' => $bundle->id,
                    'bundle_price' => $bundle->price,
                    'service_id' => $data['base_service'],
                    'service_price' => $baseService->price,
                    'effective_from' => Carbon::now()->format('Y-m-d'),
                    'created_by' => Auth::user()->id,
                    'updated_by' => Auth::user()->id,
                    'account_id' => $accountId,
                ]);

                $packageTotalPrice += $baseService->price;
                $packageServicesPrice += $baseService->price;
            }

            // Create get services
            $this->createConfigurableGetServices($bundle, $data, $getServices, $packageTotalPrice, $packageServicesPrice, $accountId);

            DB::commit();
            BundleHelper::clearCache();

            return $bundle;

        } catch (BundleException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            throw BundleException::operationFailed('Failed to create configurable bundle: ' . $e->getMessage());
        }
    }

    /**
     * Create configurable bundle get services
     */
    protected function createConfigurableGetServices(
        Bundles $bundle,
        array $data,
        $getServices,
        float &$packageTotalPrice,
        float &$packageServicesPrice,
        int $accountId
    ): void {
        $sessions = $data['sessions'] ?? [];

        foreach ($sessions as $key => $sessionCount) {
            $serviceId = $data['services_name'][$key] ?? null;
            $discountType = $data['disc_type'][$key] ?? 'custom';
            $discountAmount = $data['configurable_amount'][$key] ?? 0;

            if (!$serviceId || !$getServices->has($serviceId)) {
                continue;
            }

            $service = $getServices->get($serviceId);

            for ($i = 0; $i < (int) $sessionCount; $i++) {
                $newPrice = $this->calculateDiscountedPrice($service->price, $discountType, $discountAmount);

                $packageTotalPrice += $newPrice;
                $packageServicesPrice += $service->price;

                BundleHasServices::create([
                    'bundle_id' => $bundle->id,
                    'service_id' => $serviceId,
                    'service_price' => $newPrice,
                    'calculated_price' => $newPrice,
                    'end_node' => 1,
                    'get_service' => 1,
                    'discount_type' => $discountType,
                    'discount_amount' => $discountAmount,
                ]);

                BundleServicesPriceHistory::create([
                    'bundle_id' => $bundle->id,
                    'bundle_price' => $bundle->price,
                    'service_id' => $serviceId,
                    'service_price' => $service->price,
                    'effective_from' => Carbon::now()->format('Y-m-d'),
                    'created_by' => Auth::user()->id,
                    'updated_by' => Auth::user()->id,
                    'account_id' => $accountId,
                ]);
            }
        }

        // Update bundle with final prices
        $bundle->update([
            'price' => $packageTotalPrice,
            'services_price' => $packageServicesPrice,
        ]);
    }

    /**
     * Calculate discounted price
     */
    protected function calculateDiscountedPrice(float $price, string $discountType, float $discountAmount): float
    {
        if ($discountType === 'complimentory') {
            return 0;
        }

        $discountedPrice = ($discountAmount / 100) * $price;
        return $discountedPrice == 0 ? 0 : $price - $discountedPrice;
    }

    /**
     * Update a simple bundle
     */
    public function updateBundle(int $id, array $data): Bundles
    {
        $accountId = Auth::user()->account_id;

        // Validate date range
        if (!BundleHelper::isValidDateRange($data['start'] ?? null, $data['end'] ?? null)) {
            throw BundleException::invalidDateRange();
        }

        $bundle = Bundles::where('id', $id)
            ->where('account_id', $accountId)
            ->first();

        if (!$bundle) {
            throw BundleException::notFound();
        }

        DB::beginTransaction();

        try {
            $oldData = $bundle->toArray();

            // Prepare update data
            $updateData = [
                'name' => $data['name'],
                'price' => $data['price'],
                'start' => $data['start'] ?? null,
                'end' => $data['end'] ?? null,
                'apply_discount' => $data['apply_discount'] ?? 0,
                'tax_treatment_type_id' => $data['tax_treatment_type_id'] ?? BundleHelper::DEFAULT_TAX_TREATMENT_TYPE,
                'account_id' => $accountId,
                'type' => 'multiple',
            ];

            // Calculate services price and count
            if (!empty($data['service_id']) && is_array($data['service_id'])) {
                $updateData['total_services'] = count($data['service_id']);
                $updateData['services_price'] = BundleHelper::calculateTotalServicesPrice(
                    $data['service_id'],
                    $data['service_price']
                );
            }

            $bundle->update($updateData);

            // Log audit trail
            AuditTrails::EditEventLogger(self::$_table, 'edit', $updateData, self::$_fillable, $oldData, $id);

            // Delete old bundle services
            BundleHasServices::where('bundle_id', $bundle->id)->delete();

            // Deactivate previous price history
            BundleServicesPriceHistory::where('bundle_id', $bundle->id)
                ->whereNull('effective_to')
                ->update([
                    'effective_to' => Carbon::now()->format('Y-m-d'),
                    'active' => 0,
                    'updated_by' => Auth::user()->id,
                ]);

            // Create new bundle services
            if (!empty($data['service_id']) && is_array($data['service_id'])) {
                $this->createBundleServices($bundle, $data, $accountId);
            }

            DB::commit();
            BundleHelper::clearCache();

            return $bundle->fresh();

        } catch (\Exception $e) {
            DB::rollBack();
            throw BundleException::operationFailed('Failed to update bundle: ' . $e->getMessage());
        }
    }

    /**
     * Update a configurable bundle
     */
    public function updateConfigurableBundle(int $id, array $data): Bundles
    {
        $accountId = Auth::user()->account_id;

        // Validate date range
        if (!BundleHelper::isValidDateRange($data['start'] ?? null, $data['end'] ?? null)) {
            throw BundleException::invalidDateRange();
        }

        $bundle = Bundles::where('id', $id)
            ->where('account_id', $accountId)
            ->first();

        if (!$bundle) {
            throw BundleException::notFound();
        }

        DB::beginTransaction();

        try {
            $oldData = $bundle->toArray();

            // Get base service
            $baseService = Services::find($data['edit_base_service']);
            if (!$baseService) {
                throw BundleException::serviceNotFound('Base service not found');
            }

            // Update basic bundle data
            $bundle->update([
                'name' => $data['name'] ?? $bundle->name,
                'start' => $data['start'] ?? $bundle->start,
                'end' => $data['end'] ?? $bundle->end,
                'base_service_session' => $data['edit_sessions_buy'],
            ]);

            // Delete old services
            BundleHasServices::where('bundle_id', $bundle->id)->delete();

            // Batch fetch get services
            $getServiceIds = $data['edit_services_name'] ?? [];
            $getServices = Services::whereIn('id', $getServiceIds)->get()->keyBy('id');

            // Create base service sessions
            $packageTotalPrice = 0;
            $packageServicesPrice = 0;

            for ($i = 0; $i < (int) $data['edit_sessions_buy']; $i++) {
                BundleHasServices::create([
                    'bundle_id' => $bundle->id,
                    'service_id' => $data['edit_base_service'],
                    'service_price' => $baseService->price,
                    'calculated_price' => $baseService->price,
                    'end_node' => 1,
                    'base_service' => 1,
                ]);

                BundleServicesPriceHistory::create([
                    'bundle_id' => $bundle->id,
                    'bundle_price' => $bundle->price,
                    'service_id' => $data['edit_base_service'],
                    'service_price' => $baseService->price,
                    'effective_from' => Carbon::now()->format('Y-m-d'),
                    'created_by' => Auth::user()->id,
                    'updated_by' => Auth::user()->id,
                    'account_id' => $accountId,
                ]);

                $packageTotalPrice += $baseService->price;
                $packageServicesPrice += $baseService->price;
            }

            // Create get services
            $sessions = $data['edit_sessions'] ?? [];
            foreach ($sessions as $key => $sessionCount) {
                $serviceId = $data['edit_services_name'][$key] ?? null;
                $discountType = $data['edit_disc_type'][$key] ?? 'custom';
                $discountAmount = $data['configurable_amount'][$key] ?? 0;

                if (!$serviceId || !$getServices->has($serviceId)) {
                    continue;
                }

                $service = $getServices->get($serviceId);

                for ($i = 0; $i < (int) $sessionCount; $i++) {
                    $newPrice = $this->calculateDiscountedPrice($service->price, $discountType, $discountAmount);

                    $packageTotalPrice += $newPrice;
                    $packageServicesPrice += $service->price;

                    BundleHasServices::create([
                        'bundle_id' => $bundle->id,
                        'service_id' => $serviceId,
                        'service_price' => $newPrice,
                        'calculated_price' => $newPrice,
                        'end_node' => 1,
                        'get_service' => 1,
                        'discount_type' => $discountType,
                        'discount_amount' => $discountAmount,
                    ]);

                    BundleServicesPriceHistory::create([
                        'bundle_id' => $bundle->id,
                        'bundle_price' => $bundle->price,
                        'service_id' => $serviceId,
                        'service_price' => $service->price,
                        'effective_from' => Carbon::now()->format('Y-m-d'),
                        'created_by' => Auth::user()->id,
                        'updated_by' => Auth::user()->id,
                        'account_id' => $accountId,
                    ]);
                }
            }

            // Update bundle with final prices
            $bundle->update([
                'price' => $packageTotalPrice,
                'services_price' => $packageServicesPrice,
            ]);

            // Log audit trail
            AuditTrails::EditEventLogger(self::$_table, 'edit', $data, self::$_fillable, $oldData, $id);

            DB::commit();
            BundleHelper::clearCache();

            return $bundle->fresh();

        } catch (BundleException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            throw BundleException::operationFailed('Failed to update configurable bundle: ' . $e->getMessage());
        }
    }

    /**
     * Get bundle for editing
     */
    public function getBundleForEdit(int $id): array
    {
        $accountId = Auth::user()->account_id;

        $bundle = Bundles::where('id', $id)
            ->where('account_id', $accountId)
            ->first();

        if (!$bundle) {
            throw BundleException::notFound();
        }

        $relationships = BundleHasServices::where('bundle_id', $bundle->id)
            ->select('service_id')
            ->get();

        $bundleServices = collect();
        if ($relationships->count()) {
            $bundleServices = Services::whereIn('id', $relationships->pluck('service_id'))
                ->where('account_id', $accountId)
                ->get()
                ->keyBy('id');
        }

        return [
            'bundle' => $bundle,
            'services' => BundleHelper::getServices(),
            'bundle_services' => $bundleServices,
            'relationships' => $relationships,
            'tax_treatment_types' => BundleHelper::getTaxTreatmentTypes(),
        ];
    }

    /**
     * Get configurable bundle for editing
     */
    public function getConfigurableBundleForEdit(int $id): array
    {
        $accountId = Auth::user()->account_id;

        $bundle = Bundles::where('id', $id)
            ->where('account_id', $accountId)
            ->first();

        if (!$bundle) {
            throw BundleException::notFound();
        }

        $relationships = BundleHasServices::where('bundle_id', $bundle->id)
            ->select('service_id')
            ->get();

        $baseServices = BundleHasServices::where('bundle_id', $bundle->id)
            ->where('base_service', 1)
            ->get();

        $getServices = BundleHasServices::where('bundle_id', $bundle->id)
            ->where('get_service', 1)
            ->get();

        $bundleServices = collect();
        if ($relationships->count()) {
            $bundleServices = Services::whereIn('id', $relationships->pluck('service_id'))
                ->where('account_id', $accountId)
                ->get()
                ->keyBy('id');
        }

        return [
            'bundle' => $bundle,
            'services' => BundleHelper::getServicesTreeList(),
            'bundle_services' => $bundleServices,
            'relationships' => $relationships,
            'base_service' => $baseServices,
            'get_services' => $getServices,
            'tax_treatment_types' => BundleHelper::getTaxTreatmentTypes(),
        ];
    }

    /**
     * Delete a bundle
     */
    public function deleteBundle(int $id): void
    {
        $accountId = Auth::user()->account_id;

        $bundle = Bundles::where('id', $id)
            ->where('account_id', $accountId)
            ->first();

        if (!$bundle) {
            throw BundleException::notFound();
        }

        // Check for child records
        if (BundleHelper::hasChildRecords($id, $accountId)) {
            throw BundleException::hasChildRecords();
        }

        DB::beginTransaction();

        try {
            $bundle->delete();

            // Delete bundle services
            BundleHasServices::where('bundle_id', $id)->delete();

            // Log audit trail
            AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

            DB::commit();
            BundleHelper::clearCache();

        } catch (\Exception $e) {
            DB::rollBack();
            throw BundleException::operationFailed('Failed to delete bundle: ' . $e->getMessage());
        }
    }

    /**
     * Bulk delete bundles
     */
    protected function bulkDelete(array $ids, int $accountId): void
    {
        $bundles = Bundles::whereIn('id', $ids)
            ->where('account_id', $accountId)
            ->get();

        foreach ($bundles as $bundle) {
            if (!BundleHelper::hasChildRecords($bundle->id, $accountId)) {
                $bundle->delete();
                BundleHasServices::where('bundle_id', $bundle->id)->delete();
            }
        }

        BundleHelper::clearCache();
    }

    /**
     * Update bundle status (active/inactive)
     */
    public function updateStatus(int $id, int $status): Bundles
    {
        $accountId = Auth::user()->account_id;

        $bundle = Bundles::where('id', $id)
            ->where('account_id', $accountId)
            ->first();

        if (!$bundle) {
            throw BundleException::notFound();
        }

        $bundle->update(['active' => $status]);

        if ($status == 0) {
            AuditTrails::InactiveEventLogger(self::$_table, 'inactive', self::$_fillable, $id);
        } else {
            AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);
        }

        BundleHelper::clearCache();

        return $bundle;
    }

    /**
     * Get bundle details
     */
    public function getBundleDetails(int $id): array
    {
        $accountId = Auth::user()->account_id;

        $bundle = Bundles::where('id', $id)
            ->where('account_id', $accountId)
            ->first();

        if (!$bundle) {
            throw BundleException::notFound();
        }

        $relationships = BundleHasServices::where('bundle_id', $bundle->id)
            ->select('service_id')
            ->get();

        $bundleServices = collect();
        if ($relationships->count()) {
            $bundleServices = Services::whereIn('id', $relationships->pluck('service_id'))
                ->where('account_id', $accountId)
                ->get()
                ->keyBy('id');
        }

        return [
            'bundle' => $bundle,
            'bundle_services' => $bundleServices,
            'relationships' => $relationships,
        ];
    }
}
