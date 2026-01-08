<?php

namespace App\Http\Controllers\Admin\Patients;

use App;
use App\Helpers\ACL;
use App\Helpers\Filters;
use App\Helpers\Financelog;
use App\Helpers\NodesTree;
use App\Http\Controllers\Controller;
use App\Models\Accounts;
use App\Models\Appointments;
use App\Models\AppointmentTypes;
use App\Models\AuditTrailChanges;
use App\Models\AuditTrails;
use App\Models\Cities;
use App\Models\Discounts;
use App\Models\InvoiceDetails;
use App\Models\Invoices;
use App\Models\InvoiceStatuses;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\Packages;
use App\Models\PackageService;
use App\Models\Regions;
use App\Models\Services;
use App\Models\Settings;
use App\Models\User;
use Auth;
use Carbon\Carbon;
use Config;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use PDF;

class InvoicesController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($id)
    {
        if (! Gate::allows('patients_invoice_manage')) {
            return abort(401);
        }

        $patient = User::finduser($id);
        if ($patient) {

            $parentGroups = new NodesTree();
            $parentGroups->current_id = -1;
            $parentGroups->build(0, Auth::User()->account_id);
            $parentGroups->toList($parentGroups, -1);

            $Services = $parentGroups->nodeList;
            $leadServices = null;

            $invoicestatus = InvoiceStatuses::get()->pluck('name', 'id');
            $invoicestatus->prepend('All', '');

            $cities = Cities::getActiveSortedFeatured(ACL::getUserCities());
            $cities->prepend('All', '');

            $regions = Regions::getActiveSorted(ACL::getUserRegions());
            $regions->prepend('Select a Region', '');

            $locations = Locations::getActiveSorted(ACL::getUserCentres());
            $locations->prepend('All', '');

            $filters = Filters::all(Auth::User()->id, 'patient_invoices');

            return view('admin.patients.card.invoices.index', compact('patient', 'Services', 'invoicestatus', 'leadServices', 'cities', 'regions', 'locations', 'filters'));

        } else {
            return view('error_full');
        }
    }

    /*
       * Show the invoice data in datatable
       * */
    public function datatable(Request $request, $id)
    {

        $apply_filter = false;
        if ($request->get('action')) {
            $action = $request->get('action');
            if (isset($action[0]) && $action[0] == 'filter_cancel') {
                Filters::flush(Auth::User()->id, 'patient_invoices');
            } elseif ($action == 'filter') {
                $apply_filter = true;
            }
        }

        $records = [];
        $records['data'] = [];

        if ($request->get('customActionType') && $request->get('customActionType') == 'group_action') {
            $invoices = Invoices::getBulkData($request->get('id'));
            if ($invoices) {
                foreach ($invoices as $invoices) {
                    // Check if child records exists or not, If exist then disallow to delete it.
                    if (! Invoices::isChildExists($invoices->id, Auth::User()->account_id)) {
                        $invoices->delete();
                    }
                }
            }
            $records['customActionStatus'] = 'OK'; // pass custom message(useful for getting status of group actions)
            $records['customActionMessage'] = 'Records has been deleted successfully!'; // pass custom message(useful for getting status of group actions)
        }
        // Get Total Records
        $iTotalRecords = Invoices::getTotalRecords($request, Auth::User()->account_id, $id, $apply_filter, 'patient_invoices');

        $iDisplayLength = intval($request->get('length'));
        $iDisplayLength = $iDisplayLength < 0 ? $iTotalRecords : $iDisplayLength;
        $iDisplayStart = intval($request->get('start'));
        $sEcho = intval($request->get('draw'));

        $invoice = Invoices::getRecords($request, $iDisplayStart, $iDisplayLength, Auth::User()->account_id, $id, $apply_filter, 'patient_invoices');

        if ($invoice) {
            foreach ($invoice as $invoice) {
                $location_info = Locations::find($invoice->location_id);
                $user = User::where('id', '=', $invoice->patient_id)->first();
                $service = Services::where('id', '=', $invoice->service_id)->first();
                $cancel = InvoiceStatuses::where('slug', '=', 'cancelled')->first();
                $invoicestatus = InvoiceStatuses::where('id', '=', $invoice->invoice_status_id)->first();
                $records['data'][] = [
                    'name' => $user->name,
                    'phone' => Gate::allows('contact') ? \App\Helpers\GeneralFunctions::prepareNumber4Call($user->phone) : '***********',
                    'region' => $location_info->region->name,
                    'city' => $location_info->city->name,
                    'location' => $location_info->name,
                    'service' => $service->name,
                    'invoice_status' => $invoicestatus->name,
                    'price' => number_format($invoice->total_price),
                    'created_at' => Carbon::parse($invoice->created_at)->format('F j,Y h:i A'),
                    'actions' => view('admin.patients.card.invoices.actions', compact('invoice', 'cancel'))->render(),
                ];
            }
        }

        $records['draw'] = $sEcho;
        $records['recordsTotal'] = $iTotalRecords;
        $records['recordsFiltered'] = $iTotalRecords;

        return response()->json($records);
    }

    public function cancel($id)
    {

        if (! Gate::allows('patients_invoice_cancel')) {
            return abort(401);
        }
        $invoiceinformation = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
            ->where('invoices.id', '=', $id)
            ->select('invoice_details.package_id')->first();
        if ($invoiceinformation->package_id) {
            $package_information = Packages::find($invoiceinformation->package_id);
            if ($package_information->is_refund == '1') {
                flash('Invoice belongs to package that already refunded, so you unable to delete it.')->warning()->important();

                return redirect()->route('admin.invoices.index');
            }

        }

        $invoice = Invoices::CancelRecord($id, Auth::User()->account_id);

        $invocies = Invoices::find($id);

        $invoice_detail = InvoiceDetails::where('invoice_id', '=', $id)->first();

        if ($invoice_detail->package_id) {

            $packageservice = PackageService::InvoiceCancel($invoice_detail, Auth::User()->account_id);
        }

        $appintment = Appointments::find($invocies->appointment_id);

        $appointment_type = AppointmentTypes::where('id', '=', $appintment->appointment_type_id)->first();

        $data_package['cash_flow'] = 'in';
        $data_package['cash_amount'] = $invocies->total_price;
        $data_package['patient_id'] = $invocies->patient_id;
        $data_package['payment_mode_id'] = '1';
        $data_package['account_id'] = Auth::User()->account_id;
        $data_package['appointment_type_id'] = $appointment_type->id;
        $data_package['appointment_id'] = $invocies->appointment_id;
        $data_package['location_id'] = $appintment->location_id;
        $data_package['created_by'] = Auth::User()->id;
        $data_package['updated_by'] = Auth::User()->id;
        $data_package['invoice_id'] = $id;
        $data_package['is_cancel'] = '1';

        if ($invoice_detail->package_id != null) {
            $data_package['package_id'] = $invoice_detail->package_id;
        }
        $package_advances = PackageAdvances::createRecord_forinvoice($data_package);

        return redirect()->route('admin.invoicepatient.index', [$invocies->patient_id]);
    }

    /*display invoice
     * */
    public function displayInvoice($id)
    {

        if (! Gate::allows('patients_invoice_manage')) {
            return abort(401);
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

        return view('admin.patients.card.invoices.displayInvoice', compact('Invoiceinfo', 'patient', 'account', 'service', 'discount', 'invoicestatus', 'company_phone_number', 'location_info'));
    }

    /*
     * Display the pdf file
     * */
    public function invoice_pdf($id)
    {

        if (! Gate::allows('patients_invoice_manage')) {
            return abort(401);
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

        $appointment_info = Appointments::where('id', '=', $Invoiceinfo->appointment_id)->first();

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
        if ($appointment_info->appointment_type_id == 1) {

            $setting_info = Settings::where('slug', '=', 'sys-consultancy-invoice-medical-operator')->first();

            if ($setting_info->data == 1) {
                $content = view('admin.patients.card.invoices.InvoiceMedicalHistorypdf', compact('Invoiceinfo', 'patient', 'account', 'service', 'discount', 'invoicestatus', 'company_phone_number', 'location_info', 'appointment_info'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);

                return $pdf->stream('admin.patients.card.invoices.InvoiceMedicalHistorypdf.pdf');
            } else {
                $content = view('admin.patients.card.invoices.invoice_pdf', compact('Invoiceinfo', 'patient', 'account', 'service', 'discount', 'invoicestatus', 'company_phone_number', 'location_info'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);

                return $pdf->stream('admin.patients.card.invoices.invoice_pdf.pdf');
            }
        } else {
            $content = view('admin.patients.card.invoices.invoice_pdf', compact('Invoiceinfo', 'patient', 'account', 'service', 'discount', 'invoicestatus', 'company_phone_number', 'location_info', 'appointment_info'))->render();
            $pdf = App::make('dompdf.wrapper');
            $pdf->loadHTML($content);

            return $pdf->stream('admin.patients.card.invoices.invoice_pdf.pdf');
        }
    }

    /*
     *  Function for log for invoice
     */
    public function invoicelog($id, $patient_id, $type)
    {
        if (! Gate::allows('patients_invoice_log')) {
            return abort(401);
        }
        $patient = User::finduser($patient_id);
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
                    'updated_at' => $audit->updated_at,
                ];
                $audit_info_detail = AuditTrailChanges::where('audit_trail_id', '=', $audit->id)->get();

                foreach ($audit_info_detail as $audit_detail) {
                    $result = Financelog::Calculate_Val_advance($audit_detail);
                    $finance_log[$audit->id][$audit_detail->field_name] = $result;
                }
            }
        }
        if ($type === 'web') {
            return view('admin.patients.card.invoices.log', compact('finance_log', 'id', 'patient'));
        }

        return $this->invoicelogexcel($id, $finance_log);
    }

    /*
     *  Function for log for invoice excel in patient Card
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
}
