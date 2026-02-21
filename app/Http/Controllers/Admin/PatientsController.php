<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\ACL;
use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Helpers\NodesTree;
use App\Http\Controllers\Controller;
use App\Models\Cities;
use App\Models\Documents;
use App\Models\Leads;
use App\Models\LeadStatuses;
use App\Models\Patients;
use App\Models\User;
use Auth;
use Carbon\Carbon;
use Config;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Validator;

class PatientsController extends Controller
{
    public $success;

    public $error;

    public $unauthorized;

    public function __construct()
    {
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    /**
     * Display a listing of Lead_source.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!Gate::allows('patients_manage')) {
            return abort(401);
        }

        return view('admin.patients.index');
    }

    // NOTE: datatable() method removed - now handled by Api\PatientController::index()
    // NOTE: create() and store() methods moved to Api\PatientController
    // NOTE: edit(), update(), destroy(), status() methods moved to Api\PatientController

    /**
     * Patient Profile Preview
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function preview($id)
    {
        if (!Gate::allows('patients_manage')) {
            return abort(401);
        }

        return view('admin.patients.card.preview');
    }

    /**
     * Patient Card V2 - Section-based navigation
     * Each section is a separate page load to avoid JS conflicts
     *
     * @param  int  $id
     * @param  string|null  $section
     * @return \Illuminate\Http\Response
     */
    public function cardV2($id, $section = 'profile')
    {
        if (!Gate::allows('patients_manage')) {
            return abort(401);
        }

        // Valid sections with their required permissions (matching main modules exactly)
        // consultancy route: middleware('permission:appointments_manage')
        // treatment route: middleware('permission:treatments_manage')
        // packages route: middleware('permission:plans_manage')
        // invoices route: middleware('permission:invoices_manage')
        // refunds route: middleware('permission:refunds_manage')
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

        // Validate section
        if (!array_key_exists($section, $sectionPermissions)) {
            $section = 'profile';
        }

        // Check section-specific permission
        if (!Gate::allows($sectionPermissions[$section])) {
            return abort(401, 'Unauthorized to access this section');
        }

        // Get patient data
        $patient = \App\Models\Patients::find($id);
        if (!$patient) {
            return abort(404, 'Patient not found');
        }

        // Get patient membership (active or expired)
        $membership = \App\Models\Membership::where('patient_id', $id)
            ->orderBy('active', 'desc')
            ->orderBy('created_at', 'desc')
            ->first();

        // Get permissions for UI elements
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

        return view('admin.patients.card-v2.index', [
            'patient' => $patient,
            'patientId' => $id,
            'section' => $section,
            'permissions' => $permissions,
            'membership' => $membership,
        ]);
    }

    /**
     * Get last appointment location for patient
     * Used to redirect to calendar with correct branch filter
     *
     * @param  int  $id
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLastAppointmentLocation($id, Request $request)
    {
        try {
            $appointmentType = $request->input('appointment_type'); // 'consultancy' or 'treatment'
            
            // Map appointment type to appointment_type_id
            $appointmentTypeId = null;
            if ($appointmentType === 'consultancy') {
                $appointmentTypeId = Config::get('constants.consultancy_id');
            } elseif ($appointmentType === 'treatment') {
                $appointmentTypeId = Config::get('constants.treatment_id');
            }
            
            // Get last appointment for this patient and type
            $lastAppointment = \App\Models\Appointments::where('patient_id', $id)
                ->where('account_id', Auth::user()->account_id)
                ->when($appointmentTypeId, function ($query) use ($appointmentTypeId) {
                    return $query->where('appointment_type_id', $appointmentTypeId);
                })
                ->orderBy('created_at', 'DESC')
                ->first();
            
            if ($lastAppointment && $lastAppointment->location_id) {
                return response()->json([
                    'status' => true,
                    'data' => [
                        'location_id' => $lastAppointment->location_id,
                        'location_name' => $lastAppointment->location->name ?? null
                    ]
                ]);
            }
            
            // No appointment found
            return response()->json([
                'status' => false,
                'message' => 'No previous appointment found'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    // NOTE: getPatient() method moved to Api\PatientController (show/getPatient)

    /**
     * Patient Leads
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function leads($id)
    {
        if (!Gate::allows('patients_manage') && !Gate::allows('leads_manage') && !Gate::allows('leads_view')) {
            return abort(401);
        }

        $patient = Patients::getData($id);
        if ($patient) {
            $cities = Cities::getActiveSorted(ACL::getUserCities());
            $cities->prepend('All', '');

            $users = User::getUsers();
            $users->prepend('All', '');

            $lead_statuses = LeadStatuses::getLeadStatuses();
            $lead_statuses->prepend('All', '');

            $parentGroups = new NodesTree();
            $parentGroups->current_id = -1;
            $parentGroups->build(0, Auth::User()->account_id);
            $parentGroups->toList($parentGroups, -1);

            $Services = $parentGroups->nodeList;

            $leadServices = null;

            return view('admin.patients.card.leads.index', compact('patient', 'Services', 'cities', 'users', 'lead_statuses', 'leadServices'));
        } else {
            return view('error_full');
        }
    }

    /**
     * Display a listing of Lead_statuse.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function leadsDatatable($id, Request $request)
    {
        if (!Gate::allows('patients_manage') && !Gate::allows('leads_manage') && !Gate::allows('leads_view')) {
            return abort(401);
        }

        $where = [];

        $orderBy = 'created_at';
        $order = 'desc';

        if ($request->get('order')[0]['dir']) {
            $orderColumn = $request->get('order')[0]['column'];
            $orderBy = $request->get('columns')[$orderColumn]['data'];
            if ($orderBy == 'created_at') {
                $orderBy = 'leads.created_at';
            }
            $order = $request->get('order')[0]['dir'];
        }

        $where[] = [
            'leads.patient_id',
            '=',
            $id,
        ];

        if ($request->get('name') && $request->get('name') != '') {
            $where[] = [
                'users.name',
                'like',
                '%' . $request->get('name') . '%',
            ];
        }
        if ($request->get('phone') && $request->get('phone') != '') {
            $where[] = [
                'users.phone',
                'like',
                '%' . GeneralFunctions::cleanNumber($request->get('phone')) . '%',
            ];
        }
        if ($request->get('city_id') && $request->get('city_id') != '') {
            $where[] = [
                'city_id',
                '=',
                $request->get('city_id'),
            ];
        }
        if ($request->get('lead_status_id') && $request->get('lead_status_id')) {
            $where[] = [
                'lead_status_id',
                '=',
                $request->get('lead_status_id'),
            ];
        }
        if ($request->get('service_id') && $request->get('service_id')) {
            $where[] = [
                'service_id',
                '=',
                $request->get('service_id'),
            ];
        }
        if ($request->get('created_by') && $request->get('created_by') != '') {
            $where[] = [
                'leads.created_by',
                '=',
                $request->get('created_by'),
            ];
        }
        if ($request->get('date_from') && $request->get('date_from') != '') {
            $where[] = [
                'leads.created_at',
                '>=',
                $request->get('date_from') . ' 00:00:00',
            ];
        }
        if ($request->get('date_to') && $request->get('date_to') != '') {
            $where[] = [
                'leads.created_at',
                '<=',
                $request->get('date_to') . ' 23:59:59',
            ];
        }

        // Process Lead Status
        $DefaultJunkLeadStatus = LeadStatuses::where([
            'account_id' => Auth::User()->account_id,
            'is_junk' => 1,
        ])->first();
        if ($DefaultJunkLeadStatus) {
            $default_junk_lead_status_id = $DefaultJunkLeadStatus->id;
        } else {
            $default_junk_lead_status_id = Config::get('constants.lead_status_junk');
        }

        $countQuery = Leads::join('users', 'users.id', '=', 'leads.patient_id')
            ->where('users.user_type_id', '=', Config::get('constants.patient_id'))
            ->where(function ($query) {
                $query->whereIn('leads.city_id', ACL::getUserCities());
                $query->orWhereNull('leads.city_id');
            })
            ->whereNotIn('leads.lead_status_id', [$default_junk_lead_status_id]);

        if (count($where)) {
            $countQuery->where($where);
        }
        $iTotalRecords = $countQuery->count();

        $iDisplayLength = intval($request->get('length'));
        $iDisplayLength = $iDisplayLength < 0 ? $iTotalRecords : $iDisplayLength;
        $iDisplayStart = intval($request->get('start'));
        $sEcho = intval($request->get('draw'));

        $records = [];
        $records['data'] = [];

        $end = $iDisplayStart + $iDisplayLength;
        $end = $end > $iTotalRecords ? $iTotalRecords : $end;

        // Process Lead Status
        $DefaultJunkLeadStatus = LeadStatuses::where([
            'account_id' => Auth::User()->account_id,
            'is_junk' => 1,
        ])->first();
        if ($DefaultJunkLeadStatus) {
            $default_junk_lead_status_id = $DefaultJunkLeadStatus->id;
        } else {
            $default_junk_lead_status_id = Config::get('constants.lead_status_junk');
        }

        $resultQuery = Leads::join('users', 'users.id', '=', 'leads.patient_id')
            ->where('users.user_type_id', '=', Config::get('constants.patient_id'))
            ->where(function ($query) {
                $query->whereIn('leads.city_id', ACL::getUserCities());
                $query->orWhereNull('leads.city_id');
            })
            ->whereNotIn('leads.lead_status_id', [$default_junk_lead_status_id]);

        if (count($where)) {
            $resultQuery->where($where);
        }
        $Leads = $resultQuery->select('*', 'leads.created_by as lead_created_by', 'leads.id as lead_id', 'leads.created_at as lead_created_at', 'users.id as PatientId')
            ->limit($iDisplayLength)
            ->offset($iDisplayStart)
            ->orderBy($orderBy, $order)
            ->get();

        $Users = User::getAllRecords(Auth::User()->account_id)->getDictionary();
        $lead_status = LeadStatuses::getAllRecordsDictionary(Auth::User()->account_id);

        if ($Leads) {
            $index = 0;
            foreach ($Leads as $lead) {
                //check lead s lead status has parrent or not if yes than get parrent data and if no than get simple that row data
                if (array_key_exists($lead->lead_status_id, $lead_status)) {
                    if ($lead_status[$lead->lead_status_id]->parent_id == 0) {
                        $lead_status_data = $lead_status[$lead->lead_status_id];
                    } else {
                        $lead_status_data = $lead_status[$lead_status[$lead->lead_status_id]->parent_id];
                    }
                }
                $records['data'][$index] = [
                    'PatientId' => $lead->PatientId,
                    'name' => $lead->name,
                    'phone' => Gate::allows('contact') ? GeneralFunctions::prepareNumber4Call($lead->patient->phone) : '***********',
                    'city_id' => ($lead->city_id) ? $lead->city->name : '',
                    'lead_status_id' => ($lead->lead_status_id) ? $lead->lead_status->name : '',
                    'service_id' => ($lead->service_id) ? $lead->service->name : '',
                    'created_at' => Carbon::parse($lead->lead_created_at)->format('F j,Y h:i A'),
                    'created_by' => array_key_exists($lead->lead_created_by, $Users) ? $Users[$lead->lead_created_by]->name : 'N/A',
                ];
                $index++;
            }
        }

        if ($request->get('customActionType') && $request->get('customActionType') == 'group_action') {
            $Leads = Leads::whereIn('id', $request->get('id'));
            if ($Leads) {
                $Leads->delete();
            }
            $records['customActionStatus'] = 'OK'; // pass custom message(useful for getting status of group actions)
            $records['customActionMessage'] = 'Records has been deleted successfully!'; // pass custom message(useful for getting status of group actions)
        }

        $records['draw'] = $sEcho;
        $records['recordsTotal'] = $iTotalRecords;
        $records['recordsFiltered'] = $iTotalRecords;

        return response()->json($records);
    }

    /**
     * Patient Leads
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function appointments($id)
    {
        if (!Gate::allows('patients_appointment_manage')) {
            return abort(401);
        }

        return view('admin.patients.card.appointments.index');
    }

    // NOTE: appointmentsDatatable() method moved to Api\PatientController
    // NOTE: voucherDatatable() method removed - now handled by Api\PatientController::vouchersDatatable()

    /**
     * Display a form to upload the image.
     *
     * @param id
     * @return view
     */
    public function imageindex($id)
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

    // NOTE: imagestore() method moved to Api\PatientController (storeImage)

    /**
     * Display a list of document.
     *
     * @param id
     * @return view
     */
    public function documentindex($id)
    {
        if (!Gate::allows('patients_document_manage')) {
            return abort(401);
        }
        $patient = Patients::where([['account_id', '=', Auth::User()->account_id], ['id', '=', $id]])->first();

        $filters = Filters::all(Auth::User()->id, 'patient_documents');

        if ($patient) {
            return view('admin.patients.card.documents.add_documents', compact('patient', 'filters'));
        } else {
            return view('error_full');
        }
    }

    public function documentCreate($id)
    {

        if (!Gate::allows('patients_document_create')) {
            return abort(401);
        }
        $patient = Patients::getData($id);

        return view('admin.patients.card.documents.create', compact('patient'));
    }

    /**
     * store document to upload document.
     *
     * @param id
     * @return \Illuminate\Http\JsonResponse
     */
    public function documentstore(Request $request)
    {
        if (!Gate::allows('patients_document_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        
        // Check if file exists first before validation
        if (!$request->hasFile('file')) {
            return ApiHelper::apiResponse($this->success, 'No file was uploaded. Please select a file.', false);
        }
        
        $validator = $this->verifyDocumentFields($request);
        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }
        $patient = Patients::getData($request->patient_id);

        if (!$patient) {
            return ApiHelper::apiResponse($this->success, 'Patient not found', false);
        }

        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return ApiHelper::apiResponse($this->success, 'File upload failed. Please try again.', false);
        }
        
        $ext = strtolower($file->getClientOriginalExtension());
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'pdf', 'docx', 'xlsx'])) {
            $originalName = $file->getClientOriginalName();
            if (empty($originalName)) {
                $originalName = 'document.' . $ext;
            }
            $fileName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
            
            try {
                $file->storeAs('public/patient_image', $fileName);
            } catch (\Exception $e) {
                return ApiHelper::apiResponse($this->success, 'Failed to save file: ' . $e->getMessage(), false);
            }

            $path = 'patient_image/' . $fileName;
            Documents::CreateRecord($request, $path, $patient->id);

            return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
        }

        return ApiHelper::apiResponse($this->success, 'File format not supported. Allowed: jpg, jpeg, png, pdf, docx, xlsx', false);
    }

    /**
     * Validate form fields
     *
     * @return Validator $validator;
     */
    protected function verifyDocumentFields(Request $request)
    {
        return $validator = Validator::make($request->all(), [
            'name' => 'required',
            'file' => 'required',
        ]);
    }

    /*
     * Display the document in datatable
     * @param id and request
     * @return mixed
     */
    public function documentdatatable($id, Request $request)
    {

        $filename = 'patient_documents';

        $filters = getFilters($request->all());

        $apply_filter = checkFilters($filters, $filename);

        
        $records = [];
        $records['data'] = [];
        // Get Total Records
        $iTotalRecords = Documents::getTotalRecords($request, Auth::User()->account_id, $id, $apply_filter, $filename);

        [$orderBy, $order] = getSortBy($request);

        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

        $documents = Documents::getRecords($id, $request, $iDisplayStart, $iDisplayLength, Auth::user()->account_id, $apply_filter, $filename);

        $records = $this->getFilters($records, $filename);

        if ($documents->count()) {
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

    private function getFilters($records, $filename)
    {

        $records['active_filters'] = Filters::all(Auth::user()->id, $filename);

        $records['filter_values'] = [
            'form_types' => '',
            'status' => config('constants.status'),
        ];

        return $records;
    }

    /*
     * Display the form for edit
     *
     *@param $id
     *
     * @return view
     */
    public function documentedit($id)
    {

        if (!Gate::allows('patients_document_edit')) {
            return abort(401);
        }
        $documents = Documents::find($id);

        return view('admin.patients.card.documents.edit', compact('documents'));
    }

    /*
     *update the docucment
     *
     *@parm Request and id
     *
     *@return view
     * */
    public function documentupdate(Request $request, $id)
    {

        if (!Gate::allows('patients_document_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $validator = $this->verifyupdatedcoumentFields($request);
        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }
        if ($document = Documents::updateRecord($id, $request, Auth::user()->account_id)) {

            return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
        }

        return ApiHelper::apiResponse($this->success, 'Something went wrong.', false);
    }

    /**
     * Validate form fields
     *
     * @return Validator $validator;
     */
    protected function verifyupdatedcoumentFields(Request $request)
    {
        return $validator = Validator::make($request->all(), [
            'name' => 'required',
        ]);
    }

    /*
     * Delete the document
     *
     *@param $id
     *
     * return view
     */
    public function documentdelete($id)
    {

        if (!Gate::allows('patients_document_destroy')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        Documents::DeleteRecord($id);

        return ApiHelper::apiResponse($this->success, 'Record has been deleted successfully.');
    }
    // NOTE: assignMembership(), assignVoucher(), and addReferral() methods moved to Api\PatientController
}
