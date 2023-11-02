<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Stock extends Model
{
    use HasFactory;

    protected $fillable = ['account_id', 'product_id', 'order_id', 'quantity', 'stock_type', 'transfer_id', 'product_detail_id'];

    protected $table = 'stocks';

    /**
     * Get Total Records
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getTotalRecords(Request $request, $account_id = false, $product_id = 0)
    {
        if ($product_id == 0) {
            return self::where('product_id', $product_id)->orderBy('id', 'desc')->count();
        } else {
            return self::orderBy('id', 'desc')->count();
        }
    }

    /**
     * Get SUb of product quantity
     *
     * @param  (int)  $stock_type and  product_id
     * @return (mixed)
     */
    public static function sumProductQuantity($product_id)
    {
        $count_product_in_quantity = self::where('stock_type', 'in')->where('product_id', $product_id)->sum('quantity');
        $count_product_out_quantity = self::where('stock_type', 'out')->where('product_id', $product_id)->sum('quantity');

        return $count_product_in_quantity - $count_product_out_quantity;
    }

   /** Get the patients of order.
    */
   public function product()
   {
       return $this->belongsTo(Product::class, 'product_id');
   }
}
