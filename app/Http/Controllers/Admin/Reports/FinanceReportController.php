<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Models\Discounts;
use App\Models\InvoiceStatuses;
use App\Models\Locations;
use App\Models\MachineType;
use App\Models\Regions;
use App\Models\Resources;
use App\Reports\Finanaces;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Services;
use App\Helpers\NodesTree;
use App\Models\Doctors;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Helpers\ACL;
use Barryvdh\DomPDF\Facade as PDF;
use Illuminate\Support\Facades\App;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use App\Models\AppointmentTypes;
use App\Models\Cities;
use App\Models\AppointmentStatuses;
use App\Models\Invoices;
use Illuminate\Support\Facades\Gate;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use App\Helpers\Explode_Multi_select;
use App\Helpers\GeneralFunctions;
use App\Models\Appointments;
use App\Models\AppointmentsDailyStats;
use App\Models\RoleHasUsers;
use Illuminate\Support\Facades\DB;

class FinanceReportController extends Controller
{
    protected $error;
    protected $success;
    protected $unauthorized;


    public function __construct()
    {
        $this->error = config('constants.api_status.error');
        $this->success = config('constants.api_status.success');
        $this->unauthorized = config('constants.api_status.unauthorized');
    }

    /**
     * Display a listing filter for finanace report.
     *
     * @return \Illuminate\Http\Response
     */
    public function report()
    {
        if (!Gate::allows('finance_general_revenue_reports_manage')) {
            return abort(401);
        }
        $allserviceslug = Services::where(['slug' => 'all'])->isActive()->first();

        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::User()->account_id);
        $parentGroups->toList($parentGroups, -1);
        $services = $parentGroups->nodeList;

        foreach ($services as $key => $ser) {
            if ($key) {
                if (isset($ser['name']) && $ser['name']== $allserviceslug->name) {
                    unset($services[$key]);
                }
            }
        }

        $employees = User::getAllActiveEmployeeRecords(Auth::User()->account_id, ACL::getUserCentres())->pluck('name', 'id');

        $operators = User::getAllActivePractionersRecords(Auth::User()->account_id, ACL::getUserCentres())->pluck('name', 'id');

        $select_All = array('' => 'All');

        $users = ($select_All + $employees->toArray() + $operators->toArray());

        $operators->prepend('All', '');

        $locations = Locations::getActiveSorted(ACL::getUserCentres());
        $locations->prepend('All', '');

        $locations_com = Locations::getActiveSorted(ACL::getUserCentres());

        $appointment_types = AppointmentTypes::getAllRecords(Auth::User()->account_id)->pluck('name', 'id');
        $appointment_types->prepend('All', '');

        $regions = Regions::getActiveSorted(ACL::getUserRegions());
        $regions->prepend('All', '');

        $cities = Cities::getActiveSorted(ACL::getUserCities());
        $cities->prepend('All', '');

        return view('admin.reports.accountsalesreport.index', compact('locations', 'services', 'users', 'appointment_types', 'regions', 'locations_com', 'operators', 'cities'));
    }

    /**
     * Load Report
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function reportLoad(Request $request)
    {
        switch ($request->get('report_type')) {
            case 'collection_by_service':
                return self::collectionbyservice($request);
                break;
            case 'daily_employee_stats':
                return self::dailyEmployeeStats($request);
                break;
            case 'general_revenue_report_detail':
                return self::generalrevenuereportdetail($request);
                break;
            case 'general_revenue_report_summary':
                return self::generalrevenuereportsummary($request);
                break;
            case 'conversion_report':
                return self::conversionreport($request);
                break;
            default:
                return self::collectionbyservice($request);
                break;
        }
    }

    /**
     * Center Performance status by revenue
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    private static function centerperformancestatsbyrevenue(Request $request)
    {

        if (!Gate::allows('finance_general_revenue_reports_center_performance_stats_by_revenue_finance')) {
            return abort(401);
        }
        if ($request->get('date_range')) {
            $date_range = explode(' - ', $request->get('date_range'));
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        $filters = array();

        $users = User::getAllRecords(Auth::User()->account_id)->pluck('name', 'id');
        $users->prepend('All', '');

        $filters['doctors'] = Doctors::getAll(Auth::User()->account_id)->getDictionary();
        $filters['regions'] = Regions::getAll(Auth::User()->account_id)->getDictionary();
        $filters['cities'] = Cities::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['locations'] = Locations::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['services'] = Services::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['appointment_statuses'] = AppointmentStatuses::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['appointment_types'] = AppointmentTypes::getAllRecords(Auth::User()->account_id)->getDictionary();
        $filters['users'] = User::getAllRecords(Auth::User()->account_id)->getDictionary();

        $reportData = Finanaces::centerperformancestatsbyrevenue($request->all(), $filters);
        $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();

        foreach ($reportData as $key1 => $report_Data) {
            foreach ($report_Data['records'] as $key2 => $report_row) {
                $Salestotal = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->where([
                        ['invoices.appointment_id', '=', $report_row->id],
                        ['invoices.invoice_status_id', '=', $invoicestatus->id]
                    ])->first();
                if ($Salestotal) {
                    $reportData[$key1]['records'][$key2]['Salestotal'] = $Salestotal->tax_including_price;
                } else {
                    $reportData[$key1]['records'][$key2]['Salestotal'] = 0;
                }
            }
        }

        $filters['reportData'] = $reportData;

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.centerperformancestatsbyrevenue.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.centerperformancestatsbyrevenue.reportprint', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $content = view('admin.reports.centerperformancestatsbyrevenue.reportpdf', compact('reportData', 'filters', 'start_date', 'end_date'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A3', 'landscape');
                return $pdf->stream('Staff Appointment Schedule', 'landscape');
                break;
            case 'excel':
                self::centerperformancestatsbyrevenueExcel($reportData, $filters, $start_date, $end_date);
                break;
            default:
                return view('admin.reports.centerperformancestatsbyrevenue.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * Centre performance states by revenue Excel
     * @param (mixed) $reportData
     * @param (mixed) $filters
     * @param (mixed) $start_date
     * @param (mixed) $end_date
     *
     * @return \Illuminate\Http\Response
     */
    private static function centerperformancestatsbyrevenueExcel($reportData, $filters, $start_date, $end_date)
    {

        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From ' . $start_date . ' to ' . $end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', 'ID')->getStyle('A3')->getFont()->setBold(true);
        $activeSheet->setCellValue('B3', 'Client Name')->getStyle('B3')->getFont()->setBold(true);
        $activeSheet->setCellValue('C3', 'Created At')->getStyle('C3')->getFont()->setBold(true);
        $activeSheet->setCellValue('D3', 'Doctor')->getStyle('D3')->getFont()->setBold(true);
        $activeSheet->setCellValue('E3', 'Service')->getStyle('E3')->getFont()->setBold(true);
        $activeSheet->setCellValue('F3', 'Email')->getStyle('F3')->getFont()->setBold(true);
        $activeSheet->setCellValue('G3', 'Scheduled')->getStyle('G3')->getFont()->setBold(true);
        $activeSheet->setCellValue('H3', 'City')->getStyle('H3')->getFont()->setBold(true);
        $activeSheet->setCellValue('I3', 'Centre')->getStyle('I3')->getFont()->setBold(true);
        $activeSheet->setCellValue('J3', 'Status')->getStyle('J3')->getFont()->setBold(true);
        $activeSheet->setCellValue('K3', 'Type')->getStyle('K3')->getFont()->setBold(true);
        $activeSheet->setCellValue('L3', 'Service Price')->getStyle('L3')->getFont()->setBold(true);
        $activeSheet->setCellValue('M3', 'Invoice Price')->getStyle('M3')->getFont()->setBold(true);
        $activeSheet->setCellValue('N3', 'Created By')->getStyle('N3')->getFont()->setBold(true);

        $counter = 4;
        if (count($reportData)) {
            $count = 0;
            $salesgrandtotal = 0;
            $servicegrandtotal = 0;
            $grandcount = 0;
            foreach ($reportData as $reportpackagedata) {
                $activeSheet->setCellValue('A' . $counter, $reportpackagedata['name'])->getStyle('A' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('B' . $counter, $reportpackagedata['region'])->getStyle('B' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('C' . $counter, $reportpackagedata['city'])->getStyle('C' . $counter)->getFont()->setBold(true);

                $counter++;
                $count = 0;
                $salestotal = 0;
                $servicetotal = 0;
                foreach ($reportpackagedata['records'] as $reportRow) {

                    $serviceprice = (array_key_exists($reportRow->service_id, $filters['services'])) ? $filters['services'][$reportRow->service_id]->price : '';
                    $servicetotal += $serviceprice;
                    $salestotal += $reportRow->Salestotal;

                    $activeSheet->setCellValue('A' . $counter, $reportRow->patient_id)->getStyle('A' . $counter)->getFont();
                    $activeSheet->setCellValue('B' . $counter, $reportRow->patient->name)->getStyle('B' . $counter)->getFont();
                    $activeSheet->setCellValue('C' . $counter, \Carbon\Carbon::parse($reportRow->created_at)->format('M j, Y H:i A'))->getStyle('C' . $counter);
                    $activeSheet->setCellValue('D' . $counter, (array_key_exists($reportRow->doctor_id, $filters['doctors'])) ? $filters['doctors'][$reportRow->doctor_id]->name : '');
                    $activeSheet->setCellValue('E' . $counter, (array_key_exists($reportRow->service_id, $filters['services'])) ? $filters['services'][$reportRow->service_id]->name : '');
                    $activeSheet->setCellValue('F' . $counter, $reportRow->patient->email);
                    $activeSheet->setCellValue('G' . $counter, ($reportRow->scheduled_date) ? \Carbon\Carbon::parse($reportRow->scheduled_date, null)->format('M j, Y') . ' at ' . \Carbon\Carbon::parse($reportRow->scheduled_time, null)->format('h:i A') : '-');
                    $activeSheet->setCellValue('H' . $counter, (array_key_exists($reportRow->city_id, $filters['cities'])) ? $filters['cities'][$reportRow->city_id]->name : '');
                    $activeSheet->setCellValue('I' . $counter, (array_key_exists($reportRow->location_id, $filters['locations'])) ? $filters['locations'][$reportRow->location_id]->name : '');
                    $activeSheet->setCellValue('J' . $counter, (array_key_exists($reportRow->base_appointment_status_id, $filters['appointment_statuses'])) ? $filters['appointment_statuses'][$reportRow->base_appointment_status_id]->name : '');
                    $activeSheet->setCellValue('K' . $counter, (array_key_exists($reportRow->appointment_type_id, $filters['appointment_types'])) ? $filters['appointment_types'][$reportRow->appointment_type_id]->name : '');
                    $activeSheet->setCellValue('L' . $counter, number_format($serviceprice, 2));
                    $activeSheet->setCellValue('M' . $counter, number_format($reportRow->Salestotal, 2));
                    $activeSheet->setCellValue('N' . $counter, (array_key_exists($reportRow->created_by, $filters['users'])) ? $filters['users'][$reportRow->created_by]->name : '');
                    $counter++;
                    $grandcount++;
                    $count++;
                }
                $servicegrandtotal += $servicetotal;
                $salesgrandtotal += $salestotal;

                $activeSheet->setCellValue('A' . $counter, '');
                $counter++;

                $activeSheet->setCellValue('A' . $counter, $reportpackagedata['name'])->getStyle('A' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('B' . $counter, 'Total');
                $activeSheet->setCellValue('C' . $counter, $count)->getStyle('B' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('L' . $counter, number_format($servicetotal, 2))->getStyle('L' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('M' . $counter, number_format($salestotal, 2))->getStyle('M' . $counter)->getFont()->setBold(true);
                $counter++;

                $activeSheet->setCellValue('A' . $counter, '');
                $counter++;
            }
            $activeSheet->setCellValue('A' . $counter, '');
            $counter++;

            $activeSheet->setCellValue('B' . $counter, 'Grand Total');
            $activeSheet->setCellValue('C' . $counter, $grandcount)->getStyle('B' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('L' . $counter, number_format($servicegrandtotal, 2))->getStyle('L' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('M' . $counter, number_format($salesgrandtotal, 2))->getStyle('M' . $counter)->getFont()->setBold(true);
            $counter++;


        }
        $activeSheet->setCellValue('A' . $counter, '');
        $counter++;

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . 'centerperformancestatsbyrevenueeExcel' . '.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');
    }

    /**
     * Center Performance status by service type
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    private static function centerperformancestatsbyservicetype(Request $request)
    {

        if (!Gate::allows('finance_general_revenue_reports_center_performance_stats_by_service_type_finance')) {
            return abort(401);
        }

        if ($request->get('date_range')) {
            $date_range = explode(' - ', $request->get('date_range'));
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        $filters = array();

        $users = User::getAllRecords(Auth::User()->account_id)->pluck('name', 'id');
        $users->prepend('All', '');

        $filters['doctors'] = Doctors::getAll(Auth::User()->account_id)->getDictionary();
        $filters['regions'] = Regions::getAll(Auth::User()->account_id)->getDictionary();
        $filters['cities'] = Cities::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['locations'] = Locations::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['services'] = Services::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['appointment_statuses'] = AppointmentStatuses::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['appointment_types'] = AppointmentTypes::getAllRecords(Auth::User()->account_id)->getDictionary();
        $filters['users'] = User::getAllRecords(Auth::User()->account_id)->getDictionary();

        $reportData = Finanaces::centerperformancestatsbyservices($request->all(), $filters);
        $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();

        foreach ($reportData as $key1 => $report_Data) {
            foreach ($report_Data['records'] as $key2 => $report_row) {
                $Salestotal = Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                    ->where([
                        ['invoices.appointment_id', '=', $report_row->id],
                        ['invoices.invoice_status_id', '=', $invoicestatus->id]
                    ])->first();
                if ($Salestotal) {
                    $reportData[$key1]['records'][$key2]['Salestotal'] = $Salestotal->tax_including_price;
                } else {
                    $reportData[$key1]['records'][$key2]['Salestotal'] = 0;
                }
            }
        }

        $filters['reportData'] = $reportData;

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.centerperformancestatsbyservicetype.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.centerperformancestatsbyservicetype.reportprint', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $content = view('admin.reports.centerperformancestatsbyservicetype.reportpdf', compact('reportData', 'filters', 'start_date', 'end_date'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A2', 'landscape');
                return $pdf->stream('Staff Appointment Schedule', 'landscape');
                break;
            case 'excel':
                self::centerperformancestatsbyservicetypeExcel($reportData, $filters, $start_date, $end_date);
                break;
            default:
                return view('admin.reports.centerperformancestatsbyservicetype.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
        }

    }

    /**
     * Centre performance states by service type Excel
     * @param (mixed) $reportData
     * @param (mixed) $filters
     * @param (mixed) $start_date
     * @param (mixed) $end_date
     *
     * @return \Illuminate\Http\Response
     */
    private static function centerperformancestatsbyservicetypeExcel($reportData, $filters, $start_date, $end_date)
    {

        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From ' . $start_date . ' to ' . $end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', 'ID')->getStyle('A3')->getFont()->setBold(true);
        $activeSheet->setCellValue('B3', 'Client Name')->getStyle('B3')->getFont()->setBold(true);
        $activeSheet->setCellValue('C3', 'Created At')->getStyle('C3')->getFont()->setBold(true);
        $activeSheet->setCellValue('D4', 'Doctor')->getStyle('D4')->getFont()->setBold(true);
        $activeSheet->setCellValue('E4', 'Service')->getStyle('E4')->getFont()->setBold(true);
        $activeSheet->setCellValue('F3', 'Email')->getStyle('F3')->getFont()->setBold(true);
        $activeSheet->setCellValue('G3', 'Scheduled')->getStyle('G3')->getFont()->setBold(true);
        $activeSheet->setCellValue('H3', 'City')->getStyle('H3')->getFont()->setBold(true);
        $activeSheet->setCellValue('I3', 'Centre')->getStyle('I3')->getFont()->setBold(true);
        $activeSheet->setCellValue('J3', 'Status')->getStyle('J3')->getFont()->setBold(true);
        $activeSheet->setCellValue('K3', 'Type')->getStyle('K3')->getFont()->setBold(true);
        $activeSheet->setCellValue('L3', 'Service Price')->getStyle('L3')->getFont()->setBold(true);
        $activeSheet->setCellValue('M3', 'Invoice Price')->getStyle('M3')->getFont()->setBold(true);
        $activeSheet->setCellValue('N3', 'Created By')->getStyle('N3')->getFont()->setBold(true);

        $counter = 4;
        if (count($reportData)) {
            $count = 0;
            $salesgrandtotal = 0;
            $servicegrandtotal = 0;
            $grandcount = 0;
            foreach ($reportData as $reportpackagedata) {
                $activeSheet->setCellValue('A' . $counter, $reportpackagedata['name'])->getStyle('A' . $counter)->getFont()->setBold(true);

                $counter++;
                $count = 0;
                $salestotal = 0;
                $servicetotal = 0;
                foreach ($reportpackagedata['records'] as $reportRow) {

                    $serviceprice = (array_key_exists($reportRow->service_id, $filters['services'])) ? $filters['services'][$reportRow->service_id]->price : '';
                    $servicetotal += $serviceprice;
                    $salestotal += $reportRow->Salestotal;

                    $activeSheet->setCellValue('A' . $counter, $reportRow->patient_id)->getStyle('A' . $counter)->getFont();
                    $activeSheet->setCellValue('B' . $counter, $reportRow->patient->name)->getStyle('B' . $counter)->getFont();
                    $activeSheet->setCellValue('C' . $counter, \Carbon\Carbon::parse($reportRow->created_at)->format('M j, Y H:i A'))->getStyle('C' . $counter);
                    $activeSheet->setCellValue('D' . $counter, (array_key_exists($reportRow->doctor_id, $filters['doctors'])) ? $filters['doctors'][$reportRow->doctor_id]->name : '');
                    $activeSheet->setCellValue('E' . $counter, (array_key_exists($reportRow->service_id, $filters['services'])) ? $filters['services'][$reportRow->service_id]->name : '');
                    $activeSheet->setCellValue('F' . $counter, $reportRow->patient->email);
                    $activeSheet->setCellValue('G' . $counter, ($reportRow->scheduled_date) ? \Carbon\Carbon::parse($reportRow->scheduled_date, null)->format('M j, Y') . ' at ' . \Carbon\Carbon::parse($reportRow->scheduled_time, null)->format('h:i A') : '-');
                    $activeSheet->setCellValue('H' . $counter, (array_key_exists($reportRow->city_id, $filters['cities'])) ? $filters['cities'][$reportRow->city_id]->name : '');
                    $activeSheet->setCellValue('I' . $counter, (array_key_exists($reportRow->location_id, $filters['locations'])) ? $filters['locations'][$reportRow->location_id]->name : '');
                    $activeSheet->setCellValue('J' . $counter, (array_key_exists($reportRow->base_appointment_status_id, $filters['appointment_statuses'])) ? $filters['appointment_statuses'][$reportRow->base_appointment_status_id]->name : '');
                    $activeSheet->setCellValue('K' . $counter, (array_key_exists($reportRow->appointment_type_id, $filters['appointment_types'])) ? $filters['appointment_types'][$reportRow->appointment_type_id]->name : '');
                    $activeSheet->setCellValue('L' . $counter, number_format($serviceprice, 2));
                    $activeSheet->setCellValue('M' . $counter, number_format($reportRow->Salestotal, 2));
                    $activeSheet->setCellValue('N' . $counter, (array_key_exists($reportRow->created_by, $filters['users'])) ? $filters['users'][$reportRow->created_by]->name : '');
                    $counter++;
                    $grandcount++;
                    $count++;
                }
                $servicegrandtotal += $servicetotal;
                $salesgrandtotal += $salestotal;

                $activeSheet->setCellValue('A' . $counter, '');
                $counter++;

                $activeSheet->setCellValue('A' . $counter, $reportpackagedata['name'])->getStyle('A' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('B' . $counter, 'Total');
                $activeSheet->setCellValue('C' . $counter, $count)->getStyle('B' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('L' . $counter, number_format($servicetotal, 2))->getStyle('L' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('M' . $counter, number_format($salestotal, 2))->getStyle('M' . $counter)->getFont()->setBold(true);
                $counter++;

                $activeSheet->setCellValue('A' . $counter, '');
                $counter++;
            }
            $activeSheet->setCellValue('A' . $counter, '');
            $counter++;

            $activeSheet->setCellValue('B' . $counter, 'Grand Total');
            $activeSheet->setCellValue('C' . $counter, $grandcount)->getStyle('B' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('L' . $counter, number_format($servicegrandtotal, 2))->getStyle('L' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('M' . $counter, number_format($salesgrandtotal, 2))->getStyle('M' . $counter)->getFont()->setBold(true);
            $counter++;
        }
        $activeSheet->setCellValue('A' . $counter, '');
        $counter++;

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . 'centerperformancestatsbyservicetypeExcel' . '.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');

    }

    /**
     * Load Account sales report
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    private static function accountsalesreportReport(Request $request)
    {

        if (!Gate::allows('finance_general_revenue_reports_account_sales_report')) {
            return abort(401);
        }

        if ($request->get('date_range')) {
            $date_range = explode(' - ', $request->get('date_range'));
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        $filters = array();

        $users = User::getAllRecords(Auth::User()->account_id)->pluck('name', 'id');
        $users->prepend('All', '');

        $reportData = \App\Reports\Invoices::getAccountSalesReport($request->all());

        $filters['reportData'] = $reportData;
        $filters['locations'] = Locations::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['services'] = Services::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['discounts'] = Discounts::getDiscountforreport(Auth::User()->account_id)->getDictionary();

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.accountsalesreport.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.accountsalesreport.reportprint', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $content = view('admin.reports.accountsalesreport.reportpdf', compact('reportData', 'filters', 'start_date', 'end_date'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A3', 'landscape');
                return $pdf->stream('Accounts Sales Report', 'landscape');
                break;
            case 'excel':
                self::accountsalesreportReportExcel($reportData, $filters, $start_date, $end_date);
                break;
            default:
                return view('admin.reports.accountsalesreport.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * Daily Employee Stats (Summary) Excel
     * @param (mixed) $reportData
     * @param (mixed) $start_date
     * @param (mixed) $end_date
     *
     * @return \Illuminate\Http\Response
     */
    private static function accountsalesreportReportExcel($reportData, $filters, $start_date, $end_date)
    {
        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From ' . $start_date . ' to ' . $end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', '');
        $activeSheet->setCellValue('B3', '');

        $activeSheet->setCellValue('A4', 'Invoice No.')->getStyle('A4')->getFont()->setBold(true);
        $activeSheet->setCellValue('B4', 'Centre')->getStyle('B4')->getFont()->setBold(true);
        $activeSheet->setCellValue('C4', 'Service')->getStyle('C4')->getFont()->setBold(true);
        $activeSheet->setCellValue('D4', 'Patient')->getStyle('D4')->getFont()->setBold(true);
        $activeSheet->setCellValue('E4', 'Created by')->getStyle('E4')->getFont()->setBold(true);
        $activeSheet->setCellValue('F4', 'Service Price')->getStyle('F4')->getFont()->setBold(true);
        $activeSheet->setCellValue('G4', 'Discount Name')->getStyle('G4')->getFont()->setBold(true);
        $activeSheet->setCellValue('H4', 'Discount Type')->getStyle('H4')->getFont()->setBold(true);
        $activeSheet->setCellValue('I4', 'Discount Amount')->getStyle('I4')->getFont()->setBold(true);
        $activeSheet->setCellValue('J4', 'Amount')->getStyle('J4')->getFont()->setBold(true);
        $activeSheet->setCellValue('K4', 'Tax')->getStyle('K4')->getFont()->setBold(true);
        $activeSheet->setCellValue('L4', 'Tax Value')->getStyle('L4')->getFont()->setBold(true);
        $activeSheet->setCellValue('M4', 'Total Amount')->getStyle('M4')->getFont()->setBold(true);
        $activeSheet->setCellValue('N4', 'Is Exclusive')->getStyle('N4')->getFont()->setBold(true);
        $activeSheet->setCellValue('O4', 'Payment Date')->getStyle('O4')->getFont()->setBold(true);

        $counter = 6;
        $grandserviceprice = 0;
        $totalAmount = 0;
        $totalTaxAmount = 0;

        foreach ($reportData as $reportRow) {

            $grandserviceprice += (array_key_exists($reportRow->service_id, $filters['services'])) ? $filters['services'][$reportRow->service_id]->price : 0;

            $totalAmount += $reportRow->tax_exclusive_serviceprice == null ? 0 : $reportRow->tax_exclusive_serviceprice;

            $totalTaxAmount += $reportRow->tax_including_price == null ? 0 : $reportRow->tax_including_price;


            $activeSheet->setCellValue('A' . $counter, $reportRow->id);
            $activeSheet->setCellValue('B' . $counter, (array_key_exists($reportRow->location_id, $filters['locations'])) ? $filters['locations'][$reportRow->location_id]->name : '-');
            $activeSheet->setCellValue('C' . $counter, (array_key_exists($reportRow->service_id, $filters['services'])) ? $filters['services'][$reportRow->service_id]->name : '-');
            $activeSheet->setCellValue('D' . $counter, $reportRow->patient->name);
            $activeSheet->setCellValue('E' . $counter, $reportRow->user->name);
            $activeSheet->setCellValue('F' . $counter, number_format((array_key_exists($reportRow->service_id, $filters['services'])) ? $filters['services'][$reportRow->service_id]->price : 0, 2));
            $activeSheet->setCellValue('G' . $counter, (array_key_exists($reportRow->discount_id, $filters['discounts'])) ? $filters['discounts'][$reportRow->discount_id]->name : '-');
            $activeSheet->setCellValue('H' . $counter, $reportRow->discount_type == null ? '-' : $reportRow->discount_type);
            $activeSheet->setCellValue('I' . $counter, number_format($reportRow->discount_price == null ? '0' : $reportRow->discount_price, 2));
            $activeSheet->setCellValue('J' . $counter, number_format($reportRow->tax_exclusive_serviceprice == null ? 0 : $reportRow->tax_exclusive_serviceprice, 2));
            $activeSheet->setCellValue('K' . $counter, $reportRow->tax_percenatage . '%');
            $activeSheet->setCellValue('L' . $counter, number_format($reportRow->tax_price == null ? 0 : $reportRow->tax_price, 2));
            $activeSheet->setCellValue('M' . $counter, number_format($reportRow->tax_including_price == null ? 0 : $reportRow->tax_including_price, 2));
            $activeSheet->setCellValue('N' . $counter, ($reportRow->is_exclusive) ? 'Yes' : 'No');
            $activeSheet->setCellValue('O' . $counter, ($reportRow->created_at) ? \Carbon\Carbon::parse($reportRow->created_at, null)->format('M j, Y') . ' at ' . \Carbon\Carbon::parse($reportRow->created_at, null)->format('h:i A') : '-');
            $counter++;
        }

        $activeSheet->setCellValue('A' . $counter, '');
        $counter++;

        $activeSheet->setCellValue('A' . $counter, 'Grand Total')->getStyle('A' . $counter)->getFont()->setBold(true);
        $activeSheet->setCellValue('F' . $counter, number_format($grandserviceprice, 2))->getStyle('F' . $counter)->getFont()->setBold(true);
        $activeSheet->setCellValue('J' . $counter, number_format($totalAmount, 2))->getStyle('J' . $counter)->getFont()->setBold(true);
        $activeSheet->setCellValue('M' . $counter, number_format($totalTaxAmount, 2))->getStyle('M' . $counter)->getFont()->setBold(true);


        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . 'AccountSalesReport' . '.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');


    }

    /**
     * Daily Employee Stats (Summary)
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    private static function dailyEmployeeStatsSummary(Request $request)
    {

        if (!Gate::allows('finance_general_revenue_reports_daily_employee_stats_summary')) {
            return abort(401);
        }
        if ($request->get('date_range')) {
            $date_range = explode(' - ', $request->get('date_range'));
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        $filters = array();

        $users = User::getAllRecords(Auth::User()->account_id)->pluck('name', 'id');
        $users->prepend('All', '');

        $filters['locations'] = Locations::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['services'] = Services::getAllRecordsDictionaryWithoutAll(Auth::User()->account_id);
        $filters['users'] = User::getAllRecords(Auth::User()->account_id)->getDictionary();

        $reportData = \App\Reports\Invoices::getDailyEmployeeStatsSummary($request->all(), $filters);
        $filters['reportData'] = $reportData;
        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.dailyemployeestatssummary.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.dailyemployeestatssummary.reportprint', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $pdf = PDF::loadView('admin.reports.dailyemployeestatssummary.reportpdf', compact('reportData', 'filters', 'start_date', 'end_date'));
                $pdf->setPaper('A4', 'landscape');
                return $pdf->stream('Daily Employee Stats Summary', 'landscape');
                break;
            case 'excel':
                self::dailyEmployeeStatsSummaryExcel($reportData, $start_date, $end_date);
                break;
            default:
                return view('admin.reports.dailyemployeestatssummary.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * Daily Employee Stats (Summary) Excel
     * @param (mixed) $reportData
     * @param (mixed) $start_date
     * @param (mixed) $end_date
     *
     * @return \Illuminate\Http\Response
     */
    private static function dailyEmployeeStatsSummaryExcel($reportData, $start_date, $end_date)
    {

        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From ' . $start_date . ' to ' . $end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', 'Service')->getStyle('A3')->getFont()->setBold(true);
        $activeSheet->setCellValue('B3', 'Total')->getStyle('B3')->getFont()->setBold(true);

        $counter = 4;
        $total = 0;

        foreach ($reportData as $row) {
            $total = $total + $row['amount'];
            $activeSheet->setCellValue('A' . $counter, $row['name']);
            $activeSheet->setCellValue('B' . $counter, number_format($row['amount'], 2));
            $counter++;
        }

        $activeSheet->setCellValue('A' . $counter, '');
        $activeSheet->setCellValue('B' . $counter, '');
        $counter++;

        $activeSheet->setCellValue('A' . $counter, 'Grand Total')->getStyle('A' . $counter)->getFont()->setBold(true);
        $activeSheet->setCellValue('B' . $counter, number_format($total, 2))->getStyle('B' . $counter)->getFont()->setBold(true);;


        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . 'SaleSummaryServiceWise' . '.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');

    }

    /**
     * Daily Employee Stats
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function dailyEmployeeStats(Request $request)
    {

        if (!Gate::allows('finance_general_revenue_reports_daily_employee_stats')) {
            return abort(401);
        }

        if ($request->get('date_range')) {
            $date_range = explode(' - ', $request->get('date_range'));
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        $filters = array();

        $users = User::getAllRecords(Auth::User()->account_id)->pluck('name', 'id');
        $users->prepend('All', '');

        $filters['locations'] = Locations::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['services'] = Services::getAllRecordsDictionaryWithoutAll(Auth::User()->account_id);
        $filters['users'] = User::getAllRecords(Auth::User()->account_id)->getDictionary();
        $filters['doctors'] = Doctors::getAll(Auth::User()->account_id)->getDictionary();

        $reportData = \App\Reports\Invoices::getDailyEmployeeStats($request->all(), $filters);
        foreach ($reportData as $rowpackage)
            $filters['reportData'] = $reportData;
        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.dailyemployeestats.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.dailyemployeestats.reportprint', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $pdf = PDF::loadView('admin.reports.dailyemployeestats.reportpdf', compact('reportData', 'filters', 'start_date', 'end_date'));
                $pdf->setPaper('A4', 'landscape');
                return $pdf->stream('Daily Employee Stats', 'landscape');
                break;
            case 'excel':
                self::dailyEmployeeStatsExcel($reportData, $start_date, $end_date);
                break;
            default:
                return view('admin.reports.dailyemployeestats.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * Daily Employee Stats Excel
     * @param (mixed) $reportData
     * @param (mixed) $start_date
     * @param (mixed) $end_date
     *
     * @return \Illuminate\Http\Response
     */
    private static function dailyEmployeeStatsExcel($reportData, $start_date, $end_date)
    {
        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From ' . $start_date . ' to ' . $end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', '');
        $activeSheet->setCellValue('B3', '');
        $activeSheet->setCellValue('C3', '');

        $activeSheet->setCellValue('A4', 'Doctor')->getStyle('A4')->getFont()->setBold(true);
        $activeSheet->setCellValue('B4', 'Service')->getStyle('B4')->getFont()->setBold(true);
        $activeSheet->setCellValue('C4', 'Total')->getStyle('C4')->getFont()->setBold(true);

        $counter = 5;
        $total = 0;
        if (count($reportData)) {
            $servicegrandtotal = 0;
            foreach ($reportData as $reportpackagedata) {
                $activeSheet->setCellValue('A' . $counter, $reportpackagedata['name'])->getStyle('A' . $counter)->getFont()->setBold(true);
                $counter++;
                $count = 0;
                $servicetotal = 0;
                foreach ($reportpackagedata['records'] as $reportRow) {
                    $servicetotal += $reportRow['amount'];

                    $activeSheet->setCellValue('B' . $counter, $reportRow['name']);
                    $activeSheet->setCellValue('C' . $counter, number_format($reportRow['amount'], 2));
                    $counter++;
                }
                $servicegrandtotal += $servicetotal;
                $activeSheet->setCellValue('A' . $counter, $reportpackagedata['name'])->getStyle('A' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('B' . $counter, 'Total')->getStyle('A' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('C' . $counter, number_format($servicetotal, 2))->getStyle('A' . $counter)->getFont()->setBold(true);
                $counter++;
            }
            $activeSheet->setCellValue('A' . $counter, '');
            $activeSheet->setCellValue('B' . $counter, '');
            $activeSheet->setCellValue('C' . $counter, '');
            $counter++;
            $activeSheet->setCellValue('A' . $counter, '');
            $activeSheet->setCellValue('B' . $counter, 'Grand Total')->getStyle('B' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('C' . $counter, number_format($servicegrandtotal, 2))->getStyle('C' . $counter)->getFont()->setBold(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . 'SaleSummaryDoctorsWise' . '.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');

    }

    /**
     * Load Sales By Services category.
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function salesbyservicecategory(Request $request)
    {

        if (!Gate::allows('finance_general_revenue_reports_sales_by_service_category')) {
            return abort(401);
        }


        if ($request->get('date_range')) {
            $date_range = explode(' - ', $request->get('date_range'));
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        $filters = array();

        $users = User::getAllRecords(Auth::User()->account_id)->pluck('name', 'id');
        $users->prepend('All', '');

        $filters['locations'] = Locations::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['services'] = Services::getAllRecordsDictionaryWithoutAll(Auth::User()->account_id);
        $filters['users'] = User::getAllRecords(Auth::User()->account_id)->getDictionary();
        $filters['servicesheads'] = Services::where([['active', '=', '1'], ['parent_id', '=', '0'], ['slug', '!=', 'all']])->orderBy('name', 'asc')->get()->getDictionary();
        $filters['doctors'] = Doctors::getAll(Auth::User()->account_id)->getDictionary();

        $reportData = \App\Reports\Invoices::getSalesbyServiceCategory($request->all(), $filters);
        foreach ($reportData as $rowpackage)
            $filters['reportData'] = $reportData;
        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.salesbyservicescategory.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.salesbyservicescategory.reportprint', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $pdf = PDF::loadView('admin.reports.salesbyservicescategory.reportpdf', compact('reportData', 'filters', 'start_date', 'end_date'));
                $pdf->setPaper('A4', 'landscape');
                return $pdf->stream('Daily Employee Stats', 'landscape');
                break;
            case 'excel':
                self::salesByServiceCategoryExcel($reportData, $start_date, $end_date);
                break;
            default:
                return view('admin.reports.salesbyservicescategory.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * Sales By Service Category
     * @param (mixed) $reportData
     * @param (mixed) $start_date
     * @param (mixed) $end_date
     *
     * @return \Illuminate\Http\Response
     */
    private static function salesByServiceCategoryExcel($reportData, $start_date, $end_date)
    {
        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From ' . $start_date . ' to ' . $end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', '');
        $activeSheet->setCellValue('B3', '');
        $activeSheet->setCellValue('C3', '');
        $activeSheet->setCellValue('D3', '');

        $activeSheet->setCellValue('A4', 'Service Category')->getStyle('A4')->getFont()->setBold(true);
        $activeSheet->setCellValue('B4', 'Service')->getStyle('B4')->getFont()->setBold(true);
        $activeSheet->setCellValue('C4', 'Quantity')->getStyle('C4')->getFont()->setBold(true);
        $activeSheet->setCellValue('D4', 'Price')->getStyle('D4')->getFont()->setBold(true);

        $activeSheet->setCellValue('A5', '');
        $activeSheet->setCellValue('B5', '');
        $activeSheet->setCellValue('C5', '');
        $activeSheet->setCellValue('D5', '');

        $counter = 6;
        $total = 0;
        if (count($reportData)) {
            $grandqty = 0;
            $servicegrandtotal = 0;
            foreach ($reportData as $reportpackagedata) {
                $activeSheet->setCellValue('A' . $counter, $reportpackagedata['name'])->getStyle('A' . $counter)->getFont()->setBold(true);
                $counter++;
                $qty = 0;
                $serviceheadtotal = 0;
                foreach ($reportpackagedata['records'] as $reportRow) {
                    $qty += $reportRow['qty'];
                    $serviceheadtotal += $reportRow['amount'];

                    $activeSheet->setCellValue('B' . $counter, $reportRow['name']);
                    $activeSheet->setCellValue('C' . $counter, number_format($reportRow['qty']));
                    $activeSheet->setCellValue('D' . $counter, number_format($reportRow['amount'], 2));
                    $counter++;
                }
                $grandqty += $qty;
                $servicegrandtotal += $serviceheadtotal;

                $activeSheet->setCellValue('A' . $counter, $reportpackagedata['name'])->getStyle('A' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('B' . $counter, 'Total')->getStyle('B' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('C' . $counter, number_format($qty))->getStyle('C' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('D' . $counter, number_format($serviceheadtotal, 2))->getStyle('D' . $counter)->getFont()->setBold(true);
                $counter++;
            }
            $activeSheet->setCellValue('A' . $counter, '');
            $activeSheet->setCellValue('B' . $counter, '');
            $activeSheet->setCellValue('C' . $counter, '');
            $activeSheet->setCellValue('D' . $counter, '');

            $counter++;
            $activeSheet->setCellValue('A' . $counter, '');
            $activeSheet->setCellValue('B' . $counter, 'Grand Total')->getStyle('B' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('C' . $counter, number_format($grandqty))->getStyle('C' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('D' . $counter, number_format($servicegrandtotal, 2))->getStyle('D' . $counter)->getFont()->setBold(true);

        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . 'Sales By Service Category' . '.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');

    }

    /**
     * Load Discount Report.
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function discountReport(Request $request)
    {

        if (!Gate::allows('finance_general_revenue_reports_discount_report')) {
            return abort(401);
        }

        if ($request->get('date_range')) {
            $date_range = explode(' - ', $request->get('date_range'));
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        $filters = array();

        $users = User::getAllRecords(Auth::User()->account_id)->pluck('name', 'id');
        $users->prepend('All', '');

        $reportData = \App\Reports\Invoices::getdiscountReport($request->all());

        $filters['reportData'] = $reportData;
        $filters['locations'] = Locations::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['services'] = Services::getAllRecordsDictionaryWithoutAll(Auth::User()->account_id);
        $filters['discounts'] = Discounts::getDiscountforreport(Auth::User()->account_id)->getDictionary();

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.discountreport.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.discountreport.reportprint', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $content = view('admin.reports.discountreport.reportpdf', compact('reportData', 'filters', 'start_date', 'end_date'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A3', 'landscape');
                return $pdf->stream('Accounts Sales Report', 'landscape');
                break;
            case 'excel':
                self::discountreportexcel($reportData, $filters, $start_date, $end_date);
                break;
            default:
                return view('admin.reports.discountreport.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * Discount Report
     * @param (mixed) $reportData
     * @param (mixed) $start_date
     * @param (mixed) $end_date
     *
     * @return \Illuminate\Http\Response
     */
    private static function discountreportexcel($reportData, $filters, $start_date, $end_date)
    {

        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From ' . $start_date . ' to ' . $end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', '');
        $activeSheet->setCellValue('B3', '');

        $activeSheet->setCellValue('A4', 'Invoice No.')->getStyle('A4')->getFont()->setBold(true);
        $activeSheet->setCellValue('B4', 'Centre')->getStyle('B4')->getFont()->setBold(true);
        $activeSheet->setCellValue('C4', 'Service')->getStyle('C4')->getFont()->setBold(true);
        $activeSheet->setCellValue('D4', 'Patient')->getStyle('D4')->getFont()->setBold(true);
        $activeSheet->setCellValue('E4', 'Created by')->getStyle('E4')->getFont()->setBold(true);
        $activeSheet->setCellValue('F4', 'Service Price')->getStyle('F4')->getFont()->setBold(true);
        $activeSheet->setCellValue('G4', 'Discount Name')->getStyle('G4')->getFont()->setBold(true);
        $activeSheet->setCellValue('H4', 'Discount Type')->getStyle('H4')->getFont()->setBold(true);
        $activeSheet->setCellValue('I4', 'Discount Amount')->getStyle('I4')->getFont()->setBold(true);
        $activeSheet->setCellValue('J4', 'Amount')->getStyle('J4')->getFont()->setBold(true);
        $activeSheet->setCellValue('K4', 'Tax')->getStyle('K4')->getFont()->setBold(true);
        $activeSheet->setCellValue('L4', 'Tax Value')->getStyle('L4')->getFont()->setBold(true);
        $activeSheet->setCellValue('M4', 'Total Amount')->getStyle('M4')->getFont()->setBold(true);
        $activeSheet->setCellValue('N4', 'Is Exclusive')->getStyle('N4')->getFont()->setBold(true);
        $activeSheet->setCellValue('O4', 'Payment Date')->getStyle('O4')->getFont()->setBold(true);

        $counter = 6;
        $grandserviceprice = 0;
        $totalAmount = 0;
        $totalTaxAmount = 0;


        foreach ($reportData as $reportRow) {

            $grandserviceprice += (array_key_exists($reportRow->service_id, $filters['services'])) ? $filters['services'][$reportRow->service_id]->price : 0;

            $totalAmount += $reportRow->tax_exclusive_serviceprice == null ? 0 : $reportRow->tax_exclusive_serviceprice;

            $totalTaxAmount += $reportRow->tax_including_price == null ? 0 : $reportRow->tax_including_price;


            $activeSheet->setCellValue('A' . $counter, $reportRow->id);
            $activeSheet->setCellValue('B' . $counter, (array_key_exists($reportRow->location_id, $filters['locations'])) ? $filters['locations'][$reportRow->location_id]->name : '-');
            $activeSheet->setCellValue('C' . $counter, (array_key_exists($reportRow->service_id, $filters['services'])) ? $filters['services'][$reportRow->service_id]->name : '-');
            $activeSheet->setCellValue('D' . $counter, $reportRow->patient->name);
            $activeSheet->setCellValue('E' . $counter, $reportRow->user->name);
            $activeSheet->setCellValue('F' . $counter, number_format((array_key_exists($reportRow->service_id, $filters['services'])) ? $filters['services'][$reportRow->service_id]->price : 0, 2));
            $activeSheet->setCellValue('G' . $counter, (array_key_exists($reportRow->discount_id, $filters['discounts'])) ? $filters['discounts'][$reportRow->discount_id]->name : '-');
            $activeSheet->setCellValue('H' . $counter, $reportRow->discount_type == null ? '-' : $reportRow->discount_type);
            $activeSheet->setCellValue('I' . $counter, number_format($reportRow->discount_price == null ? '0' : $reportRow->discount_price, 2));
            $activeSheet->setCellValue('J' . $counter, number_format($reportRow->tax_exclusive_serviceprice == null ? 0 : $reportRow->tax_exclusive_serviceprice, 2));
            $activeSheet->setCellValue('K' . $counter, $reportRow->tax_percenatage . '%');
            $activeSheet->setCellValue('L' . $counter, number_format($reportRow->tax_price == null ? 0 : $reportRow->tax_price, 2));
            $activeSheet->setCellValue('M' . $counter, number_format($reportRow->tax_including_price == null ? 0 : $reportRow->tax_including_price, 2));
            $activeSheet->setCellValue('N' . $counter, ($reportRow->is_exclusive) ? 'Yes' : 'No');
            $activeSheet->setCellValue('O' . $counter, ($reportRow->created_at) ? \Carbon\Carbon::parse($reportRow->created_at, null)->format('M j, Y') . ' at ' . \Carbon\Carbon::parse($reportRow->created_at, null)->format('h:i A') : '-');

            $counter++;
        }
        $activeSheet->setCellValue('A' . $counter, '');
        $counter++;

        $activeSheet->setCellValue('A' . $counter, 'Grand Total')->getStyle('A' . $counter)->getFont()->setBold(true);
        $activeSheet->setCellValue('F' . $counter, number_format($grandserviceprice, 2))->getStyle('F' . $counter)->getFont()->setBold(true);
        $activeSheet->setCellValue('J' . $counter, number_format($totalAmount, 2))->getStyle('J' . $counter)->getFont()->setBold(true);
        $activeSheet->setCellValue('M' . $counter, number_format($totalTaxAmount, 2))->getStyle('M' . $counter)->getFont()->setBold(true);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . 'Discount Report' . '.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');
    }

    /**
     * Load General Revenue Report.
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function generalrevenuereportdetail(Request $request)
    {
        //$request->location_id_com

        if(is_array($request->location_id_com) && count($request->location_id_com) > 1){
            $location[] = implode(',',$request->location_id_com);

        }else{
            $location = $request->location_id_com;
        }

        if (!Gate::allows('finance_general_revenue_reports_general_revenue__detail_report')) {
            return abort(401);
        }
        if ($request->get('date_range')) {
            $date_range = explode(' - ', $request->get('date_range'));
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        //$report_data = Finanaces::generalrevenuereportdetail($request->all(), Auth::User()->account_id);

        if ($request->medium_type == 'web' &&  $location && count($location) > 0) {

            $report_data = Finanaces::generalrevenuereportdetail($request->all(), Auth::User()->account_id);

        } else if ($request->medium_type != 'web' && $location) {

            $location_id_com = Explode_Multi_select::explode($location);
            $request->merge([
                'location_id_com' => $location_id_com,
            ]);
            $report_data = Finanaces::generalrevenuereportdetail($request->all(), Auth::User()->account_id);

        } else {
            $report_data = null;
        }

        $total_revenue_cash_in = 0;
        $total_revenue_card_in = 0;
        $total_revenue_bank_in = 0;

        $total_refund = 0;

        if ($report_data) {
            foreach ($report_data as $reportrevenue) {
                foreach ($reportrevenue['revenue_data'] as $revenue_data) {
                    if ($revenue_data['revenue_cash_in']) {
                        $total_revenue_cash_in += $revenue_data['revenue_cash_in'];
                    }
                    if ($revenue_data['revenue_card_in']) {
                        $total_revenue_card_in += $revenue_data['revenue_card_in'];
                    }
                    if ($revenue_data['revenue_bank_in']) {
                        $total_revenue_bank_in += $revenue_data['revenue_bank_in'];
                    }
                    if ($revenue_data['refund_out']) {
                        $total_refund += $revenue_data['refund_out'];
                    }
                }
            }
        }

        $total_revenue = $total_revenue_cash_in + $total_revenue_card_in + $total_revenue_bank_in;

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.generalrevenuereport.report', compact('report_data', 'total_revenue_cash_in', 'total_revenue_card_in', 'total_revenue_bank_in', 'total_refund', 'total_revenue', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.generalrevenuereport.reportprint', compact('report_data', 'total_revenue_cash_in', 'total_revenue_card_in', 'total_revenue_bank_in', 'total_refund', 'total_revenue', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $content = view('admin.reports.generalrevenuereport.reportpdf', compact('report_data', 'total_revenue_cash_in', 'total_revenue_card_in', 'total_revenue_bank_in', 'total_refund', 'total_revenue', 'start_date', 'end_date'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A3', 'landscape');
                return $pdf->stream('General Revenue Report', 'landscape');
                break;
            case 'excel':

                self::GeneralRevenueReportExcel($report_data, $total_revenue_cash_in, $total_revenue_card_in, $total_revenue_bank_in, $total_refund, $total_revenue, $start_date, $end_date);
                break;
            default:
                return view('admin.reports.generalrevenuereport.report', compact('report_data', 'total_revenue_cash_in', 'total_revenue_card_in', 'total_revenue_bank_in', 'total_refund', 'total_revenue', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * General Revnue Report Excel
     * @param (mixed) $reportData
     * @param (mixed) $start_date
     * @param (mixed) $end_date
     *
     * @return \Illuminate\Http\Response
     */
    private static function GeneralRevenueReportExcel($report_data, $total_revenue_cash_in, $total_revenue_card_in, $total_revenue_bank_in, $total_refund, $total_revenue, $start_date, $end_date)
    {

        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From ' . $start_date . ' to ' . $end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', '');

        $activeSheet->setCellValue('A4', 'ID')->getStyle('A4')->getFont()->setBold(true);
        $activeSheet->setCellValue('B4', 'Patient Name')->getStyle('B4')->getFont()->setBold(true);
        $activeSheet->setCellValue('C4', 'Transaction Type')->getStyle('C4')->getFont()->setBold(true);
        $activeSheet->setCellValue('D4', 'Revenue Cash In')->getStyle('D4')->getFont()->setBold(true);
        $activeSheet->setCellValue('E4', 'Revenue Card In')->getStyle('E4')->getFont()->setBold(true);
        $activeSheet->setCellValue('F4', 'Revenue Bank/Wire In')->getStyle('F4')->getFont()->setBold(true);
        $activeSheet->setCellValue('G4', 'Refund/Out')->getStyle('G4')->getFont()->setBold(true);
        $activeSheet->setCellValue('H4', 'Created At')->getStyle('H4')->getFont()->setBold(true);

        $activeSheet->setCellValue('A5', '');

        $total_revenue_cash_location = 0;
        $total_revenue_card_location = 0;
        $total_revenue_bank_location = 0;
        $total_refund_location = 0;


        $counter = 6;
        if ($report_data) {
            foreach ($report_data as $reportlocation) {

                $activeSheet->setCellValue('A' . $counter, $reportlocation['name'])->getStyle('A' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('B' . $counter, $reportlocation['city'])->getStyle('B' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('C' . $counter, $reportlocation['region'])->getStyle('C' . $counter)->getFont()->setBold(true);
                $counter++;

                $activeSheet->setCellValue('A' . $counter, '');
                $counter++;

                foreach ($reportlocation['revenue_data'] as $reportRow) {

                    $total_revenue_cash_location += $reportRow['revenue_cash_in']?$reportRow['revenue_cash_in']:0;
                    $total_revenue_card_location += $reportRow['revenue_card_in']?$reportRow['revenue_card_in']:0;
                    $total_revenue_bank_location += $reportRow['revenue_bank_in']?$reportRow['revenue_bank_in']:0;
                    $total_refund_location += $reportRow['refund_out']?$reportRow['refund_out']:0;

                    $activeSheet->setCellValue('A' . $counter, $reportRow['patient_id']);
                    $activeSheet->setCellValue('B' . $counter, $reportRow['patient']);
                    $activeSheet->setCellValue('C' . $counter, $reportRow['transtype']);
                    if ($reportRow['revenue_cash_in']) {
                        $activeSheet->setCellValue('D' . $counter, number_format($reportRow['revenue_cash_in'], 2));
                    }
                    if ($reportRow['revenue_card_in']) {
                        $activeSheet->setCellValue('E' . $counter, number_format($reportRow['revenue_card_in'], 2));
                    }
                    if ($reportRow['revenue_bank_in']) {
                        $activeSheet->setCellValue('F' . $counter, number_format($reportRow['revenue_bank_in'], 2));
                    }
                    if ($reportRow['refund_out']) {
                        $activeSheet->setCellValue('G' . $counter, number_format($reportRow['refund_out'], 2));
                    }
                    $activeSheet->setCellValue('H' . $counter, $reportRow['created_at']);
                    $counter++;
                }
                $activeSheet->setCellValue('A' . $counter, '');
                $counter++;

                $activeSheet->setCellValue('A' . $counter, $reportlocation['name'])->getStyle('A' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('B' . $counter, 'Total')->getStyle('B' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('D' . $counter, number_format($total_revenue_cash_location, 2))->getStyle('D' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('E' . $counter, number_format($total_revenue_card_location, 2))->getStyle('E' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('F' . $counter, number_format($total_revenue_bank_location, 2))->getStyle('F' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('G' . $counter, number_format($total_refund_location, 2))->getStyle('G' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('H' . $counter, number_format(($total_revenue_cash_location+$total_revenue_card_location+$total_revenue_bank_location)-$total_refund_location,2))->getStyle('H' . $counter)->getFont()->setBold(true);

                $counter++;

                $total_revenue_cash_location = 0;
                $total_revenue_card_location = 0;
                $total_revenue_bank_location = 0;
                $total_refund_location = 0;

            }
            $activeSheet->setCellValue('A' . $counter, '');
            $counter++;

            $activeSheet->setCellValue('A' . $counter, 'Revenue Cash In')->getStyle('A' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('B' . $counter, number_format($total_revenue_cash_in, 2));
            $counter++;

            $activeSheet->setCellValue('A' . $counter, 'Revenue Card In')->getStyle('A' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('B' . $counter, number_format($total_revenue_card_in, 2));
            $counter++;

            $activeSheet->setCellValue('A' . $counter, 'Revenue Bank/Wire In')->getStyle('A' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('B' . $counter, number_format($total_revenue_bank_in, 2));
            $counter++;

            $activeSheet->setCellValue('A' . $counter, 'Total Revenue')->getStyle('A' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('B' . $counter, number_format($total_revenue, 2));
            $counter++;

            $activeSheet->setCellValue('A' . $counter, 'Refund')->getStyle('A' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('B' . $counter, number_format($total_refund, 2));
            $counter++;

            $activeSheet->setCellValue('A' . $counter, 'In Hand Balance')->getStyle('A' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('B' . $counter, number_format(($total_revenue - $total_refund), 2));
            $counter++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . 'GeneralRevenueReport' . '.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');

    }

    /**
     * Load General Revenue Report.
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function generalrevenuereportsummary(Request $request)
    {
        if (!Gate::allows('finance_general_revenue_reports_general_revenue__summary_report')) {
            return abort(401);
        }
        if ($request->get('date_range')) {
            $date_range = explode(' - ', $request->get('date_range'));
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        $report_data = Finanaces::generalrevenuereportsummary($request->all(), Auth::User()->account_id);

        $total_revenue_cash_in = 0;
        $total_revenue_card_in = 0;
        $total_revenue_bank_in = 0;
        $total_refund = 0;

        if ($report_data) {
            foreach ($report_data as $reportrevenue) {
                if ($reportrevenue['revenue_cash_in']) {
                    $total_revenue_cash_in += $reportrevenue['revenue_cash_in'];
                }
                if ($reportrevenue['revenue_card_in']) {
                    $total_revenue_card_in += $reportrevenue['revenue_card_in'];
                }
                if ($reportrevenue['revenue_bank_in']) {
                    $total_revenue_bank_in += $reportrevenue['revenue_bank_in'];
                }
                if ($reportrevenue['refund_out']) {
                    $total_refund += $reportrevenue['refund_out'];
                }
            }
        }
        $total_revenue = $total_revenue_cash_in + $total_revenue_card_in + $total_revenue_bank_in;

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.generalrevenuesummaryreport.report', compact('report_data', 'total_revenue_cash_in', 'total_revenue_card_in', 'total_revenue_bank_in', 'total_refund', 'total_revenue', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.generalrevenuesummaryreport.reportprint', compact('report_data', 'total_revenue_cash_in', 'total_revenue_card_in', 'total_revenue_bank_in', 'total_refund', 'total_revenue', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $content = view('admin.reports.generalrevenuesummaryreport.reportpdf', compact('report_data', 'total_revenue_cash_in', 'total_revenue_card_in', 'total_revenue_bank_in', 'total_refund', 'total_revenue', 'start_date', 'end_date'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A4', 'landscape');
                return $pdf->stream('General Revenue Report', 'landscape');
                break;
            case 'excel':
                self::GeneralRevenueSummaryReportExcel($report_data, $total_revenue_cash_in, $total_revenue_card_in, $total_revenue_bank_in, $total_refund, $total_revenue, $start_date, $end_date);
                break;
            default:
                return view('admin.reports.generalrevenuesummaryreport.report', compact('report_data', 'total_revenue_cash_in', 'total_revenue_card_in', 'total_revenue_bank_in', 'total_refund', 'total_revenue', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * General Revnue Report Excel
     * @param (mixed) $reportData
     * @param (mixed) $start_date
     * @param (mixed) $end_date
     *
     * @return \Illuminate\Http\Response
     */
    private static function GeneralRevenueSummaryReportExcel($report_data, $total_revenue_cash_in, $total_revenue_card_in, $total_revenue_bank_in, $total_refund, $total_revenue, $start_date, $end_date)
    {

        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From ' . $start_date . ' to ' . $end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', '');

        $activeSheet->setCellValue('A4', 'Centre')->getStyle('A4')->getFont()->setBold(true);
        $activeSheet->setCellValue('B4', 'City')->getStyle('B4')->getFont()->setBold(true);
        $activeSheet->setCellValue('C4', 'Region')->getStyle('C4')->getFont()->setBold(true);
        $activeSheet->setCellValue('D4', 'Revenue Cash In')->getStyle('D4')->getFont()->setBold(true);
        $activeSheet->setCellValue('E4', 'Revenue Card In')->getStyle('E4')->getFont()->setBold(true);
        $activeSheet->setCellValue('F4', 'Revenue Bank/Wire In')->getStyle('F4')->getFont()->setBold(true);
        $activeSheet->setCellValue('G4', 'Refund/Out')->getStyle('G4')->getFont()->setBold(true);
        $activeSheet->setCellValue('H4', 'In Hand')->getStyle('H4')->getFont()->setBold(true);

        $activeSheet->setCellValue('A5', '');

        $counter = 6;
        if ($report_data) {
            foreach ($report_data as $reportRow) {

                $activeSheet->setCellValue('A' . $counter, $reportRow['name']);
                $activeSheet->setCellValue('B' . $counter, $reportRow['city']);
                $activeSheet->setCellValue('C' . $counter, $reportRow['region']);
                $activeSheet->setCellValue('D' . $counter, number_format($reportRow['revenue_cash_in'], 2));
                $activeSheet->setCellValue('E' . $counter, number_format($reportRow['revenue_card_in'], 2));
                $activeSheet->setCellValue('F' . $counter, number_format($reportRow['revenue_bank_in'], 2));
                $activeSheet->setCellValue('G' . $counter, number_format($reportRow['refund_out'], 2));
                $activeSheet->setCellValue('H' . $counter, number_format($reportRow['in_hand'], 2));
                $counter++;
            }
            $activeSheet->setCellValue('A' . $counter, '');
            $counter++;

            $activeSheet->setCellValue('A' . $counter, 'Total')->getStyle('A' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('D' . $counter, number_format($total_revenue_cash_in, 2))->getStyle('D' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('E' . $counter, number_format($total_revenue_card_in, 2))->getStyle('E' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('F' . $counter, number_format($total_revenue_bank_in, 2))->getStyle('F' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('G' . $counter, number_format($total_refund, 2))->getStyle('G' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('H' . $counter, number_format(($total_revenue_cash_in + $total_revenue_card_in + $total_revenue_bank_in) - $total_refund, 2))->getStyle('H' . $counter)->getFont()->setBold(true);

            $counter++;

            $activeSheet->setCellValue('A' . $counter, '');
            $counter++;

            $activeSheet->setCellValue('A' . $counter, 'Revenue Cash In')->getStyle('A' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('B' . $counter, number_format($total_revenue_cash_in, 2));
            $counter++;

            $activeSheet->setCellValue('A' . $counter, 'Revenue Card In')->getStyle('A' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('B' . $counter, number_format($total_revenue_card_in, 2));
            $counter++;

            $activeSheet->setCellValue('A' . $counter, 'Revenue Bank/Wire In')->getStyle('A' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('B' . $counter, number_format($total_revenue_bank_in, 2));
            $counter++;

            $activeSheet->setCellValue('A' . $counter, 'Total Revenue')->getStyle('A' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('B' . $counter, number_format($total_revenue, 2));
            $counter++;

            $activeSheet->setCellValue('A' . $counter, 'Refund')->getStyle('A' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('B' . $counter, number_format($total_refund, 2));
            $counter++;

            $activeSheet->setCellValue('A' . $counter, 'In Hand Balance')->getStyle('A' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('B' . $counter, number_format(($total_revenue - $total_refund), 2));
            $counter++;
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . 'GeneralRevenueReport' . '.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');

    }

    /**
     * Pabau Record Revenue Report.
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function pabaurecordrevenuereport(Request $request)
    {
        if (!Gate::allows('finance_general_revenue_reports_pabau_record_revenue_report')) {
            return abort(401);
        }
        if ($request->get('date_range')) {
            $date_range = explode(' - ', $request->get('date_range'));
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        $reportData = Finanaces::pabaurecordrevenuereport($request->all(), Auth::User()->account_id);

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.pabaurevenuerecordreport.report', compact('reportData', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.pabaurevenuerecordreport.reportprint', compact('reportData', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $content = view('admin.reports.pabaurevenuerecordreport.reportpdf', compact('reportData', 'start_date', 'end_date'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A3', 'landscape');
                return $pdf->stream('General Revenue Report', 'landscape');
                break;
            case 'excel':
                self::pabaurevenuerecordreportExcel($reportData, $start_date, $end_date);
                break;
            default:
                return view('admin.reports.generalrevenuesummaryreport.report', compact('report_data', 'total_revenue_cash_in', 'total_revenue_card_in', 'total_refund', 'total_revenue', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * Pabau Record Revenue Report Excel
     * @param (mixed) $reportData
     * @param (mixed) $start_date
     * @param (mixed) $end_date
     *
     * @return \Illuminate\Http\Response
     */
    private static function pabaurevenuerecordreportExcel($reportData, $start_date, $end_date)
    {

        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From ' . $start_date . ' to ' . $end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', 'Center')->getStyle('A3')->getFont()->setBold(true);
        $activeSheet->setCellValue('B3', 'Region')->getStyle('B3')->getFont()->setBold(true);
        $activeSheet->setCellValue('C3', 'City')->getStyle('C3')->getFont()->setBold(true);
        $activeSheet->setCellValue('D3', 'Client')->getStyle('D3')->getFont()->setBold(true);
//        $activeSheet->setCellValue('E3', 'Phone')->getStyle('E3')->getFont()->setBold(true);
        $activeSheet->setCellValue('E3', 'Invoice No.')->getStyle('E3')->getFont()->setBold(true);
        $activeSheet->setCellValue('F3', 'Issue Date')->getStyle('F3')->getFont()->setBold(true);
        $activeSheet->setCellValue('G3', 'Total Amount')->getStyle('G3')->getFont()->setBold(true);
        $activeSheet->setCellValue('H3', 'Paid Amount')->getStyle('H3')->getFont()->setBold(true);
        $activeSheet->setCellValue('I3', 'Outstanding Amount')->getStyle('I3')->getFont()->setBold(true);
        $activeSheet->setCellValue('J3', 'Amount')->getStyle('J3')->getFont()->setBold(true);
        $activeSheet->setCellValue('K3', 'Date')->getStyle('K3')->getFont()->setBold(true);

        $activeSheet->setCellValue('A4', '');

        $counter = 5;

        if (count($reportData)) {
            $grantotal = 0;
            foreach ($reportData as $reportlocation) {

                $activeSheet->setCellValue('A' . $counter, $reportlocation['name'])->getStyle('A' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('B' . $counter, $reportlocation['region'])->getStyle('B' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('C' . $counter, $reportlocation['city'])->getStyle('C' . $counter)->getFont()->setBold(true);

                $counter++;

                $centotal = 0;
                foreach ($reportlocation['pabau_rocord'] as $reportuser) {

                    $activeSheet->setCellValue('D' . $counter, $reportuser['name']);
//                    $activeSheet->setCellValue('E' . $counter, $reportuser['phone']);
                    $activeSheet->setCellValue('E' . $counter, $reportuser['invoice_no']);
                    $activeSheet->setCellValue('F' . $counter, \Carbon\Carbon::parse($reportuser['issue_date'])->format('M j, Y H:i A'));
                    $activeSheet->setCellValue('G' . $counter, number_format($reportuser['total_amount']));
                    $activeSheet->setCellValue('H' . $counter, number_format($reportuser['paid_amount']));
                    $activeSheet->setCellValue('I' . $counter, number_format($reportuser['outstanding_amount']));
                    $counter++;
                    $activeSheet->setCellValue('A' . $counter, '');
                    $counter++;

                    $sumtotal = 0;
                    foreach ($reportuser['pabau_record_payment'] as $paymentrecord) {

                        $activeSheet->setCellValue('J' . $counter, number_format($paymentrecord['amount']));
                        $activeSheet->setCellValue('K' . $counter, \Carbon\Carbon::parse($paymentrecord['Date'])->format('M j, Y H:i A'));
                        $counter++;

                        $sumtotal += $paymentrecord['amount'];
                        $centotal += $paymentrecord['amount'];
                        $grantotal += $paymentrecord['amount'];

                    }
                    $activeSheet->setCellValue('A' . $counter, '');
                    $counter++;

                    $activeSheet->setCellValue('J' . $counter, 'Total')->getStyle('J' . $counter)->getFont()->setBold(true);
                    $activeSheet->setCellValue('K' . $counter, number_format($sumtotal))->getStyle('K' . $counter)->getFont()->setBold(true);
                    $counter++;

                    $activeSheet->setCellValue('A' . $counter, '');
                    $counter++;
                }
                $activeSheet->setCellValue('C' . $counter, 'Total')->getStyle('C' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('K' . $counter, number_format($centotal))->getStyle('K' . $counter)->getFont()->setBold(true);
                $counter++;

                $activeSheet->setCellValue('A' . $counter, '');
                $counter++;

            }

            $activeSheet->setCellValue('A' . $counter, 'Grand Total')->getStyle('A' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('K' . $counter, number_format($grantotal))->getStyle('K' . $counter)->getFont()->setBold(true);
            $counter++;
        }
        $activeSheet->setCellValue('A' . $counter, '');
        $counter++;

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . 'pabauRecordRevenuereport' . '.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');
    }

    /**
     * Machine wise Invoice Revene.
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function machinewiseinvoicerevenuereport(Request $request)
    {
        if (!Gate::allows('finance_general_revenue_reports_machine_wise_invoice_revenue_report')) {
            return abort(401);
        }
        if ($request->get('date_range')) {
            $date_range = explode(' - ', $request->get('date_range'));
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        $reportData = Finanaces::machinewiseinvoicerevenuereport($request->all(), Auth::User()->account_id);

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.machinewiseinvoicerevenuereport.report', compact('reportData', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.machinewiseinvoicerevenuereport.reportprint', compact('reportData', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $content = view('admin.reports.machinewiseinvoicerevenuereport.reportpdf', compact('reportData', 'start_date', 'end_date'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A3', 'landscape');
                return $pdf->stream('Machine Wise Invoice Revenue Report', 'landscape');
                break;
            case 'excel':
                self::machinewiseinvoicerevenuereportExcel($reportData, $start_date, $end_date);
                break;
            default:
                return view('admin.reports.machinewiseinvoicerevenuereport.report', compact('report_data', 'total_revenue_cash_in', 'total_revenue_card_in', 'total_refund', 'total_revenue', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * Machine Wise Invoice Revenue Report Excel
     * @param (mixed) $reportData
     * @param (mixed) $start_date
     * @param (mixed) $end_date
     *
     * @return \Illuminate\Http\Response
     */
    private static function machinewiseinvoicerevenuereportExcel($reportData, $start_date, $end_date)
    {

        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From ' . $start_date . ' to ' . $end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', 'Center')->getStyle('A3')->getFont()->setBold(true);
        $activeSheet->setCellValue('B3', 'Region')->getStyle('B3')->getFont()->setBold(true);
        $activeSheet->setCellValue('C3', 'City')->getStyle('C3')->getFont()->setBold(true);
        $activeSheet->setCellValue('D3', 'Machine')->getStyle('D3')->getFont()->setBold(true);
        $activeSheet->setCellValue('E3', 'Client')->getStyle('E3')->getFont()->setBold(true);
        $activeSheet->setCellValue('F3', 'Service Price')->getStyle('F3')->getFont()->setBold(true);
        $activeSheet->setCellValue('G3', 'Discount Name')->getStyle('G3')->getFont()->setBold(true);
        $activeSheet->setCellValue('H3', 'Discount Type')->getStyle('H3')->getFont()->setBold(true);
        $activeSheet->setCellValue('I3', 'Discount Price')->getStyle('I3')->getFont()->setBold(true);
        $activeSheet->setCellValue('J3', 'Amount')->getStyle('J3')->getFont()->setBold(true);
        $activeSheet->setCellValue('K3', 'Tax Value')->getStyle('K3')->getFont()->setBold(true);
        $activeSheet->setCellValue('L3', 'Net Amount')->getStyle('L3')->getFont()->setBold(true);
        $activeSheet->setCellValue('M3', 'Created At')->getStyle('M3')->getFont()->setBold(true);
        $activeSheet->setCellValue('N3', 'Is Exclusive')->getStyle('M3')->getFont()->setBold(true);

        $activeSheet->setCellValue('A4', '');

        $counter = 5;

        if (count($reportData)) {
            $grantotal = 0;
            foreach ($reportData as $reportlocation) {

                $activeSheet->setCellValue('A' . $counter, $reportlocation['name'])->getStyle('A' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('B' . $counter, $reportlocation['region'])->getStyle('B' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('C' . $counter, $reportlocation['city'])->getStyle('C' . $counter)->getFont()->setBold(true);

                $counter++;

                $centotal = 0;
                foreach ($reportlocation['machine'] as $reportmachine) {

                    $activeSheet->setCellValue('D' . $counter, $reportmachine['name']);
                    $counter++;
                    $activeSheet->setCellValue('A' . $counter, '');
                    $counter++;

                    $machinetotal = 0;
                    foreach ($reportmachine['machine_array'] as $paymentrecord) {

                        $activeSheet->setCellValue('E' . $counter, $paymentrecord['client']);
                        $activeSheet->setCellValue('F' . $counter, number_format($paymentrecord['service_price'], 2));
                        $activeSheet->setCellValue('G' . $counter, $paymentrecord['discount_name']);
                        $activeSheet->setCellValue('H' . $counter, $paymentrecord['discount_type']);
                        $activeSheet->setCellValue('I' . $counter, number_format($paymentrecord['discount_price'], 2));
                        $activeSheet->setCellValue('J' . $counter, number_format($paymentrecord['amount'], 2));
                        $activeSheet->setCellValue('K' . $counter, number_format($paymentrecord['tax_value'], 2));
                        $activeSheet->setCellValue('L' . $counter, number_format($paymentrecord['net_amount'], 2));
                        $activeSheet->setCellValue('M' . $counter, \Carbon\Carbon::parse($paymentrecord['created_at'])->format('M j, Y H:i A'));
                        $activeSheet->setCellValue('N' . $counter, $paymentrecord['is_exclusive'] ? 'Yes' : 'NO');
                        $counter++;

                        $machinetotal += $paymentrecord['net_amount'];
                        $centotal += $paymentrecord['net_amount'];
                        $grantotal += $paymentrecord['net_amount'];

                    }
                    $activeSheet->setCellValue('A' . $counter, '');
                    $counter++;

                    $activeSheet->setCellValue('D' . $counter, 'Total')->getStyle('D' . $counter)->getFont()->setBold(true);
                    $activeSheet->setCellValue('L' . $counter, number_format($machinetotal))->getStyle('L' . $counter)->getFont()->setBold(true);
                    $counter++;

                    $activeSheet->setCellValue('A' . $counter, '');
                    $counter++;
                }
                $activeSheet->setCellValue('A' . $counter, 'Total')->getStyle('A' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('L' . $counter, number_format($centotal))->getStyle('L' . $counter)->getFont()->setBold(true);
                $counter++;

                $activeSheet->setCellValue('A' . $counter, '');
                $counter++;

            }

            $activeSheet->setCellValue('A' . $counter, 'Grand Total')->getStyle('A' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('L' . $counter, number_format($grantotal))->getStyle('L' . $counter)->getFont()->setBold(true);
            $counter++;
        }
        $activeSheet->setCellValue('A' . $counter, '');
        $counter++;

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . 'machinewiseinvoicerevenuereport' . '.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');
    }

    /**
     * Patner Collection Report.
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function partnercollectionreport(Request $request)
    {
        if (!Gate::allows('finance_general_revenue_reports_partner_collection_report')) {
            return abort(401);
        }
        if ($request->get('date_range')) {
            $date_range = explode(' - ', $request->get('date_range'));
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        $reportData = Finanaces::partnercollectionreport($request->all(), Auth::User()->account_id);
        $count = 0;
        if (isset($request->machine_id) && $request->machine_id) {
            foreach ($reportData as $key => $reportlocation) {
                foreach ($reportlocation['machine'] as $reportmachine) {
                    if ($reportmachine['id'] == $request->machine_id) {
                    } else {
                        unset($reportData[$key]['machine'][$reportmachine['id']]);
                        $count++;
                    }
                }
            }
        }
        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.partnercollectionreport.report', compact('reportData', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.partnercollectionreport.reportprint', compact('reportData', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $content = view('admin.reports.partnercollectionreport.reportpdf', compact('reportData', 'start_date', 'end_date'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A3', 'landscape');
                return $pdf->stream('PatnerCollectionReport', 'landscape');
                break;
            case 'excel':
                self::partnercollectionreportExcel($reportData, $start_date, $end_date);
                break;
            default:
                return view('admin.reports.partnercollectionreport.report', compact('reportData', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * Machine Wise Invoice Revenue Report Excel
     * @param (mixed) $reportData
     * @param (mixed) $start_date
     * @param (mixed) $end_date
     *
     * @return \Illuminate\Http\Response
     */
    private static function partnercollectionreportExcel($reportData, $start_date, $end_date)
    {

        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From ' . $start_date . ' to ' . $end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', 'Center')->getStyle('A3')->getFont()->setBold(true);
        $activeSheet->setCellValue('B3', 'Region')->getStyle('B3')->getFont()->setBold(true);
        $activeSheet->setCellValue('C3', 'City')->getStyle('C3')->getFont()->setBold(true);
        $activeSheet->setCellValue('D3', 'Machine')->getStyle('D3')->getFont()->setBold(true);
        $activeSheet->setCellValue('E3', 'Client')->getStyle('E3')->getFont()->setBold(true);
        $activeSheet->setCellValue('F3', 'Cash Flow')->getStyle('F3')->getFont()->setBold(true);
        $activeSheet->setCellValue('G3', 'Amount')->getStyle('G3')->getFont()->setBold(true);
        $activeSheet->setCellValue('H3', 'Tax')->getStyle('H3')->getFont()->setBold(true);
        $activeSheet->setCellValue('I3', 'Net Amount')->getStyle('I3')->getFont()->setBold(true);
        $activeSheet->setCellValue('J3', 'Refund/Cash Out')->getStyle('J3')->getFont()->setBold(true);
        $activeSheet->setCellValue('K3', 'Balance')->getStyle('K3')->getFont()->setBold(true);

        $activeSheet->setCellValue('A4', '');

        $counter = 5;

        if (count($reportData)) {
            $machineamount_in_g = 0;
            $machinetax_in_g = 0;
            $machinenet_in_g = 0;
            $machinetotal_out_g = 0;
            foreach ($reportData as $reportlocation) {
                $activeSheet->setCellValue('A' . $counter, $reportlocation['name'])->getStyle('A' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('B' . $counter, $reportlocation['region'])->getStyle('B' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('C' . $counter, $reportlocation['city'])->getStyle('C' . $counter)->getFont()->setBold(true);
                $counter++;
                $machineamount_in_t = 0;
                $machinetax_in_t = 0;
                $machinenet_in_t = 0;
                $machinetotal_out_t = 0;
                foreach ($reportlocation['machine'] as $reportmachine) {
                    $activeSheet->setCellValue('D' . $counter, $reportmachine['name'])->getStyle('D' . $counter)->getFont()->setBold(true);
                    $counter++;
                    $machineamount_in = 0;
                    $machinetax_in = 0;
                    $machinenet_in = 0;
                    $machinetotal_out = 0;
                    foreach ($reportmachine['transaction'] as $paymentrecord) {
                        $activeSheet->setCellValue('E' . $counter, $paymentrecord['name']);
                        $activeSheet->setCellValue('F' . $counter, $paymentrecord['flow']);
                        $activeSheet->setCellValue('G' . $counter, $paymentrecord['amount'] ? number_format($paymentrecord['amount'], 2) : '');
                        $activeSheet->setCellValue('H' . $counter, $paymentrecord['tax'] ? number_format($paymentrecord['tax'], 2) : '');
                        $activeSheet->setCellValue('I' . $counter, $paymentrecord['net_amount'] ? number_format($paymentrecord['net_amount'], 2) : '');
                        $activeSheet->setCellValue('J' . $counter, $paymentrecord['amount_out'] ? number_format($paymentrecord['amount_out'], 2) : '');

                        $machineamount_in += $paymentrecord['amount'] ? $paymentrecord['amount'] : 0;
                        $machinetax_in += $paymentrecord['tax'] ? $paymentrecord['tax'] : 0;
                        $machinenet_in += $paymentrecord['net_amount'] ? $paymentrecord['net_amount'] : 0;
                        $machinetotal_out += $paymentrecord['amount_out'] ? $paymentrecord['amount_out'] : 0;

                        $machineamount_in_t += $paymentrecord['amount'] ? $paymentrecord['amount'] : 0;
                        $machinetax_in_t += $paymentrecord['tax'] ? $paymentrecord['tax'] : 0;
                        $machinenet_in_t += $paymentrecord['net_amount'] ? $paymentrecord['net_amount'] : 0;
                        $machinetotal_out_t += $paymentrecord['amount_out'] ? $paymentrecord['amount_out'] : 0;

                        $machineamount_in_g += $paymentrecord['amount'] ? $paymentrecord['amount'] : 0;
                        $machinetax_in_g += $paymentrecord['tax'] ? $paymentrecord['tax'] : 0;
                        $machinenet_in_g += $paymentrecord['net_amount'] ? $paymentrecord['net_amount'] : 0;
                        $machinetotal_out_g += $paymentrecord['amount_out'] ? $paymentrecord['amount_out'] : 0;

                        $counter++;
                    }
                    $activeSheet->setCellValue('D' . $counter, 'Total')->getStyle('D' . $counter)->getFont()->setBold(true);

                    $activeSheet->setCellValue('G' . $counter, number_format($machineamount_in, 2))->getStyle('G' . $counter)->getFont()->setBold(true);
                    $activeSheet->setCellValue('H' . $counter, number_format($machinetax_in, 2))->getStyle('H' . $counter)->getFont()->setBold(true);

                    $activeSheet->setCellValue('I' . $counter, number_format($machinenet_in, 2))->getStyle('I' . $counter)->getFont()->setBold(true);
                    $activeSheet->setCellValue('J' . $counter, number_format($machinetotal_out, 2))->getStyle('J' . $counter)->getFont()->setBold(true);
                    $activeSheet->setCellValue('K' . $counter, number_format($machineamount_in - $machinetotal_out, 2))->getStyle('K' . $counter)->getFont()->setBold(true);
                    $counter++;

                    $activeSheet->setCellValue('A' . $counter, '');
                    $counter++;
                }
                $activeSheet->setCellValue('A' . $counter, 'Total')->getStyle('A' . $counter)->getFont()->setBold(true);

                $activeSheet->setCellValue('G' . $counter, number_format($machineamount_in_t, 2))->getStyle('G' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('H' . $counter, number_format($machinetax_in_t, 2))->getStyle('H' . $counter)->getFont()->setBold(true);

                $activeSheet->setCellValue('I' . $counter, number_format($machinenet_in_t, 2))->getStyle('I' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('J' . $counter, number_format($machinetotal_out_t, 2))->getStyle('J' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('K' . $counter, number_format($machineamount_in_t - $machinetotal_out_t, 2))->getStyle('K' . $counter)->getFont()->setBold(true);
                $counter++;
            }

            $activeSheet->setCellValue('A' . $counter, 'Grand Total')->getStyle('A' . $counter)->getFont()->setBold(true);

            $activeSheet->setCellValue('G' . $counter, number_format($machineamount_in_g, 2))->getStyle('G' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('H' . $counter, number_format($machinetax_in_g, 2))->getStyle('H' . $counter)->getFont()->setBold(true);

            $activeSheet->setCellValue('I' . $counter, number_format($machinenet_in_g, 2))->getStyle('I' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('J' . $counter, number_format($machinetotal_out_g, 2))->getStyle('J' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('K' . $counter, number_format($machineamount_in_g - $machinetotal_out_g, 2))->getStyle('K' . $counter)->getFont()->setBold(true);
            $counter++;
        }
        $activeSheet->setCellValue('A' . $counter, '');
        $counter++;

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . 'partnercollectionreport' . '.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');
    }

    /**
     * Staff wise Report.
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function staffwiserevenue(Request $request)
    {

        if (!Gate::allows('finance_general_revenue_reports_staff_wise_revenue')) {
            return abort(401);
        }
        if ($request->get('date_range')) {
            $date_range = explode(' - ', $request->get('date_range'));
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        $report_data = Finanaces::staffwiserevenue($request->all(), Auth::User()->account_id);

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.staffwiserevenue.report', compact('report_data', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.staffwiserevenue.reportprint', compact('report_data', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $pdf = PDF::loadView('admin.reports.staffwiserevenue.reportpdf', compact('report_data', 'start_date', 'end_date'));
                $pdf->setPaper('A3', 'landscape');
                return $pdf->stream('General Revenue Report', 'landscape');
                break;
            case 'excel':
                self::staffwiserevenuereportexcel($report_data, $start_date, $end_date);
                break;
            default:
                return view('admin.reports.staffwiserevenue.report', compact('report_data', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * Staff wise Revenue report
     * @param (mixed) $reportData
     * @param (mixed) $start_date
     * @param (mixed) $end_date
     *
     * @return \Illuminate\Http\Response
     */
    private static function staffwiserevenuereportexcel($reportData, $start_date, $end_date)
    {

        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From ' . $start_date . ' to ' . $end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', 'Center')->getStyle('A3')->getFont()->setBold(true);
        $activeSheet->setCellValue('B3', 'City')->getStyle('B3')->getFont()->setBold(true);
        $activeSheet->setCellValue('C3', 'Region')->getStyle('C3')->getFont()->setBold(true);
        $activeSheet->setCellValue('D3', 'Doctor')->getStyle('D3')->getFont()->setBold(true);
        $activeSheet->setCellValue('E3', 'Created At')->getStyle('E3')->getFont()->setBold(true);
        $activeSheet->setCellValue('F3', 'Revenue In')->getStyle('F3')->getFont()->setBold(true);
        $activeSheet->setCellValue('G3', 'Refund/Out')->getStyle('G3')->getFont()->setBold(true);
        $activeSheet->setCellValue('H3', 'In Hand Revenue')->getStyle('H3')->getFont()->setBold(true);

        $activeSheet->setCellValue('A4', '');

        $counter = 5;

        if (count($reportData)) {

            $grandtotal = 0;

            foreach ($reportData as $reportlocation) {

                $activeSheet->setCellValue('A' . $counter, $reportlocation['centre']);
                $activeSheet->setCellValue('B' . $counter, $reportlocation['city']);
                $activeSheet->setCellValue('C' . $counter, $reportlocation['region']);

                $counter++;

                $centre_revenue_total = 0;
                $centre_refund_total = 0;
                $centre_total = 0;

                foreach ($reportlocation['doctor_info'] as $reportdoctor) {

                    $activeSheet->setCellValue('D' . $counter, $reportdoctor['doctor']);
                    $counter++;

                    $doctor_revenue_total = 0;
                    $doctor_refund_total = 0;
                    $doctor_total = 0;

                    foreach ($reportdoctor['doctor_revenue'] as $reportrevenue) {

                        $doctor_revenue_total += $reportrevenue['revenue'] ? $reportrevenue['revenue'] : 0;
                        $doctor_refund_total += $reportrevenue['refund_out'] ? $reportrevenue['refund_out'] : 0;
                        $centre_revenue_total += $reportrevenue['revenue'] ? $reportrevenue['revenue'] : 0;
                        $centre_refund_total += $reportrevenue['refund_out'] ? $reportrevenue['refund_out'] : 0;

                        $activeSheet->setCellValue('E' . $counter, $reportrevenue['created_at'] ? \Carbon\Carbon::parse($reportrevenue['created_at'], null)->format('M j, Y') : '');
                        $activeSheet->setCellValue('F' . $counter, $reportrevenue['revenue'] ? number_format($reportrevenue['revenue'], 2) : '');
                        $activeSheet->setCellValue('G' . $counter, $reportrevenue['refund_out'] ? number_format($reportrevenue['refund_out'], 2) : '');
                        $counter++;

                        $activeSheet->setCellValue('A' . $counter, '');
                        $counter++;
                    }

                    $doctor_total = $doctor_revenue_total - $doctor_refund_total;
                    $activeSheet->setCellValue('D' . $counter, 'Total')->getStyle('D' . $counter)->getFont()->setBold(true);
                    $activeSheet->setCellValue('H' . $counter, $doctor_total ? number_format($doctor_total, 2) : 0)->getStyle('H' . $counter)->getFont()->setBold(true);
                    $counter++;

                    $activeSheet->setCellValue('A4', '');
                    $counter++;
                }
                $centre_total = $centre_revenue_total - $centre_refund_total;
                $activeSheet->setCellValue('A' . $counter, 'Total')->getStyle('A' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('H' . $counter, $centre_total ? number_format($centre_total, 2) : 0)->getStyle('H' . $counter)->getFont()->setBold(true);
                $counter++;

                $activeSheet->setCellValue('A4', '');
                $counter++;
            }
            $grandtotal += $centre_total ? $centre_total : '';
            $activeSheet->setCellValue('A' . $counter, 'Grand Total')->getStyle('A' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('H' . $counter, $grandtotal ? number_format($grandtotal, 2) : 0)->getStyle('H' . $counter)->getFont()->setBold(true);
            $counter++;
        }
        $activeSheet->setCellValue('A' . $counter, '');
        $counter++;

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . 'staffwiserevenue' . '.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');
    }


    public function conversionreport(Request $request)
    {
        if (!Gate::allows('finance_general_revenue_reports_conversion_report')) {
            return abort(404);
        }

        if ($request->get('date_range')) {
            $date_range = explode(' - ', $request->get('date_range'));
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        list($report_data, $locationData) = Finanaces::conversion_report($request->all(), Auth::user()->account_id);

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.conversionreport.report', compact('report_data', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.conversionreport.reportprint', compact('report_data', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $content = view('admin.reports.conversionreport.reportpdf', compact('report_data', 'start_date', 'end_date'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A3', 'landscape');
                return $pdf->stream('conversion report', 'landscape');
                break;
            case 'excel':
                self::conversionreportexcel($report_data, $start_date, $end_date, $request->get('converted'));
                break;

            default:
                return view('admin.reports.conversionreport.report', compact('report_data', 'start_date', 'end_date'));
                break;
        }

    }

    private static function conversionreportexcel($reportData, $start_date, $end_date, $converted)
    {
        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From ' . $start_date . ' to ' . $end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', 'ID')->getStyle('A3')->getFont()->setBold(true);
        $activeSheet->setCellValue('B3', 'Doctor')->getStyle('B3')->getFont()->setBold(true);
        $activeSheet->setCellValue('C3', 'Date of Inquiry')->getStyle('C3')->getFont()->setBold(true);
        $activeSheet->setCellValue('D3', 'Client')->getStyle('D3')->getFont()->setBold(true);
        $activeSheet->setCellValue('E3', 'Appointment Type')->getStyle('E3')->getFont()->setBold(true);
        $activeSheet->setCellValue('F3', 'Service')->getStyle('F3')->getFont()->setBold(true);
        $activeSheet->setCellValue('G3', 'Converted')->getStyle('G3')->getFont()->setBold(true);
        $activeSheet->setCellValue('H3', 'Conversion Spend')->getStyle('H3')->getFont()->setBold(true);
        $activeSheet->setCellValue('I3', 'Conversion Date')->getStyle('I3')->getFont()->setBold(true);
        $activeSheet->setCellValue('J3', 'Region')->getStyle('J3')->getFont()->setBold(true);
        $activeSheet->setCellValue('K3', 'City')->getStyle('K3')->getFont()->setBold(true);
        $activeSheet->setCellValue('L3', 'Location')->getStyle('L3')->getFont()->setBold(true);

        $activeSheet->setCellValue('A4', '');


        $counter = 5;

        $total = 0;
        $count = 0;

        if (count($reportData)) {

            foreach ($reportData as $appointment) {
                if ($appointment['converted'] != '') {
                    $activeSheet->setCellValue('A' . $counter, $appointment['patient_id']);
                    $activeSheet->setCellValue('B' . $counter, $appointment['doctor']);
                    $activeSheet->setCellValue('C' . $counter, $appointment['doi']);
                    $activeSheet->setCellValue('D' . $counter, $appointment['client']);
                    $activeSheet->setCellValue('E' . $counter, 'Consultancy');
                    $activeSheet->setCellValue('F' . $counter, $appointment['service']);
                    $activeSheet->setCellValue('G' . $counter, $appointment['converted']);
                    $activeSheet->setCellValue('H' . $counter, number_format($appointment['conversion_spend'], 2));
                    $activeSheet->setCellValue('I' . $counter, \Carbon\Carbon::parse($appointment['conversion_date'])->format('F j,Y'));
                    $activeSheet->setCellValue('J' . $counter, $appointment['region']);
                    $activeSheet->setCellValue('K' . $counter, $appointment['city']);
                    $activeSheet->setCellValue('L' . $counter, $appointment['centre']);

                    $total += $appointment['conversion_spend'] ? $appointment['conversion_spend'] : 0;
                    $count++;
                    $counter++;
                }
            }
            $counter++;
            $activeSheet->setCellValue('A' . $counter, 'Total')->getStyle('A' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('H' . $counter, number_format($total, 2));
            $counter++;
            $activeSheet->setCellValue('A' . $counter, 'Total Count')->getStyle('A' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('H' . $counter, count($reportData));
            $counter++;
            $activeSheet->setCellValue('A' . $counter, 'Converted Count')->getStyle('A' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('H' . $counter, $count);
            $counter++;
            $activeSheet->setCellValue('A' . $counter, 'Converted Ration')->getStyle('A' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('H' . $counter,  $count > 0 ? number_format($count / count($reportData) * 100, 2) : 0 . '%');
            $counter++;
            $activeSheet->setCellValue('A' . $counter, 'Conversion Average')->getStyle('A' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('H' . $counter,  $total > 0 ? number_format($total / $count, 2) : 0);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . 'conversionreport' . '.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');
    }


    /*
     *  Collection by Serivce Report
     */
    public function collectionbyservice(Request $request)
    {

        if (!Gate::allows('finance_general_revenue_reports_collection_by_service')) {
            return abort(401);
        }

        if ($request->get('date_range')) {
            $date_range = explode(' - ', $request->get('date_range'));
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        $reportData = \App\Reports\Invoices::collectionbyservice($request->all(), Auth::User()->account_id);

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.collectionbyservice.report', compact('reportData', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.collectionbyservice.reportprint', compact('reportData', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $pdf = PDF::loadView('admin.reports.collectionbyservice.reportpdf', compact('reportData', 'start_date', 'end_date'));
                $pdf->setPaper('A4', 'landscape');
                return $pdf->stream('Daily Employee Stats Summary', 'landscape');
                break;
            case 'excel':
                self::collectionbyservuiceExcel($reportData, $start_date, $end_date);
                break;
            default:
                return view('admin.reports.collectionbyservice.report', compact('reportData', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * Daily Employee Stats (Summary) Excel
     * @param (mixed) $reportData
     * @param (mixed) $start_date
     * @param (mixed) $end_date
     *
     * @return \Illuminate\Http\Response
     */
    private static function collectionbyservuiceExcel($reportData, $start_date, $end_date)
    {

        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From ' . $start_date . ' to ' . $end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', 'Service')->getStyle('A3')->getFont()->setBold(true);
        $activeSheet->setCellValue('B3', 'Total')->getStyle('B3')->getFont()->setBold(true);

        $counter = 4;
        $total = 0;

        foreach ($reportData as $row) {
            if ($row['amount'] > 0) {
                $total = $total + $row['amount'];
                $activeSheet->setCellValue('A' . $counter, $row['name']);
                $activeSheet->setCellValue('B' . $counter, number_format($row['amount'], 2));
                $counter++;
            }
        }

        $activeSheet->setCellValue('A' . $counter, '');
        $activeSheet->setCellValue('B' . $counter, '');
        $counter++;

        $activeSheet->setCellValue('A' . $counter, 'Grand Total')->getStyle('A' . $counter)->getFont()->setBold(true);
        $activeSheet->setCellValue('B' . $counter, number_format($total, 2))->getStyle('B' . $counter)->getFont()->setBold(true);;


        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . 'Collectionbyservice' . '.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');

    }

    /**
     * Machine wise Collection Report.
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function machinewisecollectionreport(Request $request)
    {
        if (!Gate::allows('finance_general_revenue_reports_machine_wise_collection_report')) {
            return abort(401);
        }
        if ($request->get('date_range')) {
            $date_range = explode(' - ', $request->get('date_range'));
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

        $reportData = Finanaces::machinewisecollectionreport($request->all(), Auth::User()->account_id);

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.machinewisecollectionreport.report', compact('reportData', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.machinewisecollectionreport.reportprint', compact('reportData', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $content = view('admin.reports.machinewisecollectionreport.reportpdf', compact('reportData', 'start_date', 'end_date'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A3', 'landscape');
                return $pdf->stream('Machine Wise Invoice Revenue Report', 'landscape');
                break;
            case 'excel':
                self::machinewisecollectionsseportExcel($reportData, $start_date, $end_date);
                break;
            default:
                return view('admin.reports.machinewisecollectionreport.report', compact('report_data', 'total_revenue_cash_in', 'total_revenue_card_in', 'total_refund', 'total_revenue', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * Machine Wise collection Report Excel
     * @param (mixed) $reportData
     * @param (mixed) $start_date
     * @param (mixed) $end_date
     *
     * @return \Illuminate\Http\Response
     */
    private static function machinewisecollectionsseportExcel($reportData, $start_date, $end_date)
    {

        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From ' . $start_date . ' to ' . $end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', 'Center')->getStyle('A3')->getFont()->setBold(true);
        $activeSheet->setCellValue('B3', 'Region')->getStyle('B3')->getFont()->setBold(true);
        $activeSheet->setCellValue('C3', 'City')->getStyle('C3')->getFont()->setBold(true);
        $activeSheet->setCellValue('D3', 'Machine Type')->getStyle('D3')->getFont()->setBold(true);
        $activeSheet->setCellValue('E3', 'Client')->getStyle('E3')->getFont()->setBold(true);
        $activeSheet->setCellValue('F3', 'Cash Flow')->getStyle('F3')->getFont()->setBold(true);
        $activeSheet->setCellValue('G3', 'Cash In')->getStyle('G3')->getFont()->setBold(true);
        $activeSheet->setCellValue('H3', 'Refund/Cash Out')->getStyle('H3')->getFont()->setBold(true);
        $activeSheet->setCellValue('I3', 'Balance')->getStyle('I3')->getFont()->setBold(true);

        $activeSheet->setCellValue('A4', '');

        $counter = 5;

        if (count($reportData)) {

            $machinetotal_in_g = 0;
            $machinetotal_out_g = 0;

            foreach ($reportData as $reportlocation) {

                $activeSheet->setCellValue('A' . $counter, $reportlocation['name'])->getStyle('A' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('B' . $counter, $reportlocation['region'])->getStyle('B' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('C' . $counter, $reportlocation['city'])->getStyle('C' . $counter)->getFont()->setBold(true);

                $counter++;

                $machinetotal_in_t = 0;
                $machinetotal_out_t = 0;
                foreach ($reportlocation['machine_types'] as $reportmachine) {

                    $activeSheet->setCellValue('D' . $counter, $reportmachine['name']);
                    $counter++;
                    $activeSheet->setCellValue('A' . $counter, '');
                    $counter++;

                    $machinetotal_in = 0;
                    $machinetotal_out = 0;
                    foreach ($reportmachine['transaction'] as $paymentrecord) {

                        $activeSheet->setCellValue('E' . $counter, $paymentrecord['name']);
                        $activeSheet->setCellValue('F' . $counter, $paymentrecord['flow']);
                        $activeSheet->setCellValue('G' . $counter, $paymentrecord['amount_in'] ? number_format($paymentrecord['amount_in'], 2) : '');
                        $activeSheet->setCellValue('H' . $counter, $paymentrecord['amount_out'] ? number_format($paymentrecord['amount_out'], 2) : '');
                        $counter++;

                        $machinetotal_in += $paymentrecord['amount_in'] ? $paymentrecord['amount_in'] : 0;
                        $machinetotal_out += $paymentrecord['amount_out'] ? $paymentrecord['amount_out'] : 0;

                        $machinetotal_in_t += $paymentrecord['amount_in'] ? $paymentrecord['amount_in'] : 0;
                        $machinetotal_out_t += $paymentrecord['amount_out'] ? $paymentrecord['amount_out'] : 0;

                        $machinetotal_in_g += $paymentrecord['amount_in'] ? $paymentrecord['amount_in'] : 0;
                        $machinetotal_out_g += $paymentrecord['amount_out'] ? $paymentrecord['amount_out'] : 0;

                    }
                    $activeSheet->setCellValue('A' . $counter, '');
                    $counter++;

                    $activeSheet->setCellValue('D' . $counter, 'Total')->getStyle('D' . $counter)->getFont()->setBold(true);
                    $activeSheet->setCellValue('G' . $counter, number_format($machinetotal_in, 2))->getStyle('G' . $counter)->getFont()->setBold(true);
                    $activeSheet->setCellValue('H' . $counter, number_format($machinetotal_out, 2))->getStyle('H' . $counter)->getFont()->setBold(true);
                    $activeSheet->setCellValue('I' . $counter, number_format($machinetotal_in - $machinetotal_out, 2))->getStyle('I' . $counter)->getFont()->setBold(true);
                    $counter++;

                    $activeSheet->setCellValue('A' . $counter, '');
                    $counter++;
                }
                $activeSheet->setCellValue('A' . $counter, 'Total')->getStyle('A' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('G' . $counter, number_format($machinetotal_in_t, 2))->getStyle('G' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('H' . $counter, number_format($machinetotal_out_t, 2))->getStyle('H' . $counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('I' . $counter, number_format($machinetotal_in_t - $machinetotal_out_t, 2))->getStyle('I' . $counter)->getFont()->setBold(true);
                $counter++;

                $activeSheet->setCellValue('A' . $counter, '');
                $counter++;

            }
            $activeSheet->setCellValue('A' . $counter, 'Grand Total')->getStyle('A' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('G' . $counter, number_format($machinetotal_in_g, 2))->getStyle('G' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('H' . $counter, number_format($machinetotal_out_g, 2))->getStyle('H' . $counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('I' . $counter, number_format($machinetotal_in_g - $machinetotal_out_g, 2))->getStyle('I' . $counter)->getFont()->setBold(true);
            $counter++;
        }
        $activeSheet->setCellValue('A' . $counter, '');
        $counter++;

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . 'machinewisecollectionreport' . '.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');
    }

    /**
     * Consume Revenie of plan Report.
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function consumeplanrevenuereport(Request $request)
    {
        if (!Gate::allows('finance_general_revenue_reports_consume_plan_revenue_report')) {
            return abort(401);
        }
        if ($request->get('date_range')) {
            $date_range = explode(' - ', $request->get('date_range'));
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        $reportData = Finanaces::consumeplanrevenue($request->all(), Auth::User()->account_id);

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.consumeplanrevenue.report', compact('reportData', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.consumeplanrevenue.reportprint', compact('reportData', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $content = view('admin.reports.consumeplanrevenue.reportpdf', compact('reportData', 'start_date', 'end_date'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A3', 'landscape');
                return $pdf->stream('Consume Plan Revenue Report', 'landscape');
                break;
            case 'excel':
                self::consumeplanrevenueExcel($reportData, $start_date, $end_date);
                break;
            default:
                return view('admin.reports.consumeplanrevenue.report', compact('report_data', 'total_revenue_cash_in', 'total_revenue_card_in', 'total_refund', 'total_revenue', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * Daily Consume Revenue Excel
     * @param (mixed) $reportData
     * @param (mixed) $start_date
     * @param (mixed) $end_date
     *
     * @return \Illuminate\Http\Response
     */
    private static function consumeplanrevenueExcel($reportData, $start_date, $end_date)
    {
        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From ' . $start_date . ' to ' . $end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', '');
        $activeSheet->setCellValue('B3', '');

        $activeSheet->setCellValue('A4', 'Plan ID')->getStyle('A4')->getFont()->setBold(true);
        $activeSheet->setCellValue('B4', 'Service')->getStyle('B4')->getFont()->setBold(true);
        $activeSheet->setCellValue('C4', 'Center')->getStyle('C4')->getFont()->setBold(true);
        $activeSheet->setCellValue('D4', 'Service Price')->getStyle('D4')->getFont()->setBold(true);
        $activeSheet->setCellValue('E4', 'Discount Name')->getStyle('E4')->getFont()->setBold(true);
        $activeSheet->setCellValue('F4', 'Discount Type')->getStyle('F4')->getFont()->setBold(true);
        $activeSheet->setCellValue('G4', 'Discount Amount')->getStyle('G4')->getFont()->setBold(true);
        $activeSheet->setCellValue('H4', 'Amount')->getStyle('H4')->getFont()->setBold(true);
        $activeSheet->setCellValue('I4', 'Tax')->getStyle('I4')->getFont()->setBold(true);
        $activeSheet->setCellValue('J4', 'Tax Value')->getStyle('J4')->getFont()->setBold(true);
        $activeSheet->setCellValue('K4', 'Total Amount')->getStyle('K4')->getFont()->setBold(true);
        $activeSheet->setCellValue('L4', 'Is Exclusive')->getStyle('L4')->getFont()->setBold(true);

        $counter = 6;
        $amount_t = 0;
        $tax_price_t = 0;
        $total_amount_t = 0;

        foreach ($reportData as $reportRow) {

            $activeSheet->setCellValue('A' . $counter, $reportRow['plan_id']);
            $activeSheet->setCellValue('B' . $counter, $reportRow['service']);
            $activeSheet->setCellValue('C' . $counter, $reportRow['location']);
            $activeSheet->setCellValue('D' . $counter, number_format($reportRow['service_price']));
            $activeSheet->setCellValue('E' . $counter, $reportRow['disocunt_name'] ? $reportRow['disocunt_name'] : '-');
            $activeSheet->setCellValue('F' . $counter, $reportRow['discount_type'] ? $reportRow['discount_type'] : '-');
            $activeSheet->setCellValue('G' . $counter, $reportRow['discount_amount'] ? number_format($reportRow['discount_amount']) : '-');
            $activeSheet->setCellValue('H' . $counter, number_format($reportRow['amount']));
            $activeSheet->setCellValue('I' . $counter, $reportRow['tax'] . '%');
            $activeSheet->setCellValue('J' . $counter, $reportRow['is_exclusive'] == 1 ? number_format($reportRow['tax_value']) : number_format($reportRow['tax_amount'] - $reportRow['amount']));
            $activeSheet->setCellValue('K' . $counter, number_format($reportRow['tax_amount']));
            $activeSheet->setCellValue('L' . $counter, $reportRow['is_exclusive'] == 1 ? 'Yes' : 'No');
            $counter++;

            $amount_t += $reportRow['amount'];
            $tax_price_t += $reportRow['is_exclusive'] == 1 ? $reportRow['tax_value'] : $reportRow['tax_amount'] - $reportRow['amount'];
            $total_amount_t += $reportRow['tax_amount'];
        }
        $activeSheet->setCellValue('A' . $counter, '');
        $counter++;

        $activeSheet->setCellValue('A' . $counter, 'Total')->getStyle('A' . $counter)->getFont()->setBold(true);
        $activeSheet->setCellValue('H' . $counter, number_format($amount_t))->getStyle('H' . $counter)->getFont()->setBold(true);
        $activeSheet->setCellValue('J' . $counter, number_format($tax_price_t))->getStyle('J' . $counter)->getFont()->setBold(true);
        $activeSheet->setCellValue('K' . $counter, number_format($total_amount_t))->getStyle('K' . $counter)->getFont()->setBold(true);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . 'Consume Plan Revenue' . '.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');
    }


    /*
 * Function to lead machine
 */
    public function loadmachine(Request $request)
    {
        if ($request->location_id) {
            $machines = Resources::where('location_id', '=', $request->location_id)->get();
            $mahinetypeids = array();
            foreach ($machines as $machine) {
                if (!in_array($machine->machine_type_id, $mahinetypeids)) {
                    $mahinetypeids[] = $machine->machine_type_id;
                }
            }
            $machinetype = MachineType::whereIn('id', $mahinetypeids)->get();
        } else {
            $machinetype = [];
        }
        return response()->json(array(
            'machinearray' => view('admin.reports.partnercollectionreport.loadmachine', compact('machinetype'))->render(),
        ));
    }

    /*
     * Function to get the discounts according to change in report type for account sales report and discount report
     * */

    public function getDiscounts(Request $request)
    {

        $discounts = Discounts::where('account_id', '=', '1');

        if ($request->appointment_type === null) {

        } else {
            if ($request->appointment_type == 1) {

                $discounts = $discounts->where('discount_type', '=', config('constants.Consultancy'));

            } else {

                $discounts = $discounts->where('discount_type', '=', config('constants.Service'));
            }
        }
        $discounts = $discounts->get();

        return response()->json(array(
            'discounts' => view('admin.reports.accountsalesreport.discounts', compact('discounts'))->render(),
        ));
    }
    public function ArrivedNotConverted()
    {
        $services = Services::where(['parent_id' => 0])->whereNotIn('slug',['all'])->get();
        $cities = Cities::getActiveOnly(false, Auth::User()->account_id)->pluck('full_name', 'id');
        $locations = Locations::getActiveRecordsByCity('',ACL::getUserCentres(), Auth::User()->account_id);
        return view('admin.reports.arrived',get_defined_vars());
    }

    public function DailyArrival()
    {
        $services = Services::where(['parent_id'=>0])->whereNotIn('slug',['all'])->get();
        $cities = Cities::getActiveOnly(false, Auth::User()->account_id)->pluck('full_name', 'id');
        $locations = Locations::getActiveRecordsByCity('',ACL::getUserCentres(), Auth::User()->account_id);
        $Users = User::getAllRecords(Auth::User()->account_id)->getDictionary();
        return view('admin.reports.dailyarrival',get_defined_vars());
    }

    public function LoadDailyArrival(Request $request)
    {
        $where = array();
        if ($request->location_id  && $request->location_id ) {
            $where[] = array(['appointments.location_id' => $request->location_id]);
        }
        if ($request->service_id && $request->service_id != '') {
            $where[] = array(['appointments.service_id' => $request->service_id]);
        }
        if ($request->created_by && $request->created_by != '') {
            $where[] = array(['appointments.created_by' => $request->created_by]);
        }
        if ($request->date_from) {
            $where[] = array('appointments.scheduled_date', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $where[] = array('appointments.scheduled_date', '<=', $request->date_to);
        }
        $records = array();
        $records["data"] = array();
        if (Gate::allows('appointments_consultancy')) {
            $resultQuery = Appointments::join('users', function ($query) {
                $query->on('users.id', 'appointments.patient_id')
                    ->where(['users.user_type_id' => config('constants.patient_id')]);
            })->where(['appointments.appointment_type_id' =>  1])
                ->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }
        if (Gate::allows('appointments_consultancy') && Gate::allows('appointments_services')) {
            $resultQuery = Appointments::join('users', function ($query) {
                $query->on('users.id', 'appointments.patient_id')
                    ->where(['users.user_type_id' => config('constants.patient_id')]);
            })->whereIn('appointments.city_id', ACL::getUserCities())
                ->whereIn('appointments.location_id', ACL::getUserCentres());
        }

        if (count($where)) {
            $resultQuery->where($where);
        }
        $Appointments = $resultQuery->select('*', 'appointments.name as patient_name', 'appointments.id as app_id', 'appointments.created_by as app_created_by', 'appointments.updated_by as app_updated_by', 'appointments.created_at as app_created_at')
        ->orderBy("appointments.created_at", "DESC")
        ->get();
        $arrived = $resultQuery->where(['base_appointment_status_id' => 2])->count();
        return view('admin.reports.daily_arrived',get_defined_vars());
    }
    public function staffWiseArrival()
    {
        $locations = Locations::getActiveRecordsByCity('',ACL::getUserCentres(), Auth::User()->account_id);
        $Users = User::getAllRecords(Auth::User()->account_id)->getDictionary();
        return view('admin.reports.staffwisearrival',get_defined_vars());
    }
    public function staffWiseArrivalReport(Request $request)
    {
        $where = array();
        if ($request->location_id  && $request->location_id ) {
            $where[] = array(['centre_id' => $request->location_id]);
            $where_walkin[] = array(['centre_id' => $request->location_id]);
        }
        if ($request->created_by && $request->created_by != '') {
            $where[] = array(['user_id' => $request->created_by]);
        }
        if ($request->date_from) {
            $where[] = array('cron_current_date', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $where[] = array('cron_current_date', '<=', $request->date_to);
        }
        $records = array();
        $records["data"] = array();

        $fdm_users = RoleHasUsers::where(['role_id' => 4 ])->pluck('user_id');
        if (Gate::allows('appointments_consultancy')) {
            $resultQuery = AppointmentsDailyStats::whereIn('centre_id', ACL::getUserCentres());
        }
        if (Gate::allows('appointments_consultancy') && Gate::allows('appointments_services')) {
            $resultQuery = AppointmentsDailyStats::whereIn('centre_id', ACL::getUserCentres());
        }
        if (count($where)) {
            $resultQuery->where($where);
        }
        if(!$request->created_by){
            $walkin_customers = AppointmentsDailyStats::whereIn('user_id', $fdm_users)->count();
        }
        if($request->location_id  && $request->location_id != ''  && !$request->created_by){
                $walkin_customers = AppointmentsDailyStats::whereIn('user_id', $fdm_users)
                ->where('centre_id',$request->location_id)
                ->count();
        }
        if($request->date_from && $request->date_to && !$request->created_by){
            $walkin_customers = AppointmentsDailyStats::whereIn('user_id', $fdm_users)
            ->whereDate('cron_current_date', '>=', $request->date_from)
            ->whereDate('cron_current_date', '<=', $request->date_to)
            ->count();
        }
        if($request->location_id  && $request->location_id != '' && $request->date_from && $request->date_to && !$request->created_by){
            $walkin_customers = AppointmentsDailyStats::whereIn('user_id', $fdm_users)
            ->where(['centre_id' => $request->location_id])
            ->whereDate('cron_current_date', '>=', $request->date_from)
            ->whereDate('cron_current_date', '<=', $request->date_to)
            ->count();
        }
        $Appointments = $resultQuery->with(['user', 'appointment' => function($q){
            $q->select('*', 'appointments.name as patient_name', 'appointments.id as app_id', 'appointments.created_by as app_created_by', 'appointments.updated_by as app_updated_by', 'appointments.created_at as app_created_at')
            ->orderBy("appointments.created_at", "DESC");
        }])
        ->get();

        $arrived = $resultQuery->where(['appointment_status_id' => 2])->count();
        $user = User::where(['id' => $request->created_by])->first()->name ?? '';
        $centre = Locations::where(['id' => $request->location_id])->first()->name ?? 'All centres';
        return view('admin.reports.staff_wise_arrived',get_defined_vars());
    }
}
