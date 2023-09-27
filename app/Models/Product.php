<?php

namespace App\Models;

use DateTime;
use App\Helpers\ACL;
use App\Helpers\Filters;
use Illuminate\Http\Request;
use Spatie\Activitylog\LogOptions;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends BaseModal
{
    use LogsActivity, HasFactory;

    protected $fillable = ['name', 'account_id', 'brand_id', 'location_id', 'warehouse_id', 'parent_id', 'sale_price', 'product_type', 'status', 'created_by', 'updated_by'];

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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->dontSubmitEmptyLogs();
    }

    public function productDetail()
    {
        return $this->hasMany(ProductDetail::class, 'product_id');
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function transferProduct()
    {
        return $this->hasMany(TransferProduct::class);
    }

    public function getAvailableStockAttribute()
    {
        $quantityIn = $this->stocks()->where(['stock_type' => 'in'])->sum('quantity');
        $quantityOut = $this->stocks()->where(['stock_type' => 'out'])->sum('quantity');

        return $quantityIn - $quantityOut;
    }

    public function getTotalQuantitySoldAttribute()
    {
        return $this->orderDetails()->sum('quantity');
    }

    public function getAveragePurchaseValueAttribute()
    {
        return $this->productDetail()->avg('purchase_price');
    }

    /**
     * Get Total Records
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getTotalRecords(Request $request, $account_id = false, $apply_filter = false)
    {
        $where = self::lead_sources_filters($request, $account_id, $apply_filter);

        if (count($where)) {
            return self::where($where)
                ->where(function ($query) {
                    $query->whereIn('location_id', ACL::getUserCentres())
                        ->orWhereIn('warehouse_id', ACL::getUserWarehouse());
                })->count();
        } else {
            return self::count();
        }
    }

    /**
     * Get Records
     *
     * @param  (int)  $iDisplayStart Start Index
     * @param  (int)  $iDisplayLength Total Records Length
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false, $apply_filter = false)
    {
        $where = self::lead_sources_filters($request, $account_id, $apply_filter);
        if (count($where)) {
            return self::where($where)
                ->where(function ($query) {
                    $query->whereIn('location_id', ACL::getUserCentres())
                        ->orWhereIn('warehouse_id', ACL::getUserWarehouse());
                })
                ->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('id', 'DESC')->get();
        } else {
            return self::where(function ($query) {
                $query->whereIn('location_id', ACL::getUserCentres())
                    ->orWhereIn('warehouse_id', ACL::getUserWarehouse());
            })
                ->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('id', 'DESC')->get();
        }
    }

    /**
     * Get filters
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function lead_sources_filters($request, $account_id, $search = false)
    {
        $where = [];
        $filters = getFilters($request->all());
        if (hasFilter($filters, 'created_at')) {
            $date_range = explode(' - ', $filters['created_at']);
            $start_date_time = date('Y-m-d H:i:s', strtotime($date_range[0]));
            $end_date_string = new DateTime($date_range[1]);
            $end_date_string->setTime(23, 59, 0);
            $end_date_time = $end_date_string->format('Y-m-d H:i:s');
        } else {
            $start_date_time = null;
            $end_date_time = null;
        }

        if ($search) {
            if (hasFilter($filters, 'name')) {
                $where[] = ['name', 'like', '%' . $filters['name'] . '%'];
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
                $where[][] = ['active' => $filters['status']];
            }
            if (hasFilter($filters, 'created_at')) {
                $where[] = ['created_at', '>=', $start_date_time];
                $where[] = ['created_at', '<=', $end_date_time];
            }
        }

        return $where;
    }

    /**
     * Create Record
     *
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function createRecord($request, $account_id)
    {
        if (!is_array($request)) {
            $data = $request->all();
        } else {
            $data = $request;
        }
        // Set Account ID
        $data['account_id'] = $account_id;
        $data['created_by'] = Auth::user()->id;
        $record = self::create($data);

        $subjectModel = self::find($record->id);
        activityLog(self::$logName, $subjectModel, $request['type'], $record, $request['message']);
        return $record;
    }

    /**
     * Update Record
     *
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function updateRecord($id, $request, $account_id)
    {
        if (!is_array($request)) {
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

        if (!$record) {
            return null;
        }
        $record->update($data);

        $subjectModel = self::find($id);
        activityLog(self::$logName, $subjectModel, $data['type'], $record, $data['message']);

        return $record;
    }

    /**
     * Delete Record
     *
     * @param id
     * @return (mixed)
     */
    public static function DeleteRecord($id, $data = null)
    {
        $product = self::getData($id);
        if (!$product) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }
        // Check if child records exists or not, If exist then disallow to delete it.
        if (self::isChildExists($id, Auth::User()->account_id)) {
            return collect(['status' => false, 'message' => 'Child records exist, unable to delete resource']);
        }
        
        ProductDetail::where('product_id', $id)->delete();
        Stock::where(['product_id' => $product->id])->delete();

        $record = $product->delete();

        $subjectModel = $product;
        activityLog(self::$logName, $subjectModel, $data['type'], $record, $data['message']);

        return collect(['status' => true, 'message' => 'Record has been deleted successfully.']);
    }

    /**
     * Check if child records exist
     *
     * @param  (int)  $id
     * @return (boolean)
     */
    public static function isChildExists($id, $account_id)
    {
        if (
            TransferProduct::where(['product_id' => $id, 'account_id' => $account_id])->orwhere(['child_product_id' => $id])->count() ||
            OrderDetail::where(['product_id' => $id, 'account_id' => $account_id])->count()
        ) {
            return true;
        }
        return false;
    }

    /* * Ajax base result of patient according to id or name
    * */
    public static function getProductsAjax($request, $account_id)
    {
        if (isset($request->from_id)) {
            return self::where([
                ['status', '=', '1'],
                ['account_id', '=', $account_id],
                [$request->from_key, $request->from_id]
            ])->when($request->type == 'order', function ($q) {
                return $q->where(['product_type' => 'for_sale']);
            })->select('id', 'name', 'product_type', 'sale_price', 'warehouse_id', 'location_id')->get();
        } else if (isset($request->product_id)) {
            return self::where([
                ['status', '=', '1'],
                ['account_id', '=', $account_id],
                ['id', $request->product_id],
            ])->when($request->type == 'order', function ($q) {
                return $q->where(['product_type' => 'for_sale']);
            })->select('id', 'name', 'product_type', 'sale_price', 'warehouse_id', 'location_id')->get();
        } else if ($request['request_from'] == 'order') {
            return self::where([
                ['status', '=', '1'],
                ['account_id', '=', $account_id],
                [$request['from_key'], $request['from_id']]
            ])->when(isset($request->type) && $request->type == 'order', function ($q) {
                return $q->where(['product_type' => 'for_sale']);
            })->select('id', 'name', 'product_type', 'sale_price', 'warehouse_id', 'location_id')->get();
        }
    }

    /**
     * Get All Records
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
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

        if (!$product) {

            return false;
        }

        $record = $product->update(['status' => $status]);

        return $record;
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function parent()
    {
        return $this->hasOne(self::class, 'id', 'parent_id');
    }
}
