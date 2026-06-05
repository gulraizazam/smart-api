<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    use HasFactory;

    protected $table = 'feedback';

    protected $fillable = [
        'patient_id',
        'patient_name',
        'patient_phone',
        'doctor_id',
        'appointment_id',
        'service_id',
        'location_id',
        'treatment_id',
        'rating',
        'comment',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'patient_id' => 'integer',
            'doctor_id' => 'integer',
            'appointment_id' => 'integer',
            'service_id' => 'integer',
            'location_id' => 'integer',
            'treatment_id' => 'integer',
            'rating' => 'integer',
            'created_by' => 'integer',
        ];
    }

    /**
     * Feedback has no account_id; isolate route-bound access by the
     * caller's accessible centres (mirrors the datatable's location
     * scoping) so edit/update/destroy can't reach another clinic's
     * feedback by guessing an id (audit 2026-06).
     */
    public function resolveRouteBinding($value, $field = null): ?Feedback
    {
        return $this->where($field ?? $this->getRouteKeyName(), $value)
            ->whereIn('location_id', \App\Helpers\ACL::getUserCentres())
            ->first();
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Locations::class, 'location_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Services::class, 'service_id');
    }

    public function treatment(): BelongsTo
    {
        return $this->belongsTo(Services::class, 'treatment_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointments::class, 'appointment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
