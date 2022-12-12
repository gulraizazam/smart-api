<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use App\Models\OrderDetail;
use Auth;

class Order extends BaseModal
{
    use HasFactory;

    protected $fillable = ['account_id','patient_id','total_price','refund_order_id','order_type','created_by'];
    /**
     * Get Total Records
     *
     * @param \Illuminate\Http\Request $request
     * @param (int) $account_id Current Organization's ID
     *
     * @return (mixed)
     */
    static public function getTotalRecords(Request $request, $account_id = false, $apply_filter = false)
    {
        
        $where = Self::general_filters($request, $account_id, $apply_filter);
        
        if (count($where)) {
            return self::where($where)->count();
        } else {
            return self::count();
        }
    }

        /**
     * Get Records
     *
     * @param \Illuminate\Http\Request $request
     * @param (int) $iDisplayStart Start Index
     * @param (int) $iDisplayLength Total Records Length
     * @param (int) $account_id Current Organization's ID
     *
     * @return (mixed)
     */
    static public function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false, $apply_filter = false,$order_type='sale')
    {
        $where = self::general_filters($request, $account_id, $apply_filter);
        $wherein = self::general_filters($request, $account_id, $apply_filter,true);
        if (count($where) && !count($wherein)) {
            return self::with('patients','orders.product')->where($where)->whereNull('refund_order_id')->where('order_type',$order_type)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('id')->get();
        }elseif (count($wherein) && !count($where)) {
            return self::with('patients','orders.product')->whereIn('id',$wherein[1])->whereNull('refund_order_id')->where('order_type',$order_type)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('id')->get();
        }elseif (count($where) && count($wherein)) {
            return self::with('patients','orders.product')->where(function ($query)  use($where,$wherein,$order_type){
                $query->where($where)->whereIn('id',$wherein[1])->where('order_type',$order_type)->whereNull('refund_order_id');
            })->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('id')->get();
        } else {
            return self::with('patients','orders.product')->whereNull('refund_order_id')->where('order_type',$order_type)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('id')->get();
        }
    }

        /**
     * Get filters
     *
     * @param \Illuminate\Http\Request $request
     * @param (int) $account_id Current Organization's ID
     * @return (mixed)
     */
    static public function general_filters($request, $account_id,$search=false,$filter_flag=false)
    {
        $where = array();
        
        if($search != false){
            if(isset($search['patient_id'])){
                $where[] = array(
                    'patient_id',
                    '=',
                    $search['patient_id']
                );
            }
            if($filter_flag == true && isset($search['product_id']) && $search['product_id'] > 0){
                $wherein = array();
                $order_ids=OrderDetail::where('product_id',$search['product_id'])->pluck('order_id')->toArray();
                $wherein =  array('id',$order_ids);
                return $wherein;
            }else if($filter_flag == true){
                $wherein = array();
                return $wherein;
            }
        }
        return $where;
    }

    /**
     * Create Record
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return (mixed)
     */
    static public function createRecord($request, $account_id)
    {
        $data = $request->all();
        $order = $data['data'][0];
        // Set Account ID
        $order['account_id'] = $account_id;
        $order['created_by'] = Auth::id();
        $record = self::create($order);
        return $record;
    }

    /**
     * Delete Record
     *
     * @param id
     *
     * @return (mixed)
     */
    static public function DeleteRecord($id)
    {
        $order = self::getData($id);
        if (!$order) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }
        // Check if child records exists or not, If exist then disallow to delete it.
        if (self::isChildExists($id, Auth::User()->account_id)) {
            return collect(['status' => false, 'message' => 'Child records exist, unable to delete resource']);
        }
        $detail_records=OrderDetail::where('order_id',$id)->get();
        if(!$detail_records->isEmpty()){
            foreach($detail_records as $detail_record){
                $detail_record->delete();
            }
        }
        $record = $order->delete();
        return collect(['status' => true, 'message' => 'Record has been deleted successfully.']);
    }


    /**
     * 
     * 
     */
    static function refund($id){
        $old_order=self::find($id);
        if($old_order){
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
     *
     * @return (mixed)
     */
    static function CancelRecord($id){
        $record = self::where([
            'id' => $id
        ])->first();

        $record->status = 0;
        $record->save();
        return collect(['status' => true, 'message' => 'Record has been canceled successfully.']);
    }



    /**
     * Check if child records exist
     *
     * @param (int) $id
     * @param
     *
     * @return (boolean)
     */
    static public function isChildExists($id, $account_id)
    {
        return false;
    }

    /**
     * Get the patients of order.
     */
    public function patients()
    {
        return $this->belongsTo(Patients::class,'patient_id');
    }

     /**
     * Get the orders detail of order.
     */

    public function orders()
    {
        return $this->hasMany(OrderDetail::class,'order_id');
    }
}
