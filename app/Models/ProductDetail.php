<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductDetail extends BaseModel
{
    use  HasFactory;

    protected $fillable = ['account_id', 'product_id', 'purchase_price', 'total_purchase_price', 'quantity'];

    protected $table = 'product_details';

    protected static $logAttributes = ['product.name', 'product_id', 'purchase_price', 'total_purchase_price', 'quantity'];

    protected static $logName = 'product_detail';

    protected static $recordEvents = ['created', 'updated', 'deleted'];


    // Customize the log description (optional)
    protected static $logDescriptionForEvent = [
        'created' => 'Product has been created',
        'updated' => 'Product has been updated',
        'deleted' => 'Product has been deleted',
    ];

   


    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

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

        // Audit IN row — crm2 shape, no FIFO batch columns. `location_id`
        // pins the receipt to the centre so the order form's available calc
        // (stocks IN − sales, per location) and reports pick it up.
        $inventory = ! empty($data['inventory_id'])
            ? Inventory::find($data['inventory_id'])
            : null;

        Stock::create([
            'account_id' => $account_id,
            'product_id' => $product_id,
            'product_detail_id' => $record->id,
            'quantity' => (int) ($data['quantity'] ?? 0),
            'stock_type' => 'in',
            'location_id' => $inventory?->location_id,
        ]);

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

        $subjectModel = self::find($id);
        
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
        ])->orderBy('id', 'desc')->first();
    }

    public static function createRecordTransferProduct($data, $account_id)
    {
        Stock::create([
            'account_id' => $account_id,
            //'transfer_id' => $data['transfer_id'],
            'product_id' => $data['child_product_id'],
            'quantity' => $data['quantity'],
            'stock_type' => 'in',
        ]);
       
        $record = self::create([
            'product_id' => $data['child_product_id'],
            'account_id' => $account_id,
            'quantity' => $data['quantity'],
        ]);

        $subjectModel = self::find($record->id);
        
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
            'product_id' => $data['parent_id'],
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