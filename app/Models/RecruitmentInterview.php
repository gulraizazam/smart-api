<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InterviewType;
use App\Enums\InterviewVerdict;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecruitmentInterview extends BaseModel
{
    protected $fillable = [
        'candidate_id',
        'interviewer_id',
        'interview_date',
        'interview_type',
        'interview_notes',
        'score_communication',
        'score_technical',
        'score_cultural_fit',
        'score_experience',
        'score_personality',
        'verdict',
        'account_id',
    ];

    protected function casts(): array
    {
        return [
            'interview_date' => 'datetime',
            'interview_type' => InterviewType::class,
            'verdict' => InterviewVerdict::class,
            'score_communication' => 'integer',
            'score_technical' => 'integer',
            'score_cultural_fit' => 'integer',
            'score_experience' => 'integer',
            'score_personality' => 'integer',
        ];
    }

    // ── Relationships ──

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(RecruitmentCandidate::class, 'candidate_id');
    }

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_id')->withTrashed();
    }

    // ── Scopes ──

    public function scopeForCandidate(Builder $query, int $candidateId): Builder
    {
        return $query->where('candidate_id', $candidateId);
    }

    public function scopeForAccount(Builder $query, int $accountId): Builder
    {
        return $query->where('account_id', $accountId);
    }

    // ── Accessors ──

    public function getAverageScoreAttribute(): ?float
    {
        $scores = array_filter([
            $this->score_communication,
            $this->score_technical,
            $this->score_cultural_fit,
            $this->score_experience,
            $this->score_personality,
        ], fn ($s) => $s !== null);

        return count($scores) ? round(array_sum($scores) / count($scores), 1) : null;
    }

    public function getHasScorecardAttribute(): bool
    {
        return $this->verdict !== null
            || $this->score_communication !== null
            || $this->score_technical !== null
            || $this->score_cultural_fit !== null
            || $this->score_experience !== null
            || $this->score_personality !== null;
    }
}
