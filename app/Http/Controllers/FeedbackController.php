<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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

            return response()->json($records);
        } catch (\Exception $e) {
            return $this->handleException($e, 'FeedbackController');
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

            return $this->successResponse('Record has been updated successfully.', );
        } catch (\Exception $e) {
            return $this->handleException($e, 'FeedbackController');
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

            return $this->successResponse('Record has been created successfully.', );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return $this->errorResponse('Referenced record not found.', 200);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $this->errorResponse($e->getMessage(), 200);
        } catch (\Exception $e) {
            return $this->handleException($e, 'FeedbackController');
        }
    }

    public function destroy(Feedback $feedback): JsonResponse
    {
        abort_unless(Gate::allows('feedbacks_delete'), 401);

        try {
            $this->feedbackService->destroy($feedback);

            return $this->successResponse('Record has been deleted successfully.', );
        } catch (\Exception $e) {
            return $this->handleException($e, 'FeedbackController');
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

            $treatmentData = null;
            if ($treatment) {
                $treatmentData = $treatment->toArray();
                $treatmentData['scheduled_date'] = $treatment->scheduled_date->format('M d, Y');
            }

            return response()->json([
                'status' => $treatment ? 1 : 0,
                'message' => $treatment ? 'Treatment found' : 'Treatment not found',
                'treatments' => $treatmentData,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'FeedbackController');
        }
    }
}
