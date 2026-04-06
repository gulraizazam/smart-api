<?php

declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentComments extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'comment', 'appointment_id', 'created_by', 'created_at', 'updated_at',
    ];

    protected $table = 'appointment_comments';

    /**
     * Get the appointment that owns the comments.
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointments::class);
    }

    /**
     * Get the User that owns the Appointment comment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
