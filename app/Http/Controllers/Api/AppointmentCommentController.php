<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\AppointmentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Appointment\StoreAppointmentCommentRequest;
use App\Http\Resources\Appointment\AppointmentCommentResource;
use App\Models\AppointmentComments;
use App\Models\Appointments;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AppointmentCommentController extends Controller
{
    public function index(int $appointmentId): JsonResponse
    {
        try {
            // Dual-purpose endpoint — `appointment_id` could be either a
            // consultation or a treatment. Either module's detail-view
            // perm grants access; `appointments_manage` remains as a
            // legacy fallback for older role configurations.
            if (
                !Gate::allows('consultations.detail.view')
                && !Gate::allows('treatments.detail.view')
                && !Gate::allows('appointments_manage')
            ) {
                throw AppointmentException::unauthorized();
            }

            $this->ensureAppointmentInAccount($appointmentId);

            $comments = AppointmentComments::with('user:id,name')
                ->where('appointment_id', $appointmentId)
                ->orderByDesc('created_at')
                ->get();

            return $this->successResponse(
                'Appointment comments retrieved successfully.',
                AppointmentCommentResource::collection($comments),
            );
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Error fetching appointment comments');
        }
    }

    public function store(StoreAppointmentCommentRequest $request): JsonResponse
    {
        try {
            // Dual-purpose endpoint — `appointment_id` could be either a
            // consultation or a treatment. Either module's detail-view
            // perm grants access; `appointments_manage` remains as a
            // legacy fallback for older role configurations.
            if (
                !Gate::allows('consultations.detail.view')
                && !Gate::allows('treatments.detail.view')
                && !Gate::allows('appointments_manage')
            ) {
                throw AppointmentException::unauthorized();
            }

            $data = $request->validated();

            $comment = AppointmentComments::create([
                'appointment_id' => $data['appointment_id'],
                'comment' => $data['comment'],
                'created_by' => (int) Auth::id(),
            ])->load('user:id,name');

            return $this->successResponse(
                'Comment saved successfully.',
                new AppointmentCommentResource($comment),
                201,
            );
        } catch (AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode());
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Error saving appointment comment');
        }
    }

    private function ensureAppointmentInAccount(int $appointmentId): void
    {
        $exists = Appointments::where('id', $appointmentId)
            ->where('account_id', Auth::user()->account_id)
            ->exists();

        if (!$exists) {
            throw AppointmentException::notFound();
        }
    }
}
