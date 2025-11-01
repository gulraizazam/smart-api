<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DoctorHasLocations extends Model
{
    protected $fillable = ['user_id', 'location_id', 'service_id', 'end_node','is_allocated'];

    protected $table = 'doctor_has_locations';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
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
