<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ExportLead;
use App\HelperModule\ApiHelper;
use App\Helpers\ACL;
use App\Helpers\Filters;
use App\Helpers\TelenorSMSAPI;
use App\Http\Requests\Admin\StoreUpdateLeadCommentsRequest;
use App\Jobs\LeadUserUpdateEmailGender;
use App\Models\Cities;
use App\Models\LeadComments;
use App\Models\Leads;
use App\Models\LeadSources;
use App\Models\LeadStatuses;
use App\Models\Patients;
use App\Models\Regions;
use App\Models\Settings;
use App\Models\SMSLogs;
use App\Models\SMSTemplates;
use App\Models\Services;
use App\Models\Towns;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FileUploadLeadsRequest;
use Auth;
use File;
use App\Helpers\GeneralFunctions;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Config;
use Session;
use DB;
use Validator;
use App\Models\Appointments;
use App\Models\LeadsServices;
use App\Models\Locations;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Support\Facades\Auth as FacadesAuth;
use Illuminate\Support\Facades\DB as FacadesDB;

class LeadsController extends Controller
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
     * Display a listing of Lead.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (!Gate::allows('leads_manage')) {
            return abort(401);
        }
        return view('admin.leads.index');
    }

    /**
     * Display a listing of Lead_statuse.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request)
    {
        try {
            $where = array();
            $records = array();
            $records["data"] = array();
            $lead_type = null;
            $filename = 'leads';
            if ($request->has('type')) {
               $filename = 'junk_leads';
               $lead_type = $request->type;
            }
            $filters = getFilters($request->all());
            $apply_filter = checkFilters($filters, $filename);
            if (hasFilter($filters, 'delete')) {
                $ids = explode(',', $filters['delete']);
                $Leads = Leads::whereIn('id', $ids);
                if ($Leads->count()) {
                    $Leads->delete();
                }
                $records["status"] = true; // pass custom message(useful for getting status of group actions)
                $records["message"] = "Records has been deleted successfully!"; // pass custom message(useful for getting status of group actions)
            }
            if ($request->has('sort')) {
                list($orderBy, $order) = getSortBy($request, 'leads.created_at', 'DESC');

                Filters::put(Auth::User()->id, $filename, 'order_by', $orderBy);
                Filters::put(Auth::User()->id, $filename, 'order', $order);
            } else {
                if (
                    Filters::get(Auth::User()->id, $filename, 'order_by')
                    && Filters::get(Auth::User()->id, $filename, 'order')
                ) {
                    $orderBy = Filters::get(Auth::User()->id, $filename, 'order_by');
                    $order = Filters::get(Auth::User()->id, $filename, 'order');

                    if ($orderBy == 'created_at') {
                        $orderBy = 'leads.created_at';
                    }
                } else {
                    $orderBy = 'created_at';
                    $order = 'desc';
                    if ($orderBy == 'created_at') {
                        $orderBy = 'leads.created_at';
                    }

                    Filters::put(Auth::User()->id, $filename, 'order_by', $orderBy);
                    Filters::put(Auth::User()->id, $filename, 'order', $order);
                }
            }
            if (hasFilter($filters, 'lead_id')) {
                $where[] = array(
                    'id',
                    '=',
                    $filters['lead_id']
                    //GeneralFunctions::patientSearch($filters['patient_id'])
                );
                Filters::put(Auth::User()->id, $filename, 'lead_id', $filters['lead_id']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, $filename, 'lead_id');
                } else {
                    if (Filters::get(Auth::User()->id, $filename, 'lead_id')) {
                        $where[] = array(
                            'id',
                            '=',
                            Filters::get(Auth::User()->id, $filename, 'lead_id')
                        );
                    }
                }
            }
            if (hasFilter($filters, 'name')) {
                $where[] = array(
                    'name',
                    'like',
                    '%' . $filters['name'] . '%'
                );

                Filters::put(Auth::User()->id, $filename, 'name', $filters['name']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, $filename, 'name');
                } else {
                    if (Filters::get(Auth::User()->id, $filename, 'name')) {
                        $where[] = array(
                            'name',
                            'like',
                            '%' . Filters::get(Auth::User()->id, $filename, 'name') . '%'
                        );
                    }
                }
            }
            if (hasFilter($filters, 'phone')) {
                $where[] = array(
                    'phone',
                    'like',
                    '%' . GeneralFunctions::cleanNumber($filters['phone']) . '%'
                );

                Filters::put(Auth::User()->id, $filename, 'phone', GeneralFunctions::cleanNumber($filters['phone']));
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, $filename, 'phone');
                } else {
                    if (Filters::get(Auth::User()->id, $filename, 'phone')) {
                        $where[] = array(
                            'phone',
                            'like',
                            '%' . GeneralFunctions::cleanNumber(Filters::get(Auth::User()->id, 'leads', 'phone')) . '%'
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
                Filters::put(Auth::User()->id, $filename, 'city_id', $filters['city_id']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, $filename, 'city_id');
                } else {
                    if (Filters::get(Auth::User()->id, $filename, 'city_id')) {
                        $where[] = array(
                            'city_id',
                            '=',
                            Filters::get(Auth::User()->id, $filename, 'city_id')
                        );
                    }
                }
            }
            if (hasFilter($filters, 'location_id')) {
                $where[] = array(
                    'leads.location_id',
                    '=',
                    $filters['location_id']
                );
                Filters::put(Auth::User()->id, $filename, 'location_id', $filters['location_id']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, $filename, 'location_id');
                } else {
                    if (Filters::get(Auth::User()->id, $filename, 'location_id')) {
                        $where[] = array(
                            'leads.location_id',
                            '=',
                            Filters::get(Auth::User()->id, $filename, 'location_id')
                        );
                    }
                }
            }
            if (hasFilter($filters, 'gender_id')) {
                $where[] = array(
                    'gender',
                    '=',
                    $filters['gender_id']
                );
                Filters::put(Auth::User()->id, $filename, 'gender_id', $filters['gender_id']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, $filename, 'gender_id');
                } else {
                    if (Filters::get(Auth::User()->id, $filename, 'gender_id')) {
                        $where[] = array(
                            'gender',
                            '=',
                            Filters::get(Auth::User()->id, $filename, 'gender_id')
                        );
                    }
                }
            }
            if (hasFilter($filters, 'region_id')) {
                $where[] = array(
                    'region_id',
                    '=',
                    $filters['region_id']
                );

                Filters::put(Auth::User()->id, $filename, 'region_id', $filters['region_id']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, $filename, 'region_id');
                } else {
                    if (Filters::get(Auth::User()->id, $filename, 'region_id')) {
                        $where[] = array(
                            'region_id',
                            '=',
                            Filters::get(Auth::User()->id, $filename, 'region_id')
                        );
                    }
                }
            }
            if (hasFilter($filters, 'lead_status_id')) {
                $where[] = array(
                    'lead_status_id',
                    '=',
                    $filters['lead_status_id']
                );

                Filters::put(Auth::User()->id, $filename, 'lead_status_id', $filters['lead_status_id']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, $filename, 'lead_status_id');
                } else {
                    if (Filters::get(Auth::User()->id, $filename, 'lead_status_id')) {
                        $where[] = array(
                            'lead_status_id',
                            '=',
                            Filters::get(Auth::User()->id, $filename, 'lead_status_id')
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

                Filters::put(Auth::User()->id, $filename, 'service_id', $filters['service_id']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, $filename, 'service_id');
                } else {
                    if (Filters::get(Auth::User()->id, $filename, 'service_id')) {
                        $where[] = array(
                            'service_id',
                            '=',
                            Filters::get(Auth::User()->id, $filename, 'service_id')
                        );
                    }
                }
            }
            if (hasFilter($filters, 'created_by')) {
                $where[] = array(
                    'leads.created_by',
                    '=',
                    $filters['created_by']
                );

                Filters::put(Auth::User()->id, $filename, 'created_by', $filters['created_by']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, $filename, 'created_by');
                } else {
                    if (Filters::get(Auth::User()->id, $filename, 'created_by')) {
                        $where[] = array(
                            'leads.created_by',
                            '=',
                            Filters::get(Auth::User()->id, $filename, 'created_by')
                        );
                    }
                }
            }
            if (hasFilter($filters, 'date_from')) {
                $where[] = array(
                    'leads.created_at',
                    '>=',
                    $filters['date_from'] . ' 00:00:00'
                );

                Filters::put(Auth::User()->id, $filename, 'date_from', $filters['date_from']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, $filename, 'date_from');
                } else {
                    if (Filters::get(Auth::User()->id, $filename, 'date_from')) {
                        $where[] = array(
                            'leads.created_at',
                            '>=',
                            Filters::get(Auth::User()->id, $filename, 'date_from') . ' 00:00:00'
                        );
                    }
                }
            }
            if (hasFilter($filters, 'date_to')) {
                $where[] = array(
                    'leads.created_at',
                    '<=',
                    $filters['date_to'] . ' 23:59:59'
                );

                Filters::put(Auth::User()->id, $filename, 'date_to', $filters['date_to']);
            } else {
                if ($apply_filter) {
                    Filters::forget(Auth::User()->id, $filename, 'date_to');
                } else {
                    if (Filters::get(Auth::User()->id, $filename, 'date_to')) {
                        $where[] = array(
                            'leads.created_at',
                            '<=',
                            Filters::get(Auth::User()->id, $filename, 'date_to') . ' 23:59:59'
                        );
                    }
                }
            }
            // Find Junk Lead Status to exclude
            $junk_lead_statuses = LeadStatuses::where(array(
                'account_id' => Auth::User()->account_id,
                'is_junk' => 1,
            ))->first();
            // $countQuery = Leads::join('users', 'users.id', '=', 'leads.patient_id')
            //     ->where('users.user_type_id', '=', Config::get('constants.patient_id'))
            //     ->where(function ($query) {
            //         $query->whereIn('leads.city_id', ACL::getUserCities());
            //         $query->orWhereNull('leads.city_id');
            //     });
            $countQuery = Leads::where(function ($query) {
                    $query->whereIn('leads.city_id', ACL::getUserCities());
                    $query->orWhereNull('leads.city_id');
                });
            if (count($where)) {
                $countQuery->where($where);
            }
            // if ($lead_type) {
            //     $countQuery->where('leads.lead_status_id', $junk_lead_statuses->id ?? 0);
            // } else {
            //     $countQuery->where('leads.lead_status_id', '!=', $junk_lead_statuses->id ?? 0);
            // }
            $iTotalRecords = $countQuery->count();

            list($iDisplayLength, $iDisplayStart, $pages, $page) = getPaginationElement($request, $iTotalRecords);
            // $resultQuery = Leads::join('users', 'users.id', '=', 'leads.patient_id')
            //     ->where('users.user_type_id', '=', Config::get('constants.patient_id'))
            //     ->where(function ($query) {
            //         $query->whereIn('leads.city_id', ACL::getUserCities());
            //         $query->orWhereNull('leads.city_id');
            //     });
            $resultQuery = Leads::with('lead_service')->where(function ($query) {
                    $query->whereIn('leads.city_id', ACL::getUserCities());
                    $query->orWhereNull('leads.city_id');
                });
            if (count($where)) {
                $resultQuery->where($where);
            }
            if ($lead_type) {
                $resultQuery->where('leads.lead_status_id', $junk_lead_statuses->id ?? 0);
            } else {
                $resultQuery->where('leads.lead_status_id', '!=', $junk_lead_statuses->id ?? 0);
            }
            if(\Illuminate\Support\Facades\Gate::allows("view_inactive_leads")){
                $Leads = $resultQuery->select('*','leads.active' ,'leads.created_by as lead_created_by', 'leads.id as lead_id', 'leads.created_at as lead_created_at','gender')
                ->limit($iDisplayLength)
                ->offset($iDisplayStart)
                ->orderBy($orderBy, $order)
                ->get();
            }else{
                $Leads = $resultQuery->select('*','leads.active' ,'leads.created_by as lead_created_by', 'leads.id as lead_id', 'leads.created_at as lead_created_at','gender')
                ->where('leads.active',1)
                ->limit($iDisplayLength)
                ->offset($iDisplayStart)
                ->orderBy($orderBy, $order)
                ->get();
            }
            //dd($Leads->toArray());
            $Users = User::getAllRecords(Auth::User()->account_id)->getDictionary();
            $Regions = Regions::getAllRecordsDictionary(Auth::User()->account_id);
            $lead_status = LeadStatuses::getAllRecordsDictionary(Auth::User()->account_id);
            // Convert Lead status to Converted
            $DefaultConvertedLeadStatus = LeadStatuses::where(array(
                'account_id' => Auth::User()->account_id,
                'is_converted' => 1,
            ))->first();
            if ($DefaultConvertedLeadStatus) {
                $default_converted_lead_status_id = $DefaultConvertedLeadStatus->id;
            } else {
                $default_converted_lead_status_id = Config::get('constants.lead_status_converted');
            }
            $records = $this->getFiltersData($records, $filename);
            if ($Leads->count()) {
                $index = 0;
                foreach ($Leads as $lead) {
                    $service = [];
                    $child_service = [];
                    $service_active = [];
                    //dd($lead->toArray());
                    foreach($lead->lead_service as $data){
                        //dd($data);
                        if(!in_array($data->service->name, $service)){
                            $service[] = $data->service->name;
                        }
                        if($data->status == 1){
                            $child_service[] = $data->childservice->name;
                            $service_active[] = $data->service->name;
                        }
                    }
                    $services = implode(",", $service);
                    $child_services = implode(",", $child_service);
                    $service_actives = implode(",", $service_active);

                    //dd($services);
                    //check lead s lead status has parent or not if yes than get parent data and if no than get simple that row data
                    if (array_key_exists($lead->lead_status_id, $lead_status)) {
                        if ($lead_status[$lead->lead_status_id]->parent_id == 0) {
                            $lead_status_data = $lead_status[$lead->lead_status_id];
                        } else {
                            $lead_status_data = $lead_status[$lead_status[$lead->lead_status_id]->parent_id];
                        }
                    }
                    $records["data"][$index] = array(
                        'id' => $lead->id,
                        'lead_id' => $lead->lead_id,
                        //'PatientId' => GeneralFunctions::patientSearchStringAdd($lead->PatientId),
                        'name' => $lead->name,
                        'gender'=>$lead->gender==1 ? 'Male' : 'Female',
                        'active' => $lead->active,
                        'cityId' => $lead?->city?->id ?? 0,
                        'phone' =>  GeneralFunctions::prepareNumber4Call($lead->phone),
                        'city_id' => $lead->city->name ?? '', //view('admin.leads.city', compact('lead'))->render(),
                        'region_id' => (array_key_exists($lead->region_id, $Regions)) ? $Regions[$lead->region_id]->name : 'N/A',
                        'lead_status_id' => $lead_status_data->name ?? '',
                        'service_id' => $services ?? '',
                        'service_active' => $service_actives ?? '',
                        'created_at' => Carbon::parse($lead->lead_created_at)->format('F j,Y h:i A'),
                        'created_by' => array_key_exists($lead->lead_created_by, $Users) ? $Users[$lead->lead_created_by]->name : 'N/A',
                        'location'=>$lead->towns->name ?? '',
                        'child_service'=> $child_services ?? '',
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
            $records["permissions"] = [
                'edit' => Gate::allows('leads_edit'),
                'delete' => Gate::allows('leads_destroy'),
                'active' => Gate::allows('leads_active'),
                'inactive' => Gate::allows('leads_inactive'),
                'create' => Gate::allows('leads_create'),
                'convert' => Gate::allows('leads_convert'),
                'contact' => Gate::allows('contact'),
                'update_status' => Gate::allows('leads_lead_status'),
            ];
            return ApiHelper::apiDataTable($records);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
    public function status(Request $request){
        $lead = Leads::findOrFail($request->id);
        $lead->update(['active'=>$request->status]);
        return ApiHelper::apiResponse($this->success, 'Status Changed Successfully',true,["lead"=>$lead]);
    }
    private function getFiltersData($records, $fileName) {
        $filters = Filters::all(Auth::User()->id, $fileName);
        $cities = Cities::getActiveSortedFeatured(ACL::getUserCities());
        $regions = Regions::getActiveSorted(ACL::getUserRegions());
        $users = User::getAllActiveRecords(Auth::User()->account_id)->pluck('name', 'id');
        // Find Junk Lead Status to exclude
        $junk_lead_statuses = LeadStatuses::where(array(
            'account_id' => Auth::User()->account_id,
            'is_junk' => 1,
        ))->first();
        if ($junk_lead_statuses) {
            $lead_statuses = LeadStatuses::getLeadStatuses($junk_lead_statuses->id);
        } else {
            $lead_statuses = LeadStatuses::getLeadStatuses();
        }
        $Services = Services::where([
            'slug'=> 'custom',
            'parent_id'=> '0',
            'active'=> '1'
        ])->get()->pluck('name', 'id');
        $leadServices = Filters::get(Auth::User()->id, 'leads', 'service_id');
        $records['filter_values'] = [
            'Services' => $Services,
            'cities' => $cities,
            'regions' => $regions,
            'users' => $users,
            'lead_statuses' => $lead_statuses,
            'leadServices' => $leadServices,
        ];
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
     * Show the form for creating new Lead.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function create()
    {
        if (!Gate::allows('leads_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $cities = Cities::getActiveSortedFeatured(ACL::getUserCities());
        $lead_sources = LeadSources::getActiveSorted();
        $lead_statuses = LeadStatuses::getLeadStatuses();
        $Services = Services::where([
            ['slug', '=', 'custom'],
            ['parent_id', '=', '0'],
            ['active', '=', '1']
        ])->get()->pluck('name', 'id');
        // Create an empty Patient Object
        $lead = new \stdClass();
        $lead->id = null;
        //$lead->patient = new \stdClass();
        //$lead->id = null;
        $lead->name = null;
        $lead->email = null;
        $lead->phone = null;
        $lead->gender = null;
        //$lead->dob = null;
        //$lead->address = null;
        //$lead->cnic = null;
        //$lead->referred_by = null;
        $employees = User::getAllActiveRecords(Auth::User()->account_id);
        if ($employees) {
            $employees = $employees->pluck('full_name', 'id');
        } else {
            $employees = array();
        }
        /*belongs to edit for blocking some input */
        $edit_status = 0;
        /*end*/
        $leadServices = Filters::get(Auth::User()->id, 'leads', 'service_id');
        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'Services' => $Services,
            'cities' => $cities,
            'lead_sources' => $lead_sources,
            'lead_statuses' => $lead_statuses,
            'lead' => $lead,
            'leadServices' => $leadServices,
            'employees' => $employees,
            'edit_status' => $edit_status,
            'gender' => config('constants.gender_array')
        ]);
    }
    /**
     * Pop-up the form for creating new Lead.
     *
     * @return \Illuminate\Http\Response
     */
    public function make_pop()
    {
        if (!Gate::allows('leads_create')) {
            return abort(401);
        }
        $cities = Cities::getActiveSortedFeatured(ACL::getUserCities());
        $cities->prepend('Select a City', '');

        $towns = Towns::getActiveTowns();//->pluck('fullname', 'id');
        $towns->prepend('Select a Town', '');

        $lead_sources = LeadSources::getActiveSorted();
        $lead_sources->prepend('Select a Lead Source', '');

        $lead_statuses = LeadStatuses::getLeadStatuses();
        $lead_statuses->prepend('Select a Lead Status', '');

        $Services = Services::where([
            ['slug', '=', 'custom'],
            ['parent_id', '=', '0'],
            ['active', '=', '1']
        ])->get()->pluck('name', 'id');
        $Services->prepend('Select Service', '');

        // Create an empty Patient Object
        $lead = new \stdClass();
        $lead->id = null;
        //$lead->patient = new \stdClass();
        //$lead->patient->id = null;
        $lead->name = null;
        $lead->email = null;
        $lead->phone = null;
        $lead->gender = null;
        //$lead->dob = null;
        //$lead->address = null;
        //$lead->cnic = null;
        //$lead->referred_by = null;

        $employees = User::getAllActiveRecords(Auth::User()->account_id);
        if ($employees) {
            $employees = $employees->pluck('full_name', 'id');
            $employees->prepend('Select a Referrer', '');
        } else {
            $employees = array();
        }
        /*belongs to edit for blocking some input */
        $edit_status = 0;
        /*end*/
        $leadServices = null;
        return view('admin.leads.createTo', compact('Services', 'cities', 'lead_sources', 'lead_statuses', 'lead', 'leadServices', 'employees', 'edit_status', 'towns'));
    }


    /**
     * Store a newly created Lead in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        if (!Gate::allows('leads_create')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        try {
            $validator = $this->verifyFields($request);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
            }
            $data = $request->all();
            /*That make lead status as optional*/
            if (!$data['lead_status_id']) {
                $lead_default_status = LeadStatuses::where('is_default', '=', '1')->first();
                $data['lead_status_id'] = $lead_default_status->id;
            }
            /*End*/
            $data['phone'] = $data['phone'];
            if ($data['phone'] == '***********') {
                $data['phone'] = $data['old_phone'];
            }
            $data['phone'] = GeneralFunctions::cleanNumber($data['phone']);
            $data['created_by'] = Auth::user()->id;
            $data['updated_by'] = Auth::user()->id;
            $data['converted_by'] = Auth::user()->id;
            $data['account_id'] = Auth::User()->account_id;
            /*
             * ******************************************
             * Logger for both create and update for Lead
             * ******************************************
             */
            /*
             * Check if laad already exists or not
             */
            $lead = $this->existingLead($request);
            if ($request->new_lead == '1') {
                $data['created_by'] = Auth::User()->id;
                $data['updated_by'] = Auth::User()->id;
                if ($lead) {
                    return ApiHelper::apiResponse($this->error, 'Phone number is already exist.');
                } else {
                    $lead = Leads::createRecord($data, $status = "Lead");
                    $lead_services = LeadsServices::create([
                        'lead_id' => $lead->id,
                        'service_id' => $data['service_id'],
                        'child_service_id' => $data['child_service_id'],
                        'status' => 1
                    ]);
                }
            } else {
                $lead_check = Leads::with(['lead_service' => function($q) use($data){
                    $q->where(['service_id' => $data['service_id']]);
                }])
                ->where(['phone' => $data['phone'], 'account_id' => Auth::User()->account_id])
                ->first();
                if($lead_check->lead_service->count()){
                    $child_service_check = $lead_check->lead_service->whereIn('child_service_id', $data['child_service_id']);
                    //dd($child_service_check->count());
                    if($child_service_check->count()){
                        return ApiHelper::apiResponse($this->error, 'Service and child service already exist.');
                    } else {
                        $data['updated_by'] = Auth::User()->id;
                        $data['lead_status_id'] = 1;
                        $lead = Leads::updateRecord($lead_check->id, $data);
                        $lead_services = LeadsServices::create([
                            'lead_id' => $lead->id,
                            'service_id' => $data['service_id'],
                            'child_service_id' => $data['child_service_id'],
                            'status' => 1
                        ]);
                        LeadsServices::where('id', '!=', $lead_services->id)->where(['lead_id' => $lead->id])->update([
                            'status' => 0
                        ]);
                    }
                } else {
                    $data['updated_by'] = Auth::User()->id;
                    $data['lead_status_id'] = 1;
                    $lead = Leads::updateRecord($lead_check->id, $data);
                    $lead_services = LeadsServices::create([
                            'lead_id' => $lead->id,
                            'service_id' => $data['service_id'],
                            'child_service_id' => $data['child_service_id'],
                            'status' => 1
                    ]);
                    LeadsServices::where('id', '!=', $lead_services->id)->where(['lead_id' => $lead->id])->update([
                        'status' => 0
                    ]);
                }
            }
            //Appointments::where('patient_id', '=', $lead->patient_id)->update(['name' => $data['name']]);
            return ApiHelper::apiResponse($this->success, 'Record has been created successfully.');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
    private function existingLead(Request $request) {
        $phoneNo = $request->phone;
        if ($phoneNo == '***********') {
            $phoneNo = $request->old_phone;
        }
        $phone = GeneralFunctions::cleanNumber($phoneNo);
        return Leads::where(['phone' => $phone, 'account_id' => Auth::User()->account_id])->first(['id', 'phone']);
    }

    /**
     * Validate form fields
     *
     * @param \Illuminate\Http\Request $request
     * @return Validator $validator;
     */
    protected function verifyFields(Request $request)
    {
        return $validator = Validator::make($request->all(), [
            'name' => 'required',
            /*'email' => 'sometimes|nullable|email',*/
            'phone' => 'required',
            'gender' => 'required|numeric',
            'city_id' => 'required|numeric',
            /*'lead_source_id' => 'required|numeric',*/
            /*'lead_status_id' => 'required|numeric',*/
        ]);
    }

    /*
     * Send SMS on booking of Appointment
     *
     * @param: int $leadId
     * @param: string $patient_phone
     * @return: array|mixture
     */
    private function sendSMS($leadId, $phone)
    {
        return array(
            'status' => true,
        );
        // SEND SMS for Appointment Booked
        $SMSTemplate = SMSTemplates::findOrFail(2); // 2 for Leads SMS
        $preparedText = Leads::prepareSMSContent($leadId, $SMSTemplate->content);

        $Settings = Settings::getAllRecordsDictionary(Auth::User()->account_id);
        $SMSObj = array(
            'username' => $Settings[1]->data, // Setting ID 1 for Username
            'password' => $Settings[2]->data, // Setting ID 2 for Password
            'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($phone)),
            'text' => $preparedText,
            'mask' => $Settings[3]->data, // Setting ID 3 for Mask
            'test_mode' => $Settings[4]->data, // Setting ID 3 Test Mode
        );

        $response = TelenorSMSAPI::SendSMS($SMSObj);

        $SMSLog = array_merge($SMSObj, $response);
        $SMSLog['lead_id'] = $leadId;
        $SMSLog['created_by'] = Auth::user()->id;
        SMSLogs::create($SMSLog);
        // SEND SMS for Appointment Booked End

        return $response;
    }

    /**
     * Re-Send SMS for Appointment.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function send_sms($id)
    {
        if (!Gate::allows('leads_manage')) {
            return abort(401);
        }
        $lead = Leads::findOrFail($id);
        $patient = Patients::findOrFail($lead->patient_id);

        if (!$lead->msg_count) {
            // Send SMS via API
            $response = $this->sendSMS($lead->id, $patient->phone);
            if ($response['status']) {
                // Message is sent so set flag to true
                $data['msg_count'] = $lead->msg_count + 1;
                flash('SMS has been sent successfully. SMS Status: Sent')->success()->important();
            } else {
                flash('Unable to sent SMS. SMS Error: ' . $response['error_msg'])->error()->important();
            }
            $lead->update($data);
        } else {
            flash('SMS is already delivered to this lead, Can\'t deliver another SMS.')->warning()->important();
        }

        return redirect()->route('admin.leads.index');
    }

    /**
     * Show Lead detail.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function detail($id)
    {
        if (!Gate::allows('leads_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $lead = Leads::with( 'lead_comments.user', 'patient', 'towns', 'city', 'lead_source', 'lead_status', 'service')->find($id);
        $lead->phone = GeneralFunctions::prepareNumber4Call($lead->patient->phone);
        $lead->gender = Config::get('constants.gender_array')[$lead->patient->gender];
        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'lead' => $lead
        ]);
    }

    /**
     * Store a newly created Lead in storage.
     *
     * @param \App\Http\Requests\Admin\StoreUpdateLeadCommentsRequest $request
     * @return \Illuminate\Http\Response
     */
    public function comment_store(StoreUpdateLeadCommentsRequest $request)
    {
        if (!Gate::allows('leads_manage')) {
            return abort(401);
        }
        $data = $request->all();
        // Set Created by
        $data['created_by'] = Auth::user()->id;
        $lead = LeadComments::create($data);
        flash('Comment has been added successfully.')->success()->important();
        return redirect()->back();
    }
    public function LoadChildServices(Request $request)
    {
        try {
            if ($request->serviceId) {
                $child_services = Services::where(['parent_id'=>$request->serviceId,'active'=>1])->get();
                if ($child_services) {
                    $child_services = $child_services->pluck("name", "id");
                }
                $lead = Leads::with('lead_service')->where(['id' => $request->leadId])->first();
            }
            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'dropdown' => $child_services,
                'lead_child_service' => $lead->lead_service,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
    /**
     * Show the form for editing Lead.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        if (!Gate::allows('leads_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $lead = Leads::getData($id);//dd($lead->lead_service);
        if ($lead == null) {
            return ApiHelper::apiResponse($this->success, 'Resource not found', false);
        }
        $cities = Cities::getActiveSortedFeatured(ACL::getUserCities());
        $locations = Locations::where(['active'=>1,'city_id'=>$lead->city_id])->get()->pluck('name', 'id');
        $lead_sources = LeadSources::getActiveSorted();
        $lead_statuses = LeadStatuses::getLeadStatuses();
        $Services = Services::where([
            'slug' => 'custom',
            'parent_id' => '0',
            'active' =>'1'
        ])->get()->pluck('name', 'id');
        $child_services = Services::whereIn('id', $lead->lead_service->pluck('child_service_id')->toArray())->where([
            'slug' => 'custom',
            'active' =>'1'
        ])->get()->pluck('name', 'id');
        $employees = User::getAllActiveRecords(Auth::User()->account_id);
        if ($employees) {
            $employees = $employees->pluck('full_name', 'id');
        } else {
            $employees = array();
        }
        /*belongs to edit for blocking some input */
        $edit_status = 1;
        /*end*/
        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'Services' => $Services,
            'child_services' => $child_services,
            'lead' => $lead,
            'locations' => $locations,
            'cities' => $cities,
            'lead_sources' => $lead_sources,
            'lead_statuses' => $lead_statuses,
            'employees' => $employees,
            'edit_status' => $edit_status,
            'gender' => config('constants.gender_array')
        ]);
    }

    /**
     * Update Lead in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        if (!Gate::allows('leads_edit')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        $validator = $this->verifyFields($request);
        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }
        $lead = Leads::findOrFail($id);
        /* if($request->input('phone') == '***********'){
            $request->merge(['phone' => $request->input('old_phone')]);
        }
        $request->request->remove('old_phone'); */
        // Get all request data into a var
        $data = $request->all();
        //dd($data);
        //$data['phone'] = GeneralFunctions::cleanNumber($data['phone']);
        $data['updated_by'] = Auth::user()->id;
        $data['account_id'] = Auth::User()->account_id;
        /*
         * ******************************************
         * Logger for both create and update for Lead
         * ******************************************
         */
        /*
         * Check if laad already exists or not
         */
        foreach($request->service_id as $service){
            $lead_check = Leads::with(['lead_service' => function($q) use($service){
                $q->where(['service_id' => $service]);
            }])
            ->where(['id' => $id, 'account_id' => Auth::User()->account_id])
            ->first();
            //dd($lead_check->lead_service->count(), $service);
            if($lead_check->lead_service->count()){
                foreach($request->child_service_id as $child_service){
                    $child_service_check = $lead_check->lead_service->whereIn('child_service_id', [$child_service]);
                    //dd($lead_check->lead_service, $child_service_check->count() == 0, $child_service, $data);
                    if($child_service_check->count() == 0){
                        $data['updated_by'] = Auth::User()->id;
                        //$lead = Leads::where(['id' => $id])->update($data);
                        $lead = Leads::where(['id' => $id])->update([
                            'name' => $data['name'],
                            'gender' => $data['gender'],
                            'city_id' => $data['city_id'],
                            'location_id' => $data['location_id'],
                            'lead_source_id' => $data['lead_source_id'],
                            'lead_status_id' => $data['lead_status_id'],
                            'referred_by' => $data['referred_by'],
                        ]);
                        $lead_services = LeadsServices::create([
                            'lead_id' => $id,
                            'service_id' => $service,
                            'child_service_id' => $child_service,
                            'status' => 1
                        ]);
                        LeadsServices::where('id', '!=', $lead_services->id)->where(['lead_id' => $id])->update([
                            'status' => 0
                        ]);


                    }
                }
            } else {
                $data['updated_by'] = Auth::User()->id;
                //$lead = Leads::updateRecord($id, $data);
                $lead = Leads::where(['id' => $id])->update([
                    'name' => $data['name'],
                    'gender' => $data['gender'],
                    'city_id' => $data['city_id'],
                    'location_id' => $data['location_id'],
                    'lead_source_id' => $data['lead_source_id'],
                    'lead_status_id' => $data['lead_status_id'],
                    'referred_by' => $data['referred_by'],
                ]);
                foreach($request->child_service_id as $child_service){
                    $lead_services = LeadsServices::create([
                        'lead_id' => $id,
                        'service_id' => $service,
                        'child_service_id' => $child_service,
                        'status' => 1
                    ]);
                    LeadsServices::where('id', '!=', $lead_services->id)->where(['lead_id' => $id])->update([
                        'status' => 0
                    ]);
                }

            }
        }
        return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
    }


    /**
     * Remove Lead from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            if (!Gate::allows('leads_destroy')) {
                return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
            }
            $lead = Leads::find($id);
            $lead->delete();
            return ApiHelper::apiResponse($this->success, 'Record has been deleted successfully.');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Inactive Record from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function inactive($id)
    {
        if (!Gate::allows('leads_inactive')) {
            return abort(401);
        }
        $lead = Leads::findOrFail($id);
        $lead->update(['active' => 0]);
        flash('Record has been inactivated successfully.')->success()->important();
        return redirect()->route('admin.leads.index');
    }

    /**
     * Inactive Record from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function active($id)
    {
        if (!Gate::allows('leads_active')) {
            return abort(401);
        }
        $lead = Leads::findOrFail($id);
        $lead->update(['active' => 1]);
        flash('Record has been inactivated successfully.')->success()->important();
        return redirect()->route('admin.leads.index');
    }

    /**
     * Load all Lead Statuses.
     *
     * @param Request $request
     */
    public function showLeadStatuses(Request $request)
    {
        if (!Gate::allows('leads_lead_status')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        try {
            $lead_statuses_Pdata = LeadStatuses::getLeadStatuses();
            $lead = Leads::find($request->get('id'));
            $lead_status = LeadStatuses::where('id', '=', $lead->lead_status_id)->first();
            $lead_status_comment = LeadComments::where('lead_id', '=', $lead->id)->get();
            if ($lead_status->parent_id == 0) {
                $lead_status_parent = DB::table('lead_statuses')->where('id', '=', $lead->lead_status_id)->first();
                $lead_status_chalid = 'null';
            } else {
                $lead_status_chalid = DB::table('lead_statuses')->where('id', '=', $lead->lead_status_id)->first();
                $lead_status_parent = DB::table('lead_statuses')->where('id', '=', $lead_status_chalid->parent_id)->first();
            }
            $lead_statuses_Cdata = DB::table('lead_statuses')->where('parent_id', '=', $lead_status_parent->id)->get();
            if (count($lead_statuses_Cdata) < 1) {
                $lead_statuses_Cdata = 'nothing';
            }
            return ApiHelper::apiResponse($this->success, 'Record Found', true, [
                'lead' => $lead,
                'lead_statuses_Pdata' => $lead_statuses_Pdata,
                'lead_statuses_Cdata' => $lead_statuses_Cdata,
                'lead_status_parent' => $lead_status_parent,
                'lead_status_chalid' => $lead_status_chalid,
                'lead_status_comment' => $lead_status_comment
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Check parent data to check child in pop up field.
     *
     * @param Request $request
     */
    public function LeadStatusespopcheck(Request $request)
    {
        $lead_status = LeadStatuses::find($request->id);
        $lead_status_chalid = LeadStatuses::where('parent_id', '=', $lead_status->id)->get();
        $myarray = ['d' => $lead_status_chalid, 'lead_status' => $lead_status];
        return response()->json($myarray);
    }

    /**
     * Check child data to check comment box in pop up field.
     *
     * @param Request $request
     */
    public function LeadStatusChildpopcheck(Request $request)
    {
        $lead_status_chalid = LeadStatuses::find($request->id);
        $lead_status2 = DB::table('lead_statuses')->where('id', '=', $lead_status_chalid->parent_id)->first();
        $myarray = ['d' => $lead_status_chalid, 'lead_status2' => $lead_status2];
        return response()->json($myarray);
    }

    /**
     * Update Lead Status
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeLeadStatuses(Request $request)
    {
        try {
            $data = $request->all();
            $lead = Leads::find($request->get('id'));
            //Always save child id because our code mange it for parent id
            if ($request->get('lead_status_chalid_id') != null) {
                DB::table('leads')
                    ->where('id', $lead->id)
                    ->update([
                        'lead_status_id' => $data['lead_status_chalid_id'],
                        'converted_by' => Auth::User()->id
                    ]);
            } else {
                Leads::where('id', $lead->id)
                    ->update([
                        'lead_status_id' => $data['lead_status_parent_id'],
                        'converted_by' => Auth::User()->id
                    ]);
            }
            //End
            $data['created_by'] = Auth::User()->id;
            $data['lead_id'] = $lead->id;
            //Check the comment belong to which values
            if ($request->get('comment1') == null) {
                $data['comment'] = $request->comment2;
            }
            if ($request->get('comment2') == null) {
                $data['comment'] = $request->comment1;
            }
            if ($request->get('comment2') == null && $request->get('comment1') == null) {
                return ApiHelper::apiResponse($this->success, 'Status updated successfully!');
            }
            LeadComments::create($data);
            return ApiHelper::apiResponse($this->success, 'Status updated successfully!');
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /**
     * Load all Lead Statuses.
     *
     * @param Request $request
     */
    public function loadLeadStatuses(Request $request)
    {
        $lead_statuses = LeadStatuses::getActiveOnly();
        $data = array();
        if ($lead_statuses) {
            foreach ($lead_statuses as $lead_status) {
                $data[] = array(
                    'value' => $lead_status->id,
                    'text' => $lead_status->name,
                );
            }
        }
        return response()->json($data);
    }

    /**
     * Store Lead Status.
     *
     * @param Request $request
     */
    public function saveLeadStatus(Request $request)
    {
        if (!Gate::allows('leads_manage')) {
            return response()->json(array('status' => 0));
        } else {
            $id = $request->get('pk');;
            $lead_status_id = $request->get('value');;

            // Check if Lead found or not
            $lead = Leads::find($id);
            if (!$lead) {
                return response()->json(array('status' => 0));
            } else {
                $data = array(
                    'lead_status_id' => $lead_status_id,
                    'converted_by' => Auth::User()->id
                );
                $lead->update($data);
                /*
                     * Prepare Default Lead Status ID
                     */
                // Process Lead Status
                $DefaultJunkLeadStatus = LeadStatuses::where(array(
                    'account_id' => Auth::User()->account_id,
                    'is_junk' => 1,
                ))->first();
                if ($DefaultJunkLeadStatus) {
                    $default_junk_lead_status_id = $DefaultJunkLeadStatus->id;
                } else {
                    $default_junk_lead_status_id = Config::get('constants.lead_status_junk');
                }

                if ($lead_status_id != $default_junk_lead_status_id) {
                    if (!$lead->msg_count) {
                        $patient = Patients::find($id);
                        // Lead Status is not junk, Send SMS now
                        $response = $this->sendSMS($lead->id, $patient->phone);
                        if ($response['status']) {
                            // Message is sent so set flag to true
                            $data['msg_count'] = $lead->msg_count + 1;
                        }
                    }
                }
                $lead->update($data);
                return response()->json(array('status' => 1));
            }
        }
    }

    /**
     * Load all Treatments.
     *
     * @param Request $request
     */
    public function loadTreatments(Request $request)
    {
        $services = Services::getActiveOnly();
        $data = array();
        if ($services) {
            foreach ($services as $service) {
                $data[] = array(
                    'value' => $service->id,
                    'text' => $service->name,
                );
            }
        }
        return response()->json($data);
    }

    /**
     * Store Lead Status.
     *
     * @param Request $request
     */
    public function saveTreatment(Request $request)
    {
        if (!Gate::allows('leads_manage')) {
            return response()->json(array('status' => 0));
        } else {
            $id = $request->get('pk');;
            $service_id = $request->get('value');;

            // Check if Lead found or not
            $lead = Leads::find($id);
            if (!$lead) {
                return response()->json(array('status' => 0));
            } else {
                $data = array(
                    'service_id' => $service_id
                );
                $lead->update($data);

                return response()->json(array('status' => 1));
            }
        }
    }

    /**
     * Load all Lead Sources.
     *
     * @param Request $request
     */
    public function loadLeadSources(Request $request)
    {
        $lead_sources = LeadSources::getActiveOnly();
        $data = array();
        if ($lead_sources) {
            foreach ($lead_sources as $lead_source) {
                $data[] = array(
                    'value' => $lead_source->id,
                    'text' => $lead_source->name,
                );
            }
        }
        return response()->json($data);
    }

    /**
     * Store Lead Status.
     *
     * @param Request $request
     */
    public function saveLeadSource(Request $request)
    {
        if (!Gate::allows('leads_manage')) {
            return response()->json(array('status' => 0));
        } else {
            $id = $request->get('pk');;
            $lead_source_id = $request->get('value');;

            // Check if Lead found or not
            $lead = Leads::find($id);
            if (!$lead) {
                return response()->json(array('status' => 0));
            } else {
                $lead->update(['lead_source_id' => $lead_source_id]);
                return response()->json(array('status' => 1));
            }
        }
    }

    /**
     * Load all Lead Citys.
     *
     * @param Request $request
     */
    public function loadCities(Request $request)
    {
        if (!Gate::allows('leads_city')) {
            return abort(401);
        }
        $cities = Cities::getActiveOnly(ACL::getUserCities(), Auth::User()->account_id);
        $data = array();
        if ($cities) {
            foreach ($cities as $citie) {
                $data[] = array(
                    'value' => $citie->id,
                    'text' => $citie->name,
                );
            }
        }
        return response()->json($data);
    }

    /**
     * Store Lead Status.
     *
     * @param Request $request
     */
    public function saveCity(Request $request)
    {
        if (!Gate::allows('leads_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        } else {
            $id = $request->get('pk');
            $city_id = $request->get('value');
            // Check if Lead found or not
            $citie = Cities::find($city_id);
            $lead = Leads::find($id);
            if (!$lead || !$citie) {
                return ApiHelper::apiResponse($this->success, 'Resource not found.', false);
            }
            $lead->update([
                'city_id' => $city_id,
                'region_id' => $citie->region_id
            ]);
            return ApiHelper::apiResponse($this->success, 'City updated successfully.', true, [
                'city' => $citie->name ?? ''
            ]);
        }
    }

    /**
     * Store Lead Status.
     *
     * @param Request $request
     */
    public function importLeads(Request $request)
    {
        if (!Gate::allows('leads_import')) {
            flash('You are not authorized to access this resource.')->error()->important();
            return redirect()->route('admin.leads.index');
        }
        return view('admin.leads.import');
    }

    /**
     * Update Lead in storage.
     *
     * @param \App\Http\Requests\Admin\FileUploadLeadsRequest $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */

    public function uploadLeads(FileUploadLeadsRequest $request)
    {
        if (!Gate::allows('leads_import')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        try {
            if ($request->hasfile('leads_file')) {
                $File = $request->file('leads_file');
                $File->store('public/files');
                $fullPath = public_path('storage/files') . DIRECTORY_SEPARATOR . $File->hashName();
                $SpreadSheet = IOFactory::load($fullPath);
                \File::delete($fullPath);
                // Read File and dump data
                $SheetData = $SpreadSheet->getActiveSheet(0)->toArray(null, true, true, true);
                if (count($SheetData)) {
                    if (
                        isset($SheetData[1])
                        && (
                            trim(strtolower($SheetData[1]['A'])) == 'full name' &&
                            trim(strtolower($SheetData[1]['B'])) == 'email' &&
                            trim(strtolower($SheetData[1]['C'])) == 'phone' &&
                            trim(strtolower($SheetData[1]['D'])) == 'gender' &&
                            trim(strtolower($SheetData[1]['E'])) == 'city' &&
                            trim(strtolower($SheetData[1]['I'])) == 'centre'
                        )
                    ) {
                        $Cities = Cities::where(['account_id' => Auth::User()->account_id])->get()->pluck('id', 'name');
                        $RegionCities = Cities::getAllRecordsDictionary(Auth::User()->account_id);
                        $leadSources = LeadSources::where(['account_id' => Auth::User()->account_id])->get()->pluck('id', 'name');
                        $LeadStatuses = LeadStatuses::where(['account_id' => Auth::User()->account_id])->get()->pluck('id', 'name');
                        $Treatments = Services::where(['account_id' => Auth::User()->account_id])->get()->pluck('id', 'name');
                        $child_Services = Services::where(['account_id' => Auth::User()->account_id])->where('parent_id','!=',0)->get()->pluck('id', 'name');
                        $Locations = Locations::where(['account_id' => Auth::User()->account_id])->get()->pluck('id', 'name');
                        // Array to hold phone numbers which will be used to find duplicates if any
                        $dupPhone_list = array();
                        $dupPhones = array();
                        $allPatientMapping = array();
                        /*
                         * This array will contain all those patients
                         * which are gonna be insert into database.
                         * Why we made this array?
                         * We want to avoid duplicates.
                         */
                        $piplined_patients = array();
                        // Iterate over the data
                        foreach ($SheetData as $SingleRow) {
                            // Provided Sheet columns should match
                            if (
                                (
                                    trim(strtolower($SingleRow['A'])) == 'full name' ||
                                    trim(strtolower($SingleRow['A'])) == 'full_name'
                                )
                                &&
                                trim(strtolower($SingleRow['B'])) == 'email' &&
                                (
                                    trim(strtolower($SingleRow['C'])) == 'phone_number' ||
                                    trim(strtolower($SingleRow['C'])) == 'phone'
                                )
                            ) {
                                // Row contains headers so ignore this line
                                continue;
                            }
                            if (
                                trim(strtolower($SingleRow['A'])) == '' ||
                                trim(strtolower($SingleRow['A'])) == null ||
                                trim(strtolower($SingleRow['C'])) == '' ||
                                trim(strtolower($SingleRow['C'])) == null
                            ) {
                                /*
                                 * If Full Name and Phone are empty, Skip these records
                                 */
                                continue;
                            }
                            // Process Phone Number
                            $dupPhone_list[] = GeneralFunctions::cleanNumber(trim($SingleRow['C']));
                        }
                        /*
                         * Step A: Start
                         * Find patients who are not in system and create them
                         */
                        if (count($dupPhone_list)) {
                            // Find duplicate records in System.
                            $dupPhones = Leads::whereIn('phone', $dupPhone_list)
                            ->where('account_id', Auth::User()->account_id)
                            ->select('phone', 'id')
                            ->get()
                            ->keyBy('phone');
                            if ($dupPhones) {
                                $dupPhones = $dupPhones->toArray();
                            } else {
                                // Restore Old state again
                                $dupPhones = array();
                            }
                            $newPatientPhones = array(); /* New Patient Phones Array */
                            $found_patients = Leads::whereIn('phone', $dupPhone_list)
                            ->where('account_id', Auth::User()
                            ->account_id)->select('phone')
                            ->get()
                            ->pluck('phone');
                            if ($found_patients) {
                                $newPatientPhones = array_diff($dupPhone_list, $found_patients->toArray());
                            }
                            // New Patients found so this is time to create new patients into system
                            // if (count($newPatientPhones)) {
                            //     $newPatientsData = array(); /* New Patients Array */
                            //     // Iterate over the data
                            //     foreach ($SheetData as $SingleRow) {
                            //         // Provided Sheet columns should match
                            //         if (
                            //             (
                            //                 trim(strtolower($SingleRow['A'])) == 'full name' ||
                            //                 trim(strtolower($SingleRow['A'])) == 'full_name'
                            //             )
                            //             &&
                            //             trim(strtolower($Sing$piplined_patients[] = $phone;leRow['B'])) == 'email' &&
                            //             (
                            //                 trim(strtolower($SingleRow['C'])) == 'phone_number' ||
                            //                 trim(strtolower($SingleRow['C'])) == 'phone'
                            //             )
                            //         ) {
                            //             // Row contains headers so ignore this line
                            //             continue;
                            //         }
                            //         if (
                            //             trim(strtolower($SingleRow['A'])) == '' ||
                            //             trim(strtolower($SingleRow['A'])) == null ||
                            //             trim(strtolower($SingleRow['C'])) == '' ||
                            //             trim(strtolower($SingleRow['C'])) == null
                            //         ) {
                            //             /*
                            //             * If Full Name and Ph$piplined_patients[] = $phone;one are empty, Skip these records
                            //             */
                            //             continue;
                            //         }
                            //         // Process Phone Number
                            //         $phone = GeneralFunctions::cleanNumber(trim($SingleRow['C']));
                            //         // If Phone found in new customers array then prepare data
                            //         if (in_array($phone, $newPatientPhones)) {
                            //             // Process Gender
                            //             $gender = 0;
                            //             if (trim(strtolower($SingleRow['D'])) == 'male') {
                            //                 $gender = 1; // 1 for Male, Check constants.php
                            //             } else if (trim(strtolower($SingleRow['D'])) == 'female') {
                            //                 $gender = 2; // 2 for Female, Check constants.php
                            //             }
                            //             /*
                            //             * If patient already exists in Piplined patients
                            //             * then skip this record to avoid duplicate
                            //             */
                            //             if (in_array(
                            //                 $phone, $piplined_patients
                            //             )) {
                            //                 // Duplicate Lead is found so skip it.
                            //                 continue;
                            //             }
                            //             $newPatientsData[] = array(
                            //                 'name' => $SingleRow['A'],
                            //                 'email' => $SingleRow['B'],
                            //                 'phone' => $phone,
                            //                 'gender' => $gender,
                            //                 'user_type_id' => Config::get('constants.patient_id'),
                            //                 'account_id' => Auth::User()->account_id,
                            //                 'created_at' => Carbon::now(),
                            //                 'updated_at' => Carbon::now(),
                            //             );
                            //             $piplined_patients[] = $phone;
                            //         }
                            //     }
                            //     // Create Patient Profiles now
                            //     Leads::insert($newPatientsData);
                            // }$piplined_patients[] = $phone;
                            // $allPatientMapping = Leads::whereIn('phone', $dupPhone_list)->select('id', 'phone')->get()->keyBy('phone');
                            // if (count($allPatientMapping)) {
                            //     $allPatientMapping = $allPatientMapping->toArray();
                            // } else {
                            //     $allPatientMapping = array();
                            // }
                        }
                        /*
                         * Step A: End
                         */
                        // Var to hold all Leads Data
                        $LeadData = array();
                        /*
                         * Create Leads based on following criteria
                         *
                         * Case 1. If 'Update Existign Record' is not checked
                         * ---------------------------------------------------
                         * - If lead is found then skip it
                         * - If Treatment is not provided then this will be skipped
                         *
                         * Case 2. If 'Update Existign Record' is checked
                         * ---------------------------------------------------
                         * - If Treatment is not provided then this will be skipped
                         *
                         * a. 'Skip Leads Statuses' is not checked
                         * a.i If lead is found update it with Lead Status
                         *
                         * b. 'Skip Leads Statuses' is not checked
                         * b.i If lead is found update it without Lead Status
                         */

                        $allLeadsMapping = Leads::whereIn('phone', $dupPhone_list)
                        ->where('leads.account_id', Auth::User()->account_id)->select('phone')->get()->keyBy('phone');
                        if (count($allLeadsMapping)) {
                            $allLeadsMapping = $allLeadsMapping->toArray();
                        } else {
                            $allLeadsMapping = array();
                        }
                        /*
                         * This array will contain all those laads
                         * which are gonna be insert into Leads.
                         * Why we made this array?
                         * We want to avoid duplicates.
                         */
                        $piplined_leads = array();
                        /*
                         * Prepare Default Lead Status ID
                         */
                        // Process Lead Status
                        $DefaultLeadStatus = LeadStatuses::where(array(
                            'account_id' => Auth::User()->account_id,
                            'is_default' => 1,
                        ))->first();
                        if ($DefaultLeadStatus) {
                            $default_lead_status_id = $DefaultLeadStatus->id;
                        }else {
                            $default_lead_status_id = Config::get('constants.lead_status_open');
                        }
                        // Iterate over the data
                        $count = 0;
                        $items = [];
                        foreach ($SheetData as $index => $SingleRow) {
                            // Provided Sheet columns should match
                            if (
                                (
                                    trim(strtolower($SingleRow['A'])) == 'full name' ||
                                    trim(strtolower($SingleRow['A'])) == 'full_name'
                                )
                                &&
                                trim(strtolower($SingleRow['B'])) == 'email' &&
                                (
                                    trim(strtolower($SingleRow['C'])) == 'phone_number' ||
                                    trim(strtolower($SingleRow['C'])) == 'phone'
                                )
                            ) {
                                // Row contains headers so ignore this line
                                continue;
                            }
                            if (
                                trim(strtolower($SingleRow['A'])) == '' ||
                                trim(strtolower($SingleRow['A'])) == null ||
                                trim(strtolower($SingleRow['C'])) == '' ||
                                trim(strtolower($SingleRow['C'])) == null
                            ) {
                                /*
                                 * If Full Name and Phone are empty, Skip these records
                                 */
                                continue;
                            }
                            // Process Phone Number
                            $phone = GeneralFunctions::cleanNumber(trim($SingleRow['C']));
                            $gender = 0;
                            if (trim(strtolower($SingleRow['D'])) == 'male') {
                                $gender = 1; // 1 for Male, Check constants.php
                            } else if (trim(strtolower($SingleRow['D'])) == 'female') {
                                $gender = 2; // 2 for Female, Check constants.php
                            }
                            // Process City
                            $city_id = null;
                            $region_id = null;
                            $city = trim(strtolower($SingleRow['E']));
                            if ($Cities && $city) {
                                foreach ($Cities as $CityName => $CityId) {
                                    if ($city == trim(strtolower($CityName))) {
                                        $city_id = $CityId;
                                        $region_id = $RegionCities[$CityId]->region_id;
                                    }
                                }
                            }
                            // Process Lead Source
                            $lead_source_id = Config::get('constants.lead_source_social_media');
                            if (isset($SingleRow['F'])) {
                                $lead_source = trim(strtolower($SingleRow['F']));
                            } else {
                                $lead_source = null;
                            }
                            if ($leadSources && $lead_source) {
                                foreach ($leadSources as $SrcName => $SrcId) {
                                    if (trim(strtolower($lead_source)) == trim(strtolower($SrcName))) {
                                        $lead_source_id = $SrcId;
                                    }
                                }
                                if (!$lead_source_id) {
                                    $lead_source_id = Config::get('constants.lead_source_social_media');
                                }
                            }
                            // Process Lead Status
                            $lead_status_id = $default_lead_status_id;
                            if (isset($SingleRow['G'])) {
                                $lead_status = trim(strtolower($SingleRow['G']));
                            } else {
                                $lead_status = null;
                            }
                            if ($LeadStatuses && $lead_status) {
                                foreach ($LeadStatuses as $StatusName => $StatusId) {
                                    if (trim(strtolower($lead_status)) == trim(strtolower($StatusName))) {
                                        $lead_status_id = $StatusId;
                                    }
                                }
                                if (!$lead_status_id) {
                                    $lead_status_id = $default_lead_status_id;
                                }
                            }
                            /*
                             * Process Treatment, If Treatment not found skip this record
                             */
                            $service_id = null;
                            $service = null;
                            if (isset($SingleRow['H'])) {
                                $service = trim(strtolower($SingleRow['H']));
                            }
                            $childservice = 0;
                            if ($SingleRow['J']) {
                                $childservice = trim(strtolower($SingleRow['J']));
                            }
                            if(trim(strtolower($SingleRow['I']))==null){
                                $SingleRow['I']='Empty';
                            }
                            $location_id=null;
                            if (isset($SingleRow['I'])) {
                                $location = trim(strtolower($SingleRow['I']));
                            } else {
                                $location = null;
                            }
                            if ($Locations && $location) {
                                foreach ($Locations as $LocationName => $LocationId) {
                                    if ($location == trim(strtolower($LocationName))) {
                                        $location_id = $LocationId;
                                    }
                                }
                            }
                            if ($Treatments && $service) {
                                foreach ($Treatments as $Name => $Id) {
                                    if (trim(strtolower($service)) == trim(strtolower($Name))) {
                                        $service_id = $Id;
                                    }
                                }
                            }
                            $child_service_id=null;
                            if ($child_Services && $childservice) {
                                foreach ($child_Services as $childName => $childId) {
                                    if (trim(strtolower($childservice)) == trim(strtolower($childName))) {
                                        $child_service_id = $childId;
                                        $find_parent = Services::whereId($child_service_id)->first();
                                        if($service_id){
                                            $find_parent2 = Services::whereId($service_id)->first();
                                            if($find_parent2->id != $find_parent->parent_id ){
                                                $service_id= $find_parent->parent_id;
                                            }else{
                                                $service_id= $find_parent->parent_id;
                                            }
                                        }else{
                                            $service_id= $find_parent->parent_id;
                                        }

                                    }
                                }
                            }
                            // Treatment ID is not exist and leads are exist, lets skip this record
                            // if (!$service_id && array_key_exists($phone, $allLeadsMapping)) {
                            //     // Skip this record.
                            //     continue;
                            // }
                            /*
                             * Check cases mentioned above
                             */
                            if (array_key_exists($phone, $allLeadsMapping)) {
                                if (Leads::where(array(
                                    'phone' => $phone,
                                    'service_id' => $service_id
                                ))->count()) {
                                    if ($request->get("update_records") != '1' && $request->get("update_status") != '1') {
                                        /*
                                         * update_records' is not checked
                                         * Skip this entire record
                                         */
                                        continue;
                                    }elseif($request->update_status == '1' && $request->get("update_records") != '1'){
                                        $update_lead = array();
                                        $update_lead['lead_status_id'] = $lead_status_id;
                                        Leads::where([ 'phone' => $phone,
                                        'service_id' => $service_id])->update(['lead_status_id' => $lead_status_id]);
                                       continue;
                                    } else {
                                        /*
                                         * update_records' is checked
                                         * update records nows
                                         */
                                        $gender = 0;
                                        if (trim(strtolower($SingleRow['D'])) == 'male') {
                                            $gender = 1; // 1 for Male, Check constants.php
                                        } else if (trim(strtolower($SingleRow['D'])) == 'female') {
                                            $gender = 2; // 2 for Female, Check constants.php
                                        }
                                        /* Patients::updateOrCreate(array(
                                            'id' => $allPatientMapping[$phone]['id']
                                        ), array(
                                            'name' => trim($SingleRow['A']),
                                            'email' => trim($SingleRow['B']),
                                            'phone' => $phone,
                                            'gender' => $gender,
                                            'user_type_id' => Config::get('constants.patient_id'),
                                            'account_id' => Auth::User()->account_id,
                                            'created_at' => Carbon::now(),
                                            'updated_at' => Carbon::now(),
                                        )); */
                                        $update_lead = array(
                                            'name' => trim($SingleRow['A']),
                                            'email' => trim($SingleRow['B']),
                                            'gender' => $gender,
                                            'phone' => $phone,
                                            'city_id' => $city_id,
                                            'region_id' => $region_id,
                                            'lead_source_id' => $lead_source_id,
                                            'service_id' => $service_id,
                                            'child_service_id'=>$child_service_id ?? '',
                                            'created_by' => Auth::User()->id,
                                            'updated_by' => Auth::User()->id,
                                            'converted_by' => Auth::User()->id,
                                            'created_at' => Carbon::now(),
                                            'updated_at' => Carbon::now(),
                                            'account_id' => Auth::User()->account_id,
                                            'location_id'=>$location_id ?? ''
                                        );
                                        /*
                                         * 'skip_lead_statuses' is not checked
                                         * Update Lead Status as well
                                         */
                                        if ($request->get('skip_lead_statuses') != '1') {
                                            $update_lead['lead_status_id'] = $lead_status_id;
                                        }
                                        Leads::updateOrCreate(array(
                                            'phone' => $phone,
                                            'service_id' => $service_id
                                        ), $update_lead);
                                        continue;
                                    }
                                }
                            }
                            if (in_array($phone, $newPatientPhones)) {
                                /*
                                * If lead already exists in Piplined lead
                                * then skip this lead to avoid duplicate
                                */
                                if (in_array($phone, $piplined_leads) && in_array($service_id, $piplined_leads))
                                {
                                    continue;
                                }
                                /* if (in_array(
                                    $phone . "##" . $service_id, $piplined_leads
                                )) {
                                    // Duplicate Lead is found so skip it.
                                    continue;
                                } */

                                $LeadData[] = array(
                                    //'patient_id' => $allPatientMapping[$phone]['id'],
                                    'name' => trim($SingleRow['A']),
                                    'email' => trim($SingleRow['B']),
                                    'phone' => $phone,
                                    'gender' => $gender,
                                    'city_id' => $city_id,
                                    'region_id' => $region_id,
                                    'lead_source_id' => $lead_source_id,
                                    'lead_status_id' => 1,
                                    'service_id' => $service_id,
                                    'child_service_id'=>$child_service_id ?? '',
                                    'created_by' => Auth::User()->id,
                                    'updated_by' => Auth::User()->id,
                                    'converted_by' => Auth::User()->id,
                                    'created_at' => Carbon::now(),
                                    'updated_at' => Carbon::now(),
                                    'account_id' => Auth::User()->account_id,
                                    'location_id'=>$location_id ?? ''
                                );
                                $piplined_leads['phone'] = $phone;
                                $piplined_leads['service_id'] = $service_id;

                                /* $user_info_L = User::where('id', $allPatientMapping[$phone]['id'])->first();
                                $job_status_gender = 0;
                                $job_status_email = 0;
                                if ($user_info_L) {
                                    if ($user_info_L->gender) {
                                        $job_status_gender = 0;
                                        $gender = $user_info_L->gender;
                                    } else {
                                        $job_status_gender = 1;
                                        if (trim(strtolower($SingleRow['D'])) == 'male') {
                                            $gender = 1;
                                        } else if (trim(strtolower($SingleRow['D'])) == 'female') {
                                            $gender = 2;
                                        }
                                    }
                                    if ($user_info_L->email) {
                                        $job_status_email = 0;
                                        $email = $user_info_L->email;
                                    } else {
                                        $job_status_email = 1;
                                        $email = $SingleRow['B'];
                                    }
                                    if ($job_status_email == 1 || $job_status_gender == 1) {
                                        $job = (new LeadUserUpdateEmailGender([
                                            'user_id' => $allPatientMapping[$phone]['id'],
                                            'email' => $email,
                                            'gender' => $gender,
                                        ]))->delay(Carbon::now()->addSeconds(2));
                                        dispatch($job);
                                    }
                                } */
                            }
                        }
                        // If Get some recors insert them now
                        if (count($LeadData)) {
                            Leads::insert($LeadData);
                        }
                        // Invalid data is provided
                        return ApiHelper::apiResponse($this->success, 'Leads has been imported. Created: ' . count($LeadData) . ', Duplicates: ' . count($dupPhones));
                    } else {
                        return ApiHelper::apiResponse($this->success, 'Invalid data provided. Pattern should: Full Name, Email, Phone, Gender, City, Lead Source, Lead Status');
                    }
                } else {
                    return ApiHelper::apiResponse($this->success, 'No input file specified..');
                }
                return ApiHelper::apiResponse($this->success, 'No input file specified..', false);
            }
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
    /**
     * Display a listing of Junk Lead.
     *
     * @return \Illuminate\Http\Response
     */
    public function junk()
    {
        if (!Gate::allows('leads_junk')) {
            return abort(401);
        }
        return view('admin.leads.junk');
    }

    /**
     * Display a listing of Lead_statuse.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function junkDatatable(Request $request)
    {
        /*This function is not using, i handled both cases using just dattable function*/
        $where = array();
        /*
         * Reset form filter is applied
         */
        $apply_filter = false;
        if ($request->get('action')) {
            $action = $request->get('action');
            if (isset($action[0]) && $action[0] == 'filter_cancel') {
                Filters::flush(Auth::User()->id, 'leads_junk');
            } else if ($action == 'filter') {
                $apply_filter = true;
            }
        }
        if ($request->get('order')) {
            $orderColumn = $request->get('order')[0]['column'];
            $orderBy = $request->get('columns')[$orderColumn]['data'];
            if ($orderBy == 'created_at') {
                $orderBy = 'leads.created_at';
            }
            $order = $request->get('order')[0]['dir'];
            Filters::put(Auth::User()->id, 'leads_junk', 'order_by', $orderBy);
            Filters::put(Auth::User()->id, 'leads_junk', 'order', $order);
        } else {
            if (
                Filters::get(Auth::User()->id, 'leads_junk', 'order_by')
                && Filters::get(Auth::User()->id, 'leads_junk', 'order')
            ) {
                $orderBy = Filters::get(Auth::User()->id, 'leads_junk', 'order_by');
                $order = Filters::get(Auth::User()->id, 'leads_junk', 'order');
                if ($orderBy == 'created_at') {
                    $orderBy = 'leads.created_at';
                }
            } else {
                $orderBy = 'created_at';
                $order = 'desc';
                if ($orderBy == 'created_at') {
                    $orderBy = 'leads.created_at';
                }
                Filters::put(Auth::User()->id, 'leads_junk', 'order_by', $orderBy);
                Filters::put(Auth::User()->id, 'leads_junk', 'order', $order);
            }
        }
        if ($request->get('patient_id') && $request->get('patient_id') != '') {
            $where[] = array(
                'leads.patient_id',
                '=',
                GeneralFunctions::patientSearch($request->get('patient_id'))
            );
            Filters::put(Auth::User()->id, 'leads_junk', 'patient_id', GeneralFunctions::patientSearch($request->get('patient_id')));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'leads_junk', 'patient_id');
            } else {
                if (Filters::get(Auth::User()->id, 'leads_junk', 'patient_id')) {
                    $where[] = array(
                        'leads.patient_id',
                        '=',
                        Filters::get(Auth::User()->id, 'leads_junk', 'patient_id')
                    );
                }
            }
        }
        if ($request->get('name') && $request->get('name') != '') {
            $where[] = array(
                'users.name',
                'like',
                '%' . $request->get('name') . '%'
            );
            Filters::put(Auth::User()->id, 'leads_junk', 'name', $request->get('name'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'leads_junk', 'name');
            } else {
                if (Filters::get(Auth::User()->id, 'leads_junk', 'name')) {
                    $where[] = array(
                        'users.name',
                        'like',
                        '%' . Filters::get(Auth::User()->id, 'leads_junk', 'name') . '%'
                    );
                }
            }
        }
        if ($request->get('phone') && $request->get('phone') != '') {
            $where[] = array(
                'users.phone',
                'like',
                '%' . GeneralFunctions::cleanNumber(
                    $request->get('phone')
                ) . '%'
            );
            Filters::put(Auth::User()->id, 'leads_junk', 'phone', GeneralFunctions::cleanNumber($request->get('phone')));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'leads_junk', 'phone');
            } else {
                if (Filters::get(Auth::User()->id, 'leads_junk', 'phone')) {
                    $where[] = array(
                        'users.phone',
                        'like',
                        '%' . GeneralFunctions::cleanNumber(Filters::get(Auth::User()->id, 'leads_junk', 'phone')) . '%'
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
            Filters::put(Auth::User()->id, 'leads_junk', 'city_id', $request->get('city_id'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'leads_junk', 'city_id');
            } else {
                if (Filters::get(Auth::User()->id, 'leads_junk', 'city_id')) {
                    $where[] = array(
                        'city_id',
                        '=',
                        Filters::get(Auth::User()->id, 'leads_junk', 'city_id')
                    );
                }
            }
        }
        if ($request->get('region_id') && $request->get('region_id') != '') {
            $where[] = array(
                'region_id',
                '=',
                $request->get('region_id')
            );
            Filters::put(Auth::User()->id, 'leads_junk', 'region_id', $request->get('region_id'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'leads_junk', 'region_id');
            } else {
                if (Filters::get(Auth::User()->id, 'leads_junk', 'region_id')) {
                    $where[] = array(
                        'region_id',
                        '=',
                        Filters::get(Auth::User()->id, 'leads_junk', 'region_id')
                    );
                }
            }
        }
        if ($request->get('lead_status_id') && $request->get('lead_status_id')) {
            $where[] = array(
                'lead_status_id',
                '=',
                $request->get('lead_status_id')
            );
            Filters::put(Auth::User()->id, 'leads_junk', 'lead_status_id', $request->get('lead_status_id'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'leads_junk', 'lead_status_id');
            } else {
                if (Filters::get(Auth::User()->id, 'leads_junk', 'lead_status_id')) {
                    $where[] = array(
                        'lead_status_id',
                        '=',
                        Filters::get(Auth::User()->id, 'leads_junk', 'lead_status_id')
                    );
                }
            }
        }
        if ($request->get('service_id') && $request->get('service_id')) {
            $where[] = array(
                'service_id',
                '=',
                $request->get('service_id')
            );
            Filters::put(Auth::User()->id, 'leads_junk', 'service_id', $request->get('service_id'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'leads_junk', 'service_id');
            } else {
                if (Filters::get(Auth::User()->id, 'leads_junk', 'service_id')) {
                    $where[] = array(
                        'service_id',
                        '=',
                        Filters::get(Auth::User()->id, 'leads_junk', 'service_id')
                    );
                }
            }
        }
        if ($request->get('created_by') && $request->get('created_by') != '') {
            $where[] = array(
                'leads.created_by',
                '=',
                $request->get('created_by')
            );
            Filters::put(Auth::User()->id, 'leads_junk', 'created_by', $request->get('created_by'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'leads_junk', 'created_by');
            } else {
                if (Filters::get(Auth::User()->id, 'leads_junk', 'created_by')) {
                    $where[] = array(
                        'leads.created_by',
                        '=',
                        Filters::get(Auth::User()->id, 'leads_junk', 'created_by')
                    );
                }
            }
        }
        if ($request->get('date_from') && $request->get('date_from') != '') {
            $where[] = array(
                'leads.created_at',
                '>=',
                $request->get('date_from') . ' 00:00:00'
            );
            Filters::put(Auth::User()->id, 'leads_junk', 'date_from', $request->get('date_from'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'leads_junk', 'date_from');
            } else {
                if (Filters::get(Auth::User()->id, 'leads_junk', 'date_from')) {
                    $where[] = array(
                        'leads.created_at',
                        '>=',
                        Filters::get(Auth::User()->id, 'leads_junk', 'date_from') . ' 00:00:00'
                    );
                }
            }
        }
        if ($request->get('date_to') && $request->get('date_to') != '') {
            $where[] = array(
                'leads.created_at',
                '<=',
                $request->get('date_to') . ' 23:59:59'
            );
            Filters::put(Auth::User()->id, 'leads_junk', 'date_to', $request->get('date_to'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'leads_junk', 'date_to');
            } else {
                if (Filters::get(Auth::User()->id, 'leads_junk', 'date_to')) {
                    $where[] = array(
                        'leads.created_at',
                        '<=',
                        Filters::get(Auth::User()->id, 'leads_junk', 'date_to') . ' 23:59:59'
                    );
                }
            }
        }
        // Find Junk Lead Status to exclude
        $junk_lead_statuses = LeadStatuses::where(array(
            'account_id' => Auth::User()->account_id,
            'is_junk' => 1,
        ))->first();
        $countQuery = Leads::join('users', 'users.id', '=', 'leads.patient_id')
            ->where('users.user_type_id', '=', Config::get('constants.patient_id'))
            ->where(function ($query) {
                $query->whereIn('leads.city_id', ACL::getUserCities());
                $query->orWhereNull('leads.city_id');

            })
            ->whereIn('leads.lead_status_id', array($junk_lead_statuses->id));
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
        $resultQuery = Leads::join('users', 'users.id', '=', 'leads.patient_id')
            ->where('users.user_type_id', '=', Config::get('constants.patient_id'))
            ->where(function ($query) {
                $query->whereIn('leads.city_id', ACL::getUserCities());
                $query->orWhereNull('leads.city_id');
            })
            ->whereIn('leads.lead_status_id', array($junk_lead_statuses->id));
        if (count($where)) {
            $resultQuery->where($where);
        }
        $Leads = $resultQuery->select('*', 'leads.created_by as lead_created_by', 'leads.id as lead_id', 'leads.created_at as lead_created_at', 'users.id as PatientId')
            ->limit($iDisplayLength)
            ->offset($iDisplayStart)
            ->orderBy($orderBy, $order)
            ->get();
        $Users = User::getAllRecords(Auth::User()->account_id)->getDictionary();
        $Regions = Regions::getAllRecordsDictionary(Auth::User()->account_id);
        $lead_status = LeadStatuses::getAllRecordsDictionary(Auth::User()->account_id);
        // Convert Lead status to Converted
        $DefaultConvertedLeadStatus = LeadStatuses::where(array(
            'account_id' => Auth::User()->account_id,
            'is_converted' => 1,
        ))->first();
        if ($DefaultConvertedLeadStatus) {
            $default_converted_lead_status_id = $DefaultConvertedLeadStatus->id;
        } else {
            $default_converted_lead_status_id = Config::get('constants.lead_status_converted');
        }
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
                    'PatientId' => GeneralFunctions::patientSearchStringAdd($lead->PatientId),
                    'name' => $lead->name,
                    'phone' => '<a href="javascript:void(0)" class="clipboard" data-toggle="tooltip" title="Click to Copy" data-clipboard-text="' . GeneralFunctions::prepareNumber4Call($lead->patient->phone) . '">' . GeneralFunctions::prepareNumber4Call($lead->patient->phone) . '</a>',
                    'city_id' => view('admin.leads.city', compact('lead'))->render(),
                    'region_id' => (array_key_exists($lead->region_id, $Regions)) ? $Regions[$lead->region_id]->name : 'N/A',
                    'lead_status_id' => view('admin.leads.lead_status', compact('lead', 'lead_status_data'))->render(),
                    'service_id' => view('admin.leads.service', compact('lead'))->render(),
                    'created_at' => Carbon::parse($lead->lead_created_at)->format('F j,Y h:i A'),
                    'created_by' => array_key_exists($lead->lead_created_by, $Users) ? $Users[$lead->lead_created_by]->name : 'N/A',
                    'actions' => view('admin.leads.actions', compact('lead', 'default_converted_lead_status_id'))->render(),
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

    private function getJunkFilters() {
        $filters = Filters::all(Auth::User()->id, 'leads_junk');
        $cities = Cities::getActiveSorted(ACL::getUserCities());
        $cities->prepend('All', '');
        $regions = Regions::getActiveSorted(ACL::getUserRegions());
        $regions->prepend('Select a Region', '');
        $users = User::getAllActiveRecords(Auth::User()->account_id)->pluck('name', 'id');
        $users->prepend('All', '');
        // Find Junk Lead Status to exclude
        $junk_lead_statuses = LeadStatuses::where(array(
            'account_id' => Auth::User()->account_id,
            'is_junk' => 1,
        ))->first();
        if ($junk_lead_statuses) {
            $lead_statuses[''] = 'All';
            $lead_statuses[$junk_lead_statuses->id] = $junk_lead_statuses->name;
        } else {
            $lead_statuses[''] = 'All';
        }
        $Services = Services::where([
            ['slug', '=', 'custom'],
            ['parent_id', '=', '0'],
            ['active', '=', '1']
        ])->get()->pluck('name', 'id');
        $Services->prepend('All', '');
        $leadServices = Filters::get(Auth::User()->id, 'leads_junk', 'service_id');
        $records['filter_values'] = [
            'Services' => $Services,
            'cities' => $cities,
            'regions' => $regions,
            'users' => $users,
            'lead_statuses' => $lead_statuses,
            'leadServices' => $leadServices,
        ];
        if (isset($filters['created_from'])) {
            $filters['created_from'] = date('Y-m-d', strtotime($filters['created_from']));
        }
        if (isset($filters['created_to'])) {
            $filters['created_to'] = date('Y-m-d', strtotime($filters['created_to']));
        }
        $records['active_filters'] = $filters;
        return $records;
    }

     /*
     * Function get the variable to search in database to get the patient
     *
     * */
    public function getleadid(Request $request)
    {
        $leads = Leads::getLeadidAjax($request->search, Auth::User()->account_id);
        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'leads' => $leads
        ]);
    }

    public function getleadnumber(Request $request){
        $lead = Leads::find($request->lead_id);
        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'lead' => $lead
        ]);
    }

    public function phoneSearch(Request $request)
    {
        $leads = Leads::getLeadPhoneAjax($request->search, Auth::User()->account_id);
        return ApiHelper::apiResponse($this->success, 'Record found.', true, [
            'leads' => $leads
        ]);
    }

    /*Start Comment function for lead*/
    public function LeadStoreComment(Request $req)
    {
        $leadComment = LeadComments::where('lead_id', '=', $req->lead_id)->get();
        $lead = new LeadComments();
        $lead->comment = $req->comment;
        $lead->lead_id = $req->lead_id;
        $lead->created_by = Auth::user()->id;
        $leadCommentDate = \Carbon\Carbon::parse($lead->created_at)->format('D M, j Y h:i A');
        $lead->save();
        $username = Auth::user()->name;
        $myarray = ['username' => $username, 'lead' => $lead, 'leadCommentDate' => $leadCommentDate, 'leadCommentSection' => $leadComment];
        return response()->json($myarray);
    }
    /*End Comment function for lead*/

    /**
     * Delete all selected Appointment at once.
     *
     * @param Request $request
     * @return  Response $response
     */
    public function loadLeadData(Request $request)
    {
        $data = $request->all();
        // Add Additional Data
        $data['status'] = 0;
        $data['patient_id'] = 0;
        if (Gate::allows('leads_manage') && $request->get('phone') && !$request->get('lead_id')) {
            if($request->input('phone') == '***********'){
                $request->merge(['phone' => $request->input('old_phone')]);
            }
            $request->request->remove('old_phone');
            $phone = GeneralFunctions::cleanNumber($request->get('phone'));
            $patient = Patients::getByPhone($phone, Auth::User()->account_id, $request->patient_id);
            if (!$patient) {
                $data['status'] = 1;
                $data['service_id'] = $request->get('service_id');
                $data['phone'] = $request->get('phone');
                $data['cnic'] = $request->get('cnic');
                $data['dob'] = $request->get('dob');
                $data['address'] = $request->get('address');
                $data['referred_by'] = $request->get('referred_by');
            } else {
                $lead = Leads::where(['patient_id' => $patient->id, 'service_id' => $request->get('service_id')])->first();
                if ($lead) {
                    $data['id'] = $lead->id;
                    $data['city_id'] = $lead->city_id;
                    $data['town_id'] = $lead->town_id;
                    $data['service_id'] = $lead->service_id;
                    $data['lead_source_id'] = $lead->lead_source_id;
                    $data['lead_status_id'] = $lead->lead_status_id;
                } else {
                    $data['service_id'] = $request->get('service_id');
                }
                $data['patient_id'] = $patient->id;
                $data['gender'] = $patient->gender;
                $data['phone'] = $patient->phone;
                $data['cnic'] = $patient->cnic;
                $data['dob'] = $patient->dob;
                $data['address'] = $patient->address;
                $data['name'] = $patient->name;
                $data['email'] = $patient->email;
                $data['referred_by'] = $patient->referred_by;
            }
        }
        return response()->json($data);
    }

    /**
     * return ajax view when adding consulting appointment from full calendar.
     * @param (int) $id
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\JsonResponse|\Illuminate\View\View|void
     */
    public function convert($id)
    {
        if (!Gate::allows('appointments_manage') || !Gate::allows('leads_convert')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        try {
            $lead = Leads::getData($id);
            $user_info = User::where(['id' => $lead->patient_id, 'active' => 1, 'account_id' => Auth::User()->account_id])->first();
            if ($lead == null) {
                return ApiHelper::apiResponse($this->success, 'Resource not found.', false);
            }
            $employees = User::getAllActiveRecords(Auth::User()->account_id);
            if ($employees) {
                $employees = $employees->pluck('full_name', 'id');
            } else {
                $employees = array();
            }
            $services[''] = 'Select a Service';
            $cities = Cities::getActiveFeaturedOnly(ACL::getUserCities(), Auth::User()->account_id)->get();
            if ($cities) {
                $cities = $cities->pluck('full_name', 'id');
            }
            $lead_sources = LeadSources::getActiveSorted();
            $services = Services::getGroupsActiveOnly()->pluck('name', 'id');
            $setting = Settings::where('slug', '=', 'sys-virtual-consultancy')->first();
            return ApiHelper::apiResponse($this->success, 'Record found.', true, [
                'services' => $services,
                'lead' => $lead,
                'employees' => $employees,
                'cities' => $cities,
                'lead_sources' => $lead_sources,
                'user_info' => $user_info,
                'setting' => $setting,
                'consultancy_types' =>  Config::get("constants.consultancy_type_array"),
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function exportPdf(Request $request) {
        ini_set('memory_limit', '-1');
        set_time_limit(0);
        $resultQuery = Leads::join('users', 'users.id', '=', 'leads.patient_id')
            ->where('users.user_type_id', '=', Config::get('constants.patient_id'));
        if($request->id != null || $request->id != ''){
            $resultQuery->where('leads.patient_id', $request->id);
        }
        if($request->service_id != null || $request->service_id != ''){
            $resultQuery->where('leads.service_id', $request->service_id);
        }
        if($request->lead_status_id != null || $request->lead_status_id != ''){
            $resultQuery->where('leads.lead_status_id', $request->lead_status_id);
        }
        if($request->city_id != null || $request->city_id != ''){
            $resultQuery->where('leads.city_id', $request->city_id);
        }
        if($request->location_id != null || $request->location_id != ''){
            $resultQuery->where('leads.location_id', $request->location_id);
        }
        if($request->region_id != null || $request->region_id != ''){
            $resultQuery->where('leads.region_id', $request->region_id);
        }
        if($request->created_by != null || $request->created_by != ''){
            $resultQuery->where('leads.created_by', $request->created_by);
        }
        if($request->name != null || $request->name != ''){
            $resultQuery->where('users.name','like', $request->name.'%');
        }
        if($request->name != null || $request->name != ''){
            $resultQuery->where('users.name','like', $request->name.'%');
        }
        if($request->start_date != null || $request->start_date != ''){
            $resultQuery->whereBetween('leads.created_at', [$request->start_date, $request->end_date]);
        }
        $leads = $resultQuery->select('*', 'leads.created_by as lead_created_by', 'leads.id as lead_id', 'leads.created_at as lead_created_at', 'users.id as PatientId')
        ->orderBy("leads.created_at", "DESC")->get();
        $customPaper = array(0,0,720,1440);
        $pdf = PDF::loadView('admin.leads.lead-pdf', compact('leads'))->setPaper($customPaper, 'portrait');
        return $pdf->download('leads.pdf');
    }
    public function exportDocs(Request $request) {
        set_time_limit(0);
        ini_set('memory_limit', '-1');
        return Excel::download(new ExportLead($request), 'leads.'.$request->ext);
    }
}
