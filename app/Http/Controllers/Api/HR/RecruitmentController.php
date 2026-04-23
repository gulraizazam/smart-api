<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\HR;

use App\Enums\CandidateStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HR\ConvertCandidateRequest;
use App\Http\Requests\Admin\HR\StoreRecruitmentCandidateRequest;
use App\Http\Requests\Admin\HR\UpdateCandidateStatusRequest;
use App\Http\Requests\Admin\HR\UpdateRecruitmentCandidateRequest;
use App\Http\Resources\HR\RecruitmentCandidateResource;
use App\Models\RecruitmentCandidate;
use App\Services\HR\RecruitmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class RecruitmentController extends Controller
{
    public function __construct(
        protected readonly RecruitmentService $service,
    ) {}

    /**
     * GET /api/hr/recruitment
     *
     * Paginated candidate list. Filters mirror the web datatable.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('hr_recruitment_view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'status' => ['nullable', 'in:scheduled,in_review,shortlisted,on_hold,offer,hired,offer_declined,rejected,blacklisted'],
                'designation_id' => ['nullable', 'integer'],
                'location_id' => ['nullable', 'integer'],
                'search' => ['nullable', 'string', 'max:100'],
                'only_open' => ['nullable', 'boolean'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            ]);

            $accountId = (int) Auth::user()->account_id;

            $query = RecruitmentCandidate::query()
                ->with(['designation:id,name', 'city:id,name', 'location:id,name', 'convertedUser:id,name'])
                ->withCount('interviews')
                ->where('account_id', $accountId);

            if ($request->filled('status')) {
                $query->where('status', (string) $request->string('status'));
            }

            if ($request->boolean('only_open')) {
                $query->open();
            }

            if ($request->filled('designation_id')) {
                $query->where('designation_id', (int) $request->integer('designation_id'));
            }

            if ($request->filled('location_id')) {
                $query->where('location_id', (int) $request->integer('location_id'));
            }

            if ($request->filled('search')) {
                $term = '%'.$request->string('search')->trim().'%';
                $query->where(function ($q) use ($term): void {
                    $q->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term);
                });
            }

            $perPage = (int) ($request->integer('per_page') ?: 25);

            $paginated = $query->orderByDesc('id')->paginate($perPage);

            return $this->successResponse(
                'Candidates retrieved successfully.',
                [
                    'items' => RecruitmentCandidateResource::collection($paginated->items()),
                    'meta' => [
                        'current_page' => $paginated->currentPage(),
                        'last_page' => $paginated->lastPage(),
                        'per_page' => $paginated->perPage(),
                        'total' => $paginated->total(),
                    ],
                ],
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\RecruitmentController@index');
        }
    }

    /**
     * GET /api/hr/recruitment/summary
     *
     * Status counts — drives the recruitment pipeline widget.
     */
    public function summary(): JsonResponse
    {
        try {
            if (! Gate::allows('hr_recruitment_view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $accountId = (int) Auth::user()->account_id;

            $counts = RecruitmentCandidate::query()
                ->where('account_id', $accountId)
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray();

            $payload = [];
            foreach (CandidateStatus::cases() as $case) {
                $payload[$case->value] = (int) ($counts[$case->value] ?? 0);
            }
            $payload['total'] = array_sum($payload);

            $payload['open'] = RecruitmentCandidate::query()
                ->where('account_id', $accountId)
                ->open()
                ->count();

            return $this->successResponse('Recruitment summary retrieved successfully.', $payload);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\RecruitmentController@summary');
        }
    }

    /**
     * POST /api/hr/recruitment
     *
     * Create candidate + schedule the first interview (matches the web rule
     * that "creating a candidate always schedules a first interview"). CV
     * upload is multipart-optional; stored on the `r2` disk under the
     * tenant-scoped prefix.
     */
    public function store(StoreRecruitmentCandidateRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $user = Auth::user();
            $accountId = (int) $user->account_id;

            $candidate = DB::transaction(function () use ($validated, $user, $accountId, $request): RecruitmentCandidate {
                $candidateData = collect($validated)
                    ->except(['cv', 'interview_type', 'interviewer_id', 'interview_date'])
                    ->toArray();

                $candidateData['account_id'] = $accountId;
                $candidateData['created_by'] = (int) $user->id;
                $candidateData['status'] = CandidateStatus::Scheduled->value;

                if ($request->hasFile('cv')) {
                    $candidateData['cv_file_path'] = $request->file('cv')
                        ->store("accounts/{$accountId}/hr/recruitment", 'r2');
                }

                $created = RecruitmentCandidate::create($candidateData);

                $created->interviews()->create([
                    'interviewer_id' => (int) $validated['interviewer_id'],
                    'interview_date' => $validated['interview_date'],
                    'interview_type' => $validated['interview_type'],
                    'account_id' => $accountId,
                ]);

                return $created;
            });

            $candidate->load([
                'designation:id,name',
                'city:id,name',
                'location:id,name',
                'interviews.interviewer:id,name',
            ]);

            return $this->successResponse(
                'Candidate added and interview scheduled.',
                new RecruitmentCandidateResource($candidate),
                201,
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\RecruitmentController@store');
        }
    }

    /**
     * GET /api/hr/recruitment/{candidate}
     */
    public function show(RecruitmentCandidate $candidate): JsonResponse
    {
        try {
            if (! Gate::allows('hr_recruitment_view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            if (! $this->ownedByCaller($candidate)) {
                return $this->errorResponse('Candidate not found.', 404);
            }

            $candidate->load([
                'designation:id,name',
                'city:id,name',
                'location:id,name',
                'interviews.interviewer:id,name',
                'convertedUser:id,name',
            ]);

            return $this->successResponse(
                'Candidate retrieved successfully.',
                new RecruitmentCandidateResource($candidate),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\RecruitmentController@show');
        }
    }

    /**
     * PATCH /api/hr/recruitment/{candidate}
     */
    public function update(UpdateRecruitmentCandidateRequest $request, RecruitmentCandidate $candidate): JsonResponse
    {
        try {
            if (! $this->ownedByCaller($candidate)) {
                return $this->errorResponse('Candidate not found.', 404);
            }

            $validated = $request->validated();
            $data = collect($validated)->except('cv')->toArray();

            if ($request->hasFile('cv')) {
                $accountId = (int) Auth::user()->account_id;

                if ($candidate->cv_file_path) {
                    Storage::disk('r2')->delete($candidate->cv_file_path);
                }

                $data['cv_file_path'] = $request->file('cv')
                    ->store("accounts/{$accountId}/hr/recruitment", 'r2');
            }

            $candidate->update($data);

            $candidate->load([
                'designation:id,name',
                'city:id,name',
                'location:id,name',
            ]);

            return $this->successResponse(
                'Candidate updated successfully.',
                new RecruitmentCandidateResource($candidate),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\RecruitmentController@update');
        }
    }

    /**
     * PATCH /api/hr/recruitment/{candidate}/status
     */
    public function updateStatus(UpdateCandidateStatusRequest $request, RecruitmentCandidate $candidate): JsonResponse
    {
        try {
            if (! $this->ownedByCaller($candidate)) {
                return $this->errorResponse('Candidate not found.', 404);
            }

            $candidate->update(['status' => $request->validated()['status']]);

            return $this->successResponse(
                'Candidate status updated to '.ucfirst($candidate->status->value).'.',
                new RecruitmentCandidateResource($candidate->fresh()),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\RecruitmentController@updateStatus');
        }
    }

    /**
     * GET /api/hr/recruitment/{candidate}/cv
     */
    public function previewCv(RecruitmentCandidate $candidate): Response|JsonResponse
    {
        return $this->streamCv($candidate, inline: true);
    }

    /**
     * GET /api/hr/recruitment/{candidate}/cv/download
     */
    public function downloadCv(RecruitmentCandidate $candidate): Response|JsonResponse
    {
        return $this->streamCv($candidate, inline: false);
    }

    /**
     * POST /api/hr/recruitment/{candidate}/convert
     *
     * Convert to employee via the existing service (wraps user creation +
     * employee_detail + role + location assignment in one transaction).
     */
    public function convert(ConvertCandidateRequest $request, RecruitmentCandidate $candidate): JsonResponse
    {
        try {
            if (! $this->ownedByCaller($candidate)) {
                return $this->errorResponse('Candidate not found.', 404);
            }

            if ($candidate->converted_user_id) {
                return $this->errorResponse('Candidate has already been converted.', 409);
            }

            $user = $this->service->convertToEmployee($candidate, $request->validated());

            return $this->successResponse(
                'Candidate converted to employee successfully.',
                [
                    'user' => ['id' => (int) $user->id, 'name' => (string) $user->name],
                    'candidate' => new RecruitmentCandidateResource(
                        $candidate->fresh()->load(['convertedUser:id,name', 'designation:id,name']),
                    ),
                ],
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\RecruitmentController@convert');
        }
    }

    /**
     * DELETE /api/hr/recruitment/{candidate}
     *
     * Soft-delete. 409 if the candidate was already converted to an employee
     * — removing the audit trail for a hire is never what the caller wants.
     */
    public function destroy(RecruitmentCandidate $candidate): JsonResponse
    {
        try {
            if (! Gate::allows('hr_recruitment_delete')) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            if (! $this->ownedByCaller($candidate)) {
                return $this->errorResponse('Candidate not found.', 404);
            }

            if ($candidate->converted_user_id) {
                return $this->errorResponse(
                    'Cannot delete a candidate that has already been converted to an employee.',
                    409,
                );
            }

            $candidate->delete();

            return $this->successResponse('Candidate deleted successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\RecruitmentController@destroy');
        }
    }

    // ── internal helpers ──

    private function ownedByCaller(RecruitmentCandidate $candidate): bool
    {
        return (int) $candidate->account_id === (int) Auth::user()->account_id;
    }

    private function streamCv(RecruitmentCandidate $candidate, bool $inline): Response|JsonResponse
    {
        try {
            if (! Gate::allows('hr_recruitment_view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            if (! $this->ownedByCaller($candidate)) {
                return $this->errorResponse('Candidate not found.', 404);
            }

            $disk = Storage::disk('r2');

            if (! $candidate->cv_file_path || ! $disk->exists($candidate->cv_file_path)) {
                return $this->errorResponse('CV not found for this candidate.', 404);
            }

            $fileName = $candidate->name.'_CV.'.pathinfo($candidate->cv_file_path, PATHINFO_EXTENSION);
            $disposition = $inline ? 'inline' : 'attachment';

            return response($disk->get($candidate->cv_file_path), 200, [
                'Content-Type' => $disk->mimeType($candidate->cv_file_path),
                'Content-Disposition' => $disposition.'; filename="'.$fileName.'"',
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\RecruitmentController@streamCv');
        }
    }
}
