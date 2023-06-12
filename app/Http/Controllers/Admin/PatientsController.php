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
            'contact' => Gate::allows('contact'),
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
     * @param \Illuminate\Http\Request $request
     * @param null $id
     * @return Validator $validator;
     */
    protected function verifyFields(Request $request, $id = null)
    {
        /**
         * To validate phone number with unique
         */
        $data = $request->all();

        if($request->input('phone') == '***********'){
            GeneralFunctions::cleanNumber($data['old_phone']);
        } else {

            $data['phone'] = GeneralFunctions::cleanNumber($data['phone']);
        }

        return Validator::make($data, [
            'email' => 'sometimes|nullable|email',
            'name' => 'required',
            //'phone' => 'required|unique:users,phone,' . $id,
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

        $validator = $this->verifyFields($request, $id);

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
            Leads::where(['phone' => $data['phone']])->update(['name' => $data['name']]);

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
                    'contact' => Gate::allows('contact'),
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

        return view('admin.patients.card.appointments.index');
    }

    /**
     * Display a listing of Lead_statuse.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\JsonResponse
     */
    public function appointmentsDatatable($id, Request $request)
    {

        $fileName = 'patient_appointments';

        $filters = getFilters($request->all());

        $apply_filter = checkFilters($filters, $fileName);

        $where = array();
        if ($id){
            $where[] = array(
                'users.id',
                '=',
                $id
            );
            Filters::put(Auth::user()->id,$fileName, 'id',$id);
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id,$fileName,'id');
            } else {
                if (Filters::get(Auth::user()->id, $fileName,'id')){
                    $where[] = array(
                        'users.id',
                        '=',
                        Filters::get(Auth::user()->id, $fileName, 'id')
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
            Filters::put(Auth::user()->id, $fileName, 'account_id', Auth::user()->account_id);
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id,$fileName,'account_id');
            } else {
                if (Filters::get(Auth::user()->id,$fileName,'account_id')){
                    $where[] = array(
                        'users.account_id',
                        '=',
                        Filters::get(Auth::user()->id, $fileName, 'account_id')
                    );
                }
            }
        }

        $filters = getFilters($request->all());

        if (hasFilter($filters, 'name')) {
            $where[] = array(
                'users.name',
                'like',
                '%' . $filters['name'] . '%'
            );
            Filters::put(Auth::user()->id, $fileName, 'name', $filters['name']);
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id,$fileName,'name');
            } else {
                if (Filters::get(Auth::user()->id, $fileName,'name')){
                    $where[] = array(
                        'users.name',
                        'like',
                        '%' . Filters::get(Auth::user()->id , $fileName,'name') . '%'
                    );
                }
            }
        }

        if (hasFilter($filters, 'phone')) {
            $where[] = array(
                'users.phone',
                'like',
                '%' . GeneralFunctions::cleanNumber($filters['phone']) . '%'
            );
            Filters::put(Auth::User()->id, $fileName, 'phone', $filters['name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $fileName, 'phone');
            } else {
                if (Filters::get(Auth::User()->id, $fileName, 'phone')) {
                    $where[] = array(
                        'users.phone',
                        'like',
                        '%' . GeneralFunctions::cleanNumber(Filters::get(Auth::User()->id, 'patient_appointments', 'phone')) . '%'
                    );
                }
            }
        }
        if (hasFilter($filters, 'date_from')) {
            $where[] = array(
                'appointments.scheduled_date',
                '>=',
                $filters['date_from'] . ' 00:00:00'
            );

            Filters::put(Auth::User()->id, $fileName, 'date_from', $filters['date_from']);
        } else {
            if($apply_filter) {
                Filters::forget(Auth::User()->id, $fileName, 'date_from');
            } else {
                if(Filters::get(Auth::User()->id, $fileName, 'date_from')) {
                    $where[] = array(
                        'appointments.scheduled_date',
                        '>=',
                        Filters::get(Auth::User()->id, $fileName, 'date_from')
                    );
                }
            }
        }

        if (hasFilter($filters, 'date_to')) {
            $where[] = array(
                'appointments.scheduled_date',
                '<=',
                $filters['date_to'] . ' 23:59:59'
            );

            Filters::put(Auth::User()->id, $fileName, 'date_to', $filters['date_to']);
        } else {
            if($apply_filter) {
                Filters::forget(Auth::User()->id, $fileName, 'date_to');
            } else {
                if(Filters::get(Auth::User()->id, $fileName, 'date_to')) {
                    $where[] = array(
                        'appointments.scheduled_date',
                        '<=',
                        Filters::get(Auth::User()->id, 'patient_appointments', 'date_to')
                    );
                }
            }
        }

        if (hasFilter($filters, 'doctor_id')) {
            $where[] = array(
                'doctor_id',
                '=',
                $filters['doctor_id']
            );
            Filters::put(Auth::user()->id, $fileName,'doctor_id', $filters['doctor_id']);
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id, $fileName,'doctor_id');
            } else {
                if (Filters::get(Auth::user()->id, $fileName, 'doctor_id')){
                    $where[] = array(
                        'doctor_id',
                        '=',
                        Filters::get(Auth::user()->id, $fileName,'doctor_id')
                    );
                }
            }
        }

        if (hasFilter($filters, 'city_id')) {
            $where[] = array(
                'city_id',
                '=',
                $filters['city_id']
            );
            Filters::put(Auth::user()->id, $fileName,'city_id', $filters['city_id']);
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id, $fileName,'city_id');
            } else {
                if (Filters::get(Auth::user()->id, $fileName,'city_id')){
                    $where[] =array(
                        'city_id',
                        '=',
                        Filters::get(Auth::user()->id, $fileName,'city_id')
                    );
                }
            }
        }

        if (hasFilter($filters, 'location_id')) {
            $where[] = array(
                'location_id',
                '=',
                $filters['location_id']
            );
            Filters::put(Auth::user()->id, $fileName,'location_id', $filters['location_id']);
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id, $fileName, 'location_id');
            } else {
                if (Filters::get(Auth::user()->id, $fileName,'location_id')){
                    $where[] = array(
                        'location_id',
                        '=',
                        Filters::get(Auth::user()->id,$fileName,'location_id')
                    );
                }
            }
        }
        if (hasFilter($filters, 'service_id')) {
            $where[] = array(
                'service_id',
                '=',
                $filters['service_id']
            );
            Filters::put(Auth::user()->id, $fileName,'service_id', $filters['service_id']);
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id, $fileName,'service_id');
            } else {
                if (Filters::get(Auth::user()->id, $fileName,'service_id')){
                    $where[] = array(
                        'service_id',
                        '=',
                        Filters::get(Auth::user()->id, $fileName,'service_id')
                    );
                }
            }
        }

        if (hasFilter($filters, 'appointment_status_id')) {
            $where[] = array(
                'appointments.base_appointment_status_id',
                '=',
                $filters['appointment_status_id']
            );
            Filters::put(Auth::user()->id, $fileName, 'appointment_status_id', $filters['appointment_status_id']);
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id,$fileName, 'appointment_status_id');
            } else {
                if (Filters::get(Auth::user()->id, $fileName, 'appointment_status_id')){
                    $where[] = array(
                        'appointments.base_appointment_status_id',
                        '=',
                        Filters::get(Auth::user()->id,$fileName,'appointment_status_id')
                    );
                }
            }
        }

        if (hasFilter($filters, 'appointment_type_id')) {
            $where[] = array(
                'appointments.appointment_type_id',
                '=',
                $filters['appointment_type_id']
            );
            Filters::put(Auth::user()->id, $fileName, 'appointment_type_id', $filters['appointment_type_id']);
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id,$fileName,'appointment_type_id');
            } else {
                if (Filters::get(Auth::user()->id , $fileName, 'appointment_type_id')){
                    $where[] = array(
                        'appointments.appointment_type_id',
                        '=',
                        Filters::get(Auth::user()->id , $fileName,'appointment_type_id')
                    );
                }
            }
        }
        if (hasFilter($filters, 'consultancy_type')) {
            $where[] = array(
                'appointments.consultancy_type',
                '=',
                $filters['consultancy_type']
            );

            Filters::put(Auth::User()->id, $fileName, 'consultancy_type', $filters['consultancy_type']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $fileName, 'consultancy_type');
            } else {
                if (Filters::get(Auth::User()->id, $fileName, 'consultancy_type')) {
                    $where[] = array(
                        'appointments.consultancy_type',
                        '=',
                        Filters::get(Auth::User()->id, $fileName, 'consultancy_type')
                    );
                }
            }
        }
        if (hasFilter($filters, 'created_from')) {
            $where[] = array(
                'appointments.created_at',
                '>=',
                $filters['created_from'] . ' 00:00:00'
            );
            Filters::put(Auth::User()->id, $fileName, 'created_from', $filters['created_from'] . ' 00:00:00');
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $fileName, 'created_from');
            } else {
                if (Filters::get(Auth::User()->id, $fileName, 'created_from')) {
                    $where[] = array(
                        'appointments.created_at',
                        '>=',
                        Filters::get(Auth::User()->id, $fileName, 'created_from') . ' 00:00:00'
                    );
                }
            }
        }

        if (hasFilter($filters, 'created_to')) {
            $where[] = array(
                'appointments.created_at',
                '<=',
                $filters['created_to'] . ' 23:59:59'
            );
            Filters::put(Auth::User()->id, $fileName, 'created_to', $filters['created_to'] . ' 23:59:59');
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $fileName, 'created_to');
            } else {
                if (Filters::get(Auth::User()->id, $fileName, 'created_to')) {
                    $where[] = array(
                        'appointments.created_at',
                        '<=',
                        Filters::get(Auth::User()->id, $fileName, 'created_to') . ' 23:59:59'
                    );
                }
            }
        }

        if (hasFilter($filters, 'created_by')) {
            $where[] = array(
                'appointments.created_by',
                '=',
                $filters['created_by']
            );
            Filters::put(Auth::user()->id,$fileName, 'created_by',  $filters['created_by']);
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id,$fileName,'created_by');
            } else {
                if (Filters::get(Auth::user()->id, $fileName,'created_by')){
                    $where[] = array(
                        'appointments.created_by',
                        '=',
                        Filters::get(Auth::user()->id, $fileName,'created_by')
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
        if (hasFilter($filters, 'name')) {
            $countQuery->where(function ($query) use($filters) {
                global $request;
                $query->where(
                    'users.name',
                    'like',
                    '%' . $filters['name'] . '%'
                );
                $query->orWhere(
                    'appointments.name',
                    'like',
                    '%' . $filters['name'] . '%'
                );
            });
        }
        $iTotalRecords = $countQuery->count();

        list($orderBy, $order) = getSortBy($request, 'created_at', 'DESC', 'appointments');

        list($iDisplayLength, $iDisplayStart, $pages, $page) = getPaginationElement($request, $iTotalRecords);

        $records = array();
        $records["data"] = array();

        $resultQuery = Appointments::join('users', function ($join) {
            $join->on('users.id', '=', 'appointments.patient_id')
                ->where('users.user_type_id', '=', config('constants.patient_id'));
        })
            ->whereIn('appointments.city_id', ACL::getUserCities())
            ->whereIn('appointments.location_id', ACL::getUserCentres());
        if (count($where)) {
            $resultQuery->where($where);
        }
        if (hasFilter($filters, 'name')) {
            $resultQuery->where(function ($query) use($filters) {
                global $request;
                $query->where(
                    'users.name',
                    'like',
                    '%' . $filters['name'] . '%'
                );
                $query->orWhere(
                    'appointments.name',
                    'like',
                    '%' . $filters['name'] . '%'
                );
            });
        }

        $Appointments = $resultQuery->select('*', 'appointments.name as patient_name', 'appointments.id as app_id', 'appointments.created_by as app_created_by', 'appointments.created_at as app_created_at')
            ->limit($iDisplayLength)
            ->offset($iDisplayStart)
            ->orderBy($orderBy, $order)
            ->get();

        $AppointmentStatuses = AppointmentStatuses::getAllRecordsDictionary(Auth::User()->account_id);

        $records = $this->getFilterData($records, $fileName);

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

            $records["meta"] = [
                'field' => $orderBy,
                'page' => $page,
                'pages' => $pages,
                'perpage' => $iDisplayLength,
                'total' => $iTotalRecords,
                'sort' => $order,
            ];

        }

        if (hasFilter($filters, 'delete')) {
            $ids = explode(',', $filters['delete']);
            $Appointments = Appointments::whereIn('id', $ids);
            if ($Appointments) {
                $Appointments->delete();
            }
            $records["status"] = true; // pass custom message(useful for getting status of group actions)
            $records["message"] = "Records has been deleted successfully!"; // pass custom message(useful for getting status of group actions)
        }

        return ApiHelper::apiDataTable($records);
    }

    private function getFilterData($records, $fileName) {

        $patient = Patients::getData(request('id'));
        if($patient) {
            $cities = Cities::getActiveSortedFeatured(ACL::getUserCities());

            $doctors = Doctors::getActiveOnly(ACL::getUserCentres());

            $locations = Locations::getActiveSorted(ACL::getUserCentres());

            $services = Services::get()->pluck('name', 'id');

            $appointment_statuses = AppointmentStatuses::getAllParentRecords(Auth::User()->account_id);
            if ($appointment_statuses) {
                $appointment_statuses = $appointment_statuses->pluck('name', 'id');
            }

            $appointment_types = AppointmentTypes::get()->pluck('name', 'id');

            $users = User::getAllRecords(Auth::User()->account_id)->pluck('name', 'id');

            $filters = Filters::all(Auth::User()->id, $fileName);

            $records['filter_values'] = [
                'patient' => $patient,
                'cities' => $cities,
                'users' => $users,
                'doctors' => $doctors,
                'locations' => $locations,
                'services' => $services,
                'appointment_statuses' => $appointment_statuses,
                'appointment_types' => $appointment_types,
                'consultancy_types' => config('constants.consultancy_type_array')
            ];

            if (isset($filters['created_from'])) {
                $filters['created_from'] = date('Y-m-d', strtotime($filters['created_from']));
            }
            if (isset($filters['created_to'])) {
                $filters['created_to'] = date('Y-m-d', strtotime($filters['created_to']));
            }

            $records['active_filters'] = $filters;

        }

        return $records;

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
     * @return \Illuminate\Http\JsonResponse
     */
    public function documentstore(Request $request){

        if (!Gate::allows('patients_document_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $validator = $this->verifyDocumentFields($request);
        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }
        $patient = Patients::getData($request->patient_id);

        if (!$patient) {
            return ApiHelper::apiResponse($this->success, 'Resource not found', false);
        }

        $file = $request->file('file');
        $ext = $file->getClientOriginalExtension();
        if($ext=='jpg' || $ext=='jpeg' || $ext=='png' || $ext=='pdf' ||$ext=='docx' ||$ext=='xlsx')
        {
            $fileName = time().'-'.str_replace(' ', '-', $file->getClientOriginalName());
            $file->storeAs('public/patient_image', $fileName);

            $path = 'patient_image/'. $fileName;
            Documents::CreateRecord($request, $path, $patient->id);

            return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
        }

        return ApiHelper::apiResponse($this->success, 'File format not supported.', false);
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
            'file' => 'required'
        ]);
    }

    /*
     * Display the document in datatable
     * @param id and request
     * @return mixed
     */
    public function documentdatatable($id, Request $request){

        $filename = 'patient_documents';

        $filters = getFilters($request->all());

        $apply_filter = checkFilters($filters, $filename);

        if (!Gate::allows('users_manage')) {
            return abort(401);
        }
        $records = array();
        $records["data"] = array();
        // Get Total Records
        $iTotalRecords = Documents::getTotalRecords($request, Auth::User()->account_id,$id, $apply_filter, $filename);

        list($orderBy, $order) = getSortBy($request);

        list($iDisplayLength, $iDisplayStart, $pages, $page) = getPaginationElement($request, $iTotalRecords);

        $documents = Documents::getRecords($id,$request, $iDisplayStart, $iDisplayLength, Auth::user()->account_id, $apply_filter, $filename);

        $records = $this->getFilters($records, $filename);

        if($documents->count()) {
            $records['data'] = $documents;

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
            'edit' => Gate::allows('patients_document_edit'),
            'delete' => Gate::allows('patients_document_destroy'),
            'manage' => Gate::allows('patients_document_manage'),
        ];

        return response()->json($records);
    }

    private function getFilters($records, $filename) {

        $records['active_filters'] = Filters::all(Auth::user()->id, $filename);

        $records["filter_values"] = [
            'form_types' => '',
            'status' => config('constants.status')
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
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $validator = $this->verifyupdatedcoumentFields($request);
        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }
        if($document = Documents::updateRecord($id, $request, Auth::user()->account_id)) {

            return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');

        }

        return ApiHelper::apiResponse($this->success, 'Something went wrong.', false);
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
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        Documents::DeleteRecord($id);

        return ApiHelper::apiResponse($this->success, 'Record has been deleted successfully.');
    }

}
