<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderRefundDetail extends Model
{
    use HasFactory;
    protected $fillable = [
        'account_id',
        'order_refund_id',
        'product_id',
        'quantity',
        'sale_price',
    ];
    protected $table = 'order_refund_details';

    public static function refund($location_id,$id, $new_order_id, $request, $account_id)
    {
        $data = $request->all();
        $combinedData = array_combine($data['product_id'], $data['product_price']);
        $refund_products = array_combine($data['product_id'], $data['quantity']);

        foreach ($refund_products as $product_id => $quantity) {
            if($quantity > 0){

                    $refund_detail = new OrderRefundDetail();
                    $refund_detail->account_id = Auth::user()->account_id;
                    $refund_detail->order_refund_id = $new_order_id;
                    $refund_detail->product_id = $product_id;
                    $refund_detail->quantity = $quantity;
                    $refund_detail->sale_price = $combinedData[$product_id];
                    $refund_detail->save();
                    $order_detail =  OrderDetail::where('order_id',$id)->where('product_id',$product_id)->first();
                    $update_quantity = $order_detail->quantity - $quantity;
                    //$order_detail->update(['quantity'=>$update_quantity]);
                    $inventory_detail  = Inventory::where('product_id',$product_id)->where('location_id',$location_id)->first();
                    $inventory_updated_quantity =$inventory_detail->quantity + $quantity;
                    $inventory_detail->update(['quantity'=>$inventory_updated_quantity]);

            }

        }
        return true;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
