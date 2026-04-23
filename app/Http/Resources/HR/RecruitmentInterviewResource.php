<?php

declare(strict_types=1);

namespace App\Http\Resources\HR;

use App\Models\RecruitmentInterview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecruitmentInterviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var RecruitmentInterview $interview */
        $interview = $this->resource;

        return [
            'id' => (int) $interview->id,
            'candidate_id' => (int) $interview->candidate_id,
            'interviewer' => $interview->interviewer ? [
                'id' => (int) $interview->interviewer->id,
                'name' => (string) $interview->interviewer->name,
            ] : null,
            'interview_date' => $interview->interview_date?->toIso8601String(),
            'interview_type' => $interview->interview_type?->value,
            'interview_type_label' => $interview->interview_type?->name,
            'interview_notes' => $interview->interview_notes,
            'scores' => [
                'communication' => $interview->score_communication,
                'technical' => $interview->score_technical,
                'cultural_fit' => $interview->score_cultural_fit,
                'experience' => $interview->score_experience,
                'personality' => $interview->score_personality,
            ],
            'average_score' => $interview->average_score,
            'verdict' => $interview->verdict?->value,
            'has_scorecard' => (bool) $interview->has_scorecard,
            'created_at' => $interview->created_at?->toIso8601String(),
            'updated_at' => $interview->updated_at?->toIso8601String(),
        ];
    }
}
