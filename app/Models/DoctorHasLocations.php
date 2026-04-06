<?php

declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorHasLocations extends Model
{
    protected $fillable = ['user_id', 'location_id', 'service_id', 'end_node','is_allocated'];

    protected $table = 'doctor_has_locations';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Locations::class, 'location_id')->withTrashed();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Services::class, 'service_id')->withTrashed();
    }
}
