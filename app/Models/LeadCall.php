<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One row per outbound/inbound call attempt against a lead.
 *
 * Written by LeadCallController@initiate on the SPA's "Call" click, then
 * mutated in place by the Plivo webhook handlers as the call rings, answers,
 * and hangs up. The recording (once Plivo produces it) lives on the 1:1
 * {@see LeadRecording} sibling.
 */
class LeadCall extends BaseModel
{
    use SoftDeletes;

    protected $table = 'lead_calls';

    public static string $_table = 'lead_calls';

    protected $fillable = [
        'account_id',
        'lead_id',
        'user_id',
        'direction',
        'plivo_call_uuid',
        'telnyx_call_control_id',
        'telnyx_call_leg_id',
        'provider',
        'from_number',
        'to_number',
        'status',
        'initiated_at',
        'answered_at',
        'ended_at',
        'duration_seconds',
        'hangup_cause',
        'outcome',
        'outcome_notes',
    ];

    protected function casts(): array
    {
        return [
            'initiated_at' => 'datetime',
            'answered_at' => 'datetime',
            'ended_at' => 'datetime',
            'duration_seconds' => 'integer',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Leads::class, 'lead_id');
    }

    /** The agent who placed / took the call. */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function recording(): HasOne
    {
        return $this->hasOne(LeadRecording::class, 'lead_call_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Accounts::class, 'account_id');
    }
}
