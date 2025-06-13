<?php

namespace App\Models;

use DateTime;
use App\Helpers\ACL;
use App\Helpers\Filters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends BaseModal
{
    use HasFactory;

    protected $fillable = ['patient_id', 'location_id', 'warehouse_id', 'total_price', 'refund_order_id', 'order_type', 'payment_mode', 'created_by', 'updated_by', 'account_id', 'status','quantity','prescribed_by','employee_id','discount'];

    /**
     * Get Total Records
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getTotalRecords(Request $request, $account_id = false, $apply_filter = false, $order_type = 'sale')
    {
        $where = self::general_filters($request, $account_id, $apply_filter);
        $product_id = [];
        if ($request['query'] != null) {
            if ($request['query']['search']['product_id'] != null) {
                $product_id = Product::where('name', 'like', '%' . $request['query']['search']['product_id'] . '%')->pluck('id')->toArray();
            }
        }

        if (count($where)) {
            return self::where($where)
                ->when(($product_id != null), function ($q) use ($product_id) {
                    $q->whereIn('product_id', $product_id);
                })
                ->when(($product_id != null), function ($q) use ($product_id) {
                    return $q->with('orderDetail.product')->whereHas('orderDetail.product', function ($q) use ($product_id) {
                        $q->whereIn('id', $product_id);
                    });
                })
                ->where('order_type', $order_type)->count();
        } else {
            return self::where(function ($query) {
                $query->whereIn('location_id', ACL::getUserCentres());
                    
            })
                ->when(($product_id != null), function ($q) use ($product_id) {
                    return $q->with('orderDetail.product')->whereHas('orderDetail.product', function ($q) use ($product_id) {
                        $q->whereIn('id', $product_id);
                    });
                })
                ->where('order_type', $order_type)->count();
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
        $product_id = [];
        if ($request['query'] != null) {
            if ($request['query']['search']['product_id'] != null) {
                $product_id = Product::where('name', 'like', '%' . $request['query']['search']['product_id'] . '%')->pluck('id');
            }
        }

        if (count($where)) {
            
            return self::with('patients')->where($where)
                ->when(($product_id != null), function ($q) use ($product_id) {
                    return $q->with('orderDetail.product')->whereHas('orderDetail.product', function ($q) use ($product_id) {
                        $q->whereIn('id', $product_id);
                    });
                }, function ($q) {
                    return $q->with('orderDetail.product');
                })
                ->where(function ($query) {
                    $query->whereIn('location_id', ACL::getUserCentres());
                })
               
                ->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('id', 'desc')->get();
        } else {
           
            return self::with('patients')->where($where)
                ->when(($product_id != null), function ($q) use ($product_id) {
                    return $q->with('orderDetail.product')->whereHas('orderDetail.product', function ($q) use ($product_id) {
                        $q->whereIn('id', $product_id);
                    });
                }, function ($q) {
                    return $q->with('orderDetail.product');
                })
                ->where(function ($query) {
                    $query->whereIn('location_id', ACL::getUserCentres());
                })
               
                ->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('id', 'desc')->get();
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

        if ($filters) {
            if (hasFilter($filters, 'order_id')) {
                $where[][] = ['id' => $filters['order_id']];
            }
            if (hasFilter($filters, 'patient_id')) {
                $where[][] = ['patient_id' => $filters['patient_id']];
            }
            if (hasFilter($filters, 'location_type')) {
                if ($filters['location_type'] == 'branch') {
                    $where[][] = ['location_id' => $filters['location']];
                } else if ($filters['location_type'] == 'warehouse') {
                    $where[][] = ['warehouse_id' => $filters['location']];
                } else {
                    Filters::forget(Auth::User()->id, 'location', 'name');
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
    public static function createRecord($request, $account_id,$products)
    {
        $data = $request->all();
        if(isset($data['name']) && isset($data['phone']) && $data['phone'] !=""){
            $patient = Patients::where(['phone' => $data['phone']])->first();
            if(!$patient){
                $newPatient = Patients::create([
                    'name'=>$data['name'],
                    'phone'=>$data['phone'],
                ]);
                $data['patient_id'] = $newPatient->id;
            }else{
                $data['patient_id'] =$patient->id;
            }
           
        }
        $productTotals = [];
        // Iterate through the arrays
        for ($i = 0; $i < count($data['product_id']); $i++) {
            $productId = $data['product_id'][$i];
            $productPrice = floatval($data['product_price'][$i]);
            $quantity = intval($data['quantity'][$i]);
            // Calculate the total for this product
            $total = $productPrice * $quantity;
            // Store the total in the result array, using the product ID as the key
            $productTotals[$productId] = $total;
        }
       
        $location_id = $data['location_id'];
        // Set Account ID
        unset($data['location_id']);
        $data[$data['location_type']] = $location_id;
        $data['account_id'] = $account_id;
        $data['created_by'] = Auth::id();
        $data['total_price'] = $request->grand_total;
        $data['status'] = 1;
       
        $record = new Order();
        $record->account_id = $account_id;
        $record->patient_id = $data['patient_id'] ?$data['patient_id']: $data['employee_id'];
        $record->total_price = $data['total_price'];
        $record->created_by = Auth::id();
        $record->location_id = $data['location_id'];
        $record->payment_mode = $data['payment_mode'];
       $record->quantity = array_sum($products);
       $record->prescribed_by = $data['doctor_id'];
       $record->employee_id = $data['employee_id'] ?? null;
       $record->discount = $data['discount'] ?? 0;
        $record->save();
        //$record = self::create($data);
        
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
        if ($order->order_type == 'refund') {
            $old_order = Order::where(['id' => $order->refund_order_id])->first();
            $old_order->update([
                'refund_order_id' => null,
                'total_price' => $order->total_price,
            ]);
            $old_detail_records = OrderDetail::where('order_id', $id)->get();
            foreach ($old_detail_records as $data) {
                $quantity = $data->quantity;
                $order_detail = OrderDetail::where(['order_id' => $order->refund_order_id, 'product_id' => $data->product_id])->first();
                $order_detail->update([
                    'quantity' => $order_detail->quantity + $quantity,
                ]);
               
            }
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (self::isChildExists($id, Auth::User()->account_id)) {
            return collect(['status' => false, 'message' => 'Child records exist, unable to delete resource']);
        }
        $detail_records = OrderDetail::where('order_id', $id)->get();
        if (!$detail_records->isEmpty()) {
            foreach ($detail_records as $detail_record) {
                $inventory = Inventory::where('product_id', $detail_record->product_id)
                ->where('location_id',$order->location_id)->first();
                $updated_quantity = $inventory->quantity+$detail_record->quantity;
                $inventory->update(['quantity'=>$updated_quantity]);
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

    public static function refund($id, $request)
    {
        $old_order = self::withSum('orderDetail', 'quantity')->find($id);
        $refund_order = self::where("refund_order_id",  $old_order->id)->first();
        $productTotals = [];
        // Iterate through the arrays
        for ($i = 0; $i < count($request['product_id']); $i++) {
            $productId = $request['product_id'][$i];
            $productPrice = floatval($request['product_price'][$i]);
            $quantity = intval($request['quantity'][$i]);
            // Calculate the total for this product
            $total = $productPrice * $quantity;
            // Store the total in the result array, using the product ID as the key
            $productTotals[$productId] = $total;
        }
        if ($old_order) {
            $new_order = $old_order->toArray();
            $new_order['order_type'] = 'refund';

            if ($refund_order) {
                $new_order['updated_by'] = Auth::id();
                $new_order['total_price'] = $refund_order->total_price + array_sum($productTotals);
                unset($new_order['id']);
                unset($new_order['refund_order_id']);
                $refund_order->update($new_order);

                $old_order->update([
                    'total_price' => $old_order->total_price - array_sum($productTotals),
                ]);
                $refund = $refund_order;
            } else {
                $new_order['created_by'] = Auth::id();
                $new_order['refund_order_id'] = $old_order->id;
                $new_order['total_price'] = array_sum($productTotals);
                unset($new_order['id']);
                $old_order->update([
                    'total_price' => $old_order->total_price - array_sum($productTotals),
                ]);

                $refund = self::create($new_order);
            }

            if ($old_order->order_detail_sum_quantity == array_sum($request->quantity)) {
                $old_order->update([
                    'refund_order_id' => $refund->id,
                ]);
            }

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

    public function orderDetail()
    {
        return $this->hasMany(OrderDetail::class, 'order_id')->with('product');
    }

    public static function getRecord($id)
    {
        $record = self::with('orderDetail')->where([
            'id' => $id,
        ])->first();
        $patient = User::where(['id' => $record->patient_id])->first();
        $record->patient_name = $patient->name;
        $record->quantity = Stock::sumProductQuantity($record->orderDetail->product_id);

        return $record;
    }
    public function centre()
    {
        return $this->belongsTo(Locations::class, 'location_id');
    }
    public function doctor()
    {
        return $this->belongsTo(User::class, 'prescribed_by');
    }
}
