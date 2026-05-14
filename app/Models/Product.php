<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\ACL;
use App\Helpers\Filters;
use App\Services\Reports\Concerns\ParsesDateRange;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Product extends BaseModel
{
    use HasFactory;
    use ParsesDateRange;

    protected $fillable = ['name', 'account_id', 'brand_id', 'location_id', 'warehouse_id', 'parent_id', 'sale_price', 'product_type', 'status', 'created_by', 'updated_by', 'sku'];

    protected $table = 'products';

    protected static $logAttributes = ['name', 'account_id', 'brand_id', 'location_id', 'warehouse_id', 'parent_id', 'sale_price', 'product_type', 'status', 'created_by', 'updated_by', 'productDetail.product_id', 'productDetail.purchase_price', 'productDetail.total_purchase_price', 'productDetail.quantity'];

    protected static $logName = 'product';

    protected static $recordEvents = ['created', 'updated', 'deleted'];

    // Customize the log description (optional)
    protected static $logDescriptionForEvent = [
        'created' => 'Product has been created',
        'updated' => 'Product has been updated',
        'deleted' => 'Product has been deleted',
    ];

    // public function productDetail()
    // {
    //     return $this->hasMany(ProductDetail::class, 'product_id');
    // }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function order(): HasMany
    {
        return $this->hasMany(Order::class)->with('orderDetail');
    }

    public function orderDetails(): HasMany
    {
        return $this->hasMany(OrderDetail::class); // A product has many order details
    }

    // public function transferProduct()
    // {
    //     return $this->hasMany(TransferProduct::class);
    // }

    // public function getAvailableStockAttribute()
    // {
    //     $quantityIn = $this->stocks()->where(['stock_type' => 'in'])->sum('quantity');
    //     $quantityOut = $this->stocks()->where(['stock_type' => 'out'])->sum('quantity');

    //     return $quantityIn - $quantityOut;
    // }

    // public function getTotalQuantitySoldAttribute()
    // {
    //     return $this->orderDetails()->sum('quantity');
    // }

    // public function getAveragePurchaseValueAttribute()
    // {
    //     return $this->productDetail()->avg('purchase_price');
    // }

    // /**
    //  * Get Total Records
    //  *
    //  * @param  (int)  $account_id Current Organization's ID
    //  * @return (mixed)
    //  */
    public static function getTotalRecords(Request $request, $account_id = false, $apply_filter = false)
    {
        $where = self::lead_sources_filters($request, $account_id, $apply_filter);

        if (count($where)) {
            return self::where($where)->count();
        } else {
            return self::count();
        }
    }

    // /**
    //  * Get Records
    //  *
    //  * @param  (int)  $iDisplayStart Start Index
    //  * @param  (int)  $iDisplayLength Total Records Length
    //  * @param  (int)  $account_id Current Organization's ID
    //  * @return (mixed)
    //  */
    public static function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false, $apply_filter = false)
    {
        $where = self::lead_sources_filters($request, $account_id, $apply_filter);
        if (count($where)) {
            return self::select('products.*')
                ->where($where)
                ->orderBy('products.name', 'asc')
                ->limit($iDisplayLength)->offset($iDisplayStart)->get();
        } else {
            return self::select('products.*')
                ->orderBy('products.name', 'asc')
            // ->where(function ($query) {
            //     $query->whereIn('inventories.location_id', ACL::getUserCentres())
            //         ->orWhereIn('inventories.warehouse_id', ACL::getUserWarehouse());
            // })
                ->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('id', 'DESC')->get();
        }
    }

    // /**
    //  * Get filters
    //  *
    //  * @param  \Illuminate\Http\Request  $request
    //  * @param  (int)  $account_id Current Organization's ID
    //  * @return (mixed)
    //  */
    public static function lead_sources_filters($request, $account_id, $search = false)
    {
        $where = [];
        $filters = getFilters($request->all());
        [$start_date_time, $end_date_time] = self::parseDateRangeForFilter(
            hasFilter($filters, 'created_at') ? $filters['created_at'] : null
        );

        if ($search) {
            if (hasFilter($filters, 'name')) {
                $where[] = ['name', 'like', '%'.$filters['name'].'%'];
            }
            if (hasFilter($filters, 'product_type')) {
                $where[][] = ['product_type' => $filters['product_type']];
            }
            if (hasFilter($filters, 'brand_id')) {
                $where[][] = ['brand_id' => $filters['brand_id']];
            }
            if (hasFilter($filters, 'centre_id')) {
                $where[][] = ['location_id' => $filters['centre_id']];
            }
            if (hasFilter($filters, 'warehouse_id')) {
                $where[][] = ['warehouse_id' => $filters['warehouse_id']];
            }
            if (hasFilter($filters, 'status')) {
                // `products` carries both legacy `active` and the modern
                // `status` columns; toggleStatus writes to `status`, so the
                // filter has to read from the same column or it desynchs.
                $where[][] = ['status' => $filters['status']];
            }
            if (hasFilter($filters, 'created_at')) {
                $where[] = ['products.created_at', '>=', $start_date_time];
                $where[] = ['products.created_at', '<=', $end_date_time];
            }
        }

        return $where;
    }

    // /**
    //  * Create Record
    //  *
    //  * @param  \Illuminate\Http\Request  $request
    //  * @return (mixed)
    //  */
    public static function createRecord($request, $account_id)
    {
        if (! is_array($request)) {
            $data = $request->all();
        } else {
            $data = $request;
        }

        // Set Account ID
        $data['account_id'] = $account_id;
        $data['created_by'] = Auth::user()->id;

        // Generate unique slug from name
        $slug = Str::slug($data['name']);
        $originalSlug = $slug;
        $counter = 1;
        while (self::where('slug', $slug)->exists()) {
            $slug = $originalSlug.'-'.$counter;
            $counter++;
        }

        $product = new Product;
        $product->name = $data['name'];
        $product->slug = $slug;
        $product->account_id = $data['account_id'];
        $product->brand_id = $data['brand_id'];
        $product->sale_price = $data['sale_price'];
        $product->sku = $data['sku'];
        $product->product_type = 'for_sale';
        $product->created_by = Auth::user()->id;
        $product->save();

        //      $subjectModel = self::find($product->id);

        return $product;
    }

    /**
     * Update Record
     *
     * @param  Request  $request
     * @return (mixed)
     */
    public static function updateRecord($id, $request, $account_id)
    {

        if (! is_array($request)) {
            $data = $request->all();
        } else {
            $data = $request;
        }
        $data['account_id'] = $account_id;
        $data['updated_by'] = Auth::user()->id;

        $record = self::where([
            'id' => $id,
            'account_id' => $account_id,
        ])->first();

        if (! $record) {
            return null;
        }

        $record->update($data);

        //     $subjectModel = self::find($id);

        return $record;
    }

    // /**
    //  * Delete Record
    //  *
    //  * @param id
    //  * @return (mixed)
    //  */
    public static function DeleteRecord($id, $data = null)
    {
        $product = self::getData($id);
        if (! $product) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }
        // Check if child records exists or not, If exist then disallow to delete it.
        if (self::isChildExists($id, Auth::user()->account_id)) {
            return collect(['status' => false, 'message' => 'Child records exist, unable to delete resource']);
        }
        Stock::where(['product_id' => $product->id])->delete();
        ProductDetail::where('product_id', $id)->delete();
        $record = $product->delete();

        $subjectModel = $product;

        return collect(['status' => true, 'message' => 'Record has been deleted successfully.']);
    }

    // /**
    //  * Check if child records exist
    //  *
    //  * @param  (int)  $id
    //  * @return (boolean)
    //  */
    public static function isChildExists($id, $account_id)
    {
        // Group the product_id/child_product_id alternative under the
        // account scope. The previous form had the `orWhere` outside the
        // account_id constraint, so it matched `child_product_id = $id`
        // across ALL accounts — a cross-tenant leak that could block
        // (or wrongly approve) a delete based on another organisation's
        // transfer history.
        $hasTransfer = TransferProduct::where('account_id', $account_id)
            ->where(function ($q) use ($id) {
                $q->where('product_id', $id)
                    ->orWhere('child_product_id', $id);
            })
            ->exists();

        if ($hasTransfer) {
            return true;
        }

        return OrderDetail::where('account_id', $account_id)
            ->where('product_id', $id)
            ->exists();
    }

    /* * Ajax base result of patient according to id or name
    * */
    public static function getProductsAjax($request, $account_id)
    {
        if (isset($request->from_id)) {
            // Use same calculation logic as inventory report (stocks table)
            $location_id = $request->from_id;

            // Get products that have inventory at this location
            $productIds = DB::table('inventories')
                ->where('location_id', $location_id)
                ->distinct()
                ->pluck('product_id');

            $products = DB::table('products')
                ->whereIn('products.id', $productIds)
                ->where('products.status', '1')
                ->where('products.account_id', $account_id)
                ->when($request->type == 'order', fn ($q) => $q->where('products.product_type', 'for_sale'))
                ->orderBy('products.name', 'asc')
                ->get();

            $result = collect();

            foreach ($products as $product) {
                // Calculate available quantity: stocks(IN) - order_details(sales)
                $totalAdditions = DB::table('stocks')
                    ->where('product_id', $product->id)
                    ->where('location_id', $location_id)
                    ->where('stock_type', 'in')
                    ->sum('quantity');

                $totalSales = DB::table('order_details')
                    ->join('orders', 'orders.id', '=', 'order_details.order_id')
                    ->where('order_details.product_id', $product->id)
                    ->where('orders.location_id', $location_id)
                    ->sum('order_details.quantity');

                $totalAvailable = $totalAdditions - $totalSales;

                if ($totalAvailable <= 0) {
                    continue;
                }

                // Get first inventory for price (ignore inventory quantity)
                $inventory = DB::table('inventories')
                    ->where('product_id', $product->id)
                    ->where('location_id', $location_id)
                    ->orderBy('created_at', 'desc')
                    ->first();

                $salePrice = $inventory ? ($inventory->sale_price ?? $product->sale_price) : $product->sale_price;

                $result->push((object) [
                    'inventory_id' => $inventory?->id,
                    'id' => $product->id,
                    'name' => $product->name,
                    'product_type' => $product->product_type,
                    'sale_price' => $salePrice,
                    'location_id' => $location_id,
                    'available_quantity' => $totalAvailable,
                    'inventory_date' => $inventory?->created_at,
                ]);
            }

            return $result->values();
        } elseif (isset($request->product_id)) {
            return self::join('inventories', 'products.id', 'inventories.product_id')->where([
                ['products.status', '=', '1'],
                ['products.account_id', '=', $account_id],
                ['products.id', $request->product_id],
            ])->when($request->type == 'order', fn ($q) => $q->where(['product_type' => 'for_sale']))->select('products.id', 'products.name', 'products.product_type', 'inventories.sale_price', 'inventories.warehouse_id', 'inventories.location_id', 'inventories.id as inventory_id', 'inventories.quantity as available_quantity')->get();
        } elseif ($request['request_from'] == 'order') {
            return self::join('inventories', 'products.id', 'inventories.product_id')->where([
                ['products.status', '=', '1'],
                ['products.account_id', '=', $account_id],
                [$request['from_key'], $request['from_id']],
            ])->when(isset($request->type) && $request->type == 'order', fn ($q) => $q->where(['product_type' => 'for_sale']))->select('products.id', 'products.name', 'products.product_type', 'inventories.sale_price', 'inventories.warehouse_id', 'inventories.location_id', 'inventories.id as inventory_id', 'inventories.quantity as available_quantity')->get();
        }
    }

    public static function getTransferProductsAjax($request, $account_id)
    {
        if ($request->location_id) {
            $inventories = Inventory::where([
                'location_id' => $request->location_id,
                'product_id' => $request->product_id,
            ])->get();

            // Calculate the total available quantity across all inventories
            $totalAvailableQuantity = $inventories->sum('quantity');

            // Calculate the total quantity sold
            $totalSoldQuantity = DB::table('order_details')
                ->join('orders', 'order_details.order_id', '=', 'orders.id')
                ->where('order_details.product_id', $request->product_id)
                ->where('orders.location_id', $request->location_id)
                ->sum('order_details.quantity');

            // Calculate the updated total quantity
            $updatedQuantity = max(0, $totalAvailableQuantity - $totalSoldQuantity);

            // Set the updated quantity in the first inventory object
            $primaryInventory = $inventories->first();
            if ($primaryInventory) {
                $primaryInventory->quantity = $updatedQuantity;
            }

            // Return the updated inventory object
            return $primaryInventory;

        } else {
            $inventory = Inventory::where([
                'warehouse_id' => $request->warehouse_id, 'product_id' => $request->product_id])
                ->first();
        }

        return $inventory;

    }

    // /**
    //  * Get All Records
    //  *
    //  * @param  (int)  $account_id Current Organization's ID
    //  * @return (mixed)
    //  */
    public static function getAllRecordsDictionary($account_id)
    {
        return self::where(['account_id' => $account_id])->get()->getDictionary();
    }

    /**
     * active Record
     *
     * @param id
     * @return (mixed)
     */
    public static function activeRecord($id, $status = 1)
    {
        $product = self::getData($id);

        if (! $product) {

            return false;
        }

        $record = $product->update(['status' => $status]);

        return $record;
    }

    // public function children()
    // {
    //     return $this->hasMany(self::class, 'parent_id');
    // }

    public function parent(): HasOne
    {
        return $this->hasOne(self::class, 'id', 'parent_id');
    }

    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class, 'product_id');
    }
}
