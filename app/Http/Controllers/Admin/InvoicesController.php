<?php

namespace App\Http\Controllers\Admin;

use App;
use App\HelperModule\ApiHelper;
use App\Helpers\ACL;
use App\Helpers\Filters;
use App\Helpers\Financelog;
use App\Helpers\JazzSMSAPI;
use App\Helpers\NodesTree;
use App\Helpers\TelenorSMSAPI;
use App\Http\Controllers\Controller;
use App\Models\Accounts;
use App\Models\Appointments;
use App\Models\AppointmentTypes;
use App\Models\AuditTrailChanges;
use App\Models\AuditTrails;
use App\Models\Bundles;
use App\Models\Cities;
use App\Models\Discounts;
use App\Models\InvoiceDetails;
use App\Models\Invoices;
use App\Models\InvoiceStatuses;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\PackageBundles;
use App\Models\Packages;
use App\Models\PackageService;
use App\Models\Regions;
use App\Models\Services;
use App\Models\Settings;
use App\Models\SMSLogs;
use App\Models\User;
use App\Models\UserOperatorSettings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PDF;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class InvoicesController extends Controller
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
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        if (! Gate::allows('invoices_manage')) {
            return abort(401);
        }

        return view('admin.invoices.index');
    }

    /*
     * Show the invoice data in datatable
     * */
    public function datatable(Request $request, $id = false)
    {

        try {

            $fileName = 'invoices';

            $filters = getFilters($request->all());

            $apply_filter = checkFilters($filters, $fileName);

            $records = [];
            $records['data'] = [];

            if (hasFilter($filters, 'delete')) {
                $ids = explode(',', $filters['delete']);
                $invoices = Invoices::getBulkData($ids);
                if ($invoices) {
                    foreach ($invoices as $invoices) {
                        // Check if child records exists or not, If exist then disallow to delete it.
                        if (! Invoices::isChildExists($invoices->id, Auth::User()->account_id)) {
                            $invoices->delete();
                        }
                    }
                }

                $records['status'] = true;
                $records['message'] = 'Records has been deleted successfully!';
            }

            [$orderBy, $order] = getSortBy($request);

            // Get Total Records
            $iTotalRecords = Invoices::getTotalRecords($request, Auth::User()->account_id, $id, $apply_filter, 'invoices');

            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $invoice = Invoices::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $id, $apply_filter, 'invoices');

            $records = $this->filtersData($records);

            if ($invoice) {
                foreach ($invoice as $invoice) {
                    $location_info = Locations::find($invoice->location_id);
                    $user = User::where('id', '=', $invoice->patient_id)->first();
                    $service = Services::where('id', '=', $invoice->service_id)->first();
                    $cancel = InvoiceStatuses::where('slug', '=', 'cancelled')->first();
                    $invoicestatus = InvoiceStatuses::where('id', '=', $invoice->invoice_status_id)->first();
                    $records['data'][] = [
                        'id' => $invoice->id,
                        'invoice_number' => sprintf('%05d', $invoice->id),
                        'patient_id' => \App\Helpers\GeneralFunctions::patientSearchStringAdd($user->id),
                        'name' => $user->name,
                        'phone' => Gate::allows('contact') ? \App\Helpers\GeneralFunctions::prepareNumber4Call($user->phone) : '***********',
                        'location' => $location_info->name,
                        'service' => $service->name,
                        'invoice_status' => $invoicestatus->name,
                        'appointment_type_id' => ($invoice->appointment_type_id === 1) ? Config::get('constants.Consultancy') : Config::get('constants.Service'),
                        'price' => number_format($invoice->total_price),
                        'created_at' => Carbon::parse($invoice->created_at)->format('F j,Y h:i A'),
                        'cancel' => $cancel,
                        'invoice' => $invoice,
                    ];
                }

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
                'manage' => Gate::allows('invoices_manage'),
                'cancel' => Gate::allows('invoices_cancel'),
                'log' => Gate::allows('invoices_log'),
                'sms_log' => Gate::allows('invoices_sms_log'),
            ];

            if ($id) {
                $records['permissions'] = [
                    'manage' => Gate::allows('patients_invoice_manage'),
                    'cancel' => Gate::allows('patients_invoice_cancel'),
                    'log' => Gate::allows('patients_invoice_log'),
                    'sms_log' => Gate::allows('patients_invoice_sms_log'),
                ];
            }

            return response()->json($records);
        } catch (\Exception $e) {
            return ApiHelper::apiException($e);
        }
    }

    private function filtersData($records)
    {

        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::User()->account_id);
        $parentGroups->toList($parentGroups, -1);

        $Services = $parentGroups->nodeList;
        $leadServices = null;

        $invoicestatus = InvoiceStatuses::get()->pluck('name', 'id');

        $cities = Cities::getActiveSortedFeatured(ACL::getUserCities());

        $regions = Regions::getActiveSorted(ACL::getUserRegions());

        $locations = Locations::getActiveSorted(ACL::getUserCentres());

        $appointment_types = AppointmentTypes::where('account_id', '=', '1')->get()->pluck('name', 'id');

        $filters = Filters::all(Auth::User()->id, 'invoices');

        if ($user_id = Filters::get(Auth::User()->id, 'invoices', 'patient_id')) {
            $patient = User::where([
                'id' => $user_id,
            ])->first();
            if ($patient) {
                $patient = $patient->toArray();
            }
        } else {
            $patient = [];
        }

        $records['filter_values'] = [
            'services' => $Services,
            'invoicestatus' => $invoicestatus,
            'leadServices' => $leadServices,
            'cities' => $cities,
            'regions' => $regions,
            'locations' => $locations,
            'patient' => $patient,
            'appointment_types' => $appointment_types,
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

    public function cancel($id)
    {
        if (! Gate::allows('invoices_cancel')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }

        $invoiceinformation = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
            ->where('invoices.id', '=', $id)
            ->select('invoice_details.package_id')->first();

        if ($invoiceinformation->package_id) {
            $package_information = Packages::find($invoiceinformation->package_id);
            if ($package_information->is_refund == '1') {
                return ApiHelper::apiResponse($this->success, 'Invoice belongs to package that already refunded, so you unable to delete it.', false);
            }

        }

        $invoice = Invoices::CancelRecord($id, Auth::User()->account_id);

        $invocies = Invoices::find($id);

        $invoice_detail = InvoiceDetails::where('invoice_id', '=', $id)->first();

        if ($invoice_detail->package_id) {

            $packageservice = PackageService::InvoiceCancel($invoice_detail, Auth::User()->account_id);
        }

        $appintment = Appointments::find($invocies->appointment_id);
         $appintment->update([
            'base_appointment_status_id' => 1,
            'appointment_status_id' => 1,
            'arrived_at' => null,
            'converted_at' => null
         ]);
        $appointment_type = AppointmentTypes::where('id', '=', $appintment?->appointment_type_id)->first();
        PackageAdvances::where('invoice_id', '=', $id)->where('cash_flow', '=', 'out')->delete();
        if ($appointment_type && $appintment && $invocies) {
            $data_package['cash_flow'] = 'in';
            $data_package['cash_amount'] = $invocies->total_price ?? 0;
            $data_package['patient_id'] = $invocies->patient_id ?? 0;
            $data_package['payment_mode_id'] = '1';
            $data_package['account_id'] = Auth::User()->account_id;
            $data_package['appointment_type_id'] = $appointment_type->id ?? 0;
            $data_package['appointment_id'] = $invocies->appointment_id ?? 0;
            $data_package['location_id'] = $appintment->location_id ?? 0;
            $data_package['created_by'] = Auth::User()->id;
            $data_package['updated_by'] = Auth::User()->id;
            $data_package['invoice_id'] = $id;
            $data_package['is_cancel'] = '1';

            if ($invoice_detail->package_id != null) {
                $data_package['package_id'] = $invoice_detail->package_id;
            }
            //$package_advances = PackageAdvances::createRecord_forinvoice($data_package);

            // Log invoice cancelled activity
            $patient = \App\Models\Patients::find($invocies->patient_id);
            $location = \App\Models\Locations::with('city')->find($appintment->location_id);
            $service = \App\Models\Services::find($appintment->service_id);
            \App\Helpers\ActivityLogger::logInvoiceCancelled($invocies, $patient, $location, $service, $appointment_type, $appintment);

            return ApiHelper::apiResponse($this->success, 'Invoice has been canceled successfully.');
        }

        return ApiHelper::apiResponse($this->success, 'Record not found.', false);

    }

    /*display invoice
     * */
    public function displayInvoice($id)
    {
        if (! Gate::allows('invoices_manage')) {
            return ApiHelper::apiResponse($this->unauthorized, 'You are not authorized to access this resource.');
        }
        $Invoiceinfo = DB::table('invoices')
            ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
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
                'invoice_details.is_exclusive'
            )
            ->first();
        $location_info = Locations::find($Invoiceinfo->location_id);

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
        
        // Get doctor name from appointment
        $doctor = null;
        if ($Invoiceinfo->appointment_id) {
            $appointment = Appointments::with('doctor')->find($Invoiceinfo->appointment_id);
            $doctor = $appointment?->doctor;
        }

        if ($Invoiceinfo) {
            $Invoiceinfo->created_at = Carbon::parse($Invoiceinfo->created_at)->format('F j,Y');
        }

        return ApiHelper::apiResponse($this->success, 'Recode found.', true, [
            'Invoiceinfo' => $Invoiceinfo,
            'patient' => $patient,
            'account' => $account,
            'service' => $service,
            'discount' => $discount,
            'invoicestatus' => $invoicestatus,
            'company_phone_number' => $company_phone_number,
            'location_info' => $location_info,
            'doctor' => $doctor,
        ]);
    }

    /*
     * Display the pdf file
     * */
    public function invoice_pdf($id, $download = null, $flag = 0)
    {
        if (! Gate::allows('invoices_manage') && ! Gate::allows('appointments_invoice_display')) {
            return abort(401);
        }
        
        // 'print' is used as placeholder to pass flag without downloading
        if ($download === 'print') {
            $download = null;
        }
        $Invoiceinfo = DB::table('invoices')
            ->join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
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
                'invoice_details.is_exclusive'
            )->first();

        $appointment_info = Appointments::where('id', '=', $Invoiceinfo->appointment_id)->first();
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
        $bundle = Bundles::find($package_bundle->bundle_id ?? 0);
        $location_info = Locations::find($Invoiceinfo->location_id);

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
        
        // Get service price for treatment invoice: actual_price from package_services, fallback to services.price
        $service_price_display = $service->price ?? 0;
        if ($package_service && $package_service->actual_price !== null) {
            $service_price_display = $package_service->actual_price;
        }

        if ($appointment_info?->appointment_type_id == 1 && $flag == 0) {

            $setting_info = Settings::where('slug', '=', 'sys-consultancy-invoice-medical-operator')->first();

            if ($setting_info->data = 1) {

                if ($download) {

                    $content = view('admin.invoices.InvoiceMedicalHistorypdf', compact('Invoiceinfo', 'patient', 'account', 'service', 'discount', 'invoicestatus', 'company_phone_number', 'location_info', 'appointment_info', 'bundle', 'download'))->render();
                    $pdf = App::make('dompdf.wrapper');
                    $pdf->loadHTML($content);

                    return $pdf->download('consultancy-medical-history-form-C-'.$Invoiceinfo->patient_id.'.pdf');
                }

                return view('admin.invoices.InvoiceMedicalHistorypdf', compact('Invoiceinfo', 'patient', 'account', 'service', 'discount', 'invoicestatus', 'company_phone_number', 'location_info', 'appointment_info', 'bundle', 'download'));

            } else {

                if ($download) {

                    $content = view('admin.invoices.invoice_pdf', compact('Invoiceinfo', 'patient', 'account', 'service', 'discount', 'invoicestatus', 'company_phone_number', 'location_info', 'bundle', 'download'))->render();
                    $pdf = App::make('dompdf.wrapper');
                    $pdf->loadHTML($content);

                    return $pdf->download('admin.invoices.invoice_pdf.pdf');
                }

                return view('admin.invoices.invoice_pdf', compact('appointment_info', 'Invoiceinfo', 'patient', 'account', 'service', 'discount', 'invoicestatus', 'company_phone_number', 'location_info', 'bundle', 'download'));
            }
        } else {

            $content = view('admin.invoices.invoice_pdf', compact('Invoiceinfo', 'patient', 'account', 'service', 'discount', 'invoicestatus', 'company_phone_number', 'location_info', 'appointment_info', 'bundle', 'download', 'service_price_display'))->render();
            $pdf = App::make('dompdf.wrapper');
            $pdf->loadHTML($content);
            if ($download) {
                if ($flag == 1) {
                    return $pdf->download('consultancy-invoice-C-'.$Invoiceinfo->patient_id.'.pdf');
                }

                return $pdf->download('treatment-invoice-C-'.$Invoiceinfo->patient_id.'.pdf');
            }

            return view('admin.invoices.invoice_pdf', compact('Invoiceinfo', 'patient', 'account', 'service', 'discount', 'invoicestatus', 'company_phone_number', 'location_info', 'appointment_info', 'bundle', 'download', 'service_price_display'));
        }
    }

    /*
     *  Function for log for invoice
     */
    public function invoicelog($id, $type, $patient_id = null)
    {
        if (! Gate::allows('invoices_log')) {
            return abort(401);
        }

        $action_array = [
            1 => 'Create',
            2 => 'Edit',
            3 => 'Delete',
            4 => 'Inactive',
            5 => 'Active',
            6 => 'Cancel',
        ];
        $table_array = [
            26 => 'Invoice',
            27 => 'Invoice Detail',
            25 => 'Finance',
        ];
        $finance_log = [];

        $package_advances = PackageAdvances::where('invoice_id', '=', $id)->orderBy('created_at', 'asc')->get();

        foreach ($package_advances as $advance) {

            $audit_info = AuditTrails::where([
                ['table_record_id', '=', $advance->id],
                ['audit_trail_table_name', '=', Config::get('constants.package_advance_table_name_log')],
            ])->orderBy('created_at', 'asc')->get();

            foreach ($audit_info as $audit) {
                $finance_log[$audit->id] = [
                    'id' => $audit->id,
                    'action' => $action_array[$audit->audit_trail_action_name],
                    'table' => $table_array[$audit->audit_trail_table_name],
                    'user_id' => $audit->user->name,
                    'created_at' => $audit->created_at,
                    'updated_at' => Filters::getCurrentTimeStamp(),
                ];
                $audit_info_detail = AuditTrailChanges::where('audit_trail_id', '=', $audit->id)->get();

                foreach ($audit_info_detail as $audit_detail) {
                    $result = Financelog::Calculate_Val_advance($audit_detail);
                    $finance_log[$audit->id][$audit_detail->field_name] = $result;
                }
            }
        }

        if ($type === 'web') {
            return view('admin.invoices.log', compact('finance_log', 'id'));
        }

        return $this->invoicelogexcel($id, $finance_log);
    }

    public function invoiceDatatable(Request $request, $id)
    {

        [$orderBy, $order] = getSortBy($request);
        [$finance_log, $iDisplayLength, $iTotalRecords, $pages, $page] = $this->getInvoicesData($id, $request, $orderBy, $order);

        $records['data'] = $finance_log;
        $records['meta'] = [
            'field' => $orderBy,
            'page' => $page,
            'pages' => $pages,
            'perpage' => $iDisplayLength,
            'total' => $iTotalRecords,
            'sort' => $order,
        ];

        return ApiHelper::apiDataTable($records);
    }

    private function getInvoicesData($id, $request, $orderBy, $order)
    {

        $action_array = [
            1 => 'Create',
            2 => 'Edit',
            3 => 'Delete',
            4 => 'Inactive',
            5 => 'Active',
            6 => 'Cancel',
        ];
        $table_array = [
            26 => 'Invoice',
            27 => 'Invoice Detail',
            25 => 'Finance',
        ];
        $finance_log = [];

        $package_advances = PackageAdvances::where('invoice_id', '=', $id)->orderBy('created_at', 'asc')->get();

        foreach ($package_advances as $advance) {

            $iTotalRecords = AuditTrails::where([
                ['table_record_id', '=', $advance->id],
                ['audit_trail_table_name', '=', Config::get('constants.package_advance_table_name_log')],
            ])->count();

            [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $iTotalRecords);

            $audit_info = AuditTrails::where([
                ['table_record_id', '=', $advance->id],
                ['audit_trail_table_name', '=', Config::get('constants.package_advance_table_name_log')],
            ])->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('created_at', 'asc')->get();

            foreach ($audit_info as $audit) {
                $finance_log[$audit->id] = [
                    'id' => $audit->id,
                    'action' => $action_array[$audit->audit_trail_action_name],
                    'table' => $table_array[$audit->audit_trail_table_name],
                    'user_id' => $audit->user->name,
                    'created_at' => $audit->created_at,
                    'updated_at' => Filters::getCurrentTimeStamp(),
                ];
                $audit_info_detail = AuditTrailChanges::where('audit_trail_id', '=', $audit->id)->get();

                foreach ($audit_info_detail as $audit_detail) {
                    $result = Financelog::Calculate_Val_advance($audit_detail);
                    $finance_log[$audit->id][$audit_detail->field_name] = $result;
                }
            }
        }

        return [
            $finance_log,
            $iDisplayLength,
            $iTotalRecords,
            $pages,
            $page,
        ];
    }

    /*
     *  Function for log for invoice excel
     */
    public function invoicelogexcel($id, $finance_log)
    {
        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'INVOICE ID')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', $id);

        $activeSheet->setCellValue('A2', '#')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', 'Cash Flow')->getStyle('B2')->getFont()->setBold(true);
        $activeSheet->setCellValue('C2', 'Cash Amount')->getStyle('C2')->getFont()->setBold(true);
        $activeSheet->setCellValue('D2', 'Refund')->getStyle('D2')->getFont()->setBold(true);
        $activeSheet->setCellValue('E2', 'Adjustment')->getStyle('E2')->getFont()->setBold(true);
        $activeSheet->setCellValue('F2', 'Tax')->getStyle('F2')->getFont()->setBold(true);
        $activeSheet->setCellValue('G2', 'Cancel')->getStyle('G2')->getFont()->setBold(true);
        $activeSheet->setCellValue('H2', 'Refund Note')->getStyle('H2')->getFont()->setBold(true);
        $activeSheet->setCellValue('I2', 'Payment Mode')->getStyle('I2')->getFont()->setBold(true);
        $activeSheet->setCellValue('J2', 'Appointment Type')->getStyle('J2')->getFont()->setBold(true);
        $activeSheet->setCellValue('K2', 'Location')->getStyle('K2')->getFont()->setBold(true);
        $activeSheet->setCellValue('L2', 'Created By')->getStyle('L2')->getFont()->setBold(true);
        $activeSheet->setCellValue('M2', 'Updated By')->getStyle('M2')->getFont()->setBold(true);
        $activeSheet->setCellValue('N2', 'Plan')->getStyle('N2')->getFont()->setBold(true);
        $activeSheet->setCellValue('O2', 'Invoice Id')->getStyle('O2')->getFont()->setBold(true);
        $activeSheet->setCellValue('P2', 'Created At')->getStyle('P2')->getFont()->setBold(true);
        $activeSheet->setCellValue('Q2', 'Updated At')->getStyle('Q2')->getFont()->setBold(true);

        $count = 1;
        $counter = 4;

        if (count($finance_log)) {

            foreach ($finance_log as $log) {
                $activeSheet->setCellValue('A'.$counter, $count++);
                $activeSheet->setCellValue('B'.$counter, isset($log['cash_flow']) ? $log['cash_flow'] : '-');
                $activeSheet->setCellValue('C'.$counter, isset($log['cash_amount']) ? $log['cash_amount'] : '-');
                $activeSheet->setCellValue('D'.$counter, isset($log['is_refund']) ? $log['is_refund'] : '-');
                $activeSheet->setCellValue('E'.$counter, isset($log['is_adjustment']) ? $log['is_adjustment'] : '-');
                $activeSheet->setCellValue('F'.$counter, isset($log['is_tax']) ? $log['is_tax'] : '-');
                $activeSheet->setCellValue('G'.$counter, isset($log['is_cancel']) ? $log['is_cancel'] : '-');
                $activeSheet->setCellValue('H'.$counter, isset($log['refund_note']) ? $log['refund_note'] : '-');
                $activeSheet->setCellValue('I'.$counter, isset($log['payment_mode_id']) ? $log['payment_mode_id'] : '-');
                $activeSheet->setCellValue('J'.$counter, isset($log['appointment_type_id']) ? $log['appointment_type_id'] : '-');
                $activeSheet->setCellValue('K'.$counter, isset($log['location_id']) ? $log['location_id'] : '-');
                $activeSheet->setCellValue('L'.$counter, isset($log['created_by']) ? $log['created_by'] : '-');
                $activeSheet->setCellValue('M'.$counter, isset($log['updated_by']) ? $log['updated_by'] : '-');
                $activeSheet->setCellValue('N'.$counter, isset($log['package_id']) ? $log['package_id'] : '-');
                $activeSheet->setCellValue('O'.$counter, isset($log['invoice_id']) ? $log['invoice_id'] : '-');
                $activeSheet->setCellValue('P'.$counter, isset($log['created_at']) ? \Carbon\Carbon::parse($log['created_at'])->format('F j,Y h:i A') : '-');
                $activeSheet->setCellValue('Q'.$counter, isset($log['updated_at']) ? \Carbon\Carbon::parse($log['updated_at'])->format('F j,Y h:i A') : '-');

                $counter++;

            }

        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'Invoicelog'.'.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');

    }

    /**
     * Load invoice Sms History.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function showSMSLogs($id)
    {
        $SMSLogs = SMSLogs::where('invoice_id', '=', $id)->orderBy('created_at', 'desc')->get();

        return ApiHelper::apiResponse($this->success, 'Record found', true, [
            'SMSLogs' => $SMSLogs,
        ]);
    }

    /**
     * Re-send invoice SMS
     *
     * @param  \App\Http\Requests\Admin\StoreUpdateAppointmentsRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function sendLogSMS(Request $request)
    {

        $data = $request->all();

        $SMSLog = SMSLogs::findOrFail($request->get('id'));

        if ($SMSLog) {
            $response = $this->resendSMS($SMSLog->id, $SMSLog->to, $SMSLog->text, $SMSLog->invoice_id);

            if ($response['status']) {
                return response()->json(['status' => 1]);
            }
        }

        return response()->json(['status' => 0]);
    }

    /**
     * Calling sms log
     *
     * @param  \App\Http\Requests\Admin\StoreUpdateAppointmentsRequest  $request
     * @return \Illuminate\Http\Response
     */
    private function resendSMS($smsId, $patient_phone, $preparedText, $invoice_id)
    {
        $Invoice_info = Invoices::find($invoice_id);

        $setting = Settings::whereSlug('sys-current-sms-operator')->first();

        $UserOperatorSettings = UserOperatorSettings::getRecord($Invoice_info->account_id, $setting->data);

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
}
