<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountHasLocations extends Model
{
    protected $fillable = ['discount_id', 'location_id', 'service_id'];

    protected $table = 'discount_has_locations';


    public function discount()
    {
        return $this->belongsTo('App\Models\Discounts', 'discount_id')->withTrashed();
    }

    public function location()
    {
        return $this->belongsTo('App\Models\Locations', 'location_id')->withTrashed();
    }

    public function service()
    {
        return $this->belongsTo('App\Models\Services', 'service_id')->withTrashed();
    }
}
