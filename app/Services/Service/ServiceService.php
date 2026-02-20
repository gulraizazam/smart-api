<?php

namespace App\Services\Service;

use App\Exceptions\ServiceException;
use App\Helpers\Filters;
use App\Helpers\NodesTree;
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
use App\Models\Services;
use App\Models\StaffTargetServices;
use App\Models\TaxTreatmentType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ServiceService
{
    protected static string $table = 'services';

    protected static array $fillable = [
        'name', 'slug', 'end_node', 'complimentory', 'active',
        'tax_treatment_type_id', 'parent_id', 'duration', 'price', 'description', 'color'
    ];

    /**
     * Get services list for datatable with parent-child hierarchy
     */
    public function getServicesList(Request $request, int $accountId): array
    {
        $filters = getFilters($request->all());
        $this->applyFilters($filters, $accountId);

        $canViewInactive = ServiceHelper::canViewInactive();

        // Get parent services
        $parentQuery = Services::where('slug', '!=', 'all')
            ->where('parent_id', 0)
            ->where('account_id', $accountId);

        if (!$canViewInactive) {
            $parentQuery->where('active', 1);
        }

        $parents = $parentQuery->orderBy('id', 'asc')->get();

        $mergedServices = [];

        foreach ($parents as $parent) {
            $parentMatches = !hasFilter($filters, 'name') || stripos($parent->name, $filters['name']) !== false;

            if ($parentMatches) {
                // Parent matches: get ALL children (only filter by status)
                $childQuery = Services::where('parent_id', $parent->id);

                if (!$canViewInactive) {
                    $childQuery->where('active', 1);
                }

                if (hasFilter($filters, 'status')) {
                    $childQuery->where('active', $filters['status']);
                }

                $children = $childQuery->orderBy('sort_number', 'asc')->get()->toArray();

                $mergedServices[] = $parent->toArray();
                foreach ($children as $child) {
                    $mergedServices[] = $child;
                }
            } else {
                // Parent doesn't match: get children that match the name filter
                $childQuery = Services::where('parent_id', $parent->id)
                    ->where('name', 'like', '%' . $filters['name'] . '%');

                if (!$canViewInactive) {
                    $childQuery->where('active', 1);
                }

                if (hasFilter($filters, 'status')) {
                    $childQuery->where('active', $filters['status']);
                }

                $children = $childQuery->orderBy('sort_number', 'asc')->get()->toArray();

                // Only include parent if it has matching children
                if (count($children) > 0) {
                    $mergedServices[] = $parent->toArray();
                    foreach ($children as $child) {
                        $mergedServices[] = $child;
                    }
                }
            }
        }

        return $mergedServices;
    }

    /**
     * Apply and store filters
     */
    protected function applyFilters(array $filters, int $accountId): void
    {
        $userId = Auth::user()->id;
        $filename = 'services';
        $applyFilter = checkFilters($filters, $filename);

        if (hasFilter($filters, 'name')) {
            Filters::put($userId, $filename, 'name', $filters['name']);
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, 'name');
        }

        if (hasFilter($filters, 'status')) {
            Filters::put($userId, $filename, 'status', $filters['status']);
        } elseif ($applyFilter) {
            Filters::forget($userId, $filename, 'status');
        }
    }

    /**
     * Get total records count
     */
    public function getTotalRecords(Request $request, int $accountId): int
    {
        $filters = getFilters($request->all());
        $query = Services::where('account_id', $accountId);

        if (hasFilter($filters, 'name')) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (hasFilter($filters, 'status')) {
            $query->where('active', $filters['status']);
        }

        if (!Gate::allows('view_inactive_records')) {
            $query->where('active', 1);
        }

        return $query->count();
    }

    /**
     * Get form data for create/edit
     */
    public function getFormData(int $accountId, ?int $serviceId = null): array
    {
        $service = null;
        $selectTaxTreatmentType = ServiceHelper::DEFAULT_TAX_TREATMENT_TYPE; // Default: Is Inclusive

        if ($serviceId) {
            $service = Services::findOrFail($serviceId);
            // If existing service has "Both" (ID 1), default to "Is Inclusive" (ID 3)
            $selectTaxTreatmentType = ($service->tax_treatment_type_id && $service->tax_treatment_type_id != 1) 
                ? $service->tax_treatment_type_id 
                : ServiceHelper::DEFAULT_TAX_TREATMENT_TYPE;
        } else {
            $service = new \stdClass();
            $service->duration = null;
            $service->parent_id = null;
        }

        return [
            'parent_services' => ServiceHelper::getParentServices($accountId),
            'service' => $service,
            'durations' => ServiceHelper::getDurations(),
            'tax_treatment_types' => ServiceHelper::getTaxTreatmentTypes(),
            'select_tax_treatment_type' => $selectTaxTreatmentType,
        ];
    }

    /**
     * Create a new service
     */
    public function createService(array $data, int $accountId): Services
    {
        $data = ServiceHelper::prepareServiceData($data, $accountId);

        return DB::transaction(function () use ($data, $accountId) {
            // Create the service
            $service = Services::create($data);
            $service->update(['sort_no' => $service->id]);

            // Log audit trail
            AuditTrails::addEventLogger(self::$table, 'create', $data, self::$fillable, $service);

            // Create associated bundle
            $this->createServiceBundle($service, $data);

            // Clear cache
            ServiceHelper::clearCache($accountId);

            return $service;
        });
    }

    /**
     * Update an existing service
     */
    public function updateService(int $id, array $data, int $accountId): Services
    {
        $service = Services::where('id', $id)->where('account_id', $accountId)->first();

        if (!$service) {
            throw ServiceException::notFound($id);
        }

        // Check if changing from child to parent when appointments exist
        if ($service->parent_id > 0 && ($data['parent_id'] ?? 0) == 0) {
            $appointmentCount = Appointments::where('service_id', $id)->count();
            if ($appointmentCount > 0) {
                throw ServiceException::hasAppointments($id);
            }
        }

        // Check if parent change is allowed
        if ($this->hasChildServices($id, $accountId)) {
            if ($service->parent_id != ($data['parent_id'] ?? $service->parent_id) ||
                $service->end_node != (int)($data['end_node'] ?? $service->end_node)) {
                throw ServiceException::parentChangeNotAllowed($id);
            }
        }

        $data = ServiceHelper::prepareServiceData($data, $accountId);

        return DB::transaction(function () use ($service, $data, $id, $accountId) {
            $oldData = $service->toArray();

            // Handle color inheritance
            if (($data['parent_id'] ?? 0) == 0) {
                // This is a parent - update children colors
                Services::where('parent_id', $id)->update(['color' => $data['color'] ?? $service->color]);
            } else {
                // This is a child - inherit parent color
                $parentColor = ServiceHelper::getParentColor($data['parent_id']);
                if ($parentColor) {
                    $data['color'] = $parentColor;
                }
            }

            $service->update($data);

            // Log audit trail
            AuditTrails::EditEventLogger(self::$table, 'edit', $data, self::$fillable, $oldData, $id);

            // Update associated bundle
            $this->updateServiceBundle($service, $data, $accountId);

            // Clear cache
            ServiceHelper::clearCache($accountId);

            return $service->fresh();
        });
    }

    /**
     * Delete a service
     */
    public function deleteService(int $id, int $accountId): array
    {
        $service = Services::find($id);

        if (!$service) {
            throw ServiceException::notFound($id);
        }

        // Check for dependencies
        $dependency = $this->checkDependencies($id, $accountId);
        if ($dependency) {
            throw ServiceException::hasDependencies($id, $dependency);
        }

        return DB::transaction(function () use ($service, $id, $accountId) {
            $service->delete();

            // Log audit trail
            AuditTrails::deleteEventLogger(self::$table, 'delete', self::$fillable, $id);

            // Delete associated bundle
            $this->deleteServiceBundle($id);

            // Clear cache
            ServiceHelper::clearCache($accountId);

            return [
                'status' => true,
                'message' => 'Record has been deleted successfully.',
            ];
        });
    }

    /**
     * Activate a service
     */
    public function activateService(int $id, int $accountId): bool
    {
        $service = Services::find($id);

        if (!$service) {
            throw ServiceException::notFound($id);
        }

        return DB::transaction(function () use ($service, $id, $accountId) {
            // Activate children if this is a parent
            Services::where('parent_id', $id)->update(['active' => 1]);

            $service->update(['active' => 1]);

            // Log audit trail
            AuditTrails::activeEventLogger(self::$table, 'active', self::$fillable, $id);

            // Activate associated bundle
            $this->updateBundleStatus($id, 1);

            // Clear cache
            ServiceHelper::clearCache($accountId);

            return true;
        });
    }

    /**
     * Deactivate a service
     */
    public function deactivateService(int $id, int $accountId): bool
    {
        $service = Services::find($id);

        if (!$service) {
            throw ServiceException::notFound($id);
        }

        // If this is a parent service, deactivate all children as well
        if ($service->parent_id == 0) {
            Services::where('parent_id', $id)->update(['active' => 0]);
        }

        return DB::transaction(function () use ($service, $id, $accountId) {
            // Deactivate the service itself
            $service->update(['active' => 0]);

            // Log audit trail
            AuditTrails::inactiveEventLogger(self::$table, 'inactive', self::$fillable, $id);

            // Deactivate associated bundle
            $this->updateBundleStatus($id, 0);

            // Clear cache
            ServiceHelper::clearCache($accountId);

            return true;
        });
    }

    /**
     * Get services for sorting
     */
    public function getServicesForSort(): array
    {
        $services = Services::where('slug', '!=', 'all')
            ->where('parent_id', 0)
            ->orderBy('id', 'asc')
            ->get();

        $mergedServices = [];

        foreach ($services as $service) {
            $children = Services::where('parent_id', $service->id)
                ->orderBy('sort_number', 'asc')
                ->get()
                ->toArray();

            $mergedServices[] = $service->toArray();
            foreach ($children as $child) {
                $mergedServices[] = $child;
            }
        }

        return $mergedServices;
    }

    /**
     * Save sort order
     */
    public function saveSortOrder(array $itemIds, int $accountId): bool
    {
        if (empty($itemIds)) {
            return false;
        }

        DB::transaction(function () use ($itemIds, $accountId) {
            foreach ($itemIds as $key => $itemId) {
                Services::where('id', $itemId)->update(['sort_number' => $key]);
            }

            ServiceHelper::clearCache($accountId);
        });

        return true;
    }

    /**
     * Get service description/instructions
     */
    public function getServiceDescription(int $id): ?string
    {
        $service = Services::find($id, ['description']);
        return $service ? $service->description : null;
    }

    /**
     * Check if service has child services
     */
    public function hasChildServices(int $id, int $accountId): bool
    {
        return Services::where('parent_id', $id)
            ->where('account_id', $accountId)
            ->exists();
    }

    /**
     * Check for dependencies that prevent deletion
     * Returns the dependency name or null if none found
     */
    protected function checkDependencies(int $id, int $accountId): ?string
    {
        // Check child services
        if (Services::where('parent_id', $id)->where('account_id', $accountId)->exists()) {
            return 'child services';
        }

        // Check package services
        if (PackageService::where('service_id', $id)->exists()) {
            return 'packages';
        }

        // Check discount locations
        if (DiscountHasLocations::where('service_id', $id)->exists()) {
            return 'discounts';
        }

        // Check doctor allocations
        if (DoctorHasLocations::where('service_id', $id)->where('is_allocated', 1)->exists()) {
            return 'doctor allocations';
        }

        // Check service locations
        if (ServiceHasLocations::where('service_id', $id)->exists()) {
            return 'location assignments';
        }

        // Check appointments
        if (Appointments::where('service_id', $id)->exists()) {
            return 'appointments';
        }

        // Check staff targets
        if (StaffTargetServices::where('service_id', $id)->exists()) {
            return 'staff targets';
        }

        // Check paid invoices
        $paidStatus = InvoiceStatuses::where('slug', 'paid')->first();
        if ($paidStatus) {
            $hasInvoices = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                ->where('invoice_details.service_id', $id)
                ->where('invoices.invoice_status_id', $paidStatus->id)
                ->exists();

            if ($hasInvoices) {
                return 'paid invoices';
            }
        }

        return null;
    }

    /**
     * Create bundle for a service
     */
    protected function createServiceBundle(Services $service, array $data): void
    {
        $bundle = Bundles::create([
            'name' => $service->name,
            'price' => $service->price ?? 0.0,
            'services_price' => $service->price ?? 0.0,
            'type' => 'single',
            'total_services' => 1,
            'account_id' => 1,
            'tax_treatment_type_id' => $data['tax_treatment_type_id'] ?? ServiceHelper::DEFAULT_TAX_TREATMENT_TYPE,
        ]);

        BundleHasServices::create([
            'bundle_id' => $bundle->id,
            'service_id' => $service->id,
            'service_price' => $service->price ?? 0.0,
            'calculated_price' => $service->price ?? 0.0,
            'end_node' => $service->end_node,
        ]);

        BundleServicesPriceHistory::createRecord([
            'bundle_id' => $bundle->id,
            'bundle_price' => $service->price ?? 0.0,
            'service_id' => $service->id,
            'service_price' => $service->price ?? 0.0,
            'effective_from' => Carbon::now()->format('Y-m-d'),
            'created_by' => Auth::user()->id,
            'updated_by' => Auth::user()->id,
        ], $service->account_id);
    }

    /**
     * Update bundle for a service
     */
    protected function updateServiceBundle(Services $service, array $data, int $accountId): void
    {
        $bundleWithService = Bundles::join('bundle_has_services', 'bundle_has_services.bundle_id', '=', 'bundles.id')
            ->where([
                'bundles.account_id' => $accountId,
                'bundles.type' => 'single',
                'bundle_has_services.service_id' => $service->id,
            ])
            ->select('bundles.id')
            ->first();

        if (!$bundleWithService) {
            return;
        }

        // Deactivate previous price history
        BundleServicesPriceHistory::where('bundle_id', $bundleWithService->id)
            ->whereNull('effective_to')
            ->update([
                'effective_to' => Carbon::now()->format('Y-m-d'),
                'active' => 0,
                'updated_by' => Auth::user()->id,
            ]);

        // Update bundle
        Bundles::where('id', $bundleWithService->id)->update([
            'name' => $service->name,
            'price' => $service->price,
            'services_price' => $service->price,
            'tax_treatment_type_id' => $data['tax_treatment_type_id'] ?? ServiceHelper::DEFAULT_TAX_TREATMENT_TYPE,
        ]);

        // Update bundle has services
        BundleHasServices::where('bundle_id', $bundleWithService->id)->update([
            'service_price' => $service->price,
            'calculated_price' => $service->price,
            'end_node' => $service->end_node,
        ]);

        // Create new price history
        BundleServicesPriceHistory::createRecord([
            'bundle_id' => $bundleWithService->id,
            'bundle_price' => $service->price,
            'service_id' => $service->id,
            'service_price' => $service->price,
            'effective_from' => Carbon::now()->format('Y-m-d'),
            'created_by' => Auth::user()->id,
            'updated_by' => Auth::user()->id,
        ], $accountId);
    }

    /**
     * Delete bundle for a service
     */
    protected function deleteServiceBundle(int $serviceId): void
    {
        $bundleWithService = Bundles::join('bundle_has_services', 'bundle_has_services.bundle_id', '=', 'bundles.id')
            ->where([
                'bundles.type' => 'single',
                'bundle_has_services.service_id' => $serviceId,
            ])
            ->select('bundles.id')
            ->first();

        if ($bundleWithService) {
            Bundles::where('id', $bundleWithService->id)->delete();
            BundleHasServices::where('bundle_id', $bundleWithService->id)->delete();
        }
    }

    /**
     * Update bundle status (active/inactive)
     */
    protected function updateBundleStatus(int $serviceId, int $status): void
    {
        $bundleWithService = Bundles::join('bundle_has_services', 'bundle_has_services.bundle_id', '=', 'bundles.id')
            ->where([
                'bundles.type' => 'single',
                'bundle_has_services.service_id' => $serviceId,
            ])
            ->select('bundles.id')
            ->first();

        if ($bundleWithService) {
            Bundles::where('id', $bundleWithService->id)->update(['active' => $status]);
        }
    }

    /**
     * Get service color by ID
     */
    public function getServiceColor(int $serviceId): string
    {
        if ($serviceId == 0) {
            return '#000';
        }

        $service = Services::find($serviceId, ['color']);
        return $service ? $service->color : '#000';
    }
}
