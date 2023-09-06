<?php

namespace App\Models;

use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends BaseModal
{
    use HasFactory;

    protected $fillable = ['patient_id', 'location_id', 'warehouse_id', 'total_price', 'refund_order_id', 'order_type', 'created_by', 'updated_by', 'account_id'];

    /**
     * Get Total Records
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getTotalRecords(Request $request, $account_id = false, $apply_filter = false)
    {
        $where = self::general_filters($request, $account_id, $apply_filter);

        if (count($where)) {
            return self::where($where)->count();
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
    public static function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false, $apply_filter = false, $order_type = 'sale')
    {
        $where = self::general_filters($request, $account_id, $apply_filter);
        if (count($where)) {
            return self::with('patients', 'orders.product')->where($where)->whereNull('refund_order_id')->where('order_type', $order_type)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('id')->get();
        } else {
            return self::with('patients', 'orders.product')->whereNull('refund_order_id')->where('order_type', $order_type)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('id')->get();
        }
    }

    /**
     * Get filters
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function general_filters($request, $account_id, $search = false, $filter_flag = false)
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
            if (hasFilter($filters, 'order_id')) {
                $where[][] = ['order_id' => $filters['order_id']];
            }
            if (hasFilter($filters, 'patient_id')) {
                $where[][] = ['patient_id' => $filters['patient_id']];
            }
            if (hasFilter($filters, 'product_id')) {
                $where[][] = ['product_id' => $filters['product_id']];
            }
            if (hasFilter($filters, 'location_type')) {
                if ($filters['location_type'] == 'branch') {
                    $where[][] = ['location_id' => $filters['location']];
                } else if ($filters['location_type'] == 'warehouse') {
                    $where[][] = ['warehouse_id' => $filters['location']];
                }
            }
            if (hasFilter($filters, 'created_by')) {
                $where[][] = ['created_by' => $filters['created_by']];
            }
            if (hasFilter($filters, 'updated_by')) {
                $where[][] = ['updated_by' => $filters['updated_by']];
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
        // Set Account ID
        $data['account_id'] = $account_id;
        $data['created_by'] = Auth::id();
        $data['total_price'] = $data['total_price'];
        $record = self::create($data);

        return $record;
    }

    public static function updateRecord($request, $account_id, $id)
    {
        $data = $request->all();
        // Set Account ID
        $data['account_id'] = $account_id;
        $data['updated_by'] = Auth::id();
        $record = self::where([
            'id' => $id,
            'account_id' => $account_id,
        ])->first();

        $record->update($data);

        return $record;
    }

    /**
     * Delete Record
     *
     * @param id
     * @return (mixed)
     */
    public static function DeleteRecord($id)
    {
        $order = self::getData($id);
        if (!$order) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }
        // Check if child records exists or not, If exist then disallow to delete it.
        if (self::isChildExists($id, Auth::User()->account_id)) {
            return collect(['status' => false, 'message' => 'Child records exist, unable to delete resource']);
        }
        $detail_records = OrderDetail::where('order_id', $id)->get();
        if (!$detail_records->isEmpty()) {
            foreach ($detail_records as $detail_record) {
                $detail_record->delete();
            }
        }
        $stock_records = Stock::where('order_id', $id)->get();
        if (!$stock_records->isEmpty()) {
            foreach ($stock_records as $stock_record) {
                $stock_record->delete();
            }
        }
        $record = $order->delete();

        return collect(['status' => true, 'message' => 'Record has been deleted successfully.']);
    }

    public static function refund($id)
    {
        $old_order = self::find($id);
        if ($old_order) {
            $new_order['account_id'] = $old_order->account_id;
            $new_order['patient_id'] = $old_order->patient_id;
            $new_order['total_price'] = $old_order->total_price;
            $new_order['order_type'] = 'refund';
            $new_order['status'] = $old_order->status;
            $new_order['created_by'] = $old_order->created_by;
            $refund = self::create($new_order);
            $old_order->refund_order_id = $refund->id;
            $old_order->save();

            return $refund;
        }

        return false;
    }

    /**
     * Cancel Order
     *
     * @param id
     * @return (mixed)
     */
    public static function CancelRecord($id)
    {
        $record = self::where([
            'id' => $id,
        ])->first();

        $record->status = 0;
        $record->save();

        return collect(['status' => true, 'message' => 'Record has been canceled successfully.']);
    }

    /**
     * Check if child records exist
     *
     * @param  (int)  $id
     * @return (boolean)
     */
    public static function isChildExists($id, $account_id)
    {
        return false;
    }

    /**
     * Get the patients of order.
     */
    public function patients()
    {
        return $this->belongsTo(Patients::class, 'patient_id');
    }

    /**
     * Get the orders detail of order.
     */
    public function orders()
    {
        return $this->hasMany(OrderDetail::class, 'order_id');
    }

    public static function getRecord($id)
    {
        $record = self::with('orders')->where([
            'id' => $id,
        ])->first();
        $patient = User::where(['id' => $record->patient_id])->first();
        $record->patient_name = $patient->name;
        $record->quantity = Stock::sumProductQuantity($record->orders[0]->product_id);

        return $record;
    }
}
