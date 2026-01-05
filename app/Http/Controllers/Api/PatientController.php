<?php

namespace App\Http\Controllers\Api;

use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\PatientRequest;
use App\Services\PatientManagement\PatientService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PatientController extends Controller
{
    private PatientService $patientService;
    private int $success;
    private int $error;
    private int $unauthorized;

    public function __construct(PatientService $patientService)
    {
        $this->patientService = $patientService;
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    /**
     * Get datatable data for patients listing
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $records = $this->patientService->getDatatableData($request);
            return response()->json($records);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get data for creating a new patient
     */
    public function create(): JsonResponse
    {
        try {
            if (!Gate::allows('patients_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $data = $this->patientService->getCreateData();

            return ApiHelper::apiResponse($this->success, 'Record found', true, $data);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Store a newly created patient
     */
    public function store(PatientRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('patients_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $result = $this->patientService->create($request->validated());

            return ApiHelper::apiResponse($this->success, $result['message'], $result['status']);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get patient details
     */
    public function show(int $id): JsonResponse
    {
        try {
            $result = $this->patientService->getPatient($id);

            if (!$result) {
                return ApiHelper::apiResponse($this->success, 'Record not found', false);
            }

            return ApiHelper::apiResponse($this->success, 'Record found', true, $result);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get data for editing a patient
     */
    public function edit(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('patients_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $data = $this->patientService->getEditData($id);

            if (!$data) {
                return ApiHelper::apiResponse($this->success, 'Patient not found.', false);
            }

            return ApiHelper::apiResponse($this->success, 'Record found.', true, $data);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Update a patient
     */
    public function update(PatientRequest $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('patients_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $data = $request->validated();
            $data['old_phone'] = $request->input('old_phone');

            $result = $this->patientService->update($id, $data);

            return ApiHelper::apiResponse($this->success, $result['message'], $result['status']);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Delete a patient
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('patients_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $result = $this->patientService->delete($id);

            return ApiHelper::apiResponse($this->success, $result['message'], $result['status']);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Change patient status (activate/inactivate)
     */
    public function status(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('patients_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $result = $this->patientService->changeStatus($request->id, $request->status);

            return ApiHelper::apiResponse($this->success, $result['message'], $result['status']);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get patient data by ID
     */
    public function getPatient(int $id): JsonResponse
    {
        try {
            $result = $this->patientService->getPatient($id);

            if (!$result) {
                return ApiHelper::apiResponse($this->success, 'Record not found', false);
            }

            return ApiHelper::apiResponse($this->success, 'Record found', true, $result);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Store patient image
     */
    public function storeImage(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('patients_manage') && !Gate::allows('users_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            if (!$request->hasFile('file')) {
                return ApiHelper::apiResponse($this->success, 'Please provide a valid image.', false);
            }

            $result = $this->patientService->storeImage($request->patient_id, $request->file('file'));

            if ($result['status']) {
                return ApiHelper::apiResponse($this->success, $result['message'], true, [
                    'image' => $result['image'],
                ]);
            }

            return ApiHelper::apiResponse($this->success, $result['message'], false);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Assign membership to patient
     */
    public function assignMembership(Request $request): JsonResponse
    {
        try {
            $patientId = $request->patient_id ?? $request->id;
            $result = $this->patientService->assignMembership($patientId, $request->membership_code);

            return ApiHelper::apiResponse($this->success, $result['message'], $result['status']);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Assign voucher to patient
     */
    public function assignVoucher(Request $request): JsonResponse
    {
        try {
            $patientId = $request->patient_id ?? $request->id;
            $result = $this->patientService->assignVoucher(
                $patientId,
                $request->voucher_id,
                $request->amount
            );

            return ApiHelper::apiResponse($this->success, $result['message'], $result['status']);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Add referral to patient
     */
    public function addReferral(Request $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('patients_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $request->validate([
                'membership_code' => 'required|string',
            ]);

            $result = $this->patientService->addReferral($id, $request->membership_code);

            $statusCode = $result['status'] ? $this->success : $this->error;

            return ApiHelper::apiResponse($statusCode, $result['message'], $result['status'], $result['status'] ? [
                'referral' => $result['referral'] ?? null,
                'patient' => $result['patient'] ?? null,
            ] : []);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Search patients (AJAX)
     */
    public function search(Request $request): JsonResponse
    {
        try {
            $search = $request->input('search', $request->input('q', ''));
            $accountId = auth()->user()->account_id;

            $patients = $this->patientService->searchPatients($search, $accountId);

            return response()->json([
                'data' => [
                    'patients' => $patients
                ]
            ]);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get patient appointments datatable (OPTIMIZED)
     */
    public function appointmentsDatatable(int $id, Request $request): JsonResponse
    {
        try {
            $result = $this->patientService->getPatientAppointments($id, $request);
            return response()->json($result);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get patient vouchers datatable (OPTIMIZED)
     */
    public function vouchersDatatable(int $id, Request $request): JsonResponse
    {
        try {
            $result = $this->patientService->getPatientVouchers($id, $request);
            return response()->json($result);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
