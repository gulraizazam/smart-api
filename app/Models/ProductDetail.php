<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductDetail extends BaseModal
{
    use HasFactory;

    protected $fillable = ['account_id', 'product_id', 'purchase_price', 'total_purchase_price', 'quantity'];

    protected $table = 'product_details';

    protected static $_fillable = ['account_id', 'product_id', 'purchase_price', 'total_purchase_price', 'quantity'];

    protected static $_table = 'product_details';

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
        $record = self::create($data);

        $data['product_detail_id'] = $record->id;
        Stock::create($data);

        return $record;
    }

    /**
     * Update Record
     *
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function updateRecord($id, $request, $account_id, $product_id)
    {
        $old_data = (self::find($id))->toArray();

        $data = $request->all();
        $data['account_id'] = $account_id;

        $record = self::where([
            'id' => $id,
            'account_id' => $account_id,
        ])->first();

        if (!$record) {
            return null;
        }

        Stock::where(['product_id' => $product_id, 'product_detail_id' => $id])->update([
            'account_id' => $account_id,
            'quantity' => $data['quantity']
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
    public static function getProductDetailData($id)
    {

        return self::where([
            ['product_id', '=', $id],
            ['account_id', '=', Auth::user()->account_id],
        ])->first();
    }

    public static function createRecordTransferProduct($data, $account_id, $product_id)
    {
        Stock::create([
            'account_id' => $account_id,
            'transfer_id' => $data['transfer_id'],
            'product_id' => $data['child_product_id'],
            'quantity' => $data['quantity'],
            'stock_type' => 'in',
        ]);
        Stock::create([
            'account_id' => $account_id,
            'transfer_id' => $data['transfer_id'],
            'product_id' => $data['id'],
            'quantity' => $data['quantity'],
            'stock_type' => 'out',
        ]);
        $record = self::create([
            'product_id' => $data['child_product_id'],
            'account_id' => $account_id,
            'quantity' => $data['quantity'],
        ]);

        return $record;
    }

    public static function updateRecordTransferProduct($data, $account_id, $product_detail_id)
    {
        Stock::where(['transfer_id' => $data['transfer_id'], 'stock_type' => 'in'])->update([
            'account_id' => $account_id,
            'product_id' => $data['child_product_id'],
            'quantity' => $data['quantity'],
            'stock_type' => 'in',
        ]);
        Stock::where(['transfer_id' => $data['transfer_id'], 'stock_type' => 'out'])->update([
            'account_id' => $account_id,
            'product_id' => $data['id'],
            'quantity' => $data['quantity'],
            'stock_type' => 'out',
        ]);
        self::where(['id' => $product_detail_id])->update([
            'product_id' => $data['child_product_id'],
            'account_id' => $account_id,
            'quantity' => $data['quantity'],
        ]);

        $record = self::where(['id' => $product_detail_id])->first();

        return $record;
    }
}
