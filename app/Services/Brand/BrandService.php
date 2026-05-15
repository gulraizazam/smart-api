<?php

declare(strict_types=1);

namespace App\Services\Brand;

use App\Models\Brand;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class BrandService
{
    private const FILTER_KEY = 'warehouse';

    /**
     * Get datatable record count with filters applied.
     */
    public function getDatatableCount(array $params): int
    {
        return $this->buildFilteredQuery($params)->count();
    }

    /**
     * Get datatable records with filters, sorting and pagination.
     */
    public function getDatatableRecords(array $params): \Illuminate\Database\Eloquent\Collection
    {
        return $this->buildFilteredQuery($params)
            ->orderBy('id', 'desc')
            ->offset($params['offset'] ?? 0)
            ->limit($params['limit'] ?? 30)
            ->get();
    }

    /**
     * Find a brand by ID scoped to the current account.
     */
    public function find(int $id): ?Brand
    {
        return Brand::where('id', $id)
            ->where('account_id', Auth::user()->account_id)
            ->first();
    }

    /**
     * Create a new brand.
     */
    public function create(array $data): Brand
    {
        $data['account_id'] = Auth::user()->account_id;

        return Brand::create($data);
    }

    /**
     * Update a brand.
     */
    public function update(int $id, array $data): ?Brand
    {
        $record = $this->find($id);

        if (! $record) {
            return null;
        }

        $data['account_id'] = Auth::user()->account_id;
        $record->update($data);

        return $record;
    }

    /**
     * Delete a brand. Deactivates if child products exist.
     *
     * @return array{status: bool, message: string}
     */
    public function delete(int $id): array
    {
        $brand = $this->find($id);

        if (! $brand) {
            return ['status' => false, 'message' => 'Resource not found.'];
        }

        if (Product::where(['brand_id' => $id, 'account_id' => Auth::user()->account_id])->exists()) {
            $brand->update(['status' => 0]);

            return ['status' => false, 'message' => 'Brand Deactived Successfully!'];
        }

        $brand->delete();

        return ['status' => true, 'message' => 'Record has been deleted successfully.'];
    }

    /**
     * Bulk delete brands by IDs.
     *
     * @return array{status: bool, message: string}
     */
    public function bulkDelete(array $ids): array
    {
        $brands = Brand::getBulkData($ids);

        if ($brands->isEmpty()) {
            return ['status' => false, 'message' => 'No records found.'];
        }

        $anyDeleted = false;
        $accountId = Auth::user()->account_id;

        foreach ($brands as $brand) {
            if (! Product::where(['brand_id' => $brand->id, 'account_id' => $accountId])->exists()) {
                $brand->delete();
                $anyDeleted = true;
            }
        }

        if (! $anyDeleted) {
            return ['status' => false, 'message' => 'Child records exist, unable to delete resource!'];
        }

        return ['status' => true, 'message' => 'Records has been deleted successfully!'];
    }

    /**
     * Toggle brand active/inactive status.
     */
    public function toggleStatus(int $id, int $status): ?Brand
    {
        $brand = $this->find($id);

        if (! $brand) {
            return null;
        }

        $brand->update(['status' => $status]);

        return $brand;
    }

    /**
     * Get permissions for the current user.
     */
    public function getPermissions(): array
    {
        return [
            'edit' => Gate::allows('brand_edit'),
            'manage' => Gate::allows('brand_manage'),
            'delete' => Gate::allows('brand_destroy'),
            'create' => Gate::allows('brand_create'),
            'b_active' => Gate::allows('brand_active'),
        ];
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    private function buildFilteredQuery(array $params): \Illuminate\Database\Eloquent\Builder
    {
        $where = [];

        if (! empty($params['name'])) {
            $where[] = ['name', 'like', '%' . $params['name'] . '%'];
        }

        // Status filter: 0 = inactive, 1 = active, null/'' = no filter.
        // Compared with `!== null && !== ''` rather than `!empty` because
        // `0` is a valid (and important) filter value — operators often
        // narrow to inactive brands for cleanup.
        if (isset($params['status']) && $params['status'] !== '' && $params['status'] !== null) {
            $where[] = ['status', (int) $params['status']];
        }

        return Brand::query()
            ->when(!empty($where), fn ($q) => $q->where($where));
    }
}
