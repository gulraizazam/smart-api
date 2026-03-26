<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadsServices extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id', 'service_id', 'child_service_id', 'status',
        'meta_lead_id', 'lead_status_id', 'consultancy_id',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Services::class);
    }

    public function childservice(): BelongsTo
    {
        return $this->belongsTo(Services::class, 'child_service_id');
    }

    public function leadStatus(): BelongsTo
    {
        return $this->belongsTo(LeadStatuses::class, 'lead_status_id');
    }
}
