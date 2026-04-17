<?php

declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentsDailyStats extends Model
{
    use HasFactory;

    protected $fillable = [
        'centre_id',
        'user_id',
        'appointment_id',
        'appointment_status_id',
        'scheduled_date',
        'cron_current_date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointments::class, 'appointment_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Locations::class, 'centre_id');
    }
}
