<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderDetail extends BaseModel
{
    use HasFactory;

    protected $fillable = ['order_id', 'product_id', 'inventory_id', 'discount_id', 'quantity', 'sale_price', 'discount_price', 'sale_price_after_discount', 'order_type', 'reason', 'account_id'];

    public function order(): BelongsTo
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
        return DB::transaction(function () use ($request, $account_id, $order_id) {
            $data = $request->all();

            $discount = $data['discount'] ?? 0;
            $inventory_ids = is_array($data['inventory_id'] ?? []) ? ($data['inventory_id'] ?? []) : [$data['inventory_id']];
            $quantities = is_array($data['quantity']) ? $data['quantity'] : [$data['quantity']];
            $product_prices = is_array($data['product_price']) ? $data['product_price'] : [$data['product_price']];
            $product_ids = is_array($data['product_id']) ? $data['product_id'] : [$data['product_id']];
            
            foreach ($product_ids as $index => $product_id) {
                $quantity = $quantities[$index] ?? 1;
                $originalPrice = $product_prices[$index] ?? 0;
                $inventory_id = $inventory_ids[$index] ?? null;
                
                $data['product_id'] = $product_id;
                $data['quantity'] = $quantity;
                $data['account_id'] = $account_id;
                $data['order_id'] = $order_id;
                $data['inventory_id'] = $inventory_id;
                
                $discountAmount = $discount > 0 ? ($originalPrice * ($discount / 100)) : 0;
                $discountedPrice = $originalPrice - $discountAmount;

                // The legacy code only set `sale_price` to the discounted
                // value, but `sale_price_after_discount` is NOT NULL on the
                // schema with no default — inserts blew up at MariaDB.
                // Stamp both, plus `discount_price` (the per-unit discount
                // amount) so the row carries every column the report
                // queries downstream expect.
                $data['sale_price'] = $originalPrice;
                $data['discount_price'] = $discountAmount;
                $data['sale_price_after_discount'] = $discountedPrice;
                $data['order_type'] = 'sale';
                $data['stock_type'] = 'out';

                // Deduct from specific inventory entry (FIFO) with row locking
                if ($inventory_id) {
                    $inventory = Inventory::lockForUpdate()->find($inventory_id);
                    if ($inventory) {
                        $updated_quantity = $inventory->quantity - $quantity;
                        $inventory->update(['quantity' => $updated_quantity]);
                    }
                } else {
                    // Legacy: Deduct from first available inventory at location
                    $inventory = Inventory::where('product_id', $product_id)
                        ->where('location_id', $request->location_id)
                        ->where('quantity', '>', 0)
                        ->orderBy('created_at', 'asc')
                        ->lockForUpdate()
                        ->first();
                    if ($inventory) {
                        $updated_quantity = $inventory->quantity - $quantity;
                        $inventory->update(['quantity' => $updated_quantity]);
                    }
                }
                
                self::create($data);
            }
            return true;
        });
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
                $data['order_type'] = 'refund';
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
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /** Get the patients of order.
     */
    public function discount(): BelongsTo
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
