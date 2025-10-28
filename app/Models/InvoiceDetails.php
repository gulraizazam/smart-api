<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceDetails extends Model
{
    use SoftDeletes;

    protected $fillable = ['qty', 'discount_type', 'discount_price', 'discount_name', 'service_price', 'net_amount', 'discount_id', 'service_id', 'package_id', 'invoice_id', 'active', 'created_at', 'updated_at', 'deleted_at', 'package_service_id', 'tax_exclusive_serviceprice', 'tax_percenatage', 'tax_price', 'tax_including_price', 'is_exclusive','is_settlement'];

    protected static $_fillable = ['qty', 'discount_type', 'discount_price', 'discount_name', 'service_price', 'net_amount', 'discount_id', 'service_id', 'package_id', 'invoice_id', 'active', 'package_service_id', 'tax_exclusive_serviceprice', 'tax_percenatage', 'tax_price', 'tax_including_price', 'is_exclusive'];

    protected $table = 'invoice_details';

    protected static $_table = 'invoice_details';

    /**
     * Create Record
     *
     * @param  \Illuminate\Http\Request  $request ,$parent_data
     * @return (mixed)
     */
    public static function createRecord($data, $parent_data)
    {
        $record = self::create($data);

        $parent_id = $parent_data->id;

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record, $parent_id);

        return $record;
    }

    /*
     * get the service through relation
     */
    /*Get the user data*/
    public function service()
    {
        return $this->belongsTo('App\Models\Services', 'service_id')->withTrashed();
    }

    public function parentService(): BelongsTo
    {
        return $this->belongsTo('App\Models\Services', 'parent_id', 'service_id');
    }
    public function invoice(): BelongsTo
    {
        return $this->belongsTo('App\Models\Invoices', 'invoice_id');
    }
}
