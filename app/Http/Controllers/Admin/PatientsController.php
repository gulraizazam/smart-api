<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Helpers\ACL;
use App\Helpers\ActivityLogger;
use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Helpers\NodesTree;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePatientDocumentRequest;
use App\Http\Requests\PatientDocumentStoreRequest;
use App\Models\Appointments;
use App\Models\Cities;
use App\Models\Documents;
use App\Models\Leads;
use App\Models\LeadStatuses;
use App\Models\Membership;
use App\Models\Patients;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class PatientsController extends Controller
{
    public function index(): View
    {
        if (! Gate::allows('patients.list.view')) {
            return abort(401);
        }

        return view('admin.patients.index');
    }

    public function preview(int $id): View
    {
        if (! Gate::allows('patients.card.view')) {
            return abort(401);
        }

        return view('admin.patients.card.preview');
    }

    /**
     * Patient Card V2 - Section-based navigation.
     * Each section is a separate page load to avoid JS conflicts.
     */
    public function cardV2(int $id, string $section = 'profile'): View
    {
        if (! Gate::allows('patients.card.view')) {
            return abort(401);
        }

        // Per-section gates — each tab in the patient card is its own
        // patients-module perm. The owning module's broader manage perm
        // (consultations_manage etc.) is no longer checked here; it gates
        // the standalone modules. This lets ops revoke a tab inside the
        // card without affecting the user's access to the global module.
        $sectionPermissions = [
            'profile' => 'patients.profile.view',
            'consultations' => 'patients.consultations.view',
            'treatments' => 'patients.treatments.view',
            'plans' => 'patients.plans.view',
            'invoices' => 'patients.invoices.view',
            'refunds' => 'patients.refunds.view',
            'documents' => 'patients.documents.view',
            'activity' => 'patients.activity.view',
        ];

        if (! array_key_exists($section, $sectionPermissions)) {
            $section = 'profile';
        }

        if (! Gate::allows($sectionPermissions[$section])) {
            return abort(401, 'Unauthorized to access this section');
        }

        // Round 4 IDOR sweep — getData() filters by Auth::user()->account_id,
        // so a guessed cross-tenant ID returns null and aborts 404 instead of
        // exposing another tenant's patient row in the view.
        $patient = Patients::getData((int) $id);
        if (! $patient) {
            return abort(404, 'Patient not found');
        }

        // HIPAA audit: log the read. Throttled 1/user/patient/day inside
        // logPatientAccessed so re-visits in the same consultation don't
        // spam the feed. Silent failure is acceptable — never block the
        // page render on an audit write.
        try {
            ActivityLogger::logPatientAccessed($patient);
        } catch (\Throwable $e) {
            Log::warning('patient_accessed audit write failed', [
                'event' => 'activities.patient_accessed.write_failed',
                'patient_id' => $id,
                'error' => $e->getMessage(),
            ]);
        }

        $membership = Membership::where('patient_id', $id)
            ->orderByDesc('active')
            ->orderByDesc('created_at')
            ->first();

        $permissions = [
            'edit' => Gate::allows('patients.edit'),
            'delete' => Gate::allows('patients.delete'),
            'status' => Gate::allows('appointments_status'),
            'consultancy' => Gate::allows('appointments_manage'),
            'treatment' => Gate::allows('treatments_manage'),
            'invoice' => Gate::allows('consultancy_invoice'),
            'invoice_display' => Gate::allows('consultancy_invoice_display'),
            'log' => Gate::allows('appointments_log'),
            'plans_create' => Gate::allows('plans.create'),
            'plans_manage' => Gate::allows('plans.list.view'),
            'contact' => Gate::allows('patients.list.view_contact'),
        ];

        return view('admin.patients.card-v2.index', compact('patient', 'section', 'permissions', 'membership') + ['patientId' => $id]);
    }

    public function getLastAppointmentLocation(int $id, Request $request): JsonResponse
    {
        try {
            $appointmentTypeId = match ($request->input('appointment_type')) {
                'consultancy' => Config::get('constants.consultancy_id'),
                'treatment' => Config::get('constants.treatment_id'),
                default => null,
            };

            $lastAppointment = Appointments::where('patient_id', $id)
                ->where('account_id', Auth::user()->account_id)
                ->when($appointmentTypeId, fn ($q) => $q->where('appointment_type_id', $appointmentTypeId))
                ->orderByDesc('created_at')
                ->first();

            if ($lastAppointment?->location_id) {
                return response()->json([
                    'status' => true,
                    'data' => [
                        'location_id' => $lastAppointment->location_id,
                        'location_name' => $lastAppointment->location?->name,
                    ],
                ]);
            }

            return response()->json(['status' => false, 'message' => 'No previous appointment found']);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);

            return response()->json(['status' => false, 'message' => 'An error occurred. Please try again.'], 500);
        }
    }

    public function leads(int $id): View
    {
        if (! Gate::allows('patients.card.view') && ! Gate::allows('leads.list.view')) {
            return abort(401);
        }

        $patient = Patients::getData($id);
        if (! $patient) {
            return view('error_full');
        }

        $cities = Cities::getActiveSorted(ACL::getUserCities());
        $cities->prepend('All', '');

        $users = User::getUsers();
        $users->prepend('All', '');

        $lead_statuses = LeadStatuses::getLeadStatuses();
        $lead_statuses->prepend('All', '');

        $parentGroups = new NodesTree;
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::user()->account_id);
        $parentGroups->toList($parentGroups, -1);

        $Services = $parentGroups->nodeList;
        $leadServices = null;

        return view('admin.patients.card.leads.index', compact('patient', 'Services', 'cities', 'users', 'lead_statuses', 'leadServices'));
    }

    public function leadsDatatable(int $id, Request $request): JsonResponse
    {
        if (! Gate::allows('patients.card.view') && ! Gate::allows('leads.list.view')) {
            return abort(401);
        }

        $where = [['leads.patient_id', '=', $id]];

        // Sorting
        $orderBy = 'leads.created_at';
        $order = 'desc';
        if ($request->get('order')[0]['dir'] ?? false) {
            $orderColumn = $request->get('order')[0]['column'];
            $orderBy = $request->get('columns')[$orderColumn]['data'];
            if ($orderBy === 'created_at') {
                $orderBy = 'leads.created_at';
            }
            $order = $request->get('order')[0]['dir'];
        }

        // Apply filters
        $filterMap = [
            'name' => ['users.name', 'like'],
            'phone' => ['users.phone', 'like_phone'],
            'city_id' => ['city_id', '='],
            'lead_status_id' => ['lead_status_id', '='],
            'service_id' => ['service_id', '='],
            'created_by' => ['leads.created_by', '='],
        ];

        foreach ($filterMap as $param => [$column, $operator]) {
            $value = $request->get($param);
            if ($value && $value !== '') {
                if ($operator === 'like') {
                    $where[] = [$column, 'like', "%{$value}%"];
                } elseif ($operator === 'like_phone') {
                    $where[] = [$column, 'like', '%'.GeneralFunctions::cleanNumber($value).'%'];
                } else {
                    $where[] = [$column, '=', $value];
                }
            }
        }

        if ($request->get('date_from') && $request->get('date_from') !== '') {
            $where[] = ['leads.created_at', '>=', $request->get('date_from').' 00:00:00'];
        }
        if ($request->get('date_to') && $request->get('date_to') !== '') {
            $where[] = ['leads.created_at', '<=', $request->get('date_to').' 23:59:59'];
        }

        // Get junk lead status
        $defaultJunkStatus = LeadStatuses::where([
            'account_id' => Auth::user()->account_id,
            'is_junk' => 1,
        ])->first();
        $junkStatusId = $defaultJunkStatus?->id ?? Config::get('constants.lead_status_junk');

        // Base query builder
        $baseQuery = fn () => Leads::join('users', 'users.id', '=', 'leads.patient_id')
            ->where('users.user_type_id', Config::get('constants.patient_id'))
            ->where(fn ($q) => $q->whereIn('leads.city_id', ACL::getUserCities())->orWhereNull('leads.city_id'))
            ->whereNotIn('leads.lead_status_id', [$junkStatusId])
            ->where($where);

        $iTotalRecords = $baseQuery()->count();

        $iDisplayLength = (int) $request->get('length');
        $iDisplayLength = $iDisplayLength < 0 ? $iTotalRecords : $iDisplayLength;
        $iDisplayStart = (int) $request->get('start');

        $Leads = $baseQuery()
            ->select('*', 'leads.created_by as lead_created_by', 'leads.id as lead_id', 'leads.created_at as lead_created_at', 'users.id as PatientId')
            ->limit($iDisplayLength)
            ->offset($iDisplayStart)
            ->orderBy($orderBy, $order)
            ->get();

        $Users = User::getAllRecords(Auth::user()->account_id)->getDictionary();
        $leadStatusDict = LeadStatuses::getAllRecordsDictionary(Auth::user()->account_id);

        $records = ['data' => []];

        foreach ($Leads as $index => $lead) {
            $leadStatusData = null;
            if (array_key_exists($lead->lead_status_id, $leadStatusDict)) {
                $status = $leadStatusDict[$lead->lead_status_id];
                $leadStatusData = $status->parent_id == 0 ? $status : ($leadStatusDict[$status->parent_id] ?? $status);
            }

            $records['data'][$index] = [
                'PatientId' => $lead->PatientId,
                'name' => $lead->name,
                'phone' => Gate::allows('patients.list.view_contact') ? GeneralFunctions::prepareNumber4Call($lead->patient->phone) : '***********',
                'city_id' => $lead->city?->name ?? '',
                'lead_status_id' => $lead->lead_status?->name ?? '',
                'service_id' => $lead->service?->name ?? '',
                'created_at' => Carbon::parse($lead->lead_created_at)->format('F j,Y h:i A'),
                'created_by' => array_key_exists($lead->lead_created_by, $Users) ? $Users[$lead->lead_created_by]->name : 'N/A',
            ];
        }

        // Handle group delete action
        if ($request->get('customActionType') === 'group_action') {
            Leads::whereIn('id', $request->get('id', []))->delete();
            $records['customActionStatus'] = 'OK';
            $records['customActionMessage'] = 'Records has been deleted successfully!';
        }

        $records['draw'] = (int) $request->get('draw');
        $records['recordsTotal'] = $iTotalRecords;
        $records['recordsFiltered'] = $iTotalRecords;

        return response()->json($records);
    }

    public function appointments(int $id): View
    {
        if (! Gate::allows('patients_appointment_manage')) {
            return abort(401);
        }

        return view('admin.patients.card.appointments.index');
    }

    public function imageindex(int $id): View
    {
        if (! Gate::allows('users_manage')) {
            return abort(401);
        }

        $patient = Patients::getData($id);
        if (! $patient) {
            return abort(401);
        }

        return view('admin.patients.card.image.add_image', compact('patient'));
    }

    public function documentindex(int $id): View
    {
        if (! Gate::allows('patients.documents.view')) {
            return abort(401);
        }

        $patient = Patients::where([['account_id', '=', Auth::user()->account_id], ['id', '=', $id]])->first();

        if (! $patient) {
            return view('error_full');
        }

        $filters = Filters::all(Auth::user()->id, 'patient_documents');

        return view('admin.patients.card.documents.add_documents', compact('patient', 'filters'));
    }

    public function documentCreate(int $id): View
    {
        if (! Gate::allows('patients.documents.upload')) {
            return abort(401);
        }

        $patient = Patients::getData($id);

        return view('admin.patients.card.documents.create', compact('patient'));
    }

    public function documentstore(PatientDocumentStoreRequest $request): JsonResponse
    {
        if (! Gate::allows('patients.documents.upload')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $patient = Patients::getData($request->patient_id);
        if (! $patient) {
            return $this->errorResponse('Patient not found', 200);
        }

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName() ?: 'document.'.$file->getClientOriginalExtension();
        $fileName = time().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);

        try {
            // Round 4 C3 — write to storage/app/patient_image (the local disk),
            // NOT storage/app/public/patient_image which is exposed via the
            // public/storage symlink. Files are now served only through the
            // authenticated admin.files.patient_image route.
            $file->storeAs('patient_image', $fileName);
        } catch (\Exception $e) {
            Log::error('Failed to save file: '.$e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);

            return $this->errorResponse('Failed to save file. Please try again.', 200);
        }

        Documents::CreateRecord($request, 'patient_image/'.$fileName, $patient->id);

        return $this->successResponse('Record has been created successfully.', null, 200);
    }

    public function documentdatatable(int $id, Request $request): JsonResponse
    {
        $filename = 'patient_documents';
        $filters = getFilters($request->all());
        $apply_filter = checkFilters($filters, $filename);

        $records = ['data' => []];

        $iTotalRecords = Documents::getTotalRecords($request, Auth::user()->account_id, $id, $apply_filter, $filename);
        [$orderBy, $order] = getSortBy($request);
        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $documents = Documents::getRecords($id, $request, $iDisplayStart, $iDisplayLength, Auth::user()->account_id, $apply_filter, $filename);

        $records['active_filters'] = Filters::all(Auth::user()->id, $filename);
        $records['filter_values'] = [
            'form_types' => '',
            'status' => config('constants.status'),
        ];

        if ($documents->isNotEmpty()) {
            $records['data'] = $documents;
            $records['meta'] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ];
        }

        $records['permissions'] = [
            'edit' => Gate::allows('patients.documents.edit'),
            'delete' => Gate::allows('patients.documents.delete'),
            'manage' => Gate::allows('patients.documents.view'),
        ];

        return response()->json($records);
    }

    public function documentedit(int $id): View
    {
        if (! Gate::allows('patients.documents.edit')) {
            return abort(401);
        }

        // Round 4 IDOR sweep — Documents has no account_id column; tenant
        // ownership runs through the documents.user_id → users.account_id
        // chain. Restrict the lookup to documents whose owning patient lives
        // in the caller's account, otherwise abort 404.
        $documents = Documents::whereHas('patient', static fn ($q) => $q->where('account_id', Auth::user()->account_id))
            ->find($id);

        if (! $documents) {
            return abort(404);
        }

        return view('admin.patients.card.documents.edit', compact('documents'));
    }

    public function documentupdate(UpdatePatientDocumentRequest $request, int $id): JsonResponse
    {
        if (! Gate::allows('patients.documents.edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        if (Documents::updateRecord($id, $request, Auth::user()->account_id)) {
            return $this->successResponse('Record has been updated successfully.', null, 200);
        }

        return $this->errorResponse('Something went wrong.', 200);
    }

    public function documentdelete(int $id): JsonResponse
    {
        if (! Gate::allows('patients.documents.delete')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        Documents::DeleteRecord($id);

        return $this->successResponse('Record has been deleted successfully.', null, 200);
    }
}
