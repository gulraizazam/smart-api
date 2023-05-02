<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadsServices extends Model
{
    use HasFactory;

    protected $fillable = ['lead_id', 'service_id', 'child_service_id', 'status'];

    public function service()
    {
        return $this->belongsTo('App\Models\Services')->withTrashed();
    }
    public function childservice()
    {
        return $this->belongsTo('App\Models\Services','child_service_id')->withTrashed();
    }
}
