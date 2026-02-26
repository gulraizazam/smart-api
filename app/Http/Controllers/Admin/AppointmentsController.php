<?php

namespace App\Http\Controllers\Admin;

use DateTime;
use Carbon\Carbon;
use App\Helpers\ACL;
use App\Models\User;
use App\Models\Leads;
use App\Models\Towns;
use App\Models\Cities;
use App\Models\Bundles;
use App\Models\Doctors;
use App\Models\Regions;
use App\Models\SMSLogs;
use App\Helpers\Filters;
use App\Models\Accounts;
use App\Models\Activity;
use App\Models\Invoices;
use App\Models\Packages;
use App\Models\Patients;
use App\Models\Services;
use App\Models\Settings;
use App\Models\Discounts;
use App\Models\Locations;
use App\Models\Resources;
use App\Helpers\JazzSMSAPI;
use App\Models\AuditTrails;
use App\Models\LeadSources;
use App\Models\MachineType;
use App\Exports\ExportToday;
use App\Models\Appointments;
use App\Models\LeadStatuses;
use App\Models\PaymentModes;
use App\Models\SMSTemplates;
use Illuminate\Http\Request;
use App\Models\LeadsServices;
use App\Helpers\TelenorSMSAPI;
use App\Models\InvoiceDetails;
use App\Models\PackageBundles;
use App\Models\PackageService;
use App\Exports\TodayTreatment;
use App\HelperModule\ApiHelper;
use App\Models\InvoiceStatuses;
use App\Models\PackageAdvances;
use App\Models\ResourceHasRota;
use Illuminate\Validation\Rule;
use App\Models\AppointmentTypes;
use App\Models\AuditTrailTables;
use App\Models\UserHasLocations;
use App\Helpers\ActivityLogger;
use App\Helpers\GeneralFunctions;
use App\Models\AuditTrailActions;
use App\Exports\ExportAppointment;
use App\Models\DoctorHasLocations;
use Illuminate\Support\Facades\DB;
use App\Models\AppointmentComments;
use App\Models\AppointmentStatuses;
use App\Models\ResourceHasRotaDays;
use App\Exports\ExportConsultancies;
use App\Http\Controllers\Controller;
use App\Models\UserOperatorSettings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Config;
use App\Jobs\IndexSingleAppointmentJob;
use App\Helpers\Widgets\LocationsWidget;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Helpers\Widgets\AppointmentEditWidget;
use App\Helpers\Widgets\AppointmentCheckesWidget;
use App\Helpers\Invoice_Plan_Refund_Sms_Functions;
use App\Helpers\Widgets\PlanAppointmentCalculation;
use PhpOffice\PhpSpreadsheet\Calculation\Web\Service;
use App\Http\Requests\Admin\StoreUpdateAppointmentCommentsRequest;
use App\Models\MachineTypeHasServices;
use App\Services\MetaConversionApiService;

class AppointmentsController extends Controller
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
     * Display a listing of Appointment.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        if (! Gate::allows('appointments_consultancy')) {
            return abort(404);
        }

        // Get user's assigned centres
        $userCentres = ACL::getUserCentres();

        return view('admin.appointments.index', [
            'userCentres' => $userCentres
        ]);
    }

    public function treatment()
    {
        
        if (! Gate::allows('treatments_manage')) {
            return abort(404);
        }

        // Get user's assigned centres
        $userCentres = ACL::getUserCentres();

        return view('admin.appointments.treatment', [
            'userCentres' => $userCentres
        ]);
    }

    /**
     * Display a listing of Lead_statuse.
     * Supports optional patient_id parameter for patient-specific filtering
     *
     * @param \Illuminate\Http\Request
     * @param int|null $patientId
     * @return \Illuminate\Http\Response
     */
    public function datatable(Request $request, $patientId = null)
    {
        // Also check for patient_id in query string (for patient card context)
        if (!$patientId && $request->has('patient_id')) {
            $patientId = $request->input('patient_id');
        }
        return $this->getDefaultListing($request, $patientId);
    }

    // REMOVED: treatmentDatatable() - Migrated to App\Http\Controllers\Api\TreatmentsController@datatable

    public function todayexport()
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '0'); // for infinite time of execution
        $limit = 1000;
        $offset = 0;

        return Excel::download(new ExportToday($limit, $offset), 'todayconsultancies.xlsx');
    }

    public function todaytreatments()
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '0'); // for infinite time of execution
        $limit = 1000;
        $offset = 0;

        return Excel::download(new TodayTreatment($limit, $offset), 'todaytreatments.xlsx');
    }

    public function downloadExportdata(Request $request)
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '0'); // for infinite time of execution
        $limit = 10000;
        $offset = 0;
        if ($request->appointmenttype == 1) {
            return Excel::download(new ExportConsultancies($limit, $offset, $request), 'consultancies.xlsx');
        } else {
            return Excel::download(new ExportConsultancies($limit, $offset, $request), 'appointments.xlsx');
        }
    }

    /**
     * Get Default Listing for Appointments
     * Supports optional patient_id parameter for patient-specific filtering
     *
     * @return mixed
     */
   private function getDefaultListing(Request $request, $patientId = null)
    {
        // Use optimized ConsultancyDatatableService
        $datatableService = app(\App\Services\Appointment\ConsultancyDatatableService::class);
        $records = $datatableService->getDatatableData($request, $patientId);
        
        return ApiHelper::apiDataTable($records);
    }

    // REMOVED: getDefaultTreatmentListing() - Migrated to App\Services\Treatment\TreatmentService@getDatatableData

    /**
     * @return mixed
     */
    private function getFiltersData($records, $filename)
    {
        $regions = Regions::getActiveSorted(ACL::getUserRegions());
        $cities = Cities::getActiveSortedFeatured(ACL::getUserCities());
        $doctors = Doctors::getActiveOnly(ACL::getUserCentres());
        $locations = Locations::getActiveSorted(ACL::getUserCentres());
        $services = GeneralFunctions::ServicesTreeList();

        $appointment_statuses = AppointmentStatuses::getAllParentRecords(Auth::User()->account_id);
        if ($appointment_statuses) {
            $appointment_statuses = $appointment_statuses->pluck('name', 'id');
        }
        if (Gate::allows('appointments_consultancy')) {
            $appointment_types = AppointmentTypes::where('slug', '=', 'consultancy')->get()->pluck('name', 'id');
        }
        if (Gate::allows('treatments_services')) {
            $appointment_types = AppointmentTypes::where('slug', '=', 'treatment')->get()->pluck('name', 'id');
        }
        if (Gate::allows('appointments_consultancy') && Gate::allows('treatments_services')) {
            $appointment_types = AppointmentTypes::get()->pluck('name', 'id');
        }
        if (! Gate::allows('appointments_consultancy') && ! Gate::allows('treatments_services')) {
            $appointment_types = [];
        }
        $users = User::getAllRecords(Auth::User()->account_id)->pluck('name', 'id');
        $records['active_filters'] = Filters::all(Auth::User()->id, $filename);
        $records['filter_values'] = [
            'cities' => $cities,
            'regions' => $regions,
            'users' => $users,
            'doctors' => $doctors,
            'locations' => $locations,
            'services' => $services,
            'appointment_statuses' => $appointment_statuses,
            'appointment_types' => $appointment_types,
            'consultancy_types' => config('constants.consultancy_type_array'),
        ];

        return $records;
    }

    /**
     * Show the form for creating new Appointment.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        $user = Auth::User();
        /*
         * Set dropdown for all system users
         */
        if ($user->user_type_id == config('constants.application_user_id') || $user->user_type_id == config('constants.administrator_id')) {
            $userHasLocation = UserHasLocations::join('locations', 'user_has_locations.location_id', '=', 'locations.id')->where('user_has_locations.user_id', '=', $user->id)->orderby('name', 'asc')->first();
            if ($userHasLocation) {
                $locations = Locations::where('id', '=', $userHasLocation->location_id)->first();
                $city_id = $locations->city->id;
                $location_id = $locations->id;
                $doctors = DoctorHasLocations::where('is_allocated',1)->where('location_id', '=', $location_id)->first();
                $urlquery = '?city_id='.$city_id.'&location_id='.$location_id;
                if ($doctors) {
                    $urlquery = '?city_id='.$city_id.'&location_id='.$location_id.'&doctor_id='.$doctors->user_id;
                }
                if ($request->city_id && $request->location_id) {
                } else {
                    return redirect(route('admin.appointments.create').$urlquery);
                }
            }
        }
        /*
         * Set dropdown for all asthetic operators/ consultants
         */
        if ($user->user_type_id == config('constants.practitioner_id')) {
            $userHasLocation = DoctorHasLocations::join('locations', 'doctor_has_locations.location_id', '=', 'locations.id')->where('doctor_has_locations.is_allocated',1)->where('doctor_has_locations.user_id', '=', $user->id)->orderby('name', 'asc')->first();
            if ($userHasLocation) {
                $locations = Locations::where('id', '=', $userHasLocation->location_id)->first();
                $city_id = $locations->city_id;
                $location_id = $locations->id;
                $urlquery = '?city_id='.$city_id.'&location_id='.$location_id.'&doctor_id='.$user->id;
                if ($request->city_id && $request->location_id) {
                } else {
                    return redirect(route('admin.appointments.create').$urlquery);
                }
            }
        }
        if (! Gate::allows('appointments_consultancy')) {
            return abort(401);
        }
        if ($request->lead_id) {
            $lead = Leads::where(['id' => $request->lead_id])->first();
            if ($lead) {
                $lead = [
                    'id' => $lead->id,
                    'name' => ($lead->patient_id) ? $lead->patient->name : null,
                    'phone' => ($lead->patient_id) ? $lead->patient->phone : null,
                    'dob' => ($lead->patient_id) ? $lead->patient->dob : null,
                    'address' => ($lead->patient_id) ? $lead->patient->address : null,
                    'cnic' => ($lead->patient_id) ? $lead->patient->cnic : null,
                    'referred_by' => ($lead->patient_id) ? $lead->patient->referred_by : null,
                    'service_id' => $lead->service_id,
                ];
            } else {
                $lead = [
                    'id' => '',
                    'name' => '',
                    'phone' => '',
                    'done' => '',
                    'address' => '',
                    'cnic' => '',
                    'referred_by' => '',
                    'service_id' => '',
                ];
            }
        } else {
            $lead = [
                'id' => '',
                'name' => '',
                'phone' => '',
                'done' => '',
                'address' => '',
                'cnic' => '',
                'referred_by' => '',
                'service_id' => '',
            ];
        }
        $employees = User::getAllActiveRecords(Auth::User()->account_id);
        if ($employees) {
            $employees = $employees->pluck('full_name', 'id');
        } else {
            $employees = [];
        }
        $cities = Cities::getActiveFeaturedOnly(ACL::getUserCities(), Auth::User()->account_id)->get();
        if ($cities) {
            $cities = $cities->pluck('full_name', 'id');
        }
        $cities->prepend('Select a City', '');
        $lead_sources = LeadSources::getActiveSorted();
        $lead_sources->prepend('Select a Lead Source', '');
        // If Treatment ID is set then fetch only that Treatment
        if ($lead['service_id']) {
            $services = Services::getGroupsActiveOnly('name', 'asc', $lead['service_id'], Auth::User()->account_id)->pluck('name', 'id');
        } else {
            $services = Services::getGroupsActiveOnly()->pluck('name', 'id');
        }
        $services->prepend('Select a Service', '');
        // Get location based doctors
        $doctors = Doctors::getLocationDoctors();

        return view('admin.appointments.consultancy.consultancy_manage', compact('cities', 'lead', 'lead_sources', 'services', 'doctors', 'employees'));
    }

    /**
     * Validate form fields
     *
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function verifyFields(Request $request, $id = null)
    {
        $data = $request->all();
        $phone = $data['phone'];
        if ($data['phone'] == '***********') {
            $phone = $data['old_phone'];
        }
        $data['phone'] = GeneralFunctions::cleanNumber($phone);
        if (is_null($request->new_patient)) {
            return Validator::make($data, [
                'name' => 'required',
                'phone' => 'required',
            ]);
        }

        return Validator::make($data, [
            'name' => 'required',
            'phone' => [
                'required',
                //Rule::unique('users')->ignore($id),
            ],
        ]);
    }

    /**
     * Validate form fields
     *
     * @return Validator $validator;
     */
    protected function verifyUpdateFields(Request $request, $id = null)
    {
        // Get appointment to check status
        $appointment = null;
        if ($id) {
            $appointment = Appointments::find($id);
        } elseif ($request->has('id') || $request->route('id')) {
            $appointmentId = $request->input('id') ?? $request->route('id');
            $appointment = Appointments::find($appointmentId);
        }
        
        // Check if this is an arrived/converted consultation with permissions
        $isArrivedOrConverted = $appointment && in_array($appointment->appointment_status_id, [2, 16]);
        $hasAnyEditPermission = Gate::allows('update_consultation_service') || 
                                Gate::allows('update_consultation_doctor') || 
                                Gate::allows('update_consultation_schedule');
        
        // Debug logging
        \Log::info('verifyUpdateFields Debug', [
            'appointment_id' => $appointment ? $appointment->id : null,
            'appointment_status_id' => $appointment ? $appointment->appointment_status_id : null,
            'is_arrived_or_converted' => $isArrivedOrConverted,
            'has_any_edit_permission' => $hasAnyEditPermission,
            'request_has_scheduled_date' => $request->has('scheduled_date'),
            'scheduled_date_value' => $request->input('scheduled_date'),
            'request_all' => $request->all(),
        ]);
        
        // For arrived/converted with permissions, make fields conditionally required
        if ($isArrivedOrConverted && $hasAnyEditPermission) {
            \Log::info('Using NULLABLE validation rules');
            return $validator = Validator::make($request->all(), [
                'treatment_id' => 'nullable',
                'scheduled_date' => 'nullable',
                'scheduled_time' => 'nullable',
                'doctor_id' => 'nullable',
            ]);
        }
        
        // For other cases, all fields are required
        \Log::info('Using REQUIRED validation rules');
        return $validator = Validator::make($request->all(), [
            'treatment_id' => 'required',
            'scheduled_date' => 'required',
            'scheduled_time' => 'required',
            'doctor_id' => 'required',
        ]);
    }

    /**
     * Store a newly created Appointment in storage.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        if (! Gate::allows('appointments_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        
        try {
            $consultancyService = app(\App\Services\Appointment\ConsultancyService::class);
            $data = $request->all();
            
            unset($data['resource_id']);
            unset($data['resource_has_rota_day_id']);
            unset($data['resource_has_rota_day_id_for_machine']);
            
            $appointment = $consultancyService->createConsultancy($data);
            
            return ApiHelper::apiResponse($this->success, 'Consultation created successfully.', true, $appointment);
        } catch (\App\Exceptions\AppointmentException $e) {
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false);
        } catch (\Exception $e) {
            \Log::error('Error creating consultation: ' . $e->getMessage());
            return ApiHelper::apiResponse($this->error, 'Failed to create consultation.', false);
        }
        
        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }
        $rotaCheck = $this->scheduledConsultancy($request);
        if ($rotaCheck['status']) {
            // Store form data in a variable
            if ($request->new_patient == '1') {
                $request->request->remove('lead_id'); // Remove Lead ID index
            }
            $appointment_data = $request->all();
            $appointment_data['account_id'] = Auth::user()->account_id;
            $phone = $appointment_data['phone'];
            if ($appointment_data['phone'] == '***********') {
                $phone = $appointment_data['old_phone'];
            }
            unset($appointment_data['old_phone']);
            $appointment_data['phone'] = GeneralFunctions::cleanNumber($phone);
            $appointment_data['created_by'] = Auth::user()->id;
            
            unset($appointment_data['resource_id']);
            unset($appointment_data['resource_has_rota_day_id']);
            unset($appointment_data['resource_has_rota_day_id_for_machine']);
            
            // Set default appointment status i.e. 'pending'
            $appointment_status = AppointmentStatuses::getADefaultStatusOnly(Auth::User()->account_id);
            if ($appointment_status) {
                $appointment_data['appointment_status_id'] = $appointment_status->id;
                $appointment_data['base_appointment_status_id'] = $appointment_status->id;
                $appointment_data['appointment_status_allow_message'] = $appointment_status->allow_message;
            } else {
                $appointment_data['appointment_status_id'] = null;
                $appointment_data['base_appointment_status_id'] = null;
                $appointment_data['appointment_status_allow_message'] = 0;
            }
            $appointment_data['appointment_type_id'] = Config::get('constants.appointment_type_consultancy');
            // Get Location object to retrieve City
            $location = Locations::findOrFail($appointment_data['location_id']);
            // Set City ID after retrieving from Location
            $appointment_data['city_id'] = $location->city_id;
            $appointment_data['region_id'] = $location->region_id;
            $appointment_data['account_id'] = Auth::User()->account_id;
            $appointment_data['created_at'] = Filters::getCurrentTimeStamp();
            $appointment_data['updated_at'] = Filters::getCurrentTimeStamp();
            if ($request->start) {
                $start = $request->start;
                $service_duration = Services::find($request->service_id)->value('duration');
                $duraton_array = explode(':', $service_duration);
                if (count($duraton_array) == 2) {
                    $end = Carbon::parse($start)->addHour($service_duration[0])->addMinute($duraton_array[1]);
                    $start = Carbon::parse($start)->format('Y-m-d H:i:s');
                }
                $doctor_checking = Resources::checkingDoctorAvailbility($request->doctor_id, $start, $end);
                if ($doctor_checking) {
                    $appointment_data['scheduled_date'] = Carbon::parse($request->start)->format('Y-m-d');
                    $appointment_data['scheduled_time'] = Carbon::parse($request->start)->format('H:i:s');
                    $appointment_data['first_scheduled_date'] = Carbon::parse($request->start)->format('Y-m-d');
                    $appointment_data['first_scheduled_time'] = Carbon::parse($request->start)->format('H:i:s');
                    $appointment_data['first_scheduled_count'] = 1;
                    if ($request->appointment_type == 'treatment') {
                        $appointment_data['resource_id'] = $request->resource_id;
                    }
                }
            }
            /*
             * Check if Lead ID not provided then create a new lead
             * and assign this lead to current appointment.
             */
            if (! $request->lead_id) {
                $lead_obj = $appointment_data;
                // Set Lead status to Booked when consultation is created
                $DefaultBookedLeadStatus = LeadStatuses::where([
                    'account_id' => Auth::User()->account_id,
                    'is_booked' => 1,
                ])->first();
                if ($DefaultBookedLeadStatus) {
                    $default_booked_lead_status_id = $DefaultBookedLeadStatus->id;
                } else {
                    $default_booked_lead_status_id = Config::get('constants.lead_status_booked');
                }
                $lead_obj['lead_status_id'] = $default_booked_lead_status_id;
                $lead_obj['created_at'] = Filters::getCurrentTimeStamp();
                $lead_obj['updated_at'] = Filters::getCurrentTimeStamp();
                $lead_obj['location_id'] = $request->location_id;
                if($request->lead_id){
                    $patient = Patients::where(['id' => $request->lead_id])->first();
                }else{
                    $patient = Patients::where(['phone' => $appointment_data['phone']])->orderBy('phone', 'desc')->first();
                }
                if ($request->new_patient == '1') {
                    $appointment_data['user_type_id'] = 3;
                    if (! $patient) {
                        $patient = Patients::createRecord($appointment_data, 1);
                    } else {
                        return ApiHelper::apiResponse($this->success, 'Phone number already exist', false);
                    }

                    $checkLeadExistance = Leads::updateOrCreate([
                        'phone' => $appointment_data['phone'],
                        'account_id' => Auth::User()->account_id,
                    ], $lead_obj);
                    $lead = $checkLeadExistance;
                    LeadsServices::updateOrCreate([
                        'lead_id' => $lead->id,
                        'service_id' => $appointment_data['service_id'],
                    ], [
                        'lead_id' => $lead->id,
                        'service_id' => $appointment_data['service_id'],
                    ]);
                    LeadsServices::where(['lead_id' => $lead->id])->update(['status' => 0]);
                    $lead_service = LeadsServices::where(['lead_id' => $lead->id, 'service_id' => $appointment_data['service_id']])->first();
                    $lead_service->update(['status' => 1]);
                }
            } else {
                $lead = Leads::whereId($request->lead_id)->first();
                $appointment_data['email'] = $lead->email;
                $patient = Patients::where(['phone' => $appointment_data['phone']])->orderBy('phone', 'desc')->first();
                if (! $patient) {
                    $appointment_data['user_type_id'] = 3;
                    $patient = Patients::createRecord($appointment_data, 1);
                } else {
                    $appointment_data['patient_id'] = $patient->id;
                    Patients::where(['id' => $patient->id])->update([
                        'name' => $appointment_data['name'],
                        'email' => $appointment_data['email'],
                        'gender' => $appointment_data['gender'],
                        'referred_by' => $appointment_data['referred_by'] ?? null,
                    ]);
                }
                
                // Update lead's patient_id if it's null
                if ($lead && !$lead->patient_id && $patient) {
                    Leads::where('id', $lead->id)->update(['patient_id' => $patient->id]);
                }

                LeadsServices::updateOrCreate([
                    'lead_id' => $lead->id,
                    'service_id' => $appointment_data['service_id'],
                ], [
                    'lead_id' => $lead->id,
                    'service_id' => $appointment_data['service_id'],
                ]);
                LeadsServices::where(['lead_id' => $lead->id])->update(['status' => 0]);
                $lead_service = LeadsServices::where(['lead_id' => $lead->id, 'service_id' => $appointment_data['service_id']])->first();
                $lead_service->update(['status' => 1]);
            }


            // Set Lead ID for Appointment
            $appointment_data['patient_id'] = $patient->id;
            $appointment_data['lead_id'] = $lead->id;
            /*
             * End Lead ID Process
             */
            if ($request->scheduled_date && $request->scheduled_time) {
                $appointment_data['scheduled_date'] = Carbon::parse($request->scheduled_date)->format('Y-m-d');
                $appointment_data['scheduled_time'] = Carbon::parse($request->scheduled_time)->format('H:i:s');
            } else {
                $appointment_data['scheduled_date'] = Carbon::parse($request->start)->format('Y-m-d');
                $appointment_data['scheduled_time'] = Carbon::parse($request->start)->format('H:i:s');
            }
            $appointment_data['appointment_status_id'] = config('constants.appointment_status_pending');
            $appointment = Appointments::create($appointment_data);
            $find_cons = Appointments::latest()->first();
            if ($find_cons) {
                // Get the default Booked lead status
                $bookedStatus = LeadStatuses::where(['account_id' => Auth::User()->account_id, 'is_booked' => 1])->first();
                $bookedStatusId = $bookedStatus ? $bookedStatus->id : null;
                $openStatus = LeadStatuses::where(['account_id' => Auth::User()->account_id, 'is_default' => 1])->first();
                $openStatusId = $openStatus ? $openStatus->id : null;
                
                if ($bookedStatusId) {
                    \Log::info('Consultancy Created - Updating lead status to Booked', [
                        'phone' => $appointment_data['phone'],
                        'patient_id' => $appointment_data['patient_id'],
                        'appointment_id' => $find_cons->id,
                        'booked_status_id' => $bookedStatusId,
                    ]);
                    $lead = Leads::where(['phone' => $appointment_data['phone']])->orderBy('id', 'desc')->update(['name' => $patient->name, 'lead_status_id' => $bookedStatusId, 'location_id' => $find_cons->location_id, 'patient_id' => $appointment_data['patient_id']]);
                    \Log::info('Lead status updated to Booked', [
                        'phone' => $appointment_data['phone'],
                        'new_status_id' => $bookedStatusId,
                    ]);
                    
                    // Log lead booked activity
                    $leadRecord = Leads::where(['phone' => $appointment_data['phone']])->orderBy('id', 'desc')->first();
                    if ($leadRecord) {
                        $location = Locations::with('city')->find($find_cons->location_id);
                        $service = Services::find($find_cons->service_id);
                        ActivityLogger::logLeadBooked($leadRecord, $find_cons, $location, $service);
                        
                        // Update patient_id on lead_created activities for this lead (by phone)
                        Activity::where('activity_type', 'lead_created')
                            ->where(function($query) use ($leadRecord, $appointment_data) {
                                $query->where('lead_id', $leadRecord->id)
                                      ->orWhere('patient', $leadRecord->name);
                            })
                            ->whereNull('patient_id')
                            ->update(['patient_id' => $appointment_data['patient_id']]);
                    }
                    
                    // Send Meta CAPI event for booked status
                    if ($leadRecord) {
                        \Log::info('Sending Meta CAPI booked event', [
                            'lead_id' => $leadRecord->id,
                            'phone' => $leadRecord->phone,
                            'meta_lead_id' => $leadRecord->meta_lead_id,
                            'email' => $leadRecord->email,
                        ]);
                        try {
                            $metaService = new MetaConversionApiService();
                            $metaService->sendLeadStatus(
                                $leadRecord->phone,
                                'booked',
                                $leadRecord->meta_lead_id,
                                $leadRecord->email
                            );
                            \Log::info('Meta CAPI booked event sent successfully', [
                                'lead_id' => $leadRecord->id,
                            ]);
                        } catch (\Exception $e) {
                            \Log::error('Meta CAPI booked event failed: ' . $e->getMessage(), [
                                'lead_id' => $leadRecord->id,
                                'exception' => $e->getTraceAsString(),
                            ]);
                        }
                    } else {
                        \Log::warning('No lead record found for Meta CAPI booked event', [
                            'phone' => $appointment_data['phone'],
                        ]);
                    }
                }
                
                // Check if lead_service exists for this service
                $existingLeadService = LeadsServices::where([
                    'lead_id' => $appointment_data['lead_id'],
                    'service_id' => $find_cons->service_id,
                ])->first();
                
                if ($existingLeadService) {
                    // Service exists - update it
                    $existingLeadService->update([
                        'consultancy_id' => $find_cons->id,
                        'lead_status_id' => $bookedStatusId,
                        'status' => 1, // Set as active
                    ]);
                } else {
                    // Service doesn't exist - check if there's an open service we can update
                    $openLeadService = LeadsServices::where('lead_id', $appointment_data['lead_id'])
                        ->where(function($query) use ($openStatusId) {
                            $query->whereNull('lead_status_id');
                            if ($openStatusId) {
                                $query->orWhere('lead_status_id', $openStatusId);
                            }
                        })->first();
                    
                    if ($openLeadService) {
                        // Update the open service to the new service
                        $openLeadService->update([
                            'service_id' => $find_cons->service_id,
                            'consultancy_id' => $find_cons->id,
                            'lead_status_id' => $bookedStatusId,
                            'status' => 1, // Set as active
                        ]);
                    } else {
                        // Create new lead_service entry
                        LeadsServices::create([
                            'lead_id' => $appointment_data['lead_id'],
                            'service_id' => $find_cons->service_id,
                            'consultancy_id' => $find_cons->id,
                            'lead_status_id' => $bookedStatusId,
                            'status' => 1, // Set as active
                        ]);
                    }
                }
                
                // Set other services for this lead as inactive (keep their lead_status_id unchanged)
                LeadsServices::where('lead_id', $appointment_data['lead_id'])
                    ->where('service_id', '!=', $find_cons->service_id)
                    ->where('status', 1)
                    ->update(['status' => 0]);
            }
            /* Now We need to update name of all appointments that already in appointment table against patient
             */
            Appointments::where(['patient_id' => $appointment_data['patient_id']])->update(['name' => $patient->name]);
            // Always set send_message to 1 for new consultations
            $appointment->update([
                'send_message' => 1,
            ]);
            /*
             * Set Appointment Status if appointment scheduled date & time are not defined
             * case 1: If Scheduled Date is not set then status is 'un-scheduled'
             * case 2: If 'un-scheduled' is not set then set defautl status i.e. 'pending'
             */
            if (! $appointment->scheduled_date && ! $appointment->scheduled_time) {
                $appointment_status = AppointmentStatuses::getUnScheduledStatusOnly(Auth::User()->account_id);
                if ($appointment_status) {
                    $appointment->update([
                        'appointment_status_id' => $appointment_status->id,
                        'base_appointment_status_id' => $appointment_status->id,
                        'appointment_status_allow_message' => 0,
                        'updated_at' => Filters::getCurrentTimeStamp(),
                    ]);
                } else {
                    // Set default appointment status i.e. 'pending'
                    $appointment_status = AppointmentStatuses::getADefaultStatusOnly(Auth::User()->account_id);
                    if ($appointment_status) {
                        $appointment->update([
                            'appointment_status_id' => $appointment_status->id,
                            'base_appointment_status_id' => $appointment_status->id,
                            'appointment_status_allow_message' => 0,
                            'updated_at' => Filters::getCurrentTimeStamp(),
                        ]);
                    } else {
                        $appointment->update([
                            'appointment_status_id' => null,
                            'base_appointment_status_id' => null,
                            'appointment_status_allow_message' => 0,
                            'updated_at' => Filters::getCurrentTimeStamp(),
                        ]);
                    }
                }
            }
            $message = 'Record has been created successfully.';
            // Send Promotion SMS - Removed to prevent duplicate SMS (cron job handles this)
            // $this->sendPromotionSMS($appointment->id, $appointment_data['phone']);
            GeneralFunctions::saveAppointmentLogs('created', 'Consultancy', $appointment);
            GeneralFunctions::saveActivityLogs('booked', 'Consultancy', $appointment_data,$appointment->id);

            /**
             * Dispatch Elastic Search Index
             */
            $this->dispatch(
                new IndexSingleAppointmentJob([
                    'account_id' => Auth::User()->account_id,
                    'appointment_id' => $appointment->id,
                    'patient_phone' => $appointment_data['phone'],
                ])
            );

            return ApiHelper::apiResponse($this->success, $message, true, [
                'id' => $appointment->id,
                'city_id' => $request->city_id,
                'doctor_id' => $request->doctor_id,
                'location_id' => $request->location_id,
                'appointment_type' => 'consultancy',
            ]);
        }

        return ApiHelper::apiResponse($this->success, $rotaCheck['message'], $rotaCheck['status']);
        /*This function is also using in leads section*/
    }

    private function scheduledConsultancy(Request $request)
    {
        \Log::info('scheduledConsultancy called', [
            'has_scheduled_date' => $request->has('scheduled_date'),
            'has_scheduled_time' => $request->has('scheduled_time'),
            'scheduled_date' => $request->scheduled_date,
            'scheduled_time' => $request->scheduled_time,
            'start' => $request->start,
        ]);
        
        $appointment = new \stdClass();
        $appointment->city_id = $request->city_id;
        $appointment->doctor_id = $request->doctor_id;
        $appointment->location_id = $request->location_id;
        $appointment->appointment_type_id = 1;
        $rota = $this->checkRota($appointment, $request);
        if ($rota['status']) {
            return [
                'status' => true,
                'message' => 'Record updated successfully!',
            ];
        }

        return [
            'status' => false,
            'message' => $rota['message'] ?? 'Sorry! rota cant be created',
        ];
    }

    private function sendPromotionSMS($appointmentId, $patient_phone)
    {
        $apt = Appointments::find($appointmentId);
        if($apt->appointment_type_id==1){
            $SMSTemplate = SMSTemplates::getBySlug('on-appointment', Auth::User()->account_id);
        }
        if($apt->appointment_type_id==2){
            $SMSTemplate = SMSTemplates::getBySlug('treatment-on-appointment', Auth::User()->account_id);
        }
        //$SMSTemplate = SMSTemplates::getBySlug('promotion-sms', Auth::User()->account_id);
        if (! $SMSTemplate) {
            // SMS Promotion is disabled
            return [
                'status' => true,
                'sms_data' => 'SMS Promotion is disabled',
                'error_msg' => '',
            ];
        }
        $preparedText = Appointments::prepareSMSContent($appointmentId, $SMSTemplate->content);
        $setting = Settings::whereSlug('sys-current-sms-operator')->first();
        $UserOperatorSettings = UserOperatorSettings::getRecord(Auth::User()->account_id, $setting->data);
        if ($setting->data == 1) {
            $SMSObj = [
                'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
                'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
                'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($patient_phone)),
                'text' => $preparedText,
                'mask' => $UserOperatorSettings->mask, // Setting ID 3 for Mask
                'test_mode' => $UserOperatorSettings->test_mode, // Setting ID 3 Test Mode
            ];
            $response = TelenorSMSAPI::SendSMS($SMSObj);
        } else {
            $SMSObj = [
                'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
                'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
                'from' => $UserOperatorSettings->mask,
                'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($patient_phone)),
                'text' => $preparedText,
                'test_mode' => $UserOperatorSettings->test_mode, // Setting ID 3 Test Mode
            ];
            $response = JazzSMSAPI::SendSMS($SMSObj);
        }
        $SMSLog = array_merge($SMSObj, $response);
        $SMSLog['appointment_id'] = $appointmentId;
        $SMSLog['created_by'] = Auth::user()->id;
        if ($setting->data == 2) {
            $SMSLog['mask'] = $SMSObj['from'];
        }
        SMSLogs::create($SMSLog);

        return $response;
    }

    public function createTreatmentAppointment(Request $request)
    {
        if (! Gate::allows('appointments_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.', false);
        }
        if (
            $request->location_id &&
            $request->doctor_id
        ) {
            $location_id = $request->location_id;
            $doctor_id = $request->doctor_id;
        } else {
            $city_id = 0;
            $location_id = 0;
            $doctor_id = 0;

            return ApiHelper::apiResponse($this->success, 'Invalid request.', false);
        }
        // Commented out machine rota check for resource calendar view
        // if ($request->start) {
        //     $appointment_checkes = AppointmentCheckesWidget::AppointmentAppointmentCheckesfromcalender($request);
        // } else {
        //     $appointment_checkes = [
        //         'status' => true,
        //     ];
        // }
        $appointment_checkes = [
            'status' => true,
        ];
        if ($request->lead_id) {
            $lead = Leads::where(['id' => $request->lead_id])->first();
            if ($lead) {
                $lead = [
                    'id' => $lead->id,
                    'patient_id' => $lead->patient_id,
                    'name' => ($lead->patient_id) ? $lead->patient->name : null,
                    'phone' => ($lead->patient_id) ? $lead->patient->phone : null,
                    'dob' => ($lead->patient_id) ? $lead->patient->dob : null,
                    'address' => ($lead->patient_id) ? $lead->patient->address : null,
                    'cnic' => ($lead->patient_id) ? $lead->patient->cnic : null,
                    'referred_by' => ($lead->patient_id) ? $lead->patient->referred_by : null,
                    'service_id' => $lead->service_id,
                ];
            } else {
                $lead = [
                    'id' => '',
                    'patient_id' => '',
                    'name' => '',
                    'phone' => '',
                    'dob' => '',
                    'address' => '',
                    'cnic' => '',
                    'referred_by' => '',
                    'service_id' => '',
                ];
            }
        } else {
            $lead = [
                'id' => '',
                'patient_id' => '',
                'name' => '',
                'phone' => '',
                'dob' => '',
                'address' => '',
                'cnic' => '',
                'referred_by' => '',
                'service_id' => '',
            ];
        }
        $employees = User::getAllActiveRecords(Auth::User()->account_id);
        if ($employees) {
            $employees = $employees->pluck('full_name', 'id');
        } else {
            $employees = [];
        }

        // If machine_id is provided, load services based on both machine and doctor
        // Otherwise, load services based only on doctor
        if ($request->machine_id) {
            $intersect_resource_service_ids = LocationsWidget::loadAppointmentServiceByLocationResource($request->machine_id, Auth::User()->account_id);
            $intersect_location_doctor_service_ids = LocationsWidget::loadAppointmentServiceByLocationDoctor($request->location_id, $request->doctor_id, Auth::User()->account_id);

            $serviceIds = [];
            if (count($intersect_resource_service_ids) && count($intersect_location_doctor_service_ids)) {
                $serviceIds = array_intersect($intersect_resource_service_ids, $intersect_location_doctor_service_ids);
            }
        } else {
            // No machine selected, load services based only on doctor
            $serviceIds = LocationsWidget::loadAppointmentServiceByLocationDoctor($request->location_id, $request->doctor_id, Auth::User()->account_id);
        }

        if (count($serviceIds)) {
            $services = Services::whereIn('id', $serviceIds)->get()->pluck('name', 'id');
        } else {
            return ApiHelper::apiResponse($this->success, 'Services not found for this doctor.', false);
        }
        $lead_sources = LeadSources::getActiveSorted();
        // Get location based doctors
        $doctors = Doctors::getLocationDoctors();
        $towns = Towns::getActiveTowns();

        return ApiHelper::apiResponse($this->success, $appointment_checkes['message'] ?? 'Record found', $appointment_checkes['status'], [
            'lead_sources' => $lead_sources,
            'services' => $services,
            'doctors' => $doctors,
            'city_id' => '0',
            'location_id' => $location_id,
            'doctor_id' => $doctor_id,
            'lead' => $lead,
            'employees' => $employees,
            'appointment_checkes' => $appointment_checkes,
            'towns' => $towns,
            'genders' => Config::get('constants.gender_array'),
        ]);
    }
    /*
     * Send SMS on booking of Appointment
     *
     * @param: int $appointmentId
     * @param: string $patient_phone
     * @return: array|mixture
     */

    /**
     * return ajax view when adding consulting appointment from full calendar.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\JsonResponse|\Illuminate\View\View|void
     */
    public function createConsultingAppointment(Request $request)
    {
        if (! Gate::allows('appointments_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        if (
            $request->location_id &&
            $request->doctor_id
        ) {
            $location_id = $request->location_id;
            $doctor_id = $request->doctor_id;
        } else {
            $city_id = 0;
            $location_id = 0;
            $doctor_id = 0;

            return response()->json(['message' => 'Invalid request'], 400);
        }
        if ($request->start) {
            $appointment_checkes = AppointmentCheckesWidget::AppointmentConsultancyCheckes($request);
        } else {
            $appointment_checkes = [
                'status' => true,
            ];
        }
        if ($request->lead_id) {
            $lead = Leads::where(['id' => $request->lead_id])->first();
            if ($lead) {
                $lead = [
                    'id' => $lead->id,
                    'name' => ($lead->lead_id) ? $lead->name : null,
                    'phone' => ($lead->lead_id) ? $lead->phone : null,
                    'referred_by' => ($lead->lead_id) ? $lead->referred_by : null,
                    'service_id' => $lead->service_id,
                ];
            } else {
                $lead = [
                    'id' => '',
                    'name' => '',
                    'phone' => '',
                    'referred_by' => '',
                    'service_id' => '',
                ];
            }
        } else {
            $lead = [
                'id' => '',
                'name' => '',
                'phone' => '',
                'referred_by' => '',
                'service_id' => '',
            ];
        }
        $employees = User::getAllActiveRecords(Auth::User()->account_id);
        if ($employees) {
            $employees = $employees->pluck('full_name', 'id');
        } else {
            $employees = [];
        }
        $services = Services::where('parent_id', '=', '0')->where('name', '!=', 'All Services')->pluck('name', 'id');
        // $serviceIds = LocationsWidget::loadAppointmentServiceByLocationDoctor($request->location_id, $request->doctor_id, Auth::User()->account_id);
        // if (count($serviceIds)) {
        //     $services = Services::whereIn('id', $serviceIds)->get()->pluck('name', 'id');
        // } else {
        //     $services[''] = '';
        // }
        $lead_sources = LeadSources::getActiveSorted();
        $setting = Settings::where('slug', '=', 'sys-virtual-consultancy')->first();
        if ($appointment_checkes['status']) {
            return ApiHelper::apiResponse($this->success, 'Data Found.', true, [
                'lead_sources' => $lead_sources,
                'services' => $services,
                'city_id' => '0',
                'location_id' => $location_id,
                'doctor_id' => $doctor_id,
                'lead' => $lead,
                'employees' => $employees,
                'appointment_checkes' => $appointment_checkes,
                'setting' => $setting,
                'consultancy_types' => Config::get('constants.consultancy_type_array'),
                'genders' => Config::get('constants.gender_array'),
            ]);
        }

        return ApiHelper::apiResponse($this->success, $appointment_checkes['message'], false);
    }
    /*
     * Send SMS Promotion SMS
     *
     * @param: int $appointmentId
     * @param: string $patient_phone
     * @return: array|mixture
     */

    /**
     * Show details.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function detail($id)
    {
        if (! Gate::allows('appointments_manage') && ! Gate::allows('appointments_view')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $invoice_status = InvoiceStatuses::where('slug', '=', 'paid')->first();
        $invoice = Invoices::where([
            ['appointment_id', '=', $id],
            ['invoice_status_id', '=', $invoice_status->id],
        ])->first();
        if ($invoice) {
            $invoicearray[] = $invoice;
            $invoiceid = $invoicearray[0]['id'];
        } else {
            $invoiceid = null;
        }
        $appointment = Appointments::with(
            'patient',
            'doctor', 'city',
            'location',
            'appointment_status',
            'service',
            'appointment_comments.user'
        )->find($id);
        if (! $appointment) {
            return ApiHelper::apiResponse($this->success, 'Appointment not found.', false);
        }

        return ApiHelper::apiResponse($this->success, 'Data found.', true, [
            'appointment' => $appointment,
            'invoice' => $invoice,
            'invoiceid' => $invoiceid,
            'permissions' => [
                'edit' => Gate::allows('appointments_edit'),
                'invoice' => Gate::allows('appointments_invoice'),
                'invoice_display' => Gate::allows('appointments_invoice_display'),
                'image_manage' => Gate::allows('appointments_image_manage'),
                'measurement_manage' => Gate::allows('appointments_measurement_manage'),
                'medical_form_manage' => Gate::allows('appointments_medical_form_manage'),
                'plans_create' => Gate::allows('appointments_plans_create'),
                'patient_card' => Gate::allows('appointments_patient_card'),
                'log' => Gate::allows('appointments_log'),
                'contact' => Gate::allows('contact'),
                'delete' => Gate::allows('appointments_destroy'),
            ],
        ]);
    }

    /**
     * Show the form for editing Appointment.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        if (! Gate::allows('appointments_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $locationsids = [];
        $doctorids = [];
        $reverse_process = false;
        $appointment = Appointments::with('lead', 'patient')->find($id);
        if (! $appointment) {
            return ApiHelper::apiResponse($this->success, 'Resource not found.', false);
        }
        $resourceHadRotaDay = ResourceHasRotaDays::find($appointment->resource_has_rota_day_id);
        
        // Get all parent services (base services where parent_id = 0)
        $services = Services::where('parent_id', 0)->where('active', 1)->orderBy('name')->get()->pluck('name', 'id');
        
        if ($appointment->service_id) {
            $serviceid = Services::where(['id' => $appointment->service_id])->first();
        }
        
        // Get doctors for the appointment's location
        $doctors = $doctors_no_final = Doctors::getActiveOnly($appointment->location_id, Auth::User()->account_id);
        /*For machine type we perform that work we can remove it if any problem happen but for linkage that is best*/
        foreach ($doctors as $key => $doctor) {
            $doctor_serivce = AppointmentEditWidget::loaddoctorservice_edit($key, $appointment->location_id, Auth::User()->account_id, $reverse_process);
            if (isset($serviceid) && in_array($serviceid->id, $doctor_serivce)) {
                $doctorids[] = $key;
            }
        }
        if(Gate::allows('edit_after_arrived')){

            $doctor_ids = DoctorHasLocations::where('is_allocated',1)->where('location_id' ,$appointment->location_id )->groupBy('user_id')->pluck('user_id');

            $doctors = Doctors::whereIn('id',$doctor_ids)->where('active' , 1)->get()->pluck('name', 'id');

        }else{
            $doctors = $doctors_no_final = Doctors::whereIn('id', $doctorids)->get()->pluck('name', 'id');
            if ($doctors_no_final) {

                foreach ($doctors_no_final as $key => $doctor) {
                    $resource = Resources::where('external_id', '=', $key)->first();
                    $doctor_rota = ResourceHasRota::where([
                        ['resource_id', '=', $resource?->id],
                        ['is_consultancy', '=', '1'],
                    ])->get();
                    if (count($doctor_rota) == 0) {
                        unset($doctors[$key]);
                    }
                }
            }
        }
        
        // Always include the currently assigned doctor, even if they don't have the service allocated
        // This ensures the doctor shows up when editing existing appointments
        if ($appointment->doctor_id && !isset($doctors[$appointment->doctor_id])) {
            $currentDoctor = Doctors::find($appointment->doctor_id);
            if ($currentDoctor && $currentDoctor->active == 1) {
                $doctors[$appointment->doctor_id] = $currentDoctor->name;
            }
        }

        /*End*/

        $back_date_config = Settings::whereSlug('sys-back-date-appointment')->select('data')->first();
        $setting = Settings::where('slug', '=', 'sys-virtual-consultancy')->first();

        // Format dates for display
        $appointmentData = $appointment->toArray();
        $appointmentData['scheduled_date'] = Carbon::parse($appointment->scheduled_date)->format('Y-m-d');
        $appointmentData['scheduled_time'] = Carbon::parse($appointment->scheduled_time)->format('h:i A');
        
        return ApiHelper::apiResponse($this->success, 'Record Found', true, [
            'appointment' => $appointmentData,
            'services' => $services,
            'doctors' => $doctors,
            'resourceHadRotaDay' => $resourceHadRotaDay,
            'back_date_config' => $back_date_config,
            'setting' => $setting,
            'consultancy_type' => config('constants.consultancy_type_array'),
            'genders' => config('constants.gender_array'),
            'permissions' => [
                'contact' => Gate::allows('contact'),
                'update_consultation_service' => Gate::allows('update_consultation_service'),
                'update_consultation_doctor' => Gate::allows('update_consultation_doctor'),
                'update_consultation_schedule' => Gate::allows('update_consultation_schedule'),
            ],
        ]);
    }

    /**
     * Show the form for editing Appointment.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function editService($id)
    {
        if (! Gate::allows('appointments_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $locationsids = [];
        $doctorids = [];
        $machineids = [];
        $appointment = Appointments::with('patient', 'doctor')->find($id);
        if (! $appointment) {
            return ApiHelper::apiResponse($this->success, 'Resource not found.', false);
        }
        $resourceHadRotaDay = ResourceHasRotaDays::find($appointment->resource_has_rota_day_id);
        $machineHadRotaDay = ResourceHasRotaDays::find($appointment->resource_has_rota_day_id_for_machine);
        $biggerTime = ResourceHasRota::getBiggerTime($resourceHadRotaDay->start_time, $machineHadRotaDay->start_time);
        $smallerTime = ResourceHasRota::getSmallerTime($resourceHadRotaDay->end_time, $machineHadRotaDay->end_time);
        $cities = Cities::getActiveFeaturedOnly(ACL::getUserCities(), Auth::User()->account_id)->get();
        if ($cities) {
            $cities = $cities->pluck('full_name', 'id');
        }
        if ($appointment->service_id) {
            $services = $serviceid = Services::where(['id' => $appointment->service_id])->get()->pluck('name', 'id');
            $serviceid = Services::where(['id' => $appointment->service_id])->first();
        } else {
            $services = Services::get()->pluck('name', 'id');
        }
        $locations = Locations::getActiveRecordsByCity($appointment->city_id, ACL::getUserCentres(), Auth::User()->account_id);
        /*For machine type we perform that work we can remove it if any problem happen but for linkage that is best*/
        foreach ($locations as $location) {
            $location_serivce = AppointmentEditWidget::loadlocationservice_edit($location->id, Auth::User()->account_id, 'true');
            if (in_array($serviceid->id, $location_serivce)) {
                $locationsids[] = $location->id;
            }
        }
        $locations = Locations::whereIn('id', $locationsids)->get();
        /*End*/
        if ($locations) {
            $locations = $locations->pluck('name', 'id');
        }
        $doctors = $doctors_no_final = Doctors::getActiveOnly($appointment->location_id, Auth::User()->account_id);
        /*For machine type we perform that work we can remove it if any problem happen but for linkage that is best*/
        foreach ($doctors as $key => $doctor) {
            $doctor_serivce = AppointmentEditWidget::loaddoctorservice_edit($key, $appointment->location_id, Auth::User()->account_id, 'true');
            if (in_array($serviceid->id, $doctor_serivce)) {
                $doctorids[] = $key;
            }
        }
        $doctors = $doctors_no_final = Doctors::whereIn('id', $doctorids)->get()->pluck('name', 'id');
        /*End*/
        if ($doctors_no_final) {
            foreach ($doctors_no_final as $key => $doctor) {
                $resource = Resources::where('external_id', '=', $key)->first();
                $doctor_rota = ResourceHasRota::where([
                    ['resource_id', '=', $resource?->id],
                    ['is_treatment', '=', '1'],
                ])->get();
                if (count($doctor_rota) == 0) {
                    unset($doctors[$key]);
                }
            }
        }
        $machines = Resources::where([
            ['resource_type_id', '=', config('constants.resource_room_type_id')],
            ['location_id', '=', $appointment->location_id],
            ['account_id', '=', Auth::user()->account_id]],
            ['actvie', '=', 1]
        )->get();
        /*For machine type we perform that work we can remove it if any problem happen but for linkage that is best*/
        foreach ($machines as $machine) {
            $machinetypeid = MachineType::where('id', '=', $machine->machine_type_id)->first();
            $machine_serivce = AppointmentEditWidget::loadmachinetypeservice_edit($machinetypeid->id, Auth::User()->account_id, 'true');
            if (in_array($serviceid->id, $machine_serivce)) {
                $machineids[] = $machine->id;
            }
        }
        $machines = Resources::whereIn('id', $machineids)->get()->pluck('name', 'id');
        /*End*/
        $back_date_config = Settings::whereSlug('sys-back-date-appointment')->select('data')->first();

        return ApiHelper::apiResponse($this->success, 'Data found.', true, [
            'appointment' => $appointment,
            'cities' => $cities,
            'services' => $services,
            'locations' => $locations,
            'doctors' => $doctors,
            'machines' => $machines,
            'resourceHadRotaDay' => $resourceHadRotaDay,
            'machineHadRotaDay' => $machineHadRotaDay,
            'biggerTime' => $biggerTime,
            'smallerTime' => $smallerTime,
            'back_date_config' => $back_date_config,
            'genders' => config('constants.gender_array'),
            'consultancy_type' => config('constants.consultancy_type_array'),
        ]);
    }

    public function editAppointmentService($id)
    {
        if (! Gate::allows('appointments_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $locationsids = [];
        $doctorids = [];
        $machineids = [];
        $appointment = Appointments::with('patient', 'doctor')->find($id);
        if (! $appointment) {
            return ApiHelper::apiResponse($this->success, 'Resource not found.', false);
        }
        $resourceHadRotaDay = ResourceHasRotaDays::find($appointment->resource_has_rota_day_id);
        $machineHadRotaDay = ResourceHasRotaDays::find($appointment->resource_has_rota_day_id_for_machine);
        $biggerTime = ResourceHasRota::getBiggerTime($resourceHadRotaDay->start_time, $machineHadRotaDay->start_time);
        $smallerTime = ResourceHasRota::getSmallerTime($resourceHadRotaDay->end_time, $machineHadRotaDay->end_time);
        $cities = Cities::getActiveFeaturedOnly(ACL::getUserCities(), Auth::User()->account_id)->get();
        if ($cities) {
            $cities = $cities->pluck('full_name', 'id');
        }
        if ($appointment->service_id) {
            $services = $serviceid = Services::where(['id' => $appointment->service_id])->get()->pluck('name', 'id');
            $serviceid = Services::where(['id' => $appointment->service_id])->first();
        } else {
            $services = Services::get()->pluck('name', 'id');
        }
        $locations = Locations::getActiveRecordsByCity($appointment->city_id, ACL::getUserCentres(), Auth::User()->account_id);
        if ($locations) {
            $locations = $locations->pluck('name', 'id');
        }
        $doctors = $doctors_no_final = Doctors::getActiveOnly($appointment->location_id, Auth::User()->account_id);

        if ($doctors_no_final) {
            foreach ($doctors_no_final as $key => $doctor) {
                $resource = Resources::where('external_id', '=', $key)->first();
                $doctor_rota = ResourceHasRota::where([
                    ['resource_id', '=', $resource?->id],
                    ['is_treatment', '=', '1'],
                ])->get();
                if (count($doctor_rota) == 0) {
                    unset($doctors[$key]);
                }
            }
        }
        // $machines = Resources::where([
        //     ['resource_type_id', '=', config('constants.resource_room_type_id')],
        //     ['location_id', '=', $appointment->location_id],
        //     ['account_id', '=', Auth::user()->account_id]],
        //     ['actvie', '=', 1]
        // )->get();
        /*For machine type we perform that work we can remove it if any problem happen but for linkage that is best*/
        // foreach ($machines as $machine) {
        //     $machinetypeid = MachineType::where('id', '=', $machine->machine_type_id)->first();
        //     $machine_serivce = AppointmentEditWidget::loadmachinetypeservice_edit($machinetypeid->id, Auth::User()->account_id, 'true');
        //     if (in_array($serviceid->id, $machine_serivce)) {
        //         $machineids[] = $machine->id;
        //     }
        // }
        //$machines = Resources::whereIn('id', $machineids)->get()->pluck('name', 'id');
        /*End*/
        $machines = Resources::where([
            ['resource_type_id', '=', config('constants.resource_room_type_id')],
            ['location_id', '=', $appointment->location_id],
            ['account_id', '=', Auth::user()->account_id]],
            ['actvie', '=', 1]
        )->get()->pluck('name', 'id');
        $back_date_config = Settings::whereSlug('sys-back-date-appointment')->select('data')->first();

        // Format scheduled_date as string to prevent timezone issues
        $appointmentData = $appointment->toArray();
        if (isset($appointmentData['scheduled_date'])) {
            $appointmentData['scheduled_date'] = \Carbon\Carbon::parse($appointment->scheduled_date)->format('Y-m-d');
        }
        if (isset($appointmentData['first_scheduled_date'])) {
            $appointmentData['first_scheduled_date'] = \Carbon\Carbon::parse($appointment->first_scheduled_date)->format('Y-m-d');
        }

        return ApiHelper::apiResponse($this->success, 'Data found.', true, [
            'appointment' => $appointmentData,
            'cities' => $cities,
            'services' => $services,
            'locations' => $locations,
            'doctors' => $doctors,
            'machines' => $machines,
            'resourceHadRotaDay' => $resourceHadRotaDay,
            'machineHadRotaDay' => $machineHadRotaDay,
            'biggerTime' => $biggerTime,
            'smallerTime' => $smallerTime,
            'back_date_config' => $back_date_config,
            'genders' => config('constants.gender_array'),
            'consultancy_type' => config('constants.consultancy_type_array'),
        ]);
    }
    public function editFeedback($id)
    {
        if (! Gate::allows('appointments_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        $treatment = Appointments::with(['doctor','location','service'])
        ->where('id', $id)
        ->where('appointment_type_id', 2)
        ->where('appointment_status_id', 2)
        ->first();

        return ApiHelper::apiResponse($this->success, 'Data found.', true, [
            'appointment' => $treatment,

        ]);
    }
    /**
     * Update Appointment in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $updateService = new \App\Services\Appointment\ConsultancyUpdateService();
            $appointment = $updateService->updateConsultation($id, $request->all());
            
            return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.', true, [
                'appointment' => $appointment
            ]);
        } catch (\App\Exceptions\AppointmentException $e) {
            return ApiHelper::apiResponse($this->success, $e->getMessage(), false);
        } catch (\Exception $e) {
            \Log::error('Consultation update error', [
                'appointment_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ApiHelper::apiResponse($this->success, 'An error occurred while updating the consultation.', false);
        }
    }

    /**
     * Update Treatment in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateTreatment(Request $request, $id)
    {
        try {
            $updateService = new \App\Services\Appointment\TreatmentUpdateService();
            $appointment = $updateService->updateTreatment($id, $request->all());
            
            return ApiHelper::apiResponse($this->success, 'Treatment has been updated successfully.', true, [
                'appointment' => $appointment
            ]);
        } catch (\App\Exceptions\AppointmentException $e) {
            return ApiHelper::apiResponse($this->success, $e->getMessage(), false);
        } catch (\Exception $e) {
            \Log::error('Treatment update error', [
                'appointment_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ApiHelper::apiResponse($this->success, 'An error occurred while updating the treatment.', false);
        }
    }

    /**
     * OLD UPDATE METHOD - BACKUP (can be removed after testing)
     */
    public function updateOld(Request $request, $id)
    {

        // Get appointment to check status
        $appointment = Appointments::find($id);
        if (!$appointment) {
            return ApiHelper::apiResponse($this->success, 'Appointment not found.', false);
        }

        // Check if appointment is arrived/converted and what permissions user has
        $isArrivedOrConverted = in_array($appointment->appointment_status_id, [2, 16]); // 2 = Arrived, 16 = Converted
        $canEditDoctorAfterArrived = Gate::allows('update_consultation_doctor');
        $canEditServiceAfterArrived = Gate::allows('update_consultation_service');
        $canEditScheduleAfterArrived = Gate::allows('update_consultation_schedule');
        
        // Determine what fields are being changed
        $newServiceId = $request->treatment_service_id ?? $request->service_id ?? $request->treatment_id;
        $newDoctorId = $request->doctor_id;
        
        $isServiceChanging = $newServiceId && ($appointment->service_id != $newServiceId);
        $isDoctorChanging = $newDoctorId && ($appointment->doctor_id != $newDoctorId);
        
        // Debug logging
        \Log::info('Update Consultation Validation Debug', [
            'appointment_id' => $id,
            'appointment_status_id' => $appointment->appointment_status_id,
            'is_arrived_or_converted' => $isArrivedOrConverted,
            'permissions' => [
                'doctor' => $canEditDoctorAfterArrived,
                'service' => $canEditServiceAfterArrived,
                'schedule' => $canEditScheduleAfterArrived,
            ],
            'changes' => [
                'service' => $isServiceChanging,
                'doctor' => $isDoctorChanging,
            ],
            'old_values' => [
                'service_id' => $appointment->service_id,
                'doctor_id' => $appointment->doctor_id,
            ],
            'new_values' => [
                'service_id' => $newServiceId,
                'doctor_id' => $newDoctorId,
            ],
        ]);
        
        // For arrived/converted consultations, apply granular validation based on permissions
        if ($isArrivedOrConverted) {
            // 1. If service is changing and user has service permission
            if ($isServiceChanging && $canEditServiceAfterArrived) {
                // Validate that current doctor has the NEW service allocated
                $parent = Services::find($newServiceId);
                if ($parent) {
                    $parent_service_id = $parent->parent_id == 0 ? $parent->id : $parent->parent_id;
                    
                    $has_all_services = DoctorHasLocations::where('is_allocated', 1)
                        ->where('user_id', $appointment->doctor_id) // Current doctor
                        ->where('location_id', $request->location_id)
                        ->where('service_id', 13)
                        ->exists();
                    
                    $has_specific_service = DoctorHasLocations::where('is_allocated', 1)
                        ->where('user_id', $appointment->doctor_id) // Current doctor
                        ->where('location_id', $request->location_id)
                        ->where('service_id', $parent_service_id)
                        ->exists();
                    
                    if (!$has_all_services && !$has_specific_service) {
                        return ApiHelper::apiResponse($this->success, 'Current doctor does not have the new service allocated for this location.', false);
                    }
                }
            }
            
            // 2. If doctor is changing and user has doctor permission
            if ($isDoctorChanging && $canEditDoctorAfterArrived) {
                // Validate that NEW doctor has the current service allocated
                $service = Services::find($appointment->service_id);
                if ($service) {
                    $parent_service_id = $service->parent_id == 0 ? $service->id : $service->parent_id;
                    
                    $has_all_services = DoctorHasLocations::where('is_allocated', 1)
                        ->where('user_id', $newDoctorId) // New doctor
                        ->where('location_id', $request->location_id)
                        ->where('service_id', 13)
                        ->exists();
                    
                    $has_specific_service = DoctorHasLocations::where('is_allocated', 1)
                        ->where('user_id', $newDoctorId) // New doctor
                        ->where('location_id', $request->location_id)
                        ->where('service_id', $parent_service_id)
                        ->exists();
                    
                    if (!$has_all_services && !$has_specific_service) {
                        return ApiHelper::apiResponse($this->success, 'New doctor does not have the required service allocated for this location.', false);
                    }
                }
            }
            
            // 3. If both service AND doctor are changing
            if ($isServiceChanging && $isDoctorChanging && $canEditServiceAfterArrived && $canEditDoctorAfterArrived) {
                // Validate that NEW doctor has the NEW service allocated
                $parent = Services::find($newServiceId);
                if ($parent) {
                    $parent_service_id = $parent->parent_id == 0 ? $parent->id : $parent->parent_id;
                    
                    $has_all_services = DoctorHasLocations::where('is_allocated', 1)
                        ->where('user_id', $newDoctorId) // New doctor
                        ->where('location_id', $request->location_id)
                        ->where('service_id', 13)
                        ->exists();
                    
                    $has_specific_service = DoctorHasLocations::where('is_allocated', 1)
                        ->where('user_id', $newDoctorId) // New doctor
                        ->where('location_id', $request->location_id)
                        ->where('service_id', $parent_service_id)
                        ->exists();
                    
                    if (!$has_all_services && !$has_specific_service) {
                        return ApiHelper::apiResponse($this->success, 'New doctor does not have the new service allocated for this location.', false);
                    }
                }
            }
        } else {
            // For non-arrived/non-converted consultations, apply normal validation
            $parent = Services::whereid($request->treatment_service_id ?? $request->service_id ?? $request->treatment_id)->first();
            if ($parent && $parent->parent_id == 0) {
                $service_id = $parent->id;
            } elseif ($parent) {
                $service_id = $parent->parent_id;
            } else {
                return ApiHelper::apiResponse($this->success, 'Service not found.', false);
            }

            $has_all_services = DoctorHasLocations::where('is_allocated', 1)
                ->where('user_id', $request->doctor_id)
                ->where('location_id', $request->location_id)
                ->where('service_id', 13)
                ->exists();

            $has_specific_service = DoctorHasLocations::where('is_allocated', 1)
                ->where('user_id', $request->doctor_id)
                ->where('location_id', $request->location_id)
                ->where('service_id', $service_id)
                ->exists();

            if (!$has_all_services && !$has_specific_service) {
                return ApiHelper::apiResponse($this->success, 'This doctor does not have the required service allocated for this location.', false);
            }
        }
        
        // Proceed with update
        if (true) {
          
            $validator = $this->verifyUpdateFields($request, $id);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
            }
            $appointment = Appointments::find($id);
            $back_date_config = Settings::whereSlug('sys-back-date-appointment')->select('data')->first();
            // Only check back-date if scheduled_date is provided in request
            if ($request->has('scheduled_date') && $request->scheduled_date && ! Gate::allows('edit_after_arrived') && strtotime($request->scheduled_date) < strtotime(date('Y-m-d')) && $back_date_config->data == 0) {
                return ApiHelper::apiResponse($this->success, 'Scheduled date is older than today. Please select today or future date', false);
            }
            // Check if this is arrived/converted with edit permissions
            $isArrivedOrConverted = in_array($appointment->appointment_status_id, [2, 16]);
            $hasAnyEditPermission = Gate::allows('update_consultation_service') || 
                                    Gate::allows('update_consultation_doctor') || 
                                    Gate::allows('update_consultation_schedule');
            
            // Skip invoice check for arrived/converted with permissions (they can edit specific fields)
            // For other cases, check invoice only if user doesn't have edit_after_arrived permission
            if (!($isArrivedOrConverted && $hasAnyEditPermission) && ! Gate::allows('edit_after_arrived')) {
                if ($appointment) {
                    $check_invoice = Invoices::where('appointment_id', $appointment->id)->first();
                    if ($check_invoice) {
                        return ApiHelper::apiResponse($this->error, 'Invoice already generated. Appointment can not be rescheduled.', false);
                    }
                }
            }
            $rota = $this->checkRota($appointment, $request);
            if (! $rota['status']) {
                return ApiHelper::apiResponse($this->success, $rota['message'], $rota['status']);
            }
            if (! $appointment) {
                return ApiHelper::apiResponse($this->success, 'Appointment not found', false);
            }
            $lead = Leads::find($request->lead_id);
            if (! $lead) {
                return ApiHelper::apiResponse($this->success, 'Lead not found', false);
            }
            $patient = Patients::find($appointment->patient_id);
            if (! $patient) {
                return ApiHelper::apiResponse($this->success, 'Patient not found', false);
            }
            $value_of_sending_message = $appointment->send_message;
            
            // Get city and region from appointment's location since city field is not in edit form
            $location = Locations::find($appointment->location_id);
            
            $appointment_data = $request->all();
            
            // Set city_id and region_id from location
            if ($location) {
                $appointment_data['city_id'] = $location->city_id;
                $appointment_data['region_id'] = $location->region_id;
            }
            
            // Store old values for activity logging
            $oldDate = $appointment->scheduled_date;
            $oldTime = $appointment->scheduled_time;
            $oldDoctorId = $appointment->doctor_id;
            $oldServiceId = $appointment->service_id;
            $oldLocationId = $appointment->location_id;
            $oldCityId = $appointment->city_id;
            $oldMachineId = $appointment->resource_id;
            $oldConsultancyType = $appointment->consultancy_type;
            $oldPatientName = $patient->name;
            $oldPatientPhone = $patient->phone;
            $oldPatientGender = $patient->gender;
            $isRescheduled = false;
            
            // Only check for rescheduling if scheduled_date is provided in request
            if ($request->has('scheduled_date') && $request->scheduled_date && $appointment->scheduled_date != $request->scheduled_date) {
                $appointment_data['converted_by'] = Auth::user()->id;
                Activity::where('appointment_id',$id)->update(['action'=>'rescheduled','rescheduled_by'=>Auth::id(),'schedule_date'=>$request->scheduled_date,'updated_at'=>Carbon::now()]);
                $isRescheduled = true;
            }
            
            // Only check for time change if scheduled_time is provided in request
            // Don't update converted_by if only time changed (not date)
            if ($request->has('scheduled_time') && $request->scheduled_time && $appointment->scheduled_time != Carbon::parse($request->scheduled_time)->format('H:i:s')) {
                $isRescheduled = true;
            }
            
            // Only check for location/doctor change if they are provided in request
            $locationChanged = $request->has('location_id') && (string) $appointment->location_id !== $request->location_id;
            $doctorChanged = $request->has('doctor_id') && $request->doctor_id && (string) $appointment->doctor_id !== $request->doctor_id;
            
            if ($locationChanged || $doctorChanged) {
                $appointment_data['updated_by'] = Auth::user()->id;
            }
            if ($request->has('consultancy_type')) {
                if ((string) $appointment->consultancy_type !== $request->consultancy_type) {
                    $appointment_data['updated_by'] = Auth::user()->id;
                }
            }
            if ($request->has('machine_id')) {
                if ((string) $appointment->resource_id !== $request->machine_id) {
                    $appointment_data['updated_by'] = Auth::user()->id;
                }
            }
            
            $appointment_data['updated_at'] = Filters::getCurrentTimeStamp();
            
            // Use scheduled_date from request if provided, otherwise keep existing
            if ($request->has('scheduled_date') && $request->scheduled_date) {
                $appointment_data['scheduled_date'] = Carbon::parse($request->scheduled_date)->format('Y-m-d');
            } else {
                $appointment_data['scheduled_date'] = $appointment->scheduled_date;
            }
            
            // Use scheduled_time from request if provided, otherwise keep existing
            if ($request->has('scheduled_time') && $request->scheduled_time) {
                $appointment_data['scheduled_time'] = Carbon::parse($request->scheduled_time)->format('H:i:s');
            } else {
                $appointment_data['scheduled_time'] = $appointment->scheduled_time;
            }
            
            $appointment_data['location_id'] = $request->location_id ?? $appointment->location_id;
            
            // Use doctor_id from request if provided, otherwise keep existing
            if ($request->has('doctor_id') && $request->doctor_id) {
                $appointment_data['doctor_id'] = $request->doctor_id;
            } else {
                $appointment_data['doctor_id'] = $appointment->doctor_id;
            }
            
            // Update service_id if provided in request
            // treatment_id field contains the selected service from the dropdown
            if ($request->has('treatment_id') && $request->treatment_id) {
                $appointment_data['service_id'] = $request->treatment_id;
            } elseif ($request->has('service_id') && $request->service_id) {
                $appointment_data['service_id'] = $request->service_id;
            } elseif ($request->has('treatment_service_id') && $request->treatment_service_id) {
                $appointment_data['service_id'] = $request->treatment_service_id;
            }
            // Reset Scheduled Time to null, stop sending message
            $appointment_status = AppointmentStatuses::getADefaultStatusOnly(Auth::User()->account_id);
            if ($appointment_status) {
                $check_invoice = Invoices::where('appointment_id', $appointment->id)->first();
                if ($check_invoice) {
                    $appointment_data['appointment_status_id'] = $appointment->appointment_status_id;
                    $appointment_data['base_appointment_status_id'] = $appointment->base_appointment_status_id;
                } else {
                    $appointment_data['appointment_status_id'] = $appointment_status->id;
                    $appointment_data['base_appointment_status_id'] = $appointment_status->id;
                }
                $appointment_data['appointment_status_allow_message'] = $appointment_status->allow_message;
                $appointment_data['send_message'] = $appointment_status->allow_message;
            }
            /*
            * Grab Rota day info and update
            */
            $resource = Resources::where([
                'external_id' => $appointment_data['doctor_id'],
                'resource_type_id' => Config::get('constants.resource_doctor_type_id'),
                'account_id' => Auth::User()->account_id,
            ])->first();
            if ($resource) {
                $resource_has_rota_day = ResourceHasRotaDays::getSingleDayRotaWithResourceID($resource->id, $request->scheduled_date, Auth::User()->account_id, $appointment_data['location_id']);
                if (count($resource_has_rota_day)) {
                    $appointment_data['resource_id'] = $resource->id;
                    $appointment_data['resource_has_rota_day_id'] = $resource_has_rota_day['id'];
                }
            }
            if ($appointment->appointment_type_id == Config::get('constants.appointment_type_service')) {
                $machine_has_rota_day = ResourceHasRotaDays::getSingleDayRotaWithResourceID($appointment_data['machine_id'], $request->scheduled_date, Auth::User()->account_id, $appointment_data['location_id']);
                if (count($machine_has_rota_day)) {
                    $appointment_data['resource_id'] = $appointment_data['machine_id'];
                    $appointment_data['resource_has_rota_day_id_for_machine'] = $machine_has_rota_day['id'];
                }
            }
            $appointment->update($appointment_data);
            if (count($appointment->getChanges()) > 1) {
                // if only doctor are going to change and first sms already sent, so we need to stop sending message again
                if ($value_of_sending_message == '0') {
                    $changes = $appointment->getChanges();
                    // in future if edit form increase input field so we need to change that count also
                    // And Reader I didnt find any proper way so I use static check
                    if ($appointment->appointment_type_id == Config::get('constants.appointment_type_service')) {
                        if (count($changes) == 4) {
                            if (isset($changes['doctor_id'])) {
                                $appointment->update(['send_message' => 0]);
                            }
                        } elseif (count($changes) == 2) {
                            $appointment->update(['send_message' => $value_of_sending_message]);
                        }
                    } else {
                        if (count($changes) == 5) {
                            if (isset($changes['doctor_id'])) {
                                $appointment->update(['send_message' => 0]);
                            }
                        } elseif (count($changes) == 2) {
                            $appointment->update(['send_message' => $value_of_sending_message]);
                        }
                    }
                }
                $scheduled_at_count = $appointment->scheduled_at_count;
                $appointment->update(['scheduled_at_count' => $scheduled_at_count + 1]);
            }
            Appointments::where(['patient_id' => $appointment->patient_id])->update(['name' => $patient->name]);
            if ($appointment_data['appointment_status_id'] == 1) {
                $bookedStatus = LeadStatuses::where(['account_id' => Auth::User()->account_id, 'is_booked' => 1])->first();
                if ($bookedStatus) {
                    $appointment_data['lead_status_id'] = $bookedStatus->id;
                }
            } elseif ($appointment_data['appointment_status_id'] == 3) {
                $openStatus = LeadStatuses::where(['account_id' => Auth::User()->account_id, 'is_default' => 1])->first();
                if ($openStatus) {
                    $appointment_data['lead_status_id'] = $openStatus->id;
                }
            }
            $lead = Leads::find($appointment_data['lead_id']);
            if (! $lead) {
                return ApiHelper::apiResponse($this->success, 'Lead not found', false);
            }
            $lead->update($appointment_data);
            $patient = Patients::find($appointment->patient_id);
            if (! $patient) {
                return ApiHelper::apiResponse($this->success, 'Patient not found', false);
            }
            $patientData = $appointment_data;
            // Remove fields that don't exist in users table
            unset($patientData['updated_by']);
            unset($patientData['converted_by']);
            $screen = $appointment->appointment_type_id == 1 ? 'Consultancy' : 'Treatment';
            GeneralFunctions::saveAppointmentLogs('updated', $screen, $appointment);
            $patient = Patients::updateRecord($appointment->patient_id, $patientData);
            $patient->update($patientData);
            $this->dispatch(
                new IndexSingleAppointmentJob([
                    'account_id' => Auth::User()->account_id,
                    'appointment_id' => $appointment->id,
                ])
            );
            
            // Log rescheduled activity
            if ($isRescheduled) {
                $location = Locations::with('city')->find($appointment->location_id);
                $service = Services::find($appointment->service_id);
                ActivityLogger::logAppointmentRescheduled(
                    $appointment,
                    $patient,
                    $oldDate,
                    $oldTime,
                    Carbon::parse($request->scheduled_date)->format('Y-m-d'),
                    Carbon::parse($request->scheduled_time)->format('H:i:s'),
                    $location,
                    $service
                );
            }
            
            // Log other field changes (not rescheduling)
            $fieldChanges = [];
            
            // Check doctor change
            if ($oldDoctorId != $request->doctor_id) {
                $oldDoctor = User::find($oldDoctorId);
                $newDoctor = User::find($request->doctor_id);
                $fieldChanges['Doctor'] = [
                    'old' => $oldDoctor->name ?? 'Unknown',
                    'new' => $newDoctor->name ?? 'Unknown'
                ];
            }
            
            // Check service/treatment change (only if service_id is provided in request)
            if ($request->has('service_id') && $request->service_id && $oldServiceId != $request->service_id) {
                $oldService = Services::find($oldServiceId);
                $newService = Services::find($request->service_id);
                $fieldChanges['Treatment'] = [
                    'old' => $oldService->name ?? 'Unknown',
                    'new' => $newService->name ?? 'Unknown'
                ];
            }
            
            // Check location change
            if ($oldLocationId != $request->location_id) {
                $oldLocation = Locations::find($oldLocationId);
                $newLocation = Locations::find($request->location_id);
                $fieldChanges['Location'] = [
                    'old' => $oldLocation->name ?? 'Unknown',
                    'new' => $newLocation->name ?? 'Unknown'
                ];
            }
            
            // Check city change
            if ($oldCityId != $request->city_id) {
                $oldCity = Cities::find($oldCityId);
                $newCity = Cities::find($request->city_id);
                $fieldChanges['City'] = [
                    'old' => $oldCity->name ?? 'Unknown',
                    'new' => $newCity->name ?? 'Unknown'
                ];
            }
            
            // Check machine change (for treatments)
            if ($request->has('machine_id') && $oldMachineId != $request->machine_id) {
                $oldMachine = Resources::find($oldMachineId);
                $newMachine = Resources::find($request->machine_id);
                $fieldChanges['Machine'] = [
                    'old' => $oldMachine->name ?? 'Unknown',
                    'new' => $newMachine->name ?? 'Unknown'
                ];
            }
            
            // Check consultancy type change (for consultations)
            if ($request->has('consultancy_type') && $oldConsultancyType != $request->consultancy_type) {
                $fieldChanges['Consultancy Type'] = [
                    'old' => $oldConsultancyType ?? 'Unknown',
                    'new' => $request->consultancy_type ?? 'Unknown'
                ];
            }
            
            // Log field changes if any (excluding reschedule which is logged separately)
            if (!empty($fieldChanges)) {
                $location = Locations::with('city')->find($appointment->location_id);
                $service = Services::find($appointment->service_id);
                ActivityLogger::logAppointmentUpdated($appointment, $patient, $fieldChanges, $location, $service);
            }

            return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
        } else {
            return ApiHelper::apiResponse($this->success, 'This doctor does not have the required service allocated for this location.', false);
        }
    }

    /**
     * Remove Appointment from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        if (! Gate::allows('appointments_destroy')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $response = Appointments::DeleteRecord($id, Auth::User()->account_id);

        return ApiHelper::apiResponse($this->success, $response['message'], $response['status']);
    }

    /**
     * Inactive Record from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function inactive($id)
    {
        if (! Gate::allows('appointments_manage')) {
            return abort(401);
        }
        $permission = Cities::findOrFail($id);
        $permission->update(['active' => 0]);
        flash('Record has been inactivated successfully.')->success()->important();

        return redirect()->route('admin.appointments.index');
    }

    /**
     * Inactive Record from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function active($id)
    {
        if (! Gate::allows('appointments_manage')) {
            return abort(401);
        }
        $permission = Cities::findOrFail($id);
        $permission->update(['active' => 1]);
        flash('Record has been inactivated successfully.')->success()->important();

        return redirect()->route('admin.appointments.index');
    }

    /**
     * Delete all selected Appointment at once.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function loadLeadData(Request $request)
    {
        $data = [
            'status' => 0,
            'patient_id' => 0,
            'phone' => null,
            'cnic' => null,
            'gender' => null,
            'dob' => null,
            'address' => null,
            'town_id' => null,
            'referred_by' => null,
            'name' => null,
            'email' => null,
            'service_id' => null,
            'lead_source_id' => null,
        ];
        if (Gate::allows('appointments_manage')) {
            $phone = GeneralFunctions::cleanNumber($request->phone);
            $patient = Patients::getByPhone($phone, Auth::User()->account_id, $request->patient_id);
            if (! $patient) {
                $data['status'] = 1;
                $data['service_id'] = $request->service_id;
                $data['phone'] = $request->phone;
                $data['dob'] = $request->dob;
                $data['address'] = $request->address;
                $data['cnic'] = $request->cnic;
                $data['referred_by'] = $request->referred_by;
                $data['gender'] = $request->gender;
            } else {
                $lead = Leads::where(['patient_id' => $patient->id, 'service_id' => $request->service_id])->first();
                if ($lead) {
                    $data['service_id'] = $lead->service_id;
                    $data['lead_source_id'] = $lead->lead_source_id;
                    $data['lead_id'] = $lead->id;
                    $data['town_id'] = $lead->town_id;
                } else {
                    $data['service_id'] = $request->service_id;
                    $data['lead_id'] = '';
                }
                $data['patient_id'] = $patient->id;
                $data['phone'] = $patient->phone;
                $data['dob'] = $patient->dob;
                $data['address'] = $patient->address;
                $data['cnic'] = $patient->cnic;
                $data['referred_by'] = $patient->referred_by;
                $data['name'] = $patient->name;
                $data['email'] = $patient->email;
                $data['gender'] = $patient->gender;
            }
        }

        return ApiHelper::apiResponse($this->success, 'data found', true, $data);
    }

    /**
     * Load all Appointment Statuses.
     */
    public function showAppointmentStatuses(Request $request)
    {
        $appointment = Appointments::find($request->id);
        if (! $appointment) {
            return ApiHelper::apiResponse($this->success, 'No record found', false);
        }
        $base_appointments = AppointmentStatuses::where(['account_id' => 1])->select('id', 'parent_id', 'is_comment')->get()->keyBy('id');
        /*
         * If Un-scheduled status is present then exclude this status from drop-down
         */
        $unscheduled_appointment_status = AppointmentStatuses::getUnScheduledStatusOnly(Auth::User()->account_id);
        if ($unscheduled_appointment_status) {
            $base_appointment_statuses = AppointmentStatuses::getBaseActiveSorted(Auth::User()->account_id/*, $unscheduled_appointment_status->id*/);
        } else {
            $base_appointment_statuses = AppointmentStatuses::getBaseActiveSorted(Auth::User()->account_id);
        }

        if (isset($appointment->appointment_status) && $appointment->appointment_status->parent_id != 0) {
            $appointment_statuses = AppointmentStatuses::getActiveSorted($appointment->appointment_status->parent_id, Auth::User()->account_id);
        } else {
            $appointment_statuses[''] = '';
        }

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'appointment' => $appointment,
            'base_appointment_statuses' => $base_appointment_statuses,
            'appointment_statuses' => $appointment_statuses,
            'base_appointments' => $base_appointments,
            'appointment_status_not_show' => config('constants.appointment_status_not_show'),
            'cancellation_reason_other_reason' => config('constants.cancellation_reason_other_reason'),
        ]);
    }

    /**
     * Update Appointment Status
     *
     * @param  \App\Http\Requests\Admin\StoreUpdateAppointmentsRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeAppointmentStatuses(Request $request)
    {
       
        $data = $request->all();
        $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();
        $appointment = Appointments::find($request->id);
        if (! $appointment) {
            return ApiHelper::apiResponse($this->success, 'Appointment not found', false);
        }
        
        // Store old status for activity logging
        $oldStatusId = $appointment->base_appointment_status_id;
        $oldStatus = AppointmentStatuses::find($oldStatusId);
        $appointment_type = AppointmentTypes::where('slug', '=', 'consultancy')->first();
        $appointment_type_2 = AppointmentTypes::where('slug', '=', 'treatment')->first();
        $counterglobal = Settings::where('slug', '=', 'sys-appointmentrescheduledcounter')->first();
        $invoiceexit = Invoices::where([
            ['invoice_status_id', '=', $invoicestatus->id],
            ['appointment_id', '=', $data['id']],
        ])->get();
        if ($data['base_appointment_status_id'] == Config::get('constants.appointment_status_arrived')) {
            if (count($invoiceexit) == 0) {
                return ApiHelper::apiResponse($this->success, 'Kindly pay invoice first!', false);
            }
        }
        if ($data['base_appointment_status_id'] != Config::get('constants.appointment_status_arrived')) {
            if (count($invoiceexit) == 1) {
                return ApiHelper::apiResponse($this->success, 'Invoice paid, you not able to change status!', false);
            }
        }
        if ($appointment_type->id == $appointment->appointment_type_id) {
            if ($appointment->base_appointment_status_id == Config::get('constants.appointment_status_not_interested')) {
                if ($data['base_appointment_status_id'] != Config::get('constants.appointment_status_not_interested')) {
                    $data['counter'] = 0;
                }
            }
        }
        // Set Allow Message Flag
        if (isset($data['base_appointment_status_id'])) {
            $appointment_status = AppointmentStatuses::getData($data['base_appointment_status_id']);
            $data['appointment_status_allow_message'] = $appointment_status->allow_message;
        }
        if (! isset($data['appointment_status_id']) || $data['appointment_status_id'] == '') {
            $data['appointment_status_id'] = $data['base_appointment_status_id'];
        }
        // Set Comments
        if (isset($data['reason']) && ! $data['reason']) {
            $data['reason'] = null;
        }
        // Converted By
        // $data['converted_by'] = Auth::User()->id;
        $data['updated_by'] = Auth::User()->id;
        $data['updated_at'] = Filters::getCurrentTimeStamp();
        if ($appointment_type->id == $appointment->appointment_type_id) {
            if ($data['base_appointment_status_id'] == Config::get('constants.appointment_status_not_show')) {
                if ($appointment->counter == $counterglobal->data) {
                    $data['base_appointment_status_id'] = Config::get('constants.appointment_status_not_interested');
                    $appointment_childstatus_not_interested = AppointmentStatuses::where('parent_id', '=', Config::get('constants.appointment_status_not_interested'))->first();
                    if ($appointment_childstatus_not_interested) {
                        $data['appointment_status_id'] = $appointment_childstatus_not_interested->id;
                    } else {
                        $data['appointment_status_id'] = Config::get('constants.appointment_status_not_interested');
                    }
                } else {
                    $data['counter'] = $appointment->counter + 1;
                }
            }
        }
        $appointment->update($data);
        if ($appointment_type->id == $appointment->appointment_type_id) {
            if ($data['base_appointment_status_id'] == Config::get('constants.appointment_status_not_show')) {
                if ($appointment->counter == $counterglobal->data) {
                    $data['base_appointment_status_id'] = Config::get('constants.appointment_status_not_interested');
                    $appointment_childstatus_not_interested = AppointmentStatuses::where('parent_id', '=', Config::get('constants.appointment_status_not_interested'))->first();
                    if ($appointment_childstatus_not_interested) {
                        $data['appointment_status_id'] = $appointment_childstatus_not_interested->id;
                    } else {
                        $data['appointment_status_id'] = Config::get('constants.appointment_status_not_interested');
                    }
                }
            }
        }
        $appointment->update($data);
        $appointment_status_name = AppointmentStatuses::where('id', '=', $data['base_appointment_status_id'])->first();

        /** When appointment status will be 'No Show' then lead status will be automatically changed to 'Open' */
        if ($data['base_appointment_status_id'] == 3) {
            $openStatus = LeadStatuses::where(['account_id' => Auth::User()->account_id, 'is_default' => 1])->first();
            if ($openStatus && $appointment->lead_id) {
                $lead = Leads::findOrFail($appointment->lead_id);
                $lead->lead_status_id = $openStatus->id;
                $lead->save();
                // Update lead_services status to Open (No Show)
                LeadsServices::where([
                    'lead_id' => $appointment->lead_id,
                    'service_id' => $appointment->service_id,
                ])->update(['lead_status_id' => $openStatus->id]);
            }
        }
        if ($data['base_appointment_status_id'] == 1) {
            $bookedStatus = LeadStatuses::where(['account_id' => Auth::User()->account_id, 'is_booked' => 1])->first();
            if ($bookedStatus && $appointment->lead_id) {
                $lead = Leads::findOrFail($appointment->lead_id);
                $lead->lead_status_id = $bookedStatus->id;
                $lead->save();
                // Update lead_services status to Booked
                LeadsServices::where([
                    'lead_id' => $appointment->lead_id,
                    'service_id' => $appointment->service_id,
                ])->update(['lead_status_id' => $bookedStatus->id]);
            }
        }
        if ($data['base_appointment_status_id'] == 14) {
            $arrivedStatus = LeadStatuses::where(['account_id' => Auth::User()->account_id, 'is_arrived' => 1])->first();
            if ($arrivedStatus && $appointment->lead_id) {
                Leads::where(['id' => $appointment->lead_id])->update(['lead_status_id' => $arrivedStatus->id]);
                // Update lead_services status to Arrived
                LeadsServices::where([
                    'lead_id' => $appointment->lead_id,
                    'service_id' => $appointment->service_id,
                ])->update(['lead_status_id' => $arrivedStatus->id]);
                
                // Send Meta CAPI event for arrived status
                // $leadRecord = Leads::find($appointment->lead_id);
                // if ($leadRecord) {
                //     try {
                //         $metaService = new MetaConversionApiService();
                //         $metaService->sendLeadStatus(
                //             $leadRecord->phone,
                //             'arrived',
                //             $leadRecord->meta_lead_id,
                //             $leadRecord->email
                //         );
                //     } catch (\Exception $e) {
                //         \Log::error('Meta CAPI arrived event failed: ' . $e->getMessage());
                //     }
                // }
            }
        }

        /**
         * Dispatch Elastic Search Index
         */
        $this->dispatch(
            new IndexSingleAppointmentJob([
                'account_id' => Auth::User()->account_id,
                'appointment_id' => $appointment->id,
            ])
        );
        
        // Log appointment status change activity
        $newStatus = AppointmentStatuses::find($data['base_appointment_status_id']);
        if ($oldStatus && $newStatus && $oldStatusId != $data['base_appointment_status_id']) {
            $patient = Patients::find($appointment->patient_id);
            $location = Locations::with('city')->find($appointment->location_id);
            $service = Services::find($appointment->service_id);
            ActivityLogger::logAppointmentStatusChange($appointment, $patient, $oldStatus, $newStatus, $location, $service);
        }

        return ApiHelper::apiResponse($this->success, 'Status has been change successfully!', true, ['appontment_type_id' => $request->appointment_type_id]);
    }

    /**
     * Load Appointment SMS History.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function showSMSLogs($id)
    {
        $SMSLogs = SMSLogs::whereAppointmentId($id)->orderBy('created_at', 'desc')->get();

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'SMSLogs' => $SMSLogs,
            'sms_statuses' => config('constants.sms_array'),
        ]);
    }

    /**
     * Re-send Appointment SMS
     *
     * @param  \App\Http\Requests\Admin\StoreUpdateAppointmentsRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendLogSMS(Request $request)
    {
        $data = $request->all();
        $SMSLog = SMSLogs::find($request->id);
        if (! $SMSLog) {
            return ApiHelper::apiResponse($this->success, 'Resource not found', false);
        }
        if ($SMSLog) {
            $response = $this->resendSMS($SMSLog->id, $SMSLog->to, $SMSLog->text, $SMSLog->appointment_id);

            if ($response['status']) {
                return ApiHelper::apiResponse($this->success, 'SMS sent successfully.');
            }
        }

        return ApiHelper::apiResponse($this->success, 'Failed to send SMS.', false);
    }

    private function resendSMS($smsId, $patient_phone, $preparedText, $appointmentId)
    {
        $appointment = Appointments::find($appointmentId);
        $setting = Settings::whereSlug('sys-current-sms-operator')->first();
        $UserOperatorSettings = UserOperatorSettings::getRecord($appointment->account_id, $setting->data);
        if ($setting->data == 1) {
            $SMSObj = [
                'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
                'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
                'to' => $patient_phone,
                'text' => $preparedText,
                'mask' => $UserOperatorSettings->mask, // Setting ID 3 for Mask
                'test_mode' => $UserOperatorSettings->test_mode, // Setting ID 3 Test Mode
            ];
            $response = TelenorSMSAPI::SendSMS($SMSObj);
        } else {
            $SMSObj = [
                'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
                'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
                'from' => $UserOperatorSettings->mask,
                'to' => $patient_phone,
                'text' => $preparedText,
                'test_mode' => $UserOperatorSettings->test_mode, // Setting ID 3 Test Mode
            ];
            $response = JazzSMSAPI::SendSMS($SMSObj);
        }
        if ($response['status']) {
            SMSLogs::find($smsId)->update(['status' => 1]);
        }

        return $response;
    }
    /*
     * Send SMS on booking of Appointment
     *
     * @param: int $appointmentId
     * @param: string $patient_phone
     * @return: array|mixture
     */

    public function loadLocationsByCity(Request $request)
    {

        try {
            if ($request->city_id) {
                if ($request->machine_type_allocation) {
                    if ($request->appointment_manage == Config::get('constants.appointment_type_service_string')) {
                        $reverse_process = true;
                    } else {
                        $reverse_process = false;
                    }
                    $locationsids = [];
                    $locations = Locations::getActiveRecordsByCity($request->city_id, ACL::getUserCentres(), Auth::User()->account_id);
                    /*For machine type we perform that work we can remove it if any problem happen but for linkage that is best*/
                    foreach ($locations as $location) {
                        $location_serivce = AppointmentEditWidget::loadlocationservice_edit($location->id, Auth::User()->account_id, $reverse_process);
                        if (in_array($request->service_id, $location_serivce)) {
                            $locationsids[] = $location->id;
                        }
                    }
                    $locations = Locations::whereIn('id', $locationsids)->get();
                    if ($locations) {
                        $locations = $locations->pluck('name', 'id');
                    }

                } else {
                    $locations = Locations::getActiveRecordsByCity($request->city_id, ACL::getUserCentres(), Auth::User()->account_id);
                    if ($locations) {
                        $locations = $locations->pluck('name', 'id');
                    }
                }

                return ApiHelper::apiResponse($this->success, 'Record found', true, [
                    'dropdown' => $locations,
                ]);
            }
            $assigned_locations = ACL::getUserCentres();
            $locations = Locations::getActiveRecordsByCity('', ACL::getUserCentres(), Auth::User()->account_id);

            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'dropdown' => $locations->pluck('name', 'id'),
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    public function LoadChildServices(Request $request)
    {
        try {
            if ($request->serviceId) {
                $child_services = Services::where(['parent_id' => $request->serviceId, 'active' => 1])->get();
                if ($child_services) {
                    $child_services = $child_services->pluck('name', 'id');
                }
            }

            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'dropdown' => $child_services,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
    /*
     * Load Locations by City
     *
     * @oaran \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */

    public function loadDoctorsByLocation(Request $request)
    {
        try {
            if ($request->location_id) {
                if ($request->machine_type_allocation) {
                    $doctors = $doctors_no_final = LocationsWidget::loadAppointmentDoctorByLocation($request->location_id, Auth::User()->account_id);
                    if ($request->appointment_manage == Config::get('constants.appointment_type_service_string')) {
                        $reverse_process = true;
                    } else {
                        $reverse_process = false;
                    }
                    $doctorids = [];
                    /*For machine type we perform that work we can remove it if any problem happen but for linkage that is best*/
                    foreach ($doctors as $key => $doctor) {
                        $doctor_serivce = AppointmentEditWidget::loaddoctorservice_edit($key, $request->location_id, Auth::User()->account_id, $reverse_process);
                        if (in_array($request->service_id, $doctor_serivce)) {
                            $doctorids[] = $key;
                        }
                    }
                    $doctors = $doctors_no_final = Doctors::whereIn('id', $doctorids)->get()->pluck('name', 'id');
                } else {
                    $doctors = LocationsWidget::loadAppointmentDoctorByLocation($request->location_id, Auth::User()->account_id);
                }

                return ApiHelper::apiResponse($this->success, 'Record found', true, [
                    'dropdown' => $doctors,
                ]);
            }

            return ApiHelper::apiResponse($this->success, 'Record found', false, [
                'dropdown' => null,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
    public function loadConsultantDoctorsByLocation(Request $request)
    {
        try {
            if ($request->location_id) {
                // Use the proper consultant doctor loader that filters by doctor_has_locations with is_allocated = 1
                $doctors = LocationsWidget::loadConsultantDoctorByLocation($request->location_id, Auth::User()->account_id);

                return ApiHelper::apiResponse($this->success, 'Record found', true, [
                    'dropdown' => $doctors->toArray(),
                ]);
            }

            return ApiHelper::apiResponse($this->success, 'Record found', false, [
                'dropdown' => null,
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    /*
     * Load Locations by City
     *
     * @oaran \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */

    public function loadServiceByLocation(Request $request)
    {
        if ($request->location_id) {
            $doctors = LocationsWidget::loadAppointmentDoctorByLocation($request->location_id, Auth::User()->account_id);
            //$doctors = Doctors::getActiveOnly($request->location_id);
            $doctors->prepend('Select a Doctor', '');

            return response()->json([
                'status' => 1,
                'dropdown' => view('admin.appointments.dropdowns.doctors', compact('doctors'))->render(),
            ]);
        } else {
            return response()->json([
                'status' => 0,
                'dropdown' => null,
            ]);
        }
    }
    /*
     * Load Resource Rota Day by Doctor
     *
     * @oaran \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */

    public function loadRotaByDoctor(Request $request)
    {
        if (
            $request->doctor_id &&
            $request->appointment_id &&
            $request->scheduled_date &&
            $request->resourceRotaDayID
        ) {
            $appointment = Appointments::find($request->appointment_id);
            if ($request->resourceRotaDayID != $appointment->resource_has_rota_day_id) {
                /*
                    * Data is changed, avoid to provide rota
                    */
                return response()->json([
                    'status' => 0,
                    'resource_has_rota_day' => null,
                    'machine_has_rota_day' => null,
                    'selected' => null,
                ]);
            }
            /**
             * Location Information
             */
            $location_id = $request->location_id;
            $doctor = User::findOrFail($request->doctor_id);
            $resource = Resources::where([
                'external_id' => $doctor->id,
                'resource_type_id' => Config::get('constants.resource_doctor_type_id'),
                'account_id' => Auth::User()->account_id,
            ])->first();
            if ($resource) {
                if ($appointment->appointment_type_id == Config::get('constants.appointment_type_consultancy')) {
                    /*
                     * Consultancy: Grab Rota day info
                     */
                    $resource_has_rota_day = ResourceHasRotaDays::getSingleDayRotaWithResourceID($resource->id, $request->scheduled_date, Auth::User()->account_id, $location_id);
                    if (count($resource_has_rota_day)) {
                        if ($resource_has_rota_day['start_time'] && $resource_has_rota_day['end_time'] && $appointment->scheduled_time) {
                            $selected = (ResourceHasRota::checkTime(Carbon::parse($appointment->scheduled_time)->format('h:i A'), $resource_has_rota_day['start_time'], $resource_has_rota_day['end_time'], true)) ? Carbon::parse($appointment->scheduled_time)->format('h:i A') : '';
                            $resource_has_rota_day['start_time'] = Carbon::parse($resource_has_rota_day['start_time'])->format('h:ia');
                            $resource_has_rota_day['end_time'] = Carbon::parse($resource_has_rota_day['end_time'])->subMinutes($appointment->service->duration_in_minutes)->format('h:ia');

                            if ($resource_has_rota_day['start_off']) {
                                $resource_has_rota_day['start_off'] = Carbon::parse($resource_has_rota_day['start_off'])->subMinutes($appointment->service->duration_in_minutes)->addMinute('5')->format('h:ia');
                                $resource_has_rota_day['end_off'] = Carbon::parse($resource_has_rota_day['end_off'])->format('h:ia');
                            } else {
                                $resource_has_rota_day['start_off'] = null;
                                $resource_has_rota_day['end_off'] = null;
                            }
                        } else {
                            $selected = '';
                        }

                        return response()->json([
                            'status' => 1,
                            'resource_has_rota_day' => $resource_has_rota_day,
                            'machine_has_rota_day' => $resource_has_rota_day,
                            'selected' => ($selected) ? Carbon::parse($selected)->format('g:ia') : null,
                        ]);
                    }
                } else {
                    $resource_id = $request->machine_id;
                    if (($request->machineRotaDayID != $appointment->resource_has_rota_day_id_for_machine) || ! $resource_id) {
                        /*
                         * Data is changed, avoid to provide rota
                         */
                        return response()->json([
                            'status' => 0,
                            'resource_has_rota_day' => null,
                            'machine_has_rota_day' => null,
                            'selected' => null,
                        ]);
                    }
                    /*
                     * Treatment: Find overlapped doctor and machine area
                     */
                    $resource_has_rota_day = ResourceHasRotaDays::getSingleDayRotaWithResourceID($resource->id, $request->scheduled_date, Auth::User()->account_id, $location_id);
                    $machine_has_rota_day = ResourceHasRotaDays::getSingleDayRotaWithResourceID($resource_id, $request->scheduled_date, Auth::User()->account_id, $location_id);
                    if (count($resource_has_rota_day) && count($machine_has_rota_day)) {
                        if (
                            ($resource_has_rota_day['start_time'] && $resource_has_rota_day['end_time']) &&
                            ($machine_has_rota_day['start_time'] && $machine_has_rota_day['end_time']) &&
                            $appointment->scheduled_time
                        ) {
                            $biggerTime = ResourceHasRota::getBiggerTime($resource_has_rota_day['start_time'], $machine_has_rota_day['start_time']);
                            $smallerTime = ResourceHasRota::getSmallerTime($resource_has_rota_day['end_time'], $machine_has_rota_day['end_time']);
                            $selected = (ResourceHasRota::checkTime(Carbon::parse($appointment->scheduled_time)->format('h:i A'), $biggerTime, $smallerTime, true)) ? Carbon::parse($appointment->scheduled_time)->format('h:i A') : '';
                            $resource_has_rota_day['start_time'] = Carbon::parse($biggerTime)->format('h:ia');
                            $resource_has_rota_day['end_time'] = Carbon::parse($smallerTime)->subMinutes($appointment->service->duration_in_minutes)->format('h:ia');

                            if ($resource_has_rota_day['start_off']) {
                                $resource_has_rota_day['start_off'] = Carbon::parse($resource_has_rota_day['start_off'])->subMinutes($appointment->service->duration_in_minutes)->addMinute('5')->format('h:ia');
                                $resource_has_rota_day['end_off'] = Carbon::parse($resource_has_rota_day['end_off'])->format('h:ia');
                            } else {
                                $resource_has_rota_day['start_off'] = null;
                                $resource_has_rota_day['end_off'] = null;
                            }
                        } else {
                            $selected = '';
                        }

                        return response()->json([
                            'status' => 1,
                            'resource_has_rota_day' => $resource_has_rota_day,
                            'machine_has_rota_day' => $resource_has_rota_day,
                            'selected' => ($selected) ? Carbon::parse($selected)->format('g:ia') : null,
                        ]);
                    }
                }
            }
        }

        return response()->json([
            'status' => 0,
            'resource_has_rota_day' => null,
            'machine_has_rota_day' => null,
            'selected' => null,
        ]);
    }
    /*
     * Load Doctors by Location
     *
     * @oaran \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */

    public function getNonScheduledAppointments(Request $request)
    {
        if (
            $request->city_id &&
            $request->location_id &&
            $request->doctor_id
        ) {
            $appointments = Appointments::getNonScheduledAppointments($request, Config::get('constants.appointment_type_consultancy'), Auth::User()->account_id);
            if ($appointments) {
                $data = [];
                foreach ($appointments as $appointment) {
                    $data[$appointment->id] = [
                        'id' => $appointment->id,
                        'service' => $appointment->service->name,
                        'patient' => ($appointment->name) ? $appointment->name : $appointment->patient->name,
                        'created_by' => ($appointment->created_by) ? $appointment->user->name : '',
                        'phone' => Gate::allows('contact') ? GeneralFunctions::prepareNumber4Call($appointment->patient->phone) : '***********',
                        'duration' => $appointment->service->duration,
                        'editable' => true,
                        'overlap' => false,
                        'color' => $appointment->service->color,
                        'resourceId' => $appointment->doctor_id,
                    ];
                }

                return response()->json([
                    'status' => 1,
                    'events' => $data,
                ]);
            } else {
                return response()->json([
                    'status' => 0,
                    'events' => null,
                ]);
            }
        } else {
            return response()->json([
                'status' => 0,
                'events' => null,
            ]);
        }
    }
    /*
     * Load Appointments
     *
     * @oaran \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */

     public function getScheduledAppointments(Request $request)
     {
         if ($request->location_id) {
            $appointments = Appointments::getScheduledAppointments($request, Config::get('constants.appointment_type_consultancy'), Auth::User()->account_id);
            $start = $request->start;
             $end = $request->end;
             if ($request->doctor_id) {
                 $doctor_rotas = Resources::getDoctorWithRotas($request->location_id, $request->doctor_id, $request->start, $request->end);
             }
             $location_id = $request->location_id;
             $doctor_id = $request->doctor_id;
             $machine_id = $request->machine_id;
             $minTime = Resources::getMinTimeWithDr($location_id, $doctor_id, $start, $end);
             if ($appointments) {
                 $data = [];
                 foreach ($appointments as $appointment) {
                     $dutation = explode(':', $appointment?->service?->duration ?? '');
                     $data[$appointment->id] = [
                         'id' => $appointment->id,
                         'service' => $appointment?->service?->name ?? '',
                         'patient' => ($appointment->name) ? $appointment->name : $appointment->patient->name,
                         'created_by' => ($appointment->created_by) ? $appointment->user->name : '',
                         'phone' => Gate::allows('contact') ? GeneralFunctions::prepareNumber4Call($appointment?->patient?->phone ?? '0300') : '***********',
                         'duration' => $appointment?->service?->duration ?? '00',
                         'editable' => true,
                         'overlap' => false,
                         'start' => Carbon::parse($appointment->scheduled_date, null)->format('Y-m-d').' '.Carbon::parse($appointment->scheduled_time, null)->format('H:i'),
                         'end' => Carbon::parse($appointment->scheduled_date, null)->format('Y-m-d').' '.Carbon::parse($appointment->scheduled_time, null)->addHours($dutation[0] ?? 0)->addMinutes($dutation[1] ?? 0)->format('H:i'),
                         'color' => $appointment?->service?->color ?? '#fff',
                         'resourceId' => $appointment->doctor_id,
                     ];
                 }
                 if ($request->doctor_id) {
                     return response()->json([
                         'status' => 1,
                         'events' => $data,
                         'min_time' => $minTime,
                         'rotas' => isset($doctor_rotas) ? $doctor_rotas->toArray() : '',
                         'start_time' => \Illuminate\Support\Carbon::parse($doctor_rotas->pluck('doctor_rotas')->flatten(1)->min('start_time'))->format('H:i:s'),
                         'end_time' => \Illuminate\Support\Carbon::parse($doctor_rotas->pluck('doctor_rotas')->flatten(1)->max('end_time'))->format('H:i:s'),

                     ]);
                 } else {
                     return response()->json([
                         'status' => 1,
                         'events' => $data,
                         'min_time' => $minTime,
                         'rotas' => isset($doctor_rotas) ? $doctor_rotas->toArray() : '',
                         'start_time' => '10:00',
                         'end_time' => '23:00',
                     ]);
                 }
             } else {
                 return response()->json([
                     'status' => 0,
                     'events' => null,
                 ]);
             }
         } else {
             return response()->json([
                 'status' => 0,
                 'events' => null,
             ]);
         }
     }

    /*
     * check and save Consulting appointment

     *
     * @oaran \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    public function checkAndSaveAppointments(Request $request)
    {
        $appointment_checkes = AppointmentCheckesWidget::AppointmentConsultancyCheckes($request);
        if ($appointment_checkes['status']) {
            $doctor_check_availability = Resources::checkDoctorAvailbility($request);
            if (
                $request->id &&
                $request->start &&
                $request->doctor_id &&
                $request->end
            ) {
                if ($doctor_check_availability) {
                    // Appointment Data
                    $data = $request->all();
                    $data['reschedule'] = 1;
                    $appointment = Appointments::findOrFail($request->id);

                    // Validate that the doctor has the service allocated at this location
                    \Log::info('checkAndSaveAppointments: Validating service allocation', [
                        'appointment_id' => $appointment->id,
                        'doctor_id' => $request->doctor_id,
                        'location_id' => $appointment->location_id,
                        'service_id' => $appointment->service_id,
                    ]);
                    
                    // Check if doctor has the appointment's service allocated
                    $hasService = \DB::table('doctor_has_locations')
                        ->where('user_id', $request->doctor_id)
                        ->where('location_id', $appointment->location_id)
                        ->where('service_id', $appointment->service_id)
                        ->where('is_allocated', 1)
                        ->exists();

                    \Log::info('checkAndSaveAppointments: Validation result', [
                        'has_service' => $hasService,
                        'will_block' => !$hasService,
                    ]);

                    if (!$hasService) {
                        \Log::warning('checkAndSaveAppointments: Blocking update - doctor does not have service');
                        return ApiHelper::apiResponse($this->success, 'This doctor does not have the required service allocated for this location.', false);
                    }
                    // Store old values for activity logging
                    $oldDate = $appointment->scheduled_date;
                    $oldTime = $appointment->scheduled_time;
                    $oldDoctorId = $appointment->doctor_id;
                    
                    $data['first_scheduled_count'] = $appointment->first_scheduled_count;
                    $data['scheduled_at_count'] = $appointment->scheduled_at_count;
                    
                    unset($data['resource_id']);
                    unset($data['resource_has_rota_day_id']);
                    unset($data['resource_has_rota_day_id_for_machine']);
                    $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();
                    $invoice = Invoices::where([
                        ['appointment_id', '=', $appointment->id],
                        ['invoice_status_id', '=', $invoicestatus->id],
                    ])->get();
                    if (count($invoice) > 0) {
                        return ApiHelper::apiResponse($this->success, 'Appointment has invoice.', false);
                    }
                    $record = Appointments::updateRecord($request->id, $data, Auth::User()->account_id);
                    if ($record) {
                        /*
                         * Set Appointment Status 'pending' and set send message flag
                         */
                        $appointment_status = AppointmentStatuses::getADefaultStatusOnly(Auth::User()->account_id);
                        if ($appointment_status) {
                            $record->update([
                                'appointment_status_id' => $appointment_status->id,
                                'base_appointment_status_id' => $appointment_status->id,
                                'appointment_status_allow_message' => $appointment_status->allow_message,
                                'send_message' => 1, // Set flag 1 to send message on cron job
                            ]);
                        }
                        /**
                         * Dispatch Elastic Search Index
                         */
                        $this->dispatch(
                            new IndexSingleAppointmentJob([
                                'account_id' => Auth::User()->account_id,
                                'appointment_id' => $appointment->id,
                            ])
                        );

                        // Log activity for date, time, or doctor changes
                        $newDate = Carbon::parse($request->start)->format('Y-m-d');
                        $newTime = Carbon::parse($request->start)->format('H:i:s');
                        $newDoctorId = $request->doctor_id;
                        
                        $fieldChanges = [];
                        
                        // Check date change
                        if ($oldDate != $newDate) {
                            $fieldChanges['Date'] = [
                                'old' => Carbon::parse($oldDate)->format('M j, Y'),
                                'new' => Carbon::parse($newDate)->format('M j, Y')
                            ];
                        }
                        
                        // Check time change
                        if ($oldTime != $newTime) {
                            $fieldChanges['Time'] = [
                                'old' => Carbon::parse($oldTime)->format('h:i A'),
                                'new' => Carbon::parse($newTime)->format('h:i A')
                            ];
                        }
                        
                        // Check doctor change
                        if ($oldDoctorId != $newDoctorId) {
                            $oldDoctor = Doctors::find($oldDoctorId);
                            $newDoctor = Doctors::find($newDoctorId);
                            $fieldChanges['Doctor'] = [
                                'old' => $oldDoctor->name ?? 'Unknown',
                                'new' => $newDoctor->name ?? 'Unknown'
                            ];
                        }
                        
                        // Log if any changes were made
                        if (!empty($fieldChanges)) {
                            $patient = Patients::find($record->patient_id);
                            $location = Locations::with('city')->find($record->location_id);
                            $service = Services::find($record->service_id);
                            ActivityLogger::logAppointmentUpdated($record, $patient, $fieldChanges, $location, $service);
                        }

                        return ApiHelper::apiResponse($this->success, 'Appointment Updated Successfully');
                    }
                }

                return ApiHelper::apiResponse($this->success, 'Doctor is not available', false);
            }

            return ApiHelper::apiResponse($this->success, 'Invalid paramters', false);
        }

        return ApiHelper::apiResponse($this->success, $appointment_checkes['message'], false);
    }
    /*
     * Save Appointment Data
     *
     * @oaran \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */

    public function loadAppointmentStatuses(Request $request)
    {
        if ($request->appointment_status_id) {
            $appointment_statuses = AppointmentStatuses::getActiveSorted($request->appointment_status_id, Auth::User()->account_id);
            $appointment_status = AppointmentStatuses::find($request->appointment_status_id);
            if ($appointment_status) {
                $appointment_status = $appointment_status->toArray();
            }

            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'dropdown' => count($appointment_statuses) > 0 ? $appointment_statuses : null,
                'count' => count($appointment_statuses),
                'appointment_status' => $appointment_status,
            ]);
        }

        return ApiHelper::apiResponse($this->success, 'Record found', false, [
            'dropdown' => null,
            'count' => 0,
            'appointment_status' => null,
        ]);
    }
    /*
     * Load Statuses
     *
     * @oaran \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */

    public function loadAppointmentStatusData(Request $request)
    {
        if ($request->appointment_status_id && $request->base_appointment_status_id) {
            $appointment_status = AppointmentStatuses::find($request->appointment_status_id);
            if ($appointment_status) {
                $appointment_status = $appointment_status->toArray();
            }
            $base_appointment_status = AppointmentStatuses::find($request->base_appointment_status_id);
            if ($base_appointment_status) {
                $base_appointment_status = $base_appointment_status->toArray();
            }

            return ApiHelper::apiResponse($this->success, 'Record Found', true, [
                'appointment_status' => count($appointment_status) > 0 ? $appointment_status : null,
                'base_appointment_status' => count($base_appointment_status) > 0 ? $base_appointment_status : null,
            ]);
        }

        return ApiHelper::apiResponse($this->success, 'Record Found', false, [
            'appointment_status' => null,
            'base_appointment_status' => null,
        ]);
    }
    /*
     * Create Invoice index
     *
     * @oaran $id
     *
     * @return mixed
     */

    public function invoice($id)
    {
        
        if (! Gate::allows('appointments_manage') && ! Gate::allows('appointments_view')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $invoice_status = InvoiceStatuses::where('slug', '=', 'paid')->first();
        $invoice = Invoices::where([
            ['appointment_id', '=', $id],
            ['invoice_status_id', '=', $invoice_status->id],
        ])->first();
        if ($invoice == null) {
            $price = 0;
            $packages = null;
            $amount_create_is_inclusive = 0;
            $status = 'true';
            $appointment = Appointments::find($id);
            $balance = 0;
            $appointment_type = AppointmentTypes::find($appointment->appointment_type_id);
            $service = Services::find($appointment->service_id);
            /*In case of treatment not belongs to treatment plans So i set but must be null in case of consultancy and treatment plans*/
            $amount_create = 0;
            $tax_create = 0;
            $location_id = 0;
            $checked_treatment = 0;
            $appointmentArray = [];
            $service_in_plan = false;
            if ($appointment_type->name == Config::get('constants.Service')) {
                /*Check if service has */
                // Check if service exists in any plan (consumed or not)
                $serviceInAnyPlan = DB::table('packages')
                    ->leftjoin('package_services', 'packages.id', '=', 'package_services.package_id')
                    ->where([
                        'packages.active' => '1',
                        'packages.patient_id' =>  $appointment->patient_id,
                        'package_services.service_id' => $appointment->service_id,
                        'packages.location_id' => $appointment->location_id,
                    ])->exists();
                
                $service_in_plan = $serviceInAnyPlan;
                
                $packages = DB::table('packages')
                    ->leftjoin('package_services', 'packages.id', '=', 'package_services.package_id')
                    ->where([
                        'packages.active' => '1',
                        'packages.patient_id' =>  $appointment->patient_id,
                        'package_services.service_id' => $appointment->service_id,
                        'package_services.is_consumed' => '0',
                        'packages.location_id' => $appointment->location_id,
                    ])->select('packages.id', 'packages.name')->groupby('packages.id')->orderBy('packages.id', 'desc')->get();
                      
                    $status = 'true';
                if (count($packages) <= 0) {
                    $location_information = Locations::find($appointment->location_id);
                    $location_id = $appointment->location_id;
                    $serviceinfo = Services::where('id', '=', $appointment->service_id)->first();
                    
                    // Get service price from package_services.actual_price if exists, otherwise use services.price
                    $service_price = $serviceinfo->price;
                    if ($service_in_plan) {
                        $package_service = DB::table('package_services')
                            ->join('packages', 'package_services.package_id', '=', 'packages.id')
                            ->where([
                                'packages.active' => '1',
                                'packages.patient_id' => $appointment->patient_id,
                                'package_services.service_id' => $appointment->service_id,
                                'packages.location_id' => $appointment->location_id,
                            ])
                            ->select('package_services.actual_price')
                            ->first();
                        
                        if ($package_service && $package_service->actual_price !== null) {
                            $service_price = $package_service->actual_price;
                        }
                    }
                    
                    if ($serviceinfo->tax_treatment_type_id == Config::get('constants.tax_both') || $serviceinfo->tax_treatment_type_id == Config::get('constants.tax_is_exclusive')) {
                        $amount_create = $amount_create_is_inclusive = $service_price;
                        $tax_create = ceil($service_price * ($location_information->tax_percentage / 100));
                        $price = ceil($amount_create + (($amount_create * $location_information->tax_percentage) / 100));

                    } else {
                        $price = $amount_create_is_inclusive = $service_price;
                        $amount_create = ceil((100 * $price) / ($location_information->tax_percentage + 100));
                        $tax_create = ceil($price - $amount_create);
                    }
                    $checked_treatment = 1;
                    $status = 'false';
                    $data['patient_id'] = $appointment->patient_id;
                    $data['location_id'] = $appointment->location_id;
                    $data = (object) $data;
                    $appointmentArray = PlanAppointmentCalculation::tagAppointments($data);
                }
            }
            $cash = 0;
            $outstanding = $price - $cash - $balance;
            if ($outstanding < 0) {
                $outstanding = 0;
            }
            $settleamount_1 = $price - $cash;
            $settleamount = min($settleamount_1, $balance);
            $invoice_status = false;
        } else {
            $invoice_status = true;
            $price = null;
            $packages = null;
            $appointment_type = null;
            $status = null;
            $service = null;
            $balance = null;
            $settleamount = null;
            $outstanding = null;
            $amount_create = null;
            $tax_create = null;
            $location_id = null;
            $checked_treatment = null;
        }

        $paymentmodes = PaymentModes::where('type', '=', 'application')->pluck('name', 'id');
        $paymentmodes->prepend('Select', '0');
        
        // Get patient name for modal title
        $patient = null;
        if (isset($appointment)) {
            $patient = User::find($appointment->patient_id);
        }

        return view('admin.appointments.invoice_create', compact('price', 'packages', 'appointment_type', 'status', 'id', 'service', 'balance', 'settleamount', 'outstanding', 'invoice_status', 'paymentmodes', 'tax_create', 'amount_create', 'location_id', 'checked_treatment', 'appointmentArray', 'amount_create_is_inclusive', 'service_in_plan', 'patient'));
    }

    /*
     * Load plans information
     *
     * @oaran $request
     *
     * @return mixed
     */
    public function getplansinformation(Request $request)
    {
        // Validate input parameters
        $appointmentId = $request->appointment_id_create;
        $packageId = $request->package_id_create;

        if (!$appointmentId || !$packageId) {
            return response()->json([
                'status' => true,
                'packagebundles' => [],
                'packageservices' => [],
            ]);
        }

        // Get only the service_id we need from appointment
        $appointmentinfo = Appointments::select('service_id')->find($appointmentId);

        if (!$appointmentinfo) {
            return response()->json([
                'status' => true,
                'packagebundles' => [],
                'packageservices' => [],
            ]);
        }

        $serviceId = $appointmentinfo->service_id;

        // Get bundle IDs using pluck instead of loop
        $bundleIds = Bundles::join('bundle_has_services', 'bundles.id', '=', 'bundle_has_services.bundle_id')
            ->where('bundle_has_services.service_id', '=', $serviceId)
            ->pluck('bundles.id')
            ->toArray();

        // Check if package exists
        $package = Packages::select('id')->find($packageId);

        if (!$package) {
            return response()->json([
                'status' => true,
                'packagebundles' => [],
                'packageservices' => [],
            ]);
        }

        // Get package bundles — two paths:
        // 1. Bundle-type: bundle_id is a real bundles.id (JOIN works)
        // 2. Plan-type: bundle_id stores service_id (JOIN fails, use LEFT JOIN with service name)
        $bundleTypeBundles = collect();
        if (!empty($bundleIds)) {
            $bundleTypeBundles = PackageBundles::join('bundles', 'package_bundles.bundle_id', '=', 'bundles.id')
                ->where('package_bundles.package_id', '=', $package->id)
                ->whereIn('package_bundles.bundle_id', $bundleIds)
                ->select('package_bundles.*', 'package_bundles.discount_name as discountname', 'bundles.name as bundlename')
                ->get();
        }

        // Plan-type bundles: bundle_id = service_id, get service name instead
        $bundleTypeBundleIds = $bundleTypeBundles->pluck('id')->toArray();
        $planTypeBundles = PackageBundles::leftJoin('services', 'package_bundles.bundle_id', '=', 'services.id')
            ->where('package_bundles.package_id', '=', $package->id)
            ->whereNotIn('package_bundles.id', $bundleTypeBundleIds)
            ->whereHas('packageservice', function ($q) use ($serviceId) {
                $q->where('service_id', $serviceId);
            })
            ->select('package_bundles.*', 'package_bundles.discount_name as discountname', 'services.name as bundlename')
            ->get();

        $packagebundles = $bundleTypeBundles->merge($planTypeBundles);

        // Get package services with config_group_id from package_bundles
        $packageservices = PackageService::join('services', 'package_services.service_id', '=', 'services.id')
            ->leftJoin('package_bundles', 'package_services.package_bundle_id', '=', 'package_bundles.id')
            ->where('package_services.package_id', '=', $package->id)
            ->where('package_services.service_id', '=', $serviceId)
            ->select('package_services.*', 'services.name as servicename', 'package_bundles.config_group_id')
            ->get();

        // Calculate total payments for this plan (for consumption lock checks)
        $totalPlanPayments = PackageAdvances::where('package_id', $package->id)
            ->where('cash_flow', 'in')
            ->sum('cash_amount');

        // Calculate total already consumed value
        $totalConsumedValue = PackageService::where('package_id', $package->id)
            ->where('is_consumed', 1)
            ->sum('tax_including_price');

        // Get all package services in same config groups for ordering checks
        $configGroupIds = $packageservices->pluck('config_group_id')->filter()->unique()->values();
        $configGroupServices = [];
        if ($configGroupIds->isNotEmpty()) {
            $configGroupServices = PackageService::join('services', 'package_services.service_id', '=', 'services.id')
                ->leftJoin('package_bundles', 'package_services.package_bundle_id', '=', 'package_bundles.id')
                ->where('package_services.package_id', '=', $package->id)
                ->whereIn('package_bundles.config_group_id', $configGroupIds)
                ->select('package_services.id', 'package_services.is_consumed', 'package_services.consumption_order', 'package_services.tax_including_price', 'package_bundles.config_group_id', 'services.name as servicename')
                ->get()
                ->groupBy('config_group_id')
                ->toArray();
        }

        // Check if plan is fully paid (total payments >= total plan value)
        $totalPlanValue = PackageService::where('package_id', $package->id)
            ->sum('tax_including_price');
        $isPlanFullyPaid = ($totalPlanPayments >= $totalPlanValue);

        return response()->json([
            'status' => true,
            'packagebundles' => $packagebundles,
            'packageservices' => $packageservices,
            'total_plan_payments' => (float) $totalPlanPayments,
            'total_consumed_value' => (float) $totalConsumedValue,
            'is_plan_fully_paid' => $isPlanFullyPaid,
            'config_group_services' => $configGroupServices,
        ]);
    }
    /*
     * Load Invoice information
     *
     * @oaran $request
     *
     * @return mixed
     */

     public function getpackageprice(Request $request)
     {
         // Validate input parameters
         $appointmentId = $request->appointment_id_create;
         $packageId = $request->package_id_create;
         $packageServiceId = $request->package_service_id;

         if (!$appointmentId || !$packageId || !$packageServiceId) {
             return response()->json([
                 'status' => false,
                 'message' => 'Missing required parameters',
             ]);
         }

         // Get only patient_id from appointment
         $appointmentinfo = Appointments::select('patient_id')->find($appointmentId);

         if (!$appointmentinfo) {
             return response()->json([
                 'status' => false,
                 'message' => 'Appointment not found',
             ]);
         }

         $patientId = $appointmentinfo->patient_id;

         // Calculate balance using single query with conditional sum
         $balanceData = PackageAdvances::where('patient_id', '=', $patientId)
             ->where('package_id', '=', $packageId)
             ->selectRaw("
                 SUM(CASE WHEN cash_flow = 'in' THEN cash_amount ELSE 0 END) as total_in,
                 SUM(CASE WHEN cash_flow = 'out' THEN cash_amount ELSE 0 END) as total_out
             ")
             ->first();

         $balance = ceil(($balanceData->total_in ?? 0) - ($balanceData->total_out ?? 0));

         // Get package service
         $package_service = PackageService::find($packageServiceId);

         if (!$package_service) {
             return response()->json([
                 'status' => false,
                 'message' => 'Package service not found',
             ]);
         }

         // Get package bundle
         $package_bundle = PackageBundles::find($package_service->package_bundle_id);

         if (!$package_bundle) {
             return response()->json([
                 'status' => false,
                 'message' => 'Package bundle not found',
             ]);
         }

         // Get bundle (only if type is 'multiple')
         $bundle = Bundles::where('id', '=', $package_bundle->bundle_id)
             ->where('type', '=', 'multiple')
             ->first();

         // Get service
         $service = Services::find($package_service->service_id);

         if (!$service) {
             return response()->json([
                 'status' => false,
                 'message' => 'Service not found',
             ]);
         }

         // Determine package access
         $package_access = 1;
         if ($bundle) {
             if ($balance < $bundle->price && $balance < $service->price) {
                 $package_access = 0;
             }
         }

         // Calculate total package cost and remaining amount to pay for bundle logic
         $total_package_cost = $package_bundle->tax_including_price;

         // Calculate how much has been consumed from this specific bundle
         $consumed_from_bundle = PackageService::where('package_bundle_id', '=', $package_bundle->id)
             ->where('is_consumed', '=', 1)
             ->sum('tax_including_price');

         // Remaining to pay is the bundle price minus what's been consumed minus current balance
         $remaining_to_pay = max(0, $total_package_cost - $consumed_from_bundle - $balance);

         $cash = 0;

         // Helper function to calculate outstanding
         $calculateOutstanding = function ($bundle, $service, $balance, $cash, $remaining_to_pay, $fallbackAmount = 0) {
             if ($bundle) {
                 if ($balance >= $service->price) {
                     return 0;
                 }
                 $naive_outstanding = intval($service->price) - intval($balance) - $cash;
                 return min($naive_outstanding, max(0, $remaining_to_pay));
             }
             return intval($fallbackAmount) - $cash - intval($balance);
         };

         if ($package_access == 1) {
             $price = $package_service->tax_including_price;
             $outstanding = $calculateOutstanding($bundle, $service, $balance, $cash, $remaining_to_pay, $package_service->tax_including_price);
             $remaining = 0;
             $settleamount = min($price - $cash, $balance);
         } else {
             if ($package_service->price > ($package_bundle->net_amount - $balance)) {
                 $price = $package_service->price;
                 $outstanding = $calculateOutstanding($bundle, $service, $balance, $cash, $remaining_to_pay);
                 $settleamount = min(intval($package_bundle->net_amount - $balance) - $cash, $balance);
             } else {
                 $price = $package_service->price;
                 $outstanding = $calculateOutstanding($bundle, $service, $balance, $cash, $remaining_to_pay);
                 $settleamount = min($price - $cash, $balance);
             }
             $remaining = $package_service->tax_including_price;
         }

         // Ensure outstanding is not negative
         $outstanding = max(0, $outstanding);

         return response()->json([
             'status' => true,
             'amount' => $package_service->tax_exclusive_price,
             'tax_price' => $package_service->tax_price,
             'serviceprice' => $price,
             'outstanding' => $outstanding,
             'settleamount' => round($settleamount, 2),
             'balance' => round($balance, 2),
             'remaining' => $remaining,
             'package_service_id' => $request->package_id_create,
         ]);
     }

    /*
     * Get the package price against package id
     *
     * */
    public function getinvoicecalculation(Request $request)
    {
        if ($request->cash_create == 0 || $request->cash_create < 0) {
            return response()->json([
                'status' => true,
                'outstdanding' => $request->outstanding_for_zero,
                'settleamount' => $request->settleamount_for_zero,
            ]);
        }
        $outstdanding = $request->outstanding_for_zero - $request->cash_create;
        $balance = $request->balance_create;
        $settleamount = $request->price_create - $request->cash_create;

        return response()->json([
            'status' => true,
            'outstdanding' => round($outstdanding, 2),
            'settleamount' => round($settleamount, 2),

        ]);
    }

    /*
     * Get the calculation of service price according to exclusive and inclusive check
     *
     * */
    public function getcalculatedPriceExclusicecheck(Request $request)
    {
        $location_info = Locations::find($request->location_id);
        if ($request->tax_treatment_type_id == Config::get('constants.tax_both')) {
            if ($request->is_exclusive == '1') {
                $amount_create = $request->price_orignal;
                $tax_create = ceil($request->price_orignal * ($location_info->tax_percentage / 100));
                $price = ceil($amount_create + (($amount_create * $location_info->tax_percentage) / 100));
            } else {
                $price = $request->price_orignal;
                $amount_create = ceil((100 * $price) / ($location_info->tax_percentage + 100));
                $tax_create = ceil($price - $amount_create);
            }
        } elseif ($request->tax_treatment_type_id == Config::get('constants.tax_is_exclusive')) {
            $amount_create = $request->price_orignal;
            $tax_create = ceil($request->price_orignal * ($location_info->tax_percentage / 100));
            $price = ceil($amount_create + (($amount_create * $location_info->tax_percentage) / 100));
        } else {
            $price = $request->price_orignal;
            $amount_create = ceil((100 * $price) / ($location_info->tax_percentage + 100));
            $tax_create = ceil($price - $amount_create);
        }
        $outstdanding = $price;
        $settleamount = 0;

        return response()->json([
            'status' => true,
            'amount_create' => $amount_create,
            'tax_create' => $tax_create,
            'price' => $price,
            'outstdanding' => $outstdanding,
            'settleamount' => $settleamount,
        ]);
    }
    /*
     * get the value for invoice calucation
     * */

    public function saveinvoice(Request $request)
    {
        $check_is_setteled = PackageAdvances::where([
            ['cash_flow', '=', 'out'],
            ['cash_amount', '>', 0],
            ['is_setteled', '=', '1'],
            ['package_id', '=', $request->package_id],
        ])->first();
        if($check_is_setteled){
            return ApiHelper::apiResponse($this->success, 'This plan is settled out and cannot consume any further treatments.', false,['setteled'=>1]);
        }

        // ============================================
        // CONSUMPTION LOCK: Server-side validation
        // ============================================
        if ($request->package_service_id && $request->package_id) {
            $packageService = PackageService::find($request->package_service_id);

            // Calculate plan-level payment totals once (used by both checks)
            $totalPlanPayments = PackageAdvances::where('package_id', $request->package_id)
                ->where('cash_flow', 'in')
                ->sum('cash_amount');

            $totalPlanValue = PackageService::where('package_id', $request->package_id)
                ->sum('tax_including_price');

            $isPlanFullyPaid = ($totalPlanPayments >= $totalPlanValue);

            // Ordering check: enforce BUY-before-GET within configurable discount groups
            // Skip ordering if the plan is fully paid (safe because plan is locked after first consumption)
            if (!$isPlanFullyPaid && $packageService && $packageService->consumption_order > 0) {
                $packageBundle = PackageBundles::find($packageService->package_bundle_id);
                $configGroupId = $packageBundle ? $packageBundle->config_group_id : null;

                if ($configGroupId) {
                    $hasUnconsumedPrior = PackageService::join('package_bundles', 'package_services.package_bundle_id', '=', 'package_bundles.id')
                        ->where('package_services.package_id', $request->package_id)
                        ->where('package_bundles.config_group_id', $configGroupId)
                        ->where('package_services.is_consumed', 0)
                        ->where('package_services.consumption_order', '<', $packageService->consumption_order)
                        ->where('package_services.id', '!=', $packageService->id)
                        ->exists();

                    if ($hasUnconsumedPrior) {
                        return ApiHelper::apiResponse($this->success, 'Cannot consume this service yet. Please consume the paid sessions first before discounted/free sessions.', false, ['consumption_locked' => 1]);
                    }
                }
            }

            // Payment coverage check (for any service with price > 0)
            if ($packageService && $packageService->tax_including_price > 0) {
                $totalConsumedValue = PackageService::where('package_id', $request->package_id)
                    ->where('is_consumed', 1)
                    ->sum('tax_including_price');

                if ($totalPlanPayments < ($totalConsumedValue + $packageService->tax_including_price)) {
                    $shortfall = ceil(($totalConsumedValue + $packageService->tax_including_price) - $totalPlanPayments);
                    return ApiHelper::apiResponse($this->success, 'Insufficient payment on this plan. Please collect Rs. ' . number_format($shortfall) . ' before consuming this service.', false, ['consumption_locked' => 1]);
                }
            }
        }
        // ============================================

        $paymentmode_settle = PaymentModes::where('payment_type', '=', Config::get('constants.payment_type_settle'))->first();
        $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();
        $appointmentinfo = Appointments::find($request->appointment_id);

        if (isset($request->appointment_id_consultancy)) {
            // Now we need to work our tag appointment for upselling
            $tag_appoint = explode('.', $request->appointment_id_consultancy);
            if ($tag_appoint[1] == 'A') {
                $appointment_id_consultancy = $tag_appoint[0];
            } else {
                $PlanAppointmentCalculation = new PlanAppointmentCalculation();
                $appointment_id_consultancy = $PlanAppointmentCalculation->storeAppointment($appointmentinfo->patient_id, $appointmentinfo->location_id, $appointmentinfo->service_id, $tag_appoint[0], true);
                $PlanAppointmentCalculation->saveinvoice($appointment_id_consultancy);
            }
            $appointmentinfo->update(['appointment_id' => $appointment_id_consultancy, 'updated_at' => Filters::getCurrentTimeStamp()]);
        }
        if ($request->package_mode_id == '0') {
            $paymemt = PaymentModes::first();
            $payment_mode_id = $paymemt->id;
        } else {
            $payment_mode_id = $request->package_mode_id;
        }
        if ($request->checked_treatment == '0') {
            /*Than First find that bundle package id */
            $package_service_info = PackageService::where([
                ['package_id', '=', $request->package_id],
                ['id', '=', $request->exclusive_or_bundle],
            ])->first();
            $is_exclusive = $package_service_info->is_exclusive;
        } else {
            if ($appointmentinfo->appointment_type->name == Config::get('constants.Service')) {
                if ($request->tax_treatment_type_id == Config::get('constants.tax_both')) {
                    $is_exclusive = $request->exclusive_or_bundle;
                } elseif ($request->tax_treatment_type_id == Config::get('constants.tax_is_exclusive')) {
                    $is_exclusive = 1;
                } else {
                    $is_exclusive = 0;
                }
            } else {
                $is_exclusive = 1;
            }
        }
        if ($request->remaining != 0) {
            $data['total_price'] = $request->remaining;
        } else {
            $data['total_price'] = $request->price;
        }
        $data['account_id'] = Auth::User()->account_id;
        $data['patient_id'] = $appointmentinfo->patient_id;
        $data['appointment_id'] = $request->appointment_id;
        $data['invoice_status_id'] = $invoicestatus->id;
        $data['created_by'] = Auth::User()->id;
        $data['location_id'] = $appointmentinfo->location_id;
        $data['doctor_id'] = $appointmentinfo->doctor_id;
        $data['is_exclusive'] = $is_exclusive;
        $data['created_at'] = Filters::getCurrentTimeStamp();
        $data['updated_at'] = Filters::getCurrentTimeStamp();
        $invoice = Invoices::CreateRecord($data);
        $data_detail['tax_exclusive_serviceprice'] = $request->amount_create;
        $data_detail['tax_percenatage'] = $appointmentinfo->location->tax_percentage;
        $data_detail['tax_price'] = $request->tax_create;
        if ($request->remaining != 0) {
            $data_detail['tax_including_price'] = $request->remaining;
            $data_detail['net_amount'] = $request->remaining;
        } else {
            $data_detail['tax_including_price'] = $request->price;
            $data_detail['net_amount'] = $request->price;
        }
        $data_detail['is_exclusive'] = $is_exclusive;
        $data_detail['qty'] = '1';
        if ($request->remaining != 0) {
            $data_detail['service_price'] = $request->remaining;
        } else {
            $data_detail['service_price'] = $appointmentinfo->service->price;
        }
        $data_detail['service_id'] = $appointmentinfo->service_id;
        $data_detail['invoice_id'] = $invoice->id;
        $data_detail['created_at'] = Filters::getCurrentTimeStamp();
        $data_detail['updated_at'] = Filters::getCurrentTimeStamp();
        if ($request->package_service_id) {
            $tax_info_package_service = PackageService::with('packagebundle')->find($request->package_service_id);
            $data_detail['tax_percenatage'] = $tax_info_package_service->tax_percenatage;
            $data_detail['package_service_id'] = $request->package_service_id;
            
            // Get discount info from the package_bundle via package_service
            if ($tax_info_package_service->packagebundle) {
                $packageBundle = $tax_info_package_service->packagebundle;
                if ($packageBundle->discount_id || $packageBundle->discount_name) {
                    $data_detail['discount_type'] = $packageBundle->discount_type;
                    $data_detail['discount_price'] = $packageBundle->discount_price;
                    $data_detail['discount_id'] = $packageBundle->discount_id;
                    $data_detail['discount_name'] = $packageBundle->discount_name ?? '';
                }
            }
        }
        if ($request->package_id != null) {
            // Only fetch from package_bundles if we don't already have discount info from package_service
            if (!isset($data_detail['discount_name']) || empty($data_detail['discount_name'])) {
                $packages = DB::table('packages')
                    ->join('package_bundles', 'packages.id', '=', 'package_bundles.package_id')
                    ->join('package_services', 'package_bundles.id', '=', 'package_services.package_bundle_id')
                    ->where([
                        ['packages.id', '=', $request->package_id],
                        ['package_services.service_id', '=', $appointmentinfo->service_id],
                        ['package_services.is_consumed', '=', 0],
                    ])->select('package_bundles.discount_type', 'package_bundles.discount_price', 'package_bundles.discount_id', 'package_bundles.discount_name')->first();
                if ($packages) {
                    // Set discount data if discount_id or discount_name exists
                    if ($packages->discount_id || $packages->discount_name) {
                        $data_detail['discount_type'] = $packages->discount_type;
                        $data_detail['discount_price'] = $packages->discount_price;
                        $data_detail['discount_id'] = $packages->discount_id;
                        $data_detail['discount_name'] = $packages->discount_name ?? '';
                    }
                }
            }
            $data_detail['package_id'] = $request->package_id;
        }
        $invoice_detail = InvoiceDetails::createRecord($data_detail, $invoice);
        if ($invoice_detail->package_id != null) {
            $data_package['cash_flow'] = 'in';
            $data_package['cash_amount'] = $request->cash;
            $data_package['patient_id'] = $appointmentinfo->patient_id;
            $data_package['payment_mode_id'] = $payment_mode_id;
            $data_package['account_id'] = Auth::User()->account_id;
            $data_package['location_id'] = $appointmentinfo->location_id;
            $data_package['created_by'] = Auth::User()->id;
            $data_package['updated_by'] = Auth::User()->id;
            $data_package['package_id'] = $invoice_detail->package_id;
            $data_package['package_id'] = $invoice_detail->package_id;
            $packagebundle = PackageBundles::where([
                'package_id' => $invoice_detail->package_id,
                'is_allocate' => '1',
            ])->pluck('id');
            $GetAppointment = Appointments::join('invoices', 'appointments.id', 'invoices.appointment_id')
                ->select('appointments.id', 'appointments.service_id', 'invoices.created_at')
                ->where(['appointments.patient_id' => $appointmentinfo->patient_id, 'appointments.appointment_type_id' => 1])
                ->latest('invoices.created_at')->first();
            $GetInvoiceInfo = Invoices::where(['appointment_id' => $GetAppointment->id])->first();
            $packageservicez = PackageService::with('service')
                ->whereIn('package_bundle_id', $packagebundle)
                ->where('created_at', '>', Carbon::parse($GetInvoiceInfo->created_at))
                ->get();
            if (count($packageservicez) > 0) {
                $data_package['appointment_id'] = $GetAppointment->id;
            } else {
                $data_package['appointment_id'] = $request->appointment_id;
            }
        } else {
            $data_package['cash_flow'] = 'in';
            $data_package['cash_amount'] = $request->cash;
            $data_package['patient_id'] = $appointmentinfo->patient_id;
            $data_package['payment_mode_id'] = $payment_mode_id;
            $data_package['account_id'] = Auth::User()->account_id;
            $data_package['appointment_type_id'] = $appointmentinfo->appointment_type_id;
            $data_package['appointment_id'] = $request->appointment_id;
            $data_package['location_id'] = $appointmentinfo->location_id;
            $data_package['invoice_id'] = $invoice->id;
            $data_package['created_by'] = Auth::User()->id;
            $data_package['updated_by'] = Auth::User()->id;
        }

        $data_package['created_at'] = Filters::getCurrentTimeStamp();
        $data_package['updated_at'] = Filters::getCurrentTimeStamp();
        $package_advances = PackageAdvances::createRecord_forinvoice($data_package);
        if ($request->package_id && $request->cash > 0) {
            Invoice_Plan_Refund_Sms_Functions::PlanCashReceived_SMS($request->package_id, $package_advances);
        }
        if ($request->remaining != 0) {
            $out_transcation = $request->remaining;
        } else {
            $out_transcation = $request->cash + $request->settle;
        }
        $out_transcation_price = $out_transcation - $invoice_detail->tax_price;
        $out_transcation_tax = $invoice_detail->tax_price;
        $tran = [
            '1' => $out_transcation_price,
            '2' => $out_transcation_tax,
        ];
        $count = 0;
        foreach ($tran as $trans) {
            if ($count == '1') {
                $data_package['is_tax'] = 1;
            }
            $data_package['cash_flow'] = 'out';
            $data_package['cash_amount'] = $trans;
            $data_package['patient_id'] = $appointmentinfo->patient_id;
            $data_package['payment_mode_id'] = $paymentmode_settle->id;
            $data_package['account_id'] = Auth::User()->account_id;
            $data_package['appointment_type_id'] = $appointmentinfo->appointment_type_id;
            $data_package['appointment_id'] = $request->appointment_id;
            $data_package['location_id'] = $appointmentinfo->location_id;
            $data_package['invoice_id'] = $invoice->id;
            $data_package['created_by'] = Auth::User()->id;
            $data_package['updated_by'] = Auth::User()->id;
            $data_package['created_at'] = Filters::getCurrentTimeStamp();
            $data_package['updated_at'] = Filters::getCurrentTimeStamp();
            if ($invoice_detail->package_id != null) {
                $data_package['package_id'] = $invoice_detail->package_id;
            }
            $package_advances = PackageAdvances::createRecord_forinvoice($data_package);
            $count++;
        }
        if ($package_advances->package_id != null) {
            PackageService::where('id', '=', $request->package_service_id)->update(['is_consumed' => 1, 'updated_at' => Filters::getCurrentTimeStamp(),'consumed_at' => Filters::getCurrentTimeStamp()]);
            $packagesservice = PackageService::find($request->package_service_id);
            
            // Update plan_name in packages table
            $this->updatePlanNameForPackage($package_advances->package_id);
            $package_service_log = PackageService::updateRecordInvoice($packagesservice);
            if ($request->cash > 0) {
                $patient = User::whereId($appointmentinfo->patient_id)->first();
                $location = Locations::whereId($appointmentinfo->location_id)->first();
                $servicename = Services::whereId($appointmentinfo->service_id)->first();
                $activity = new Activity();
                $activity->timestamps = false;
                $activity->action = 'received';
                $activity->activity_type = 'payment_received';
                $activity->patient = $patient->name;
                $activity->patient_id = $patient->id;
                $activity->appointment_type = 'Plan';
                $activity->created_by = Auth::user()->id;
                $activity->account_id = Auth::user()->account_id;
                $activity->invoice_id = $invoice->id;
                $activity->planId = $package_advances->package_id;
                $activity->amount = $request->cash;
                $activity->location = $location->name;
                $activity->centre_id = $appointmentinfo->location_id;
                $activity->created_at = Filters::getCurrentTimeStamp();
                $activity->updated_at = Filters::getCurrentTimeStamp();
                $activity->save();
            }
        }
        if ($request->package_id && $invoice && $invoice_detail) {
            Invoice_Plan_Refund_Sms_Functions::InvoiceCashReceived_SMS($invoice, $invoice_detail, $request->package_id);
        } else {
            Invoice_Plan_Refund_Sms_Functions::InvoiceCashReceived_SMS($invoice, $invoice_detail, false);
        }
        $arrivedStatus = AppointmentStatuses::where('is_arrived', '=', 1)->select('id')->first();
        if (Appointments::where('id', '=', $request->appointment_id)->where('appointment_type_id', '=', Config::get('constants.appointment_type_service'))->where('base_appointment_status_id', '!=', Config::get('constants.appointment_type_service'))->exists()) {
            if (AppointmentStatuses::where('parent_id', '=', $arrivedStatus->id)->exists()) {
                $appointmentStatus = AppointmentStatuses::where('parent_id', '=', $arrivedStatus->id)->where('active', '=', 1)->first();
                if ($appointmentStatus) {
                    Appointments::where('id', '=', $request->appointment_id)->update(['base_appointment_status_id' => $arrivedStatus->id, 'appointment_status_id' => $appointmentStatus->id, 'updated_at' => Filters::getCurrentTimeStamp()]);
                } else {
                    Appointments::where('id', '=', $request->appointment_id)->update(['base_appointment_status_id' => $arrivedStatus->id, 'appointment_status_id' => $arrivedStatus->id, 'updated_at' => Filters::getCurrentTimeStamp()]);
                }
            } else {
                Appointments::where('id', '=', $request->appointment_id)->update(['base_appointment_status_id' => $arrivedStatus->id, 'appointment_status_id' => $arrivedStatus->id, 'updated_at' => Filters::getCurrentTimeStamp()]);
            }
        }
        // In case of auto change status we need to update by so that s why we did
        $appointment_data_status['updated_by'] = Auth::User()->id;
        $appointmentinfo->update($appointment_data_status);

        ///Save activity//
        $patient = User::whereId($appointmentinfo->patient_id)->first();
        $location = Locations::whereId($appointmentinfo->location_id)->first();
        $servicename = Services::whereId($appointmentinfo->service_id)->first();
        $creatorName = Auth::user()->name ?? 'System';
        $patientName = $patient->name ?? 'Unknown';
        $serviceName = $servicename->name ?? 'Service';
        $locationName = $location->name ?? '';
        $amount = number_format($invoice_detail->net_amount);
        $scheduleDate = $appointmentinfo->scheduled_date ? date('M j, Y', strtotime($appointmentinfo->scheduled_date)) : '';
        
        // Format description with highlights
        $description = '<span class="highlight">' . $creatorName . '</span> consumed <span class="highlight-green">Rs. ' . $amount . '</span> from <span class="highlight-orange">' . $patientName . '</span> for <span class="highlight-orange">' . $serviceName . '</span> Treatment' . ($locationName ? ' at <span class="highlight">' . $locationName . '</span>' : '') . ($scheduleDate ? ' on <span class="highlight-purple">' . $scheduleDate . '</span>' : '');
        
        $activity = new Activity();
        $activity->action = 'consumed';
        $activity->description = $description;
        $activity->patient = $patient->name;
        $activity->patient_id = $patient->id;
        $activity->appointment_type = $servicename->name.' Treatment';
        $activity->activity_type = 'Treatment';
        $activity->schedule_date = $appointmentinfo->scheduled_date;
        $activity->created_by = Auth::user()->id;
        $activity->account_id = Auth::user()->account_id;
        $activity->invoice_id = $invoice->id;
        $activity->amount = $invoice_detail->net_amount;
        $activity->location = $location->name;
        $activity->centre_id = $appointmentinfo->location_id;
        $activity->service_id = $appointmentinfo->service_id;
        $activity->service = $servicename->name;
        $activity->created_at = Filters::getCurrentTimeStamp();
        $activity->updated_at = Filters::getCurrentTimeStamp();
        $activity->save();
        /**
         * Dispatch Elastic Search Index
         */
        $this->dispatch(
            new IndexSingleAppointmentJob([
                'account_id' => Auth::User()->account_id,
                'appointment_id' => $appointmentinfo->id,
            ])
        );

        return ApiHelper::apiResponse($this->success, 'Invoice created successfully', true, [
            'invoice_id' => $invoice?->id ?? 0,
        ]);
    }

    /**
     * Show the form for creating new Appointment.
     *
     * @return \Illuminate\Http\Response
     */
    public function createService(Request $request)
    {
        if (! Gate::allows('treatments_services')) {
            return abort(401);
        }
        $user = Auth::User();
        /*
         * Set dropdown for all system users
         */
        if ($user->user_type_id == config('constants.application_user_id') || $user->user_type_id == config('constants.administrator_id')) {
            $userHasLocation = UserHasLocations::join('locations', 'user_has_locations.location_id', '=', 'locations.id')->where('user_has_locations.user_id', '=', $user->id)->orderby('name', 'asc')->first();
            if ($userHasLocation) {
                $locations = Locations::where('id', '=', $userHasLocation->location_id)->first();
                $resource = Resources::where('location_id', '=', $userHasLocation->location_id)->first();

                $city_id = $locations->city_id;
                $location_id = $locations->id;
                $doctors = DoctorHasLocations::where('is_allocated',1)->where('location_id', '=', $location_id)->first();
                $urlquery = '?city_id='.$city_id.'&location_id='.$location_id;
                if ($doctors) {
                    $urlquery = '?city_id='.$city_id.'&location_id='.$location_id.'&doctor_id='.$doctors->user_id;
                }
                if ($resource) {
                    $urlquery .= '&machine_id='.$resource->id;
                }
                if ($request->city_id && $request->location_id) {
                } else {
                    return redirect(route('admin.appointments.manage_services').$urlquery);
                }
            }
        }
        /*
         * Set dropdown for all asthetic operators/ consultants
         */
        if ($user->user_type_id == config('constants.practitioner_id')) {
            $userHasLocation = DoctorHasLocations::join('locations', 'doctor_has_locations.location_id', '=', 'locations.id')->where('is_allocated',1)->where('doctor_has_locations.user_id', '=', $user->id)->orderby('name', 'asc')->first();
            if ($userHasLocation) {
                $locations = Locations::where('id', '=', $userHasLocation->location_id)->first();
                $resource = Resources::where('location_id', '=', $userHasLocation->location_id)->first();
                $city_id = $locations->city_id;
                $location_id = $locations->id;
                $urlquery = '?city_id='.$city_id.'&location_id='.$location_id.'&doctor_id='.$user->id;
                if ($resource) {
                    $urlquery .= '&machine_id='.$resource->id;
                }
                if ($request->city_id && $request->location_id) {
                } else {
                    return redirect(route('admin.appointments.manage_services').$urlquery);
                }
            }
        }
        if ($request->lead_id) {
            $lead = Leads::where(['id' => $request->lead_id])->first();
            if ($lead) {
                $lead = [
                    'id' => $lead->id,
                    'patient_id' => $lead->patient_id,
                    'name' => ($lead->patient_id) ? $lead->patient->name : null,
                    'phone' => ($lead->patient_id) ? $lead->patient->phone : null,
                    'dob' => ($lead->patient_id) ? $lead->patient->dob : null,
                    'address' => ($lead->patient_id) ? $lead->patient->address : null,
                    'cnic' => ($lead->patient_id) ? $lead->patient->cnic : null,
                    'referred_by' => ($lead->patient_id) ? $lead->patient->referred_by : null,
                    'service_id' => $lead->service_id,
                ];
            } else {
                $lead = [
                    'id' => '',
                    'patient_id' => '',
                    'name' => '',
                    'phone' => '',
                    'dob' => '',
                    'address' => '',
                    'cnic' => '',
                    'referred_by' => '',
                    'service_id' => '',
                ];
            }
        } else {
            $lead = [
                'id' => '',
                'patient_id' => '',
                'name' => '',
                'phone' => '',
                'dob' => '',
                'address' => '',
                'cnic' => '',
                'referred_by' => '',
                'service_id' => '',
            ];
        }
        $employees = User::getAllActiveRecords(Auth::User()->account_id);
        if ($employees) {
            $employees = $employees->pluck('full_name', 'id');
        } else {
            $employees = [];
        }
        $cities = Cities::getActiveFeaturedOnly(ACL::getUserCities(), Auth::User()->account_id)->get();
        if ($cities) {
            $cities = $cities->pluck('full_name', 'id');
        }
        $cities->prepend('Select a City', '');
        $lead_sources = LeadSources::getActiveSorted();
        $lead_sources->prepend('Select a Lead Source', '');
        // If Treatment ID is set then fetch only that Treatment
        if ($lead['service_id']) {
            $services = Services::getGroupsActiveOnly('name', 'asc', $lead['service_id'], Auth::User()->account_id)->pluck('name', 'id');
        } else {
            $services = Services::getGroupsActiveOnly()->pluck('name', 'id');
        }
        $services->prepend('Select a Service', '');
        // Get location based doctors
        $doctors = Doctors::getLocationDoctors();

        return view('admin.appointments.services.service_manage', compact('cities', 'lead', 'lead_sources', 'services', 'doctors', 'employees'));
    }

    /************************************************************
     * Appointment Services Start
     */
    public function getRoomResourcesWithDate(Request $request)
    {
        if ($resources = Resources::getMachinesResourcesRotaWithoutDays($request->location_id, $request->machine_id)) {
            return response()->json(['status' => 1, 'data' => $resources], 200);
        } else {
            return response()->json(['status' => 0, 'data' => null], 200);
        }
    }

    public function getRoomResources(Request $request)
    {
        return response()->json(['status' => 1, 'data' => Resources::getRoomsWithRotas()->toArray()], 200);
    }

    /*
     * Save Appointment Data
     *
     * @oaran \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */

    public function getNonScheduledServiceAppointments(Request $request)
    {
        if (
            $request->city_id &&
            $request->location_id &&
            $request->doctor_id
        ) {
            $appointments = Appointments::getNonScheduledAppointments($request, Config::get('constants.appointment_type_service'), Auth::User()->account_id);
            if ($appointments) {
                $data = [];
                foreach ($appointments as $appointment) {
                    $data[$appointment->id] = [
                        'id' => $appointment->id,
                        'service' => $appointment->service->name,
                        'patient' => ($appointment->name) ? $appointment->name : $appointment->patient->name,
                        'created_by' => ($appointment->created_by) ? $appointment->user->name : '',
                        'phone' => GeneralFunctions::prepareNumber4Call($appointment->patient->phone),
                        'duration' => $appointment->service->duration,
                        'editable' => true,
                        'overlap' => false,
                        'color' => $appointment->service->color,
                        'resourceId' => $appointment->doctor_id,
                    ];
                }

                return response()->json([
                    'status' => 1,
                    'events' => $data,
                ]);
            } else {
                return response()->json([
                    'status' => 0,
                    'events' => null,
                ]);
            }
        } else {
            return response()->json([
                'status' => 0,
                'events' => null,
            ]);
        }
    }
    /*
     * Load Appointments
     *
     * @oaran \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */

    public function getScheduledServiceAppointments(Request $request)
    {

        $location_id = $request->location_id;
        $doctor_id = $request->doctor_id;
        $machine_id = $request->machine_id;
        $account_id = Auth::User()->account_id;
        $cancelled_appointment_status = AppointmentStatuses::getCancelledStatusOnly($account_id);
        $appointments = Appointments::getScheduledAppointments($request, Config::get('constants.appointment_type_service'), Auth::User()->account_id, true);
        $resources = Resources::getRoomsResourceRotaWithoutDays($request->location_id);
        $start = $request->start;
        $end = $request->end;
        $minTime = Resources::getMinTimeWithDrAndMachine($location_id, $doctor_id, $machine_id, $start, $end);
        if ($request->has('start') && $request->has('end')) {

            $doctor_rotas = Resources::getDoctorWithRotasWithSpecificDate($request->location_id, $request->doctor_id, $request->start, $request->end);
        } else {
            $doctor_rotas = collect();
        }

        if ($appointments) {
            $data = [];
            if ($request->doctor_id != '') {
                foreach ($appointments as $appointment) {
                    $dutation = explode(':', $appointment->service->duration);
                    $data[$appointment->id] = [
                        'id' => $appointment->id,
                        'service' => $appointment->service->name,
                        'patient' => ($appointment->name) ? $appointment->name : ($appointment->patient->name ?? ''),
                        'created_by' => ($appointment->created_by) ? $appointment->user->name : '',
                        'phone' => GeneralFunctions::prepareNumber4Call($appointment->patient->phone ?? ''),
                        'duration' => $appointment->service->duration,
                        'editable' => ($request->doctor_id == $appointment->doctor_id) ? true : false,
                        'overlap' => false,
                        'start' => Carbon::parse($appointment->scheduled_date, null)->format('Y-m-d').' '.Carbon::parse($appointment->scheduled_time, null)->format('H:i'),
                        'end' => Carbon::parse($appointment->scheduled_date, null)->format('Y-m-d').' '.Carbon::parse($appointment->scheduled_time, null)->addHours($dutation[0])->addMinutes($dutation[1])->format('H:i'),
                        'color' => $appointment->service->color, // Use exact service color
                        'resourceId' => $appointment->doctor_id, // Use doctor_id for resource calendar view
                    ];
                }
            } else {
                foreach ($appointments as $appointment) {
                    $dutation = explode(':', $appointment->service->duration);
                    $data[$appointment->id] = [
                        'id' => $appointment->id,
                        'service' => $appointment->service->name,
                        'patient' => ($appointment->name) ? $appointment->name : ($appointment->patient->name ?? ''),
                        'created_by' => ($appointment->created_by) ? $appointment->user->name : '',
                        'phone' => GeneralFunctions::prepareNumber4Call($appointment->patient->phone ?? ''),
                        'duration' => $appointment->service->duration,
                        'editable' => ($request->doctor_id == $appointment->doctor_id) ? true : false,
                        'overlap' => false,
                        'start' => Carbon::parse($appointment->scheduled_date, null)->format('Y-m-d').' '.Carbon::parse($appointment->scheduled_time, null)->format('H:i'),
                        'end' => Carbon::parse($appointment->scheduled_date, null)->format('Y-m-d').' '.Carbon::parse($appointment->scheduled_time, null)->addHours($dutation[0])->addMinutes($dutation[1])->format('H:i'),
                        'color' => $appointment->service->color,
                        'resourceId' => $appointment->doctor_id, // Use doctor_id for resource calendar view
                    ];
                }
            }

            $resource_ids = [];
            $resources = array_filter($resources);
            foreach ($resources as $resource) {
                $resource_ids[] = $resource['id'];
            }
            // Get business closures, time offs, and working days
            $closures = \App\Http\Controllers\Api\AppointmentsController::getBusinessClosures($account_id, $location_id, $start, $end);
            $workingDays = \App\Http\Controllers\Api\AppointmentsController::getBusinessWorkingDays($account_id);
            $workingDayExceptions = \App\Http\Controllers\Api\AppointmentsController::getWorkingDayExceptions($account_id);
            $timeOffs = [];
            if ($doctor_id) {
                $timeOffs = \App\Http\Controllers\Api\AppointmentsController::getDoctorTimeOffs($account_id, $location_id, $doctor_id, $start, $end);
            }
            
            if ($request->doctor_id) {
                return response()->json([
                    'status' => 1,
                    'events' => $data,
                    'rotas' => $doctor_rotas->toArray(),
                    'min_time' => $minTime,
                    'resource_ids' => $resource_ids,
                    'start_time' => \Illuminate\Support\Carbon::parse($doctor_rotas->pluck('doctor_rotas')->flatten(1)->min('start_time'))->format('H:i:s'),
                    'end_time' => \Illuminate\Support\Carbon::parse($doctor_rotas->pluck('doctor_rotas')->flatten(1)->max('end_time'))->format('H:i:s'),
                    'closures' => $closures,
                    'time_offs' => $timeOffs,
                    'working_days' => $workingDays,
                    'working_day_exceptions' => $workingDayExceptions,
                ]);
            } else {
                return response()->json([
                    'status' => 1,
                    'events' => $data,
                    'rotas' => $doctor_rotas->toArray() ?? '',
                    'min_time' => $minTime,
                    'resource_ids' => $resource_ids,
                    'start_time' => '10:00',
                    'end_time' => '22:00',
                    'closures' => $closures,
                    'time_offs' => $timeOffs,
                    'working_days' => $workingDays,
                    'working_day_exceptions' => $workingDayExceptions,
                ]);
            }

        } else {
            // Still return closures, time offs, and working days even when no appointments
            $closures = \App\Http\Controllers\Api\AppointmentsController::getBusinessClosures($account_id, $location_id, $start, $end);
            $workingDays = \App\Http\Controllers\Api\AppointmentsController::getBusinessWorkingDays($account_id);
            $workingDayExceptions = \App\Http\Controllers\Api\AppointmentsController::getWorkingDayExceptions($account_id);
            $timeOffs = [];
            if ($doctor_id) {
                $timeOffs = \App\Http\Controllers\Api\AppointmentsController::getDoctorTimeOffs($account_id, $location_id, $doctor_id, $start, $end);
            }
            
            return response()->json([
                'status' => 1,
                'events' => [],
                'rotas' => $doctor_rotas ? $doctor_rotas->toArray() : [],
                'closures' => $closures,
                'time_offs' => $timeOffs,
                'working_days' => $workingDays,
                'working_day_exceptions' => $workingDayExceptions,
            ]);
        }
    }
    /*
     * check and update treatment appointment
     * Load Appointments by Doctor
     *
     * @oaran \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */

    /**
     * check appointment scheduling time. Is doctor and resource available and save that
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function serviceSchedule(Request $request)
    {
        $appointment_checkes = AppointmentCheckesWidget::AppointmentAppointmentCheckesfromcard($request);
        if ($appointment_checkes['status']) {
            // Only check doctor availability (machine rota check removed)
            $doctor_check_availability = Resources::checkDoctorAvailbility($request);
            if (
                $request->id &&
                $request->start &&
                $request->end
            ) {
                if ($doctor_check_availability) {
                    // Appointment Data
                    $data = $request->all();
                    $data['resource_id'] = $request->resourceId ?? null;
                    $appointment = Appointments::findOrFail($request->id);
                    $data['first_scheduled_count'] = $appointment->first_scheduled_count;
                    $data['scheduled_at_count'] = $appointment->scheduled_at_count;
                    if ($appointment->appointment_type_id = Config::get('constants.appointment_type_service')) {
                        // Only get doctor rota (machine rota check removed)
                        $resource_dcotor = Resources::where('external_id', '=', $data['doctor_id'])->first();
                        if ($resource_dcotor) {
                            $response = Resources::getResourceRotaHasDay($data['start'], $resource_dcotor->id);
                            if (isset($response['resource_has_rota_day_id']) && $response['resource_has_rota_day_id']) {
                                $data['resource_has_rota_day_id'] = $response['resource_has_rota_day_id'];
                            }
                        }
                    }
                    $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();
                    $invoice = Invoices::where([
                        ['appointment_id', '=', $appointment->id],
                        ['invoice_status_id', '=', $invoicestatus->id],
                    ])->get();
                    if (count($invoice) > 0) {
                        return ApiHelper::apiResponse($this->success, 'Appointment has invoice.', false);
                    }
                    $record = Appointments::updateServiceRecord($request->id, $data, Auth::User()->account_id);
                    if ($record) {
                        /*
                         * Set Appointment Status 'pending' and set send message flag
                         */
                        $appointment_status = AppointmentStatuses::getADefaultStatusOnly(Auth::User()->account_id);
                        if ($appointment_status) {
                            $record->update([
                                'appointment_status_id' => $appointment_status->id,
                                'base_appointment_status_id' => $appointment_status->id,
                                'appointment_status_allow_message' => $appointment_status->allow_message,
                                'send_message' => 1, // Set flag 1 to send message on cron job
                            ]);
                        }
                        $this->dispatch(
                            new IndexSingleAppointmentJob([
                                'account_id' => Auth::User()->account_id,
                                'appointment_id' => $appointment->id,
                            ])
                        );

                        return ApiHelper::apiResponse($this->success, 'Appointment Updated Successfully.');
                    }
                    
                    return ApiHelper::apiResponse($this->success, 'Failed to update appointment.', false);
                } else {
                    return ApiHelper::apiResponse($this->success, 'Doctor is not available.', false);
                }
            }

            return ApiHelper::apiResponse($this->success, 'Requested parameter not provided.', false);
        }

        return ApiHelper::apiResponse($this->success, $appointment_checkes['message'], false);
    }

    /**
     * @return \Illuminate\Http\JsonResponse
     */
    public function loadEndServiceByBaseService(Request $request)
    {

        if ($request->service_id) {
            $child_services = Appointments::getNodeServices($request->service_id, Auth::User()->account_id, true, true);
            
            // If resource_id is provided, filter services by machine type
            if ($request->resource_id) {
                $resource = Resources::whereId($request->resource_id)->first();
                if ($resource) {
                    $machine_services = MachineTypeHasServices::where('machine_type_id', $resource->machine_type_id)
                    ->where('service_id',$request->service_id)
                    ->first();
                    if($machine_services){
                        return ApiHelper::apiResponse($this->success, 'Record found', true, [
                            'services' => $child_services,
                        ]);
                    }else{

                        $machine_services = MachineTypeHasServices::where('machine_type_id', $resource->machine_type_id)
                        ->whereIn('service_id',array_keys($child_services))
                        ->pluck('service_id');

                        $available_services = array_filter($child_services, function ($service, $id) use ($machine_services) {
                            return in_array($id, $machine_services->toArray()); // Convert collection to array
                        }, ARRAY_FILTER_USE_BOTH);
                        return ApiHelper::apiResponse($this->success, 'Record found', true, [
                            'services' => $available_services,
                        ]);
                    }
                }
            }
            
            // No resource selected or resource not found, return all child services
            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'services' => $child_services,
            ]);
        }

        return ApiHelper::apiResponse($this->success, 'Record not found', false);
    }

    /**
     * Load all active child services (where parent_id != 0)
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function loadAllChildServices(Request $request)
    {
        $account_id = Auth::User()->account_id;

        // Get all active child services (parent_id != 0)
        $childServices = Services::where('account_id', $account_id)
            ->where('active', 1)
            ->where('parent_id', '!=', 0)
            ->orderBy('name', 'asc')
            ->get();

        // If resource_id is provided, filter services by machine type
        if ($request->resource_id) {
            $resource = Resources::whereId($request->resource_id)->first();
            if ($resource) {
                // Get all service IDs that are compatible with this machine type
                $compatibleServiceIds = MachineTypeHasServices::where('machine_type_id', $resource->machine_type_id)
                    ->pluck('service_id')
                    ->toArray();

                // Filter child services to only those compatible with the machine
                // Either the service itself OR its parent should be in compatible services
                $childServices = $childServices->filter(function ($service) use ($compatibleServiceIds) {
                    return in_array($service->id, $compatibleServiceIds) || in_array($service->parent_id, $compatibleServiceIds);
                });
            }
        }

        // Format for dropdown
        $services = [];
        foreach ($childServices as $service) {
            $services[$service->id] = $service->name;
        }

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'services' => $services,
        ]);
    }

    /*
     * Load End Node Services by Service ID
     *
     * @oaran \Illuminate\Http\Request $request
     *
     * @return \Illuminate\Http\Response
     */
    /*For now that function not use anywhere*/

    private function sendSMS($appointmentId, $patient_phone)
    {
        // Get Appointment
        $appointment = Appointments::find($appointmentId);
        if ($appointment->appointment_type_id == Config::get('constants.appointment_type_consultancy')) {
            // SEND SMS for Appointment Booked
            $SMSTemplate = SMSTemplates::getBySlug('on-appointment', Auth::User()->account_id); // 'on-appointment' for Appointment SMS
        } else {
            // SEND SMS for Appointment Booked
            $SMSTemplate = SMSTemplates::getBySlug('treatment-on-appointment', Auth::User()->account_id); // 'on-appointment' for Appointment SMS
        }
        if (! $SMSTemplate) {
            // SMS Promotion is disabled
            return [
                'status' => true,
                'sms_data' => 'SMS is disabled',
                'error_msg' => '',
            ];
        }
        $preparedText = Appointments::prepareSMSContent($appointmentId, $SMSTemplate->content);
        $UserOperatorSettings = UserOperatorSettings::getRecord(Auth::User()->account_id);
        $SMSObj = [
            'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
            'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
            'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($patient_phone)),
            'text' => $preparedText,
            'mask' => $UserOperatorSettings->mask, // Setting ID 3 for Mask
            'test_mode' => $UserOperatorSettings->test_mode, // Setting ID 3 Test Mode
        ];
        $response = TelenorSMSAPI::SendSMS($SMSObj);
        $SMSLog = array_merge($SMSObj, $response);
        $SMSLog['appointment_id'] = $appointmentId;
        $SMSLog['created_by'] = Auth::user()->id;
        SMSLogs::create($SMSLog);
        // SEND SMS for Appointment Booked End
        return $response;
    }

    public function center_machines(Request $request, $location_id)
    {
        if ($request->machine_type_allocation) {
            $machines = Resources::where([['resource_type_id', '=', config('constants.resource_room_type_id')], ['active', '=', '1'], ['location_id', '=', $location_id], ['account_id', '=', Auth::User()->account_id]])->get();
            if ($request->appointment_manage == Config::get('constants.appointment_type_service_string')) {
                $reverse_process = true;
            } else {
                $reverse_process = false;
            }
            $machineids = [];
            /*For machine type we perform that work we can remove it if any problem happen but for linkage that is best*/
            foreach ($machines as $machine) {
                $machinetypeid = MachineType::where('id', '=', $machine->machine_type_id)->first();
                $machine_serivce = AppointmentEditWidget::loadmachinetypeservice_edit($machinetypeid->id, Auth::User()->account_id, 'true');
                if (in_array($request->service_id, $machine_serivce)) {
                    $machineids[] = $machine->id;
                }
            }
            $machines = Resources::whereIn('id', $machineids)->get()->pluck('name', 'id');
            /*End*/
        } else {
            $machines = Resources::where([['resource_type_id', '=', config('constants.resource_room_type_id')], ['active', '=', '1'], ['location_id', '=', $location_id], ['account_id', '=', Auth::User()->account_id]])->get()->pluck('name', 'id');
        }
        if ($machines) {
            return ApiHelper::apiResponse($this->success, 'recourd found', true, [
                'dropdown' => $machines,
            ]);
        }

        return ApiHelper::apiResponse($this->success, 'recourd found', false, [
            'dropdown' => null,
        ]);
    }
    /************************************************************
     * Appointment Services End
     */

    /*
     * Appointment Comments section start
     */

    /**
     * Store a newly created Appointment in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function comment_store(StoreUpdateAppointmentCommentsRequest $request)
    {
        if (! Gate::allows('appointments_manage')) {
            return abort(401);
        }
        $data = $request->all();
        // Set Created by
        $data['created_by'] = Auth::user()->id;
        $appointment = AppointmentComments::create($data);
        flash('Comment has been added successfully.')->success()->important();

        return redirect()->back();
    }

    /**
     * Store a newly created Appointment in storage.
     *
     * @param  \App\Http\Requests\Admin\StoreUpdateAppointmentCommentsRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function AppointmentStoreComment(Request $req)
    {
        $appointmentComment = AppointmentComments::where('appointment_id', '=', $req->appointment_id)->get();
        $appointment = new AppointmentComments();
        $appointment->comment = $req->comment;
        $appointment->appointment_id = $req->appointment_id;
        $appointment->created_by = Auth::user()->id;
        $appointmentCommentDate = \Carbon\Carbon::parse($appointment->created_at)->format('D M d, Y g:i A');
        $appointment->save();
        $username = Auth::user()->name;
        $myarray = ['username' => $username, 'appointment' => $appointment, 'appointmentCommentDate' => $appointmentCommentDate, 'appointmentCommentSection' => $appointmentComment];

        return response()->json($myarray);
    }

    public function displayInvoiceAppointment($id)
    {
        if (! Gate::allows('appointments_invoice_display')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $Invoiceinfo = DB::table('invoices')
            ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
            ->join('appointments', 'appointments.id', '=', 'invoices.appointment_id')
            ->where('invoices.id', '=', $id)
            ->select('invoices.*',
                'invoice_details.discount_type',
                'invoice_details.discount_price',
                'invoice_details.discount_name',
                'invoice_details.service_price',
                'invoice_details.net_amount',
                'invoice_details.service_id',
                'invoice_details.discount_id',
                'invoice_details.package_id',
                'invoice_details.invoice_id',
                'invoice_details.tax_exclusive_serviceprice',
                'invoice_details.tax_percenatage',
                'invoice_details.tax_price',
                'invoice_details.tax_including_price',
                'invoice_details.is_exclusive',
                'appointments.appointment_type_id'
            )
            ->first();
        $location_info = Locations::find($Invoiceinfo->location_id);
        $package_service = PackageService::where('package_id', '=', $Invoiceinfo->package_id)->where('service_id', '=', $Invoiceinfo->service_id)->first();
        if ($package_service) {
            if ($package_service->package_bundle_id != null) {
                $package_bundle = PackageBundles::find($package_service->package_bundle_id);
            } else {
                $package_bundle = PackageBundles::where('package_id', '=', $Invoiceinfo->package_id)->first();
            }
        } else {
            $package_bundle = PackageBundles::where('package_id', '=', $Invoiceinfo->package_id)->first();
        }
        $bundle = Bundles::find($package_bundle->bundle_id);
        $invoicestatus = InvoiceStatuses::find($Invoiceinfo->invoice_status_id);
        if ($Invoiceinfo->discount_id) {
            $discount = Discounts::find($Invoiceinfo->discount_id);
        } else {
            $discount = null;
        }
        $service = Services::find($Invoiceinfo->service_id);
        
        // Get service price from package_services.actual_price first, fallback to services.price
        if ($package_service && $package_service->actual_price !== null) {
            $service_price = $package_service->actual_price;
        } else {
            $service_price = $service->price;
        }
        
        $patient = User::find($Invoiceinfo->patient_id);
        $account = Accounts::find($Invoiceinfo->account_id);
        $company_phone_number = Settings::where('slug', '=', 'sys-headoffice')->first();
        
        // Get doctor name from appointment
        $doctor = null;
        if ($Invoiceinfo->appointment_id) {
            $appointment = Appointments::with('doctor')->find($Invoiceinfo->appointment_id);
            $doctor = $appointment?->doctor;
        }

        return view('admin.appointments..invoice.displayInvoice', compact('Invoiceinfo', 'patient', 'account', 'service', 'discount', 'invoicestatus', 'company_phone_number', 'location_info', 'bundle', 'service_price', 'doctor'));
    }

    public function appointmentexcel(Request $request)
    {
        $today = Carbon::now()->toDateString();
        $this_month = Carbon::now()->firstOfMonth()->toDateString();
        $created_F = '';
        $created_T = '';
        $schedule_F = '';
        $schedule_T = '';
        $where = [];
        if ($request->patient_id && $request->patient_id != '') {
            $where[] = [['users.id' => $request->patient_id]];
        }
        if ($request->phone && $request->phone != '') {
            $where[] = [
                'users.phone',
                'like',
                '%'.GeneralFunctions::cleanNumber($request->phone).'%',
            ];
        }
        if (Gate::allows('appointments_export_all')) {
            if ($request->date_from && $request->date_from != '') {
                $where[] = [
                    'appointments.scheduled_date',
                    '>=',
                    $request->date_from.' 00:00:00',
                ];
                $schedule_F = $request->date_from;
            }
            if ($request->date_to && $request->date_to != '') {
                $where[] = [
                    'appointments.scheduled_date',
                    '<=',
                    $request->date_to.'23:59:59',
                ];
                $schedule_T = $request->date_to;
            }
        } elseif (Gate::allows('appointments_export_today')) {
            $where[] = [
                'appointments.scheduled_date',
                '>=',
                $today.' 00:00:00',
            ];
            $schedule_F = $today;
            $where[] = [
                'appointments.scheduled_date',
                '<=',
                $today.'23:59:59',
            ];
            $schedule_T = $today;
        } elseif (Gate::allows('appointments_export_this_month')) {
            $where[] = [
                'appointments.scheduled_date',
                '>=',
                $this_month.' 00:00:00',
            ];
            $schedule_F = $this_month;
            $where[] = [
                'appointments.scheduled_date',
                '<=',
                $today.'23:59:59',
            ];
            $schedule_T = $today;
        }
        if ($request->doctor_id && $request->doctor_id != '') {
            $where[] = [
                'doctor_id',
                '=',
                $request->doctor_id,
            ];
        }
        if ($request->region_id && $request->region_id != '') {
            $where[] = [['region_id' => $request->region_id]];
        }
        if ($request->city_id && $request->city_id != '') {
            $where[] = [['city_id' => $request->city_id]];
        }
        if ($request->location_id && $request->location_id != '') {
            $where[] = [['location_id' => $request->location_id]];
        }
        if ($request->service_id && $request->service_id != '') {
            $where[] = [['service_id' => $request->service_id]];
        }
        if ($request->created_by && $request->created_by != '') {
            $where[] = [['appointments.created_by' => $request->created_by]];
        }
        if ($request->converted_by && $request->converted_by != '') {
            $where[] = [['appointments.converted_by' => $request->converted_by]];
        }
        if ($request->updated_by && $request->updated_by != '') {
            $where[] = [['appointments.updated_by' => $request->updated_by]];
        }
        if ($request->appointment_status_id && $request->appointment_status_id != '') {
            $where[] = [['appointments.base_appointment_status_id' => $request->appointment_status_id]];
        }
        if ($request->appointment_type_id && $request->appointment_type_id != '') {
            $where[] = [['appointments.appointment_type_id' => $request->appointment_type_id]];
        }
        if ($request->consultancy_type && $request->consultancy_type != '') {
            $where[] = [['appointments.consultancy_type' => $request->consultancy_type]];
        }
        if (Gate::allows('appointments_export_all')) {
            if ($request->created_from && $request->created_from != '') {
                $where[] = [
                    'appointments.created_at',
                    '>=',
                    $request->created_from.' 00:00:00',
                ];
                $created_F = $request->created_from;
            }
            if ($request->created_to && $request->created_to != '') {
                $where[] = [
                    'appointments.created_at',
                    '<=',
                    $request->created_to.' 23:59:59',
                ];
                $created_T = $request->created_to;
            }
        }
        $consultancyslug = AppointmentTypes::where('slug', '=', 'consultancy')->first();
        $treatmentslug = AppointmentTypes::where('slug', '=', 'treatment')->first();
        $records = [];
        $records['data'] = [];
        if (Gate::allows('appointments_consultancy')) {
            $resultQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->where('appointments.appointment_type_id', '=', $consultancyslug->id)
                ->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (Gate::allows('treatments_services')) {
            $resultQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->where('appointments.appointment_type_id', '=', $treatmentslug->id)
                ->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (Gate::allows('appointments_consultancy') && Gate::allows('treatments_services')) {
            $resultQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (! Gate::allows('appointments_consultancy') && ! Gate::allows('treatments_services')) {
            $resultQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->where([
                ['appointments.appointment_type_id', '!=', $consultancyslug->id],
                ['appointments.appointment_type_id', '!=', $treatmentslug->id],
            ])
                ->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (count($where)) {
            $resultQuery->where($where);
        }
        if ($request->name && $request->name != '') {
            $resultQuery->where(function ($query) {
                global $request;
                $query->where(
                    'users.name',
                    'like',
                    '%'.$request->name.'%'
                );
                $query->orWhere(
                    'appointments.name',
                    'like',
                    '%'.$request->name.'%'
                );
            });
        }
        if ($request->name && $request->name != '') {
            $resultQuery->where(function ($query) use ($request) {
                $query->where(
                    'users.name',
                    'like',
                    '%'.$request->name.'%'
                );
                $query->orWhere(
                    'appointments.name',
                    'like',
                    '%'.$request->name.'%'
                );
            });
        }
        $Appointments_count = $resultQuery->select('*', 'appointments.name as patient_name', 'appointments.id as app_id', 'appointments.created_by as app_created_by', 'appointments.updated_by as app_updated_by', 'appointments.created_at as app_created_at')->count();
        if ($Appointments_count > 10000) {
            flash('The data you are trying to pull is too large in size. Please apply some filters to reduce the data count ( maximum 10,000 ) to be able to export it.')->warning();

            return redirect()->back();
        }
        $Appointments = $resultQuery->select('*', 'appointments.name as patient_name', 'appointments.id as app_id', 'appointments.created_by as app_created_by', 'appointments.updated_by as app_updated_by', 'appointments.created_at as app_created_at')->orderBy('appointments.created_at', 'desc')->get();
        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);
        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();
        $activeSheet->setCellValue('A1', 'ID')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'Patient')->getStyle('B1')->getFont()->setBold(true);
        $activeSheet->setCellValue('C1', 'Phone')->getStyle('C1')->getFont()->setBold(true);
        $activeSheet->setCellValue('D1', 'Scheduled')->getStyle('D1')->getFont()->setBold(true);
        $activeSheet->setCellValue('E1', 'Doctor')->getStyle('E1')->getFont()->setBold(true);
        $activeSheet->setCellValue('F1', 'Region')->getStyle('F1')->getFont()->setBold(true);
        $activeSheet->setCellValue('G1', 'City')->getStyle('G1')->getFont()->setBold(true);
        $activeSheet->setCellValue('H1', 'Centre')->getStyle('H1')->getFont()->setBold(true);
        $activeSheet->setCellValue('I1', 'Service')->getStyle('I1')->getFont()->setBold(true);
        $activeSheet->setCellValue('J1', 'Status')->getStyle('J1')->getFont()->setBold(true);
        $activeSheet->setCellValue('K1', 'Type')->getStyle('K1')->getFont()->setBold(true);
        $activeSheet->setCellValue('L1', 'Consultancy Type')->getStyle('L1')->getFont()->setBold(true);
        $activeSheet->setCellValue('M1', 'Created At')->getStyle('M1')->getFont()->setBold(true);
        $activeSheet->setCellValue('N1', 'Created By')->getStyle('N1')->getFont()->setBold(true);
        $activeSheet->setCellValue('O1', 'Updated By')->getStyle('O1')->getFont()->setBold(true);
        $activeSheet->setCellValue('P1', 'Reschedule By')->getStyle('P1')->getFont()->setBold(true);
        $counter = 2;
        if (count($Appointments)) {
            $Regions = Regions::getAllRecordsDictionary(Auth::User()->account_id);
            $Users = User::getAllRecords(Auth::User()->account_id)->getDictionary();
            $AppointmentStatuses = AppointmentStatuses::getAllRecordsDictionary(Auth::User()->account_id);
            foreach ($Appointments as $appointment) {
                if ($appointment->consultancy_type == 'in_person') {
                    $consultancy_type = 'In Person';
                } elseif ($appointment->consultancy_type == 'virtual') {
                    $consultancy_type = 'Virtual';
                } else {
                    $consultancy_type = '';
                }
                $activeSheet->setCellValue('A'.$counter, $appointment->patient_id);
                $activeSheet->setCellValue('B'.$counter, ($appointment->patient_name) ? $appointment->patient_name : $appointment->name);
                $activeSheet->setCellValue('C'.$counter, \App\Helpers\GeneralFunctions::prepareNumber4Call($appointment->patient->phone, 1));
                $activeSheet->setCellValue('D'.$counter, ($appointment->scheduled_date) ? Carbon::parse($appointment->scheduled_date, null)->format('M j, Y').' at '.Carbon::parse($appointment->scheduled_time, null)->format('h:i A') : '-');
                $activeSheet->setCellValue('E'.$counter, $appointment->doctor->name);
                $activeSheet->setCellValue('F'.$counter, (array_key_exists($appointment->region_id, $Regions)) ? $Regions[$appointment->region_id]->name : 'N/A');
                $activeSheet->setCellValue('G'.$counter, $appointment->city_id ? $appointment->city->name : 'N/A');
                $activeSheet->setCellValue('H'.$counter, $appointment->location_id ? $appointment->location->name : 'N/A');
                $activeSheet->setCellValue('I'.$counter, $appointment->service->name);
                $activeSheet->setCellValue('J'.$counter, ($appointment->appointment_status_id ? ($appointment->appointment_status->parent_id ? $AppointmentStatuses[$appointment->appointment_status->parent_id]->name : $appointment->appointment_status->name) : ''));
                $activeSheet->setCellValue('K'.$counter, $appointment->appointment_type->name);
                $activeSheet->setCellValue('L'.$counter, $consultancy_type);
                $activeSheet->setCellValue('M'.$counter, Carbon::parse($appointment->app_created_at)->format('F j,Y h:i A'));
                $activeSheet->setCellValue('N'.$counter, array_key_exists($appointment->app_created_by, $Users) ? $Users[$appointment->app_created_by]->name : 'N/A');
                $activeSheet->setCellValue('O'.$counter, array_key_exists($appointment->converted_by, $Users) ? $Users[$appointment->converted_by]->name : 'N/A');
                $activeSheet->setCellValue('p'.$counter, array_key_exists($appointment->app_updated_by, $Users) ? $Users[$appointment->app_updated_by]->name : 'N/A');
                $counter++;
            }
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'General Report'.'.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');
    }

    public function logPage($id)
    {
        return view('admin.appointments.logs.appointmentlog', compact('id'));
    }

    public function viewLog($id, $type)
    {
        if (! Gate::allows('appointments_log')) {
            abort(404);
        }
        $appointments = AuditTrailTables::whereName('appointments')->first();
        $audit_trails = AuditTrails::has('auditTrailChanges')->with('auditTrailChanges')->where('audit_trail_table_name', '=', $appointments->id)->where('table_record_id', '=', $id)->get();
        $data = [];
        foreach ($audit_trails as $audit_trail) {
            $audit_trail_action = AuditTrailActions::find($audit_trail->audit_trail_action_name);
            $data[$audit_trail->id] = [
                'action' => $audit_trail_action->name,
                'caused_by' => $audit_trail->userr->name,
                'created_at' => $audit_trail->created_at,
            ];
            foreach ($audit_trail->auditTrailChanges as $auditTrailChange) {
                $company = Accounts::find(1, ['name']);
                $data[$audit_trail->id]['company'] = $company->name;
                switch ($auditTrailChange->field_name) {
                    case 'scheduled_date':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->field_after;
                        break;
                    case 'scheduled_time':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->field_after;
                        break;
                    case 'name':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->field_after;
                        break;
                    case 'patient_id':
                        $data[$audit_trail->id]['phone'] = $auditTrailChange->user->phone;
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->field_after;
                        break;
                    case 'appointment_type_id':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->AppointmentType->name;
                        break;
                    case 'base_appointment_status_id':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->appointmentStatus->name;
                        break;
                    case 'appointment_status_id':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->appointmentStatus->name;
                        break;
                    case 'created_by':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->appointmentCreatedBy->name;
                        break;
                    case 'updated_by':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->appointmentCreatedBy->name;
                        break;
                    case 'converted_by':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->appointmentCreatedBy->name;
                        break;
                    case 'service_id':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->service->name;
                        break;
                    case 'doctor_id':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->doctor->name;
                        break;
                    case 'resource_id':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->resource->name;
                        break;
                    case 'region_id':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->region->name;
                        break;
                    case 'city_id':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->city->name;
                        break;
                    case 'location_id':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->location->name;
                        break;
                    case 'send_message':
                        $data[$audit_trail->id][$auditTrailChange->field_name] = $auditTrailChange->field_after;
                        break;
                }
            }
        }
        if ($type === 'web') {
            $records['data'] = $data;
            $records['meta'] = [
                'field' => 'action',
                'page' => 1,
                'pages' => count($data),
                'perpage' => 20,
                'total' => count($data),
                'sort' => 'DESC',
            ];
            $records['permissions'] = [
                'contact' => Gate::allows('contact'),
            ];

            return ApiHelper::apiDataTable($records);
        }

        return $this->viewLogInExcel($id, $data);
    }

    public function viewLogInExcel($id, $data)
    {
        $appointment = Appointments::withTrashed()->find($id);
        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);
        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();
        $activeSheet->setCellValue('A1', 'APPOINTMENT ID')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', $id);
        if ($appointment->appointment_type_id === config('constants.appointment_type_service')) {
            $activeSheet->setCellValue('A2', '#')->getStyle('A2')->getFont()->setBold(true);
            $activeSheet->setCellValue('B2', 'Action')->getStyle('B2')->getFont()->setBold(true);
            $activeSheet->setCellValue('C2', 'Patient Name')->getStyle('C2')->getFont()->setBold(true);
            $activeSheet->setCellValue('D2', 'Phone')->getStyle('D2')->getFont()->setBold(true);
            $activeSheet->setCellValue('E2', 'Scheduled At')->getStyle('E2')->getFont()->setBold(true);
            $activeSheet->setCellValue('F2', 'Doctor')->getStyle('F2')->getFont()->setBold(true);
            $activeSheet->setCellValue('G2', 'Resource')->getStyle('G2')->getFont()->setBold(true);
            $activeSheet->setCellValue('H2', 'Region')->getStyle('H2')->getFont()->setBold(true);
            $activeSheet->setCellValue('I2', 'City')->getStyle('I2')->getFont()->setBold(true);
            $activeSheet->setCellValue('J2', 'Centre')->getStyle('J2')->getFont()->setBold(true);
            $activeSheet->setCellValue('K2', 'Service')->getStyle('K2')->getFont()->setBold(true);
            $activeSheet->setCellValue('L2', 'Parent Status')->getStyle('L2')->getFont()->setBold(true);
            $activeSheet->setCellValue('M2', 'Child Status')->getStyle('M2')->getFont()->setBold(true);
            $activeSheet->setCellValue('N2', 'Type')->getStyle('N2')->getFont()->setBold(true);
            $activeSheet->setCellValue('O2', 'Created At')->getStyle('O2')->getFont()->setBold(true);
            $activeSheet->setCellValue('P2', 'Created By')->getStyle('P2')->getFont()->setBold(true);
            $activeSheet->setCellValue('Q2', 'Updated By')->getStyle('Q2')->getFont()->setBold(true);
            $activeSheet->setCellValue('R2', 'Rescheduled By')->getStyle('R2')->getFont()->setBold(true);
            $activeSheet->setCellValue('S2', 'Message')->getStyle('S2')->getFont()->setBold(true);
            $counter = 4;
            $count = 1;
            if (count($data)) {
                foreach ($data as $log) {
                    $activeSheet->setCellValue('A'.$counter, $count++);
                    $activeSheet->setCellValue('B'.$counter, $log['action']);
                    $activeSheet->setCellValue('C'.$counter, isset($log['name']) ? $log['name'] : '-');
                    $activeSheet->setCellValue('D'.$counter, isset($log['phone']) ? \App\Helpers\GeneralFunctions::prepareNumber4Call($log['phone']) : '-');
                    if (isset($log['scheduled_date']) && isset($log['scheduled_time'])) {
                        $activeSheet->setCellValue('E'.$counter, \Carbon\Carbon::parse($log['scheduled_date'], null)->format('M j, Y').' at '.\Carbon\Carbon::parse($log['scheduled_time'], null)->format('h:i A'));
                    } elseif (isset($log['scheduled_time'])) {
                        $activeSheet->setCellValue('E'.$counter, \Carbon\Carbon::parse($log['scheduled_time'], null)->format('h:i A'));
                    } elseif (isset($log['scheduled_date'])) {
                        $activeSheet->setCellValue('E'.$counter, \Carbon\Carbon::parse($log['scheduled_date'], null)->format('M j, Y'));
                    } else {
                        $activeSheet->setCellValue('E'.$counter, '-');
                    }
                    $activeSheet->setCellValue('F'.$counter, isset($log['doctor_id']) ? $log['doctor_id'] : '-');
                    $activeSheet->setCellValue('G'.$counter, isset($log['resource_id']) ? $log['resource_id'] : '-');
                    $activeSheet->setCellValue('H'.$counter, isset($log['region_id']) ? $log['region_id'] : '-');
                    $activeSheet->setCellValue('I'.$counter, isset($log['city_id']) ? $log['city_id'] : '-');
                    $activeSheet->setCellValue('J'.$counter, isset($log['location_id']) ? $log['location_id'] : '-');
                    $activeSheet->setCellValue('K'.$counter, isset($log['service_id']) ? $log['service_id'] : '-');
                    $activeSheet->setCellValue('L'.$counter, isset($log['base_appointment_status_id']) ? $log['base_appointment_status_id'] : '-');
                    $activeSheet->setCellValue('M'.$counter, isset($log['appointment_status_id']) ? $log['appointment_status_id'] : '-');
                    $activeSheet->setCellValue('N'.$counter, isset($log['appointment_type_id']) ? $log['appointment_type_id'] : '-');
                    $activeSheet->setCellValue('O'.$counter, isset($log['created_at']) ? \Carbon\Carbon::parse($log['created_at'])->format('F j,Y h:i A') : '-');
                    $activeSheet->setCellValue('P'.$counter, isset($log['created_by']) ? $log['created_by'] : '-');
                    $activeSheet->setCellValue('Q'.$counter, isset($log['converted_by']) ? $log['converted_by'] : '-');
                    $activeSheet->setCellValue('R'.$counter, isset($log['updated_by']) ? $log['updated_by'] : '-');
                    $activeSheet->setCellValue('S'.$counter, isset($log['send_message']) ? ($log['send_message'] == 1) ? 'Sent' : 'Not Sent' : '-');
                    $counter++;
                }
            }
        } else {
            $activeSheet->setCellValue('A2', '#')->getStyle('A2')->getFont()->setBold(true);
            $activeSheet->setCellValue('B2', 'Action')->getStyle('B2')->getFont()->setBold(true);
            $activeSheet->setCellValue('C2', 'Patient Name')->getStyle('C2')->getFont()->setBold(true);
            $activeSheet->setCellValue('D2', 'Phone')->getStyle('D2')->getFont()->setBold(true);
            $activeSheet->setCellValue('E2', 'Scheduled At')->getStyle('E2')->getFont()->setBold(true);
            $activeSheet->setCellValue('F2', 'Doctor')->getStyle('F2')->getFont()->setBold(true);
            $activeSheet->setCellValue('G2', 'Region')->getStyle('G2')->getFont()->setBold(true);
            $activeSheet->setCellValue('H2', 'City')->getStyle('H2')->getFont()->setBold(true);
            $activeSheet->setCellValue('I2', 'Centre')->getStyle('I2')->getFont()->setBold(true);
            $activeSheet->setCellValue('J2', 'Service')->getStyle('J2')->getFont()->setBold(true);
            $activeSheet->setCellValue('K2', 'Parent Status')->getStyle('K2')->getFont()->setBold(true);
            $activeSheet->setCellValue('L2', 'Child Status')->getStyle('L2')->getFont()->setBold(true);
            $activeSheet->setCellValue('M2', 'Type')->getStyle('M2')->getFont()->setBold(true);
            $activeSheet->setCellValue('N2', 'Created At')->getStyle('N2')->getFont()->setBold(true);
            $activeSheet->setCellValue('O2', 'Created By')->getStyle('O2')->getFont()->setBold(true);
            $activeSheet->setCellValue('P2', 'Updated By')->getStyle('P2')->getFont()->setBold(true);
            $activeSheet->setCellValue('Q2', 'Rescheduled By')->getStyle('Q2')->getFont()->setBold(true);
            $activeSheet->setCellValue('R2', 'Message')->getStyle('R2')->getFont()->setBold(true);
            $counter = 4;
            $count = 1;
            if (count($data)) {
                foreach ($data as $log) {
                    $activeSheet->setCellValue('A'.$counter, $count++);
                    $activeSheet->setCellValue('B'.$counter, $log['action']);
                    $activeSheet->setCellValue('C'.$counter, isset($log['name']) ? $log['name'] : '-');
                    $activeSheet->setCellValue('D'.$counter, isset($log['phone']) ? \App\Helpers\GeneralFunctions::prepareNumber4Call($log['phone']) : '-');
                    if (isset($log['scheduled_date']) && isset($log['scheduled_time'])) {
                        $activeSheet->setCellValue('E'.$counter, \Carbon\Carbon::parse($log['scheduled_date'], null)->format('M j, Y').' at '.\Carbon\Carbon::parse($log['scheduled_time'], null)->format('h:i A'));
                    } elseif (isset($log['scheduled_time'])) {
                        $activeSheet->setCellValue('E'.$counter, \Carbon\Carbon::parse($log['scheduled_time'], null)->format('h:i A'));
                    } elseif (isset($log['scheduled_date'])) {
                        $activeSheet->setCellValue('E'.$counter, \Carbon\Carbon::parse($log['scheduled_date'], null)->format('M j, Y'));
                    } else {
                        $activeSheet->setCellValue('E'.$counter, '-');
                    }
                    $activeSheet->setCellValue('F'.$counter, isset($log['doctor_id']) ? $log['doctor_id'] : '-');
                    $activeSheet->setCellValue('G'.$counter, isset($log['region_id']) ? $log['region_id'] : '-');
                    $activeSheet->setCellValue('H'.$counter, isset($log['city_id']) ? $log['city_id'] : '-');
                    $activeSheet->setCellValue('I'.$counter, isset($log['location_id']) ? $log['location_id'] : '-');
                    $activeSheet->setCellValue('J'.$counter, isset($log['service_id']) ? $log['service_id'] : '-');
                    $activeSheet->setCellValue('K'.$counter, isset($log['base_appointment_status_id']) ? $log['base_appointment_status_id'] : '-');
                    $activeSheet->setCellValue('L'.$counter, isset($log['appointment_status_id']) ? $log['appointment_status_id'] : '-');
                    $activeSheet->setCellValue('M'.$counter, isset($log['appointment_type_id']) ? $log['appointment_type_id'] : '-');
                    $activeSheet->setCellValue('N'.$counter, isset($log['created_at']) ? \Carbon\Carbon::parse($log['created_at'])->format('F j,Y h:i A') : '-');
                    $activeSheet->setCellValue('O'.$counter, isset($log['created_by']) ? $log['created_by'] : '-');
                    $activeSheet->setCellValue('P'.$counter, isset($log['converted_by']) ? $log['converted_by'] : '-');
                    $activeSheet->setCellValue('Q'.$counter, isset($log['updated_by']) ? $log['updated_by'] : '-');
                    $activeSheet->setCellValue('R'.$counter, isset($log['send_message']) ? ($log['send_message'] == 1) ? 'Sent' : 'Not Sent' : '-');
                    $counter++;
                }
            }
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'AppointmentLog'.'.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');
    }

    public function checkPhoneExist(Request $request)
    {
        $record = Patients::where('phone', 'like', '%'.GeneralFunctions::cleanNumber($request->input('phone').'%'))->first();
        if ($record) {
            return response()->json(1);
        } else {
            return response()->json(0);
        }
    }

    public function export(Request $request)
    {

        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '0'); // for infinite time of execution

        return Excel::download(new ExportAppointment($limit, $offset), 'appointments.xlsx');
    }

    public function getSchedule(Request $request)
    {

        $appointment = Appointments::select('id', 'scheduled_date', 'scheduled_time')->find($request->id);

        if ($appointment) {
            // Convert to array and format dates
            $appointmentData = [
                'id' => $appointment->id,
                'scheduled_date' => Carbon::parse($appointment->scheduled_date)->format('Y-m-d'),
                'scheduled_time' => Carbon::parse($appointment->scheduled_time)->format('h:i A'),
            ];
            
            return response()->json($appointmentData);
        }

        return response()->json(null);
    }

    public function updateSchedule(Request $request)
    {

        $data = [];
        $appointment = Appointments::find($request->appointment_id);
       
        if ($appointment) {
            // Store old date/time for activity logging
            $oldDate = $appointment->scheduled_date;
            $oldTime = $appointment->scheduled_time;
            $isRescheduled = false;
            
            // Compare dates in same format (Y-m-d)
            $oldDateFormatted = Carbon::parse($appointment->scheduled_date)->format('Y-m-d');
            $newDateFormatted = Carbon::parse($request->scheduled_date)->format('Y-m-d');
            
            if ($oldDateFormatted != $newDateFormatted) {
                $data['converted_by'] = Auth::user()->id;
                $isRescheduled = true;
            }
            
            // Check if time changed
            $oldTimeFormatted = Carbon::parse($appointment->scheduled_time)->format('H:i:s');
            $newTimeFormatted = Carbon::parse($request->scheduled_time)->format('H:i:s');
            
            if ($oldTimeFormatted != $newTimeFormatted) {
                $isRescheduled = true;
            }
           
            if ($appointment->appointment_status_id == config('constants.appointment_status_arrived')
                || $appointment->appointment_status_id == config('constants.appointment_status_cancelled')) {
                return ApiHelper::apiResponse($this->success, 'Appointment has Invoice or has been canceled!', false);
            }

            // Validate business closure, working days, and time offs
            $scheduleValidation = $this->validateScheduleDate($appointment, $request);
            if (!$scheduleValidation['status']) {
                return ApiHelper::apiResponse($this->success, $scheduleValidation['message'], false);
            }

            $rota = $this->checkRota($appointment, $request);
            if ($rota['status']) {
                $updateData = [
                    'scheduled_date' => Carbon::parse($request->scheduled_date)->format('Y-m-d'),
                    'scheduled_time' => Carbon::parse($request->scheduled_time)->format('H:i:s'),
                    'converted_by' => isset($data['converted_by']) ? $data['converted_by'] : $appointment->converted_by,
                    'appointment_status_id' => config('constants.appointment_status_pending'),
                    'base_appointment_status_id' => config('constants.appointment_status_pending'),
                    'updated_at' => Filters::getCurrentTimeStamp(),
                ];
                
                // Set send_message to 1 if consultation is rescheduled and status is pending
                if ($isRescheduled && $appointment->base_appointment_status_id == config('constants.appointment_status_pending', 1)) {
                    $updateData['send_message'] = 1;
                }
                
                $appointment->update($updateData);
                $screen = $appointment->appointment_type_id == 1 ? 'Consultancy' : 'Treatment';
                GeneralFunctions::saveAppointmentLogs('rescheduled', $screen, $appointment);
                $log_type = 'sms';
                $patient = Patients::findOrFail($appointment->patient_id);
                if ($appointment->isDirty('scheduled_date')) {
                    $this->SendRescheduleSms($request->appointment_id, $patient->phone, $log_type, $appointment->account_id);
                }
                Activity::where('appointment_id',$request->appointment_id)->update(['action'=>'rescheduled','rescheduled_by'=>Auth::id(),'schedule_date'=>$request->scheduled_date,'updated_at'=>Carbon::now()]);
                
                // Log rescheduled activity
                if ($isRescheduled) {
                    $location = Locations::with('city')->find($appointment->location_id);
                    $service = Services::find($appointment->service_id);
                    ActivityLogger::logAppointmentRescheduled(
                        $appointment,
                        $patient,
                        $oldDate,
                        $oldTime,
                        Carbon::parse($request->scheduled_date)->format('Y-m-d'),
                        Carbon::parse($request->scheduled_time)->format('H:i:s'),
                        $location,
                        $service
                    );
                }
                
                return ApiHelper::apiResponse($this->success, 'Record updated successfully!');
            }

            return ApiHelper::apiResponse($this->success, $rota['message'], $rota['status']);
        }

        return ApiHelper::apiResponse($this->success, 'Appointment not found!', false);
    }

    private function checkRota($appointment, $request)
    {

        $object = new \stdClass();
        // Always prefer scheduled_date and scheduled_time if available (from form)
        // Otherwise fall back to start (from calendar click)
        if ($request->has('scheduled_date') && $request->has('scheduled_time')) {
            $object->start = $request->scheduled_date.'T'.\Illuminate\Support\Carbon::parse($request->scheduled_time)->format('H:i:s');
        } elseif ($request->scheduled_date && $request->scheduled_time) {
            $object->start = $request->scheduled_date.'T'.\Illuminate\Support\Carbon::parse($request->scheduled_time)->format('H:i:s');
        } else {
            $object->start = $request->start;
        }
        $object->city_id = $request->city_id ?? '';
        $object->doctor_id = $request->doctor_id;
        $object->location_id = $request->location_id;
        $object->appointment_type = $appointment->appointment_type_id == 1 ? 'consulting' : 'treatment';
        if ($appointment->appointment_type_id == config('constants.appointment_type_consultancy')) {
            $rota = AppointmentCheckesWidget::AppointmentConsultancyCheckes($object);
        } else {
            $object->machine_id = $appointment->resource_id;
            $rota = AppointmentCheckesWidget::AppointmentAppointmentCheckesfromcalender($object);
        }

        return $rota;
    }

    private function checkRotaUpdate($appointment, $request)
    {

        $object = new \stdClass();
        if ($request->scheduled_date && $request->scheduled_time) {
            $object->start = $request->scheduled_date.'T'.\Illuminate\Support\Carbon::parse($request->scheduled_time)->format('h:i:s');
        } else {
            $object->start = $request->start;
        }
        $object->city_id = $appointment->city_id;
        $object->doctor_id = $appointment->doctor_id;
        $object->location_id = $appointment->location_id;
        $object->appointment_type = $appointment->appointment_type_id == 1 ? 'consulting' : 'treatment';
        if ($appointment->appointment_type_id == config('constants.appointment_type_consultancy')) {
            $rota = AppointmentCheckesWidget::AppointmentConsultancyCheckes($object);
        } else {
            $object->machine_id = $appointment->resource_id;
            $rota = AppointmentCheckesWidget::AppointmentAppointmentCheckesfromcalender($object);
        }

        return $rota;
    }

    /**
     * Validate schedule date for business closures, working days, and time offs
     */
    private function validateScheduleDate($appointment, $request)
    {
        $accountId = Auth::user()->account_id;
        $locationId = $request->location_id ?? $appointment->location_id;
        $doctorId = $request->doctor_id ?? $appointment->doctor_id;
        $date = Carbon::parse($request->scheduled_date)->format('Y-m-d');
        $time = Carbon::parse($request->scheduled_time)->format('H:i:s');

        // 1. Check for business closures
        $allCentresId = 30;
        $closure = \App\Models\BusinessClosure::where('account_id', $accountId)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->where(function ($query) use ($locationId, $allCentresId) {
                $query->whereHas('locations', function ($subQ) use ($locationId) {
                    $subQ->where('locations.id', $locationId);
                })
                ->orWhereHas('locations', function ($subQ) use ($allCentresId) {
                    $subQ->where('locations.id', $allCentresId);
                })
                ->orWhereDoesntHave('locations');
            })
            ->first();

        if ($closure) {
            return [
                'status' => false,
                'message' => 'Cannot schedule appointment on ' . Carbon::parse($date)->format('d M, Y') . '. Business is closed: ' . ($closure->title ?? 'Business Closed')
            ];
        }

        // 2. Check for working days (with exceptions)
        $workingDays = \App\Http\Controllers\Api\AppointmentsController::getBusinessWorkingDays($accountId);
        $isWorkingDay = \App\Models\WorkingDayException::isWorkingDay($accountId, $date, $workingDays);

        if (!$isWorkingDay) {
            return [
                'status' => false,
                'message' => 'Cannot schedule appointment on ' . Carbon::parse($date)->format('l, d M Y') . '. Business is closed on this day.'
            ];
        }

        // 3. Check for doctor time offs
        if ($doctorId) {
            $resource = \App\Models\Resources::where([
                'external_id' => $doctorId,
                'resource_type_id' => Config::get('constants.resource_doctor_type_id'),
                'account_id' => $accountId,
            ])->first();

            if ($resource) {
                $timeOffs = \App\Models\ResourceTimeOff::where('resource_id', $resource->id)
                    ->where('account_id', $accountId)
                    ->where('location_id', $locationId)
                    ->where(function ($query) use ($date) {
                        $query->whereDate('start_date', $date)
                            ->orWhere(function ($q) use ($date) {
                                $q->where('is_repeat', 1)
                                    ->whereDate('start_date', '<=', $date)
                                    ->where(function ($rq) use ($date) {
                                        $rq->whereNull('repeat_until')
                                            ->orWhereDate('repeat_until', '>=', $date);
                                    });
                            });
                    })
                    ->get();

                foreach ($timeOffs as $timeOff) {
                    $timeOffStart = Carbon::parse($timeOff->start_time)->format('H:i:s');
                    $timeOffEnd = Carbon::parse($timeOff->end_time)->format('H:i:s');

                    if ($time >= $timeOffStart && $time < $timeOffEnd) {
                        return [
                            'status' => false,
                            'message' => 'Doctor has time off during this time slot (' . Carbon::parse($timeOff->start_time)->format('h:i A') . ' - ' . Carbon::parse($timeOff->end_time)->format('h:i A') . ').'
                        ];
                    }
                }
            }
        }

        return ['status' => true, 'message' => ''];
    }

    private function SendRescheduleSms($appointmentId, $patient_phone, $log_type, $account_id)
    {
        $appointment = Appointments::find($appointmentId);
        if ($appointment->appointment_type_id == Config::get('constants.appointment_type_consultancy')) {
            // SEND SMS for Appointment Booked
            if ($appointment->consultancy_type == 'virtual') {
                $SMSTemplate = SMSTemplates::getBySlug('virtual-on-appointment', $account_id); // 'on-appointment' for virtual consultancy SMS
            } else {
                $SMSTemplate = SMSTemplates::getBySlug('on-appointment', $account_id); // 'on-appointment' for Appointment SMS
            }
        } else {
            // SEND SMS for Appointment Booked
            $SMSTemplate = SMSTemplates::getBySlug('treatment-on-appointment', $account_id); // 'on-appointment' for Appointment SMS
        }
        if (! $SMSTemplate) {
            // SMS Promotion is disabled
            return [
                'status' => true,
                'sms_data' => 'SMS Promotion is disabled',
                'error_msg' => '',
            ];
        }
        $preparedText = Appointments::prepareSMSContent($appointmentId, $SMSTemplate->content);
        $setting = Settings::whereSlug('sys-current-sms-operator')->first();
        $UserOperatorSettings = UserOperatorSettings::getRecord($account_id, $setting->data);
        if ($setting->data == 1) {
            $SMSObj = [
                'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
                'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
                'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($patient_phone)),
                'text' => $preparedText,
                'mask' => $UserOperatorSettings->mask, // Setting ID 3 for Mask
                'test_mode' => $UserOperatorSettings->test_mode, // Setting ID 3 Test Mode
            ];
            $response = TelenorSMSAPI::SendSMS($SMSObj);
        } else {
            $SMSObj = [
                'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
                'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
                'from' => $UserOperatorSettings->mask,
                'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($patient_phone)),
                'text' => $preparedText,
                'test_mode' => $UserOperatorSettings->test_mode, // Setting ID 3 Test Mode
            ];
            $response = JazzSMSAPI::SendSMS($SMSObj);
        }
        $SMSLog = array_merge($SMSObj, $response);
        $SMSLog['appointment_id'] = $appointmentId;
        $SMSLog['created_by'] = 1;
        $SMSLog['log_type'] = $log_type;
        if ($setting->data == 2) {
            $SMSLog['mask'] = $SMSObj['from'];
        }
        SMSLogs::create($SMSLog);
        // SEND SMS for Appointment Booked End
        return $response;
    }

    /**
     * Get WhatsApp data for appointment
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getWhatsAppData(Request $request)
    {
        try {
            $appointmentId = $request->input('id');

            // Fetch appointment with patient details
            $appointment = Appointments::with([
                'patient',
                'doctor',
                'location',
                'service',
                'appointment_status'
            ])->find($appointmentId);

            if (!$appointment) {
                return response()->json([
                    'status' => false,
                    'message' => 'Appointment not found'
                ]);
            }

            // Check if patient has WhatsApp number
            $whatsappNumber = $appointment->patient->phone ?? null;

            if (!$whatsappNumber) {
                return response()->json([
                    'status' => false,
                    'message' => 'Customer WhatsApp number not found'
                ]);
            }

            // Clean the phone number (remove spaces, dashes, etc.)
            $whatsappNumber = preg_replace('/[^0-9]/', '', $whatsappNumber);

            // Ensure phone number has country code (Pakistan = 92)
            // If number starts with 0, replace with 92
            if (substr($whatsappNumber, 0, 1) === '0') {
                $whatsappNumber = '92' . substr($whatsappNumber, 1);
            }
            // If number doesn't start with 92 and is 10 digits, add 92
            elseif (strlen($whatsappNumber) === 10 && substr($whatsappNumber, 0, 2) !== '92') {
                $whatsappNumber = '92' . $whatsappNumber;
            }

            // Determine template slug based on appointment type
            // appointment_type_id: 1 = Consultancy, 2 = Treatment
            $templateSlug = ($appointment->appointment_type_id == 2) ? 'treatment_whatsapp' : 'consultancy_whatsapp';

            // Fetch SMS template
            $template = SMSTemplates::getBySlug($templateSlug, Auth::user()->account_id);

            if (!$template) {
                $templateType = ($appointment->appointment_type_id == 2) ? 'Treatment' : 'Consultancy';
                return response()->json([
                    'status' => false,
                    'message' => 'WhatsApp template not found. Please create a template with slug "' . $templateSlug . '" for ' . $templateType . ' appointments'
                ]);
            }

            // Replace variables in template content
            $message = $template->content;

            // Format appointment time (only time, not date)
            $appointmentTime = 'N/A';
            if ($appointment->scheduled_date && $appointment->scheduled_time) {
                try {
                    $time = \Carbon\Carbon::parse($appointment->scheduled_time);
                    $appointmentTime = $time->format('h:i A');
                } catch (\Exception $e) {
                    $appointmentTime = $appointment->scheduled_time ?? 'N/A';
                }
            }

            // Replace single # variables
            $message = str_replace('#patient_name#', $appointment->patient->name ?? 'N/A', $message);
            $message = str_replace('#appointment_time#', $appointmentTime, $message);
            $message = str_replace('#patient_id#', $appointment->patient->id ?? 'N/A', $message);
            $message = str_replace('#appointment_id#', $appointment->id ?? 'N/A', $message);
            $message = str_replace('#doctor_name#', $appointment->doctor->name ?? 'N/A', $message);
            $message = str_replace('#location_name#', $appointment->location->name ?? 'N/A', $message);
            $message = str_replace('#centre_google_map#', $appointment->location->google_map ?? 'N/A', $message);
            $message = str_replace('#service_name#', $appointment->service->name ?? 'N/A', $message);
            $message = str_replace('#scheduled_date#', $appointment->scheduled_date ?? 'N/A', $message);
            $message = str_replace('#scheduled_time#', $appointment->scheduled_time ?? 'N/A', $message);
            $message = str_replace('#status#', $appointment->appointment_status->name ?? 'N/A', $message);

            // Also support double ## format for backward compatibility
            $message = str_replace('##patient_name##', $appointment->patient->name ?? 'N/A', $message);
            $message = str_replace('##appointment_time##', $appointmentTime, $message);
            $message = str_replace('##patient_id##', $appointment->patient->id ?? 'N/A', $message);
            $message = str_replace('##appointment_id##', $appointment->id ?? 'N/A', $message);
            $message = str_replace('##doctor_name##', $appointment->doctor->name ?? 'N/A', $message);
            $message = str_replace('##location_name##', $appointment->location->name ?? 'N/A', $message);
            $message = str_replace('##centre_google_map##', $appointment->location->google_map ?? 'N/A', $message);
            $message = str_replace('##service_name##', $appointment->service->name ?? 'N/A', $message);
            $message = str_replace('##scheduled_date##', $appointment->scheduled_date ?? 'N/A', $message);
            $message = str_replace('##scheduled_time##', $appointment->scheduled_time ?? 'N/A', $message);
            $message = str_replace('##status##', $appointment->appointment_status->name ?? 'N/A', $message);

            return response()->json([
                'status' => true,
                'data' => [
                    'whatsapp' => $whatsappNumber,
                    'message' => $message
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error fetching WhatsApp data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update plan_name in packages table based on consumed services
     * Generates name from first two bundle names (comma separated)
     * For plan type: adds '...' if more than 2 bundles
     */
    private function updatePlanNameForPackage(int $packageId): void
    {
        $package = Packages::find($packageId);
        if (!$package) {
            return;
        }

        // Only update plan_name if it's currently empty
        if (!empty($package->plan_name)) {
            return;
        }

        if ($package->plan_type === 'membership') {
            $membershipNames = PackageBundles::where('package_bundles.package_id', $package->id)
                ->join('membership_types', 'package_bundles.membership_type_id', '=', 'membership_types.id')
                ->orderBy('package_bundles.id', 'asc')
                ->limit(2)
                ->pluck('membership_types.name')
                ->toArray();

            if (!empty($membershipNames)) {
                $planName = implode(', ', $membershipNames);
                Packages::where('id', $package->id)->update(['plan_name' => $planName]);
            }
            return;
        }

        $totalBundleCount = PackageBundles::where('package_id', $package->id)->count();
        
        $packageBundles = PackageBundles::where('package_bundles.package_id', $package->id)
            ->join('bundles', 'package_bundles.bundle_id', '=', 'bundles.id')
            ->orderBy('package_bundles.id', 'asc')
            ->limit(2)
            ->pluck('bundles.name')
            ->toArray();

        if (empty($packageBundles)) {
            return;
        }

        $planName = implode(', ', $packageBundles);
        
        if ($package->plan_type === 'plan' && $totalBundleCount > 2) {
            $planName .= '...';
        }

        Packages::where('id', $package->id)->update(['plan_name' => $planName]);
    }
}
