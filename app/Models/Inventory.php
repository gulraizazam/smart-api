<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    use HasFactory;
    protected $fillable = [
        'product_id',
        'warehouse_id',
        'location_id',
        'is_saleable',
        'quantity',
        'sale_price',
    ];


    public static function getTotalRecords($request, $account_id = false, $product_id = 0)
    {
        if ($product_id == 0) {
            return self::join('products','products.id','inventories.product_id')->where('product_id', $product_id)->orderBy('id', 'desc')->count();
        } else {
            return self::join('products','products.id','inventories.product_id')->count();
        }
    }
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
    public function centre(): BelongsTo
    {
        return $this->belongsTo(Locations::class, 'location_id');
    }
}