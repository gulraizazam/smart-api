<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\EncryptedLegacy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    use HasFactory;

    protected $table = 'activities';

    protected $fillable = [
        'account_id',
        'plan_id',
        'package_id',
        'appointment_id',
        'action',
        'activity_type',
        'log_tier',
        'description',
        'service',
        'service_id',
        'appointment_type',
        'patient_name',
        'patient',
        'patient_id',
        'received_by',
        'centre_id',
        'user_id',
        'created_by',
        'schedule_date',
        'lead_id',
        'lead_status',
        'lead_status_id',
        'deleted_by',
        'rescheduled_by',
        'deleted_date',
        // Numeric/location columns omitted from earlier fillable list — every
        // Activity::create() that passed them was silently dropping the value.
        // Symptom: dashboard activity feed showed "RCVD Rs. 0" for plan
        // payments (Activity::create wrote amount=NULL → renderer formatted
        // NULL as Rs. 0). Same applies to invoice_id / location / timestamps.
        'amount',
        'location',
        'invoice_id',
        'created_at',
        'updated_at',
    ];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_by' => 'integer',
            'patient_id' => 'integer',
            'centre_id' => 'integer',
            // HIPAA §164.312(a)(2)(iv): encrypt at rest. EncryptedLegacy is
            // forward-compatible with the 397k existing plaintext rows — on
            // read, a failed decrypt falls back to returning the raw value.
            // New writes from this point forward are AES-256-CBC/HMAC.
            //
            // IMPORTANT: description-text LIKE search is no longer reliable
            // (encrypted envelopes don't contain the search term). The
            // ActivityLogService search has been re-routed to clear-text
            // columns: patient, service, action, and the user.name relation.
            'description' => EncryptedLegacy::class,
        ];
    }

    /**
     * The app runs on Asia/Karachi (PKT); the DB-default current_timestamp() is
     * UTC. With $timestamps disabled, a writer that omits created_at/updated_at
     * would fall to that UTC default — leaving the activity feed 5h behind (the
     * ActivityLogObserver / Auth / Permission listeners did exactly that).
     * Stamp PKT now() at the source so every activity row is local wall-clock.
     */
    protected static function booted(): void
    {
        static::creating(function (self $activity): void {
            $now = now()->format('Y-m-d H:i:s');
            $activity->created_at ??= $now;
            $activity->updated_at ??= $now;
        });
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function serviceR(): BelongsTo
    {
        return $this->belongsTo(Services::class, 'service_id');
    }

    public function centre(): BelongsTo
    {
        return $this->belongsTo(Locations::class, 'centre_id');
    }

    public function patientR(): BelongsTo
    {
        return $this->belongsTo(Patients::class, 'patient_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rescheduleBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rescheduled_by');
    }

    public function deleteBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
