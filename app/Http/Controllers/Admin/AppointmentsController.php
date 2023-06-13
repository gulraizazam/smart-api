<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ExportAppointment;
use App\HelperModule\ApiHelper;
use App\Helpers\ACL;
use App\Helpers\Elastic\AppointmentsElastic;
use App\Helpers\Filters;
use App\Helpers\GeneralFunctions;
use App\Helpers\TelenorSMSAPI;
use App\Helpers\Widgets\LocationsWidget;
use App\Helpers\Widgets\PlanAppointmentCalculation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUpdateAppointmentCommentsRequest;
use App\Jobs\IndexSingleAppointmentJob;
use App\Models\AppointmentComments;
use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\AppointmentTypes;
use App\Models\AuditTrailActions;
use App\Models\AuditTrailChanges;
use App\Models\AuditTrails;
use App\Models\AuditTrailTables;
use App\Models\Bundles;
use App\Models\Cities;
use App\Models\DoctorHasLocations;
use App\Models\Doctors;
use App\Models\InvoiceDetails;
use App\Models\Invoices;
use App\Models\InvoiceStatuses;
use App\Models\Leads;
use App\Models\LeadsServices;
use App\Models\LeadSources;
use App\Models\LeadStatuses;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\PackageBundles;
use App\Models\Patients;
use App\Models\Regions;
use App\Models\ResourceHasRota;
use App\Models\ResourceHasRotaDays;
use App\Models\Resources;
use App\Models\Services;
use App\Models\SMSLogs;
use App\Models\SMSTemplates;
use App\Models\Towns;
use App\Models\UserHasLocations;
use App\Models\UserOperatorSettings;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Support\Facades\Validator;
use App\Models\Packages;
use App\Models\PackageService;
use App\Helpers\Widgets\AppointmentCheckesWidget;
use App\Models\Accounts;
use App\Models\PaymentModes;
use App\Models\Discounts;
use App\Models\Settings;
use Maatwebsite\Excel\Facades\Excel;
use App;
use App\Exports\ExportConsultancies;
use App\Exports\ExportToday;
use App\Exports\TodayTreatment;
use App\Helpers\JazzSMSAPI;
use App\Helpers\Widgets\AppointmentEditWidget;
use App\Models\MachineType;
use App\Helpers\Invoice_Plan_Refund_Sms_Functions;
use App\Models\Activity;
use PhpOffice\PhpSpreadsheet\Calculation\Web\Service;

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
        if (!Gate::allows('appointments_consultancy')) {
            return abort(404);
        }
        return view('admin.appointments.index');
    }
    public function treatment()
    {
        if (!Gate::allows('appointments_services')) {
            return abort(404);
        }
        return view('admin.appointments.treatment');
    }
    /**
     * Display a listing of Lead_statuse.
     *
     * @param \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function datatable(Request $request)
    {
        $listing_setting = Settings::where([
            'account_id' => Auth::User()->account_id,
            'slug' => 'sys-list-mode'
        ])->first();
        switch ($listing_setting->data) {
            case 'elastic':
                return $this->getElasticListing($request);
                break;
            default:
                return $this->getDefaultListing($request);
                break;
        }
    }
    public function treatmentDatatable(Request $request)
    {
        $listing_setting = Settings::where([
            'account_id' => Auth::User()->account_id,
            'slug' => 'sys-list-mode'
        ])->first();
        switch ($listing_setting->data) {
            case 'elastic':
                return $this->getElasticListing($request);
                break;
            default:
                return $this->getDefaultTreatmentListing($request);
                break;
        }
    }
    public function todayexport()
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '0'); // for infinite time of execution
        $limit=1000;
        $offset=0;
        return Excel::download(new ExportToday($limit, $offset), 'todayconsultancies.xlsx');
    }
    public function todaytreatments()
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '0'); // for infinite time of execution
        $limit=1000;
        $offset=0;
        return Excel::download(new TodayTreatment($limit, $offset), 'todaytreatments.xlsx');
    }
    public function downloadExportdata(Request $request)
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '0'); // for infinite time of execution
        $limit=1000;
        $offset=0;
        if($request->appointmenttype==1){
            return Excel::download(new ExportConsultancies($limit, $offset,$request), 'consultancies.xlsx');
        }else{
            return Excel::download(new ExportConsultancies($limit, $offset,$request), 'appointments.xlsx');
        }
    }
    /**
     * Get Elastic Listing for Appointments
     *
     * @param Request $request
     * @return mixed
     */
    private function getElasticListing(Request $request)
    {
        $where = array();
        $filter = array();
        $where[] = [
            'match' => [
                'account_id' => Auth::User()->account_id
            ]
        ];
        /*
         * Reset form filter is applied
         */
        $apply_filter = false;
        if ($request->action) {
            $action = $request->action;
            if (isset($action[0]) && $action[0] == 'filter_cancel') {
                Filters::flush(Auth::User()->id, 'appointments');
            } else if ($action == 'filter') {
                $apply_filter = true;
            }
        }
        if ($request->order) {
            $orderColumn = $request->order[0]['column'];
            $orderBy = $request->columns[$orderColumn]['data'];

            if ($orderBy == 'scheduled_date') {
                $orderBy = 'scheduled_datetime';
            }
            $order = $request->order[0]['dir'];
            Filters::put(Auth::User()->id, 'appointments', 'order_by', $orderBy);
            Filters::put(Auth::User()->id, 'appointments', 'order', $order);
        } else {
            if (
                Filters::get(Auth::User()->id, 'appointments', 'order_by')
                && Filters::get(Auth::User()->id, 'appointments', 'order')
            ) {
                $orderBy = Filters::get(Auth::User()->id, 'appointments', 'order_by');
                $order = Filters::get(Auth::User()->id, 'appointments', 'order');
                if ($orderBy == 'scheduled_date') {
                    $orderBy = 'scheduled_datetime';
                }
            } else {
                $orderBy = 'created_at';
                $order = 'desc';
                if ($orderBy == 'scheduled_date') {
                    $orderBy = 'scheduled_datetime';
                }
                Filters::put(Auth::User()->id, 'appointments', 'order_by', $orderBy);
                Filters::put(Auth::User()->id, 'appointments', 'order', $order);
            }
        }
        if ($request->patient_id && $request->patient_id != '') {
            $where[] = [
                'match' => [
                    'patient_id' => $request->patient_id
                ]
            ];
            Filters::put(Auth::User()->id, 'appointments', 'patient_id', $request->patient_id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'appointments', 'patient_id');
            } else {
                if (Filters::get(Auth::User()->id, 'appointments', 'patient_id')) {
                    $where[] = [
                        'match' => [
                            'patient_id' => Filters::get(Auth::User()->id, 'appointments', 'patient_id')
                        ]
                    ];
                }
            }
        }
        if ($request->phone && $request->phone != '') {
            $where[] = [
                'match_phrase' => [
                    'patient_phone' => GeneralFunctions::cleanNumber($request->phone)
                ]
            ];
            Filters::put(Auth::User()->id, 'appointments', 'phone', $request->phone);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'appointments', 'phone');
            } else {
                if (Filters::get(Auth::User()->id, 'appointments', 'phone')) {
                    $where[] = [
                        'match_phrase' => [
                            'patient_phone' => GeneralFunctions::cleanNumber(Filters::get(Auth::User()->id, 'appointments', 'phone'))
                        ]
                    ];
                }
            }
        }
        $scheduled_date = array(
            'range' => [
                'scheduled_datetime' => array()
            ]
        );
        if ($request->date_from && $request->date_from != '') {
            $scheduled_date['range']['scheduled_datetime']['gte'] = strtotime($request->date_from . ' 00:00:00');

            Filters::put(Auth::User()->id, 'appointments', 'date_from', $request->date_from . '00:00:00');
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'appointments', 'date_from');
            } else {
                if (Filters::get(Auth::User()->id, 'appointments', 'date_from')) {
                    $scheduled_date['range']['scheduled_datetime']['gte'] = strtotime(Filters::get(Auth::User()->id, 'appointments', 'date_from'));
                }
            }
        }
        if ($request->date_to && $request->date_to != '') {
            $scheduled_date['range']['scheduled_datetime']['lte'] = strtotime($request->date_to . ' 23:59:59');

            Filters::put(Auth::User()->id, 'appointments', 'date_to', $request->date_to . '23:59:59');
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'appointments', 'date_to');
            } else {
                if (Filters::get(Auth::User()->id, 'appointments', 'date_to')) {
                    $scheduled_date['range']['scheduled_datetime']['lte'] = strtotime(Filters::get(Auth::User()->id, 'appointments', 'date_to'));
                }
            }
        }
        if (count($scheduled_date['range']['scheduled_datetime'])) {
            $filter[] = $scheduled_date;
        }
        if ($request->doctor_id && $request->doctor_id != '') {

            $where[] = [
                'match' => [
                    'doctor_id' => $request->doctor_id
                ]
            ];
            Filters::put(Auth::User()->id, 'appointments', 'doctor_id', $request->doctor_id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'appointments', 'doctor_id');
            } else {
                if (Filters::get(Auth::User()->id, 'appointments', 'doctor_id')) {
                    $where[] = [
                        'match' => [
                            'doctor_id' => Filters::get(Auth::User()->id, 'appointments', 'doctor_id')
                        ]
                    ];
                }
            }
        }
        if ($request->region_id && $request->region_id != '') {

            $where[] = [
                'match' => [
                    'region_id' => $request->region_id
                ]
            ];
            Filters::put(Auth::User()->id, 'appointments', 'region_id', $request->region_id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'appointments', 'region_id');
            } else {
                if (Filters::get(Auth::User()->id, 'appointments', 'region_id')) {
                    $where[] = [
                        'match' => [
                            'region_id' => Filters::get(Auth::User()->id, 'appointments', 'region_id')
                        ]
                    ];
                }
            }
        }
        if ($request->city_id && $request->city_id != '') {

            $where[] = [
                'match' => [
                    'city_id' => $request->city_id
                ]
            ];
            Filters::put(Auth::User()->id, 'appointments', 'city_id', $request->city_id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'appointments', 'city_id');
            } else {
                if (Filters::get(Auth::User()->id, 'appointments', 'city_id')) {
                    $where[] = [
                        'match' => [
                            'city_id' => Filters::get(Auth::User()->id, 'appointments', 'city_id')
                        ]
                    ];
                }
            }
        }
        if ($request->location_id && $request->location_id != '') {
            $where[] = [
                'match' => [
                    'location_id' => $request->location_id
                ]
            ];
            Filters::put(Auth::User()->id, 'appointments', 'location_id', $request->location_id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'appointments', 'location_id');
            } else {
                if (Filters::get(Auth::User()->id, 'appointments', 'location_id')) {
                    $where[] = [
                        'match' => [
                            'location_id' => Filters::get(Auth::User()->id, 'appointments', 'location_id')
                        ]
                    ];
                }
            }
        }
        if ($request->service_id && $request->service_id != '') {
            $where[] = [
                'match' => [
                    'service_id' => $request->service_id
                ]
            ];
            Filters::put(Auth::User()->id, 'appointments', 'service_id', $request->service_id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'appointments', 'service_id');
            } else {
                if (Filters::get(Auth::User()->id, 'appointments', 'service_id')) {
                    $where[] = [
                        'match' => [
                            'service_id' => Filters::get(Auth::User()->id, 'appointments', 'service_id')
                        ]
                    ];
                }
            }
        }
        if ($request->created_by && $request->created_by != '') {
            $where[] = [
                'match' => [
                    'created_by' => $request->created_by
                ]
            ];
            Filters::put(Auth::User()->id, 'appointments', 'created_by', $request->created_by);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'appointments', 'created_by');
            } else {
                if (Filters::get(Auth::User()->id, 'appointments', 'created_by')) {
                    $where[] = [
                        'match' => [
                            'created_by' => Filters::get(Auth::User()->id, 'appointments', 'created_by')
                        ]
                    ];
                }
            }
        }
        if ($request->converted_by && $request->converted_by != '') {
            $where[] = [
                'match' => [
                    'converted_by' => $request->converted_by
                ]
            ];
            Filters::put(Auth::User()->id, 'appointments', 'converted_by', $request->converted_by);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'appointments', 'converted_by');
            } else {
                if (Filters::get(Auth::User()->id, 'appointments', 'converted_by')) {
                    $where[] = [
                        'match' => [
                            'converted_by' => Filters::get(Auth::User()->id, 'appointments', 'converted_by')
                        ]
                    ];
                }
            }
        }
        if ($request->updated_by && $request->updated_by != '') {
            $where[] = [
                'match' => [
                    'updated_by' => $request->updated_by
                ]
            ];
            Filters::put(Auth::User()->id, 'appointments', 'updated_by', $request->updated_by);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'appointments', 'updated_by');
            } else {
                if (Filters::get(Auth::User()->id, 'appointments', 'updated_by')) {
                    $where[] = [
                        'match' => [
                            'updated_by' => Filters::get(Auth::User()->id, 'appointments', 'updated_by')
                        ]
                    ];
                }
            }
        }
        if ($request->appointment_status_id && $request->appointment_status_id != '') {
            $where[] = [
                'match' => [
                    'base_appointment_status_id' => $request->appointment_status_id
                ]
            ];
            Filters::put(Auth::User()->id, 'appointments', 'appointment_status_id', $request->appointment_status_id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'appointments', 'appointment_status_id');
            } else {
                if (Filters::get(Auth::User()->id, 'appointments', 'appointment_status_id')) {
                    $where[] = [
                        'match' => [
                            'base_appointment_status_id' => Filters::get(Auth::User()->id, 'appointments', 'appointment_status_id')
                        ]
                    ];
                }
            }
        }
        if ($request->appointment_type_id && $request->appointment_type_id != '') {
            $where[] = [
                'match' => [
                    'appointment_type_id' => $request->appointment_type_id
                ]
            ];
            Filters::put(Auth::User()->id, 'appointments', 'appointment_type_id', $request->appointment_type_id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'appointments', 'appointment_type_id');
            } else {
                if (Filters::get(Auth::User()->id, 'appointments', 'appointment_type_id')) {
                    $where[] = [
                        'match' => [
                            'appointment_type_id' => Filters::get(Auth::User()->id, 'appointments', 'appointment_type_id')
                        ]
                    ];
                }
            }
        }
        if ($request->consultancy_type && $request->consultancy_type != '') {
            $where[] = [
                'match' => [
                    'consultancy_type' => $request->consultancy_type
                ]
            ];
            Filters::put(Auth::User()->id, 'appointments', 'consultancy_type', $request->consultancy_type);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'appointments', 'consultancy_type');
            } else {
                if (Filters::get(Auth::User()->id, 'appointments', 'consultancy_type')) {
                    $where[] = [
                        'match' => [
                            'consultancy_type' => Filters::get(Auth::User()->id, 'appointments', 'consultancy_type')
                        ]
                    ];
                }
            }
        }
        $created_at = array(
            'range' => [
                'created_at' => array()
            ]
        );
        if ($request->created_from && $request->created_from != '') {
            $created_at['range']['created_at']['gte'] = strtotime($request->created_from . ' 00:00:00');

            Filters::put(Auth::User()->id, 'appointments', 'created_from', $request->created_from);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'appointments', 'created_from');
            } else {
                if (Filters::get(Auth::User()->id, 'appointments', 'created_from')) {
                    $created_at['range']['created_at']['gte'] = strtotime(Filters::get(Auth::User()->id, 'appointments', 'created_from'));
                }
            }
        }
        if ($request->created_to && $request->created_to != '') {
            $created_at['range']['created_at']['lte'] = strtotime($request->created_to . ' 23:59:59');

            Filters::put(Auth::User()->id, 'appointments', 'created_to', $request->created_to);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'appointments', 'created_to');
            } else {
                if (Filters::get(Auth::User()->id, 'appointments', 'created_to')) {
                    $created_at['range']['created_at']['lte'] = strtotime(Filters::get(Auth::User()->id, 'appointments', 'created_to'));
                }
            }
        }
        if (count($created_at['range']['created_at'])) {
            $filter[] = $created_at;
        }
        if ($request->name && $request->name != '') {
            $where[] = [
                'multi_match' => [
                    "query" => $request->name,
                    "fields" => ["patient_name", "name"]
                ]
            ];
            Filters::put(Auth::User()->id, 'appointments', 'name', $request->name);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'appointments', 'name');
            } else {
                if (Filters::get(Auth::User()->id, 'appointments', 'name')) {
                    $where[] = [
                        'multi_match' => [
                            "query" => Filters::get(Auth::User()->id, 'appointments', 'name'),
                            "fields" => ["patient_name", "name"]
                        ]
                    ];
                }
            }
        }
        $user_cities = array(
            'terms' => [
                'city_id' => ACL::getUserCities()
            ]
        );
        if (count($user_cities['terms']['city_id'])) {
            $filter[] = $user_cities;
        }
        $user_locations = array(
            'terms' => [
                'location_id' => ACL::getUserCentres()
            ]
        );
        if (count($user_locations['terms']['location_id'])) {
            $filter[] = $user_locations;
        }
        if (!Gate::allows('appointments_services') && !Gate::allows('appointments_consultancy')) {
            $filter[] = array(
                "terms" => [
                    "appointment_type_id" => [200]
                ]
            );
        } else if (!Gate::allows('appointments_services') || !Gate::allows('appointments_consultancy')) {
            if (Gate::allows('appointments_consultancy')) {
                $consultancyslug = AppointmentTypes::where('slug', '=', 'consultancy')->first();
                $filter[] = array(
                    "terms" => [
                        "appointment_type_id" => [$consultancyslug->id]
                    ]
                );
            } else if (Gate::allows('appointments_services')) {
                $treatmentslug = AppointmentTypes::where('slug', '=', 'treatment')->first();
                $filter[] = array(
                    "terms" => [
                        "appointment_type_id" => [$treatmentslug->id]
                    ]
                );
            }
        }
        $records = array();
        $records["data"] = array();
        $iDisplayLength = intval($request->length);
        $iDisplayLength = $iDisplayLength < 0 ? 0 : $iDisplayLength;
        $iDisplayStart = intval($request->start);
        $sEcho = intval($request->draw);
        $results = AppointmentsElastic::getAllObjects($where, $filter, $iDisplayStart, $iDisplayLength, $orderBy, $order);
        $appointments = null;
        if (isset($results['hits']) && isset($results['hits']['total']) && isset($results['hits']['total']['value']) && $results['hits']['total']['value'] > 0) {
            $iTotalRecords = $results['hits']['total']['value'];
            $appointments = $results['hits']['hits'];
        } else if (isset($results['hits']) && isset($results['hits']['total']) && $results['hits']['total'] > 0) {
            $iTotalRecords = $results['hits']['total'];
            $appointments = $results['hits']['hits'];
        } else {
            $iTotalRecords = 0;
        }
        if ($iTotalRecords) {
            $AppointmentStatuses = AppointmentStatuses::getAllRecordsDictionary(Auth::User()->account_id);
            $invoice_status = InvoiceStatuses::where('slug', '=', 'paid')->first();
            $unscheduled_appointment_status = AppointmentStatuses::getUnScheduledStatusOnly(Auth::User()->account_id);
            $cancelled_appointment_status = AppointmentStatuses::getCancelledStatusOnly(Auth::User()->account_id);
            $index = 0;
            $invoiceid = 0;
            foreach ($appointments as $appointment_row) {
                $appointment = $appointment_row['_source'];
                $appointment['app_id'] = $appointment_row['_id'];
                $appointment['id'] = $appointment_row['_id'];
                $appointment['_id'] = $appointment_row['_id'];
                $invoice = Invoices::where([
                    ['appointment_id', '=', $appointment['_id']],
                    ['invoice_status_id', '=', $invoice_status->id]
                ])->first();
                $invoicearray[] = $invoice;
                if ($invoice) {
                    $invoiceid = $invoice->id;
                }
                if ($appointment['consultancy_type'] == 'in_person') {
                    $consultancy_type = 'In Person';
                } else if ($appointment['consultancy_type'] == 'virtual') {
                    $consultancy_type = 'Virtual';
                } else {
                    $consultancy_type = '';
                }
                $records["data"][$index] = array(
                    'Patient_ID' => $appointment['patient_id'],
                    'name' => ($appointment['name']) ? $appointment['name'] : $appointment['patient_name'],
                    'phone' => '<a href="javascript:void(0)" class="clipboard" data-toggle="tooltip" title="Click to Copy" data-clipboard-text="' . GeneralFunctions::prepareNumber4Call($appointment['patient_phone']) . '">' . GeneralFunctions::prepareNumber4Call($appointment['patient_phone']) . '</a>',
                    'scheduled_date' => ($appointment['scheduled_date']) ? Carbon::parse($appointment['scheduled_date'], null)->format('M j, Y') . ' at ' . Carbon::parse($appointment['scheduled_time'], null)->format('h:i A') : '-',
                    'doctor_id' => $appointment['doctor_name'],
                    'region_id' => ($appointment['region_name']) ? $appointment['region_name'] : 'N/A',
                    'city_id' => $appointment['city_name'] ? $appointment['city_name'] : 'N/A',
                    'location_id' => $appointment['location_name'] ? $appointment['location_name'] : 'N/A',
                    'service_id' => $appointment['service_name'] ? $appointment['service_name'] : 'N/A',
                    'appointment_type_id' => $appointment['appointment_type_name'] ? $appointment['appointment_type_name'] : 'N/A',
                    'consultancy_type' => $consultancy_type,
                    'created_at' => Carbon::parse()->timestamp($appointment['created_at'])->format('F j,Y h:i A'),
                    'created_by' => ($appointment['created_by_name']) ? $appointment['created_by_name'] : 'N/A',
                    'converted_by' => ($appointment['converted_by_name']) ? $appointment['converted_by_name'] : 'N/A',
                    'updated_by' => ($appointment['updated_by_name']) ? $appointment['updated_by_name'] : 'N/A',
                    'actions' => view('admin.appointments.actions_elastic', compact('appointment', 'invoice', 'invoiceid', 'unscheduled_appointment_status', 'cancelled_appointment_status'))->render(),
                );
                if (Gate::allows('appointments_appointment_status')) {
                    if ($unscheduled_appointment_status && ($appointment['appointment_status_id'] == $unscheduled_appointment_status->id)) {
                        $records["data"][$index]['appointment_status_id'] = ($appointment['appointment_status_id'] ? ($AppointmentStatuses[$appointment['appointment_status_id']]->parent_id ? $AppointmentStatuses[$AppointmentStatuses[$appointment['appointment_status_id']]->parent_id]->name : $appointment['appointment_status_name']) : '');
                    } else {
                        $records["data"][$index]['appointment_status_id'] = '<a id="appointment' . $appointment['_id'] . '" href="' . route('admin.appointments.showappointmentstatus', ['id' => $appointment['_id']]) . '" data-target="#ajax" data-toggle="modal">' . ($appointment['appointment_status_id'] ? ($AppointmentStatuses[$appointment['appointment_status_id']]->parent_id ? $AppointmentStatuses[$AppointmentStatuses[$appointment['appointment_status_id']]->parent_id]->name : $appointment['appointment_status_name']) : '') . '</a>';
                    }
                } else {
                    $records["data"][$index]['appointment_status_id'] = ($appointment['appointment_status_id'] ? ($AppointmentStatuses[$appointment['appointment_status_id']]->parent_id ? $AppointmentStatuses[$AppointmentStatuses[$appointment['appointment_status_id']]->parent_id]->name : $appointment['appointment_status_name']) : '');
                }
                $index++;
            }
        }
        $records["draw"] = $sEcho;
        $records["recordsTotal"] = $iTotalRecords;
        $records["recordsFiltered"] = $iTotalRecords;
        return response()->json($records);
    }
    /**
     * Get Default Listing for Appointments
     *
     * @param Request $request
     * @return mixed
     */
    private function getDefaultListing(Request $request)
    {

        $where = array();
        /*
         * Reset form filter is applied
         */
        $filename = 'appointments';
        $filters = getFilters($request->all());
        if ($request->has('sort')) {
            list($orderBy, $order) = getSortBy($request, 'appointments.scheduled_date', 'DESC', 'appointments');
            Filters::put(Auth::User()->id, 'appointments', 'order_by', $orderBy);
            Filters::put(Auth::User()->id, 'appointments', 'order', $order);
        } else {
            $orderBy = 'scheduled_date';
            $order = 'desc';
            if ($orderBy == 'scheduled_date') {
                $orderBy = 'appointments.scheduled_date';
                Filters::put(Auth::User()->id, 'appointments', 'order_by', $orderBy);
                Filters::put(Auth::User()->id, 'appointments', 'order', $order);
            }
        }
        if (hasFilter($filters, 'patient_id')) {
            $where[] = array(['users.id' => GeneralFunctions::patientSearch($filters['patient_id'])]);
            Filters::put(Auth::User()->id, $filename, 'patient_id', GeneralFunctions::patientSearch($filters['patient_id']));
        }
        if (hasFilter($filters, 'phone')) {
            $where[] = array(
                'users.phone',
                'like',
                '%' . GeneralFunctions::cleanNumber($filters['phone']) . '%'
            );
            Filters::put(Auth::User()->id, $filename, 'phone', $filters['phone']);
        }
        if (hasFilter($filters, 'date_from')) {
            $where[] = array(
                'appointments.scheduled_date',
                '>=',
                $filters['date_from'] . ' 00:00:00'
            );
            Filters::put(Auth::User()->id, $filename, 'date_from', $filters['date_from'] . ' 00:00:00');
        }
        if (hasFilter($filters, 'date_to')) {
            $where[] = array(
                'appointments.scheduled_date',
                '<=',
                $filters['date_to'] . ' 23:59:59'
            );
            Filters::put(Auth::User()->id, $filename, 'date_to', $filters['date_to'] . ' 23:59:59');
        }
        if (hasFilter($filters, 'doctor_id')) {
            $where[] = array(['doctor_id' => $filters['doctor_id']]);
            Filters::put(Auth::User()->id, $filename, 'doctor_id', $filters['doctor_id']);
        }
        if (hasFilter($filters, 'region_id')) {
            $where[] = array(['region_id' => $filters['region_id']]);
            Filters::put(Auth::User()->id, $filename, 'region_id', $filters['region_id']);
        }
        if (hasFilter($filters, 'city_id')) {
            $where[] = array(['city_id' => $filters['city_id']]);
            Filters::put(Auth::User()->id, $filename, 'city_id', $filters['city_id']);
        }
        if (hasFilter($filters, 'service_id')) {
            $where[] = array(['service_id' => $filters['service_id']]);
            Filters::put(Auth::User()->id, $filename, 'service_id', $filters['service_id']);
        }
        if (hasFilter($filters, 'created_by')) {
            $where[] = array(['appointments.created_by' => $filters['created_by']]);
            Filters::put(Auth::User()->id, $filename, 'created_by', $filters['created_by']);
        }
        if (hasFilter($filters, 'converted_by')) {
            $where[] = array(['appointments.converted_by' => $filters['converted_by']]);
            Filters::put(Auth::User()->id, 'appointments', 'converted_by', $filters['converted_by']);
        }
        if (hasFilter($filters, 'updated_by')) {
            $where[] = array(['appointments.updated_by' => $filters['updated_by']]);
            Filters::put(Auth::User()->id, $filename, 'updated_by', $filters['updated_by']);
        }
        if (hasFilter($filters, 'appointment_status_id')) {
            $where[] = array(['appointments.base_appointment_status_id' => $filters['appointment_status_id']]);
            Filters::put(Auth::User()->id, $filename, 'appointment_status_id', $filters['appointment_status_id']);
        }
        if (hasFilter($filters, 'appointment_type_id')) {
            $where[] = array(['appointments.appointment_type_id' => $filters['appointment_type_id']]);
            Filters::put(Auth::user()->id, $filename, 'appointment_type_id', $filters['appointment_type_id']);
        }
        if (hasFilter($filters, 'consultancy_type')) {
            $where[] = array(['appointments.consultancy_type' => $filters['consultancy_type']]);
            Filters::put(Auth::User()->id, $filename, 'consultancy_type', $filters['consultancy_type']);
        }
        if (hasFilter($filters, 'created_from')) {
            $where[] = array(
                'appointments.created_at',
                '>=',
                $filters['created_from'] . ' 00:00:00'
            );
            Filters::put(Auth::User()->id, $filename, 'created_from', $filters['created_from']);
        }
        if (hasFilter($filters, 'created_to')) {
            $where[] = array(
                'appointments.created_at',
                '<=',
                $filters['created_to'] . ' 23:59:59'
            );
            Filters::put(Auth::User()->id, $filename, 'created_to', $filters['created_to']);
        }
        if (hasFilter($filters, 'phone')) {
            $phone = substr($filters['phone'],1);
            $where[] = array(['users.phone' => $phone]);
            Filters::put(Auth::User()->id, $filename, 'phone', $phone);
        }
        $consultancyslug = AppointmentTypes::where('slug', '=', 'consultancy')->first();
        $treatmentslug = AppointmentTypes::where('slug', '=', 'treatment')->first();
        if (Gate::allows('appointments_consultancy')) {
            $countQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->where('appointments.appointment_type_id', '=', $consultancyslug->id)
                ->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (Gate::allows('appointments_services')) {
            $countQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->where('appointments.appointment_type_id', '=', $treatmentslug->id)
                ->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (Gate::allows('appointments_services') && Gate::allows('appointments_consultancy')) {
            $countQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (!Gate::allows('appointments_services') && !Gate::allows('appointments_consultancy')) {
            $countQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->where([
                ['appointments.appointment_type_id', '!=', $consultancyslug->id],
                ['appointments.appointment_type_id', '!=', $treatmentslug->id]
            ])
                ->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        $countQuery->where('appointment_type_id', config('constants.appointment_type_consultancy'));
        if (count($where)) {
            $countQuery->where($where);
        }
        if (hasFilter($filters, 'location_id')) {
            $ids = explode(',', $filters['location_id']);
            if (count($ids) > 1) {
                $countQuery->whereIn('location_id', $ids);
            } else {
                $countQuery->where('location_id', $ids);
            }
            Filters::put(Auth::User()->id, $filename, 'location_id', $filters['location_id']);
        }
        if (hasFilter($filters, 'name')) {
            $countQuery->where(function ($query) use ($filters) {
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
            Filters::put(Auth::User()->id, $filename, 'name', $filters['name']);
        }
        $iTotalRecords = $countQuery->count();
        list($iDisplayLength, $iDisplayStart, $pages, $page) = getPaginationElement($request, $iTotalRecords);
        $records = array();
        $records["data"] = array();
        if (Gate::allows('appointments_consultancy')) {
            $resultQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->where('appointments.appointment_type_id', '=', $consultancyslug->id)
                ->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (Gate::allows('appointments_services')) {
            $resultQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->where('appointments.appointment_type_id', '=', $treatmentslug->id)
                ->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (Gate::allows('appointments_consultancy') && Gate::allows('appointments_services')) {
            $resultQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (!Gate::allows('appointments_consultancy') && !Gate::allows('appointments_services')) {
            $resultQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->where([
                ['appointments.appointment_type_id', '!=', $consultancyslug->id],
                ['appointments.appointment_type_id', '!=', $treatmentslug->id]
            ])
                ->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        $resultQuery->where('appointment_type_id', config('constants.appointment_type_consultancy'));
        if (count($where)) {
            $resultQuery->where($where);
        }
        if (hasFilter($filters, 'location_id')) {
            $ids = explode(',', $filters['location_id']);
            if (count($ids) > 1) {
                $resultQuery->whereIn('location_id', $ids);
            } else {
                $resultQuery->where('location_id', $ids);
            }
            Filters::put(Auth::User()->id, $filename, 'location_id', $filters['location_id']);
        }
        if (hasFilter($filters, 'name')) {
            $resultQuery->where(function ($query) use ($filters) {
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
            Filters::put(Auth::User()->id, $filename, 'name', $filters['name']);
        }
        if ($orderBy == 'name') { /* Need to append appropriate table name to order by, it was missing before*/
            $orderBy = 'appointments.name';
        }
        $Appointments = $resultQuery->select('*', 'appointments.name as patient_name', 'appointments.id as app_id', 'appointments.created_by as app_created_by', 'appointments.updated_by as app_updated_by', 'appointments.created_at as app_created_at')
            ->limit($iDisplayLength)
            ->offset($iDisplayStart)
            ->orderBy("appointments.created_at", "DESC")
            ->get();
        $invoicearray = array();
        $records = $this->getFiltersData($records, $filename);
        if ($Appointments) {
            $Regions = Regions::getAllRecordsDictionary(Auth::User()->account_id);
            $Users = User::getAllRecords(Auth::User()->account_id)->getDictionary();
            $AppointmentStatuses = AppointmentStatuses::getAllRecordsDictionary(Auth::User()->account_id);
            $invoice_status = InvoiceStatuses::where('slug', '=', 'paid')->first();
            $unscheduled_appointment_status = AppointmentStatuses::getUnScheduledStatusOnly(Auth::User()->account_id, ['id']);
            $cancelled_appointment_status = AppointmentStatuses::getCancelledStatusOnly(Auth::User()->account_id);
            $index = 0;
            $invoiceid = 0;
            foreach ($Appointments as $appointment) {
                $invoice = Invoices::where([
                    ['appointment_id', '=', $appointment->app_id],
                    ['invoice_status_id', '=', $invoice_status->id]
                ])->first();
                $invoicearray[] = $invoice;
                if ($invoice) {
                    $invoiceid = $invoice->id;
                }
                if ($appointment->consultancy_type == 'in_person') {
                    $consultancy_type = 'In Person';
                } else if ($appointment->consultancy_type == 'virtual') {
                    $consultancy_type = 'Virtual';
                } else {
                    $consultancy_type = '';
                }
                $records["data"][$index] = array(
                    'id' => $appointment->app_id,
                    'patient_id' => $appointment->patient_id,
                    'Patient_ID' => GeneralFunctions::patientSearchStringAdd($appointment->patient_id),
                    'name' => ($appointment->patient_name) ? $appointment->patient_name : $appointment->name,
                    'phone' => GeneralFunctions::prepareNumber4Call($appointment->phone),
                    'scheduled_date' => ($appointment->scheduled_date) ? Carbon::parse($appointment->scheduled_date, null)->format('M j, Y') . ' at ' . Carbon::parse($appointment->scheduled_time, null)->format('h:i A') : '-',
                    'doctor_id' => $appointment->doctor->name ?? 'N/A',
                    'doctorId' => $appointment->doctor->id ?? 0,
                    'region_id' => (array_key_exists($appointment->region_id, $Regions)) ? $Regions[$appointment->region_id]->name : 'N/A',
                    'city_id' => $appointment->city_id ? $appointment->city->name : 'N/A',
                    'cityId' => $appointment->city_id ?? 0,
                    'location_id' => $appointment->location_id ? $appointment->location->name : 'N/A',
                    'locationId' => $appointment->location_id ?? 'N/A',
                    'service_id' => $appointment->service->name ?? 'N/A',
                    'resource_id' => $appointment->resource_id ?? 0,
                    'appointment_type_id' => $appointment->appointment_type->name,
                    'appointment_type' => $appointment->appointment_type->id,
                    'consultancy_type' => $consultancy_type,
                    'created_at' => Carbon::parse($appointment->app_created_at)->format('F j,Y h:i A'),
                    'created_by' => array_key_exists($appointment->app_created_by, $Users) ? $Users[$appointment->app_created_by]->name : 'N/A',
                    'converted_by' => array_key_exists($appointment->converted_by, $Users) ? $Users[$appointment->converted_by]->name : 'N/A',
                    'updated_by' => array_key_exists($appointment->app_updated_by, $Users) ? $Users[$appointment->app_updated_by]->name : 'N/A',
                    'unscheduled_appointment_status' => $unscheduled_appointment_status,
                    'cancelled_appointment_status' => $cancelled_appointment_status,
                    'appointment_status_id' => ($appointment->appointment_status_id ? ($appointment->appointment_status->parent_id ? $AppointmentStatuses[$appointment->appointment_status->parent_id]->name : $appointment->appointment_status->name) : ''),
                    'appointment_status' => $appointment->appointment_status_id,
                    'invoice_id' => $invoiceid,
                    'invoice' => $invoice,
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
            $records["status"] = true;
            $records["message"] = "Records has been deleted successfully!";
        }
        $records["permissions"] = [
            'edit' => Gate::allows('appointments_edit'),
            'consultancy' => Gate::allows('appointments_consultancy'),
            'treatment' => Gate::allows('appointments_services'),
            'delete' => Gate::allows('appointments_destroy'),
            'active' => Gate::allows('appointments_active'),
            'inactive' => Gate::allows('appointments_inactive'),
            'create' => Gate::allows('appointments_create'),
            'log' => Gate::allows('appointments_log'),
            'status' => Gate::allows('appointments_appointment_status'),
            'invoice' => Gate::allows('appointments_invoice'),
            'invoice_display' => Gate::allows('appointments_invoice_display'),
            'image_manage' => Gate::allows('appointments_image_manage'),
            'measurement_manage' => Gate::allows('appointments_measurement_manage'),
            'medical_form_manage' => Gate::allows('appointments_medical_form_manage'),
            'plans_create' => Gate::allows('appointments_plans_create'),
            'patient_card' => Gate::allows('appointments_patient_card'),
            'contact' => Gate::allows('contact'),
        ];

        return ApiHelper::apiDataTable($records);
    }
    private function getDefaultTreatmentListing(Request $request)
    {
        $where = array();
        /*
         * Reset form filter is applied
         */
        $filename = 'appointments';
        $filters = getFilters($request->all());
        if ($request->has('sort')) {
            list($orderBy, $order) = getSortBy($request, 'appointments.created_at', 'DESC', 'appointments');
            Filters::put(Auth::User()->id, 'appointments', 'order_by', $orderBy);
            Filters::put(Auth::User()->id, 'appointments', 'order', $order);
        } else {
            $orderBy = 'created_at';
            $order = 'desc';
            if ($orderBy == 'created_at') {
                $orderBy = 'appointments.created_at';
                Filters::put(Auth::User()->id, 'appointments', 'order_by', $orderBy);
                Filters::put(Auth::User()->id, 'appointments', 'order', $order);
            }
        }
        if (hasFilter($filters, 'patient_id')) {
            $where[] = array(['users.id' => GeneralFunctions::patientSearch($filters['patient_id'])]);
            Filters::put(Auth::User()->id, $filename, 'patient_id', GeneralFunctions::patientSearch($filters['patient_id']));
        }
        if (hasFilter($filters, 'phone')) {
            $where[] = array(
                'users.phone',
                'like',
                '%' . GeneralFunctions::cleanNumber($filters['phone']) . '%'
            );
            Filters::put(Auth::User()->id, $filename, 'phone', $filters['phone']);
        }
        if (hasFilter($filters, 'date_from')) {
            $where[] = array(
                'appointments.scheduled_date',
                '>=',
                $filters['date_from'] . ' 00:00:00'
            );
            Filters::put(Auth::User()->id, $filename, 'date_from', $filters['date_from'] . ' 00:00:00');
        }
        if (hasFilter($filters, 'date_to')) {
            $where[] = array(
                'appointments.scheduled_date',
                '<=',
                $filters['date_to'] . ' 23:59:59'
            );
            Filters::put(Auth::User()->id, $filename, 'date_to', $filters['date_to'] . ' 23:59:59');
        }
        if (hasFilter($filters, 'doctor_id')) {
            $where[] = array(['doctor_id' => $filters['doctor_id']]);
            Filters::put(Auth::User()->id, $filename, 'doctor_id', $filters['doctor_id']);
        }
        if (hasFilter($filters, 'region_id')) {
            $where[] = array(['region_id' => $filters['region_id']]);
            Filters::put(Auth::User()->id, $filename, 'region_id', $filters['region_id']);
        }
        if (hasFilter($filters, 'city_id')) {
            $where[] = array(['city_id' => $filters['city_id']]);
            Filters::put(Auth::User()->id, $filename, 'city_id', $filters['city_id']);
        }
        if (hasFilter($filters, 'phone')) {
            $phone = substr($filters['phone'],1);
            $where[] = array(['users.phone' => $phone]);
            Filters::put(Auth::User()->id, $filename, 'phone', $phone);
        }
        if (hasFilter($filters, 'service_id')) {
            $where[] = array(['service_id' => $filters['service_id']]);
            Filters::put(Auth::User()->id, $filename, 'service_id', $filters['service_id']);
        }
        if (hasFilter($filters, 'created_by')) {
            $where[] = array(['appointments.created_by' => $filters['created_by']]);
            Filters::put(Auth::User()->id, $filename, 'created_by', $filters['created_by']);
        }
        if (hasFilter($filters, 'converted_by')) {
            $where[] = array(['appointments.converted_by' => $filters['converted_by']]);
            Filters::put(Auth::User()->id, 'appointments', 'converted_by', $filters['converted_by']);
        }
        if (hasFilter($filters, 'updated_by')) {
            $where[] = array(['appointments.updated_by' => $filters['updated_by']]);
            Filters::put(Auth::User()->id, $filename, 'updated_by', $filters['updated_by']);
        }
        if (hasFilter($filters, 'appointment_status_id')) {
            $where[] = array(['appointments.base_appointment_status_id' => $filters['appointment_status_id']]);
            Filters::put(Auth::User()->id, $filename, 'appointment_status_id', $filters['appointment_status_id']);
        }
        if (hasFilter($filters, 'appointment_type_id')) {
            $where[] = array(['appointments.appointment_type_id' => $filters['appointment_type_id']]);
            Filters::put(Auth::user()->id, $filename, 'appointment_type_id', $filters['appointment_type_id']);
        }
        if (hasFilter($filters, 'consultancy_type')) {
            $where[] = array(['appointments.consultancy_type' => $filters['consultancy_type']]);
            Filters::put(Auth::User()->id, $filename, 'consultancy_type', $filters['consultancy_type']);
        }
        if (hasFilter($filters, 'created_from')) {
            $where[] = array(
                'appointments.created_at',
                '>=',
                $filters['created_from'] . ' 00:00:00'
            );
            Filters::put(Auth::User()->id, $filename, 'created_from', $filters['created_from']);
        }
        if(hasFilter($filters, 'created_to')) {
            $where[] = array(
                'appointments.created_at',
                '<=',
                $filters['created_to'] . ' 23:59:59'
            );
            Filters::put(Auth::User()->id, $filename, 'created_to', $filters['created_to']);
        }
        $consultancyslug = AppointmentTypes::where('slug', '=', 'consultancy')->first();
        $treatmentslug = AppointmentTypes::where('slug', '=', 'treatment')->first();
        if (Gate::allows('appointments_consultancy')) {
            $countQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->where('appointments.appointment_type_id', '=', $consultancyslug->id)
                ->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (Gate::allows('appointments_services')) {
            $countQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->where('appointments.appointment_type_id', '=', $treatmentslug->id)
                ->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (Gate::allows('appointments_services') && Gate::allows('appointments_consultancy')) {
            $countQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (!Gate::allows('appointments_services') && !Gate::allows('appointments_consultancy')) {
            $countQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->where([
                ['appointments.appointment_type_id', '!=', $consultancyslug->id],
                ['appointments.appointment_type_id', '!=', $treatmentslug->id]
            ])
                ->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        $countQuery->where('appointment_type_id', config('constants.appointment_type_service'));
        if (count($where)) {
            $countQuery->where($where);
        }
        if (hasFilter($filters, 'location_id')) {
            $ids = explode(',', $filters['location_id']);
            if (count($ids) > 1) {
                $countQuery->whereIn('location_id', $ids);
            } else {
                $countQuery->where('location_id', $ids);
            }
            Filters::put(Auth::User()->id, $filename, 'location_id', $filters['location_id']);
        }
        if (hasFilter($filters, 'name')) {
            $countQuery->where(function ($query) use ($filters) {
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
            Filters::put(Auth::User()->id, $filename, 'name', $filters['name']);
        }
        $iTotalRecords = $countQuery->count();
        list($iDisplayLength, $iDisplayStart, $pages, $page) = getPaginationElement($request, $iTotalRecords);
        $records = array();
        $records["data"] = array();
        if (Gate::allows('appointments_consultancy')) {
            $resultQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->where('appointments.appointment_type_id', '=', $consultancyslug->id)
                ->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (Gate::allows('appointments_services')) {
            $resultQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->where('appointments.appointment_type_id', '=', $treatmentslug->id)
                ->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (Gate::allows('appointments_consultancy') && Gate::allows('appointments_services')) {
            $resultQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (!Gate::allows('appointments_consultancy') && !Gate::allows('appointments_services')) {
            $resultQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->where([
                ['appointments.appointment_type_id', '!=', $consultancyslug->id],
                ['appointments.appointment_type_id', '!=', $treatmentslug->id]
            ])
                ->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        $resultQuery->where('appointment_type_id', config('constants.appointment_type_service'));
        if (count($where)) {
            $resultQuery->where($where);
        }
        if (hasFilter($filters, 'location_id')) {
            $ids = explode(',', $filters['location_id']);
            if (count($ids) > 1) {
                $resultQuery->whereIn('location_id', $ids);
            } else {
                $resultQuery->where('location_id', $ids);
            }
            Filters::put(Auth::User()->id, $filename, 'location_id', $filters['location_id']);
        }
        if (hasFilter($filters, 'name')) {
            $resultQuery->where(function ($query) use ($filters) {
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
            Filters::put(Auth::User()->id, $filename, 'name', $filters['name']);
        }
        if ($orderBy == 'name') { /* Need to append appropriate table name to order by, it was missing before*/
            $orderBy = 'appointments.name';
        }
        $Appointments = $resultQuery->select('*', 'appointments.name as patient_name', 'appointments.id as app_id', 'appointments.created_by as app_created_by', 'appointments.updated_by as app_updated_by', 'appointments.created_at as app_created_at')
            ->limit($iDisplayLength)
            ->offset($iDisplayStart)
            ->orderBy("appointments.created_at", "DESC")
            ->get();
        $invoicearray = array();
        $records = $this->getFiltersData($records, $filename);
        if ($Appointments) {
            $Regions = Regions::getAllRecordsDictionary(Auth::User()->account_id);
            $Users = User::getAllRecords(Auth::User()->account_id)->getDictionary();
            $AppointmentStatuses = AppointmentStatuses::getAllRecordsDictionary(Auth::User()->account_id);
            $invoice_status = InvoiceStatuses::where('slug', '=', 'paid')->first();
            // Default Un-scheduled Appointment Status
            $unscheduled_appointment_status = AppointmentStatuses::getUnScheduledStatusOnly(Auth::User()->account_id, ['id']);
            $cancelled_appointment_status = AppointmentStatuses::getCancelledStatusOnly(Auth::User()->account_id);
            $index = 0;
            $invoiceid = 0;
            foreach ($Appointments as $appointment) {
                $invoice = Invoices::where([
                    ['appointment_id', '=', $appointment->app_id],
                    ['invoice_status_id', '=', $invoice_status->id]
                ])->first();
                $invoicearray[] = $invoice;
                if ($invoice) {
                    $invoiceid = $invoice->id;
                }
                if ($appointment->consultancy_type == 'in_person') {
                    $consultancy_type = 'In Person';
                } else if ($appointment->consultancy_type == 'virtual') {
                    $consultancy_type = 'Virtual';
                } else {
                    $consultancy_type = '';
                }
                $records["data"][$index] = array(
                    'id' => $appointment->app_id,
                    'patient_id' => $appointment->patient_id,
                    'Patient_ID' => GeneralFunctions::patientSearchStringAdd($appointment->patient_id),
                    'name' => ($appointment->patient_name) ? $appointment->patient_name : $appointment->name,
                    'phone' => GeneralFunctions::prepareNumber4Call($appointment->phone),
                    'scheduled_date' => ($appointment->scheduled_date) ? Carbon::parse($appointment->scheduled_date, null)->format('M j, Y') . ' at ' . Carbon::parse($appointment->scheduled_time, null)->format('h:i A') : '-',
                    'apt_scheduled_date' => $appointment->scheduled_date,
                    'doctor_id' => $appointment->doctor->name ?? 'N/A',
                    'doctorId' => $appointment->doctor->id ?? 0,
                    'region_id' => (array_key_exists($appointment->region_id, $Regions)) ? $Regions[$appointment->region_id]->name : 'N/A',
                    'city_id' => $appointment->city_id ? $appointment->city->name : 'N/A',
                    'cityId' => $appointment->city_id ?? 0,
                    'location_id' => $appointment->location_id ? $appointment->location->name : 'N/A',
                    'locationId' => $appointment->location_id ?? 'N/A',
                    'service_id' => $appointment->service->name ?? 'N/A',
                    'resource_id' => $appointment->resource_id ?? 0,
                    'appointment_type_id' => $appointment->appointment_type->name,
                    'appointment_type' => $appointment->appointment_type->id,
                    'consultancy_type' => $consultancy_type,
                    'created_at' => Carbon::parse($appointment->app_created_at)->format('F j,Y h:i A'),
                    'created_by' => array_key_exists($appointment->app_created_by, $Users) ? $Users[$appointment->app_created_by]->name : 'N/A',
                    'converted_by' => array_key_exists($appointment->converted_by, $Users) ? $Users[$appointment->converted_by]->name : 'N/A',
                    'updated_by' => array_key_exists($appointment->app_updated_by, $Users) ? $Users[$appointment->app_updated_by]->name : 'N/A',
                    'unscheduled_appointment_status' => $unscheduled_appointment_status,
                    'cancelled_appointment_status' => $cancelled_appointment_status,
                    'appointment_status_id' => ($appointment->appointment_status_id ? ($appointment->appointment_status->parent_id ? $AppointmentStatuses[$appointment->appointment_status->parent_id]->name : $appointment->appointment_status->name) : ''),
                    'appointment_status' => $appointment->appointment_status_id,
                    'invoice_id' => $invoiceid,
                    'invoice' => $invoice,
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
            $records["status"] = true;
            $records["message"] = "Records has been deleted successfully!";
        }
        $records["permissions"] = [
            'edit' => Gate::allows('appointments_edit'),
            'consultancy' => Gate::allows('appointments_consultancy'),
            'treatment' => Gate::allows('appointments_services'),
            'delete' => Gate::allows('appointments_destroy'),
            'active' => Gate::allows('appointments_active'),
            'inactive' => Gate::allows('appointments_inactive'),
            'create' => Gate::allows('appointments_create'),
            'log' => Gate::allows('appointments_log'),
            'status' => Gate::allows('appointments_appointment_status'),
            'invoice' => Gate::allows('appointments_invoice'),
            'invoice_display' => Gate::allows('appointments_invoice_display'),
            'image_manage' => Gate::allows('appointments_image_manage'),
            'measurement_manage' => Gate::allows('appointments_measurement_manage'),
            'medical_form_manage' => Gate::allows('appointments_medical_form_manage'),
            'plans_create' => Gate::allows('appointments_plans_create'),
            'patient_card' => Gate::allows('appointments_patient_card'),
            'contact' => Gate::allows('contact'),
        ];
        return ApiHelper::apiDataTable($records);
    }
    /**
     * @param $records
     * @param $filename
     * @return mixed
     */
    private function getFiltersData($records, $filename) {
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
        if (Gate::allows('appointments_services')) {
            $appointment_types = AppointmentTypes::where('slug', '=', 'treatment')->get()->pluck('name', 'id');
        }
        if (Gate::allows('appointments_consultancy') && Gate::allows('appointments_services')) {
            $appointment_types = AppointmentTypes::get()->pluck('name', 'id');
        }
        if (!Gate::allows('appointments_consultancy') && !Gate::allows('appointments_services')) {
            $appointment_types = array();
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
        if ($user->user_type_id == config("constants.application_user_id") || $user->user_type_id == config("constants.administrator_id")) {
            $userHasLocation = UserHasLocations::join('locations', 'user_has_locations.location_id', '=', 'locations.id')->where('user_has_locations.user_id', '=', $user->id)->orderby('name', 'asc')->first();
            if ($userHasLocation) {
                $locations = Locations::where('id', '=', $userHasLocation->location_id)->first();
                $city_id = $locations->city->id;
                $location_id = $locations->id;
                $doctors = DoctorHasLocations::where('location_id', '=', $location_id)->first();
                $urlquery = "?city_id=" . $city_id . "&location_id=" . $location_id;
                if ($doctors) {
                    $urlquery = "?city_id=" . $city_id . "&location_id=" . $location_id . "&doctor_id=" . $doctors->user_id;
                }
                if ($request->city_id && $request->location_id) {
                } else {
                    return redirect(route('admin.appointments.create') . $urlquery);
                }
            }
        }
        /*
         * Set dropdown for all asthetic operators/ consultants
         */
        if ($user->user_type_id == config("constants.practitioner_id")) {
            $userHasLocation = DoctorHasLocations::join('locations', 'doctor_has_locations.location_id', '=', 'locations.id')->where('doctor_has_locations.user_id', '=', $user->id)->orderby('name', 'asc')->first();
            if ($userHasLocation) {
                $locations = Locations::where('id', '=', $userHasLocation->location_id)->first();
                $city_id = $locations->city_id;
                $location_id = $locations->id;
                $urlquery = "?city_id=" . $city_id . "&location_id=" . $location_id . "&doctor_id=" . $user->id;
                if ($request->city_id && $request->location_id) {
                } else {
                    return redirect(route('admin.appointments.create') . $urlquery);
                }
            }
        }
        if (!Gate::allows('appointments_consultancy')) {
            return abort(401);
        }
        if ($request->lead_id) {
            $lead = Leads::where(['id' => $request->lead_id])->first();
            if ($lead) {
                $lead = array(
                    'id' => $lead->id,
                    'name' => ($lead->patient_id) ? $lead->patient->name : null,
                    'phone' => ($lead->patient_id) ? $lead->patient->phone : null,
                    'dob' => ($lead->patient_id) ? $lead->patient->dob : null,
                    'address' => ($lead->patient_id) ? $lead->patient->address : null,
                    'cnic' => ($lead->patient_id) ? $lead->patient->cnic : null,
                    'referred_by' => ($lead->patient_id) ? $lead->patient->referred_by : null,
                    'service_id' => $lead->service_id,
                );
            } else {
                $lead = array(
                    'id' => '',
                    'name' => '',
                    'phone' => '',
                    'done' => '',
                    'address' => '',
                    'cnic' => '',
                    'referred_by' => '',
                    'service_id' => '',
                );
            }
        } else {
            $lead = array(
                'id' => '',
                'name' => '',
                'phone' => '',
                'done' => '',
                'address' => '',
                'cnic' => '',
                'referred_by' => '',
                'service_id' => '',
            );
        }
        $employees = User::getAllActiveRecords(Auth::User()->account_id);
        if ($employees) {
            $employees = $employees->pluck('full_name', 'id');
        } else {
            $employees = array();
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
     * @param \Illuminate\Http\Request $request
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
     * @param \Illuminate\Http\Request $request
     * @return Validator $validator;
     */
    protected function verifyUpdateFields(Request $request)
    {
        return $validator = Validator::make($request->all(), [
            'name' => 'required',
            'phone' => 'required',
            'scheduled_date' => 'required',
            'scheduled_time' => 'required',
            'city_id' => 'required',
            'location_id' => 'required',
            'doctor_id' => 'required',
        ]);
    }
    /**
     * Store a newly created Appointment in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        if (!Gate::allows('appointments_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
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
            if ($request->appointment_type == Config::get('constants.appointment_type_consultancy_string') || $request->appointment_type_id == Config::get('constants.appointment_type_consultancy')) {
                $response = Resources::getDoctorRotaHasDay($request->start, $request->doctor_id);
                if (isset($response['resource_id']) && $response['resource_id']) {
                    $appointment_data['resource_id'] = $response['resource_id'];
                }
                if (isset($response['resource_has_rota_day_id']) && $response['resource_has_rota_day_id']) {
                    $appointment_data['resource_has_rota_day_id'] = $response['resource_has_rota_day_id'];
                }
            }
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
                $service_duration = Services::find($request->service_id)->value("duration");
                $duraton_array = explode(":", $service_duration);
                if (count($duraton_array) == 2) {
                    $end = Carbon::parse($start)->addHour($service_duration[0])->addMinute($duraton_array[1]);
                    $start = Carbon::parse($start)->format("Y-m-d H:i:s");
                }
                $doctor_checking = Resources::checkingDoctorAvailbility($request->doctor_id, $start, $end);
                if ($doctor_checking) {
                    $appointment_data['scheduled_date'] = Carbon::parse($request->start)->format("Y-m-d");
                    $appointment_data['scheduled_time'] = Carbon::parse($request->start)->format("H:i:s");
                    $appointment_data['first_scheduled_date'] = Carbon::parse($request->start)->format("Y-m-d");
                    $appointment_data['first_scheduled_time'] = Carbon::parse($request->start)->format("H:i:s");
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
            if (!$request->lead_id) {
                $lead_obj = $appointment_data;
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
                $lead_obj['lead_status_id'] = $default_converted_lead_status_id;
                $lead_obj['created_at'] = Filters::getCurrentTimeStamp();
                $lead_obj['updated_at'] = Filters::getCurrentTimeStamp();
                $lead_obj['location_id'] = $request->location_id;
                $lead_obj['lead_status_id'] = $default_converted_lead_status_id;
                $patient = Patients::where(['phone' => $appointment_data['phone']])->orderBy('phone', 'desc')->first();
                if ($request->new_patient == '1') {
                    $appointment_data['user_type_id'] = 3;
                    if(!$patient){
                        $patient = Patients::createRecord($appointment_data, 1);
                    } else {
                        return ApiHelper::apiResponse($this->success, 'Phone number already exist', false);
                    }

                    $checkLeadExistance = Leads::updateOrCreate([
                        'phone' => $appointment_data['phone'],
                        'account_id' => Auth::User()->account_id
                    ], $lead_obj);
                    $lead = $checkLeadExistance;
                    LeadsServices::updateOrCreate([
                        'lead_id' => $lead->id,
                        'service_id' => $appointment_data['service_id'],
                    ],[
                        'lead_id' => $lead->id,
                        'service_id' => $appointment_data['service_id'],
                    ]);
                    LeadsServices::where(['lead_id' => $lead->id])->update(['status' => 0]);
                    $lead_service = LeadsServices::where(['lead_id' => $lead->id, 'service_id' => $appointment_data['service_id']])->first();
                    $lead_service->update(['status' => 1]);
                }
            } else {
                $lead = Leads::findOrFail($request->lead_id);
                /*
                 * If appointment is for the first time then
                 * update user information, otherwise not
                 */
                $patient = Patients::where(['phone' => $appointment_data['phone']])->orderBy('phone', 'desc')->first();
                if(!$patient){
                    $appointment_data['user_type_id'] = 3;
                    $patient = Patients::createRecord($appointment_data, 1);
                } else {
                    $appointment_data['patient_id'] = $patient->id;
                    Patients::where(['id' => $patient->id])->update([
                        'name' => $appointment_data['name'],
                        'gender' => $appointment_data['gender'],
                        'referred_by' => $appointment_data['referred_by'] ?? null,
                    ]);
                }

                LeadsServices::updateOrCreate([
                    'lead_id' => $lead->id,
                    'service_id' => $appointment_data['service_id'],
                ],[
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
                $appointment_data['scheduled_date'] = Carbon::parse($request->scheduled_date)->format("Y-m-d");
                $appointment_data['scheduled_time'] = Carbon::parse($request->scheduled_time)->format("H:i:s");
            } else {
                $appointment_data['scheduled_date'] = Carbon::parse($request->start)->format("Y-m-d");
                $appointment_data['scheduled_time'] = Carbon::parse($request->start)->format("H:i:s");
            }
            $appointment_data['appointment_status_id'] = config('constants.appointment_status_pending');
            $appointment = Appointments::create($appointment_data);
            $find_cons = Appointments::latest()->first();
            if($find_cons){
                $lead = Leads::where(['phone' => $appointment_data['phone']])->orderBy('id', 'desc')->update(['name' => $patient->name, 'lead_status_id' => 4, 'location_id' => $find_cons->location_id, 'patient_id' => $appointment_data['patient_id']]);
                LeadsServices::where([
                    'lead_id' => $appointment_data['lead_id'],
                    'service_id' => $find_cons->service_id,
                ])->update([
                    'consultancy_id' => $find_cons->id
                ]);
            }
            /* Now We need to update name of all appointments that already in appointment table against patient
             */
            Appointments::where(['patient_id' => $appointment_data['patient_id']])->update(['name' => $patient->name]);
            // Based on allow message by status and scheduled date, allow send sms
            if ($appointment->appointment_status_allow_message && $appointment->scheduled_date) {
                $appointment->update(array(
                    'send_message' => 1
                ));
            }
            /*
             * Set Appointment Status if appointment scheduled date & time are not defined
             * case 1: If Scheduled Date is not set then status is 'un-scheduled'
             * case 2: If 'un-scheduled' is not set then set defautl status i.e. 'pending'
             */
            if (!$appointment->scheduled_date && !$appointment->scheduled_time) {
                $appointment_status = AppointmentStatuses::getUnScheduledStatusOnly(Auth::User()->account_id);
                if ($appointment_status) {
                    $appointment->update(array(
                        'appointment_status_id' => $appointment_status->id,
                        'base_appointment_status_id' => $appointment_status->id,
                        'appointment_status_allow_message' => 0,
                        'updated_at'=>Filters::getCurrentTimeStamp()
                    ));
                } else {
                    // Set default appointment status i.e. 'pending'
                    $appointment_status = AppointmentStatuses::getADefaultStatusOnly(Auth::User()->account_id);
                    if ($appointment_status) {
                        $appointment->update(array(
                            'appointment_status_id' => $appointment_status->id,
                            'base_appointment_status_id' => $appointment_status->id,
                            'appointment_status_allow_message' => 0,
                            'updated_at'=>Filters::getCurrentTimeStamp()
                        ));
                    } else {
                        $appointment->update(array(
                            'appointment_status_id' => null,
                            'base_appointment_status_id' => null,
                            'appointment_status_allow_message' => 0,
                            'updated_at'=>Filters::getCurrentTimeStamp()
                        ));
                    }
                }
            }
            $message = 'Record has been created successfully.';
            // Send Promotion SMS
            $this->sendPromotionSMS($appointment->id, $appointment_data['phone']);
            GeneralFunctions::saveAppointmentLogs('created', 'Consultancy', $appointment);
            /**
             * Dispatch Elastic Search Index
             */
            $this->dispatch(
                new IndexSingleAppointmentJob([
                    'account_id' => Auth::User()->account_id,
                    'appointment_id' => $appointment->id,
                    'patient_phone' => $appointment_data['phone']
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
    private function scheduledConsultancy(Request $request) {
        $appointment = new \stdClass();
        $appointment->city_id = $request->city_id;
        $appointment->doctor_id = $request->doctor_id;
        $appointment->location_id = $request->location_id;
        $appointment->appointment_type_id = 1;
        $rota = $this->checkRota($appointment, $request);
        if ($rota['status']) {
            return [
                'status' => true,
                'message' => 'Record updated successfully!'
            ];
        }
        return [
            'status' => false,
            'message' => $rota['message'] ?? "Sorry! rota cant be created"
        ];
    }
    private function sendPromotionSMS($appointmentId, $patient_phone)
    {
        // SEND SMS for Appointment Booked
        $SMSTemplate = SMSTemplates::getBySlug('promotion-sms', Auth::User()->account_id);
        if (!$SMSTemplate) {
            // SMS Promotion is disabled
            return array(
                'status' => true,
                'sms_data' => 'SMS Promotion is disabled',
                'error_msg' => '',
            );
        }
        $preparedText = Appointments::prepareSMSContent($appointmentId, $SMSTemplate->content);
        $setting = Settings::whereSlug('sys-current-sms-operator')->first();
        $UserOperatorSettings = UserOperatorSettings::getRecord(Auth::User()->account_id, $setting->data);
        if ($setting->data == 1) {
            $SMSObj = array(
                'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
                'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
                'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($patient_phone)),
                'text' => $preparedText,
                'mask' => $UserOperatorSettings->mask, // Setting ID 3 for Mask
                'test_mode' => $UserOperatorSettings->test_mode, // Setting ID 3 Test Mode
            );
            $response = TelenorSMSAPI::SendSMS($SMSObj);
        } else {
            $SMSObj = array(
                'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
                'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
                'from' => $UserOperatorSettings->mask,
                'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($patient_phone)),
                'text' => $preparedText,
                'test_mode' => $UserOperatorSettings->test_mode, // Setting ID 3 Test Mode
            );
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
        if (!Gate::allows('appointments_manage')) {
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
        if ($request->start) {
            $appointment_checkes = AppointmentCheckesWidget::AppointmentAppointmentCheckesfromcalender($request);
        } else {
            $appointment_checkes = array(
                'status' => true
            );
        }
        if ($request->lead_id) {
            $lead = Leads::where(['id' => $request->lead_id])->first();
            if ($lead) {
                $lead = array(
                    'id' => $lead->id,
                    'patient_id' => $lead->patient_id,
                    'name' => ($lead->patient_id) ? $lead->patient->name : null,
                    'phone' => ($lead->patient_id) ? $lead->patient->phone : null,
                    'dob' => ($lead->patient_id) ? $lead->patient->dob : null,
                    'address' => ($lead->patient_id) ? $lead->patient->address : null,
                    'cnic' => ($lead->patient_id) ? $lead->patient->cnic : null,
                    'referred_by' => ($lead->patient_id) ? $lead->patient->referred_by : null,
                    'service_id' => $lead->service_id,
                );
            } else {
                $lead = array(
                    'id' => '',
                    'patient_id' => '',
                    'name' => '',
                    'phone' => '',
                    'dob' => '',
                    'address' => '',
                    'cnic' => '',
                    'referred_by' => '',
                    'service_id' => '',
                );
            }
        } else {
            $lead = array(
                'id' => '',
                'patient_id' => '',
                'name' => '',
                'phone' => '',
                'dob' => '',
                'address' => '',
                'cnic' => '',
                'referred_by' => '',
                'service_id' => '',
            );
        }
        $employees = User::getAllActiveRecords(Auth::User()->account_id);
        if ($employees) {
            $employees = $employees->pluck('full_name', 'id');
        } else {
            $employees = array();
        }


        $intersect_resource_service_ids = LocationsWidget::loadAppointmentServiceByLocationResource($request->machine_id, Auth::User()->account_id);

        $intersect_location_doctor_service_ids = LocationsWidget::loadAppointmentServiceByLocationDoctor($request->location_id, $request->doctor_id, Auth::User()->account_id);

        $serviceIds = array();
        if (count($intersect_resource_service_ids) && count($intersect_location_doctor_service_ids)) {
            $serviceIds = array_intersect($intersect_resource_service_ids, $intersect_location_doctor_service_ids);
        }
        if (count($serviceIds)) {
            $services = Services::whereIn("id", $serviceIds)->get()->pluck('name', 'id');
        } else {
            return ApiHelper::apiResponse($this->success, "Services not found for this doctor and resource.", false);
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
            'genders' => Config::get("constants.gender_array")
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
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Http\JsonResponse|\Illuminate\View\View|void
     */
    public function createConsultingAppointment(Request $request)
    {
        if (!Gate::allows('appointments_manage')) {
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
            return response()->json(array("message" => "Invalid request"), 400);
        }
        if ($request->start) {
            $appointment_checkes = AppointmentCheckesWidget::AppointmentConsultancyCheckes($request);
        } else {
            $appointment_checkes = array(
                'status' => true
            );
        }
        if ($request->lead_id) {
            $lead = Leads::where(['id' => $request->lead_id])->first();
            if ($lead) {
                $lead = array(
                    'id' => $lead->id,
                    'name' => ($lead->lead_id) ? $lead->name : null,
                    'phone' => ($lead->lead_id) ? $lead->phone : null,
                    'referred_by' => ($lead->lead_id) ? $lead->referred_by : null,
                    'service_id' => $lead->service_id,
                );
            } else {
                $lead = array(
                    'id' => '',
                    'name' => '',
                    'phone' => '',
                    'referred_by' => '',
                    'service_id' => '',
                );
            }
        } else {
            $lead = array(
                'id' => '',
                'name' => '',
                'phone' => '',
                'referred_by' => '',
                'service_id' => '',
            );
        }
        $employees = User::getAllActiveRecords(Auth::User()->account_id);
        if ($employees) {
            $employees = $employees->pluck('full_name', 'id');
        } else {
            $employees = array();
        }
        $serviceIds = LocationsWidget::loadAppointmentServiceByLocationDoctor($request->location_id, $request->doctor_id, Auth::User()->account_id);
        if (count($serviceIds)) {
            $services = Services::whereIn("id", $serviceIds)->get()->pluck('name', 'id');
        } else {
            $services[''] = '';
        }
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
                'consultancy_types' => Config::get("constants.consultancy_type_array"),
                'genders' => Config::get("constants.gender_array")
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
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function detail($id)
    {
        if (!Gate::allows('appointments_manage') && !Gate::allows('appointments_view')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $invoice_status = InvoiceStatuses::where('slug', '=', 'paid')->first();
        $invoice = Invoices::where([
            ['appointment_id', '=', $id],
            ['invoice_status_id', '=', $invoice_status->id]
        ])->first();
        if($invoice){
            $invoicearray[] = $invoice;
            $invoiceid = $invoicearray[0]['id'];
        }else{
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
        return ApiHelper::apiResponse($this->success, 'Data found.',  true, [
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
                'contact' => Gate::allows('contact')
            ]
        ]);
    }
    /**
     * Show the form for editing Appointment.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit($id)
    {
        if (!Gate::allows('appointments_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $locationsids = array();
        $doctorids = array();
        $reverse_process = false;
        $appointment = Appointments::with('lead', 'patient')->find($id);
        if (!$appointment) {
            return ApiHelper::apiResponse($this->success, 'Resource not found.', false);
        }
        $resourceHadRotaDay = ResourceHasRotaDays::find($appointment->resource_has_rota_day_id);
        $cities = Cities::getActiveFeaturedOnly(ACL::getUserCities(), Auth::User()->account_id)->get();
        if ($cities) {
            $cities = $cities->pluck('full_name', 'id');
        }
        $appointment->scheduled_time = Carbon::parse($appointment->scheduled_time)->format("h:i A");
        if ($appointment->service_id) {
            $services = Services::where(['id' => $appointment->service_id])->get()->pluck('name', 'id');
            $serviceid = Services::where(['id' => $appointment->service_id])->first();
        } else {
            $services = Services::get()->pluck('name', 'id');
        }
        $locations = Locations::getActiveRecordsByCity($appointment->city_id, ACL::getUserCentres(), Auth::User()->account_id);
        /*For machine type we perform that work we can remove it if any problem happen but for linkage that is best*/
        foreach ($locations as $location) {
            $location_serivce = AppointmentEditWidget::loadlocationservice_edit($location->id, Auth::User()->account_id, $reverse_process);
            if (isset($serviceid) && in_array($serviceid->id, $location_serivce)) {
                $locationsids[] = $location->id;
            }
        }
        $locations = Locations::whereIn('id', $locationsids)->get();
        /*End*/
        if ($locations) {
            $locations = $locations->pluck("name", "id");
        }
        $doctors = $doctors_no_final = Doctors::getActiveOnly($appointment->location_id, Auth::User()->account_id);
        /*For machine type we perform that work we can remove it if any problem happen but for linkage that is best*/
        foreach ($doctors as $key => $doctor) {
            $doctor_serivce = AppointmentEditWidget::loaddoctorservice_edit($key, $appointment->location_id, Auth::User()->account_id, $reverse_process);
            if (isset($serviceid) && in_array($serviceid->id, $doctor_serivce)) {
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
                    ['is_consultancy', '=', '1']
                ])->get();
                if (count($doctor_rota) == 0) {
                    unset($doctors[$key]);
                }
            }
        }
        $back_date_config = Settings::whereSlug('sys-back-date-appointment')->select('data')->first();
        $setting = Settings::where('slug', '=', 'sys-virtual-consultancy')->first();
        return ApiHelper::apiResponse($this->success, 'Record Found', true, [
            'appointment' => $appointment,
            'cities' => $cities,
            'services' => $services,
            'locations' => $locations,
            'doctors' => $doctors,
            'resourceHadRotaDay' => $resourceHadRotaDay,
            'back_date_config' => $back_date_config,
            'setting' => $setting,
            'consultancy_type' => config('constants.consultancy_type_array'),
            'genders' => config('constants.gender_array')
        ]);
    }
    /**
     * Show the form for editing Appointment.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function editService($id)
    {
        if (!Gate::allows('appointments_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $locationsids = array();
        $doctorids = array();
        $machineids = array();
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
            $locations = $locations->pluck("name", "id");
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
                    ['is_treatment', '=', '1']
                ])->get();
                if (count($doctor_rota) == 0) {
                    unset($doctors[$key]);
                }
            }
        }
        $machines = Resources::where([
            ["resource_type_id", "=", config("constants.resource_room_type_id")],
            ["location_id", "=", $appointment->location_id],
            ["account_id", "=", Auth::user()->account_id]],
            ["actvie", "=", 1]
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
        if (!Gate::allows('appointments_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $locationsids = array();
        $doctorids = array();
        $machineids = array();
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
            $locations = $locations->pluck("name", "id");
        }
        $doctors = $doctors_no_final = Doctors::getActiveOnly($appointment->location_id, Auth::User()->account_id);

        if ($doctors_no_final) {
            foreach ($doctors_no_final as $key => $doctor) {
                $resource = Resources::where('external_id', '=', $key)->first();
                $doctor_rota = ResourceHasRota::where([
                    ['resource_id', '=', $resource?->id],
                    ['is_treatment', '=', '1']
                ])->get();
                if (count($doctor_rota) == 0) {
                    unset($doctors[$key]);
                }
            }
        }
        $machines = Resources::where([
            ["resource_type_id", "=", config("constants.resource_room_type_id")],
            ["location_id", "=", $appointment->location_id],
            ["account_id", "=", Auth::user()->account_id]],
            ["actvie", "=", 1]
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
    /**
     * Update Appointment in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {

        if (!Gate::allows('appointments_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $doctor_has_service = DoctorHasLocations::where(['user_id'=>$request->doctor_id])->first();
        if($doctor_has_service->service_id==13){
            $validator = $this->verifyUpdateFields($request);
            if ($validator->fails()) {
                return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
            }
            $appointment = Appointments::find($id);
            $back_date_config = Settings::whereSlug('sys-back-date-appointment')->select('data')->first();
            if (!Gate::allows('edit_after_arrived')&&  strtotime($request->scheduled_date) < strtotime(date('Y-m-d')) && $back_date_config->data == 0 ) {
                return ApiHelper::apiResponse($this->success, 'Scheduled date is older than today. Please select today or future date', false);
            }
            if (!Gate::allows('edit_after_arrived')) {
                if($appointment){
                    $check_invoice = Invoices::where('appointment_id', $appointment->id)->first();
                    if($check_invoice){
                        return ApiHelper::apiResponse($this->error, 'Invoice already generated. Appointment can not be rescheduled.', false);
                    }
                }
            }
            $rota = $this->checkRota($appointment, $request);
            if (!$rota['status']) {
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
            $city_info = Cities::find($request->city_id);
            if($request->input('phone') == '***********'){
                $request->merge(['phone' => $request->input('old_phone')]);
            }
            $request->request->remove('old_phone');
            $appointment_data = $request->all();
            $appointment_data['region_id'] = $city_info->region_id;
            $appointment_data['phone'] = GeneralFunctions::cleanNumber($appointment_data['phone']);
            if($appointment->scheduled_date != $request->scheduled_date ){
                $appointment_data['converted_by'] = Auth::user()->id;
            }
            if($appointment->scheduled_time != Carbon::parse($request->scheduled_time)->format("H:i:s")){
                $appointment_data['converted_by'] = Auth::user()->id;
            }
            if((string)$appointment->city_id !== $request->city_id || (string)$appointment->location_id !== $request->location_id || (string)$appointment->doctor_id !== $request->doctor_id || (string)$patient->gender !== $request->gender) {
                $appointment_data['updated_by'] = Auth::user()->id;
            }
            if($request->has('consultancy_type')){
                if((string)$appointment->consultancy_type !== $request->consultancy_type){
                    $appointment_data['updated_by'] = Auth::user()->id;
                }
            }
            if($request->has('machine_id')){
                if((string)$appointment->resource_id !== $request->machine_id){
                    $appointment_data['updated_by'] = Auth::user()->id;
                }
            }
            $appointment_data['updated_at'] = Filters::getCurrentTimeStamp();
            $appointment_data['scheduled_date'] = Carbon::parse($appointment_data['scheduled_date'])->format("Y-m-d");
            $appointment_data['scheduled_time'] = Carbon::parse($appointment_data['scheduled_time'])->format("H:i:s");
            $appointment_data['location_id'] = $request->location_id ?? $appointment->location_id;
            // Reset Scheduled Time to null, stop sending message
            $appointment_status = AppointmentStatuses::getADefaultStatusOnly(Auth::User()->account_id);
            if ($appointment_status) {
                $check_invoice = Invoices::where('appointment_id', $appointment->id)->first();
                if($check_invoice){
                    $appointment_data['appointment_status_id'] = $appointment->appointment_status_id;
                    $appointment_data['base_appointment_status_id'] = $appointment->base_appointment_status_id;
                }else{
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
                        } else if (count($changes) == 2) {
                            $appointment->update(['send_message' => $value_of_sending_message]);
                        }
                    } else {
                        if (count($changes) == 5) {
                            if (isset($changes['doctor_id'])) {
                                $appointment->update(['send_message' => 0]);
                            }
                        } else if (count($changes) == 2) {
                            $appointment->update(['send_message' => $value_of_sending_message]);
                        }
                    }
                }
                $scheduled_at_count = $appointment->scheduled_at_count;
                $appointment->update(['scheduled_at_count' => $scheduled_at_count + 1]);
            }
            Appointments::where(['patient_id' => $appointment->patient_id])->update(['name' => $patient->name]);
            if($appointment_data['appointment_status_id'] == 1){
                $appointment_data['lead_status_id'] = 4;
            }else if($appointment_data['appointment_status_id'] == 3){
                $appointment_data['lead_status_id'] = 1;
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
            $screen = $appointment->appointment_type_id == 1 ? 'Consultancy' : 'Treatment';
            GeneralFunctions::saveAppointmentLogs('updated', $screen, $appointment);
            $patient = Patients::updateRecord($appointment->patient_id, $patientData);
            $patient->update($patientData);
            $this->dispatch(
                new IndexSingleAppointmentJob([
                    'account_id' => Auth::User()->account_id,
                    'appointment_id' => $appointment->id
                ])
            );
            return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
        }else{
            $parent = Services::whereid($request->treatment_service_id)->first();
            if($parent && $parent->parent_id==0){
                $service = $parent->id;
            }else{
                $service = $parent->parent_id;
            }
            $doctor_has_service = DoctorHasLocations::where(['user_id'=>$request->doctor_id,'service_id'=>$service])->first();
            if($doctor_has_service)
            {
                $validator = $this->verifyUpdateFields($request);
                if ($validator->fails()) {
                    return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
                }
                $appointment = Appointments::find($id);
                $back_date_config = Settings::whereSlug('sys-back-date-appointment')->select('data')->first();
                if (!Gate::allows('edit_after_arrived') &&  strtotime($request->scheduled_date) < strtotime(date('Y-m-d')) && $back_date_config->data == 0 ) {
                    return ApiHelper::apiResponse($this->success, 'Scheduled date is older than today. Please select today or future date', false);
                }
                if (!Gate::allows('edit_after_arrived')) {
                    if($appointment){
                        $check_invoice = Invoices::where('appointment_id', $appointment->id)->first();
                        if($check_invoice){
                            return ApiHelper::apiResponse($this->error, 'Invoice already generated. Appointment can not be rescheduled.', false);
                        }
                    }
                }
                $rota = $this->checkRota($appointment, $request);
                if (!$rota['status']) {
                    return ApiHelper::apiResponse($this->success, $rota['message'], $rota['status']);
                }
                if (! $appointment) {
                    return ApiHelper::apiResponse($this->success, 'Appointment not found', false);
                }
                $value_of_sending_message = $appointment->send_message;
                $city_info = Cities::find($request->city_id);
                if($request->input('phone') == '***********'){
                    $request->merge(['phone' => $request->input('old_phone')]);
                }
                $request->request->remove('old_phone');
                $appointment_data = $request->all();
                $appointment_data['region_id'] = $city_info->region_id;
                $appointment_data['phone'] = GeneralFunctions::cleanNumber($appointment_data['phone']);
                $lead = Leads::find($appointment_data['lead_id']);
                if (! $lead) {
                    return ApiHelper::apiResponse($this->success, 'Lead not found', false);
                }
                $patient = Patients::find($appointment->patient_id);
                if (! $patient) {
                    return ApiHelper::apiResponse($this->success, 'Patient not found', false);
                }
                if((string)$appointment->city_id !== $request->city_id || (string)$appointment->location_id !== $request->location_id || (string)$appointment->doctor_id !== $request->doctor_id || (string)$patient->gender !== $request->gender) {
                    $appointment_data['updated_by'] = Auth::user()->id;
                }
                if($request->has('consultancy_type')){
                    if((string)$appointment->consultancy_type !== $request->consultancy_type){
                        $appointment_data['updated_by'] = Auth::user()->id;
                    }
                }
                if($request->has('machine_id')){
                    if((string)$appointment->resource_id !== $request->machine_id){
                        $appointment_data['updated_by'] = Auth::user()->id;
                    }
                }
                $appointment_data['updated_at'] = Filters::getCurrentTimeStamp();
                if($appointment->scheduled_date != $request->scheduled_date ){
                    $appointment_data['converted_by'] = Auth::user()->id;
                }
                if($appointment->scheduled_time != Carbon::parse($request->scheduled_time)->format("H:i:s")){
                    $appointment_data['converted_by'] = Auth::user()->id;
                }
                $appointment_data['scheduled_date'] = Carbon::parse($appointment_data['scheduled_date'])->format("Y-m-d");
                $appointment_data['scheduled_time'] = Carbon::parse($appointment_data['scheduled_time'])->format("H:i:s");
                // Reset Scheduled Time to null, stop sending message
                $appointment_status = AppointmentStatuses::getADefaultStatusOnly(Auth::User()->account_id);
                if ($appointment_status) {
                    $check_invoice = Invoices::where('appointment_id', $appointment->id)->first();
                    if($check_invoice){
                        $appointment_data['appointment_status_id'] = $appointment->appointment_status_id;
                        $appointment_data['base_appointment_status_id'] = $appointment->base_appointment_status_id;
                    }else{
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
                            } else if (count($changes) == 2) {
                                $appointment->update(['send_message' => $value_of_sending_message]);
                            }
                        } else {
                            if (count($changes) == 5) {
                                if (isset($changes['doctor_id'])) {
                                    $appointment->update(['send_message' => 0]);
                                }
                            } else if (count($changes) == 2) {
                                $appointment->update(['send_message' => $value_of_sending_message]);
                            }
                        }
                    }
                    // End: That code only belong to stop sending message
                    $scheduled_at_count = $appointment->scheduled_at_count;
                    $appointment->update(['scheduled_at_count' => $scheduled_at_count + 1]);
                }
                Appointments::where('patient_id', '=', $appointment->patient_id)->update(['name' => $appointment_data['name']]);
                /*
                 * Perform Lead Operations
                 */
                if($appointment_data['appointment_status_id'] == 1){
                    $appointment_data['lead_status_id'] = 4;
                }else if($appointment_data['appointment_status_id'] == 3){
                    $appointment_data['lead_status_id'] = 1;
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
                /* In our initial logic, We not change the name in patient when user search the patient and change the name so we change it in appointment but not in
                 * patient, so for now we also change it at patient, below code that I comment help me to update patient name.
                 */
                $screen = $appointment->appointment_type_id == 1 ? 'Consultancy' : 'Treatment';
                GeneralFunctions::saveAppointmentLogs('updated', $screen, $appointment);
                $patient = Patients::updateRecord($appointment->patient_id, $patientData);
                $patient->update($patientData);
                $this->dispatch(
                    new IndexSingleAppointmentJob([
                        'account_id' => Auth::User()->account_id,
                        'appointment_id' => $appointment->id
                    ])
                );
                return ApiHelper::apiResponse($this->success, 'Record has been updated successfully.');
            }else{
                return ApiHelper::apiResponse($this->error, 'Service is not assigned to this doctor', false);
            }
        }
    }
    /**
     * Remove Appointment from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        if (!Gate::allows('appointments_destroy')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $response = Appointments::DeleteRecord($id, Auth::User()->account_id);
        /**
         * Work need on destory
         */
        AppointmentsElastic::deleteObject($id);
        return ApiHelper::apiResponse($this->success, $response['message'], $response['status']);
    }
    /**
     * Inactive Record from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function inactive($id)
    {
        if (!Gate::allows('appointments_manage')) {
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
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function active($id)
    {
        if (!Gate::allows('appointments_manage')) {
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
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function loadLeadData(Request $request)
    {
        $data = array(
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
        );
        if (Gate::allows('appointments_manage')) {
            $phone = GeneralFunctions::cleanNumber($request->phone);
            $patient = Patients::getByPhone($phone, Auth::User()->account_id, $request->patient_id);
            if (!$patient) {
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
     *
     * @param Request $request
     */
    public function showAppointmentStatuses(Request $request)
    {
        $appointment = Appointments::find($request->id);
        if (!$appointment) {
            return ApiHelper::apiResponse($this->success, 'No record found', false);
        }
        $base_appointments = AppointmentStatuses::where(['account_id' => 1])->select("id", "parent_id", "is_comment")->get()->keyBy('id');
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
     * @param \App\Http\Requests\Admin\StoreUpdateAppointmentsRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeAppointmentStatuses(Request $request)
    {
        $data = $request->all();
        $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();
        $appointment = Appointments::find($request->id);
        if (!$appointment) {
            return ApiHelper::apiResponse($this->success, 'Appointment not found', false);
        }
        $appointment_type = AppointmentTypes::where('slug', '=', 'consultancy')->first();
        $appointment_type_2 = AppointmentTypes::where('slug', '=', 'treatment')->first();
        $counterglobal = Settings::where('slug', '=', 'sys-appointmentrescheduledcounter')->first();
        $invoiceexit = Invoices::where([
            ['invoice_status_id', '=', $invoicestatus->id],
            ['appointment_id', '=', $data['id']]
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
        if (!isset($data['appointment_status_id']) || $data['appointment_status_id'] == '') {
            $data['appointment_status_id'] = $data['base_appointment_status_id'];
        }
        // Set Comments
        if (isset($data['reason']) && !$data['reason']) {
            $data['reason'] = null;
        }
        // Converted By
        $data['converted_by'] = Auth::User()->id;
        /*$data['updated_by'] = Auth::User()->id;*/
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
        if($data['base_appointment_status_id'] == 3){
            $lead = Leads::findOrFail($appointment->lead_id);
            $lead->lead_status_id = 1;
            $lead->save();
        }if($data['base_appointment_status_id'] == 1){
            $lead = Leads::findOrFail($appointment->lead_id);
            $lead->lead_status_id = 4;
            $lead->save();
        }
        if($data['base_appointment_status_id'] == 14){
            $lead = Leads::where(['id' => $appointment->lead_id])->update(['lead_status_id'=>2]);
        }

        /**
         * Dispatch Elastic Search Index
         */
        $this->dispatch(
            new IndexSingleAppointmentJob([
                'account_id' => Auth::User()->account_id,
                'appointment_id' => $appointment->id
            ])
        );
        return ApiHelper::apiResponse($this->success, 'Status has been change successfully!',true,['appontment_type_id'=>$request->appointment_type_id]);
    }
    /**
     * Load Appointment SMS History.
     *
     * @param int $id
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
     * @param \App\Http\Requests\Admin\StoreUpdateAppointmentsRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendLogSMS(Request $request)
    {
        $data = $request->all();
        $SMSLog = SMSLogs::find($request->id);
        if (!$SMSLog) {
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
            $SMSObj = array(
                'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
                'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
                'to' => $patient_phone,
                'text' => $preparedText,
                'mask' => $UserOperatorSettings->mask, // Setting ID 3 for Mask
                'test_mode' => $UserOperatorSettings->test_mode, // Setting ID 3 Test Mode
            );
            $response = TelenorSMSAPI::SendSMS($SMSObj);
        } else {
            $SMSObj = array(
                'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
                'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
                'from' => $UserOperatorSettings->mask,
                'to' => $patient_phone,
                'text' => $preparedText,
                'test_mode' => $UserOperatorSettings->test_mode, // Setting ID 3 Test Mode
            );
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
                    $locationsids = array();
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
                        $locations = $locations->pluck("name", "id");
                    }

                } else {
                    $locations = Locations::getActiveRecordsByCity($request->city_id, ACL::getUserCentres(), Auth::User()->account_id);
                    if ($locations) {
                        $locations = $locations->pluck("name", "id");
                    }
                }
                return ApiHelper::apiResponse($this->success, 'Record found', true, [
                    'dropdown' => $locations
                ]);
            }
            $assigned_locations = ACL::getUserCentres();
            $locations = Locations::getActiveRecordsByCity('',ACL::getUserCentres(), Auth::User()->account_id);

            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'dropdown' =>$locations->pluck("name", "id")
            ]);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }
    public function LoadChildServices(Request $request)
    {
        try {
            if ($request->serviceId) {
                $child_services = Services::where(['parent_id'=>$request->serviceId,'active'=>1])->get();
                if ($child_services) {
                    $child_services = $child_services->pluck("name", "id");
                }
            }
            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'dropdown' => $child_services
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
                    $doctorids = array();
                    /*For machine type we perform that work we can remove it if any problem happen but for linkage that is best*/
                    foreach ($doctors as $key => $doctor) {
                        $doctor_serivce = AppointmentEditWidget::loaddoctorservice_edit($key, $request->location_id, Auth::User()->account_id, $reverse_process);
                        if (in_array($request->service_id, $doctor_serivce)) {
                            $doctorids[] = $key;
                        }
                    }
                    $doctors = $doctors_no_final = Doctors::whereIn('id', $doctorids)->get()->pluck('name', 'id');
                } else {
                    $doctors = $doctors_no_final = LocationsWidget::loadAppointmentDoctorByLocation($request->location_id, Auth::User()->account_id);
                }
                foreach ($doctors_no_final as $key => $doctor) {
                    $resource = Resources::where('external_id', '=', $key)->first();
                    if ($request->appointment_manage == Config::get('constants.appointment_type_service_string')) {
                        $doctor_rota = ResourceHasRota::where([
                            ['resource_id', '=', $resource->id],
                            ['is_treatment', '=', '1']
                        ])->get();
                        if (count($doctor_rota) == 0) {
                            unset($doctors[$key]);
                        }
                    }
                    if ($request->appointment_manage == Config::get('constants.appointment_type_consultancy_string')) {
                        $doctor_rota = ResourceHasRota::where([
                            ['resource_id', '=', $resource->id],
                            ['is_consultancy', '=', '1']
                        ])->get();
                        if (count($doctor_rota) == 0) {
                            unset($doctors[$key]);
                        }
                    }
                }
                return ApiHelper::apiResponse($this->success, 'Record found', true, [
                    'dropdown' => $doctors
                ]);
            }
            return ApiHelper::apiResponse($this->success, 'Record found', false, [
                'dropdown' => null
            ]);
        }  catch (\Exception $e) {
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
            return response()->json(array(
                'status' => 1,
                'dropdown' => view('admin.appointments.dropdowns.doctors', compact('doctors'))->render(),
            ));
        } else {
            return response()->json(array(
                'status' => 0,
                'dropdown' => null,
            ));
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
                return response()->json(array(
                    'status' => 0,
                    'resource_has_rota_day' => null,
                    'machine_has_rota_day' => null,
                    'selected' => null,
                ));
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
                        return response()->json(array(
                            'status' => 1,
                            'resource_has_rota_day' => $resource_has_rota_day,
                            'machine_has_rota_day' => $resource_has_rota_day,
                            'selected' => ($selected) ? Carbon::parse($selected)->format('g:ia') : null
                        ));
                    }
                } else {
                    $resource_id = $request->machine_id;
                    if (($request->machineRotaDayID != $appointment->resource_has_rota_day_id_for_machine) || !$resource_id) {
                        /*
                         * Data is changed, avoid to provide rota
                         */
                        return response()->json(array(
                            'status' => 0,
                            'resource_has_rota_day' => null,
                            'machine_has_rota_day' => null,
                            'selected' => null,
                        ));
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
                        return response()->json(array(
                            'status' => 1,
                            'resource_has_rota_day' => $resource_has_rota_day,
                            'machine_has_rota_day' => $resource_has_rota_day,
                            'selected' => ($selected) ? Carbon::parse($selected)->format('g:ia') : null
                        ));
                    }
                }
            }
        }
        return response()->json(array(
            'status' => 0,
            'resource_has_rota_day' => null,
            'machine_has_rota_day' => null,
            'selected' => null,
        ));
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
                $data = array();
                foreach ($appointments as $appointment) {
                    $data[$appointment->id] = array(
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
                    );
                }
                return response()->json(array(
                    'status' => 1,
                    'events' => $data,
                ));
            } else {
                return response()->json(array(
                    'status' => 0,
                    'events' => null,
                ));
            }
        } else {
            return response()->json(array(
                'status' => 0,
                'events' => null,
            ));
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
             if($request->doctor_id){
                 $doctor_rotas = Resources::getDoctorWithRotas($request->location_id, $request->doctor_id, $request->start, $request->end);
             }
             $location_id = $request->location_id;
             $doctor_id = $request->doctor_id;
             $machine_id = $request->machine_id;
             $minTime = Resources::getMinTimeWithDr($location_id, $doctor_id, $start, $end);
             if ($appointments) {
                 $data = array();
                 foreach ($appointments as $appointment) {
                     $dutation = explode(':', $appointment?->service?->duration ?? '');
                         $data[$appointment->id] = array(
                             'id' => $appointment->id,
                             'service' => $appointment?->service?->name ?? '',
                             'patient' => ($appointment->name) ? $appointment->name : $appointment->patient->name,
                             'created_by' => ($appointment->created_by) ? $appointment->user->name : '',
                             'phone' => GeneralFunctions::prepareNumber4Call($appointment?->patient?->phone ?? '0300'),
                             'duration' => $appointment?->service?->duration ?? '00',
                             'editable' => true,
                             'overlap' => false,
                             'start' => Carbon::parse($appointment->scheduled_date, null)->format('Y-m-d') . ' ' . Carbon::parse($appointment->scheduled_time, null)->format('H:i'),
                             'end' => Carbon::parse($appointment->scheduled_date, null)->format('Y-m-d') . ' ' . Carbon::parse($appointment->scheduled_time, null)->addHours($dutation[0] ?? 0)->addMinutes($dutation[1] ?? 0)->format('H:i'),
                             'color' => $appointment?->service?->color ?? '#fff',
                             'resourceId' => $appointment->doctor_id,
                         );
                     }
                     if($request->doctor_id){
                         return response()->json(array(
                             'status' => 1,
                             'events' => $data,
                             'min_time' => $minTime,
                             "rotas" => isset($doctor_rotas)? $doctor_rotas->toArray() : '',
                             'start_time' => \Illuminate\Support\Carbon::parse($doctor_rotas->pluck('doctor_rotas')->flatten(1)->min('start_time'))->format("H:i:s"),
                             'end_time' => \Illuminate\Support\Carbon::parse($doctor_rotas->pluck('doctor_rotas')->flatten(1)->max('end_time'))->format("H:i:s"),

                         ));
                     }else{
                         return response()->json(array(
                             'status' => 1,
                             'events' => $data,
                             'min_time' => $minTime,
                             "rotas" => isset($doctor_rotas)? $doctor_rotas->toArray() : '',
                             'start_time' => '10:00',
                             'end_time' => '23:00',
                         ));
                     }
             } else {
                 return response()->json(array(
                     'status' => 0,
                     'events' => null,
                 ));
             }
         } else {
             return response()->json(array(
                 'status' => 0,
                 'events' => null,
             ));
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
                    $data['first_scheduled_count'] = $appointment->first_scheduled_count;
                    $data['scheduled_at_count'] = $appointment->scheduled_at_count;
                    if ($appointment->appointment_type_id = Config::get('constants.appointment_type_consultancy')) {
                        $response = Resources::getDoctorRotaHasDay($request->start, $appointment->doctor_id);
                        if (isset($response['resource_id']) && $response['resource_id']) {
                            $data['resource_id'] = $response['resource_id'];
                        }
                        if (isset($response['resource_has_rota_day_id']) && $response['resource_has_rota_day_id']) {
                            $data['resource_has_rota_day_id'] = $response['resource_has_rota_day_id'];
                        }
                    }
                    $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();
                    $invoice = Invoices::where([
                        ['appointment_id', '=', $appointment->id],
                        ['invoice_status_id', '=', $invoicestatus->id]
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
                            $record->update(array(
                                'appointment_status_id' => $appointment_status->id,
                                'base_appointment_status_id' => $appointment_status->id,
                                'appointment_status_allow_message' => $appointment_status->allow_message,
                                'send_message' => 1, // Set flag 1 to send message on cron job
                            ));
                        }
                        /**
                         * Dispatch Elastic Search Index
                         */
                        $this->dispatch(
                            new IndexSingleAppointmentJob([
                                'account_id' => Auth::User()->account_id,
                                'appointment_id' => $appointment->id
                            ])
                        );
                        return ApiHelper::apiResponse($this->success, 'Appointment Updated Successfully');
                    }
                }
                return ApiHelper::apiResponse($this->success, 'Doctor is not available', false);
            }
            return ApiHelper::apiResponse($this->success, 'Invalid paramters', false);
        }
        return ApiHelper::apiResponse($this->success,  $appointment_checkes['message'], false);
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
        if (!Gate::allows('appointments_manage') && !Gate::allows('appointments_view')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $invoice_status = InvoiceStatuses::where('slug', '=', 'paid')->first();
        $invoice = Invoices::where([
            ['appointment_id', '=', $id],
            ['invoice_status_id', '=', $invoice_status->id]
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
            $appointmentArray = array();
            if ($appointment_type->name == Config::get('constants.Service')) {
                /*Check if service has */
                $packages = DB::table('packages')
                    ->leftjoin('package_services', 'packages.id', '=', 'package_services.package_id')
                    ->where([
                        ['packages.is_refund', '=', '0'],
                        ['packages.active', '=', '1'],
                        ['packages.patient_id', '=', $appointment->patient_id],
                        ['package_services.service_id', '=', $appointment->service_id],
                        ['package_services.is_consumed', '=', '0'],
                        ['packages.location_id', '=', $appointment->location_id]
                    ])->select('packages.id', 'packages.name')->groupby('packages.id')->orderBy('packages.id', 'desc')->get();
                $status = 'true';
                if (count($packages) <= 0) {
                    $location_information = Locations::find($appointment->location_id);
                    $location_id = $appointment->location_id;
                    $serviceinfo = Services::where('id', '=', $appointment->service_id)->first();
                    if ($serviceinfo->tax_treatment_type_id == Config::get('constants.tax_both') || $serviceinfo->tax_treatment_type_id == Config::get('constants.tax_is_exclusive')) {
                        $amount_create = $amount_create_is_inclusive = $serviceinfo->price;
                        $tax_create = ceil($serviceinfo->price * ($location_information->tax_percentage / 100));
                        $price = ceil($amount_create + (($amount_create * $location_information->tax_percentage) / 100));

                    } else {
                        $price = $amount_create_is_inclusive = $serviceinfo->price;
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
        return view('admin.appointments.invoice_create', compact('price', 'packages', 'appointment_type', 'status', 'id', 'service', 'balance', 'settleamount', 'outstanding', 'invoice_status', 'paymentmodes', 'tax_create', 'amount_create', 'location_id', 'checked_treatment', 'appointmentArray', 'amount_create_is_inclusive'));
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
        $appointmentinfo = Appointments::find($request->appointment_id_create);
        $bundleinfo = Bundles::join('bundle_has_services', 'bundles.id', '=', 'bundle_has_services.bundle_id')
            ->where([
                ['bundle_has_services.service_id', '=', $appointmentinfo->service_id]
            ])
            ->select('bundles.id')
            ->get();
        foreach ($bundleinfo as $bundleinfo) {
            $bundleid[] = $bundleinfo->id;
        }
        $package = Packages::find($request->package_id_create);
        if($package == null){
            return response()->json(array(
                'status' => true,
                'packagebundles' => [],
                'packageservices' => [],
            ));
        }
        $packagebundles = PackageBundles::leftjoin('discounts', 'package_bundles.discount_id', '=', 'discounts.id')
            ->join('bundles', 'package_bundles.bundle_id', '=', 'bundles.id')
            ->where('package_bundles.package_id', '=', $package->id)
            ->whereIn('package_bundles.bundle_id', $bundleid)
            ->select('package_bundles.*', 'discounts.name as discountname', 'bundles.name as bundlename')
            ->get();
        $packageservices = PackageService::join('services', 'package_services.service_id', '=', 'services.id')
            ->where([
                ['package_services.package_id', '=', $package->id],
                ['package_services.service_id', '=', $appointmentinfo->service_id]
            ])
            ->select('package_services.*', 'services.name as servicename')
            ->get();
        return response()->json(array(
            'status' => true,
            'packagebundles' => $packagebundles,
            'packageservices' => $packageservices,
        ));
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
        $appointmentinfo = Appointments::where('id', '=', $request->appointment_id_create)->first();
        $balance_patient_in = PackageAdvances::where([
            ['patient_id', '=', $appointmentinfo->patient_id],
            ['package_id', '=', $request->package_id_create],
            ['cash_flow', '=', 'in']
        ])->sum('cash_amount');
        $balance_patient_out = PackageAdvances::where([
            ['patient_id', '=', $appointmentinfo->patient_id],
            ['package_id', '=', $request->package_id_create],
            ['cash_flow', '=', 'out']
        ])->sum('cash_amount');
        $balance = $balance_patient_in - $balance_patient_out;
        $balance = ceil($balance);
        $package_service = PackageService::find($request->package_service_id);
        $package = Packages::find($request->package_id_create);
        $package_bundle = PackageBundles::find($package_service->package_bundle_id);
        $bundle = Bundles::where("id",'=',$package_bundle->bundle_id)->where("type", '=','multiple')->first();
        $service = Services::find($package_service->service_id);
        if($bundle){
            if($balance_patient_in >= $bundle->price){
                $package_access= 1;
            }else if($balance >= $service->price){
                $package_access= 1;
            }else{
                $package_access= 0;
            }
        }else{
            $package_access= 1;
        }
        $cash = 0;
        if($package_access == 1){
            $price = $package_service->tax_including_price;
            $outstanding = intval($package_service->tax_including_price) - $cash - intval($balance);
            $remaining = 0;
            $settleamount_1 = $price - $cash;
            $settleamount = min($settleamount_1, $balance);
        }else{
            if($service->price > ($package_bundle->net_amount - $balance_patient_in)) {
                $price = $package_service->price;
                $outstanding = intval($package_bundle->net_amount - $balance_patient_in) - $cash;
                $settleamount_1 = intval($package_bundle->net_amount - $balance_patient_in) - $cash;
                $settleamount = min($settleamount_1, $balance);
            } else {
                $price = $service->price;
                $outstanding = intval($price) - $cash - intval($balance);
                $settleamount_1 = $price - $cash;
                $settleamount = min($settleamount_1, $balance);
            }
            $remaining = $package_service->tax_including_price;
        }
        if ($outstanding < 0) {
            $outstanding = 0;
        }
        return response()->json(array(
            'status' => true,
            'amount' => $package_service->tax_exclusive_price,
            'tax_price' => $package_service->tax_price,
            'serviceprice' => $price,
            'outstanding' => $outstanding,
            'settleamount' => round($settleamount,2),
            'balance' => round($balance,2),
            'remaining' => $remaining,
            'package_service_id' => $request->package_id_create
        ));
    }
    /*
     * Get the package price against package id
     *
     * */
    public function getinvoicecalculation(Request $request)
    {
        if ($request->cash_create == 0 || $request->cash_create < 0) {
            return response()->json(array(
                'status' => true,
                'outstdanding' => $request->outstanding_for_zero,
                'settleamount' => $request->settleamount_for_zero,
            ));
        }
        $outstdanding = $request->outstanding_for_zero - $request->cash_create ;
        $balance = $request->balance_create;
        $settleamount = $request->price_create - $request->cash_create;
        return response()->json(array(
            'status' => true,
            'outstdanding' => round($outstdanding,2),
            'settleamount' => round($settleamount, 2),

        ));
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
        } else if ($request->tax_treatment_type_id == Config::get('constants.tax_is_exclusive')) {
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
        return response()->json(array(
            'status' => true,
            'amount_create' => $amount_create,
            'tax_create' => $tax_create,
            'price' => $price,
            'outstdanding' => $outstdanding,
            'settleamount' => $settleamount,
        ));
    }
    /*
     * get the value for invoice calucation
     * */

    public function saveinvoice(Request $request)
    {
        $paymentmode_settle = PaymentModes::where('payment_type', '=', Config::get('constants.payment_type_settle'))->first();
        $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();
        $appointmentinfo = Appointments::find($request->appointment_id);
        if(isset($request->appointment_id_consultancy)){
            // Now we need to work our tag appointment for upselling
            $tag_appoint = explode('.', $request->appointment_id_consultancy);
            if ($tag_appoint[1] == 'A') {
                $appointment_id_consultancy = $tag_appoint[0];
            } else {
                $PlanAppointmentCalculation = new PlanAppointmentCalculation();
                $appointment_id_consultancy = $PlanAppointmentCalculation->storeAppointment($appointmentinfo->patient_id, $appointmentinfo->location_id, $appointmentinfo->service_id, $tag_appoint[0],true);
                $PlanAppointmentCalculation->saveinvoice($appointment_id_consultancy);
            }
            $appointmentinfo->update(['appointment_id' => $appointment_id_consultancy,'updated_at'=>Filters::getCurrentTimeStamp()]);
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
                ['id', '=', $request->exclusive_or_bundle]
            ])->first();
            $is_exclusive = $package_service_info->is_exclusive;
        } else {
            if ($appointmentinfo->appointment_type->name == Config::get('constants.Service')) {
                if ($request->tax_treatment_type_id == Config::get('constants.tax_both')) {
                    $is_exclusive = $request->exclusive_or_bundle;
                } else if ($request->tax_treatment_type_id == Config::get('constants.tax_is_exclusive')) {
                    $is_exclusive = 1;
                } else {
                    $is_exclusive = 0;
                }
            } else {
                $is_exclusive = 1;
            }
        }
        if($request->remaining != 0){
            $data['total_price'] = $request->remaining;
        }else{
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
        $data['created_at'] =Filters::getCurrentTimeStamp();
        $data['updated_at'] = Filters::getCurrentTimeStamp();
        $invoice = Invoices::CreateRecord($data);
        $data_detail['tax_exclusive_serviceprice'] = $request->amount_create;
        $data_detail['tax_percenatage'] = $appointmentinfo->location->tax_percentage;
        $data_detail['tax_price'] = $request->tax_create;
        if($request->remaining != 0){
            $data_detail['tax_including_price'] = $request->remaining;
            $data_detail['net_amount'] = $request->remaining;
        }else{
            $data_detail['tax_including_price'] = $request->price;
            $data_detail['net_amount'] = $request->price;
        }
        $data_detail['is_exclusive'] = $is_exclusive;
        $data_detail['qty'] = '1';
        if($request->remaining != 0){
            $data_detail['service_price'] = $request->remaining;
        }else{
            $data_detail['service_price'] = $appointmentinfo->service->price;
        }
        $data_detail['service_id'] = $appointmentinfo->service_id;
        $data_detail['invoice_id'] = $invoice->id;
        $data_detail['created_at'] = Filters::getCurrentTimeStamp();
        $data_detail['updated_at'] = Filters::getCurrentTimeStamp();
        if ($request->package_service_id) {
            $tax_info_package_service = PackageService::find($request->package_service_id);
            $data_detail['tax_percenatage'] = $tax_info_package_service->tax_percenatage;
            $data_detail['package_service_id'] = $request->package_service_id;
        }
        if ($request->package_id != null) {
            $packages = DB::table('packages')
                ->join('package_bundles', 'packages.id', '=', 'package_bundles.package_id')
                ->join('package_services', 'package_bundles.id', '=', 'package_services.package_bundle_id')
                ->where([
                    ['packages.id', '=', $request->package_id],
                    ['package_services.service_id', '=', $appointmentinfo->service_id],
                    ['package_services.is_consumed', '= 0']
                ])->select('package_bundles.discount_type', 'package_bundles.discount_price', 'package_bundles.discount_id')->first();
            if ($packages->discount_type != null) {
                $discount_info = Discounts::find($packages->discount_id);
                $data_detail['discount_type'] = $packages->discount_type;
                $data_detail['discount_price'] = $packages->discount_price;
                $data_detail['discount_id'] = $packages->discount_id;
                $data_detail['discount_name'] = $discount_info->name;
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
                'is_allocate' => '1'
            ])->pluck('id');
            $GetAppointment = Appointments::join('invoices','appointments.id','invoices.appointment_id')
            ->select('appointments.id','appointments.service_id','invoices.created_at')
            ->where(['appointments.patient_id' => $appointmentinfo->patient_id ,'appointments.appointment_type_id' => 1 ])
           ->latest('invoices.created_at')->first();
            $GetInvoiceInfo = Invoices::where(['appointment_id' => $GetAppointment->id])->first();
            $packageservicez = PackageService::with('service')
            ->whereIn('package_bundle_id',$packagebundle)
            ->where('created_at','>',Carbon::parse($GetInvoiceInfo->created_at))
            ->get();
            if(count($packageservicez)> 0){
                $data_package['appointment_id'] = $GetAppointment->id;
            }else{
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
        $data_package['updated_at'] =Filters::getCurrentTimeStamp();
        $package_advances = PackageAdvances::createRecord_forinvoice($data_package);
        if ($request->package_id && $request->cash > 0) {
            Invoice_Plan_Refund_Sms_Functions::PlanCashReceived_SMS($request->package_id, $package_advances);
        }
        if($request->remaining != 0){
            $out_transcation = $request->remaining;
        }else{
            $out_transcation = $request->cash + $request->settle;
        }
        $out_transcation_price = $out_transcation - $invoice_detail->tax_price;
        $out_transcation_tax = $invoice_detail->tax_price;
        $tran = array(
            '1' => $out_transcation_price,
            '2' => $out_transcation_tax
        );
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
            PackageService::where('id', '=', $request->package_service_id)->update(['is_consumed' => 1, 'updated_at' => Filters::getCurrentTimeStamp()]);
            $packagesservice = PackageService::find($request->package_service_id);
            $package_service_log = PackageService::updateRecordInvoice($packagesservice);
            if($request->cash > 0){
                $patient = User::whereId($appointmentinfo->patient_id)->first();
                $location = Locations::whereId($appointmentinfo->location_id)->first();
                $servicename = Services::whereId($appointmentinfo->service_id)->first();
                $activity = new Activity();
                $activity->timestamps = false;
                $activity->action = 'received';
                $activity->patient = $patient->name;
                $activity->appointment_type = 'Plan';
                $activity->created_by = Auth::user()->name;
                $activity->invoice_id = $invoice->id;
                $activity->invoice_id = $invoice->id;
                $activity->planId = $package_advances->package_id;
                $activity->amount =$request->cash;
                $activity->location = $location->name;
                $activity->created_at =Filters::getCurrentTimeStamp();
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
                    Appointments::where('id', '=', $request->appointment_id)->update(['base_appointment_status_id' => $arrivedStatus->id, 'appointment_status_id' => $appointmentStatus->id,'updated_at'=>Filters::getCurrentTimeStamp()]);
                } else {
                    Appointments::where('id', '=', $request->appointment_id)->update(['base_appointment_status_id' => $arrivedStatus->id, 'appointment_status_id' => $arrivedStatus->id,'updated_at'=>Filters::getCurrentTimeStamp()]);
                }
            } else {
                Appointments::where('id', '=', $request->appointment_id)->update(['base_appointment_status_id' => $arrivedStatus->id, 'appointment_status_id' => $arrivedStatus->id,'updated_at'=>Filters::getCurrentTimeStamp()]);
            }
        }
        // In case of auto change status we need to update by so that s why we did
        $appointment_data_status['updated_by'] = Auth::User()->id;
        $appointmentinfo->update($appointment_data_status);

        ///Save activity//
        $patient = User::whereId($appointmentinfo->patient_id)->first();
        $location = Locations::whereId($appointmentinfo->location_id)->first();
        $servicename = Services::whereId($appointmentinfo->service_id)->first();
        $activity = new Activity();
        $activity->action = 'consumed';
        $activity->patient = $patient->name;
        $activity->appointment_type = $servicename->name . ' Treatment';
        $activity->created_by = Auth::user()->name;
        $activity->invoice_id = $invoice->id;
        $activity->amount = $invoice_detail->net_amount;
        $activity->location = $location->name;
        $activity->created_at = Filters::getCurrentTimeStamp();
        $activity->updated_at = Filters::getCurrentTimeStamp();
        $activity->save();
        /**
         * Dispatch Elastic Search Index
         */
        $this->dispatch(
            new IndexSingleAppointmentJob([
                'account_id' => Auth::User()->account_id,
                'appointment_id' => $appointmentinfo->id
            ])
        );
        return ApiHelper::apiResponse($this->success, 'Invoice created successfully', true, [
                'invoice_id' => $invoice?->id ?? 0
            ]);
	}
    /**
     * Show the form for creating new Appointment.
     *
     * @return \Illuminate\Http\Response
     */
    public function createService(Request $request)
    {
        if (!Gate::allows('appointments_services')) {
            return abort(401);
        }
        $user = Auth::User();
        /*
         * Set dropdown for all system users
         */
        if ($user->user_type_id == config("constants.application_user_id") || $user->user_type_id == config("constants.administrator_id")) {
            $userHasLocation = UserHasLocations::join('locations', 'user_has_locations.location_id', '=', 'locations.id')->where('user_has_locations.user_id', '=', $user->id)->orderby('name', 'asc')->first();
            if ($userHasLocation) {
                $locations = Locations::where('id', '=', $userHasLocation->location_id)->first();
                $resource = Resources::where('location_id', '=', $userHasLocation->location_id)->first();

                $city_id = $locations->city_id;
                $location_id = $locations->id;
                $doctors = DoctorHasLocations::where('location_id', '=', $location_id)->first();
                $urlquery = "?city_id=" . $city_id . "&location_id=" . $location_id;
                if ($doctors) {
                    $urlquery = "?city_id=" . $city_id . "&location_id=" . $location_id . "&doctor_id=" . $doctors->user_id;
                }
                if ($resource) {
                    $urlquery .= '&machine_id=' . $resource->id;
                }
                if ($request->city_id && $request->location_id) {
                } else {
                    return redirect(route('admin.appointments.manage_services') . $urlquery);
                }
            }
        }
        /*
         * Set dropdown for all asthetic operators/ consultants
         */
        if ($user->user_type_id == config("constants.practitioner_id")) {
            $userHasLocation = DoctorHasLocations::join('locations', 'doctor_has_locations.location_id', '=', 'locations.id')->where('doctor_has_locations.user_id', '=', $user->id)->orderby('name', 'asc')->first();
            if ($userHasLocation) {
                $locations = Locations::where('id', '=', $userHasLocation->location_id)->first();
                $resource = Resources::where('location_id', '=', $userHasLocation->location_id)->first();
                $city_id = $locations->city_id;
                $location_id = $locations->id;
                $urlquery = "?city_id=" . $city_id . "&location_id=" . $location_id . "&doctor_id=" . $user->id;
                if ($resource) {
                    $urlquery .= '&machine_id=' . $resource->id;
                }
                if ($request->city_id && $request->location_id) {
                } else {
                    return redirect(route('admin.appointments.manage_services') . $urlquery);
                }
            }
        }
        if ($request->lead_id) {
            $lead = Leads::where(['id' => $request->lead_id])->first();
            if ($lead) {
                $lead = array(
                    'id' => $lead->id,
                    'patient_id' => $lead->patient_id,
                    'name' => ($lead->patient_id) ? $lead->patient->name : null,
                    'phone' => ($lead->patient_id) ? $lead->patient->phone : null,
                    'dob' => ($lead->patient_id) ? $lead->patient->dob : null,
                    'address' => ($lead->patient_id) ? $lead->patient->address : null,
                    'cnic' => ($lead->patient_id) ? $lead->patient->cnic : null,
                    'referred_by' => ($lead->patient_id) ? $lead->patient->referred_by : null,
                    'service_id' => $lead->service_id,
                );
            } else {
                $lead = array(
                    'id' => '',
                    'patient_id' => '',
                    'name' => '',
                    'phone' => '',
                    'dob' => '',
                    'address' => '',
                    'cnic' => '',
                    'referred_by' => '',
                    'service_id' => '',
                );
            }
        } else {
            $lead = array(
                'id' => '',
                'patient_id' => '',
                'name' => '',
                'phone' => '',
                'dob' => '',
                'address' => '',
                'cnic' => '',
                'referred_by' => '',
                'service_id' => '',
            );
        }
        $employees = User::getAllActiveRecords(Auth::User()->account_id);
        if ($employees) {
            $employees = $employees->pluck('full_name', 'id');
        } else {
            $employees = array();
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
            return response()->json(array("status" => 1, "data" => $resources), 200);
        } else {
            return response()->json(array("status" => 0, "data" => null), 200);
        }
    }
    public function getRoomResources(Request $request)
    {
        return response()->json(array("status" => 1, "data" => Resources::getRoomsWithRotas()->toArray()), 200);
    }
    /**
     * Store a newly created Appointment in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function storeService(Request $request)
    {
        $messages = array();
        if (!Gate::allows('appointments_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $validator = $this->verifyServiceFields($request, $request->patient_id);
        if ($validator->fails()) {
            return ApiHelper::apiResponse($this->success, $validator->messages()->first(), false);
        }
        // Store form data in a variable
        $appointment_data = $request->all();
        $phone = $appointment_data['phone'];
        if ($appointment_data['phone'] == '***********') {
            $phone = $appointment_data['old_phone'];
        }
        $appointment_data['phone'] = GeneralFunctions::cleanNumber($phone);
        $appointment_data['account_id'] = Auth::User()->account_id;
        $appointment_data['created_by'] = Auth::user()->id;
        $appointment_data['consultancy_type'] = 'treatment';
        if (GeneralFunctions::AppointmentType($request->appointment_type) == config('constants.appointment_type_service')) {
            $response = Resources::getResourceRotaHasDay($request->start, $request->resource_id);
            if (isset($response['resource_has_rota_day_id']) && $response['resource_has_rota_day_id']) {
                $appointment_data['resource_has_rota_day_id_for_machine'] = $response['resource_has_rota_day_id'];
            }
            $resource_doctor = Resources::where('external_id', '=', $request->doctor_id)->first();
            $response = Resources::getResourceRotaHasDay($request->start, $resource_doctor->id);
            if (isset($response['resource_has_rota_day_id']) && $response['resource_has_rota_day_id']) {
                $appointment_data['resource_has_rota_day_id'] = $response['resource_has_rota_day_id'];
            }
        } else {
            return ApiHelper::apiResponse($this->success, "Appointment types is not set", false);
        }
        // Set Appointment Status
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
        // Set Appointment Type
        $appointment_data['appointment_type_id'] = config('constants.appointment_type_service');
        $location = Locations::findOrFail($appointment_data['location_id']);
        $appointment_data['city_id'] = $location->city_id;
        $appointment_data['region_id'] = $location->region_id;
        $appointment_data['account_id'] = Auth::User()->account_id;
        if ($request->start) {
            $start = $request->start;
            $service_duration = Services::find($request->service_id)->value("duration");
            $duraton_array = explode(":", $service_duration);
            if (count($duraton_array) == 2) {
                $end = Carbon::parse($start)->addHour($service_duration[0])->addMinute($duraton_array[1]);
                $start = Carbon::parse($start)->format("Y-m-d H:i:s");
            }
            $doctor_checking = Resources::checkingDoctorAvailbility($request->doctor_id, $start, $end);
            $room_check_availability = Resources::checkingRoomAvailbility($request->resource_id, $start, $end);
            if ($doctor_checking && $room_check_availability) {
                $appointment_data['scheduled_date'] = Carbon::parse($request->start)->format("Y-m-d");
                $appointment_data['scheduled_time'] = Carbon::parse($request->start)->format("H:i:s ");
                $appointment_data['first_scheduled_date'] = Carbon::parse($request->start)->format("Y-m-d");
                $appointment_data['first_scheduled_time'] = Carbon::parse($request->start)->format("H:i:s");
                $appointment_data['first_scheduled_count'] = 1;
                if ($request->appointment_type == 'treatment') {
                    $appointment_data['resource_id'] = $request->resource_id;
                }
            } else {
                return ApiHelper::apiResponse($this->success, "Doctor or machine is not available and Appointment is not scheduled.", false);
            }
        }
        $lead = Leads::where(['phone' => $request->phone])->orderBy('id', 'desc')->first();
        $patientData = $appointment_data;
        Patients::updateRecord($appointment_data['patient_id'], false, $appointment_data, $patientData);
        $appointment_data['lead_id'] = $lead->id;
        $appointment_data['created_at'] = Filters::getCurrentTimeStamp();
        $appointment_data['updated_at'] = Filters::getCurrentTimeStamp();

        $appointment = Appointments::create($appointment_data);
        $find_cons = Appointments::latest()->first();
        if($find_cons){
            $lead_service = LeadsServices::where(['lead_id' => $lead->id, 'service_id' => $request->base_service_id])->first();
            if($lead_service){
                $lead_service->update([
                    'child_service_id' => $request->service_id,
                    'treatment_id' => $find_cons->id,
                ]);
            } else {
                $lead_service_latest = LeadsServices::where(['lead_id' => $lead->id])->orderBy('id', 'desc')->first();
                $lead_service_latest->update([
                    'service_id' => $request->base_service_id,
                    'child_service_id' => $request->service_id,
                    'treatment_id' => $find_cons->id,
                ]);
            }
            LeadsServices::where(['lead_id' => $lead->id])->update(['status' => 0]);
            $lead_service = LeadsServices::updateOrCreate([
                'lead_id' => $lead->id,
                'service_id' => $request->base_service_id,
                'child_service_id' => $request->service_id
            ], [
                'status' => 1,
            ]);
        }

        Appointments::where(['patient_id' => $appointment_data['patient_id']])->update(['name' => $appointment_data['name'], 'updated_at' => $appointment_data['updated_at']]);
        if ($appointment->appointment_status_allow_message && $appointment->scheduled_date) {
            $appointment->update(array(
                'send_message' => 1
            ));
        }
        /*
         * Set Appointment Status if appointment scheduled date & time are not defined
         * case 1: If Scheduled Date is not set then status is 'un-scheduled'
         * case 2: If 'un-scheduled' is not set then set defautl status i.e. 'pending'
         */
        if (!$appointment->scheduled_date && !$appointment->scheduled_time) {
            $appointment_status = AppointmentStatuses::getUnScheduledStatusOnly(Auth::User()->account_id);
            if ($appointment_status) {
                $appointment->update(array(
                    'appointment_status_id' => $appointment_status->id,
                    'base_appointment_status_id' => $appointment_status->id,
                    'appointment_status_allow_message' => 0
                ));
            } else {
                $appointment_status = AppointmentStatuses::getADefaultStatusOnly(Auth::User()->account_id);
                if ($appointment_status) {
                    $appointment->update(array(
                        'appointment_status_id' => $appointment_status->id,
                        'base_appointment_status_id' => $appointment_status->id,
                        'appointment_status_allow_message' => 0
                    ));
                } else {
                    $appointment->update(array(
                        'appointment_status_id' => null,
                        'base_appointment_status_id' => null,
                        'appointment_status_allow_message' => 0
                    ));
                }
            }
        }
        $message = 'Record has been created successfully.';
        $this->sendPromotionSMS($appointment->id, $appointment_data['phone']);
        GeneralFunctions::saveAppointmentLogs('booked', 'Treatment', $appointment);
        $this->dispatch(
            new IndexSingleAppointmentJob([
                'account_id' => Auth::User()->account_id,
                'appointment_id' => $appointment->id
            ])
        );
        return ApiHelper::apiResponse($this->success, $message, true, [
            "log" => $messages,
            'id' => $appointment->id,
        ]);
    }
    /**
     * Validate form fields
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function verifyServiceFields(Request $request, $id = null)
    {
        $data = $request->all();
        $phone = $data['phone'];
        if ($data['phone'] == '***********') {
            $phone = $data['old_phone'];
        }
        $data['phone'] = GeneralFunctions::cleanNumber($phone);
        return Validator::make($data, [
            'name' => 'required',
            'phone' => 'required',
            'location_id' => 'required',
            'doctor_id' => 'required',
        ]);
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
                $data = array();
                foreach ($appointments as $appointment) {
                    $data[$appointment->id] = array(
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
                    );
                }
                return response()->json(array(
                    'status' => 1,
                    'events' => $data,
                ));
            } else {
                return response()->json(array(
                    'status' => 0,
                    'events' => null,
                ));
            }
        } else {
            return response()->json(array(
                'status' => 0,
                'events' => null,
            ));
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
        $appointments = Appointments::getScheduledAppointments($request, Config::get('constants.appointment_type_service'), Auth::User()->account_id, true);
        $resources = Resources::getRoomsResourceRotaWithoutDays($request->location_id);
        $start = $request->start;
        $end = $request->end;
        $minTime = Resources::getMinTimeWithDrAndMachine($location_id, $doctor_id, $machine_id, $start, $end);
        if ($request->has("start") && $request->has("end")) {
            $doctor_rotas = Resources::getDoctorWithRotasWithSpecificDate($request->location_id, $request->doctor_id, $request->start, $request->end);
        } else {
            $doctor_rotas = collect();
        }
        if ($appointments) {
            $data = array();
            if($request->doctor_id != ''){
                foreach ($appointments as $appointment) {
                    $dutation = explode(':', $appointment->service->duration);
                    $data[$appointment->id] = array(
                        'id' => $appointment->id,
                        'service' => $appointment->service->name,
                        'patient' => ($appointment->name) ? $appointment->name : $appointment->patient->name,
                        'created_by' => ($appointment->created_by) ? $appointment->user->name : '',
                        'phone' => GeneralFunctions::prepareNumber4Call($appointment->patient->phone),
                        'duration' => $appointment->service->duration,
                        'editable' => ($request->doctor_id == $appointment->doctor_id) ? true : false,
                        'overlap' => false,
                        'start' => Carbon::parse($appointment->scheduled_date, null)->format('Y-m-d') . ' ' . Carbon::parse($appointment->scheduled_time, null)->format('H:i'),
                        'end' => Carbon::parse($appointment->scheduled_date, null)->format('Y-m-d') . ' ' . Carbon::parse($appointment->scheduled_time, null)->addHours($dutation[0])->addMinutes($dutation[1])->format('H:i'),
                        'color' => ($request->doctor_id == $appointment->doctor_id) ? $appointment->service->color : $appointment->service->color.'-',
                        'resourceId' => $appointment->resource_id,
                    );
                }
            }else{
                foreach ($appointments as $appointment) {
                    $dutation = explode(':', $appointment->service->duration);
                    $data[$appointment->id] = array(
                        'id' => $appointment->id,
                        'service' => $appointment->service->name,
                        'patient' => ($appointment->name) ? $appointment->name : $appointment->patient->name,
                        'created_by' => ($appointment->created_by) ? $appointment->user->name : '',
                        'phone' => GeneralFunctions::prepareNumber4Call($appointment->patient->phone),
                        'duration' => $appointment->service->duration,
                        'editable' => ($request->doctor_id == $appointment->doctor_id) ? true : false,
                        'overlap' => false,
                        'start' => Carbon::parse($appointment->scheduled_date, null)->format('Y-m-d') . ' ' . Carbon::parse($appointment->scheduled_time, null)->format('H:i'),
                        'end' => Carbon::parse($appointment->scheduled_date, null)->format('Y-m-d') . ' ' . Carbon::parse($appointment->scheduled_time, null)->addHours($dutation[0])->addMinutes($dutation[1])->format('H:i'),
                        'color' => $appointment->service->color,
                        'resourceId' => $appointment->resource_id,
                    );
                }
            }

            $resource_ids = array();
            $resources = array_filter($resources);
            foreach ($resources as $resource) {
                $resource_ids[] = $resource["id"];
            }
            if($request->doctor_id){
                return response()->json(array(
                    'status' => 1,
                    'events' => $data,
                    'rotas' => $doctor_rotas->toArray(),
                    'min_time' => $minTime,
                    'resource_ids' => $resource_ids,
                    'start_time' => \Illuminate\Support\Carbon::parse($doctor_rotas->pluck('doctor_rotas')->flatten(1)->min('start_time'))->format("H:i:s"),
                    'end_time' => \Illuminate\Support\Carbon::parse($doctor_rotas->pluck('doctor_rotas')->flatten(1)->max('end_time'))->format("H:i:s"),
                ));
            }else{
                return response()->json(array(
                    'status' => 1,
                    'events' => $data,
                    'rotas' => $doctor_rotas->toArray() ?? '',
                    'min_time' => $minTime,
                    'resource_ids' => $resource_ids,
                    'start_time' => '10:00',
                    'end_time' => '22:00',
                ));
            }

        } else {
            return response()->json(array(
                'status' => 0,
                'events' => null,
            ));
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
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function serviceSchedule(Request $request)
    {
        $appointment_checkes = AppointmentCheckesWidget::AppointmentAppointmentCheckesfromcard($request);
        if ($appointment_checkes['status']) {
            $doctor_check_availability = Resources::checkDoctorAvailbility($request);
            $room_check_availability = Resources::checkRoomAvailbility($request);
            if (
                $request->id &&
                $request->start &&
                $request->end &&
                $request->resourceId
            ) {
                if ($doctor_check_availability) {
                    if ($room_check_availability) {
                        // Appointment Data
                        $data = $request->all();
                        $data['resource_id'] = $data['resourceId'];
                        $appointment = Appointments::findOrFail($request->id);
                        $data['first_scheduled_count'] = $appointment->first_scheduled_count;
                        $data['scheduled_at_count'] = $appointment->scheduled_at_count;
                        if ($appointment->appointment_type_id = Config::get('constants.appointment_type_service')) {
                            $response = Resources::getResourceRotaHasDay($data['start'], $data['resourceId']);
                            if (isset($response['resource_has_rota_day_id']) && $response['resource_has_rota_day_id']) {
                                $data['resource_has_rota_day_id_for_machine'] = $response['resource_has_rota_day_id'];
                            }
                            $resource_dcotor = Resources::where('external_id', '=', $data['doctor_id'])->first();
                            $response = Resources::getResourceRotaHasDay($data['start'], $resource_dcotor->id);
                            if (isset($response['resource_has_rota_day_id']) && $response['resource_has_rota_day_id']) {
                                $data['resource_has_rota_day_id'] = $response['resource_has_rota_day_id'];
                            }
                        }
                        $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();
                        $invoice = Invoices::where([
                            ['appointment_id', '=', $appointment->id],
                            ['invoice_status_id', '=', $invoicestatus->id]
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
                                $record->update(array(
                                    'appointment_status_id' => $appointment_status->id,
                                    'base_appointment_status_id' => $appointment_status->id,
                                    'appointment_status_allow_message' => $appointment_status->allow_message,
                                    'send_message' => 1, // Set flag 1 to send message on cron job
                                ));
                            }
                            $this->dispatch(
                                new IndexSingleAppointmentJob([
                                    'account_id' => Auth::User()->account_id,
                                    'appointment_id' => $appointment->id
                                ])
                            );
                            return ApiHelper::apiResponse($this->success, 'Appointment Updated Successfully Updated Successfully.');
                        }
                    }
                    return ApiHelper::apiResponse($this->success, 'Doctor is Available But Machine is not available.', false);
                } else {
                    if ($room_check_availability) {
                        return ApiHelper::apiResponse($this->success, 'Machine is Available. But Doctor is not.', false);
                    }
                    return ApiHelper::apiResponse($this->success, 'Neither Doctor nor Machine available.', false);
                }
            }
            return ApiHelper::apiResponse($this->success, 'Requested parameter not provided.', false);
        }
        return ApiHelper::apiResponse($this->success, $appointment_checkes['message'], false);
    }
    /**
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function loadEndServiceByBaseService(Request $request)
    {

        if ($request->service_id) {
            $services = Appointments::getNodeServices($request->service_id, Auth::User()->account_id, true, true);
            return ApiHelper::apiResponse($this->success, 'Record found', true, [
                'services' => $services
            ]);
        }
        return ApiHelper::apiResponse($this->success, 'Record not found', false);
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
        if (!$SMSTemplate) {
            // SMS Promotion is disabled
            return array(
                'status' => true,
                'sms_data' => 'SMS is disabled',
                'error_msg' => '',
            );
        }
        $preparedText = Appointments::prepareSMSContent($appointmentId, $SMSTemplate->content);
        $UserOperatorSettings = UserOperatorSettings::getRecord(Auth::User()->account_id);
        $SMSObj = array(
            'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
            'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
            'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($patient_phone)),
            'text' => $preparedText,
            'mask' => $UserOperatorSettings->mask, // Setting ID 3 for Mask
            'test_mode' => $UserOperatorSettings->test_mode, // Setting ID 3 Test Mode
        );
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
            $machines = Resources::where([["resource_type_id", "=", config("constants.resource_room_type_id")], ["active", "=", '1'], ["location_id", "=", $location_id], ["account_id", "=", Auth::User()->account_id]])->get();
            if ($request->appointment_manage == Config::get('constants.appointment_type_service_string')) {
                $reverse_process = true;
            } else {
                $reverse_process = false;
            }
            $machineids = array();
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
            $machines = Resources::where([["resource_type_id", "=", config("constants.resource_room_type_id")], ["active", "=", '1'], ["location_id", "=", $location_id], ["account_id", "=", Auth::User()->account_id]])->get()->pluck("name", "id");
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
     * @param \App\Http\Requests\Admin\StoreUpdateAppointmentCommentsRequest $request
     * @return \Illuminate\Http\Response
     */
    public function comment_store(StoreUpdateAppointmentCommentsRequest $request)
    {
        if (!Gate::allows('appointments_manage')) {
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
     * @param \App\Http\Requests\Admin\StoreUpdateAppointmentCommentsRequest $request
     * @return \Illuminate\Http\Response
     */
    public function AppointmentStoreComment(Request $req)
    {
        $appointmentComment = AppointmentComments::where('appointment_id', '=', $req->appointment_id)->get();
        $appointment = new AppointmentComments();
        $appointment->comment = $req->comment;
        $appointment->appointment_id = $req->appointment_id;
        $appointment->created_by = Auth::user()->id;
        $appointmentCommentDate = \Carbon\Carbon::parse($appointment->created_at)->format('D M, j Y h:i A');
        $appointment->save();
        $username = Auth::user()->name;
        $myarray = ['username' => $username, 'appointment' => $appointment, 'appointmentCommentDate' => $appointmentCommentDate, 'appointmentCommentSection' => $appointmentComment];
        return response()->json($myarray);
    }
    public function displayInvoiceAppointment($id)
    {
        if (!Gate::allows('appointments_invoice_display')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $Invoiceinfo = DB::table('invoices')
            ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
            ->join('appointments', 'appointments.id', '=', 'invoices.appointment_id')
            ->where('invoices.id', '=', $id)
            ->select('invoices.*',
                'invoice_details.discount_type',
                'invoice_details.discount_price',
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
        $package_service = PackageService::where('package_id','=',$Invoiceinfo->package_id)->where('service_id','=',$Invoiceinfo->service_id)->first();
        if($package_service){
            if($package_service->package_bundle_id != null){
                $package_bundle = PackageBundles::find($package_service->package_bundle_id);
            }else{
                $package_bundle = PackageBundles::where('package_id','=',$Invoiceinfo->package_id)->first();
            }
        }else{
            $package_bundle = PackageBundles::where('package_id','=',$Invoiceinfo->package_id)->first();
        }
        $bundle = Bundles::find($package_bundle->bundle_id);
        $invoicestatus = InvoiceStatuses::find($Invoiceinfo->invoice_status_id);
        if ($Invoiceinfo->discount_id) {
            $discount = Discounts::find($Invoiceinfo->discount_id);
        } else {
            $discount = null;
        }
        $service = Services::find($Invoiceinfo->service_id);
        $patient = User::find($Invoiceinfo->patient_id);
        $account = Accounts::find($Invoiceinfo->account_id);
        $company_phone_number = Settings::where('slug', '=', 'sys-headoffice')->first();
        return view('admin.appointments..invoice.displayInvoice', compact('Invoiceinfo', 'patient', 'account', 'service', 'discount', 'invoicestatus', 'company_phone_number', 'location_info','bundle'));
    }
    public function appointmentexcel(Request $request)
    {
        $today = Carbon::now()->toDateString();
        $this_month = Carbon::now()->firstOfMonth()->toDateString();
        $created_F = '';
        $created_T = '';
        $schedule_F = '';
        $schedule_T = '';
        $where = array();
        if ($request->patient_id && $request->patient_id != '') {
            $where[] = array(['users.id' => $request->patient_id]);
        }
        if ($request->phone && $request->phone != '') {
            $where[] = array(
                'users.phone',
                'like',
                '%' . GeneralFunctions::cleanNumber($request->phone) . '%'
            );
        }
        if (Gate::allows('appointments_export_all')) {
            if ($request->date_from && $request->date_from != '') {
                $where[] = array(
                    'appointments.scheduled_date',
                    '>=',
                    $request->date_from . ' 00:00:00'
                );
                $schedule_F = $request->date_from;
            }
            if ($request->date_to && $request->date_to != '') {
                $where[] = array(
                    'appointments.scheduled_date',
                    '<=',
                    $request->date_to . '23:59:59'
                );
                $schedule_T = $request->date_to;
            }
        } else if (Gate::allows('appointments_export_today')) {
            $where[] = array(
                'appointments.scheduled_date',
                '>=',
                $today . ' 00:00:00'
            );
            $schedule_F = $today;
            $where[] = array(
                'appointments.scheduled_date',
                '<=',
                $today . '23:59:59'
            );
            $schedule_T = $today;
        } else if (Gate::allows('appointments_export_this_month')) {
            $where[] = array(
                'appointments.scheduled_date',
                '>=',
                $this_month . ' 00:00:00'
            );
            $schedule_F = $this_month;
            $where[] = array(
                'appointments.scheduled_date',
                '<=',
                $today . '23:59:59'
            );
            $schedule_T = $today;
        }
        if ($request->doctor_id && $request->doctor_id != '') {
            $where[] = array(
                'doctor_id',
                '=',
                $request->doctor_id
            );
        }
        if ($request->region_id && $request->region_id != '') {
            $where[] = array(['region_id' => $request->region_id]);
        }
        if ($request->city_id && $request->city_id != '') {
            $where[] = array(['city_id' => $request->city_id]);
        }
        if ($request->location_id && $request->location_id != '') {
            $where[] = array(['location_id' => $request->location_id]);
        }
        if ($request->service_id && $request->service_id != '') {
            $where[] = array(['service_id' => $request->service_id]);
        }
        if ($request->created_by && $request->created_by != '') {
            $where[] = array(['appointments.created_by' => $request->created_by]);
        }
        if ($request->converted_by && $request->converted_by != '') {
            $where[] = array(['appointments.converted_by' => $request->converted_by]);
        }
        if ($request->updated_by && $request->updated_by != '') {
            $where[] = array(['appointments.updated_by' => $request->updated_by]);
        }
        if ($request->appointment_status_id && $request->appointment_status_id != '') {
            $where[] = array(['appointments.base_appointment_status_id' => $request->appointment_status_id]);
        }
        if ($request->appointment_type_id && $request->appointment_type_id != '') {
            $where[] = array(['appointments.appointment_type_id' => $request->appointment_type_id]);
        }
        if ($request->consultancy_type && $request->consultancy_type != '') {
            $where[] = array(['appointments.consultancy_type' => $request->consultancy_type]);
        }
        if (Gate::allows('appointments_export_all')) {
            if ($request->created_from && $request->created_from != '') {
                $where[] = array(
                    'appointments.created_at',
                    '>=',
                    $request->created_from . ' 00:00:00'
                );
                $created_F = $request->created_from;
            }
            if ($request->created_to && $request->created_to != '') {
                $where[] = array(
                    'appointments.created_at',
                    '<=',
                    $request->created_to . ' 23:59:59'
                );
                $created_T = $request->created_to;
            }
        }
        $consultancyslug = AppointmentTypes::where('slug', '=', 'consultancy')->first();
        $treatmentslug = AppointmentTypes::where('slug', '=', 'treatment')->first();
        $records = array();
        $records["data"] = array();
        if (Gate::allows('appointments_consultancy')) {
            $resultQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->where('appointments.appointment_type_id', '=', $consultancyslug->id)
                ->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (Gate::allows('appointments_services')) {
            $resultQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->where('appointments.appointment_type_id', '=', $treatmentslug->id)
                ->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (Gate::allows('appointments_consultancy') && Gate::allows('appointments_services')) {
            $resultQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (!Gate::allows('appointments_consultancy') && !Gate::allows('appointments_services')) {
            $resultQuery = Appointments::join('users', function ($join) {
                $join->on('users.id', '=', 'appointments.patient_id')
                    ->where('users.user_type_id', '=', config('constants.patient_id'));
            })->where([
                ['appointments.appointment_type_id', '!=', $consultancyslug->id],
                ['appointments.appointment_type_id', '!=', $treatmentslug->id]
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
                    '%' . $request->name . '%'
                );
                $query->orWhere(
                    'appointments.name',
                    'like',
                    '%' . $request->name . '%'
                );
            });
        }
        if ($request->name && $request->name != '') {
            $resultQuery->where(function ($query) use ($request) {
                $query->where(
                    'users.name',
                    'like',
                    '%' . $request->name . '%'
                );
                $query->orWhere(
                    'appointments.name',
                    'like',
                    '%' . $request->name . '%'
                );
            });
        }
        $Appointments_count = $resultQuery->select('*', 'appointments.name as patient_name', 'appointments.id as app_id', 'appointments.created_by as app_created_by', 'appointments.updated_by as app_updated_by', 'appointments.created_at as app_created_at')->count();
        if ($Appointments_count > 10000) {
            flash("The data you are trying to pull is too large in size. Please apply some filters to reduce the data count ( maximum 10,000 ) to be able to export it.")->warning();
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
                } else if ($appointment->consultancy_type == 'virtual') {
                    $consultancy_type = 'Virtual';
                } else {
                    $consultancy_type = '';
                }
                $activeSheet->setCellValue('A' . $counter, $appointment->patient_id);
                $activeSheet->setCellValue('B' . $counter, ($appointment->patient_name) ? $appointment->patient_name : $appointment->name);
                $activeSheet->setCellValue('C' . $counter, \App\Helpers\GeneralFunctions::prepareNumber4Call($appointment->patient->phone,1));
                $activeSheet->setCellValue('D' . $counter, ($appointment->scheduled_date) ? Carbon::parse($appointment->scheduled_date, null)->format('M j, Y') . ' at ' . Carbon::parse($appointment->scheduled_time, null)->format('h:i A') : '-');
                $activeSheet->setCellValue('E' . $counter, $appointment->doctor->name);
                $activeSheet->setCellValue('F' . $counter, (array_key_exists($appointment->region_id, $Regions)) ? $Regions[$appointment->region_id]->name : 'N/A');
                $activeSheet->setCellValue('G' . $counter, $appointment->city_id ? $appointment->city->name : 'N/A');
                $activeSheet->setCellValue('H' . $counter, $appointment->location_id ? $appointment->location->name : 'N/A');
                $activeSheet->setCellValue('I' . $counter, $appointment->service->name);
                $activeSheet->setCellValue('J' . $counter, ($appointment->appointment_status_id ? ($appointment->appointment_status->parent_id ? $AppointmentStatuses[$appointment->appointment_status->parent_id]->name : $appointment->appointment_status->name) : ''));
                $activeSheet->setCellValue('K' . $counter, $appointment->appointment_type->name);
                $activeSheet->setCellValue('L' . $counter, $consultancy_type);
                $activeSheet->setCellValue('M' . $counter, Carbon::parse($appointment->app_created_at)->format('F j,Y h:i A'));
                $activeSheet->setCellValue('N' . $counter, array_key_exists($appointment->app_created_by, $Users) ? $Users[$appointment->app_created_by]->name : 'N/A');
                $activeSheet->setCellValue('O' . $counter, array_key_exists($appointment->converted_by, $Users) ? $Users[$appointment->converted_by]->name : 'N/A');
                $activeSheet->setCellValue('p' . $counter, array_key_exists($appointment->app_updated_by, $Users) ? $Users[$appointment->app_updated_by]->name : 'N/A');
                $counter++;
            }
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . 'General Report' . '.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');
    }
    function logPage($id) {
        return view('admin.appointments.logs.appointmentlog', compact('id'));
    }
    public function viewLog($id, $type)
    {
        if (!Gate::allows('appointments_log')) {
            abort(404);
        }
        $appointments = AuditTrailTables::whereName('appointments')->first();
        $audit_trails = AuditTrails::has('auditTrailChanges')->with('auditTrailChanges')->where('audit_trail_table_name', '=', $appointments->id)->where('table_record_id', '=', $id)->get();
        $data = array();
        foreach ($audit_trails as $audit_trail) {
            $audit_trail_action = AuditTrailActions::find($audit_trail->audit_trail_action_name);
            $data[$audit_trail->id] = array(
                'action' => $audit_trail_action->name,
                'caused_by' => $audit_trail->userr->name,
                'created_at' => $audit_trail->created_at,
            );
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
            $records["meta"] = [
                'field' => "action",
                'page' => 1,
                'pages' => count($data),
                'perpage' => 20,
                'total' => count($data),
                'sort' => "DESC",
            ];
            $records["permissions"] = [
                'contact' => Gate::allows('contact')
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
                    $activeSheet->setCellValue('A' . $counter, $count++);
                    $activeSheet->setCellValue('B' . $counter, $log['action']);
                    $activeSheet->setCellValue('C' . $counter, isset($log['name']) ? $log['name'] : '-');
                    $activeSheet->setCellValue('D' . $counter, isset($log['phone']) ? \App\Helpers\GeneralFunctions::prepareNumber4Call($log['phone']) : '-');
                    if (isset($log['scheduled_date']) && isset($log['scheduled_time']))
                        $activeSheet->setCellValue('E' . $counter, \Carbon\Carbon::parse($log['scheduled_date'], null)->format('M j, Y') . ' at ' . \Carbon\Carbon::parse($log['scheduled_time'], null)->format('h:i A'));
                    elseif (isset($log['scheduled_time']))
                        $activeSheet->setCellValue('E' . $counter, \Carbon\Carbon::parse($log['scheduled_time'], null)->format('h:i A'));
                    elseif (isset($log['scheduled_date']))
                        $activeSheet->setCellValue('E' . $counter, \Carbon\Carbon::parse($log['scheduled_date'], null)->format('M j, Y'));
                    else
                        $activeSheet->setCellValue('E' . $counter, '-');
                    $activeSheet->setCellValue('F' . $counter, isset($log['doctor_id']) ? $log['doctor_id'] : '-');
                    $activeSheet->setCellValue('G' . $counter, isset($log['resource_id']) ? $log['resource_id'] : '-');
                    $activeSheet->setCellValue('H' . $counter, isset($log['region_id']) ? $log['region_id'] : '-');
                    $activeSheet->setCellValue('I' . $counter, isset($log['city_id']) ? $log['city_id'] : '-');
                    $activeSheet->setCellValue('J' . $counter, isset($log['location_id']) ? $log['location_id'] : '-');
                    $activeSheet->setCellValue('K' . $counter, isset($log['service_id']) ? $log['service_id'] : '-');
                    $activeSheet->setCellValue('L' . $counter, isset($log['base_appointment_status_id']) ? $log['base_appointment_status_id'] : '-');
                    $activeSheet->setCellValue('M' . $counter, isset($log['appointment_status_id']) ? $log['appointment_status_id'] : '-');
                    $activeSheet->setCellValue('N' . $counter, isset($log['appointment_type_id']) ? $log['appointment_type_id'] : '-');
                    $activeSheet->setCellValue('O' . $counter, isset($log['created_at']) ? \Carbon\Carbon::parse($log['created_at'])->format('F j,Y h:i A') : '-');
                    $activeSheet->setCellValue('P' . $counter, isset($log['created_by']) ? $log['created_by'] : '-');
                    $activeSheet->setCellValue('Q' . $counter, isset($log['converted_by']) ? $log['converted_by'] : '-');
                    $activeSheet->setCellValue('R' . $counter, isset($log['updated_by']) ? $log['updated_by'] : '-');
                    $activeSheet->setCellValue('S' . $counter, isset($log['send_message']) ? ($log['send_message'] == 1) ? 'Sent' : 'Not Sent' : '-');
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
                    $activeSheet->setCellValue('A' . $counter, $count++);
                    $activeSheet->setCellValue('B' . $counter, $log['action']);
                    $activeSheet->setCellValue('C' . $counter, isset($log['name']) ? $log['name'] : '-');
                    $activeSheet->setCellValue('D' . $counter, isset($log['phone']) ? \App\Helpers\GeneralFunctions::prepareNumber4Call($log['phone']) : '-');
                    if (isset($log['scheduled_date']) && isset($log['scheduled_time']))
                        $activeSheet->setCellValue('E' . $counter, \Carbon\Carbon::parse($log['scheduled_date'], null)->format('M j, Y') . ' at ' . \Carbon\Carbon::parse($log['scheduled_time'], null)->format('h:i A'));
                    elseif (isset($log['scheduled_time']))
                        $activeSheet->setCellValue('E' . $counter, \Carbon\Carbon::parse($log['scheduled_time'], null)->format('h:i A'));
                    elseif (isset($log['scheduled_date']))
                        $activeSheet->setCellValue('E' . $counter, \Carbon\Carbon::parse($log['scheduled_date'], null)->format('M j, Y'));
                    else
                        $activeSheet->setCellValue('E' . $counter, '-');
                    $activeSheet->setCellValue('F' . $counter, isset($log['doctor_id']) ? $log['doctor_id'] : '-');
                    $activeSheet->setCellValue('G' . $counter, isset($log['region_id']) ? $log['region_id'] : '-');
                    $activeSheet->setCellValue('H' . $counter, isset($log['city_id']) ? $log['city_id'] : '-');
                    $activeSheet->setCellValue('I' . $counter, isset($log['location_id']) ? $log['location_id'] : '-');
                    $activeSheet->setCellValue('J' . $counter, isset($log['service_id']) ? $log['service_id'] : '-');
                    $activeSheet->setCellValue('K' . $counter, isset($log['base_appointment_status_id']) ? $log['base_appointment_status_id'] : '-');
                    $activeSheet->setCellValue('L' . $counter, isset($log['appointment_status_id']) ? $log['appointment_status_id'] : '-');
                    $activeSheet->setCellValue('M' . $counter, isset($log['appointment_type_id']) ? $log['appointment_type_id'] : '-');
                    $activeSheet->setCellValue('N' . $counter, isset($log['created_at']) ? \Carbon\Carbon::parse($log['created_at'])->format('F j,Y h:i A') : '-');
                    $activeSheet->setCellValue('O' . $counter, isset($log['created_by']) ? $log['created_by'] : '-');
                    $activeSheet->setCellValue('P' . $counter, isset($log['converted_by']) ? $log['converted_by'] : '-');
                    $activeSheet->setCellValue('Q' . $counter, isset($log['updated_by']) ? $log['updated_by'] : '-');
                    $activeSheet->setCellValue('R' . $counter, isset($log['send_message']) ? ($log['send_message'] == 1) ? 'Sent' : 'Not Sent' : '-');
                    $counter++;
                }
            }
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . 'AppointmentLog' . '.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');
    }
    public function checkPhoneExist(Request $request){
        $record=Patients::where('phone','like','%' .GeneralFunctions::cleanNumber($request->input('phone').'%'))->first();
        if($record){
            return response()->json(1);
        }else{
            return response()->json(0);
        }
    }
    public function export(Request $request) {

        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', '0'); // for infinite time of execution

        return Excel::download(new ExportAppointment($limit, $offset), 'appointments.xlsx');
    }
    public function getSchedule(Request $request) {

        $appointment = Appointments::select('id', 'scheduled_date', 'scheduled_time')->find($request->id);

        $appointment->scheduled_time = Carbon::parse($appointment->scheduled_time)->format("h:i A");

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'appointment' => $appointment
        ]);
    }
    public function updateSchedule(Request $request) {
        $data = [];
        $appointment = Appointments::find($request->appointment_id);
        if ($appointment) {
            if($appointment->scheduled_date != $request->scheduled_date ){
                $data['converted_by'] = Auth::user()->id;
            }
            if($appointment->scheduled_time != Carbon::parse($request->scheduled_time)->format("H:i:s")){
                $data['converted_by'] = Auth::user()->id;
            }
            if ($appointment->appointment_status_id == config('constants.appointment_status_arrived')
                || $appointment->appointment_status_id == config('constants.appointment_status_cancelled')) {
                return ApiHelper::apiResponse($this->success, 'Appointment has Invoice or has been canceled!', false);
            }
            $rota = $this->checkRota($appointment, $request);
            if ($rota['status']) {
                $appointment->update([
                    'scheduled_date' => Carbon::parse($request->scheduled_date)->format("Y-m-d"),
                    'scheduled_time' => Carbon::parse($request->scheduled_time)->format("H:i:s"),
                    'converted_by' => ($data == null) ? $appointment->converted_by : $data['converted_by'],
                    'appointment_status_id' => config('constants.appointment_status_pending'),
                    'base_appointment_status_id' => config('constants.appointment_status_pending'),
                    'updated_at'=>Filters::getCurrentTimeStamp()
                ]);
                $screen = $appointment->appointment_type_id == 1 ? 'Consultancy' : 'Treatment';
                GeneralFunctions::saveAppointmentLogs('rescheduled', $screen, $appointment);
                $log_type = 'sms';
                $patient = Patients::findOrFail($appointment->patient_id);
                if($appointment->isDirty('scheduled_date')){
                    $this->SendRescheduleSms($request->appointment_id, $patient->phone, $log_type, $appointment->account_id);
                }
                return ApiHelper::apiResponse($this->success, 'Record updated successfully!');
            }
            return ApiHelper::apiResponse($this->success, $rota['message'], $rota['status']);
        }
        return ApiHelper::apiResponse($this->success, 'Appointment not found!', false);
    }
    private function checkRota($appointment, $request) {

        $object = new \stdClass();
        if ($request->scheduled_date && $request->scheduled_time) {
            $object->start = $request->scheduled_date ."T". \Illuminate\Support\Carbon::parse($request->scheduled_time)->format("H:i:s");
        } else {
            $object->start = $request->start;
        }
        $object->city_id = $request->city_id ?? '';
        $object->doctor_id = $request->doctor_id;
        $object->location_id = $request->location_id;
        $object->appointment_type = $appointment->appointment_type_id == 1 ? 'consulting' : 'treatment';
        if ($appointment->appointment_type_id == config('constants.appointment_type_consultancy') ) {
            $rota = AppointmentCheckesWidget::AppointmentConsultancyCheckes($object);
        } else {
            $object->machine_id = $appointment->resource_id;
            $rota = AppointmentCheckesWidget::AppointmentAppointmentCheckesfromcalender($object);
        }
        return $rota;
    }
    private function checkRotaUpdate($appointment, $request) {

        $object = new \stdClass();
        if ($request->scheduled_date && $request->scheduled_time) {
            $object->start = $request->scheduled_date ."T". \Illuminate\Support\Carbon::parse($request->scheduled_time)->format("h:i:s");
        } else {
            $object->start = $request->start;
        }
        $object->city_id = $appointment->city_id;
        $object->doctor_id = $appointment->doctor_id;
        $object->location_id = $appointment->location_id;
        $object->appointment_type = $appointment->appointment_type_id == 1 ? 'consulting' : 'treatment';
        if ($appointment->appointment_type_id == config('constants.appointment_type_consultancy') ) {
            $rota = AppointmentCheckesWidget::AppointmentConsultancyCheckes($object);
        } else {
            $object->machine_id = $appointment->resource_id;
            $rota = AppointmentCheckesWidget::AppointmentAppointmentCheckesfromcalender($object);
        }
        return $rota;
    }
    private function SendRescheduleSms($appointmentId, $patient_phone, $log_type = 'sms', $account_id)
    {
        $appointment = Appointments::find($appointmentId);
        if ($appointment->appointment_type_id == Config::get('constants.appointment_type_consultancy')) {
            // SEND SMS for Appointment Booked
            if($appointment->consultancy_type == 'virtual'){
                $SMSTemplate = SMSTemplates::getBySlug('virtual-on-appointment', $account_id); // 'on-appointment' for virtual consultancy SMS
            } else {
                $SMSTemplate = SMSTemplates::getBySlug('on-appointment', $account_id); // 'on-appointment' for Appointment SMS
            }
        } else {
            // SEND SMS for Appointment Booked
            $SMSTemplate = SMSTemplates::getBySlug('treatment-on-appointment', $account_id); // 'on-appointment' for Appointment SMS
        }
        if (!$SMSTemplate) {
            // SMS Promotion is disabled
            return array(
                'status' => true,
                'sms_data' => 'SMS Promotion is disabled',
                'error_msg' => '',
            );
        }
        $preparedText = Appointments::prepareSMSContent($appointmentId, $SMSTemplate->content);
        $setting = Settings::whereSlug('sys-current-sms-operator')->first();
        $UserOperatorSettings = UserOperatorSettings::getRecord($account_id, $setting->data);
        if ($setting->data == 1) {
            $SMSObj = array(
                'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
                'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
                'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($patient_phone)),
                'text' => $preparedText,
                'mask' => $UserOperatorSettings->mask, // Setting ID 3 for Mask
                'test_mode' => $UserOperatorSettings->test_mode, // Setting ID 3 Test Mode
            );
            $response = TelenorSMSAPI::SendSMS($SMSObj);
        } else {
            $SMSObj = array(
                'username' => $UserOperatorSettings->username, // Setting ID 1 for Username
                'password' => $UserOperatorSettings->password, // Setting ID 2 for Password
                'from' => $UserOperatorSettings->mask,
                'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($patient_phone)),
                'text' => $preparedText,
                'test_mode' => $UserOperatorSettings->test_mode, // Setting ID 3 Test Mode
            );
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
}
