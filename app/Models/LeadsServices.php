<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadsServices extends Model
{
    use HasFactory;

    protected $fillable = ['lead_id', 'service_id', 'child_service_id', 'status', 'meta_lead_id', 'lead_status_id', 'consultancy_id'];

    public function service()
    {
        return $this->belongsTo('App\Models\Services');
    }

    public function childservice()
    {
        return $this->belongsTo('App\Models\Services', 'child_service_id');
    }

    public function leadStatus()
    {
        return $this->belongsTo('App\Models\LeadStatuses', 'lead_status_id');
    }
}
