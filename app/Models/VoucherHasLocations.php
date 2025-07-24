<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VoucherHasLocations extends Model
{
    use HasFactory;
    protected $fillable = ['voucher_id', 'location_id', 'service_id'];

    protected $table = 'voucher_has_locations';

    public function voucher()
    {
        return $this->belongsTo('App\Models\Voucher', 'voucher_id')->withTrashed();
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
