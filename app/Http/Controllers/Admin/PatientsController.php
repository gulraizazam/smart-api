<?php

namespace App\Http\Controllers\Admin;

use App\HelperModule\ApiHelper;
use App\Helpers\ACL;
use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Helpers\NodesTree;
use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\AppointmentTypes;
use App\Models\Cities;
use App\Models\Doctors;
use App\Models\Documents;
use App\Models\Leads;
use App\Models\LeadStatuses;
use App\Models\Locations;
use App\Models\Patients;
use App\Models\Services;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Http\Controllers\Controller;
use DB, Auth, Validator, Config;
use PHPUnit\Util\Filter;

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

    /**
     * Display a listing of Lead_statuse.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function datatable(Request $request)
    {

        $filename = 'patients';
        $filters = getFilters($request->all());
        $apply_filter = checkFilters($filters, $filename);

        $records = array();
        $records["data"] = array();

        if (hasFilter($filters, 'delete')) {
        $ids = explode(',', $filters['delete']);
            $Patients = Patients::getBulkData($ids);
            $anyDeleted = false ;
            if ($Patients) {
                foreach ($Patients as $Patient) {
                    // Check if child records exists or not, If exist then disallow to delete it.
                    if (!Patients::isChildExists($Patient->id, Auth::User()->account_id)) {
                        $anyDeleted = true ;
                        $Patient->delete();
                    }
                }
            }
            if ($anyDeleted){
                $records["status"] = true; // pass custom message(useful for getting status of group actions)
                $records["message"] = "One or more record has been deleted successfully!"; // pass custom message(useful for getting status of group actions)
            } else {
                $records["status"] = false; // pass custom message(useful for getting status of group actions)
                $records["message"] = "Child records exist, unable to delete patient"; // pass custom message(useful for getting status of group actions)
            }

        }

        // Get Total Records
        $iTotalRecords = Patients::getTotalRecords($request, Auth::User()->account_id,$apply_filter, $filename);

        list($orderBy, $order) = getSortBy($request);

        list( $iDisplayLength, $iDisplayStart, $pages, $page) = getPaginationElement($request, $iTotalRecords);

        $Patients = Patients::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter, $filename);

        $records = $this->getFiltersData($records);

        if ($Patients) {
            $records["data"] = $Patients;

            $records["meta"] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ];
        }

        $records["permissions"] = [
            'edit' => Gate::allows('patients_edit'),
            'delete' => Gate::allows('patients_destroy'),
            'active' => Gate::allows('patients_active'),
            'inactive' => Gate::allows('patients_inactive'),
            'manage' => Gate::allows('patients_manage'),
        ];

        return response()->json($records);
    }

    private function getFiltersData($records) {

        $records['filter_values'] = [
            'gender' => config('constants.gender_array'),
            'status' => config('constants.status')
        ];

        $filters = Filters::all(Auth::User()->id, 'patients');

        if (isset($filters['created_from'])) {
            $filters['created_from'] = date('Y-m-d', strtotime($filters['created_from']));
        }
        if (isset($filters['created_to'])) {
            $filters['created_to'] = date('Y-m-d', strtotime($filters['created_to']));
        }

        $records['active_filters'] = $filters;

        return $records;
    }

    /**
     * Show the form for creating new Lead_source.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        if (!Gate::allows('patients_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'gender' => config('constants.gender_array')
        ]);
    }

    /**
     * Store a newly created Lead_source in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        if (!Gate::allows('patients_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }

        $data = $request->all();

        $data['phone'] = GeneralFunctions::cleanNumber($data['phone']);
        $data['created_by'] = Auth::user()->id;
        $data['updated_by'] = Auth::user()->id;
        $data['user_type_id'] = Config::get('constants.patient_id');
        $data['account_id'] = Auth::User()->account_id;

        /*
         * *********************************************
         * Logger for both create and update for patient
         * *********************************************
         */
        /*
         * Check if patient already exists or not
         */

        $logLevelPatient = Patients::where(array(
            'phone' => $data['phone'],
            'user_type_id' => Config::get('constants.patient_id'),
            'account_id' => Auth::User()->account_id
        ))->first();

        if ($logLevelPatient) {
            $patient = Patients::updateRecord($logLevelPatient->id, $data);
            Appointments::where('patient_id', '=', $logLevelPatient->id)->update(['name' => $data['name']]);

        } else {
            $patient = Patients::createRecord($data);
        }

        if ($patient) {
            return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
        }

        return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
    }

    /**
     * Validate form fields
     *
     * @param  \Illuminate\Http\Request $request
     * @return Validator $validator;
     */
    protected function verifyFields(Request $request)
    {
        return Validator::make($request->all(), [
            'email' => 'sometimes|nullable|email',
            'name' => 'required',
            'phone' => 'required',
            'gender' => 'required',
        ]);
    }


    /**
     * Show the form for editing Lead_source.
     *
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        if (!Gate::allows('patients_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $patient = Patients::getData($id);

        if (!$patient) {
            return ApiHelper::apiResponse($this->success, 'Patient not found.', false);
        }

        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'patient' => $patient,
            'gender' => config('constants.gender_array')
        ]);
    }

    /**
     * Update Lead_source in storage.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        if (!Gate::allows('patients_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }
        if($request->input('phone') == '***********'){
            $request->merge(['phone' => $request->input('old_phone')]);
          }
        $request->request->remove('old_phone');
        $data = $request->all();

        $data['phone'] = GeneralFunctions::cleanNumber($data['phone']);
        $data['created_by'] = Auth::user()->id;
        $data['updated_by'] = Auth::user()->id;
        $data['user_type_id'] = Config::get('constants.patient_id');
        $data['account_id'] = Auth::User()->account_id;

        if (Patients::updateRecord($id, $data)) {

            Appointments::where('patient_id', '=', $id)->update(['name' => $data['name']]);

            return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');

        }

        return ApiHelper::apiResponse($this->success, 'Something went wrong, please try again later.', false);
    }

    /**
     * Remove Lead_source from storage.
     *
     * @param  int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        if (!Gate::allows('patients_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        $response = Patients::DeleteRecord($id);

        return ApiHelper::apiResponse($this->success, $response['message'], $response['status']);
    }

    /**
     * Inactive Record from storage.
     *
     * @param  Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request)
    {
        if (!Gate::allows('patients_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }

        if ($request->status == 1) {
            $response = Patients::activeRecord($request->id);
        } else {
            $response = Patients::InactiveRecord($request->id);
        }

        return ApiHelper::apiResponse($this->success, $response['message'], $response['status']);
    }

    /**
     * Patient Profile Preview
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function preview($id)
    {
        if (!Gate::allows('patients_manage')) {
            return abort(401);
        }

        return view('admin.patients.card.preview');

    }

    public function getPatient($id) {

        $patient = Patients::getData($id);

        if ($patient) {
            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'patient' => $patient,
                'permissions' => [
                    'edit' => Gate::allows('patients_edit'),
                    'delete' => Gate::allows('patients_destroy'),
                    'active' => Gate::allows('patients_active'),
                    'inactive' => Gate::allows('patients_inactive'),
                    'manage' => Gate::allows('patients_manage'),
                ],
            ]);
        }

        return ApiHelper::apiResponse($this->success, 'Record found', false);
    }

    /**
     * Patient Leads
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function leads($id)
    {
        if (!Gate::allows('patients_manage') && !Gate::allows('leads_manage') && !Gate::allows('leads_view')) {
            return abort(401);
        }

        $patient = Patients::getData($id);
        if($patient){
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

        $where = array();

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

        $where[] = array(
            'leads.patient_id',
            '=',
            $id
        );

        if ($request->get('name') && $request->get('name') != '') {
            $where[] = array(
                'users.name',
                'like',
                '%' . $request->get('name') . '%'
            );
        }
        if ($request->get('phone') && $request->get('phone') != '') {
            $where[] = array(
                'users.phone',
                'like',
                '%' . GeneralFunctions::cleanNumber($request->get('phone')) . '%'
            );
        }
        if ($request->get('city_id') && $request->get('city_id') != '') {
            $where[] = array(
                'city_id',
                '=',
                $request->get('city_id')
            );
        }
        if ($request->get('lead_status_id') && $request->get('lead_status_id')) {
            $where[] = array(
                'lead_status_id',
                '=',
                $request->get('lead_status_id')
            );
        }
        if ($request->get('service_id') && $request->get('service_id')) {
            $where[] = array(
                'service_id',
                '=',
                $request->get('service_id')
            );
        }
        if ($request->get('created_by') && $request->get('created_by') != '') {
            $where[] = array(
                'leads.created_by',
                '=',
                $request->get('created_by')
            );
        }
        if ($request->get('date_from') && $request->get('date_from') != '') {
            $where[] = array(
                'leads.created_at',
                '>=',
                $request->get('date_from') . ' 00:00:00'
            );
        }
        if ($request->get('date_to') && $request->get('date_to') != '') {
            $where[] = array(
                'leads.created_at',
                '<=',
                $request->get('date_to') . ' 23:59:59'
            );
        }

        // Process Lead Status
        $DefaultJunkLeadStatus = LeadStatuses::where(array(
            'account_id' => Auth::User()->account_id,
            'is_junk' => 1,
        ))->first();
        if($DefaultJunkLeadStatus) {
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
            ->whereNotIn('leads.lead_status_id', array($default_junk_lead_status_id));


        if (count($where)) {
            $countQuery->where($where);
        }
        $iTotalRecords = $countQuery->count();


        $iDisplayLength = intval($request->get('length'));
        $iDisplayLength = $iDisplayLength < 0 ? $iTotalRecords : $iDisplayLength;
        $iDisplayStart = intval($request->get('start'));
        $sEcho = intval($request->get('draw'));

        $records = array();
        $records["data"] = array();

        $end = $iDisplayStart + $iDisplayLength;
        $end = $end > $iTotalRecords ? $iTotalRecords : $end;

        // Process Lead Status
        $DefaultJunkLeadStatus = LeadStatuses::where(array(
            'account_id' => Auth::User()->account_id,
            'is_junk' => 1,
        ))->first();
        if($DefaultJunkLeadStatus) {
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
            ->whereNotIn('leads.lead_status_id', array($default_junk_lead_status_id));


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
                $records["data"][$index] = array(
                    'PatientId' => $lead->PatientId,
                    'name' => $lead->name,
                    'phone' => GeneralFunctions::prepareNumber4Call($lead->patient->phone),
                    'city_id' => ($lead->city_id) ? $lead->city->name : '',
                    'lead_status_id' => ($lead->lead_status_id) ? $lead->lead_status->name : '',
                    'service_id' => ($lead->service_id) ? $lead->service->name : '',
                    'created_at' => Carbon::parse($lead->lead_created_at)->format('F j,Y h:i A'),
                    'created_by' => array_key_exists($lead->lead_created_by, $Users) ? $Users[$lead->lead_created_by]->name : 'N/A',
                );
                $index++;
            }
        }

        if ($request->get('customActionType') && $request->get('customActionType') == "group_action") {
            $Leads = Leads::whereIn('id', $request->get('id'));
            if ($Leads) {
                $Leads->delete();
            }
            $records["customActionStatus"] = "OK"; // pass custom message(useful for getting status of group actions)
            $records["customActionMessage"] = "Records has been deleted successfully!"; // pass custom message(useful for getting status of group actions)
        }

        $records["draw"] = $sEcho;
        $records["recordsTotal"] = $iTotalRecords;
        $records["recordsFiltered"] = $iTotalRecords;

        return response()->json($records);
    }

    /**
     * Patient Leads
     *
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function appointments($id)
    {
        if (!Gate::allows('patients_appointment_manage')) {
            return abort(401);
        }

        $patient = Patients::getData($id);
        if($patient){
            $cities = Cities::getActiveSortedFeatured(ACL::getUserCities());
            $cities->prepend('All','');

            $doctors = Doctors::getActiveOnly(ACL::getUserCentres());
            $doctors->prepend('All','');

            $locations = Locations::getActiveSorted(ACL::getUserCentres());
            $locations->prepend('All','');

            $services = Services::get()->pluck('name','id');
            $services->prepend('All','');

            $appointment_statuses = AppointmentStatuses::getAllParentRecords(Auth::User()->account_id);
            if ($appointment_statuses) {
                $appointment_statuses = $appointment_statuses->pluck('name', 'id');
            }
            $appointment_statuses->prepend('All', '');

            $appointment_types = AppointmentTypes::get()->pluck('name', 'id');
            $appointment_types->prepend('All', '');

            $users = User::getAllRecords(Auth::User()->account_id)->pluck('name', 'id');
            $users->prepend('All', '');

            $filters = Filters::all(Auth::User()->id, 'patient_appointments');

            return view('admin.patients.card.appointments.index', compact('patient','cities', 'users', 'doctors', 'locations', 'services', 'appointment_statuses', 'appointment_types','filters'));
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
    public function appointmentsDatatable($id, Request $request)
    {

        $apply_filter = false;
        if($request->get('action')) {
            $action = $request->get('action');
            if(isset($action[0]) && $action[0] == 'filter_cancel') {
                Filters::flush(Auth::User()->id, 'patient_appointments');
            } else if($action == 'filter') {
                $apply_filter = true;
            }
        }

        $where = array();
        if ($id){
            $where[] = array(
                'users.id',
                '=',
                $id
            );
            Filters::put(Auth::user()->id,'patient_appointments', 'id',$id);
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id,'patient_appointments','id');
            } else {
                if (Filters::get(Auth::user()->id, 'patient_appointments','id')){
                    $where[] = array(
                        'users.id',
                        '=',
                        Filters::get(Auth::user()->id, 'patient_appointments', 'id')
                    );
                }
            }
        }

        if (Auth::user()->id){
            $where[] = array(
                'users.account_id',
                '=',
                Auth::user()->account_id
            );
            Filters::put(Auth::user()->id, 'patient_appointments', 'account_id', Auth::user()->account_id);
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id,'patient_appointments','account_id');
            } else {
                if (Filters::get(Auth::user()->id,'patient_appointments','account_id')){
                    $where[] = array(
                        'users.account_id',
                        '=',
                        Filters::get(Auth::user()->id, 'patient_appointments', 'account_id')
                    );
                }
            }
        }


        $orderBy = 'created_at';
        $order = 'desc';

        if ($request->get('order')[0]['dir']) {
            $orderColumn = $request->get('order')[0]['column'];
            $orderBy = $request->get('columns')[$orderColumn]['data'];
            if ($orderBy == 'created_at') {
                $orderBy = 'appointments.created_at';
            }
            $order = $request->get('order')[0]['dir'];
        }

        if ($request->get('name') && $request->get('name') != ''){
            $where[] = array(
                'users.name',
                'like',
                '%' . $request->get('name') . '%'
            );
            Filters::put(Auth::user()->id, 'patient_appointments', 'name', $request->get('name'));
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id,'patient_appointments','name');
            } else {
                if (Filters::get(Auth::user()->id, 'patient_appointments','name')){
                    $where[] = array(
                        'users.name',
                        'like',
                        '%' . Filters::get(Auth::user()->id , 'patient_appointments','name') . '%'
                    );
                }
            }
        }

        if ($request->get('phone') && $request->get('phone') != '') {
            $where[] = array(
                'users.phone',
                'like',
                '%' . GeneralFunctions::cleanNumber($request->get('phone')) . '%'
            );
            Filters::put(Auth::User()->id, 'patient_appointments', 'phone', $request->get('phone'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'patient_appointments', 'phone');
            } else {
                if (Filters::get(Auth::User()->id, 'patient_appointments', 'phone')) {
                    $where[] = array(
                        'users.phone',
                        'like',
                        '%' . GeneralFunctions::cleanNumber(Filters::get(Auth::User()->id, 'patient_appointments', 'phone')) . '%'
                    );
                }
            }
        }
        if ($request->get('date_from') && $request->get('date_from') != '') {
            $where[] = array(
                'appointments.scheduled_date',
                '>=',
                $request->get('date_from') . ' 00:00:00'
            );

            Filters::put(Auth::User()->id, 'patient_appointments', 'date_from', $request->get('date_from'));
        } else {
            if($apply_filter) {
                Filters::forget(Auth::User()->id, 'patient_appointments', 'date_from');
            } else {
                if(Filters::get(Auth::User()->id, 'patient_appointments', 'date_from')) {
                    $where[] = array(
                        'appointments.scheduled_date',
                        '>=',
                        Filters::get(Auth::User()->id, 'patient_appointments', 'date_from')
                    );
                }
            }
        }

        if ($request->get('date_to') && $request->get('date_to') != '') {
            $where[] = array(
                'appointments.scheduled_date',
                '<=',
                $request->get('date_to') . ' 23:59:59'
            );

            Filters::put(Auth::User()->id, 'patient_appointments', 'date_to', $request->get('date_to'));
        } else {
            if($apply_filter) {
                Filters::forget(Auth::User()->id, 'patient_appointments', 'date_to');
            } else {
                if(Filters::get(Auth::User()->id, 'patient_appointments', 'date_to')) {
                    $where[] = array(
                        'appointments.scheduled_date',
                        '<=',
                        Filters::get(Auth::User()->id, 'patient_appointments', 'date_to')
                    );
                }
            }
        }

        if ($request->get('doctor_id') && $request->get('doctor_id') != '') {
            $where[] = array(
                'doctor_id',
                '=',
                $request->get('doctor_id')
            );
            Filters::put(Auth::user()->id,'patient_appointments','doctor_id', $request->get('doctor_id'));
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id,'patient_appointments','doctor_id');
            } else {
                if (Filters::get(Auth::user()->id,'patient_appointments', 'doctor_id')){
                    $where[] = array(
                        'doctor_id',
                        '=',
                        Filters::get(Auth::user()->id,'patient_appointments','doctor_id')
                    );
                }
            }
        }

        if ($request->get('city_id') && $request->get('city_id') != '') {
            $where[] = array(
                'city_id',
                '=',
                $request->get('city_id')
            );
            Filters::put(Auth::user()->id,'patient_appointments','city_id',$request->get('city_id'));
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id,'patient_appointments','city_id');
            } else {
                if (Filters::get(Auth::user()->id,'patient_appointments','city_id')){
                    $where[] =array(
                        'city_id',
                        '=',
                        Filters::get(Auth::user()->id,'patient_appointments','city_id')
                    );
                }
            }
        }

        if ($request->get('location_id') && $request->get('location_id') != '') {
            $where[] = array(
                'location_id',
                '=',
                $request->get('location_id')
            );
            Filters::put(Auth::user()->id,'patient_appointments','location_id',$request->get('location_id'));
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id,'patient_appointments', 'location_id');
            } else {
                if (Filters::get(Auth::user()->id,'patient_appointments','location_id')){
                    $where[] = array(
                        'location_id',
                        '=',
                        Filters::get(Auth::user()->id,'patient_appointments','location_id')
                    );
                }
            }
        }
        if ($request->get('service_id') && $request->get('service_id') != '') {
            $where[] = array(
                'service_id',
                '=',
                $request->get('service_id')
            );
            Filters::put(Auth::user()->id,'patient_appointments','service_id',$request->get('service_id'));
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id,'patient_appointments','service_id');
            } else {
                if (Filters::get(Auth::user()->id,'patient_appointments','service_id')){
                    $where[] = array(
                        'service_id',
                        '=',
                        Filters::get(Auth::user()->id,'patient_appointments','service_id')
                    );
                }
            }
        }

        if ($request->get('appointment_status_id') && $request->get('appointment_status_id') != '') {
            $where[] = array(
                'appointments.base_appointment_status_id',
                '=',
                $request->get('appointment_status_id')
            );
            Filters::put(Auth::user()->id, 'patient_appointments', 'appointment_status_id', $request->get('appointment_status_id'));
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id,'patient_appointments', 'appointment_status_id');
            } else {
                if (Filters::get(Auth::user()->id, 'patient_appointments', 'appointment_status_id')){
                    $where[] = array(
                        'appointments.base_appointment_status_id',
                        '=',
                        Filters::get(Auth::user()->id,'patient_appointments','appointment_status_id')
                    );
                }
            }
        }

        if ($request->get('appointment_type_id') && $request->get('appointment_type_id') != '') {
            $where[] = array(
                'appointments.appointment_type_id',
                '=',
                $request->get('appointment_type_id')
            );
            Filters::put(Auth::user()->id, 'patient_appointments', 'appointment_type_id', $request->get('appointment_type_id'));
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id,'patient_appointments','appointment_type_id');
            } else {
                if (Filters::get(Auth::user()->id , 'patient_appointments', 'appointment_type_id')){
                    $where[] = array(
                        'appointments.appointment_type_id',
                        '=',
                        Filters::get(Auth::user()->id , 'patient_appointments','appointment_type_id')
                    );
                }
            }
        }
        if ($request->get('consultancy_type') && $request->get('consultancy_type') != '') {
            $where[] = array(
                'appointments.consultancy_type',
                '=',
                $request->get('consultancy_type')
            );

            Filters::put(Auth::User()->id, 'patient_appointments', 'consultancy_type', $request->get('consultancy_type'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'patient_appointments', 'consultancy_type');
            } else {
                if (Filters::get(Auth::User()->id, 'patient_appointments', 'consultancy_type')) {
                    $where[] = array(
                        'appointments.consultancy_type',
                        '=',
                        Filters::get(Auth::User()->id, 'patient_appointments', 'consultancy_type')
                    );
                }
            }
        }
        if ($request->get('created_from') && $request->get('created_from') != '') {
            $where[] = array(
                'appointments.created_at',
                '>=',
                $request->get('created_from') . ' 00:00:00'
            );
            Filters::put(Auth::User()->id, 'patient_appointments', 'created_from', $request->get('created_from') . ' 00:00:00');
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'patient_appointments', 'created_from');
            } else {
                if (Filters::get(Auth::User()->id, 'patient_appointments', 'created_from')) {
                    $where[] = array(
                        'appointments.created_at',
                        '>=',
                        Filters::get(Auth::User()->id, 'patient_appointments', 'created_from') . ' 00:00:00'
                    );
                }
            }
        }

        if ($request->get('created_to') != '') {
            $where[] = array(
                'appointments.created_at',
                '<=',
                $request->get('created_to') . ' 23:59:59'
            );
            Filters::put(Auth::User()->id, 'patient_appointments', 'created_to', $request->get('created_to') . ' 23:59:59');
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'patient_appointments', 'created_to');
            } else {
                if (Filters::get(Auth::User()->id, 'patient_appointments', 'created_to')) {
                    $where[] = array(
                        'appointments.created_at',
                        '<=',
                        Filters::get(Auth::User()->id, 'patient_appointments', 'created_to') . ' 23:59:59'
                    );
                }
            }
        }

        if ($request->get('created_by') && $request->get('created_by') != '') {
            $where[] = array(
                'appointments.created_by',
                '=',
                $request->get('created_by')
            );
            Filters::put(Auth::user()->id,'patient_appointments', 'created_by', $request->get('created_by'));
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id,'patient_appointments','created_by');
            } else {
                if (Filters::get(Auth::user()->id, 'patient_appointments','created_by')){
                    $where[] = array(
                        'appointments.created_by',
                        '=',
                        Filters::get(Auth::user()->id, 'patient_appointments','created_by')
                    );
                }
            }
        }

        $countQuery = Appointments::join('users', function ($join) {
            $join->on('users.id', '=', 'appointments.patient_id')
                ->where('users.user_type_id', '=', config('constants.patient_id'));
        })
            ->whereIn('appointments.city_id', ACL::getUserCities())
            ->whereIn('appointments.location_id', ACL::getUserCentres());
        if (count($where)) {
            $countQuery->where($where);
        }
        if ($request->get('name') && $request->get('name') != '') {
            $countQuery->where(function ($query) {
                global $request;
                $query->where(
                    'users.name',
                    'like',
                    '%' . $request->get('name') . '%'
                );
                $query->orWhere(
                    'appointments.name',
                    'like',
                    '%' . $request->get('name') . '%'
                );
            });
        }
        $iTotalRecords = $countQuery->count();


        $iDisplayLength = intval($request->get('length'));
        $iDisplayLength = $iDisplayLength < 0 ? $iTotalRecords : $iDisplayLength;
        $iDisplayStart = intval($request->get('start'));
        $sEcho = intval($request->get('draw'));

        $records = array();
        $records["data"] = array();

        $end = $iDisplayStart + $iDisplayLength;
        $end = $end > $iTotalRecords ? $iTotalRecords : $end;

        $resultQuery = Appointments::join('users', function ($join) {
            $join->on('users.id', '=', 'appointments.patient_id')
                ->where('users.user_type_id', '=', config('constants.patient_id'));
        })
            ->whereIn('appointments.city_id', ACL::getUserCities())
            ->whereIn('appointments.location_id', ACL::getUserCentres());
        if (count($where)) {
            $resultQuery->where($where);
        }
        if ($request->get('name') && $request->get('name') != '') {
            $resultQuery->where(function ($query) {
                global $request;
                $query->where(
                    'users.name',
                    'like',
                    '%' . $request->get('name') . '%'
                );
                $query->orWhere(
                    'appointments.name',
                    'like',
                    '%' . $request->get('name') . '%'
                );
            });
        }

        $Appointments = $resultQuery->select('*', 'appointments.name as patient_name', 'appointments.id as app_id', 'appointments.created_by as app_created_by', 'appointments.created_at as app_created_at')
            ->limit($iDisplayLength)
            ->offset($iDisplayStart)
            ->orderBy($orderBy, $order)
            ->get();

        $AppointmentStatuses = AppointmentStatuses::getAllRecordsDictionary(Auth::User()->account_id);

        if ($Appointments) {

            /*
             * Grab User IDs and prepare
             */
            $user_ids = [];
            foreach ($Appointments as $appointment) {
                $user_ids[] = $appointment->app_created_by;
            }

            $Users = User::where('account_id', Auth::User()->account_id)
                ->whereIn('id', $user_ids)
                ->select('id', 'name')
                ->get()
                ->getDictionary();

            $index = 0;
            foreach ($Appointments as $appointment) {
                if ($appointment->consultancy_type == 'in_person') {
                    $consultancy_type = 'In Person';
                } else if ($appointment->consultancy_type == 'virtual') {
                    $consultancy_type = 'Virtual';
                } else {
                    $consultancy_type = '';
                }
                $records["data"][$index] = array(
                    'Patient_ID' => $appointment->patient_id,
                    'name' => ($appointment->patient_name) ? $appointment->patient_name : $appointment->name,
                    'phone' => GeneralFunctions::prepareNumber4Call($appointment->phone),
                    'scheduled_date' => ($appointment->scheduled_date) ? Carbon::parse($appointment->scheduled_date, null)->format('M j, Y') . ' at ' . Carbon::parse($appointment->scheduled_time, null)->format('h:i A') : '-',
                    'doctor_id' => $appointment->doctor->name,
                    'city_id' => $appointment->city_id ? $appointment->city->name : 'N/A',
                    'location_id' => $appointment->location_id ? $appointment->location->name : 'N/A',
                    'service_id' => $appointment->service->name,
                    'appointment_type_id' => $appointment->appointment_type->name,
                    'consultancy_type' => $consultancy_type,
                    'created_at' => Carbon::parse($appointment->app_created_at)->format('F j,Y h:i A'),
                    'created_by' => array_key_exists($appointment->app_created_by, $Users) ? $Users[$appointment->app_created_by]->name : 'N/A',
                    'appointment_status_id' => ($appointment->appointment_status_id ? ($appointment->appointment_status->parent_id ? $AppointmentStatuses[$appointment->appointment_status->parent_id]->name : $appointment->appointment_status->name) : ''),
                );

                $index++;
            }
        }

        if ($request->get('customActionType') && $request->get('customActionType') == "group_action") {
            $Appointments = Appointments::whereIn('id', $request->get('id'));
            if ($Appointments) {
                $Appointments->delete();
            }
            $records["customActionStatus"] = "OK"; // pass custom message(useful for getting status of group actions)
            $records["customActionMessage"] = "Records has been deleted successfully!"; // pass custom message(useful for getting status of group actions)
        }

        $records["draw"] = $sEcho;
        $records["recordsTotal"] = $iTotalRecords;
        $records["recordsFiltered"] = $iTotalRecords;

        return response()->json($records);
    }

    /**
     * Display a form to upload the image.
     * @param id
     * @return view
     */
    public function imageindex($id){

        if (!Gate::allows('patients_manage') && !Gate::allows('users_manage')) {
            return abort(401);
        }

        $patient = Patients::getData($id);
        if (!$patient) {
            return abort(401);
        }

        return view('admin.patients.card.image.add_image',compact('patient'));
    }

    /**
     * store the image of patient.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function imagestore(Request $request) {

        if (!Gate::allows('patients_manage') && !Gate::allows('users_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        $patient = Patients::getData($request->patient_id);

        if (!$patient) {
            return ApiHelper::apiResponse($this->success, 'Resource not found.', false);
        }
        if($request->file('file'))
        {
            $file = $request->file('file');

            $ext = $file->getClientOriginalExtension();
            if($ext=='jpg' || $ext=='jpeg' || $ext=='png' || $ext=='gif')
            {
                $fileName = time().'-'.str_replace(' ', '-', $file->getClientOriginalName());
                $file->storeAs('public/patient_image', $fileName);

                DB::table('users')->where('id', $patient->id)->update(['image_src' => $fileName]);
                return ApiHelper::apiResponse($this->success, 'Picture save successfully.', true, [
                    'image' => asset('storage/patient_image/'.$fileName)
                ]);
            }
            else{
                return ApiHelper::apiResponse($this->success, 'JPG , JPEG, PNG, GIF Only Allow.', false);
            }
        } else {
            return ApiHelper::apiResponse($this->success, 'Please provide the valid image.', false);
        }


    }

    /**
     * Display a list of document.
     * @param id
     * @return view
     */
    public function documentindex($id)
    {
        if (!Gate::allows('patients_document_manage')) {
            return abort(401);
        }
        $patient = Patients::where([['account_id','=',Auth::User()->account_id],['id','=',$id]])->first();

        $filters = Filters::all(Auth::User()->id, 'patient_documents');

        if($patient){
            return view('admin.patients.card.documents.add_documents',compact('patient', 'filters'));
        } else {
            return view('error_full');
        }

    }
    public function documentCreate($id){

        if (!Gate::allows('patients_document_create')) {
            return abort(401);
        }
        $patient = Patients::getData($id);
        return view('admin.patients.card.documents.create',compact('patient'));
    }

    /**
     * store document to upload document.
     * @param id
     * @return view
     */
    public function documentstore(Request $request){
        if (!Gate::allows('patients_document_create')) {
            return abort(401);
        }
        $validator = $this->verifyDocumentFields($request);
        if ($validator->fails()) {
            return response()->json(array(
                'status' => 0,
                'message' => $validator->messages()->all(),
            ));
        }
        $patient = Patients::getData($request->patient_id);

        if (!$patient) {
            return abort(401);
        }
        $file=$request->file('upload_file');
        $file->move('patient_document',$file->getClientOriginalName());
        $ext=$file->getClientOriginalExtension();
        if($ext=='jpg' || $ext=='jpeg' || $ext=='png' || $ext=='pdf' ||$ext=='docx' ||$ext=='xlsx')
        {
            $document = Documents::CreateRecord($file,$request,$patient->id);

            flash('Record has been created successfully.')->success()->important();
            return redirect()->route('admin.patients.document', ['id' => $patient->id]);
        }
        else{
            flash('File format not supported.')->warning()->important();
            return redirect()->route('admin.patients.document', ['id' => $patient->id]);
        }
    }

    /**
     * Validate form fields
     *
     * @param  \Illuminate\Http\Request $request
     * @return Validator $validator;
     */
    protected function verifyDocumentFields(Request $request)
    {
        return $validator = Validator::make($request->all(), [
            'name' => 'required',
            'upload_file' => 'required'
        ]);
    }

    /*
     * Display the document in datatable
     * @param id and request
     * @return mixed
     */
    public function documentdatatable($id, Request $request){

        $filename = 'patient_documents';
        $apply_filter = false;
        if($request->get('action')) {
            $action = $request->get('action');
            if(isset($action[0]) && $action[0] == 'filter_cancel') {
                Filters::flush(Auth::User()->id, $filename);
            } else if($action == 'filter') {
                $apply_filter = true;
            }
        }

        if (!Gate::allows('users_manage')) {
            return abort(401);
        }
        $records = array();
        $records["data"] = array();
        // Get Total Records
        $iTotalRecords = Documents::getTotalRecords($request, Auth::User()->account_id,$id, $apply_filter, $filename);
        $iDisplayLength = intval($request->get('length'));
        $iDisplayLength = $iDisplayLength < 0 ? $iTotalRecords : $iDisplayLength;
        $iDisplayStart = intval($request->get('start'));
        $sEcho = intval($request->get('draw'));

        $documents = Documents::getRecords($id,$request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $apply_filter, $filename);

        if($documents) {
            foreach($documents as $document) {
                $records["data"][] = array(
                    'name' => $document->name,
                    'created_at' => Carbon::parse($document->created_at)->format('F j,Y h:i A'),
                    'actions' => view('admin.patients.card.documents.actions', compact('document'))->render(),
                );
            }
        }
        $records["draw"] = $sEcho;
        $records["recordsTotal"] = $iTotalRecords;
        $records["recordsFiltered"] = $iTotalRecords;

        return response()->json($records);
    }

    /*
     * Display the form for edit
     *
     *@param $id
     *
     * @return view
     */
    public function documentedit($id){

        if (!Gate::allows('patients_document_edit')) {
            return abort(401);
        }
        $documents = Documents::find($id);
        return view('admin.patients.card.documents.edit',compact('documents'));
    }

    /*
     *update the docucment
     *
     *@parm Request and id
     *
     *@return view
     * */
    public function documentupdate(Request $request,$id){

        if (!Gate::allows('patients_document_edit')) {
            return abort(401);
        }
        $validator = $this->verifyupdatedcoumentFields($request);
        if ($validator->fails()) {
            return response()->json(array(
                'status' => 0,
                'message' => $validator->messages()->all(),
            ));
        }
        if($document = Documents::updateRecord($id, $request, Auth::User()->account_id)) {

            flash('Record has been updated successfully.')->success()->important();
            return redirect()->route('admin.patients.document', ['id' => $document->user_id]);

        } else {
            flash('Something went wrong.')->warning()->important();
            return redirect()->route('admin.patients.document', ['id' => $document->user_id]);
        }
    }

    /**
     * Validate form fields
     *
     * @param  \Illuminate\Http\Request $request
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
    public function documentdelete($id){

        if (! Gate::allows('patients_document_destroy')) {
            return abort(401);
        }
        $document = Documents::find($id);

        Documents::DeleteRecord($id);

        return redirect()->route('admin.patients.document', ['id' => $document->user_id]);
    }

}
