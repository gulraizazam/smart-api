<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasFactory;
    protected $guarded =[];


    public static function getTotalRecords($request, $account_id = false, $product_id = 0)
    {
        if ($product_id == 0) {
            return self::join('products','products.id','inventories.product_id')->where('product_id', $product_id)->orderBy('id', 'desc')->count();
        } else {
            return self::join('products','products.id','inventories.product_id')->count();
        }
    }
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
    public function centre()
    {
        return $this->belongsTo(Locations::class, 'location_id');
    }
}