<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientNote extends Model
{
    use SoftDeletes;

    protected $table = 'patient_notes';

    protected $fillable = [
        'patient_id',
        'created_by',
        'note',
        'is_pinned',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'patient_id' => 'integer',
            'created_by' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patients::class, 'patient_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
