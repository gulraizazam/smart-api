<?php

namespace App\Http\Controllers\Api;

use App\HelperModule\ApiHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\PatientDocumentRequest;
use App\Http\Requests\PatientNoteRequest;
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

class PatientController extends Controller
{
    private readonly int $success;
    private readonly int $error;
    private readonly int $unauthorized;

    public function __construct(
        private readonly PatientService $patientService,
    ) {
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json($this->patientService->getDatatableData($request));
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function create(): JsonResponse
    {
        try {
            if (!Gate::allows('patients_manage')) {
                return $this->unauthorizedResponse();
            }

            return ApiHelper::apiResponse($this->success, 'Record found', true, $this->patientService->getCreateData());
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function store(PatientRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('patients_manage')) {
                return $this->unauthorizedResponse();
            }

            $result = $this->patientService->create($request->validated());

            return ApiHelper::apiResponse($this->success, $result['message'], $result['status']);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function show(int $id): JsonResponse
    {
        try {
            $result = $this->patientService->getPatient($id);

            return $result
                ? ApiHelper::apiResponse($this->success, 'Record found', true, $result)
                : ApiHelper::apiResponse($this->success, 'Record not found', false);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function getTabCounts(int $id): JsonResponse
    {
        try {
            $patient = $this->findPatientOrFail($id);
            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient not found'], 404);
            }

            $accountId = Auth::user()->account_id;

            $counts = [
                'appointments' => $patient->appointments()->where('account_id', $accountId)->count(),
                'consultations' => $patient->appointments()->where('appointment_type_id', 1)->count(),
                'treatments' => $patient->appointments()->where('account_id', $accountId)->where('appointment_type_id', 2)->count(),
                'vouchers' => \App\Models\UserVouchers::where('user_id', $id)->count(),
                'documents' => $patient->documents()->count(),
                'plans' => $patient->packages()->count(),
                'invoices' => $patient->invoices()->count(),
                'refunds' => \DB::table('package_advances')->where('patient_id', $id)->where('is_refund', 1)->count(),
                'activity_logs' => \App\Models\Activity::where('patient_id', $id)->whereNotNull('description')->count(),
            ];

            return ApiHelper::apiResponse($this->success, 'Tab counts retrieved', true, $counts);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function edit(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('patients_manage')) {
                return $this->unauthorizedResponse();
            }

            $data = $this->patientService->getEditData($id);

            return $data
                ? ApiHelper::apiResponse($this->success, 'Record found.', true, $data)
                : ApiHelper::apiResponse($this->success, 'Patient not found.', false);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function update(PatientRequest $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('patients_manage')) {
                return $this->unauthorizedResponse();
            }

            $data = $request->validated();
            $data['old_phone'] = $request->input('old_phone');

            $result = $this->patientService->update($id, $data);

            return ApiHelper::apiResponse($this->success, $result['message'], $result['status']);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('patients_manage')) {
                return $this->unauthorizedResponse();
            }

            $result = $this->patientService->delete($id);

            return ApiHelper::apiResponse($this->success, $result['message'], $result['status']);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function status(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('patients_manage')) {
                return $this->unauthorizedResponse();
            }

            $result = $this->patientService->changeStatus($request->id, $request->status);

            return ApiHelper::apiResponse($this->success, $result['message'], $result['status']);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function getPatient(int $id): JsonResponse
    {
        try {
            $result = $this->patientService->getPatient($id);

            return $result
                ? ApiHelper::apiResponse($this->success, 'Record found', true, $result)
                : ApiHelper::apiResponse($this->success, 'Record not found', false);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function storeImage(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('patients_manage') && !Gate::allows('users_manage')) {
                return $this->unauthorizedResponse();
            }

            if (!$request->hasFile('file')) {
                return ApiHelper::apiResponse($this->success, 'Please provide a valid image.', false);
            }

            $result = $this->patientService->storeImage($request->patient_id, $request->file('file'));

            return $result['status']
                ? ApiHelper::apiResponse($this->success, $result['message'], true, ['image' => $result['image']])
                : ApiHelper::apiResponse($this->success, $result['message'], false);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

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

    public function assignVoucher(Request $request): JsonResponse
    {
        try {
            $patientId = $request->patient_id ?? $request->id;
            $result = $this->patientService->assignVoucher($patientId, $request->voucher_id, $request->amount);

            return ApiHelper::apiResponse($this->success, $result['message'], $result['status']);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function addReferral(Request $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('patients_manage')) {
                return $this->unauthorizedResponse();
            }

            $request->validate(['membership_code' => 'required|string']);

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

    public function search(Request $request): JsonResponse
    {
        try {
            $search = $request->input('search', $request->input('q', ''));
            $patients = $this->patientService->searchPatients($search, auth()->user()->account_id);

            return response()->json(['data' => ['patients' => $patients]]);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function appointmentsDatatable(int $id, Request $request): JsonResponse
    {
        try {
            return response()->json($this->patientService->getPatientAppointments($id, $request));
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function consultationsDatatable(int $id, Request $request): JsonResponse
    {
        try {
            return response()->json($this->patientService->getPatientConsultations($id, $request));
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function treatmentsDatatable(int $id, Request $request): JsonResponse
    {
        try {
            return response()->json($this->patientService->getPatientTreatments($id, $request));
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function uploadDocument(int $id, PatientDocumentRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('patients_document_create')) {
                return response()->json(['status' => false, 'message' => 'You are not authorized to access this resource.'], 403);
            }

            $patient = $this->findPatientOrFail($id);
            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient not found'], 404);
            }

            $file = $request->file('file');
            $ext = $file->getClientOriginalExtension() ?: ($file->guessExtension() ?: 'bin');
            $fileName = time() . '_' . uniqid() . '.' . $ext;

            $storagePath = storage_path('app/public/patient_image');
            if (!file_exists($storagePath)) {
                mkdir($storagePath, 0755, true);
            }

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
                'data' => ['id' => $document->id, 'name' => $document->name, 'url' => $path],
            ]);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function updateDocument(int $id, int $documentId, PatientDocumentRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('patients_document_edit')) {
                return response()->json(['status' => false, 'message' => 'You are not authorized to access this resource.'], 403);
            }

            $patient = $this->findPatientOrFail($id);
            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient not found'], 404);
            }

            $document = Documents::where('id', $documentId)->where('user_id', $patient->id)->first();
            if (!$document) {
                return response()->json(['status' => false, 'message' => 'Document not found'], 404);
            }

            $document->document_type = $request->document_type;

            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $ext = $file->getClientOriginalExtension() ?: ($file->guessExtension() ?: 'bin');
                $fileName = time() . '_' . uniqid() . '.' . $ext;

                $storagePath = storage_path('app/public/patient_image');
                if (!file_exists($storagePath)) {
                    mkdir($storagePath, 0755, true);
                }

                // Delete old file
                $oldFilePath = storage_path('app/public/' . $document->url);
                if (file_exists($oldFilePath)) {
                    @unlink($oldFilePath);
                }

                $file->move($storagePath, $fileName);
                $document->url = 'patient_image/' . $fileName;
            }

            $document->save();

            return response()->json([
                'status' => true,
                'message' => 'Document updated successfully',
                'data' => ['id' => $document->id, 'name' => $document->name, 'url' => $document->url],
            ]);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function getActivityHistory(int $id): JsonResponse
    {
        try {
            $patient = $this->findPatientOrFail($id);
            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient not found'], 404);
            }

            $activities = \App\Services\ActivityLogService::getActivityLogs(['patient_id' => $id]);

            return response()->json(['status' => true, 'data' => $activities]);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function getActivityHistoryLegacy(int $id): JsonResponse
    {
        try {
            $patient = $this->findPatientOrFail($id);
            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient not found'], 404);
            }

            $activities = $this->buildLegacyActivityHistory($id, $patient);

            return response()->json(['status' => true, 'data' => $activities]);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function getVoucherHistory(int $patientId, int $userVoucherId): JsonResponse
    {
        try {
            if (!Gate::allows('vouchers_manage')) {
                return $this->unauthorizedResponse();
            }

            $userVoucher = \App\Models\UserVouchers::with(['user', 'voucher'])->findOrFail($userVoucherId);

            if ($userVoucher->user_id != $patientId) {
                return ApiHelper::apiResponse($this->error, 'Voucher does not belong to this patient.', false);
            }

            $rawHistory = \App\Models\PackageVouchers::where('user_id', $patientId)
                ->where('voucher_id', $userVoucher->voucher_id)
                ->orderByDesc('created_at')
                ->get();

            $usageHistory = $rawHistory->map(function ($item) {
                $packageId = $item->package_id;
                if (!$packageId && $item->package_random_id) {
                    $packageId = \App\Models\Packages::where('random_id', $item->package_random_id)->value('id');
                }

                $serviceName = 'N/A';
                if (!empty($item->main_service_id)) {
                    $serviceName = \DB::table('bundles')->where('id', $item->main_service_id)->value('name') ?? 'N/A';
                }

                return [
                    'package_id' => $packageId,
                    'service_name' => $serviceName,
                    'amount_deducted' => $item->amount ?? 0,
                    'applied_date' => $item->created_at?->format('M d, Y h:i A') ?? '-',
                ];
            });

            $totalAmount = $userVoucher->total_amount ?? 0;
            $currentBalance = $userVoucher->amount ?? 0;

            return ApiHelper::apiResponse($this->success, 'Voucher history retrieved successfully.', true, [
                'voucher_name' => $userVoucher->voucher?->name ?? 'N/A',
                'total_amount' => $totalAmount,
                'consumed_amount' => $totalAmount - $currentBalance,
                'balance' => $currentBalance,
                'history' => $usageHistory,
            ]);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function getNotes(int $id): JsonResponse
    {
        try {
            $patient = $this->findPatientOrFail($id);
            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient not found'], 404);
            }

            $notes = PatientNote::where('patient_id', $id)
                ->with('creator:id,name')
                ->orderByDesc('is_pinned')
                ->orderByDesc('created_at')
                ->get();

            return response()->json(['status' => true, 'data' => $notes]);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function addNote(int $id, PatientNoteRequest $request): JsonResponse
    {
        try {
            $patient = $this->findPatientOrFail($id);
            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient not found'], 404);
            }

            $note = PatientNote::create([
                'patient_id' => $id,
                'created_by' => Auth::id(),
                'note' => $request->note,
                'is_pinned' => false,
            ]);

            $note->load('creator:id,name');

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

            return response()->json(['status' => true, 'message' => 'Note added successfully', 'data' => $note]);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function deleteNote(int $id, int $noteId): JsonResponse
    {
        try {
            $patient = $this->findPatientOrFail($id);
            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient not found'], 404);
            }

            $note = PatientNote::where('id', $noteId)->where('patient_id', $id)->first();
            if (!$note) {
                return response()->json(['status' => false, 'message' => 'Note not found'], 404);
            }

            if (!$this->canManageNote($note)) {
                return response()->json(['status' => false, 'message' => 'You can only delete notes you created'], 403);
            }

            $note->delete();

            return response()->json(['status' => true, 'message' => 'Note deleted successfully']);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function updateNote(int $id, int $noteId, PatientNoteRequest $request): JsonResponse
    {
        try {
            $patient = $this->findPatientOrFail($id);
            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient not found'], 404);
            }

            $note = PatientNote::where('id', $noteId)->where('patient_id', $id)->first();
            if (!$note) {
                return response()->json(['status' => false, 'message' => 'Note not found'], 404);
            }

            if (!$this->canManageNote($note)) {
                return response()->json(['status' => false, 'message' => 'You can only edit notes you created'], 403);
            }

            $note->update(['note' => $request->note]);
            $note->load('creator:id,name');

            return response()->json(['status' => true, 'message' => 'Note updated successfully', 'data' => $note]);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function togglePinNote(int $id, int $noteId): JsonResponse
    {
        try {
            $patient = $this->findPatientOrFail($id);
            if (!$patient) {
                return response()->json(['status' => false, 'message' => 'Patient not found'], 404);
            }

            $note = PatientNote::where('id', $noteId)->where('patient_id', $id)->first();
            if (!$note) {
                return response()->json(['status' => false, 'message' => 'Note not found'], 404);
            }

            $note->update(['is_pinned' => !$note->is_pinned]);

            return response()->json([
                'status' => true,
                'message' => $note->is_pinned ? 'Note pinned' : 'Note unpinned',
                'data' => $note,
            ]);
        } catch (Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Private Helpers
    |--------------------------------------------------------------------------
    */

    private function unauthorizedResponse(): JsonResponse
    {
        return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
    }

    private function findPatientOrFail(int $id): ?Patients
    {
        return Patients::where('id', $id)
            ->where('account_id', Auth::user()->account_id)
            ->first();
    }

    private function canManageNote(PatientNote $note): bool
    {
        $user = Auth::user();
        $isSuperAdmin = $user->hasRole('Super Admin') || Gate::allows('users_manage');

        return $isSuperAdmin || $note->created_by === $user->id;
    }

    private function buildActivityDescription(object $activity): string
    {
        $creatorName = $activity->created_by
            ? (\DB::table('users')->where('id', $activity->created_by)->value('name') ?? 'System')
            : 'System';

        $dateStr = match (true) {
            !empty($activity->schedule_date) => date('Y-m-d', strtotime($activity->schedule_date)),
            !empty($activity->created_at) => date('Y-m-d', strtotime($activity->created_at)),
            default => '',
        };

        $patient = $activity->patient ?? '';
        $service = $activity->service ?? '';
        $location = $activity->location ?? '';
        $amount = $activity->amount ?? '';
        $planId = $activity->plan_id ?? $activity->planId ?? '';
        $appointmentType = $activity->appointment_type ?? '';
        $type = $activity->activity_type ?? '';

        $hl = fn(string $text, string $class = 'highlight') => "<span class=\"{$class}\">{$text}</span>";
        $loc = $location ? " in {$hl($location)}" : '';
        $date = $dateStr ? " on {$dateStr}" : '';

        return match ($type) {
            'lead_created' => "{$hl($creatorName)} created a lead for {$hl($patient, 'highlight-orange')}{$loc}{$date}",
            'lead_booked', 'consultation_booked', 'Consultancy' => "{$hl($creatorName)} booked a {$hl($service ?: 'Service', 'highlight-orange')} Consultation for {$hl($patient, 'highlight-orange')}{$loc}{$date}",
            'lead_arrived' => "{$hl($creatorName)} marked {$hl($patient, 'highlight-orange')} as arrived" . ($service ? " for {$hl($service, 'highlight-orange')}" : '') . "{$loc}{$date}",
            'treatment_booked' => "{$hl($creatorName)} booked a {$hl($service ?: 'Service', 'highlight-orange')} Treatment for {$hl($patient, 'highlight-orange')}{$loc}{$date}",
            'package_created' => "{$hl($creatorName)} created Package {$hl("Plan Id: {$planId}", 'highlight-orange')}" . ($amount ? " for Rs. {$amount}" : '') . " for {$hl($patient, 'highlight-orange')}{$loc}{$date}",
            'payment_received' => "{$hl($creatorName)} received payment Rs. {$amount} from {$hl($patient, 'highlight-orange')}" . ($planId ? " for {$hl("Plan Id: {$planId}", 'highlight-orange')}" : '') . "{$loc}{$date}",
            'refund_made' => "{$hl($creatorName)} made refund Rs. {$amount} to {$hl($patient, 'highlight-orange')}" . ($planId ? " for {$hl("Plan Id: {$planId}", 'highlight-orange')}" : '') . "{$loc}{$date}",
            'invoice_created' => "{$hl($creatorName)} created invoice Rs. {$amount} for {$hl($appointmentType ?: $service ?: 'Consultation', 'highlight-orange')}{$loc}{$date}",
            default => $this->buildDefaultActivityDescription($activity->action ?? 'Activity', $creatorName, $patient, $service, $appointmentType, $location, $amount, $dateStr),
        };
    }

    private function buildDefaultActivityDescription(string $action, string $creator, string $patient, string $service, string $appointmentType, string $location, string $amount, string $dateStr): string
    {
        $hl = fn(string $text, string $class = 'highlight') => "<span class=\"{$class}\">{$text}</span>";
        $loc = $location ? " in {$hl($location)}" : '';
        $date = $dateStr ? " on {$dateStr}" : '';

        return match ($action) {
            'booked' => "{$hl($creator)} booked a {$hl($service ?: $appointmentType ?: 'Service', 'highlight-orange')} Consultation for {$hl($patient, 'highlight-orange')}{$loc}{$date}",
            'received' => "{$hl($creator)} received Rs. {$amount} for {$hl($appointmentType ?: 'Consultation', 'highlight-orange')}{$loc}{$date}",
            default => "{$hl($creator)} " . strtolower($action) . ($patient ? " for {$hl($patient, 'highlight-orange')}" : '') . ($service ? " - {$service}" : '') . "{$loc}{$date}",
        };
    }

    private function buildLegacyActivityHistory(int $id, Patients $patient): array
    {
        $activities = [];

        $typePriority = [
            'lead_created' => 1, 'consultation_booked' => 2, 'treatment_booked' => 3,
            'consultation_arrived' => 4, 'treatment_arrived' => 5, 'package_created' => 6,
            'service_added' => 7, 'payment_made' => 8, 'refund_made' => 9, 'invoice_created' => 10,
        ];

        // 1. Leads
        $leads = \DB::table('leads')->where('patient_id', $id)->select('id', 'created_at', 'location_id')->get();
        foreach ($leads as $lead) {
            $location = \DB::table('locations')
                ->leftJoin('cities', 'locations.city_id', '=', 'cities.id')
                ->where('locations.id', $lead->location_id)
                ->select('locations.name as location_name', 'cities.name as city_name')
                ->first();
            $locationStr = $location ? "{$location->city_name}-{$location->location_name}" : '';

            $activities[] = [
                'type' => 'lead_created',
                'description' => '<span class="highlight-purple">Lead Created</span> for <span class="highlight">' . $patient->name . '</span>' . ($locationStr ? ' at <span class="location">' . $locationStr . '</span>' : ''),
                'created_at' => $lead->created_at,
            ];
        }

        // 2. Appointments
        $appointments = \DB::table('appointments')
            ->leftJoin('locations', 'appointments.location_id', '=', 'locations.id')
            ->leftJoin('cities', 'locations.city_id', '=', 'cities.id')
            ->leftJoin('appointment_statuses', 'appointments.appointment_status_id', '=', 'appointment_statuses.id')
            ->leftJoin('services', 'appointments.service_id', '=', 'services.id')
            ->where('appointments.patient_id', $id)
            ->select('appointments.id', 'appointments.created_at', 'appointments.appointment_status_id', 'appointments.appointment_type_id', 'services.name as service_name', 'locations.name as location_name', 'cities.name as city_name', 'appointment_statuses.name as status_name')
            ->get();

        foreach ($appointments as $appt) {
            $locationStr = ($appt->city_name ?? '') . '-' . ($appt->location_name ?? '');
            $serviceName = $appt->service_name ?? 'Service';
            $isConsultation = $appt->appointment_type_id == 1;
            $typeLabel = $isConsultation ? 'Consultation' : 'Treatment';
            $typeKey = $isConsultation ? 'consultation' : 'treatment';

            $activities[] = [
                'type' => "{$typeKey}_booked",
                'description' => "<span class=\"highlight\">{$typeLabel} Booked</span> for <span class=\"highlight-orange\">{$serviceName}</span> at <span class=\"location\">{$locationStr}</span>",
                'created_at' => $appt->created_at,
            ];

            if (in_array($appt->appointment_status_id, [3, 4, 5])) {
                $activities[] = [
                    'type' => "{$typeKey}_arrived",
                    'description' => "<span class=\"highlight-green\">{$typeLabel} Arrived</span> - " . ($appt->status_name ?? 'Completed') . " for <span class=\"highlight-orange\">{$serviceName}</span> at <span class=\"location\">{$locationStr}</span>",
                    'created_at' => $appt->created_at,
                ];
            }
        }

        // 3. Packages
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

        // 4. Package Services
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

        // 5. Payments
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

        // 6. Refunds
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

        // Sort: newest first, then by priority for same timestamp
        usort($activities, function ($a, $b) use ($typePriority) {
            $timeDiff = strtotime($b['created_at']) - strtotime($a['created_at']);
            if ($timeDiff !== 0) {
                return $timeDiff;
            }
            return ($typePriority[$b['type']] ?? 99) - ($typePriority[$a['type']] ?? 99);
        });

        return $activities;
    }
}
