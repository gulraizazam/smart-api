<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\GuardsTenantBoundary;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class Stock extends Model
{
    use HasFactory;
    use GuardsTenantBoundary;

    protected $fillable = [
        'account_id', 'product_id', 'order_id', 'quantity', 'stock_type',
        'transfer_id', 'product_detail_id',
        // `location_id` was previously omitted, so Stock::create([...])
        // silently dropped it for callers that didn't fall back to direct
        // property assignment. Per-location availability calculations
        // require the column to be populated on every insert.
        'location_id',
    ];

    protected $table = 'stocks';

    /**
     * Get Total Records.
     *
     * Was previously: when $product_id == 0 it filtered `product_id = 0`
     * (matching nothing); when set, it returned ALL records ignoring the
     * filter. Logic was inverted — fixed so a 0/falsy product_id means
     * "no filter" and a positive one scopes the count.
     *
     * @param  int|false  $account_id  Current organization's ID (reserved
     *                                 for future scoping; legacy callers
     *                                 ignore it).
     */
    public static function getTotalRecords(Request $request, $account_id = false, $product_id = 0)
    {
        $query = self::query();

        if ((int) $product_id > 0) {
            $query->where('product_id', (int) $product_id);
        }

        return $query->count();
    }

    /**
     * Net quantity in the stocks ledger: IN + REFUND − OUT.
     *
     * `refund` is a real stock_type value in the schema enum — when a
     * customer returns a product the corresponding row is logged as
     * `refund`, NOT `in`. The previous implementation summed only `in`
     * minus `out`, so refunded inventory silently disappeared from the
     * available-quantity total.
     *
     * `$locationId` lets callers scope to a specific centre. The legacy
     * default (null) keeps the global behaviour for any caller that
     * really wants company-wide totals — but callers showing per-centre
     * availability MUST pass the centre id, otherwise stock at warehouse
     * A and centre B is mixed into one meaningless number.
     */
    public static function sumProductQuantity(int $product_id, ?int $locationId = null): int
    {
        $query = self::where('product_id', $product_id);
        if ($locationId !== null) {
            $query->where('location_id', $locationId);
        }

        $row = $query
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN stock_type IN ('in','refund') THEN quantity ELSE 0 END), 0) AS additions, "
                ."COALESCE(SUM(CASE WHEN stock_type = 'out' THEN quantity ELSE 0 END), 0) AS removals",
            )
            ->first();

        return (int) (($row->additions ?? 0) - ($row->removals ?? 0));
    }

   /** Get the patients of order.
    */
   public function product(): BelongsTo
   {
       return $this->belongsTo(Product::class, 'product_id');
   }
}
