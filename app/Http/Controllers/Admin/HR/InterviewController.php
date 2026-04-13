<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\HR;

use App\Enums\CandidateStatus;
use App\Http\Controllers\Controller;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentInterview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InterviewController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        try {
            $accountId = Auth::user()->account_id;
            $validated = $request->validate([
                'candidate_id' => ['required', "exists:recruitment_candidates,id,account_id,{$accountId}"],
                'interviewer_id' => ['required', "exists:users,id,account_id,{$accountId}"],
                'interview_date' => ['required', 'date'],
                'interview_type' => ['required', 'in:ceo,director,hr'],
                'interview_notes' => ['nullable', 'string', 'max:150'],
                'score_communication' => ['nullable', 'integer', 'min:1', 'max:5'],
                'score_technical' => ['nullable', 'integer', 'min:1', 'max:5'],
                'score_cultural_fit' => ['nullable', 'integer', 'min:1', 'max:5'],
                'score_experience' => ['nullable', 'integer', 'min:1', 'max:5'],
                'score_personality' => ['nullable', 'integer', 'min:1', 'max:5'],
                'verdict' => ['nullable', 'in:strong_hire,hire,no_hire,strong_no_hire'],
            ]);

            $validated['account_id'] = $accountId;

            $interview = RecruitmentInterview::create($validated);

            // Auto-transition: Scheduled → In Review when scorecard/verdict is submitted
            if ($interview->has_scorecard) {
                $candidate = RecruitmentCandidate::find($validated['candidate_id']);
                if ($candidate && $candidate->status === CandidateStatus::Scheduled) {
                    $candidate->update(['status' => CandidateStatus::InReview->value]);
                }
            }

            return $this->successResponse('Interview round added successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'InterviewController@store');
        }
    }
}
