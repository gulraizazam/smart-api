<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddReferralRequest;
use App\Http\Requests\AssignMembershipRequest;
use App\Http\Requests\AssignVoucherRequest;
use App\Http\Requests\PatientDocumentRequest;
use App\Http\Requests\PatientImageRequest;
use App\Http\Requests\PatientNoteRequest;
use App\Http\Requests\PatientRequest;
use App\Http\Requests\PatientStatusRequest;
use App\Http\Resources\Patient\PatientAppointmentResource;
use App\Http\Resources\Patient\PatientDocumentResource;
use App\Http\Resources\Patient\PatientLastAppointmentLocationResource;
use App\Http\Resources\Patient\PatientNoteResource;
use App\Services\PatientManagement\PatientService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PatientController extends Controller
{
    public function __construct(
        private readonly PatientService $patientService,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Datatable & Search
    |--------------------------------------------------------------------------
    */

    public function index(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('patients.list.view')) {
                return $this->unauthorized();
            }

            return response()->json($this->patientService->getDatatableData($request));
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    public function search(Request $request): JsonResponse
    {
        try {
            // Search is consumed by other modules' patient pickers as well,
            // so the gate is the broad list-view rather than a search-only
            // perm. Revoking list view also removes ability to look up a
            // patient by name/phone/id from the SPA pickers.
            if (!Gate::allows('patients.list.view')) {
                return $this->unauthorized();
            }

            $search = $request->input('search', $request->input('q', ''));
            $patients = $this->patientService->searchPatients($search, auth()->user()->account_id);

            return $this->success('Patients retrieved.', ['patients' => $patients]);
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CRUD
    |--------------------------------------------------------------------------
    */

    // create() and store() removed: patients are created only as a
    // side-effect of booking a consultation, appointment, or treatment
    // (AppointmentService::create handles the User::create + Lead pairing).

    public function show(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('patients.card.view')) {
                return $this->unauthorized();
            }

            $result = $this->patientService->getPatient($id);

            return $result
                ? $this->success('Record found.', $result)
                : $this->fail('Record not found.');
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    public function edit(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('patients.edit')) {
                return $this->unauthorized();
            }

            $data = $this->patientService->getEditData($id);

            return $data
                ? $this->success('Record found.', $data)
                : $this->notFound('Patient not found.');
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    public function update(PatientRequest $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('patients.edit')) {
                return $this->unauthorized();
            }

            $result = $this->patientService->update($id, $request->validated());

            return $this->fromService($result);
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('patients.delete')) {
                return $this->unauthorized();
            }

            $result = $this->patientService->delete($id);

            return $this->fromService($result);
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    public function status(PatientStatusRequest $request): JsonResponse
    {
        try {
            // Direction-aware gate — status=1 is activate, 0 is deactivate.
            // SPA row buttons only render the direction the user can move
            // in, but a direct API call could send either; check the perm
            // that matches the requested transition.
            $next = (int) $request->validated('status');
            $required = $next === 1 ? 'patients.activate' : 'patients.deactivate';
            if (!Gate::allows($required)) {
                return $this->unauthorized();
            }

            $result = $this->patientService->changeStatus(
                (int) $request->validated('id'),
                $next,
            );

            return $this->fromService($result);
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Patient Detail & Tabs
    |--------------------------------------------------------------------------
    */

    public function getPatient(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('patients.card.view')) {
                return $this->unauthorized();
            }

            $result = $this->patientService->getPatient($id);

            return $result
                ? $this->success('Record found.', $result)
                : $this->notFound('Record not found.');
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    public function getTabCounts(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('patients.card.view')) {
                return $this->unauthorized();
            }

            $counts = $this->patientService->getTabCounts($id);

            return $counts
                ? $this->success('Tab counts retrieved.', $counts)
                : $this->notFound('Patient not found.');
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Last Appointment Location
    |--------------------------------------------------------------------------
    */

    public function lastAppointmentLocation(int $id, Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('patients.card.view')) {
                return $this->unauthorized();
            }

            $data = $this->patientService->lastAppointmentLocation(
                $id,
                $request->input('appointment_type'),
            );

            if ($data === null) {
                return $this->respond(false, 'No previous appointment found.', null, 404);
            }

            return $this->success(
                'Last appointment location retrieved.',
                new PatientLastAppointmentLocationResource($data),
            );
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * Centre to launch the "New consultation" calendar at: the location
     * of the patient's last arrived consultation, falling back to their
     * most recent consultation of any status. 404 when the patient has
     * no consultation history (the SPA then opens the calendar with no
     * location pre-selected).
     */
    public function consultationLaunchLocation(int $id): JsonResponse
    {
        try {
            if (! Gate::allows('patients.consultations.create')) {
                return $this->unauthorized();
            }

            $data = $this->patientService->consultationLaunchLocation($id);

            if ($data === null) {
                return $this->respond(false, 'No previous consultation found.', null, 404);
            }

            return $this->success(
                'Consultation launch location retrieved.',
                new PatientLastAppointmentLocationResource($data),
            );
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    /**
     * Treatment twin of consultationLaunchLocation(): the location of the
     * patient's last arrived treatment, falling back to their most recent
     * treatment of any status. 404 when the patient has no treatment
     * history (the SPA then opens the calendar with no location
     * pre-selected). Drives the patient card's "New treatment" button.
     */
    public function treatmentLaunchLocation(int $id): JsonResponse
    {
        try {
            if (! Gate::allows('patients.treatments.create')) {
                return $this->unauthorized();
            }

            $data = $this->patientService->treatmentLaunchLocation($id);

            if ($data === null) {
                return $this->respond(false, 'No previous treatment found.', null, 404);
            }

            return $this->success(
                'Treatment launch location retrieved.',
                new PatientLastAppointmentLocationResource($data),
            );
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Image
    |--------------------------------------------------------------------------
    */

    public function storeImage(PatientImageRequest $request): JsonResponse
    {
        try {
            // No patient-card avatar upload exists in the SPA, but the
            // endpoint is still exposed for staff/user image uploads which
            // route through PatientImageRequest. Gate on `users_manage` —
            // the original patient-side perm was dropped after audit.
            if (!Gate::allows('users_manage')) {
                return $this->unauthorized();
            }

            $result = $this->patientService->storeImage(
                (int) $request->validated('patient_id'),
                $request->file('file'),
            );

            return $result['status']
                ? $this->success($result['message'], ['image' => $result['image']])
                : $this->fail($result['message']);
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Membership & Voucher
    |--------------------------------------------------------------------------
    */

    public function assignMembership(AssignMembershipRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('patients.membership.assign')) {
                return $this->unauthorized();
            }

            $result = $this->patientService->assignMembership(
                $request->patientId(),
                $request->validated('membership_code'),
            );

            return $this->fromService($result);
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    public function assignVoucher(AssignVoucherRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('patients.voucher.assign')) {
                return $this->unauthorized();
            }

            $result = $this->patientService->assignVoucher(
                $request->patientId(),
                (int) $request->validated('voucher_id'),
                (float) $request->validated('amount'),
            );

            return $this->fromService($result);
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    public function addReferral(AddReferralRequest $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('patients.referral.add')) {
                return $this->unauthorized();
            }

            $result = $this->patientService->addReferral($id, $request->validated('membership_code'));

            return $result['status']
                ? $this->success($result['message'], [
                    'referral' => $result['referral'] ?? null,
                    'patient' => $result['patient'] ?? null,
                ])
                : $this->fail($result['message']);
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Appointments / Consultations / Treatments Datatables
    |--------------------------------------------------------------------------
    */

    public function appointmentsDatatable(int $id, Request $request): JsonResponse
    {
        try {
            // No dedicated "all appointments" tab in the SPA card — this
            // legacy endpoint serves the combined feed. Gate on card view
            // so revoking card access removes it too.
            if (!Gate::allows('patients.card.view')) {
                return $this->unauthorized();
            }

            $data = $this->patientService->getPatientAppointments($id, $request);
            $data['data'] = PatientAppointmentResource::collection($data['data']);

            return response()->json($data);
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    public function consultationsDatatable(int $id, Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('patients.consultations.view')) {
                return $this->unauthorized();
            }

            $data = $this->patientService->getPatientConsultations($id, $request);
            $data['data'] = PatientAppointmentResource::collection($data['data']);

            return response()->json($data);
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    public function treatmentsDatatable(int $id, Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('patients.treatments.view')) {
                return $this->unauthorized();
            }

            $data = $this->patientService->getPatientTreatments($id, $request);
            $data['data'] = PatientAppointmentResource::collection($data['data']);

            return response()->json($data);
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Documents
    |--------------------------------------------------------------------------
    */

    public function documents(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('patients.documents.view')) {
                return $this->forbidden();
            }

            $result = $this->patientService->listDocuments($id);

            return $result['status']
                ? $this->success($result['message'], PatientDocumentResource::collection($result['documents']))
                : $this->respond(false, $result['message'], null, $result['code'] ?? 400);
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    public function deleteDocument(int $id, int $documentId): JsonResponse
    {
        try {
            if (!Gate::allows('patients.documents.delete')) {
                return $this->forbidden();
            }

            $result = $this->patientService->deleteDocument($id, $documentId);

            return $result['status']
                ? $this->success($result['message'], null)
                : $this->respond(false, $result['message'], null, $result['code'] ?? 400);
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    public function uploadDocument(int $id, PatientDocumentRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('patients.documents.upload')) {
                return $this->forbidden();
            }

            $result = $this->patientService->uploadDocument(
                $id,
                $request->file('file'),
                $request->validated('document_type'),
            );

            return $result['status']
                ? $this->success($result['message'], new PatientDocumentResource($result['document']))
                : $this->respond(false, $result['message'], null, $result['code'] ?? 400);
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    public function updateDocument(int $id, int $documentId, PatientDocumentRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('patients.documents.edit')) {
                return $this->forbidden();
            }

            $result = $this->patientService->updateDocument(
                $id,
                $documentId,
                $request->validated('document_type'),
                $request->file('file'),
            );

            return $result['status']
                ? $this->success($result['message'], new PatientDocumentResource($result['document']))
                : $this->respond(false, $result['message'], null, $result['code'] ?? 400);
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Activity & Voucher History
    |--------------------------------------------------------------------------
    */

    public function getActivityHistory(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('patients.activity.view')) {
                return $this->unauthorized();
            }

            $activities = $this->patientService->getActivityHistory($id);

            return $activities !== null
                ? $this->success('Activity history retrieved.', $activities)
                : $this->notFound('Patient not found.');
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    public function getVoucherHistory(int $patientId, int $userVoucherId): JsonResponse
    {
        try {
            if (!Gate::allows('patients.vouchers.view_history')) {
                return $this->unauthorized();
            }

            $result = $this->patientService->getVoucherHistory($patientId, $userVoucherId);

            return $result['status']
                ? $this->success($result['message'], $result['data'])
                : $this->respond(false, $result['message'], null, $result['code'] ?? 400);
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Notes
    |--------------------------------------------------------------------------
    */

    public function getNotes(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('patients.notes.view')) {
                return $this->unauthorized();
            }

            $result = $this->patientService->getNotes($id);

            if (!$result) {
                return $this->notFound('Patient not found.');
            }

            return $this->success('Notes retrieved.', PatientNoteResource::collection($result['notes']));
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    public function addNote(int $id, PatientNoteRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('patients.notes.create')) {
                return $this->unauthorized();
            }

            $result = $this->patientService->addNote($id, $request->validated('note'));

            if (!$result) {
                return $this->notFound('Patient not found.');
            }

            return $this->success('Note added successfully.', new PatientNoteResource($result['note']));
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    public function updateNote(int $id, int $noteId, PatientNoteRequest $request): JsonResponse
    {
        try {
            // Override switch — bypass creator-only enforcement for super-
            // admins (legacy behaviour) and for any role granted the explicit
            // `patients.notes.manage` perm. Service then handles the actual
            // creator-equality check for everyone else.
            $canOverride = $this->isSuperAdmin() || Gate::allows('patients.notes.manage');
            $result = $this->patientService->updateNote($id, $noteId, $request->validated('note'), $canOverride);

            return $result['status']
                ? $this->success($result['message'], new PatientNoteResource($result['note']))
                : $this->respond(false, $result['message'], null, $result['code'] ?? 400);
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    public function deleteNote(int $id, int $noteId): JsonResponse
    {
        try {
            $canOverride = $this->isSuperAdmin() || Gate::allows('patients.notes.manage');
            $result = $this->patientService->deleteNote($id, $noteId, $canOverride);

            return $result['status']
                ? $this->success($result['message'])
                : $this->respond(false, $result['message'], null, $result['code'] ?? 400);
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    public function togglePinNote(int $id, int $noteId): JsonResponse
    {
        try {
            if (!Gate::allows('patients.notes.pin')) {
                return $this->unauthorized();
            }

            $result = $this->patientService->togglePinNote($id, $noteId);

            return $result['status']
                ? $this->success($result['message'], new PatientNoteResource($result['note']))
                : $this->respond(false, $result['message'], null, $result['code'] ?? 400);
        } catch (Exception $e) {
            return $this->exceptionToResponse($e);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Standardized Response Helpers
    |--------------------------------------------------------------------------
    */

    private function respond(bool $success, string $message, mixed $data = null, int $httpCode = 200, array $errors = []): JsonResponse
    {
        return response()->json([
            'status' => $success,
            'success' => $success,
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
        ], $httpCode);
    }

    private function success(string $message, mixed $data = null): JsonResponse
    {
        return $this->respond(true, $message, $data);
    }

    private function fail(string $message): JsonResponse
    {
        return $this->respond(false, $message);
    }

    private function notFound(string $message): JsonResponse
    {
        return $this->respond(false, $message, null, 404);
    }

    private function unauthorized(): JsonResponse
    {
        return $this->respond(false, 'You are not authorized to access this resource.', null, 403);
    }

    private function forbidden(): JsonResponse
    {
        return $this->respond(false, 'You are not authorized to access this resource.', null, 403);
    }

    private function fromService(array $result): JsonResponse
    {
        return $this->respond($result['status'], $result['message']);
    }

    private function exceptionToResponse(Exception $e): JsonResponse
    {
        $message = config('app.debug')
            ? $e->getMessage() . ' Line ' . $e->getLine() . ' File ' . $e->getFile()
            : 'Something went wrong, please try again later.';

        return $this->respond(false, $message, null, 500);
    }

    private function isSuperAdmin(): bool
    {
        $user = auth()->user();

        return $user->hasRole('Super Admin') || Gate::allows('users_manage');
    }
}
