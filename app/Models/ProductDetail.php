<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductDetail extends BaseModal
{
    use HasFactory;

    protected $fillable = ['account_id', 'product_id', 'purchase_price', 'total_purchase_price', 'quantity', 'bulq'];

    protected $table = 'product_details';

    /**
     * Create Record
     *
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function createRecord($request, $account_id, $product_id)
    {
        $data = $request->all();
        // Set Account ID
        $data['account_id'] = $account_id;
        $data['product_id'] = $product_id;
        $record = self::where('product_id', $product_id)->latest()->first();
        if ($record == null) {
            $data['bulq'] = 1;
        } else {
            $data['bulq'] = $record->bulq + 1;
        }
        $data['stock_type'] = 'in';
        $stock = Stock::create($data);
        $record = self::create($data);

        return $record;
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
     * @param (int) $id
     * @return (mixed)
     */
    public static function getProductDetailData($id)
    {

        return self::where([
            ['product_id', '=', $id],
            ['account_id', '=', Auth::user()->account_id],
        ])->first();
    }
}
