<?php

declare(strict_types=1);

namespace App\Services\Product;

use App\Helpers\ACL;
use App\Helpers\Widgets\LocationsWidget;
use App\Models\Brand;
use App\Models\Inventory;
use App\Models\Locations;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductDetail;
use App\Models\Stock;
use App\Models\TransferProduct;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Reports\Concerns\ParsesDateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

class ProductService
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
        $query = $this->buildFilteredQuery($params);

        if (count($this->buildWhereConditions($params)) > 0) {
            return $query->select('products.*')
                ->orderBy('products.name', 'asc')
                ->offset($params['offset'] ?? 0)
                ->limit($params['limit'] ?? 30)
                ->get();
        }

        return $query->select('products.*')
            ->orderBy('products.name', 'asc')
            ->offset($params['offset'] ?? 0)
            ->limit($params['limit'] ?? 30)
            ->orderBy('id', 'DESC')
            ->get();
    }

    /**
     * Transform product records for datatable display.
     */
    public function transformForDatatable(\Illuminate\Database\Eloquent\Collection $products, array $brands): Collection
    {
        return collect($products)->map(function ($product) use ($brands) {
            $product->brand_id = (array_key_exists($product->brand_id, $brands)) ? $brands[$product->brand_id]->name : 'N/A';
            $product->sale_price = $product->sale_price ?? 'N/A';
            $product->product_type = ucwords(str_replace('_', ' ', $product->product_type));

            return $product;
        });
    }

    // ---------------------------------------------------------------
    // CRUD
    // ---------------------------------------------------------------

    public function find(int $id): ?Product
    {
        return Product::where('id', $id)
            ->where('account_id', Auth::user()->account_id)
            ->first();
    }

    public function create(array $data): Product
    {
        $accountId = Auth::user()->account_id;

        // The slug uniqueness loop that previously sat here referenced a
        // `products.slug` column that doesn't exist in the schema (and
        // isn't in the model's $fillable). The SKU column already enforces
        // its own unique constraint via StoreProductRequest, which is the
        // identifier callers actually need.

        $product = new Product;
        $product->name = $data['name'];
        $product->account_id = $accountId;
        $product->brand_id = $data['brand_id'];
        // `sale_price` and `purchase_price` are NOT NULL columns on the
        // products table but neither is a meaningful catalog-level value:
        // sale price lives on `inventories.sale_price` per location, and
        // purchase price lives on `product_details.purchase_price` per
        // batch. Stamp 0 so the insert satisfies the constraint without
        // pretending the catalog row carries a real price.
        $product->sale_price = $data['sale_price'] ?? 0;
        $product->purchase_price = $data['purchase_price'] ?? 0;
        $product->sku = $data['sku'];
        $product->product_type = 'for_sale';
        $product->created_by = Auth::id();
        $product->save();

        return $product;
    }

    public function update(int $id, array $data): ?Product
    {
        $accountId = Auth::user()->account_id;

        $record = Product::where('id', $id)
            ->where('account_id', $accountId)
            ->first();

        if (! $record) {
            return null;
        }

        $data['account_id'] = $accountId;
        $data['updated_by'] = Auth::id();
        $record->update($data);

        return $record;
    }

    /**
     * @return array{status: bool, message: string}
     */
    public function delete(int $id): array
    {
        $product = $this->find($id);

        if (! $product) {
            return ['status' => false, 'message' => 'Resource not found.'];
        }

        $accountId = Auth::user()->account_id;

        if (
            TransferProduct::where(['product_id' => $id, 'account_id' => $accountId])->orWhere(['child_product_id' => $id])->exists() ||
            OrderDetail::where(['product_id' => $id, 'account_id' => $accountId])->exists()
        ) {
            return ['status' => false, 'message' => 'Child records exist, unable to delete resource'];
        }

        Stock::where(['product_id' => $product->id])->delete();
        ProductDetail::where('product_id', $id)->delete();
        $product->delete();

        return ['status' => true, 'message' => 'Record has been deleted successfully.'];
    }

    /**
     * Bulk delete products.
     *
     * @return array{status: bool, message: string}
     */
    public function bulkDelete(array $ids): array
    {
        $products = Product::getBulkData($ids);

        if ($products->isEmpty()) {
            return ['status' => false, 'message' => 'No records found.'];
        }

        $anyDeleted = false;
        $accountId = Auth::user()->account_id;

        foreach ($products as $product) {
            if (! Product::isChildExists($product->id, $accountId)) {
                Stock::where(['product_id' => $product->id])->delete();
                ProductDetail::where(['product_id' => $product->id])->delete();
                $product->delete();
                $anyDeleted = true;
            }
        }

        if (! $anyDeleted) {
            return ['status' => false, 'message' => 'Child records exist, unable to delete resource!'];
        }

        return ['status' => true, 'message' => 'Records has been deleted successfully!'];
    }

    public function toggleStatus(int $id, int $status): ?Product
    {
        $product = $this->find($id);

        if (! $product) {
            return null;
        }

        $product->update(['status' => $status]);

        return $product;
    }

    // ---------------------------------------------------------------
    // Sale Price
    // ---------------------------------------------------------------

    public function updateSalePrice(int $id, array $data): ?Product
    {
        $product = $this->find($id);

        if (! $product) {
            return null;
        }

        $data['account_id'] = Auth::user()->account_id;
        $data['updated_by'] = Auth::id();
        $product->update($data);

        return $product;
    }

    // ---------------------------------------------------------------
    // Stock Management
    // ---------------------------------------------------------------

    public function addStock(int $productId, array $data): bool
    {
        $accountId = Auth::user()->account_id;

        return DB::transaction(function () use ($data, $accountId, $productId): bool {
            // Resolve the target inventory. Two flows:
            //   - Legacy: caller supplies inventory_id directly.
            //   - Modern: caller supplies centre_id; we look up the existing
            //     inventory at that centre, OR create one when this is the
            //     product's first receipt at that centre. The first-time
            //     case requires `sale_price` so the centre's customer-
            //     facing price is set right alongside the stock receipt
            //     (this is the Allocate flow, folded in).
            $inventory = null;

            if (! empty($data['inventory_id'])) {
                $inventory = Inventory::where('product_id', $productId)
                    ->where('id', $data['inventory_id'])
                    ->first();
            } elseif (! empty($data['centre_id'])) {
                $inventory = Inventory::where('product_id', $productId)
                    ->where('location_id', $data['centre_id'])
                    ->first();

                if (! $inventory) {
                    if (empty($data['sale_price']) || $data['sale_price'] <= 0) {
                        return false;
                    }

                    $inventory = Inventory::create([
                        'product_id'  => $productId,
                        'location_id' => $data['centre_id'],
                        'is_saleable' => 1,
                        'quantity'    => 0,
                        'sale_price'  => $data['sale_price'],
                    ]);
                }
            }

            if (! $inventory) {
                return false;
            }

            // Reject zero/negative receipts up front — they corrupt the
            // ledger (zero rows clutter the audit log, negatives would
            // need a separate flow). The legacy controller validator only
            // checked `< 0`, so a 0-qty submit slipped through.
            $incomingQty = (int) ($data['quantity'] ?? 0);
            if ($incomingQty <= 0) {
                return false;
            }

            // ProductDetail::createRecord reads `inventory_id` from the
            // request payload to link the stock movement back to this
            // inventory row, so make sure the resolved id is in $data
            // (the modern flow only sends `centre_id`).
            $data['inventory_id'] = $inventory->id;

            $request = new Request($data);
            $productDetail = ProductDetail::createRecord($request, $accountId, $productId);

            if (! $productDetail) {
                return false;
            }

            $updates = ['quantity' => $inventory->quantity + $data['quantity']];

            // The SPA always sends sale_price now (required field on the
            // Add stock dialog). Honour it on top-ups too so the operator
            // can correct or refresh a centre's customer-facing price as
            // part of receiving stock — same row, same flow.
            if (! empty($data['sale_price']) && $data['sale_price'] > 0) {
                $updates['sale_price'] = $data['sale_price'];
            }

            $inventory->update($updates);

            return true;
        });
    }

    // ---------------------------------------------------------------
    // Stock & Inventory Detail Datatables
    // ---------------------------------------------------------------

    public function getStockDetailData(int $productId): array
    {
        $rows = Stock::with('product')
            ->where('product_id', $productId)
            ->orderBy('id', 'desc')
            ->get();

        return ['data' => $rows, 'total' => $rows->count()];
    }

    public function getInventoryDetailData(int $productId): array
    {
        $total = Inventory::where('product_id', $productId)->count();
        $data = Inventory::with('product', 'warehouse', 'centre')
            ->where('product_id', $productId)
            ->orderBy('id', 'desc')
            ->get();

        return ['data' => $data, 'total' => $total];
    }

    // ---------------------------------------------------------------
    // Transfer from Product page
    // ---------------------------------------------------------------

    public function getTransferProductData(int $inventoryId): array
    {
        $product = Product::join('inventories', 'products.id', 'inventories.product_id')
            ->select('products.*', 'inventories.warehouse_id', 'inventories.location_id', 'inventories.quantity')
            ->where('inventories.id', $inventoryId)
            ->first();

        $centres = Locations::whereIn('id', ACL::getUserCentres())->pluck('name', 'id');
        $warehouse = Warehouse::whereActive(1)->pluck('name', 'id');

        return [
            'product' => $product,
            'centres' => $centres,
            'warehouse' => $warehouse,
        ];
    }

    /**
     * Process product transfer from product page (with DB transaction).
     */
    public function transferProduct(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $request = new Request($data);
            $request['type'] = 'product_transfer_create';
            $request['message'] = 'Product transfer';

            $transferResult = TransferProduct::createRecord($request, Auth::user()->account_id);

            if (! $transferResult['record']) {
                $message = $transferResult['message'] ?? 'Something went wrong, please try again later.';

                return ['success' => false, 'message' => $message];
            }

            $productDetail = ProductDetail::createRecordTransferProduct($transferResult['data'], Auth::user()->account_id);

            if (! $productDetail) {
                return ['success' => false, 'message' => 'Failed to create product detail.'];
            }

            TransferProduct::where(['id' => $transferResult['record']->id])
                ->update(['product_detail_id' => $productDetail->id]);

            $productType = Product::find($data['product_id']);

            // Resolve source inventory
            if ($data['from_warehouse_id'] ?? null) {
                $minusInventory = Inventory::where('product_id', $data['product_id'])
                    ->where('warehouse_id', $data['from_warehouse_id'])
                    ->lockForUpdate()->first();
            } else {
                $minusInventory = Inventory::where('product_id', $data['product_id'])
                    ->where('location_id', $data['from_location_id'])
                    ->lockForUpdate()->first();
            }

            // Resolve destination inventory
            if ($data['to_warehouse_id'] ?? null) {
                $updateInventory = Inventory::where('product_id', $data['product_id'])
                    ->where('warehouse_id', $data['to_warehouse_id'])
                    ->lockForUpdate()->first();
            } else {
                $updateInventory = Inventory::where('product_id', $data['product_id'])
                    ->where('location_id', $data['to_location_id'])
                    ->lockForUpdate()->first();
            }

            // Deduct from source. Three guards added here:
            //   1. The source inventory row must exist — previously a
            //      missing row crashed on $minusInventory->quantity.
            //   2. Transfer quantity must be positive — zero/negative
            //      transfers are nonsense and would just shuffle audit
            //      noise (or worse, silently restore qty on negatives).
            //   3. The result must not go negative — without this, a
            //      misconfigured request could push inventories below
            //      zero, breaking every downstream availability check.
            $transferQty = (int) ($data['quantity'] ?? 0);

            if (! $minusInventory) {
                throw new \RuntimeException('Source inventory does not exist at the selected origin.');
            }
            if ($transferQty <= 0) {
                throw new \RuntimeException('Transfer quantity must be greater than zero.');
            }
            if ($minusInventory->quantity < $transferQty) {
                throw new \RuntimeException(
                    'Insufficient stock at source (available '.(int) $minusInventory->quantity
                    .', requested '.$transferQty.').'
                );
            }

            $minusInventory->update(['quantity' => $minusInventory->quantity - $transferQty]);

            // Add to destination
            if ($updateInventory) {
                $updateInventory->update(['quantity' => $updateInventory->quantity + $transferQty]);
            } else {
                $inventory = new Inventory;
                $inventory->product_id = $data['product_id'];

                if ($data['to_warehouse_id'] ?? null) {
                    $inventory->warehouse_id = $data['to_warehouse_id'];
                } else {
                    $inventory->location_id = $data['to_location_id'];
                }

                $inventory->quantity = $transferQty;
                $inventory->is_saleable = $productType->product_type === 'for_sale' ? 1 : 0;
                $inventory->save();
            }

            // Append the matching audit rows to the `stocks` ledger so
            // every quantity-changing event is logged. Without this, the
            // ledger silently diverges from inventories.quantity and
            // any availability check that consults stocks (Stock::
            // sumProductQuantity, the inventory reports, validateOrderStock)
            // returns numbers that don't match what the operator sees.
            //
            // The schema's `stocks.location_id` FKs `locations.id`, so we
            // can only log centre↔centre legs. Warehouse-involved transfers
            // skip the ledger writes — that gap stays until the schema
            // grows a polymorphic source/destination.
            $accountId = Auth::user()->account_id;
            $transferId = $transferResult['record']->id;
            $productDetailId = $productDetail->id;
            $sourceCentreId = empty($data['from_warehouse_id']) ? (int) ($data['from_location_id'] ?? 0) : null;
            $destCentreId = empty($data['to_warehouse_id']) ? (int) ($data['to_location_id'] ?? 0) : null;

            if ($sourceCentreId) {
                Stock::create([
                    'account_id'        => $accountId,
                    'product_id'        => (int) $data['product_id'],
                    'product_detail_id' => $productDetailId,
                    'transfer_id'       => $transferId,
                    'location_id'       => $sourceCentreId,
                    'quantity'          => $transferQty,
                    'stock_type'        => 'out',
                ]);
            }
            if ($destCentreId) {
                Stock::create([
                    'account_id'        => $accountId,
                    'product_id'        => (int) $data['product_id'],
                    'product_detail_id' => $productDetailId,
                    'transfer_id'       => $transferId,
                    'location_id'       => $destCentreId,
                    'quantity'          => $transferQty,
                    'stock_type'        => 'in',
                ]);
            }

            return ['success' => true, 'message' => 'Record has been created successfully.'];
        });
    }

    // ---------------------------------------------------------------
    // Logs
    // ---------------------------------------------------------------

    public function getActivityLogs(int $productId): Collection
    {
        $productsLogs = Activity::where(['subject_id' => $productId, 'log_name' => 'product'])
            ->orWhere(['properties->attributes->product_id' => $productId])
            ->orWhere(['properties->attributes->child_product_id' => $productId])
            ->get()->toArray();

        $ids = [];
        $singleLogs = [];
        $pairedLogs = [];
        $batchUuids = [];

        $count = count($productsLogs);

        for ($i = 0; $i < $count; $i++) {
            $foundPair = false;
            for ($j = $i + 1; $j < $count; $j++) {
                if (isset($productsLogs[$i]['batch_uuid'], $productsLogs[$j]['batch_uuid'])) {
                    if ($productsLogs[$i]['batch_uuid'] === $productsLogs[$j]['batch_uuid']) {
                        if (! in_array($productsLogs[$i]['id'], $ids, true) && ! in_array($productsLogs[$j]['id'], $ids, true)) {
                            $pairedLogs[] = [$productsLogs[$i], $productsLogs[$j]];
                            $ids[] = $productsLogs[$i]['id'];
                            $ids[] = $productsLogs[$j]['id'];
                            $batchUuids[] = $productsLogs[$i]['batch_uuid'];
                        }
                        $foundPair = true;
                    }
                }
            }

            if (! $foundPair && ! in_array($productsLogs[$i]['id'], $ids, true) && ! in_array($productsLogs[$i]['batch_uuid'] ?? null, $batchUuids, true)) {
                $singleLogs[] = $productsLogs[$i];
                $ids[] = $productsLogs[$i]['id'];
            }
        }

        $users = User::getAllRecords(Auth::user()->account_id)->getDictionary();
        $brands = Brand::getAllRecordsDictionary(Auth::user()->account_id);
        $centres = Locations::getAllRecordsDictionary(Auth::user()->account_id, 'custom', 'id', 'desc');
        $warehouse = Warehouse::getAllRecordsDictionary(Auth::user()->account_id);
        $products = Product::getAllRecordsDictionary(Auth::user()->account_id);

        $records = [];
        foreach ($singleLogs as $log) {
            $entry = [];
            $entry['log_name'] = $log['log_name'];
            $entry['event'] = $log['event'];
            $entry['subject_id'] = $log['subject_id'];
            $entry['causer_id'] = $log['causer_id'];
            $entry['batch_uuid'] = $log['batch_uuid'];
            $entry['properties']['attributes'] = $log['properties']['attributes'];
            $entry['created_at'] = $log['created_at'];
            $entry['updated_at'] = $log['updated_at'];
            $records[] = $entry;
        }

        $records2 = [];
        foreach ($pairedLogs as $log) {
            $entry = [];
            $entry['log_name'] = $log[0]['log_name'];
            $entry['event'] = $log[0]['event'];
            $entry['subject_id'] = $log[0]['subject_id'];
            $entry['causer_id'] = $log[0]['causer_id'];
            $entry['batch_uuid'] = $log[0]['batch_uuid'];
            $entry['properties']['attributes'] = array_merge($log[0]['properties']['attributes'], $log[1]['properties']['attributes']);
            unset($entry['properties']['attributes']['id']);
            $entry['created_at'] = $log[0]['created_at'];
            $entry['updated_at'] = $log[0]['updated_at'];
            $records2[] = $entry;
        }

        $finalRecords = array_merge($records, $records2);

        return collect($finalRecords)->map(function ($log) use ($productId, $users, $brands, $centres, $warehouse, $products) {
            $properties = $log['properties']['attributes'] ?? null;

            $brandId = $properties['brand_id'] ?? null;
            $locationId = $properties['location_id'] ?? null;
            $warehouseId = $properties['warehouse_id'] ?? null;
            $createdBy = $properties['created_by'] ?? null;
            $updatedBy = $properties['updated_by'] ?? null;
            $toLocationId = $properties['to_location_id'] ?? null;
            $toWarehouseId = $properties['to_warehouse_id'] ?? null;
            $fromLocationId = $properties['from_location_id'] ?? null;
            $fromWarehouseId = $properties['from_warehouse_id'] ?? null;
            $childProductId = $properties['child_product_id'] ?? null;
            $productName = $properties['name'] ?? ($properties['product_id'] ?? null);
            $causerName = (array_key_exists($log['causer_id'], $users)) ? $users[$log['causer_id']]->name : 'N/A';

            $log['product_id'] = $productId;
            $log['product_name'] = (array_key_exists($productName, $products)) ? $products[$properties['product_id']]->name : $productName;
            $log['brand_id'] = (array_key_exists($brandId, $brands)) ? $brands[$brandId]->name : 'N/A';
            $log['location'] = (array_key_exists($locationId, $centres)) ? $centres[$locationId]->name : 'N/A';
            $log['warehouse'] = (array_key_exists($warehouseId, $warehouse)) ? $warehouse[$warehouseId]->name : 'N/A';
            $log['to_location'] = (array_key_exists($toLocationId, $centres)) ? $centres[$toLocationId]->name : 'N/A';
            $log['to_warehouse'] = (array_key_exists($toWarehouseId, $warehouse)) ? $warehouse[$toWarehouseId]->name : 'N/A';
            $log['from_location'] = (array_key_exists($fromLocationId, $centres)) ? $centres[$fromLocationId]->name : 'N/A';
            $log['from_warehouse'] = (array_key_exists($fromWarehouseId, $warehouse)) ? $warehouse[$fromWarehouseId]->name : 'N/A';
            $log['child_product'] = (array_key_exists($childProductId, $products)) ? $products[$childProductId]->name : 'N/A';
            $log['created_by'] = (array_key_exists($createdBy, $users)) ? $users[$createdBy]->name : $causerName;
            $log['updated_by'] = (array_key_exists($updatedBy, $users)) ? $users[$updatedBy]->name : $causerName;

            return $log;
        })->sortByDesc('created_at');
    }

    // ---------------------------------------------------------------
    // Allocation
    // ---------------------------------------------------------------

    public function getLocationAllocation(int $productId): array
    {
        $product = Product::find($productId);
        $location = LocationsWidget::generateDropDownArray(Auth::user()->account_id);

        return [
            'product' => $product,
            'location' => $location,
        ];
    }

    public function saveAllocation(array $data): void
    {
        DB::transaction(function () use ($data) {
            $inventory = new Inventory;
            $inventory->product_id = $data['product_id'];
            $inventory->location_id = $data['location_id'];
            $inventory->is_saleable = 1;
            $inventory->quantity = $data['quantity'];
            $inventory->sale_price = $data['sale_price'];
            $inventory->save();

            $stock = new Stock;
            $stock->account_id = 1;
            $stock->product_id = $data['product_id'];
            $stock->quantity = $data['quantity'];
            $stock->location_id = $data['location_id'];
            $stock->save();
        });
    }

    // ---------------------------------------------------------------
    // Inventory Edit
    // ---------------------------------------------------------------

    public function getEditInventoryData(int $inventoryId): array
    {
        $inventory = Inventory::with('product', 'centre', 'warehouse')->whereId($inventoryId)->first();
        $warehouses = Warehouse::where('active', 1)->get();
        $locations = Locations::where('active', 1)->get();

        return [
            'inventory' => $inventory,
            'warehouse' => $warehouses,
            'locations' => $locations,
        ];
    }

    // ---------------------------------------------------------------
    // Search
    // ---------------------------------------------------------------

    public function searchProducts(string $search): \Illuminate\Database\Eloquent\Collection
    {
        return Product::where('account_id', Auth::user()->account_id)
            ->where('name', 'like', '%'.$search.'%')
            ->select('id', 'name')
            ->limit(20)
            ->get();
    }

    // ---------------------------------------------------------------
    // Create form data
    // ---------------------------------------------------------------

    public function getCreateFormData(): array
    {
        $centres = Locations::whereIn('id', ACL::getUserCentres())->pluck('name', 'id');
        $warehouse = Warehouse::whereActive(1)->pluck('name', 'id');
        $brands = Brand::whereStatus(1)->pluck('name', 'id');

        return [
            'centres' => $centres,
            'warehouse' => $warehouse,
            'brands' => $brands,
        ];
    }

    /**
     * Get edit form data for a product.
     */
    public function getEditFormData(int $id): ?array
    {
        $product = $this->find($id);

        if (! $product) {
            return null;
        }

        $productDetail = ProductDetail::getProductDetailData($product->id);
        $quantity = self::stockCheck($id);

        return [
            'product' => $product,
            'product_detail' => $productDetail,
            'quantity' => $quantity,
        ];
    }

    // ---------------------------------------------------------------
    // Permissions
    // ---------------------------------------------------------------

    public function getPermissions(): array
    {
        return [
            'active' => Gate::allows('product_active'),
            'edit' => Gate::allows('product_edit'),
            'manage' => Gate::allows('product_manage'),
            'delete' => Gate::allows('product_destroy'),
            'create' => Gate::allows('product_create'),
            'sale_price' => Gate::allows('product_sale_price'),
            'add_stock' => Gate::allows('product_add_stock'),
            'stock_detail' => Gate::allows('product_stock_detail'),
            'transfer_product' => Gate::allows('product_transfer'),
            'log' => Gate::allows('product_log'),
        ];
    }

    /**
     * Get filter values for datatable.
     */
    public function getFilterValues(): array
    {
        $accountId = Auth::user()->account_id;

        return [
            'brands' => collect(Brand::getAllRecordsDictionary($accountId))->pluck('name', 'id'),
            'centres' => collect(Locations::getAllRecordsDictionary($accountId, 'custom', 'id', 'desc', ACL::getUserCentres()))->pluck('name', 'id'),
            'warehouse' => collect(Warehouse::getAllRecordsDictionary($accountId, ACL::getUserWarehouse()))->pluck('name', 'id'),
            'status' => config('constants.status'),
            'sku' => Product::pluck('sku')->toArray(),
        ];
    }

    // ---------------------------------------------------------------
    // Private helpers
    // ---------------------------------------------------------------

    private function buildFilteredQuery(array $params): Builder
    {
        $where = $this->buildWhereConditions($params);

        return Product::query()
            ->when(! empty($where), fn ($q) => $q->where($where));
    }

    private function buildWhereConditions(array $params): array
    {
        $where = [];
        // `apply_filter` is the legacy KTDatatable sentinel — set when the
        // user clicked the "Filter" button. Modern SPA callers don't send
        // it, so we no longer use it as a master gate. Each filter below
        // guards itself with hasFilter()/!empty(), which is enough.
        $filters = $params['filters'] ?? [];

        [$startDateTime, $endDateTime] = self::parseDateRangeForFilter(
            hasFilter($filters, 'created_at') ? $filters['created_at'] : null
        );

        if (! empty($filters['name'])) {
            $where[] = ['name', 'like', '%'.$filters['name'].'%'];
        }
        if (! empty($filters['product_type'])) {
            $where[][] = ['product_type' => $filters['product_type']];
        }
        if (! empty($filters['brand_id'])) {
            $where[][] = ['brand_id' => $filters['brand_id']];
        }
        if (! empty($filters['centre_id'])) {
            $where[][] = ['location_id' => $filters['centre_id']];
        }
        if (! empty($filters['warehouse_id'])) {
            $where[][] = ['warehouse_id' => $filters['warehouse_id']];
        }
        if (! empty($filters['status'])) {
            $where[][] = ['active' => $filters['status']];
        }
        if (isset($startDateTime, $endDateTime)) {
            $where[] = ['products.created_at', '>=', $startDateTime];
            $where[] = ['products.created_at', '<=', $endDateTime];
        }

        return $where;
    }

    public static function stockCheck(int|string $id): array
    {
        $in = Stock::where('stock_type', 'in')->where('product_id', $id)->sum('quantity');
        $out = Stock::where('stock_type', 'out')->where('product_id', $id)->sum('quantity');
        $stock_quantity = $in - $out;

        return [
            'stock_quantity' => $stock_quantity,
            'stock_available' => $stock_quantity > 0,
        ];
    }

    /**
     * On-hand quantity for the product at the source location/warehouse.
     *
     * Returns 0 when no inventory row exists for the (product, source)
     * pair — previously it dereferenced `$record->quantity` on a null
     * Eloquent result and crashed the transfer-validation flow. Callers
     * downstream interpret 0 as "out of stock", which is the right
     * behaviour for the "not allocated to this centre yet" case.
     */
    public static function inventoryCheck(mixed $request): int|float
    {
        if ($request->from_location_id) {
            $record = Inventory::where([
                'product_id' => $request->product_id,
                'location_id' => $request->from_location_id,
            ])->first();
        } else {
            $record = Inventory::where([
                'product_id' => $request->product_id,
                'warehouse_id' => $request->from_warehouse_id,
            ])->first();
        }

        return $record ? (int) $record->quantity : 0;
    }

    public static function stockC(int|string $id): int|float
    {
        $in = Stock::where('stock_type', 'in')->where('product_id', $id)->sum('quantity');
        $out = Stock::where('stock_type', 'out')->where('product_id', $id)->sum('quantity');

        return $in - $out;
    }
}
