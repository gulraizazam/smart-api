<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\HelperModule\ApiHelper;
use App\Http\Requests\Feedback\FeedbackDatatableRequest;
use App\Http\Requests\Feedback\GetTreatmentInfoRequest;
use App\Http\Requests\Feedback\GetTreatmentRequest;
use App\Http\Requests\Feedback\StoreFeedbackRequest;
use App\Http\Requests\Feedback\UpdateFeedbackRequest;
use App\Models\Feedback;
use App\Services\Feedback\FeedbackService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class FeedbackController extends Controller
{
    public function __construct(
        private readonly FeedbackService $feedbackService,
    ) {}

    public function index(): View
    {
        abort_unless(Gate::allows('feedbacks_manage'), 401);

        return view('admin.feedback.index');
    }

    public function datatable(FeedbackDatatableRequest $request): JsonResponse
    {
        try {
            $records = $this->feedbackService->getDatatableData($request->all());

            return ApiHelper::apiDataTable($records);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function edit(Feedback $feedback): JsonResponse
    {
        abort_unless(Gate::allows('feedbacks_edit'), 401);

        return response()->json($feedback->only(['id', 'rating']));
    }

    public function update(UpdateFeedbackRequest $request, Feedback $feedback): JsonResponse
    {
        try {
            $this->feedbackService->update($feedback, (int) $request->validated('rating'));

            return ApiHelper::apiResponse(
                config('constants.api_status.success'),
                'Record has been updated successfully.'
            );
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function store(StoreFeedbackRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            $this->feedbackService->store(
                appointmentId: (int) $validated['treatment'],
                rating: (int) $validated['rating'],
                comment: $validated['comment'] ?? null,
            );

            return ApiHelper::apiResponse(
                config('constants.api_status.success'),
                'Record has been created successfully.'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return ApiHelper::apiResponse(
                config('constants.api_status.error'),
                'Referenced record not found.',
                false
            );
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return ApiHelper::apiResponse(
                $e->getStatusCode(),
                $e->getMessage(),
                false
            );
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function destroy(Feedback $feedback): JsonResponse
    {
        abort_unless(Gate::allows('feedbacks_delete'), 401);

        try {
            $this->feedbackService->destroy($feedback);

            return ApiHelper::apiResponse(
                config('constants.api_status.success'),
                'Record has been deleted successfully.'
            );
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function getTreatment(GetTreatmentRequest $request): JsonResponse
    {
        try {
            $patientId = $request->validated('patient_id');

            if (empty($patientId)) {
                return response()->json([
                    'status' => 1,
                    'message' => 'Treatment found',
                    'treatments' => [],
                ]);
            }

            $treatments = $this->feedbackService->getAvailableTreatments((int) $patientId);

            return response()->json([
                'status' => 1,
                'message' => 'Treatment found',
                'treatments' => $treatments,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function getTreatmentInfo(GetTreatmentInfoRequest $request): JsonResponse
    {
        try {
            $treatmentId = $request->validated('treatment_id');

            if (empty($treatmentId)) {
                return response()->json([
                    'status' => 1,
                    'message' => 'Treatment found',
                    'treatments' => null,
                ]);
            }

            $treatment = $this->feedbackService->getTreatmentInfo((int) $treatmentId);

            if ($treatment) {
                $treatment->scheduled_date = $treatment->scheduled_date->format('M d, Y');
            }

            return response()->json([
                'status' => $treatment ? 1 : 0,
                'message' => $treatment ? 'Treatment found' : 'Treatment not found',
                'treatments' => $treatment,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
