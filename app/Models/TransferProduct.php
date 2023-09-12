<?php

namespace App\Models;

use DateTime;
use App\Helpers\ACL;
use App\Helpers\Filters;
use Illuminate\Http\Request;
use App\Models\ProductDetail;
use Spatie\Activitylog\LogOptions;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TransferProduct extends BaseModal
{
    use LogsActivity, HasFactory;

    protected $fillable = ['product_id', 'child_product_id', 'product_detail_id', 'account_id', 'from_location_id', 'to_location_id', 'from_warehouse_id', 'to_warehouse_id', 'quantity', 'transfer_date', 'created_by', 'updated_by'];

    protected $table = 'transfer_products';

    protected static $logAttributes = ['product_id', 'child_product_id', 'product_detail_id', 'account_id', 'from_location_id', 'to_location_id', 'from_warehouse_id', 'to_warehouse_id', 'quantity', 'transfer_date', 'created_by', 'updated_by'];

    protected static $logName = 'transfer_product';

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
            ->useLogName(self::$logName)
            ->logOnly(self::$logAttributes)
            ->setDescriptionForEvent(fn (string $eventName) => self::$logDescriptionForEvent[$eventName])
            ->dontSubmitEmptyLogs();
    }

    public function transferProductItem()
    {
        return $this->hasmany(TransferProductItems::class, 'transfer_product_id');
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

        $product_id = [];
        if ($request['query'] != null) {
            if ($request['query']['search']['name'] != null) {
                $product_id = Product::where('name', 'like', '%' . $request['query']['search']['name'] . '%')->get()->pluck('id');
            }
        }
        if (count($where)) {
            return self::where($where)->when($product_id != null, function ($q) use ($product_id) {
                $q->whereIn('product_id', $product_id);
            })->count();
        } else {
            return self::when($product_id != null, function ($q) use ($product_id) {
                $q->whereIn('product_id', $product_id);
            })->count();
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
        $product_id = null;

        if ($request['query'] != null) {
            if ($request['query']['search']['name'] != null) {
                $product_id = Product::where('name', 'like', '%' . $request['query']['search']['name'] . '%')->get()->pluck('id');
            }
        }
        if (count($where)) {
            return self::where($where)->when($product_id != null, function ($q) use ($product_id) {
                return $q->whereIn('product_id', $product_id);
            })->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('id')->get();
        } else {
            return self::when($product_id != null, function ($q) use ($product_id) {
                return $q->whereIn('product_id', $product_id);
            })->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('id')->get();
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
            if (hasFilter($filters, 'location_from') && hasFilter($filters, 'transfer_from')) {
                if ($filters['location_from'] == 'branch') {
                    $where[][] = ['from_location_id' => $filters['transfer_from']];
                } elseif ($filters['location_from'] == 'warehouse') {
                    $where[][] = ['from_warehouse_id' => $filters['transfer_from']];
                }
            }
            if (hasFilter($filters, 'location_to') && hasFilter($filters, 'transfer_to')) {
                if ($filters['location_to'] == 'branch') {
                    $where[][] = ['to_location_id' => $filters['transfer_to']];
                } elseif ($filters['location_to'] == 'warehouse') {
                    $where[][] = ['to_warehouse_id' => $filters['transfer_to']];
                }
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
        $data = $request->all();
        $data['account_id'] = $account_id;
        $data['created_by'] = Auth::user()->id;
        //dd($request->all());
        $record = null;
        $message = null;
        $parent_product_id = $request->product_id;
        if ($request->product_type_option_from == 'in_warehouse') {
            $from_key = "warehouse_id";
            $from_value = $request->from_warehouse_id;
        } else {
            $from_key = "location_id";
            $from_value = $request->from_location_id;
        }
        if ($request->product_type_option_to == 'in_warehouse') {
            $to_key = "warehouse_id";
            $to_value = $request->to_warehouse_id;
        } else {
            $to_key = "location_id";
            $to_value = $request->to_location_id;
        }
        $product = Product::where(['id' => $parent_product_id, $from_key => $from_value])->first();

        $data2['id'] = $parent_product_id;
        $data2['name'] = $product->name;
        $data2['brand_id'] = $product->brand_id;
        $data2['sale_price'] = $product->sale_price;
        $data2['product_type'] = $product->product_type;
        $data2['warehouse_id'] = $request->to_warehouse_id;
        $data2['location_id'] = $request->to_location_id;
        $data2['quantity'] = $request->quantity;
        $data2['status'] = $product->status;
        $data2['parent_id'] = $product->id;
        $data2['account_id'] = $account_id;
        $data2['transfer_date'] = $request->transfer_date;

        $product_quantity = Stock::sumProductQuantity($parent_product_id);
        //dd($request->quantity <= $product_quantity);
        if ($request->quantity <= $product_quantity) {
            $check_product = Product::where([$to_key => $to_value, 'parent_id' => $request->product_id])->first();
            if ($check_product) {
                $product = $check_product;
            } else {
                $product = Product::createRecord($data2, $account_id);
            }
            $data['child_product_id'] = $product->id;
            $data2['child_product_id'] = $product->id;

            $record = self::create($data);

            $data2['transfer_id'] = $record->id;
            //dd($data, $product, 'check_product', $check_product, $key, $from_value, $to_value);
        } else {
            $message = "Out of stock quantity product.";
        }

        return [
            'record' => $record,
            'data' => $data2,
            'message' => $message
        ];
    }

    /**
     * Update Record
     *
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function updateRecord($id, $request, $account_id)
    {
        $old_data = (self::find($id))->toArray();

        $data = $request->all();
        unset($data['_method']);

        $data['account_id'] = $account_id;
        $data['updated_by'] = Auth::user()->id;

        $record = null;
        $message = null;
        $parent_product_id = $request->product_id;
        if ($request->product_type_option_from == 'in_warehouse') {
            $from_key = "warehouse_id";
            $from_value = $request->from_warehouse_id;
        } else {
            $from_key = "location_id";
            $from_value = $request->from_location_id;
        }
        if ($request->product_type_option_to == 'in_warehouse') {
            $to_key = "warehouse_id";
            $to_value = $request->to_warehouse_id;
        } else {
            $to_key = "location_id";
            $to_value = $request->to_location_id;
        }
        $product = Product::where(['id' => $request->product_id])->first();

        unset($data['product_type_option_from']);
        unset($data['product_type_option_to']);
        $data2['id'] = $parent_product_id;
        $data2['name'] = $product->name;
        $data2['brand_id'] = $product->brand_id;
        $data2['sale_price'] = $product->sale_price;
        $data2['product_type'] = $product->product_type;
        $data2['warehouse_id'] = $request->to_warehouse_id;
        $data2['location_id'] = $request->to_location_id;
        $data2['quantity'] = $request->quantity;
        $data2['status'] = $product->status;
        $data2['parent_id'] = $product->id;
        $data2['account_id'] = $account_id;
        $data2['transfer_date'] = $request->transfer_date;
        $data2['transfer_id'] = $id;


        $product_quantity = Stock::sumProductQuantity($parent_product_id);
        //dd($request->quantity <= $product_quantity);
        if ($request->quantity <= $product_quantity) {
            $check_product = Product::where([$to_key => $to_value, 'id' => $request->child_product_id])->first();

            if ($check_product) {
                $product_update = Product::updateRecord($request->child_product_id, $data2, $account_id);
            } else {
                $product_update = Product::createRecord($request->child_product_id, $data2, $account_id);
            }
            $data['child_product_id'] = $product_update->id;
            $data2['child_product_id'] = $product_update->id;
            self::where(['id' => $id])->update($data);
            //dd($data, $product, 'check_product', $check_product, $key, $from_value, $to_value);
        } else {
            $message = "Out of stock quantity product.";
        }
        $record = self::where(['id' => $id])->first();
        $data2['product_detail_id'] = $record->product_detail_id;
        return [
            'record' => $record,
            'data' => $data2,
            'message' => $message
        ];
    }

    /**
     * Delete Record
     *
     * @param id
     * @return (mixed)
     */
    public static function DeleteRecord($id)
    {
        $transfer_product = self::getData($id);
        if (!$transfer_product) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }
        // Check if child records exists or not, If exist then disallow to delete it.
        if (self::isChildExists($id, Auth::User()->account_id)) {
            return collect(['status' => false, 'message' => 'Child records exist, unable to delete resource']);
        }
        $detail_p_records = Product::where('id', $transfer_product->product_id)->get();
        $detail_p_records->delete();
        $detail_records = ProductDetail::where('product_id', $transfer_product->product_id)->get();
        if (!$detail_records->isEmpty()) {
            foreach ($detail_records as $detail_record) {
                $detail_record->delete();
            }
        }
        $record = $transfer_product->delete();

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
        $product_details = ProductDetail::where(['id' => $id, 'account_id' => $account_id])->get();
        if ($product_details) {
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
                [$request->from_key, $request->from_id],
            ])->select('id', 'name', 'product_type', 'sale_price', 'warehouse_id', 'location_id')->get();
        }
        if (isset($request->product_id)) {
            return self::where([
                ['status', '=', '1'],
                ['account_id', '=', $account_id],
                ['id', $request->product_id],
            ])->select('id', 'name', 'product_type', 'sale_price', 'warehouse_id', 'location_id')->get();
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

    public static function childLocation($id)
    {
        $centres = Locations::getAllRecordsDictionary(Auth::user()->account_id, 'custom', 'id', 'desc', ACL::getUserCentres());
        $warehouse = Warehouse::getAllRecordsDictionary(Auth::user()->account_id);
        $transfer_product = TransferProduct::where(['id' => $id])->first();
        return ($transfer_product->to_location_id != null) ? ((array_key_exists($transfer_product->to_location_id, $centres)) ? $centres[$transfer_product->to_location_id]->name : 'N/A') : ((array_key_exists($transfer_product->to_warehouse_id, $warehouse)) ? $warehouse[$transfer_product->to_warehouse_id]->name : 'N/A');
    }

    public static function parentLocation($id)
    {
        $centres = Locations::getAllRecordsDictionary(Auth::user()->account_id, 'custom', 'id', 'desc', ACL::getUserCentres());
        $warehouse = Warehouse::getAllRecordsDictionary(Auth::user()->account_id);
        $transfer_product = TransferProduct::where(['id' => $id])->first();
        return ($transfer_product->from_location_id != null) ? ((array_key_exists($transfer_product->from_location_id, $centres)) ? $centres[$transfer_product->from_location_id]->name : 'N/A') : ((array_key_exists($transfer_product->from_warehouse_id, $warehouse)) ? $warehouse[$transfer_product->from_warehouse_id]->name : 'N/A');
    }
}
