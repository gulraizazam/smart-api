<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BundleServicesPriceHistory extends Model
{

    protected $fillable = [
        'bundle_id',
        'bundle_price',
        'bundle_services_price',
        'service_id',
        'service_price',
        'active',
        'effective_from',
        'effective_to',
        'created_by',
        'updated_by',
        'account_id'
    ];

    protected $table = 'bundle_services_price_history';

    /**
     * Get Bundle Service belong to Service.
     */
    public function service()
    {
        return $this->belongsTo('App\Models\Services', 'service_id');
    }

    /**
     * Get Bundle Service belong to Bundle.
     */
    public function bundle()
    {
        return $this->belongsTo('App\Models\Bundles', 'bundle_id');
    }

    /**
     * Create Record
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return (mixed)
     */
    static public function createRecord($data, $account_id)
    {
        // Set Account ID
        $data['account_id'] = $account_id;

        $record = self::create($data);

        return $record;
    }

    /**
     * Update Record
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return (mixed)
     */
    static public function updateRecord($id, $data, $account_id)
    {
        // Set Account ID
        $data['account_id'] = $account_id;

        $record = self::where([
            'id' => $id,
            'account_id' => $account_id
        ])->first();

        if(!$record) {
            return null;
        }

        $record->update($data);

        return $record;
    }
}
