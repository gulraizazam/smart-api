<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Helpers\ACL;
use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Helpers\NodesTree;
use App\Http\Controllers\Controller;
use App\Http\Requests\PatientDocumentStoreRequest;
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

class PatientsController extends Controller
{
    public function index(): mixed
    {
        if (!Gate::allows('patients_manage')) {
            return abort(401);
        }

        return view('admin.patients.index');
    }

    public function preview(int $id): mixed
    {
        if (!Gate::allows('patients_manage')) {
            return abort(401);
        }

        return view('admin.patients.card.preview');
    }

    /**
     * Patient Card V2 - Section-based navigation.
     * Each section is a separate page load to avoid JS conflicts.
     */
    public function cardV2(int $id, string $section = 'profile'): mixed
    {
        if (!Gate::allows('patients_manage')) {
            return abort(401);
        }

        $sectionPermissions = [
            'profile' => 'patients_manage',
            'consultations' => 'appointments_manage',
            'treatments' => 'treatments_manage',
            'plans' => 'plans_manage',
            'invoices' => 'invoices_manage',
            'refunds' => 'refunds_manage',
            'documents' => 'patients_manage',
            'activity' => 'patients_manage',
        ];

        if (!array_key_exists($section, $sectionPermissions)) {
            $section = 'profile';
        }

        if (!Gate::allows($sectionPermissions[$section])) {
            return abort(401, 'Unauthorized to access this section');
        }

        $patient = Patients::find($id);
        if (!$patient) {
            return abort(404, 'Patient not found');
        }

        $membership = Membership::where('patient_id', $id)
            ->orderByDesc('active')
            ->orderByDesc('created_at')
            ->first();

        $permissions = [
            'edit' => Gate::allows('patients_edit'),
            'delete' => Gate::allows('patients_delete'),
            'status' => Gate::allows('appointments_status'),
            'consultancy' => Gate::allows('appointments_manage'),
            'treatment' => Gate::allows('treatments_manage'),
            'invoice' => Gate::allows('consultancy_invoice'),
            'invoice_display' => Gate::allows('consultancy_invoice_display'),
            'log' => Gate::allows('appointments_log'),
            'plans_create' => Gate::allows('plans_create'),
            'plans_manage' => Gate::allows('plans_manage'),
            'contact' => Gate::allows('contact'),
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

            $lastAppointment = \App\Models\Appointments::where('patient_id', $id)
                ->where('account_id', Auth::user()->account_id)
                ->when($appointmentTypeId, fn($q) => $q->where('appointment_type_id', $appointmentTypeId))
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
            return response()->json(['status' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    public function leads(int $id): mixed
    {
        if (!Gate::allows('patients_manage') && !Gate::allows('leads_manage') && !Gate::allows('leads_view')) {
            return abort(401);
        }

        $patient = Patients::getData($id);
        if (!$patient) {
            return view('error_full');
        }

        $cities = Cities::getActiveSorted(ACL::getUserCities());
        $cities->prepend('All', '');

        $users = User::getUsers();
        $users->prepend('All', '');

        $lead_statuses = LeadStatuses::getLeadStatuses();
        $lead_statuses->prepend('All', '');

        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::user()->account_id);
        $parentGroups->toList($parentGroups, -1);

        $Services = $parentGroups->nodeList;
        $leadServices = null;

        return view('admin.patients.card.leads.index', compact('patient', 'Services', 'cities', 'users', 'lead_statuses', 'leadServices'));
    }

    public function leadsDatatable(int $id, Request $request): JsonResponse
    {
        if (!Gate::allows('patients_manage') && !Gate::allows('leads_manage') && !Gate::allows('leads_view')) {
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
                    $where[] = [$column, 'like', '%' . GeneralFunctions::cleanNumber($value) . '%'];
                } else {
                    $where[] = [$column, '=', $value];
                }
            }
        }

        if ($request->get('date_from') && $request->get('date_from') !== '') {
            $where[] = ['leads.created_at', '>=', $request->get('date_from') . ' 00:00:00'];
        }
        if ($request->get('date_to') && $request->get('date_to') !== '') {
            $where[] = ['leads.created_at', '<=', $request->get('date_to') . ' 23:59:59'];
        }

        // Get junk lead status
        $defaultJunkStatus = LeadStatuses::where([
            'account_id' => Auth::user()->account_id,
            'is_junk' => 1,
        ])->first();
        $junkStatusId = $defaultJunkStatus?->id ?? Config::get('constants.lead_status_junk');

        // Base query builder
        $baseQuery = fn() => Leads::join('users', 'users.id', '=', 'leads.patient_id')
            ->where('users.user_type_id', Config::get('constants.patient_id'))
            ->where(fn($q) => $q->whereIn('leads.city_id', ACL::getUserCities())->orWhereNull('leads.city_id'))
            ->whereNotIn('leads.lead_status_id', [$junkStatusId])
            ->where($where);

        $iTotalRecords = $baseQuery()->count();

        $iDisplayLength = intval($request->get('length'));
        $iDisplayLength = $iDisplayLength < 0 ? $iTotalRecords : $iDisplayLength;
        $iDisplayStart = intval($request->get('start'));

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
                'phone' => Gate::allows('contact') ? GeneralFunctions::prepareNumber4Call($lead->patient->phone) : '***********',
                'city_id' => $lead->city_id ? $lead->city->name : '',
                'lead_status_id' => $lead->lead_status_id ? $lead->lead_status->name : '',
                'service_id' => $lead->service_id ? $lead->service->name : '',
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

        $records['draw'] = intval($request->get('draw'));
        $records['recordsTotal'] = $iTotalRecords;
        $records['recordsFiltered'] = $iTotalRecords;

        return response()->json($records);
    }

    public function appointments(int $id): mixed
    {
        if (!Gate::allows('patients_appointment_manage')) {
            return abort(401);
        }

        return view('admin.patients.card.appointments.index');
    }

    public function imageindex(int $id): mixed
    {
        if (!Gate::allows('patients_manage') && !Gate::allows('users_manage')) {
            return abort(401);
        }

        $patient = Patients::getData($id);
        if (!$patient) {
            return abort(401);
        }

        return view('admin.patients.card.image.add_image', compact('patient'));
    }

    public function documentindex(int $id): mixed
    {
        if (!Gate::allows('patients_document_manage')) {
            return abort(401);
        }

        $patient = Patients::where([['account_id', '=', Auth::user()->account_id], ['id', '=', $id]])->first();

        if (!$patient) {
            return view('error_full');
        }

        $filters = Filters::all(Auth::user()->id, 'patient_documents');

        return view('admin.patients.card.documents.add_documents', compact('patient', 'filters'));
    }

    public function documentCreate(int $id): mixed
    {
        if (!Gate::allows('patients_document_create')) {
            return abort(401);
        }

        $patient = Patients::getData($id);

        return view('admin.patients.card.documents.create', compact('patient'));
    }

    public function documentstore(PatientDocumentStoreRequest $request): JsonResponse
    {
        if (!Gate::allows('patients_document_create')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $patient = Patients::getData($request->patient_id);
        if (!$patient) {
            return $this->errorResponse('Patient not found', 200);
        }

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName() ?: 'document.' . $file->getClientOriginalExtension();
        $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);

        try {
            $file->storeAs('public/patient_image', $fileName);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to save file: ' . $e->getMessage(), 200);
        }

        Documents::CreateRecord($request, 'patient_image/' . $fileName, $patient->id);

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
            'edit' => Gate::allows('patients_document_edit'),
            'delete' => Gate::allows('patients_document_destroy'),
            'manage' => Gate::allows('patients_document_manage'),
        ];

        return response()->json($records);
    }

    public function documentedit(int $id): mixed
    {
        if (!Gate::allows('patients_document_edit')) {
            return abort(401);
        }

        $documents = Documents::find($id);

        return view('admin.patients.card.documents.edit', compact('documents'));
    }

    public function documentupdate(Request $request, int $id): JsonResponse
    {
        if (!Gate::allows('patients_document_edit')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $request->validate(['name' => 'required|string|max:255']);

        if (Documents::updateRecord($id, $request, Auth::user()->account_id)) {
            return $this->successResponse('Record has been updated successfully.', null, 200);
        }

        return $this->errorResponse('Something went wrong.', 200);
    }

    public function documentdelete(int $id): JsonResponse
    {
        if (!Gate::allows('patients_document_destroy')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        Documents::DeleteRecord($id);

        return $this->successResponse('Record has been deleted successfully.', null, 200);
    }
}
