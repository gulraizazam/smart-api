<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\HR;

use App\Enums\CandidateStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HR\StoreRecruitmentInterviewRequest;
use App\Http\Requests\Admin\HR\UpdateRecruitmentInterviewRequest;
use App\Http\Resources\HR\RecruitmentInterviewResource;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentInterview;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RecruitmentInterviewController extends Controller
{
    /**
     * GET /api/hr/recruitment/{candidate}/interviews
     */
    public function index(RecruitmentCandidate $candidate): JsonResponse
    {
        try {
            if (! Gate::allows('hr_recruitment_view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            if (! $this->candidateOwnedByCaller($candidate)) {
                return $this->errorResponse('Candidate not found.', 404);
            }

            $interviews = $candidate->interviews()->with('interviewer:id,name')->get();

            return $this->successResponse(
                'Interviews retrieved successfully.',
                RecruitmentInterviewResource::collection($interviews),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\RecruitmentInterviewController@index');
        }
    }

    /**
     * POST /api/hr/recruitment/{candidate}/interviews
     *
     * Also accepts a flat POST to /api/hr/recruitment/interviews with a
     * `candidate_id` field — preserves the legacy web endpoint contract.
     */
    public function store(StoreRecruitmentInterviewRequest $request, ?RecruitmentCandidate $candidate = null): JsonResponse
    {
        try {
            $validated = $request->validated();
            $accountId = (int) Auth::user()->account_id;

            if ($candidate === null) {
                $candidate = RecruitmentCandidate::find((int) $validated['candidate_id']);
            }

            if (! $candidate || ! $this->candidateOwnedByCaller($candidate)) {
                return $this->errorResponse('Candidate not found.', 404);
            }

            $payload = collect($validated)->except('candidate_id')->toArray();
            $payload['candidate_id'] = (int) $candidate->id;
            $payload['account_id'] = $accountId;

            $interview = RecruitmentInterview::create($payload);

            // Auto-transition: Scheduled → In Review when any scorecard/verdict submitted
            // (mirrors legacy InterviewController behaviour).
            if ($interview->has_scorecard && $candidate->status === CandidateStatus::Scheduled) {
                $candidate->update(['status' => CandidateStatus::InReview->value]);
            }

            $interview->load('interviewer:id,name');

            return $this->successResponse(
                'Interview round added successfully.',
                new RecruitmentInterviewResource($interview),
                201,
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\RecruitmentInterviewController@store');
        }
    }

    /**
     * PATCH /api/hr/recruitment/interviews/{interview}
     */
    public function update(UpdateRecruitmentInterviewRequest $request, RecruitmentInterview $interview): JsonResponse
    {
        try {
            if ((int) $interview->account_id !== (int) Auth::user()->account_id) {
                return $this->errorResponse('Interview not found.', 404);
            }

            $interview->update($request->validated());

            // Keep the Scheduled → In Review auto-transition symmetric with `store`.
            if ($interview->has_scorecard) {
                $candidate = $interview->candidate;
                if ($candidate && $candidate->status === CandidateStatus::Scheduled) {
                    $candidate->update(['status' => CandidateStatus::InReview->value]);
                }
            }

            $interview->load('interviewer:id,name');

            return $this->successResponse(
                'Interview updated successfully.',
                new RecruitmentInterviewResource($interview->fresh()->load('interviewer:id,name')),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\RecruitmentInterviewController@update');
        }
    }

    /**
     * DELETE /api/hr/recruitment/interviews/{interview}
     */
    public function destroy(RecruitmentInterview $interview): JsonResponse
    {
        try {
            if (! Gate::allows('hr_recruitment_interview_manage')) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            if ((int) $interview->account_id !== (int) Auth::user()->account_id) {
                return $this->errorResponse('Interview not found.', 404);
            }

            $interview->delete();

            return $this->successResponse('Interview deleted successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\RecruitmentInterviewController@destroy');
        }
    }

    private function candidateOwnedByCaller(RecruitmentCandidate $candidate): bool
    {
        return (int) $candidate->account_id === (int) Auth::user()->account_id;
    }
}
