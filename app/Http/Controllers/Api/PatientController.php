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
            return response()->json($this->patientService->getDatatableData($request));
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function search(Request $request): JsonResponse
    {
        try {
            $search = $request->input('search', $request->input('q', ''));
            $patients = $this->patientService->searchPatients($search, auth()->user()->account_id);

            return $this->success('Patients retrieved.', ['patients' => $patients]);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CRUD
    |--------------------------------------------------------------------------
    */

    public function create(): JsonResponse
    {
        try {
            if (!Gate::allows('patients_manage')) {
                return $this->unauthorized();
            }

            return $this->success('Record found.', $this->patientService->getCreateData());
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function store(PatientRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('patients_manage')) {
                return $this->unauthorized();
            }

            $result = $this->patientService->create($request->validated());

            return $this->fromService($result);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $result = $this->patientService->getPatient($id);

            return $result
                ? $this->success('Record found.', $result)
                : $this->fail('Record not found.');
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function edit(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('patients_manage')) {
                return $this->unauthorized();
            }

            $data = $this->patientService->getEditData($id);

            return $data
                ? $this->success('Record found.', $data)
                : $this->notFound('Patient not found.');
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function update(PatientRequest $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('patients_manage')) {
                return $this->unauthorized();
            }

            $result = $this->patientService->update($id, $request->validated());

            return $this->fromService($result);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('patients_manage')) {
                return $this->unauthorized();
            }

            $result = $this->patientService->delete($id);

            return $this->fromService($result);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function status(PatientStatusRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('patients_manage')) {
                return $this->unauthorized();
            }

            $result = $this->patientService->changeStatus(
                (int) $request->validated('id'),
                (int) $request->validated('status'),
            );

            return $this->fromService($result);
        } catch (Exception $e) {
            return $this->errorResponse($e);
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
            $result = $this->patientService->getPatient($id);

            return $result
                ? $this->success('Record found.', $result)
                : $this->notFound('Record not found.');
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function getTabCounts(int $id): JsonResponse
    {
        try {
            $counts = $this->patientService->getTabCounts($id);

            return $counts
                ? $this->success('Tab counts retrieved.', $counts)
                : $this->notFound('Patient not found.');
        } catch (Exception $e) {
            return $this->errorResponse($e);
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
            if (!Gate::allows('patients_manage') && !Gate::allows('users_manage')) {
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
            return $this->errorResponse($e);
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
            $result = $this->patientService->assignMembership(
                $request->patientId(),
                $request->validated('membership_code'),
            );

            return $this->fromService($result);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function assignVoucher(AssignVoucherRequest $request): JsonResponse
    {
        try {
            $result = $this->patientService->assignVoucher(
                $request->patientId(),
                (int) $request->validated('voucher_id'),
                (float) $request->validated('amount'),
            );

            return $this->fromService($result);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function addReferral(AddReferralRequest $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('patients_manage')) {
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
            return $this->errorResponse($e);
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
            $data = $this->patientService->getPatientAppointments($id, $request);
            $data['data'] = PatientAppointmentResource::collection($data['data']);

            return response()->json($data);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function consultationsDatatable(int $id, Request $request): JsonResponse
    {
        try {
            $data = $this->patientService->getPatientConsultations($id, $request);
            $data['data'] = PatientAppointmentResource::collection($data['data']);

            return response()->json($data);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function treatmentsDatatable(int $id, Request $request): JsonResponse
    {
        try {
            $data = $this->patientService->getPatientTreatments($id, $request);
            $data['data'] = PatientAppointmentResource::collection($data['data']);

            return response()->json($data);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Documents
    |--------------------------------------------------------------------------
    */

    public function uploadDocument(int $id, PatientDocumentRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('patients_document_create')) {
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
            return $this->errorResponse($e);
        }
    }

    public function updateDocument(int $id, int $documentId, PatientDocumentRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('patients_document_edit')) {
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
            return $this->errorResponse($e);
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
            $activities = $this->patientService->getActivityHistory($id);

            return $activities !== null
                ? $this->success('Activity history retrieved.', $activities)
                : $this->notFound('Patient not found.');
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function getVoucherHistory(int $patientId, int $userVoucherId): JsonResponse
    {
        try {
            if (!Gate::allows('vouchers_manage')) {
                return $this->unauthorized();
            }

            $result = $this->patientService->getVoucherHistory($patientId, $userVoucherId);

            return $result['status']
                ? $this->success($result['message'], $result['data'])
                : $this->respond(false, $result['message'], null, $result['code'] ?? 400);
        } catch (Exception $e) {
            return $this->errorResponse($e);
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
            $result = $this->patientService->getNotes($id);

            if (!$result) {
                return $this->notFound('Patient not found.');
            }

            return $this->success('Notes retrieved.', PatientNoteResource::collection($result['notes']));
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function addNote(int $id, PatientNoteRequest $request): JsonResponse
    {
        try {
            $result = $this->patientService->addNote($id, $request->validated('note'));

            if (!$result) {
                return $this->notFound('Patient not found.');
            }

            return $this->success('Note added successfully.', new PatientNoteResource($result['note']));
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function updateNote(int $id, int $noteId, PatientNoteRequest $request): JsonResponse
    {
        try {
            $result = $this->patientService->updateNote($id, $noteId, $request->validated('note'), $this->isSuperAdmin());

            return $result['status']
                ? $this->success($result['message'], new PatientNoteResource($result['note']))
                : $this->respond(false, $result['message'], null, $result['code'] ?? 400);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function deleteNote(int $id, int $noteId): JsonResponse
    {
        try {
            $result = $this->patientService->deleteNote($id, $noteId, $this->isSuperAdmin());

            return $result['status']
                ? $this->success($result['message'])
                : $this->respond(false, $result['message'], null, $result['code'] ?? 400);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }

    public function togglePinNote(int $id, int $noteId): JsonResponse
    {
        try {
            $result = $this->patientService->togglePinNote($id, $noteId);

            return $result['status']
                ? $this->success($result['message'], new PatientNoteResource($result['note']))
                : $this->respond(false, $result['message'], null, $result['code'] ?? 400);
        } catch (Exception $e) {
            return $this->errorResponse($e);
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

    private function errorResponse(Exception $e): JsonResponse
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
