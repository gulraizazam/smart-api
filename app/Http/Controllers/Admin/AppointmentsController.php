<?php

declare(strict_types=1);

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
use App\Models\InvoiceStatuses;
use App\Models\PackageAdvances;
use App\Models\ResourceHasRota;
use Illuminate\Validation\Rule;
use App\Models\AppointmentTypes;
use App\Enums\AppointmentType;
use App\Models\AuditTrailTables;
use App\Models\UserHasLocations;
use App\Helpers\ActivityLogger;
use App\Helpers\GeneralFunctions;
use App\Services\Phone\PhoneFormattingService;
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
use App\Services\Appointment\ConsultancyDatatableService;
use App\Services\Appointment\ConsultancyService;

class AppointmentsController extends Controller
{
    public function __construct(
        protected readonly ConsultancyDatatableService $datatableService,
        protected readonly ConsultancyService $consultancyService,
    ) {}


    /**
     * Display a listing of Appointment.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(): \Illuminate\View\View
    {
        if (! Gate::allows('appointments_manage') && ! Gate::allows('consultations_manage')) {
            return abort(404);
        }

        // Get user's assigned centres
        $userCentres = ACL::getUserCentres();

        return view('admin.appointments.index', [
            'userCentres' => $userCentres
        ]);
    }

    public function treatment(): \Illuminate\View\View
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
    public function datatable(Request $request, $patientId = null): \Illuminate\Http\JsonResponse
    {
        // Also check for patient_id in query string (for patient card context)
        if (!$patientId && $request->has('patient_id')) {
            $patientId = $request->input('patient_id');
        }
        return $this->getDefaultListing($request, $patientId);
    }

    // REMOVED: treatmentDatatable() - Migrated to App\Http\Controllers\Api\TreatmentsController@datatable
    // REMOVED: todayexport() - Migrated to AppointmentExportController
    // REMOVED: todaytreatments() - Migrated to AppointmentExportController
    // REMOVED: downloadExportdata() - Migrated to AppointmentExportController

    /**
     * Get Default Listing for Appointments
     * Supports optional patient_id parameter for patient-specific filtering
     *
     * @return mixed
     */
   private function getDefaultListing(Request $request, $patientId = null): \Illuminate\Http\JsonResponse
    {
        // Use optimized ConsultancyDatatableService
        $records = $this->datatableService->getDatatableData($request, $patientId !== null ? (int) $patientId : null);
        
        return response()->json($records);
    }

    // REMOVED: getDefaultTreatmentListing() - Migrated to App\Services\Treatment\TreatmentService@getDatatableData

    /**
     * Show the form for creating new Appointment.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        $user = Auth::user();
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
        if (! Gate::allows('consultations_manage')) {
            return abort(401);
        }
        if ($request->lead_id) {
            $lead = Leads::where(['id' => $request->lead_id])->first();
            if ($lead) {
                $lead = [
                    'id' => $lead->id,
                    'name' => $lead->patient?->name,
                    'phone' => Gate::allows('contact') ? $lead->patient?->phone : '***********',
                    'dob' => $lead->patient?->dob,
                    'address' => $lead->patient?->address,
                    'cnic' => $lead->patient?->cnic,
                    'referred_by' => $lead->patient?->referred_by,
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
        $employees = User::getAllActiveRecords(Auth::user()->account_id);
        if ($employees) {
            $employees = $employees->pluck('full_name', 'id');
        } else {
            $employees = [];
        }
        $cities = Cities::getActiveFeaturedOnly(ACL::getUserCities(), Auth::user()->account_id)->get();
        if ($cities) {
            $cities = $cities->pluck('full_name', 'id');
        }
        $cities->prepend('Select a City', '');
        $lead_sources = LeadSources::getActiveSorted();
        $lead_sources->prepend('Select a Lead Source', '');
        // If Treatment ID is set then fetch only that Treatment
        if ($lead['service_id']) {
            $services = Services::getGroupsActiveOnly('name', 'asc', $lead['service_id'], Auth::user()->account_id)->pluck('name', 'id');
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
    protected function verifyFields(Request $request, ?int $id = null): \Illuminate\Contracts\Validation\Validator
    {
        $data = $request->all();
        $phone = $data['phone'];
        if ($data['phone'] == '***********') {
            $phone = $data['old_phone'];
        }
        $data['phone'] = PhoneFormattingService::cleanNumber($phone);
        if ($request->new_patient === null) {
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
    protected function verifyUpdateFields(Request $request, ?int $id = null): \Illuminate\Contracts\Validation\Validator
    {
        // Get appointment to check status.
        // Round 4 IDOR sweep — Appointments has no BaseModel::getData() helper
        // (it extends Model, not BaseModel). Constrain by Auth::user()->account_id
        // so a guessed cross-tenant ID can't influence the validator branch
        // (isArrivedOrConverted would otherwise read another tenant's row).
        $appointment = null;
        $accountId = Auth::user()->account_id;
        if ($id) {
            $appointment = Appointments::where('id', $id)->where('account_id', $accountId)->first();
        } elseif ($request->has('id') || $request->route('id')) {
            $appointmentId = $request->input('id') ?? $request->route('id');
            $appointment = Appointments::where('id', $appointmentId)->where('account_id', $accountId)->first();
        }
        
        // Check if this is an arrived/converted consultation with permissions
        $isArrivedOrConverted = $appointment && in_array($appointment->appointment_status_id, [2, 16], true);
        $hasAnyEditPermission = Gate::allows('update_consultation_service') || 
                                Gate::allows('update_consultation_doctor') || 
                                Gate::allows('update_consultation_schedule');
        
        // For arrived/converted with permissions, make fields conditionally required
        if ($isArrivedOrConverted && $hasAnyEditPermission) {
            return $validator = Validator::make($request->all(), [
                'treatment_id' => 'nullable',
                'scheduled_date' => 'nullable',
                'scheduled_time' => 'nullable',
                'doctor_id' => 'nullable',
            ]);
        }
        
        // For other cases, all fields are required
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
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('appointments_manage')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }
        
        try {
            $data = $request->all();
            
            unset($data['resource_id']);
            unset($data['resource_has_rota_day_id']);
            unset($data['resource_has_rota_day_id_for_machine']);
            
            $appointment = $this->consultancyService->createConsultancy($data);
            
            return $this->successResponse('Consultation created successfully.', $appointment, 200);
        } catch (\App\Exceptions\AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), 500);
        } catch (\Exception $e) {
            \Log::error('Error creating consultation: ' . $e->getMessage());
            return $this->errorResponse('Failed to create consultation.', 500);
        }
        
        $validator = $this->verifyFields($request);

        if ($validator->fails()) {
            return $this->errorResponse($validator->messages()->first(), 200);
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
            $appointment_data['phone'] = PhoneFormattingService::cleanNumber($phone);
            $appointment_data['created_by'] = Auth::user()->id;
            
            unset($appointment_data['resource_id']);
            unset($appointment_data['resource_has_rota_day_id']);
            unset($appointment_data['resource_has_rota_day_id_for_machine']);
            
            // Set default appointment status i.e. 'pending'
            $appointment_status = AppointmentStatuses::getADefaultStatusOnly(Auth::user()->account_id);
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
            $appointment_data['account_id'] = Auth::user()->account_id;
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
                    'account_id' => Auth::user()->account_id,
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
                    // Policy: patient creation is reserved for the
                    // consultation booking path (AppointmentService).
                    // Treatment / drag-drop bookings cannot spawn a
                    // patient row.
                    if ($request->appointment_type !== 'consulting' && $request->appointment_type !== 'consultancy') {
                        return $this->errorResponse(
                            'No registered patient with this phone. Book a consultation first to register the patient.',
                            422,
                        );
                    }
                    $appointment_data['user_type_id'] = 3;
                    if (! $patient) {
                        $patient = Patients::createRecord($appointment_data, 1);
                    } else {
                        return $this->errorResponse('Phone number already exist', 200);
                    }

                    $checkLeadExistance = Leads::updateOrCreate([
                        'phone' => $appointment_data['phone'],
                        'account_id' => Auth::user()->account_id,
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
                    // Same policy gate as above.
                    if ($request->appointment_type !== 'consulting' && $request->appointment_type !== 'consultancy') {
                        return $this->errorResponse(
                            'No registered patient with this phone. Book a consultation first to register the patient.',
                            422,
                        );
                    }
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
                $bookedStatus = LeadStatuses::where(['account_id' => Auth::user()->account_id, 'is_booked' => 1])->first();
                $bookedStatusId = $bookedStatus?->id;
                $openStatus = LeadStatuses::where(['account_id' => Auth::user()->account_id, 'is_default' => 1])->first();
                $openStatusId = $openStatus?->id;
                
                if ($bookedStatusId) {
                    $lead = Leads::where(['phone' => $appointment_data['phone']])->orderBy('id', 'desc')->update(['name' => $patient->name, 'lead_status_id' => $bookedStatusId, 'location_id' => $find_cons->location_id, 'patient_id' => $appointment_data['patient_id']]);

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
                    
                    // Send Meta CAPI event for booked status — gated on
                    // per-appointment `meta_booked_sent` so reschedules
                    // of the same row don't re-fire (Meta would over-
                    // count Booked).
                    if ($leadRecord && $appointment && ! $appointment->meta_booked_sent) {
                        try {
                            $metaService = new MetaConversionApiService();
                            $metaService->sendLeadStatus(
                                $leadRecord->phone,
                                'booked',
                                $leadRecord->meta_lead_id,
                                $leadRecord->email
                            );
                            $appointment->update(['meta_booked_sent' => 1]);
                        } catch (\Exception $e) {
                            \Log::error('Meta CAPI booked event failed: ' . $e->getMessage());
                        }
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
                $appointment_status = AppointmentStatuses::getUnScheduledStatusOnly(Auth::user()->account_id);
                if ($appointment_status) {
                    $appointment->update([
                        'appointment_status_id' => $appointment_status->id,
                        'base_appointment_status_id' => $appointment_status->id,
                        'appointment_status_allow_message' => 0,
                        'updated_at' => Filters::getCurrentTimeStamp(),
                    ]);
                } else {
                    // Set default appointment status i.e. 'pending'
                    $appointment_status = AppointmentStatuses::getADefaultStatusOnly(Auth::user()->account_id);
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
            ActivityLogger::saveAppointmentLogs('created', 'Consultancy', $appointment);
            ActivityLogger::saveActivityLogs('booked', 'Consultancy', $appointment_data, $appointment->id);

            /**
             * Dispatch Elastic Search Index
             */
            IndexSingleAppointmentJob::dispatch([
                    'account_id' => Auth::user()->account_id,
                    'appointment_id' => $appointment->id,
                    'patient_phone' => $appointment_data['phone'],
                ]
            );

            return $this->successResponse($message, [
                'id' => $appointment->id,
                'city_id' => $request->city_id,
                'doctor_id' => $request->doctor_id,
                'location_id' => $request->location_id,
                'appointment_type' => 'consultancy',
            ]);
        }

        return $rotaCheck['status'] ? $this->successResponse($rotaCheck['message']) : $this->errorResponse($rotaCheck['message'], 400);
        /*This function is also using in leads section*/
    }

    private function scheduledConsultancy(Request $request): array
    {
        $appointment = new \stdClass();
        $appointment->city_id = $request->city_id;
        $appointment->doctor_id = $request->doctor_id;
        $appointment->location_id = $request->location_id;
        $appointment->appointment_type_id = AppointmentType::Consultancy->value;
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

    private function sendPromotionSMS($appointmentId, $patient_phone): array
    {
        $apt = Appointments::find($appointmentId);
        if($apt->appointment_type_id === AppointmentType::Consultancy->value){
            $SMSTemplate = SMSTemplates::getBySlug('on-appointment', Auth::user()->account_id);
        }
        if($apt->appointment_type_id === AppointmentType::Treatment->value){
            $SMSTemplate = SMSTemplates::getBySlug('treatment-on-appointment', Auth::user()->account_id);
        }
        //$SMSTemplate = SMSTemplates::getBySlug('promotion-sms', Auth::user()->account_id);
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
        $UserOperatorSettings = UserOperatorSettings::getRecord(Auth::user()->account_id, $setting->data);
        if ($setting->data === '1') {
            $SMSObj = [
                'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
                'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
                'to' => PhoneFormattingService::prepareNumber(PhoneFormattingService::cleanNumber($patient_phone)),
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
                'to' => PhoneFormattingService::prepareNumber(PhoneFormattingService::cleanNumber($patient_phone)),
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

    public function createTreatmentAppointment(Request $request): \Illuminate\Http\JsonResponse
    {
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

            return $this->errorResponse('Invalid request.', 200);
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
                    'name' => $lead->patient?->name,
                    'phone' => $lead->patient?->phone,
                    'dob' => $lead->patient?->dob,
                    'address' => $lead->patient?->address,
                    'cnic' => $lead->patient?->cnic,
                    'referred_by' => $lead->patient?->referred_by,
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
        $employees = User::getAllActiveRecords(Auth::user()->account_id);
        if ($employees) {
            $employees = $employees->pluck('full_name', 'id');
        } else {
            $employees = [];
        }

        // If machine_id is provided, load services based on both machine and doctor
        // Otherwise, load services based only on doctor
        if ($request->machine_id) {
            $intersect_resource_service_ids = LocationsWidget::loadAppointmentServiceByLocationResource($request->machine_id, Auth::user()->account_id);
            $intersect_location_doctor_service_ids = LocationsWidget::loadAppointmentServiceByLocationDoctor($request->location_id, $request->doctor_id, Auth::user()->account_id);

            $serviceIds = [];
            if (count($intersect_resource_service_ids) && count($intersect_location_doctor_service_ids)) {
                $serviceIds = array_intersect($intersect_resource_service_ids, $intersect_location_doctor_service_ids);
            }
        } else {
            // No machine selected, load services based only on doctor
            $serviceIds = LocationsWidget::loadAppointmentServiceByLocationDoctor($request->location_id, $request->doctor_id, Auth::user()->account_id);
        }

        if (count($serviceIds)) {
            $services = Services::whereIn('id', $serviceIds)->pluck('name', 'id');
        } else {
            return $this->errorResponse('Services not found for this doctor.', 200);
        }
        $lead_sources = LeadSources::getActiveSorted();
        // Get location based doctors
        $doctors = Doctors::getLocationDoctors();
        $towns = Towns::getActiveTowns();

        $data = [
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
        ];

        return $appointment_checkes['status']
            ? $this->successResponse($appointment_checkes['message'] ?? 'Record found', $data)
            : $this->errorResponse($appointment_checkes['message'] ?? 'Error', 400);
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
    public function createConsultingAppointment(Request $request): \Illuminate\Http\JsonResponse
    {
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
        $employees = User::getAllActiveRecords(Auth::user()->account_id);
        if ($employees) {
            $employees = $employees->pluck('full_name', 'id');
        } else {
            $employees = [];
        }
        // Root services store parent_id as NULL (migration 2026_04_08_100034);
        // legacy rows may still use 0 — match both.
        $services = Services::where('name', '!=', 'All Services')
            ->where(function ($q) {
                $q->whereNull('parent_id')->orWhere('parent_id', 0);
            })
            ->pluck('name', 'id');
        // $serviceIds = LocationsWidget::loadAppointmentServiceByLocationDoctor($request->location_id, $request->doctor_id, Auth::user()->account_id);
        // if (count($serviceIds)) {
        //     $services = Services::whereIn('id', $serviceIds)->pluck('name', 'id');
        // } else {
        //     $services[''] = '';
        // }
        $lead_sources = LeadSources::getActiveSorted();
        $setting = Settings::where('slug', '=', 'sys-virtual-consultancy')->first();
        if ($appointment_checkes['status']) {
            return $this->successResponse('Data Found.', [
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

        return $this->errorResponse($appointment_checkes['message'], 200);
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
    public function detail(int $id): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('appointments_manage')
            && ! Gate::allows('appointments_view')
            && ! Gate::allows('appointments_consultancy')
            && ! Gate::allows('consultations_manage')
        ) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
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
            'service:id,name,parent_id',
            'service.parent:id,name',
            'appointment_comments.user'
        )->find($id);
        if (! $appointment) {
            return $this->errorResponse('Appointment not found.', 200);
        }

        return $this->successResponse('Data found.', [
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
    public function edit(int $id): \Illuminate\Http\JsonResponse
    {
        $locationsids = [];
        $doctorids = [];
        $reverse_process = false;
        $appointment = Appointments::with('lead', 'patient')->find($id);
        if (! $appointment) {
            return $this->errorResponse('Resource not found.', 200);
        }
        $resourceHadRotaDay = ResourceHasRotaDays::find($appointment->resource_has_rota_day_id);
        
        // Get all parent/root services. Root services store parent_id as NULL
        // (migration 2026_04_08_100034); legacy rows may still use 0.
        $services = Services::where('active', 1)
            ->where(function ($q) {
                $q->whereNull('parent_id')->orWhere('parent_id', 0);
            })
            ->orderBy('name')
            ->pluck('name', 'id');

        if ($appointment->service_id) {
            $serviceid = Services::where(['id' => $appointment->service_id])->first();
            if ($serviceid && !$services->has($appointment->service_id)) {
                $services->put($appointment->service_id, $serviceid->name);
            }
        }
        
        // Get doctors for the appointment's location
        $doctors = $doctors_no_final = Doctors::getActiveOnly($appointment->location_id, Auth::user()->account_id);
        /*For machine type we perform that work we can remove it if any problem happen but for linkage that is best*/
        foreach ($doctors as $key => $doctor) {
            $doctor_serivce = AppointmentEditWidget::loaddoctorservice_edit($key, $appointment->location_id, Auth::user()->account_id, $reverse_process);
            if (isset($serviceid) && in_array($serviceid->id, $doctor_serivce, true)) {
                $doctorids[] = $key;
            }
        }
        if(Gate::allows('edit_after_arrived')){

            $doctor_ids = DoctorHasLocations::where('is_allocated',1)->where('location_id' ,$appointment->location_id )->distinct()->pluck('user_id');

            $doctors = Doctors::whereIn('id',$doctor_ids)->where('active' , 1)->pluck('name', 'id');

        }else{
            $doctors = $doctors_no_final = Doctors::whereIn('id', $doctorids)->pluck('name', 'id');
            if ($doctors_no_final) {
                $lifestyleConsultantIds = User::whereIn('id', $doctors_no_final->keys()->toArray())
                    ->whereHas('user_roles', function ($q) {
                        $q->whereIn('name', ['Consultant', 'Lifestyle Consultant']);
                    })
                    ->pluck('id')
                    ->toArray();

                foreach ($doctors_no_final as $key => $doctor) {
                    if (in_array($key, $lifestyleConsultantIds, true)) {
                        continue;
                    }
                    $resource = Resources::where('external_id', '=', $key)->first();
                    $doctor_rota = ResourceHasRota::where([
                        ['resource_id', '=', $resource?->id],
                        ['is_consultancy', '=', '1'],
                    ])->get();
                    if (empty($doctor_rota)) {
                        unset($doctors[$key]);
                    }
                }
            }
        }
        
        // Always include the currently assigned doctor, even if they don't have the service allocated
        // This ensures the doctor shows up when editing existing appointments
        if ($appointment->doctor_id && !isset($doctors[$appointment->doctor_id])) {
            $currentDoctor = Doctors::find($appointment->doctor_id);
            if ($currentDoctor && $currentDoctor->active === 1) {
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
        
        return $this->successResponse('Record Found', [
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

    public function editAppointmentService(int $id): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('appointments_manage')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }
        $locationsids = [];
        $doctorids = [];
        $machineids = [];
        $appointment = Appointments::with('patient', 'doctor')->find($id);
        if (! $appointment) {
            return $this->errorResponse('Resource not found.', 200);
        }
        $resourceHadRotaDay = ResourceHasRotaDays::find($appointment->resource_has_rota_day_id);
        $machineHadRotaDay = ResourceHasRotaDays::find($appointment->resource_has_rota_day_id_for_machine);
        $biggerTime = ResourceHasRota::getBiggerTime($resourceHadRotaDay->start_time, $machineHadRotaDay->start_time);
        $smallerTime = ResourceHasRota::getSmallerTime($resourceHadRotaDay->end_time, $machineHadRotaDay->end_time);
        $cities = Cities::getActiveFeaturedOnly(ACL::getUserCities(), Auth::user()->account_id)->get();
        if ($cities) {
            $cities = $cities->pluck('full_name', 'id');
        }
        if ($appointment->service_id) {
            // Was 2 queries fetching the same row (one as pluck, one as first()).
            // Now 1 query that yields both shapes.
            $serviceid = Services::where(['id' => $appointment->service_id])->first();
            $services = $serviceid ? collect([$serviceid->id => $serviceid->name]) : collect();
        } else {
            // pluck() at the query level fetches only the two columns.
            $services = Services::pluck('name', 'id');
        }
        $locations = Locations::getActiveRecordsByCity($appointment->city_id, ACL::getUserCentres(), Auth::user()->account_id);
        if ($locations) {
            $locations = $locations->pluck('name', 'id');
        }
        $doctors = $doctors_no_final = Doctors::getActiveOnly($appointment->location_id, Auth::user()->account_id);

        // NOTE: Removed dead loop here (same pattern as in editService above):
        // `if (empty($doctor_rota))` on an Eloquent Collection always returns
        // false in PHP, so the unset() never fired. The loop was making N+1
        // queries with no observable effect.
        // $machines = Resources::where([
        //     ['resource_type_id', '=', config('constants.resource_room_type_id')],
        //     ['location_id', '=', $appointment->location_id],
        //     ['account_id', '=', Auth::user()->account_id]],
        //     ['actvie', '=', 1]
        // )->get();
        /*For machine type we perform that work we can remove it if any problem happen but for linkage that is best*/
        // foreach ($machines as $machine) {
        //     $machinetypeid = MachineType::where('id', '=', $machine->machine_type_id)->first();
        //     $machine_serivce = AppointmentEditWidget::loadmachinetypeservice_edit($machinetypeid->id, Auth::user()->account_id, 'true');
        //     if (in_array($serviceid->id, $machine_serivce, true)) {
        //         $machineids[] = $machine->id;
        //     }
        // }
        //$machines = Resources::whereIn('id', $machineids)->pluck('name', 'id');
        /*End*/
        $machines = Resources::where([
            ['resource_type_id', '=', config('constants.resource_room_type_id')],
            ['location_id', '=', $appointment->location_id],
            ['account_id', '=', Auth::user()->account_id],
            ['active', '=', 1],
        ])->pluck('name', 'id');
        $back_date_config = Settings::whereSlug('sys-back-date-appointment')->select('data')->first();

        // Format scheduled_date as string to prevent timezone issues
        $appointmentData = $appointment->toArray();
        if (isset($appointmentData['scheduled_date'])) {
            $appointmentData['scheduled_date'] = \Carbon\Carbon::parse($appointment->scheduled_date)->format('Y-m-d');
        }
        if (isset($appointmentData['first_scheduled_date'])) {
            $appointmentData['first_scheduled_date'] = \Carbon\Carbon::parse($appointment->first_scheduled_date)->format('Y-m-d');
        }

        return $this->successResponse('Data found.', [
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
    public function editFeedback(int $id): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('appointments_manage')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }

        $treatment = Appointments::with(['doctor','location','service'])
        ->where('id', $id)
        ->where('appointment_type_id', 2)
        ->where('appointment_status_id', 2)
        ->first();

        return $this->successResponse('Data found.', [
            'appointment' => $treatment,

        ]);
    }
    /**
     * Update Appointment in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        try {
            $updateService = new \App\Services\Appointment\ConsultancyUpdateService();
            $appointment = $updateService->updateConsultation($id, $request->all());
            
            return $this->successResponse('Record has been updated successfully.', [
                'appointment' => $appointment
            ]);
        } catch (\App\Exceptions\AppointmentException $e) {
            return $this->errorResponse($e->getMessage(), 200);
        } catch (\Exception $e) {
            // Round 4 Crypto-H3 — getTraceAsString() inlines argument
            // values (patient ids, phone numbers) into the log line.
            // Use file/line so PII does not land in storage/logs/laravel.log.
            \Log::error('Consultation update error', [
                'appointment_id' => $id,
                'error'          => $e->getMessage(),
                'file'           => $e->getFile(),
                'line'           => $e->getLine(),
            ]);
            return $this->errorResponse('An error occurred while updating the consultation.', 200);
        }
    }


    /**
     * Remove Appointment from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        if (! Gate::allows('appointments_destroy')) {
            return $this->errorResponse('You are not authorized to access this resource.', 401);
        }
        $response = Appointments::DeleteRecord($id, Auth::user()->account_id);

        return $response['status'] ? $this->successResponse($response['message']) : $this->errorResponse($response['message'], 400);
    }

    /**
     * Inactive Record from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function inactive(int $id): \Illuminate\Http\RedirectResponse
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
    public function active(int $id): \Illuminate\Http\RedirectResponse
    {
        if (! Gate::allows('appointments_manage')) {
            return abort(401);
        }
        $permission = Cities::findOrFail($id);
        $permission->update(['active' => 1]);
        flash('Record has been inactivated successfully.')->success()->important();

        return redirect()->route('admin.appointments.index');
    }

    // REMOVED: loadLeadData() — Migrated to AppointmentLookupController
    // REMOVED: showAppointmentStatuses() + storeAppointmentStatuses() + loadAppointmentStatuses() + loadAppointmentStatusData() — Migrated to AppointmentStatusController
    // REMOVED: showSMSLogs() + sendLogSMS() + resendSMS() + comment_store() + AppointmentStoreComment() + getWhatsAppData() — Migrated to AppointmentCommunicationController
    // REMOVED: loadLocationsByCity() + LoadChildServices() + loadDoctorsByLocation() + loadConsultantDoctorsByLocation() + loadServiceByLocation() + loadRotaByDoctor() + loadEndServiceByBaseService() + loadAllChildServices() + getRoomResourcesWithDate() + getRoomResources() + center_machines() + checkPhoneExist() — Migrated to AppointmentLookupController
    // REMOVED: getNonScheduledAppointments() + getScheduledAppointments() + checkAndSaveAppointments() + getNonScheduledServiceAppointments() + getScheduledServiceAppointments() + serviceSchedule() + getSchedule() + updateSchedule() — Migrated to AppointmentScheduleController
    // REMOVED: invoice() + getplansinformation() + getpackageprice() + getinvoicecalculation() + getcalculatedPriceExclusicecheck() + saveinvoice() + displayInvoiceAppointment() + updatePlanNameForPackage() — Migrated to AppointmentInvoiceController
    // REMOVED: todayexport() + todaytreatments() + downloadExportdata() + appointmentexcel() + export() + logPage() + viewLog() + viewLogInExcel() — Migrated to AppointmentExportController
    // REMOVED: validateScheduleDate() + SendRescheduleSms() — Migrated to AppointmentScheduleController

    public function createService(Request $request): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        if (! Gate::allows('treatments_services')) {
            return abort(401);
        }
        $user = Auth::user();
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
                    'name' => $lead->patient?->name,
                    'phone' => $lead->patient?->phone,
                    'dob' => $lead->patient?->dob,
                    'address' => $lead->patient?->address,
                    'cnic' => $lead->patient?->cnic,
                    'referred_by' => $lead->patient?->referred_by,
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
        $employees = User::getAllActiveRecords(Auth::user()->account_id);
        if ($employees) {
            $employees = $employees->pluck('full_name', 'id');
        } else {
            $employees = [];
        }
        $cities = Cities::getActiveFeaturedOnly(ACL::getUserCities(), Auth::user()->account_id)->get();
        if ($cities) {
            $cities = $cities->pluck('full_name', 'id');
        }
        $cities->prepend('Select a City', '');
        $lead_sources = LeadSources::getActiveSorted();
        $lead_sources->prepend('Select a Lead Source', '');
        // If Treatment ID is set then fetch only that Treatment
        if ($lead['service_id']) {
            $services = Services::getGroupsActiveOnly('name', 'asc', $lead['service_id'], Auth::user()->account_id)->pluck('name', 'id');
        } else {
            $services = Services::getGroupsActiveOnly()->pluck('name', 'id');
        }
        $services->prepend('Select a Service', '');
        // Get location based doctors
        $doctors = Doctors::getLocationDoctors();

        return view('admin.appointments.services.service_manage', compact('cities', 'lead', 'lead_sources', 'services', 'doctors', 'employees'));
    }


    private function checkRota($appointment, $request): array
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
        $object->appointment_type = $appointment->appointment_type_id === AppointmentType::Consultancy->value ? 'consulting' : 'treatment';
        if ($appointment->appointment_type_id == config('constants.appointment_type_consultancy')) {

        $rota = AppointmentCheckesWidget::AppointmentConsultancyCheckes($object);
        } else {

            $object->machine_id = $appointment->resource_id;

            $rota = AppointmentCheckesWidget::AppointmentAppointmentCheckesfromcalender($object);
        }
 
        return $rota;
    }

}
