<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderDetail extends BaseModal
{
    use HasFactory;

    protected $fillable = ['account_id', 'order_id', 'product_id', 'discount_id', 'quantity', 'sale_price', 'discount_price', 'sale_price_after_discount', 'order_type', 'reason'];

    /**
     * Create Record
     *
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function createRecord($request, $account_id, $order_id)
    {
        $data = $request->all();
        foreach ($data['data'] as $order) {
            // Set Account ID and Order ID
            $order['account_id'] = $account_id;
            $order['order_id'] = $order_id;
            $order['stock_type'] = 'out';
            $stock = Stock::create($order);
            $record = self::create($order);
        }

        return true;
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

        // Set Account ID
        $data['account_id'] = $account_id;

        $record = self::where([
            'id' => $id,
            'account_id' => $account_id,
        ])->first();

        if (! $record) {
            return null;
        }

        $record->update($data);

        return $record;

    }

    /**
     * Get Data
     *
     * @param  (int)  $id
     * @return (mixed)
     */
    public static function getDetailData($id)
    {

        return self::with('product', 'discount')->where([
            ['order_id', '=', $id],
            ['account_id', '=', Auth::user()->account_id],
        ])->get();
    }

    public static function refund($id, $new_order_id)
    {

        $old_orders_detail = self::where('order_id', $id)->get();
        foreach ($old_orders_detail as $order_detail) {
            $new_order_detail = [];
            $new_order_detail['account_id'] = $order_detail->account_id;
            $new_order_detail['order_id'] = $new_order_id;
            $new_order_detail['product_id'] = $order_detail->product_id;
            $new_order_detail['discount_id'] = $order_detail->discount_id;
            $new_order_detail['quantity'] = $order_detail->quantity;
            $new_order_detail['sale_price'] = $order_detail->sale_price;
            $new_order_detail['discount_price'] = $order_detail->discount_price;
            $new_order_detail['sale_price_after_discount'] = $order_detail->sale_price_after_discount;
            $new_order_detail['order_type'] = 'refund';
            $new_order_detail['reason'] = $order_detail->reason;
            $refund = self::create($new_order_detail);
            $new_order_detail['stock_type'] = 'in';
            $stock = Stock::create($new_order_detail);
        }
    }

   /** Get the patients of order.
    */
   public function product()
   {
       return $this->belongsTo(Product::class, 'product_id');
   }

    /** Get the patients of order.
     */
    public function discount()
    {
        return $this->belongsTo(Discounts::class, 'discount_id');
    }
}
