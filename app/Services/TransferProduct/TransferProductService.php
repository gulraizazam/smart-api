<?php

declare(strict_types=1);

namespace App\Services\TransferProduct;

use App\Helpers\ACL;
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
use App\Services\Product\ProductService;
use App\Services\Reports\Concerns\ParsesDateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TransferProductService
{
    use ParsesDateRange;

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
    public function transformForDatatable(\Illuminate\Database\Eloquent\Collection $transfers): Collection
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
            $stockCheck = ProductService::stockC($data['product_id']);
        } else {
            $request = new Request($data);
            $stockCheck = ProductService::inventoryCheck($request);
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
     * Wrapped in a single DB transaction so the transfer record, the
     * source decrement, and the destination increment either all commit
     * together or all roll back. Source/destination inventory rows are
     * locked via `lockForUpdate()` before the math so two concurrent
     * transfers cannot interleave reads with writes on the same row —
     * previously the same product could be over-transferred from the
     * same centre by two clerks racing.
     *
     * Stock ledger rows (`stock_type='in'`/`'out'`) are written for both
     * sides — sales reports and `Stock::sumProductQuantity` rely on the
     * ledger, so a transfer that only touched the `inventories` mirror
     * silently diverged availability totals from the ledger.
     *
     * TODO(fifo-batches-on-transfer): the destination batch shape is
     * still a single new IN row carrying the product's global sale_price.
     * The proper bridge moves source FIFO batches (preserving original
     * `sale_price` and `created_at`) across to the destination. Tracked
     * as a follow-up — for now transferred stock at the destination is
     * treated as a fresh batch at the current product price, which is
     * acceptable when transfers happen at-cost and the SPA shows the
     * product's current price anyway.
     *
     * @return array{success: bool, message: string}
     */
    public function store(array $data): array
    {
        $accountId = (int) Auth::user()->account_id;
        $productId = (int) $data['product_id'];
        $quantity = (int) $data['quantity'];

        if ($quantity <= 0) {
            return ['success' => false, 'message' => 'Please add valid product quantity.'];
        }

        // Account-scope guard — the request comes from the SPA and the
        // SPA's pickers are scoped by ACL, but a hand-rolled API caller
        // can pass any product_id. Block products belonging to a
        // different tenant outright so we don't leak inventory across
        // accounts.
        $product = Product::where('id', $productId)->where('account_id', $accountId)->first();
        if (! $product) {
            return ['success' => false, 'message' => 'Product not found for this organization.'];
        }

        $data['type'] = 'product_transfer_create';
        $data['message'] = 'Transfer Product create';

        try {
            return DB::transaction(function () use ($data, $accountId, $productId, $quantity, $product): array {
                $request = new Request($data);

                $transferResult = TransferProduct::createRecord($request, $accountId);

                if (! $transferResult['record']) {
                    return [
                        'success' => false,
                        'message' => $transferResult['message'] ?? 'Something went wrong, please try again later.',
                    ];
                }

                $productDetail = ProductDetail::createRecordTransferProduct($transferResult['data'], $accountId);

                if (! $productDetail) {
                    throw new \RuntimeException('Failed to create product detail.');
                }

                TransferProduct::where(['id' => $transferResult['record']->id])
                    ->update(['product_detail_id' => $productDetail->id]);

                // Resolve & LOCK source inventory. The lock blocks any
                // other writer until this transaction commits.
                $sourceQuery = Inventory::where('product_id', $productId);
                if (! empty($data['from_warehouse_id'])) {
                    $sourceQuery->where('warehouse_id', $data['from_warehouse_id']);
                } else {
                    $sourceQuery->where('location_id', $data['from_location_id']);
                }
                $sourceInventory = $sourceQuery->lockForUpdate()->first();

                if (! $sourceInventory || (int) $sourceInventory->quantity < $quantity) {
                    throw new \Symfony\Component\HttpKernel\Exception\HttpException(
                        422,
                        'Source stock is no longer available — refresh and try again.',
                    );
                }

                $sourceInventory->update([
                    'quantity' => (int) $sourceInventory->quantity - $quantity,
                ]);

                // Destination inventory — lock if it exists, otherwise
                // create. Use the same row-level guard so two clerks
                // transferring into the same centre don't both create
                // duplicate inventory rows.
                $destQuery = Inventory::where('product_id', $productId);
                if (! empty($data['to_warehouse_id'])) {
                    $destQuery->where('warehouse_id', $data['to_warehouse_id']);
                } else {
                    $destQuery->where('location_id', $data['to_location_id']);
                }
                $destInventory = $destQuery->lockForUpdate()->first();

                if ($destInventory) {
                    $destInventory->update([
                        'quantity' => (int) $destInventory->quantity + $quantity,
                    ]);
                } else {
                    $destInventory = new Inventory();
                    $destInventory->product_id = $productId;
                    if (! empty($data['to_warehouse_id'])) {
                        $destInventory->warehouse_id = $data['to_warehouse_id'];
                    } else {
                        $destInventory->location_id = $data['to_location_id'];
                    }
                    $destInventory->quantity = $quantity;
                    $destInventory->is_saleable = $product->product_type === 'for_sale' ? 1 : 0;
                    $destInventory->save();
                }

                // Source OUT ledger row — keeps Stock-based reports
                // (Stock::sumProductQuantity) net consistent with the
                // inventories cache.
                Stock::create([
                    'account_id' => $accountId,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'stock_type' => 'out',
                    'location_id' => $data['from_location_id'] ?? null,
                    'warehouse_id' => $data['from_warehouse_id'] ?? null,
                ]);

                // Destination IN ledger row — also seeds a FIFO batch at
                // the destination so subsequent sales can draw from it.
                // See the TODO above re: bridging source-batch prices.
                Stock::create([
                    'account_id' => $accountId,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'stock_type' => 'in',
                    'sale_price' => $product->sale_price,
                    'remaining_quantity' => $quantity,
                    'location_id' => $data['to_location_id'] ?? null,
                    'warehouse_id' => $data['to_warehouse_id'] ?? null,
                ]);

                return ['success' => true, 'message' => 'Record has been created successfully.'];
            });
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
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

        $request = new Request($data);
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
        $request = new Request($params);
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
        $request = new Request($params);
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

    private function buildFilteredQuery(array $params): Builder
    {
        $filters = $params['filters'] ?? [];
        $where = [];

        $productId = null;
        if (! empty($filters['name'])) {
            $productId = Product::where('name', 'like', '%'.$filters['name'].'%')->pluck('id');
        }

        // Date filter
        if (! empty($filters['created_at'])) {
            [$startDateTime, $endDateTime] = self::parseDateRangeForFilter($filters['created_at']);
            $where[] = ['created_at', '>=', $startDateTime];
            $where[] = ['created_at', '<=', $endDateTime];
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
            ->when(! empty($where), fn ($q) => $q->where($where))
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
