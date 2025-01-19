<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderDetail extends BaseModal
{
    use HasFactory;

    protected $fillable = ['order_id', 'product_id', 'discount_id', 'quantity', 'sale_price', 'discount_price', 'sale_price_after_discount', 'order_type', 'reason', 'account_id'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Create Record
     *
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function createRecord($request, $account_id, $order_id)
    {
        $data = $request->all();

        $combinedData = array_combine($data['product_id'], $data['product_price']);
        $products = array_combine($data['product_id'], $data['quantity']);
        foreach ($products as $product_id => $quantity) {
            $data['product_id'] = $product_id;
            $data['quantity'] = $quantity;
            $data['account_id'] = $account_id;
            $data['order_id'] = $order_id;
            $data['sale_price'] = $combinedData[$product_id];
            $data['stock_type'] = 'out';

           $inventory = Inventory::where('product_id',$product_id)->where('location_id',$request->location_id)->first();
           $updated_quantity = $inventory->quantity-$quantity;
           $inventory ->update(['quantity'=>$updated_quantity]);
            self::create($data);
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
        $data = $request->all();

        // Set Account ID
        $data['account_id'] = $account_id;

        $record = self::where([
            'order_id' => $id,
            'account_id' => $account_id,
        ])->first();

        if (!$record) {
            return null;
        }
        $stock = Stock::where([
            'order_id' => $id,
            'account_id' => $account_id,
        ])->first();

        $stock->update([
            'account_id' => $account_id,
            'product_id' => $data['product_id'],
            'order_id' => $id,
            'quantity' => $data['quantity'],
            'stock_type' => 'out',
        ]);

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

    public static function refund($id, $new_order_id, $request, $account_id)
    {
        $data = $request->all();

        $combinedData = array_combine($data['product_id'], $data['product_price']);
        $refund_products = array_combine($data['product_id'], $data['quantity']);

        foreach ($refund_products as $product_id => $quantity) {
            if($quantity > 0){
                $data['product_id'] = $product_id;
                $data['quantity'] = $quantity;
                $data['account_id'] = $account_id;
                $data['order_id'] = $new_order_id;
                $data['sale_price'] = $combinedData[$product_id];
                $data['stock_type'] = 'in';

                $check_order_detail = self::where(['order_id' => $new_order_id, 'product_id' => $product_id])->first();

                /* old product quantity update */
                $old_orders_detail = self::where(['order_id' => $id, 'product_id' => $product_id])->first();
                $old_orders_detail->quantity = $old_orders_detail->quantity - $quantity;
                $old_orders_detail->save();

                Stock::create($data);
                if ($check_order_detail) {
                    $data['quantity'] = $quantity + $check_order_detail->quantity;
                    $check_order_detail->update($data);
                } else {
                    self::create($data);
                }
            }
        }
        return true;
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

    public static function priceCalculate($request)
    {
        $product = Product::where(['id' => $request->product_id])->first();
        $total_price = $product->sale_price * $request->quantity;
        return $total_price;
    }
}
