<?php

namespace App\Http\Controllers\Api;

use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\PatientRequest;
use App\Models\Activity;
use App\Models\Documents;
use App\Models\PatientNote;
use App\Models\Patients;
use App\Services\PatientManagement\PatientService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

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
     * Get patient tab counts
     */
    public function getTabCounts(int $id): JsonResponse
    {
        try {
            $accountId = Auth::user()->account_id;
            
            $counts = [
                'appointments' => \DB::table('appointments')->where('patient_id', $id)->where('account_id', $accountId)->count(),
                'consultations' => \DB::table('appointments')->where('patient_id', $id)->where('appointment_type_id', 1)->where('deleted_at',null)->count(),
                'treatments' => \DB::table('appointments')->where('patient_id', $id)->where('account_id', $accountId)->where('deleted_at',null)->where('appointment_type_id', 2)->count(),
                'vouchers' => \DB::table('user_vouchers')->where('user_id', $id)->count(),
                'documents' => \DB::table('documents')->where('user_id', $id)->count(),
                'plans' => \DB::table('packages')->where('patient_id', $id)->count(),
                'invoices' => \DB::table('invoices')->where('patient_id', $id)->count(),
                'refunds' => \DB::table('package_advances')->where('patient_id', $id)->where('is_refund', 1)->count(),
                'activity_logs' => \DB::table('activities')->where('patient_id', $id)->whereNotNull('description')->count(),
            ];

            return ApiHelper::apiResponse($this->success, 'Tab counts retrieved', true, $counts);
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
     * Get patient consultations datatable (appointment_type_id = 1)
     */
    public function consultationsDatatable(int $id, Request $request): JsonResponse
    {
        try {
            $result = $this->patientService->getPatientConsultations($id, $request);
            return response()->json($result);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get patient treatments datatable (appointment_type_id = 2)
     */
    public function treatmentsDatatable(int $id, Request $request): JsonResponse
    {
        try {
            $result = $this->patientService->getPatientTreatments($id, $request);
            return response()->json($result);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Upload document for patient (OPTIMIZED API)
     */
    public function uploadDocument(int $id, Request $request): JsonResponse
    {
        try {
            \Log::info('uploadDocument called', ['patient_id' => $id, 'has_file' => $request->hasFile('file')]);
            
            if (!Gate::allows('patients_document_create')) {
                return response()->json([
                    'status' => false,
                    'message' => 'You are not authorized to access this resource.'
                ], 403);
            }

            // Check if file exists first
            if (!$request->hasFile('file')) {
                return response()->json([
                    'status' => false,
                    'message' => 'No file was uploaded. Please select a file.'
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'document_type' => 'required|string|in:consent_form,consultation_form,others',
                'file' => 'required|file|mimes:jpg,jpeg,png,pdf,docx,xlsx|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first()
                ], 422);
            }

            $patient = Patients::where('id', $id)
                ->where('account_id', Auth::user()->account_id)
                ->first();

            if (!$patient) {
                return response()->json([
                    'status' => false,
                    'message' => 'Patient not found'
                ], 404);
            }

            $file = $request->file('file');
            
            // Get extension with fallback
            $ext = $file->getClientOriginalExtension();
            if (empty($ext)) {
                $ext = $file->guessExtension() ?: 'bin';
            }
            
            // Generate unique filename
            $fileName = time() . '_' . uniqid() . '.' . $ext;
            
            // Ensure storage directory exists
            $storagePath = storage_path('app/public/patient_image');
            if (!file_exists($storagePath)) {
                mkdir($storagePath, 0755, true);
            }
            
            // Move file directly instead of using storeAs
            $file->move($storagePath, $fileName);
            
            $path = 'patient_image/' . $fileName;

            $document = Documents::create([
                'name' => $file->getClientOriginalName(),
                'document_type' => $request->document_type,
                'url' => $path,
                'user_id' => $patient->id,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Document uploaded successfully',
                'data' => [
                    'id' => $document->id,
                    'name' => $document->name,
                    'url' => $path,
                ]
            ]);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Update document for patient (OPTIMIZED API)
     */
    public function updateDocument(int $id, int $documentId, Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('patients_document_edit')) {
                return response()->json([
                    'status' => false,
                    'message' => 'You are not authorized to access this resource.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'document_type' => 'required|string|in:consent_form,consultation_form,others',
                'file' => 'nullable|file|mimes:jpg,jpeg,png,pdf,docx,xlsx|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first()
                ], 422);
            }

            $patient = Patients::where('id', $id)
                ->where('account_id', Auth::user()->account_id)
                ->first();

            if (!$patient) {
                return response()->json([
                    'status' => false,
                    'message' => 'Patient not found'
                ], 404);
            }

            $document = Documents::where('id', $documentId)
                ->where('user_id', $patient->id)
                ->first();

            if (!$document) {
                return response()->json([
                    'status' => false,
                    'message' => 'Document not found'
                ], 404);
            }

            // Update document_type
            $document->document_type = $request->document_type;

            // Handle file upload if new file provided
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                
                // Get extension with fallback
                $ext = $file->getClientOriginalExtension();
                if (empty($ext)) {
                    $ext = $file->guessExtension() ?: 'bin';
                }
                
                // Generate unique filename
                $fileName = time() . '_' . uniqid() . '.' . $ext;
                
                // Ensure storage directory exists
                $storagePath = storage_path('app/public/patient_image');
                if (!file_exists($storagePath)) {
                    mkdir($storagePath, 0755, true);
                }
                
                // Delete old file if exists
                $oldFilePath = storage_path('app/public/' . $document->url);
                if (file_exists($oldFilePath)) {
                    @unlink($oldFilePath);
                }
                
                // Move new file
                $file->move($storagePath, $fileName);
                
                $document->url = 'patient_image/' . $fileName;
            }

            $document->save();

            return response()->json([
                'status' => true,
                'message' => 'Document updated successfully',
                'data' => [
                    'id' => $document->id,
                    'name' => $document->name,
                    'url' => $document->url,
                ]
            ]);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get patient activity history from activities table
     */
    public function getActivityHistory(int $id): JsonResponse
    {
        try {
            $patient = Patients::where('id', $id)
                ->where('account_id', Auth::user()->account_id)
                ->first();

            if (!$patient) {
                return response()->json([
                    'status' => false,
                    'message' => 'Patient not found'
                ], 404);
            }

            // Use shared ActivityLogService
            $activities = \App\Services\ActivityLogService::getActivityLogs([
                'patient_id' => $id
            ]);

            return response()->json([
                'status' => true,
                'data' => $activities
            ]);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Build activity description from activity record if description is not set
     * Format: [User] booked a [Service] Consultation for [Patient] in [Location] on [Date]
     */
    private function buildActivityDescription($activity): string
    {
        $action = $activity->action ?? 'Activity';
        $patient = $activity->patient ?? '';
        $service = $activity->service ?? '';
        $location = $activity->location ?? '';
        $amount = $activity->amount ?? '';
        $planId = $activity->plan_id ?? $activity->planId ?? '';
        $appointmentType = $activity->appointment_type ?? '';
        $createdBy = $activity->created_by ?? '';
        $scheduleDate = $activity->schedule_date ?? '';
        
        // Get creator name if we have created_by ID
        $creatorName = '';
        if ($createdBy) {
            $creator = \DB::table('users')->where('id', $createdBy)->first();
            $creatorName = $creator->name ?? 'System';
        } else {
            $creatorName = 'System';
        }
        
        // Format schedule date
        $dateStr = '';
        if ($scheduleDate) {
            $dateStr = date('Y-m-d', strtotime($scheduleDate));
        } elseif ($activity->created_at) {
            $dateStr = date('Y-m-d', strtotime($activity->created_at));
        }
        
        $type = $activity->activity_type ?? '';
        
        switch ($type) {
            case 'lead_created':
                return '<span class="highlight">' . $creatorName . '</span> created a lead for <span class="highlight-orange">' . $patient . '</span>' . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' on ' . $dateStr : '');
            
            case 'lead_booked':
                return '<span class="highlight">' . $creatorName . '</span> booked a <span class="highlight-orange">' . ($service ?: 'Service') . '</span> Consultation for <span class="highlight-orange">' . $patient . '</span>' . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' on ' . $dateStr : '');
            
            case 'lead_arrived':
                return '<span class="highlight">' . $creatorName . '</span> marked <span class="highlight-orange">' . $patient . '</span> as arrived' . ($service ? ' for <span class="highlight-orange">' . $service . '</span>' : '') . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' on ' . $dateStr : '');
            
            case 'consultation_booked':
            case 'Consultancy':
                return '<span class="highlight">' . $creatorName . '</span> booked a <span class="highlight-orange">' . ($service ?: 'Service') . '</span> Consultation for <span class="highlight-orange">' . $patient . '</span>' . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' on ' . $dateStr : '');
            
            case 'treatment_booked':
                return '<span class="highlight">' . $creatorName . '</span> booked a <span class="highlight-orange">' . ($service ?: 'Service') . '</span> Treatment for <span class="highlight-orange">' . $patient . '</span>' . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' on ' . $dateStr : '');
            
            case 'package_created':
                return '<span class="highlight">' . $creatorName . '</span> created Package <span class="highlight-orange">Plan Id: ' . $planId . '</span>' . ($amount ? ' for Rs. ' . $amount : '') . ' for <span class="highlight-orange">' . $patient . '</span>' . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' on ' . $dateStr : '');
            
            case 'payment_received':
                return '<span class="highlight">' . $creatorName . '</span> received payment Rs. ' . $amount . ' from <span class="highlight-orange">' . $patient . '</span>' . ($planId ? ' for <span class="highlight-orange">Plan Id: ' . $planId . '</span>' : '') . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' on ' . $dateStr : '');
            
            case 'refund_made':
                return '<span class="highlight">' . $creatorName . '</span> made refund Rs. ' . $amount . ' to <span class="highlight-orange">' . $patient . '</span>' . ($planId ? ' for <span class="highlight-orange">Plan Id: ' . $planId . '</span>' : '') . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' on ' . $dateStr : '');
            
            case 'invoice_created':
                return '<span class="highlight">' . $creatorName . '</span> created invoice Rs. ' . $amount . ' for <span class="highlight-orange">' . ($appointmentType ?: $service ?: 'Consultation') . '</span>' . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' on ' . $dateStr : '');
            
            default:
                // Fallback for existing records without activity_type - check action field
                if ($action == 'booked') {
                    return '<span class="highlight">' . $creatorName . '</span> booked a <span class="highlight-orange">' . ($service ?: $appointmentType ?: 'Service') . '</span> Consultation for <span class="highlight-orange">' . $patient . '</span>' . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' on ' . $dateStr : '');
                }
                if ($action == 'received') {
                    return '<span class="highlight">' . $creatorName . '</span> received Rs. ' . $amount . ' for <span class="highlight-orange">' . ($appointmentType ?: 'Consultation') . '</span>' . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' on ' . $dateStr : '');
                }
                return '<span class="highlight">' . $creatorName . '</span> ' . strtolower($action) . ($patient ? ' for <span class="highlight-orange">' . $patient . '</span>' : '') . ($service ? ' - ' . $service : '') . ($location ? ' in <span class="highlight">' . $location . '</span>' : '') . ($dateStr ? ' on ' . $dateStr : '');
        }
    }

    /**
     * Get patient activity history (legacy - from multiple tables)
     * Keep for backward compatibility
     */
    public function getActivityHistoryLegacy(int $id): JsonResponse
    {
        try {
            $patient = Patients::where('id', $id)
                ->where('account_id', Auth::user()->account_id)
                ->first();

            if (!$patient) {
                return response()->json([
                    'status' => false,
                    'message' => 'Patient not found'
                ], 404);
            }

            $activities = [];
            
            // Activity type priorities (lower number = appears first when same timestamp)
            $typePriority = [
                'lead_created' => 1,
                'consultation_booked' => 2,
                'treatment_booked' => 3,
                'consultation_arrived' => 4,
                'treatment_arrived' => 5,
                'package_created' => 6,
                'service_added' => 7,
                'payment_made' => 8,
                'refund_made' => 9,
                'invoice_created' => 10,
            ];

            // 1. Lead Created - from leads table
            $leads = \DB::table('leads')
                ->where('patient_id', $id)
                ->select('id', 'created_at', 'location_id')
                ->get();

            foreach ($leads as $lead) {
                $location = \DB::table('locations')
                    ->leftJoin('cities', 'locations.city_id', '=', 'cities.id')
                    ->where('locations.id', $lead->location_id)
                    ->select('locations.name as location_name', 'cities.name as city_name')
                    ->first();
                
                $locationStr = $location ? ($location->city_name . '-' . $location->location_name) : '';
                
                $activities[] = [
                    'type' => 'lead_created',
                    'description' => '<span class="highlight-purple">Lead Created</span> for <span class="highlight">' . $patient->name . '</span>' . ($locationStr ? ' at <span class="location">' . $locationStr . '</span>' : ''),
                    'created_at' => $lead->created_at,
                ];
            }

            // 2. Appointments - consultation booked, treatment booked, arrived
            $appointments = \DB::table('appointments')
                ->leftJoin('locations', 'appointments.location_id', '=', 'locations.id')
                ->leftJoin('cities', 'locations.city_id', '=', 'cities.id')
                ->leftJoin('appointment_statuses', 'appointments.appointment_status_id', '=', 'appointment_statuses.id')
                ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
                ->where('appointments.patient_id', $id)
                ->select(
                    'appointments.id',
                    'appointments.created_at',
                    'appointments.appointment_status_id',
                    'appointments.appointment_type_id',
                    'services.name as service_name',
                    'locations.name as location_name',
                    'cities.name as city_name',
                    'appointment_statuses.name as status_name'
                )
                ->get();

            foreach ($appointments as $appt) {
                $locationStr = ($appt->city_name ?? '') . '-' . ($appt->location_name ?? '');
                $serviceName = $appt->service_name ?? 'Service';
                
                // appointment_type_id = 1 is Consultation, appointment_type_id = 2 is Treatment
                if ($appt->appointment_type_id == 1) {
                    // Consultation booked
                    $activities[] = [
                        'type' => 'consultation_booked',
                        'description' => '<span class="highlight-orange">Consultation Booked</span> for <span class="highlight">' . $serviceName . '</span> at <span class="location">' . $locationStr . '</span>',
                        'created_at' => $appt->created_at,
                    ];
                    
                    // Consultation arrived (status = arrived/completed)
                    if (in_array($appt->appointment_status_id, [3, 4, 5])) {
                        $activities[] = [
                            'type' => 'consultation_arrived',
                            'description' => '<span class="highlight-green">Consultation Arrived</span> - ' . ($appt->status_name ?? 'Completed') . ' for <span class="highlight-orange">' . $serviceName . '</span> at <span class="location">' . $locationStr . '</span>',
                            'created_at' => $appt->created_at,
                        ];
                    }
                } else {
                    // Treatment booked (appointment_type_id = 2)
                    $activities[] = [
                        'type' => 'treatment_booked',
                        'description' => '<span class="highlight">Treatment Booked</span> for <span class="highlight-orange">' . $serviceName . '</span> at <span class="location">' . $locationStr . '</span>',
                        'created_at' => $appt->created_at,
                    ];
                    
                    // Treatment arrived (status = arrived/completed)
                    if (in_array($appt->appointment_status_id, [3, 4, 5])) {
                        $activities[] = [
                            'type' => 'treatment_arrived',
                            'description' => '<span class="highlight-green">Treatment Arrived</span> - ' . ($appt->status_name ?? 'Completed') . ' for <span class="highlight-orange">' . $serviceName . '</span> at <span class="location">' . $locationStr . '</span>',
                            'created_at' => $appt->created_at,
                        ];
                    }
                }
            }

            // 3. Packages created
            $packages = \DB::table('packages')
                ->leftJoin('locations', 'packages.location_id', '=', 'locations.id')
                ->leftJoin('cities', 'locations.city_id', '=', 'cities.id')
                ->where('packages.patient_id', $id)
                ->select('packages.id', 'packages.name', 'packages.total_price', 'packages.created_at', 'locations.name as location_name', 'cities.name as city_name')
                ->get();

            foreach ($packages as $pkg) {
                $locationStr = ($pkg->city_name ?? '') . '-' . ($pkg->location_name ?? '');
                
                $activities[] = [
                    'type' => 'package_created',
                    'description' => '<span class="highlight">Package Created</span> - <span class="highlight-purple">Plan Id: ' . $pkg->id . '</span> (' . ($pkg->name ?? 'Package') . ') for Rs. ' . number_format($pkg->total_price) . ' at <span class="location">' . $locationStr . '</span>',
                    'created_at' => $pkg->created_at,
                ];
            }

            // 4. Package Services added
            $packageServices = \DB::table('package_services')
                ->leftJoin('packages', 'package_services.package_id', '=', 'packages.id')
                ->leftJoin('services', 'package_services.service_id', '=', 'services.id')
                ->leftJoin('locations', 'packages.location_id', '=', 'locations.id')
                ->leftJoin('cities', 'locations.city_id', '=', 'cities.id')
                ->where('packages.patient_id', $id)
                ->select('package_services.id', 'package_services.package_id', 'package_services.created_at', 'services.name as service_name', 'locations.name as location_name', 'cities.name as city_name')
                ->get();

            foreach ($packageServices as $ps) {
                $locationStr = ($ps->city_name ?? '') . '-' . ($ps->location_name ?? '');
                
                $activities[] = [
                    'type' => 'service_added',
                    'description' => '<span class="highlight-orange">Service Added</span> - <span class="highlight">' . ($ps->service_name ?? 'Service') . '</span> to <span class="highlight-purple">Plan Id: ' . $ps->package_id . '</span> at <span class="location">' . $locationStr . '</span>',
                    'created_at' => $ps->created_at,
                ];
            }

            // 5. Package Advances (payments) - only show if amount > 0
            $payments = \DB::table('package_advances')
                ->leftJoin('packages', 'package_advances.package_id', '=', 'packages.id')
                ->leftJoin('locations', 'packages.location_id', '=', 'locations.id')
                ->leftJoin('cities', 'locations.city_id', '=', 'cities.id')
                ->where('packages.patient_id', $id)
                ->where('package_advances.cash_flow', 'in')
                ->where('package_advances.is_cancel', 0)
                ->where('package_advances.cash_amount', '>', 0)
                ->select('package_advances.id', 'package_advances.package_id', 'package_advances.cash_amount', 'package_advances.created_at', 'locations.name as location_name', 'cities.name as city_name')
                ->get();

            foreach ($payments as $payment) {
                $locationStr = ($payment->city_name ?? '') . '-' . ($payment->location_name ?? '');
                
                $activities[] = [
                    'type' => 'payment_made',
                    'description' => '<span class="highlight-green">Payment Received</span> Rs. ' . number_format($payment->cash_amount) . ' from <span class="highlight">' . $patient->name . '</span> for <span class="highlight-purple">Plan Id: ' . $payment->package_id . '</span> at <span class="location">' . $locationStr . '</span>',
                    'created_at' => $payment->created_at,
                ];
            }

            // 6. Refunds (package_advances where is_refund = 1)
            $refunds = \DB::table('package_advances')
                ->leftJoin('packages', 'package_advances.package_id', '=', 'packages.id')
                ->leftJoin('locations', 'packages.location_id', '=', 'locations.id')
                ->leftJoin('cities', 'locations.city_id', '=', 'cities.id')
                ->where('packages.patient_id', $id)
                ->where('package_advances.is_refund', 1)
                ->where('package_advances.is_cancel', 0)
                ->where('package_advances.cash_amount', '>', 0)
                ->select('package_advances.id', 'package_advances.package_id', 'package_advances.cash_amount', 'package_advances.created_at', 'locations.name as location_name', 'cities.name as city_name')
                ->get();

            foreach ($refunds as $refund) {
                $locationStr = ($refund->city_name ?? '') . '-' . ($refund->location_name ?? '');
                
                $activities[] = [
                    'type' => 'refund_made',
                    'description' => '<span class="highlight-orange">Refund Made</span> Rs. ' . number_format($refund->cash_amount) . ' to <span class="highlight">' . $patient->name . '</span> for <span class="highlight-purple">Plan Id: ' . $refund->package_id . '</span> at <span class="location">' . $locationStr . '</span>',
                    'created_at' => $refund->created_at,
                ];
            }

            // 7. Invoices
            $invoices = \DB::table('invoices')
                ->leftJoin('locations', 'invoices.location_id', '=', 'locations.id')
                ->leftJoin('cities', 'locations.city_id', '=', 'cities.id')
                ->leftJoin('appointments', 'invoices.appointment_id', '=', 'appointments.id')
                ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
                ->where('invoices.patient_id', $id)
                ->select('invoices.id', 'invoices.total_price', 'invoices.created_at', 'services.name as service_name', 'locations.name as location_name', 'cities.name as city_name')
                ->get();

            foreach ($invoices as $invoice) {
                $locationStr = ($invoice->city_name ?? '') . '-' . ($invoice->location_name ?? '');
                
                $activities[] = [
                    'type' => 'invoice_created',
                    'description' => '<span class="highlight-green">Invoice Created</span> Rs. ' . number_format($invoice->total_price) . ' for <span class="highlight-orange">' . ($invoice->service_name ?? 'Consultation') . '</span> at <span class="location">' . $locationStr . '</span>',
                    'created_at' => $invoice->created_at,
                ];
            }

            // Sort by created_at descending (newest first), then by priority for same timestamp
            // Timeline displays bottom-to-top, so higher priority number appears first (at bottom)
            usort($activities, function($a, $b) use ($typePriority) {
                $timeA = strtotime($a['created_at']);
                $timeB = strtotime($b['created_at']);
                
                if ($timeA != $timeB) {
                    return $timeB - $timeA; // Newest first
                }
                
                // Same timestamp - sort by priority (higher priority number first for bottom-to-top display)
                $priorityA = $typePriority[$a['type']] ?? 99;
                $priorityB = $typePriority[$b['type']] ?? 99;
                return $priorityB - $priorityA;
            });

            return response()->json([
                'status' => true,
                'data' => $activities
            ]);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get voucher usage history for a specific user voucher
     */
    public function getVoucherHistory($patientId, $userVoucherId): JsonResponse
    {
        try {
            if (!Gate::allows('vouchers_manage')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
            }

            $userVoucher = \App\Models\UserVouchers::with(['user', 'voucher'])->findOrFail($userVoucherId);

            // Verify the voucher belongs to this patient
            if ($userVoucher->user_id != $patientId) {
                return ApiHelper::apiResponse($this->error, 'Voucher does not belong to this patient.', false);
            }

            $voucher = $userVoucher->voucher;

            // Get all package_vouchers entries for this user and voucher
            // Note: In package_vouchers, voucher_id refers to discounts.id (the voucher definition)
            // We need to match using the voucher_id from user_vouchers
            $rawHistory = \App\Models\PackageVouchers::where('user_id', $patientId)
                ->where('voucher_id', $userVoucher->voucher_id)
                ->orderBy('created_at', 'desc')
                ->get();
            
            $usageHistory = $rawHistory->map(function ($item) {
                    // Get package ID - try package_id first, then look up from package_random_id
                    $packageId = $item->package_id;
                    $packageRandomId = $item->package_random_id;
                    
                    if (!$packageId && $packageRandomId) {
                        $package = \App\Models\Packages::where('random_id', $packageRandomId)->first();
                        $packageId = $package ? $package->id : null;
                    }
                    
                    // Get service name by matching main_service_id to bundle_id in bundles table
                    $serviceName = 'N/A';
                    
                    if (!empty($item->main_service_id)) {
                        $bundle = \DB::table('bundles')
                            ->where('id', $item->main_service_id)
                            ->select('name')
                            ->first();
                        if ($bundle) {
                            $serviceName = $bundle->name;
                        }
                    }

                    return [
                        'package_id' => $packageId,
                        'service_name' => $serviceName,
                        'amount_deducted' => $item->amount ?? 0,
                        'applied_date' => $item->created_at ? $item->created_at->format('M d, Y h:i A') : '-',
                    ];
                });

            // Calculate totals
            $totalAmount = $userVoucher->total_amount ?? 0;
            $currentBalance = $userVoucher->amount ?? 0;
            $consumedAmount = $totalAmount - $currentBalance;

            return ApiHelper::apiResponse($this->success, 'Voucher history retrieved successfully.', true, [
                'voucher_name' => $voucher ? $voucher->name : 'N/A',
                'total_amount' => $totalAmount,
                'consumed_amount' => $consumedAmount,
                'balance' => $currentBalance,
                'history' => $usageHistory,
            ]);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Get patient notes
     */
    public function getNotes(int $id): JsonResponse
    {
        try {
            $patient = Patients::where('id', $id)
                ->where('account_id', Auth::user()->account_id)
                ->first();

            if (!$patient) {
                return response()->json([
                    'status' => false,
                    'message' => 'Patient not found'
                ], 404);
            }

            $notes = PatientNote::where('patient_id', $id)
                ->with('creator:id,name')
                ->orderBy('is_pinned', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => true,
                'data' => $notes
            ]);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Add a note to patient
     */
    public function addNote(int $id, Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'note' => 'required|string|max:2000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first()
                ], 422);
            }

            $patient = Patients::where('id', $id)
                ->where('account_id', Auth::user()->account_id)
                ->first();

            if (!$patient) {
                return response()->json([
                    'status' => false,
                    'message' => 'Patient not found'
                ], 404);
            }

            $note = PatientNote::create([
                'patient_id' => $id,
                'created_by' => Auth::id(),
                'note' => $request->note,
                'is_pinned' => false,
            ]);

            $note->load('creator:id,name');

            // Log activity
            Activity::create([
                'account_id' => Auth::user()->account_id,
                'patient_id' => $id,
                'patient' => $patient->name,
                'activity_type' => 'note_added',
                'action' => 'added',
                'description' => '<span class="highlight">' . Auth::user()->name . '</span> added a note for <span class="highlight-orange">' . $patient->name . '</span>',
                'created_by' => Auth::id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Note added successfully',
                'data' => $note
            ]);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Delete a patient note
     */
    public function deleteNote(int $id, int $noteId): JsonResponse
    {
        try {
            $patient = Patients::where('id', $id)
                ->where('account_id', Auth::user()->account_id)
                ->first();

            if (!$patient) {
                return response()->json([
                    'status' => false,
                    'message' => 'Patient not found'
                ], 404);
            }

            $note = PatientNote::where('id', $noteId)
                ->where('patient_id', $id)
                ->first();

            if (!$note) {
                return response()->json([
                    'status' => false,
                    'message' => 'Note not found'
                ], 404);
            }

            // Check permission: only super admin or note creator can delete
            $user = Auth::user();
            $isSuperAdmin = $user->hasRole('Super Admin') || Gate::allows('users_manage');
            if (!$isSuperAdmin && $note->created_by != $user->id) {
                return response()->json([
                    'status' => false,
                    'message' => 'You can only delete notes you created'
                ], 403);
            }

            $note->delete();

            return response()->json([
                'status' => true,
                'message' => 'Note deleted successfully'
            ]);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Update a patient note
     */
    public function updateNote(int $id, int $noteId, Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'note' => 'required|string|max:2000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => $validator->errors()->first()
                ], 422);
            }

            $patient = Patients::where('id', $id)
                ->where('account_id', Auth::user()->account_id)
                ->first();

            if (!$patient) {
                return response()->json([
                    'status' => false,
                    'message' => 'Patient not found'
                ], 404);
            }

            $note = PatientNote::where('id', $noteId)
                ->where('patient_id', $id)
                ->first();

            if (!$note) {
                return response()->json([
                    'status' => false,
                    'message' => 'Note not found'
                ], 404);
            }

            // Check permission: only super admin or note creator can edit
            $user = Auth::user();
            $isSuperAdmin = $user->hasRole('Super Admin') || Gate::allows('users_manage');
            if (!$isSuperAdmin && $note->created_by != $user->id) {
                return response()->json([
                    'status' => false,
                    'message' => 'You can only edit notes you created'
                ], 403);
            }

            $note->note = $request->note;
            $note->save();

            $note->load('creator:id,name');

            return response()->json([
                'status' => true,
                'message' => 'Note updated successfully',
                'data' => $note
            ]);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Toggle pin status of a note
     */
    public function togglePinNote(int $id, int $noteId): JsonResponse
    {
        try {
            $patient = Patients::where('id', $id)
                ->where('account_id', Auth::user()->account_id)
                ->first();

            if (!$patient) {
                return response()->json([
                    'status' => false,
                    'message' => 'Patient not found'
                ], 404);
            }

            $note = PatientNote::where('id', $noteId)
                ->where('patient_id', $id)
                ->first();

            if (!$note) {
                return response()->json([
                    'status' => false,
                    'message' => 'Note not found'
                ], 404);
            }

            $note->is_pinned = !$note->is_pinned;
            $note->save();

            return response()->json([
                'status' => true,
                'message' => $note->is_pinned ? 'Note pinned' : 'Note unpinned',
                'data' => $note
            ]);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
}
