<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadsServices extends Model
{
    use HasFactory;

    protected $table = 'leads_services';

    protected $fillable = [
        'lead_id', 'service_id', 'child_service_id', 'status',
        'meta_lead_id', 'lead_status_id', 'consultancy_id',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'lead_id' => 'integer',
            'service_id' => 'integer',
            'child_service_id' => 'integer',
            'status' => 'integer',
            'lead_status_id' => 'integer',
            'consultancy_id' => 'integer',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

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
