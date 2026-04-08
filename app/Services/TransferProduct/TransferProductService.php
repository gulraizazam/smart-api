<?php

declare(strict_types=1);

namespace App\Services\TransferProduct;

use App\Helpers\ACL;
use App\Helpers\GeneralFunctions;
use App\Models\DoctorHasLocations;
use App\Models\Inventory;
use App\Models\Locations;
use App\Models\Product;
use App\Models\ProductDetail;
use App\Models\RoleHasUsers;
use App\Models\Stock;
use App\Models\TransferProduct;
use App\Models\User;
use App\Models\UserHasLocations;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TransferProductService
{
    // ---------------------------------------------------------------
    // Datatable
    // ---------------------------------------------------------------

    public function getDatatableCount(array $params): int
    {
        return $this->buildFilteredQuery($params)->count();
    }

    public function getDatatableRecords(array $params): \Illuminate\Database\Eloquent\Collection
    {
        return $this->buildFilteredQuery($params)
            ->orderBy('id', 'desc')
            ->offset($params['offset'] ?? 0)
            ->limit($params['limit'] ?? 30)
            ->get();
    }

    /**
     * Transform transfer product records for datatable display.
     */
    public function transformForDatatable(\Illuminate\Database\Eloquent\Collection $transfers): \Illuminate\Support\Collection
    {
        $fromCentres = Locations::getAllRecordsDictionary(Auth::user()->account_id, 'custom', 'id', 'desc', ACL::getUserCentres());
        $toCentres = Locations::getAllRecordsDictionary(Auth::user()->account_id, 'custom', 'id', 'desc');
        $warehouse = Warehouse::getAllRecordsDictionary(Auth::user()->account_id, ACL::getUserWarehouse());

        return collect($transfers)->map(function ($transfer, $index) use ($fromCentres, $toCentres, $warehouse) {
            $transfer->transfer_index = $index + 1;

            $productName = Product::where(['id' => $transfer->product_id])->select('name')->first();
            $productDetailQuantity = ProductDetail::where(['id' => $transfer->product_detail_id])->first();

            $transfer->from = $this->resolveLocationName($transfer, 'from', $fromCentres, $warehouse);
            $transfer->to = $this->resolveLocationName($transfer, 'to', $toCentres, $warehouse);
            $transfer->name = $productName->name ?? 'N/A';
            $transfer->quantity = $productDetailQuantity->quantity ?? 0;

            return $transfer;
        });
    }

    // ---------------------------------------------------------------
    // CRUD
    // ---------------------------------------------------------------

    public function find(int $id): ?TransferProduct
    {
        return TransferProduct::find($id);
    }

    /**
     * Get create form data.
     */
    public function getCreateFormData(): array
    {
        return [
            'centres' => Locations::whereIn('id', ACL::getUserCentres())->pluck('name', 'id'),
            'warehouse' => Warehouse::whereActive(1)->pluck('name', 'id'),
        ];
    }

    /**
     * Get edit form data.
     */
    public function getEditFormData(int $id): ?array
    {
        $product = TransferProduct::findOrFail($id);
        $productDetails = ProductDetail::findOrFail($product->product_detail_id);
        $products = Product::getAllRecordsDictionary(Auth::user()->account_id);

        return [
            'product' => $product,
            'product_details' => $productDetails,
            'products' => $products,
        ];
    }

    /**
     * Validate transfer request data.
     *
     * @return array{valid: bool, message: string}|null
     */
    public function validateTransfer(array $data, bool $isUpdate = false): ?array
    {
        if ($isUpdate) {
            $stockCheck = \App\Services\Product\ProductService::stockC($data['product_id']);
        } else {
            $request = new \Illuminate\Http\Request($data);
            $stockCheck = \App\Services\Product\ProductService::inventoryCheck($request);
        }

        if ($stockCheck < $data['quantity']) {
            return ['valid' => false, 'message' => 'This product stock not available.'];
        }
        if ($data['quantity'] <= 0) {
            return ['valid' => false, 'message' => 'Please add valid product quantity.'];
        }
        if (($data['product_type_option_to'] ?? '') === 'in_warehouse' && empty($data['to_warehouse_id'])) {
            return ['valid' => false, 'message' => 'Please select warehouse'];
        }
        if (($data['product_type_option_to'] ?? '') === 'in_branch' && empty($data['to_location_id'])) {
            return ['valid' => false, 'message' => 'Please select any centre.'];
        }

        // Check same location
        if (($data['product_type_option_to'] ?? '') === 'in_warehouse') {
            if (($data['from_warehouse_id'] ?? null) == ($data['to_warehouse_id'] ?? null)) {
                return ['valid' => false, 'message' => 'Please add different location.'];
            }
        } else {
            if (($data['from_location_id'] ?? null) == ($data['to_location_id'] ?? null)) {
                return ['valid' => false, 'message' => 'Please add different location.'];
            }
        }

        return null;
    }

    /**
     * Create a transfer product with inventory adjustments.
     *
     * @return array{success: bool, message: string}
     */
    public function store(array $data): array
    {
        $data['type'] = 'product_transfer_create';
        $data['message'] = 'Transfer Product create';

        $request = new \Illuminate\Http\Request($data);
        $accountId = Auth::user()->account_id;

        $transferResult = TransferProduct::createRecord($request, $accountId);

        if (! $transferResult['record']) {
            $message = $transferResult['message'] ?? 'Something went wrong, please try again later.';

            return ['success' => false, 'message' => $message];
        }

        $productDetail = ProductDetail::createRecordTransferProduct($transferResult['data'], $accountId);

        if (! $productDetail) {
            return ['success' => false, 'message' => 'Failed to create product detail.'];
        }

        TransferProduct::where(['id' => $transferResult['record']->id])
            ->update(['product_detail_id' => $productDetail->id]);

        $productType = Product::find($data['product_id']);

        // Resolve source inventory
        if (! empty($data['from_warehouse_id'])) {
            $minusInventory = Inventory::where('product_id', $data['product_id'])
                ->where('warehouse_id', $data['from_warehouse_id'])->first();

            $updateInventory = ! empty($data['to_warehouse_id'])
                ? Inventory::where('product_id', $data['product_id'])->where('warehouse_id', $data['to_warehouse_id'])->first()
                : Inventory::where('product_id', $data['product_id'])->where('location_id', $data['to_location_id'])->first();
        } else {
            $minusInventory = Inventory::where('product_id', $data['product_id'])
                ->where('location_id', $data['from_location_id'])->first();

            $updateInventory = ! empty($data['to_warehouse_id'])
                ? Inventory::where('product_id', $data['product_id'])->where('warehouse_id', $data['to_warehouse_id'])->first()
                : Inventory::where('product_id', $data['product_id'])->where('location_id', $data['to_location_id'])->first();
        }

        // Deduct from source
        $minusInventory->update(['quantity' => $minusInventory->quantity - $data['quantity']]);

        // Add to destination
        if ($updateInventory) {
            $updateInventory->update(['quantity' => $updateInventory->quantity + $data['quantity']]);
        } else {
            $inventory = new Inventory();
            $inventory->product_id = $data['product_id'];

            if (! empty($data['to_warehouse_id'])) {
                $inventory->warehouse_id = $data['to_warehouse_id'];
            } else {
                $inventory->location_id = $data['to_location_id'];
            }

            $inventory->quantity = $data['quantity'];
            $inventory->is_saleable = $productType->product_type === 'for_sale' ? 1 : 0;
            $inventory->save();
        }

        return ['success' => true, 'message' => 'Record has been created successfully.'];
    }

    /**
     * Update a transfer product.
     *
     * @return array{success: bool, message: string}
     */
    public function update(int $id, array $data): array
    {
        $data['type'] = 'product_transfer_update';
        $data['message'] = 'Transfer Product update';

        $request = new \Illuminate\Http\Request($data);
        $accountId = Auth::user()->account_id;

        $transferResult = TransferProduct::updateRecord($id, $request, $accountId);
        $productDetail = ProductDetail::updateRecordTransferProduct(
            $transferResult['data'],
            $accountId,
            $transferResult['data']['product_detail_id'],
        );

        if ($transferResult && $productDetail) {
            TransferProduct::where(['id' => $transferResult['record']->id])
                ->update(['product_detail_id' => $productDetail->id]);

            return ['success' => true, 'message' => 'Record has been created successfully.'];
        }

        return ['success' => false, 'message' => 'Something went wrong, please try again later.'];
    }

    /**
     * @return array{status: bool, message: string}
     */
    public function delete(int $id): array
    {
        $result = TransferProduct::DeleteRecord($id);

        return ['status' => $result->get('status'), 'message' => $result->get('message')];
    }

    // ---------------------------------------------------------------
    // Get Products (for transfer forms)
    // ---------------------------------------------------------------

    public function getProductsWithDoctors(array $params): array
    {
        $request = new \Illuminate\Http\Request($params);
        $products = Product::getProductsAjax($request, Auth::user()->account_id);

        $fromId = $params['from_id'] ?? null;

        $doctors = DoctorHasLocations::where('is_allocated', 1)
            ->where('location_id', $fromId)
            ->pluck('user_id')->toArray();

        $users = User::whereIn('id', $doctors)
            ->where('active', 1)
            ->pluck('name', 'id')->toArray();

        $locationIds = is_array($fromId) ? $fromId : [$fromId];

        $findFDM = UserHasLocations::whereIn('location_id', $locationIds)->pluck('user_id')->toArray();
        $findRole = DB::table('roles')->where('name', 'FDM')->first();
        $roleHasUser = RoleHasUsers::where('role_id', $findRole->id)->pluck('user_id')->toArray();
        $fdmUsers = array_intersect($findFDM, $roleHasUser);

        $FDMUsers = User::whereIn('id', $fdmUsers)->pluck('name', 'id')->toArray();

        return [
            'products' => $products,
            'doctors' => $users + $FDMUsers,
        ];
    }

    public function getTransferProducts(array $params): array
    {
        $request = new \Illuminate\Http\Request($params);
        $products = Product::getTransferProductsAjax($request, Auth::user()->account_id);

        if (! empty($params['location_id'])) {
            $warehouseId = TransferProduct::where([
                'product_id' => $params['product_id'],
                'to_location_id' => $params['location_id'],
            ])->pluck('from_warehouse_id')->toArray();

            $warehouses = Warehouse::whereIn('id', $warehouseId)->get();
        } else {
            $warehouses = Warehouse::get();
        }

        $Users = User::where('user_type_id', 5)->where('active', 1)->pluck('name', 'id');

        return [
            'products' => $products,
            'warehouses' => $warehouses,
            'users' => $Users,
        ];
    }

    // ---------------------------------------------------------------
    // Permissions & Filter values
    // ---------------------------------------------------------------

    public function getPermissions(): array
    {
        return [
            'edit' => Gate::allows('transfer_product_edit'),
            'manage' => Gate::allows('transfer_product_manage'),
            'delete' => Gate::allows('transfer_product_destroy'),
            'create' => Gate::allows('transfer_product_create'),
        ];
    }

    public function getFilterValues(): array
    {
        $accountId = Auth::user()->account_id;

        return [
            'centres' => collect(Locations::getAllRecordsDictionary($accountId, 'custom', 'id', 'desc', ACL::getUserCentres()))->pluck('name', 'id'),
            'warehouse' => collect(Warehouse::getAllRecordsDictionary($accountId, ACL::getUserWarehouse()))->pluck('name', 'id'),
        ];
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    private function buildFilteredQuery(array $params): \Illuminate\Database\Eloquent\Builder
    {
        $filters = $params['filters'] ?? [];
        $where = [];

        $productId = null;
        if (! empty($filters['name'])) {
            $productId = Product::where('name', 'like', '%' . $filters['name'] . '%')->pluck('id');
        }

        // Date filter
        if (! empty($filters['created_at'])) {
            $dateRange = explode(' - ', $filters['created_at']);
            $startDateTime = date('Y-m-d H:i:s', strtotime($dateRange[0]));
            $endDateString = new \DateTime($dateRange[1]);
            $endDateString->setTime(23, 59, 0);
            $where[] = ['created_at', '>=', $startDateTime];
            $where[] = ['created_at', '<=', $endDateString->format('Y-m-d H:i:s')];
        }

        // Location filters
        if (! empty($filters['location_from']) && ! empty($filters['transfer_from'])) {
            if ($filters['location_from'] === 'branch') {
                $where[][] = ['from_location_id' => $filters['transfer_from']];
            } elseif ($filters['location_from'] === 'warehouse') {
                $where[][] = ['from_warehouse_id' => $filters['transfer_from']];
            }
        }
        if (! empty($filters['location_to']) && ! empty($filters['transfer_to'])) {
            if ($filters['location_to'] === 'branch') {
                $where[][] = ['to_location_id' => $filters['transfer_to']];
            } elseif ($filters['location_to'] === 'warehouse') {
                $where[][] = ['to_warehouse_id' => $filters['transfer_to']];
            }
        }

        return TransferProduct::query()
            ->when(!empty($where), fn ($q) => $q->where($where))
            ->when($productId !== null, fn ($q) => $q->whereIn('product_id', $productId))
            ->where(fn ($query) => $query->whereIn('from_location_id', ACL::getUserCentres()));
    }

    private function resolveLocationName(TransferProduct $transfer, string $direction, array $centres, array $warehouse): string
    {
        $locationId = $direction === 'from' ? $transfer->from_location_id : $transfer->to_location_id;
        $warehouseId = $direction === 'from' ? $transfer->from_warehouse_id : $transfer->to_warehouse_id;

        if ($locationId !== null) {
            return array_key_exists($locationId, $centres) ? $centres[$locationId]->name : 'N/A';
        }

        return array_key_exists($warehouseId, $warehouse) ? $warehouse[$warehouseId]->name : 'N/A';
    }
}
