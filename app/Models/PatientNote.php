<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
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

    protected $casts = [
        'is_pinned' => 'boolean',
    ];

    /**
     * Get the patient that owns the note.
     */
    public function patient()
    {
        return $this->belongsTo(Patients::class, 'patient_id');
    }

    /**
     * Get the user who created the note.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
