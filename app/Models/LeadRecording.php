<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One row per successfully-stored recording for a {@see LeadCall}.
 *
 * Written by {@see \App\Jobs\DownloadLeadRecordingJob} once Plivo has
 * finished producing the recording and posted it to our webhook. The audio
 * bytes live at `file_path` on whichever disk the job used (local for
 * phase 1, r2_invoices later — the schema doesn't care).
 */
class LeadRecording extends BaseModel
{
    use SoftDeletes;

    protected $table = 'lead_recordings';

    public static string $_table = 'lead_recordings';

    protected $fillable = [
        'account_id',
        'lead_call_id',
        'lead_id',
        'file_path',
        'file_size',
        'mime_type',
        'sha256',
        'duration_seconds',
        'plivo_recording_id',
        'telnyx_recording_id',
        'provider',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
            'file_size' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function call(): BelongsTo
    {
        return $this->belongsTo(LeadCall::class, 'lead_call_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Leads::class, 'lead_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Accounts::class, 'account_id');
    }
}
