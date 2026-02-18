<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResourceTimeOff extends Model
{
    use SoftDeletes;

    protected $table = 'resource_time_offs';

    protected $fillable = [
        'resource_id',
        'location_id',
        'account_id',
        'type',
        'start_date',
        'start_time',
        'end_time',
        'is_full_day',
        'is_repeat',
        'repeat_until',
        'description',
    ];

    protected $casts = [
        'start_date' => 'date',
        'repeat_until' => 'date',
        'is_full_day' => 'boolean',
        'is_repeat' => 'boolean',
    ];

    /**
     * Get the resource that owns the time off
     */
    public function resource()
    {
        return $this->belongsTo(Resources::class, 'resource_id');
    }

    /**
     * Get the location
     */
    public function location()
    {
        return $this->belongsTo(Locations::class, 'location_id');
    }

    /**
     * Get type label
     */
    public function getTypeLabelAttribute(): string
    {
        $types = [
            'time_off' => 'Time Off',
            'annual_leave' => 'Annual Leave',
            'sick_leave' => 'Sick Leave',
            'personal_leave' => 'Personal Leave',
            'unpaid_leave' => 'Unpaid Leave',
            'other' => 'Other',
        ];

        return $types[$this->type] ?? $this->type;
    }
}
