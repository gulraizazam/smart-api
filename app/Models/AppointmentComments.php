<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AppointmentComments extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'comment', 'appointment_id', 'created_by','created_at', 'updated_at'
    ];

    protected $table = 'appointment_comments';

    /**
     * Get the appointment that owns the comments.
     */
    public function appointment()
    {
        return $this->belongsTo('App\Models\Appointments');
    }

    /**
     * Get the User that owns the Appointment comment.
     */
    public function user()
    {
        return $this->belongsTo('App\User', 'created_by');
    }
}
