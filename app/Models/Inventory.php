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


    /**
     * Count inventory rows, optionally scoped to a single product.
     *
     * Was previously inverted — when $product_id was 0 it filtered
     * `product_id = 0` (matched nothing); when set, it ignored the
     * filter and returned ALL inventory rows. Pagination math
     * downstream was wrong everywhere this was called. Fixed so a
     * 0/falsy product_id means "no filter" and a positive one scopes
     * the count (mirrors Stock::getTotalRecords).
     */
    public static function getTotalRecords($request, $account_id = false, $product_id = 0)
    {
        $query = self::query()->join('products', 'products.id', '=', 'inventories.product_id');

        if ((int) $product_id > 0) {
            $query->where('inventories.product_id', (int) $product_id);
        }

        return $query->count();
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