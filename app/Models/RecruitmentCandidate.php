<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CandidateStatus;
use App\Enums\DriveUploadStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecruitmentCandidate extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'designation_id',
        'city_id',
        'location_id',
        'status',
        'cv_file_path',
        'cv_google_drive_url',
        'cv_google_drive_file_id',
        'cv_drive_upload_status',
        'notes',
        'converted_user_id',
        'account_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => CandidateStatus::class,
            'cv_drive_upload_status' => DriveUploadStatus::class,
        ];
    }

    // ── Relationships ──

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(Cities::class, 'city_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Locations::class, 'location_id');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(RecruitmentInterview::class, 'candidate_id')
            ->orderByDesc('interview_date');
    }

    public function convertedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'converted_user_id')->withTrashed();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    // ── Scopes ──

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            CandidateStatus::Scheduled,
            CandidateStatus::InReview,
            CandidateStatus::Shortlisted,
            CandidateStatus::OnHold,
            CandidateStatus::Offer,
        ]);
    }

    public function scopeForAccount(Builder $query, int $accountId): Builder
    {
        return $query->where('account_id', $accountId);
    }

}
