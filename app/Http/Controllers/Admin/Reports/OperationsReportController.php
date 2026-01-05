<?php

namespace App\Http\Controllers\Admin\Reports;

use App;
use App\Helpers\ACL;
use App\Helpers\GeneralFunctions;
use App\Helpers\NodesTree;
use App\Http\Controllers\Controller;
use App\Models\Accounts;
use App\Models\AppointmentStatuses;
use App\Models\AppointmentTypes;
use App\Models\Cities;
use App\Models\Doctors;
use App\Models\Locations;
use App\Models\Regions;
use App\Models\Services;
use App\Models\User;
use App\Reports\Finanaces;
use App\Reports\Operations;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class OperationsReportController extends Controller
{
    /**
     * Display a listing filter for finanace report.
     *
     * @return \Illuminate\Http\Response
     */
    public function report()
    {
        if (! Gate::allows('operations_reports_manage')) {
            return abort(401);
        }
        $allserviceslug = Services::where('slug', '=', 'all')->first();
        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::User()->account_id);
        $parentGroups->toList($parentGroups, -1);
        $services = $parentGroups->nodeList;

        foreach ($services as $key => $ser) {
            if ($key) {
                if ($ser['name'] == $allserviceslug->name) {
                    unset($services[$key]);
                }
            }
        }
        $cities = Cities::getActiveOnly(false, Auth::User()->account_id)->pluck('full_name', 'id');
        $cities->prepend('Select a City', '');

        $locations = Locations::getActiveSorted(ACL::getUserCentres());
        $locations->prepend('All', '');

        $operators = User::getAllActivePractionersRecords(Auth::User()->account_id, ACL::getUserCentres())->pluck('name', 'id');
        $select_All = ['' => 'All'];

        $employees = ($select_All + $operators->toArray());

        $months_data = Config::get('constants.months_array');
        foreach ($months_data as $key => $value) {
            $months[$key] = $value;
        }

        $regions = Regions::getActiveSorted(ACL::getUserRegions());
        $regions->prepend('Select a Region', '');

        $years_data = range(Carbon::now()->year, Carbon::now()->subYears(10)->year);
        foreach ($years_data as $year) {
            $years[$year] = $year;
        }
        $appointment_types = AppointmentTypes::getAllRecords(Auth::User()->account_id)->pluck('name', 'id');
        $appointment_types->prepend('All', '');

        $csrs = GeneralFunctions::getCSR();

        $agents = User::whereIn('id', $csrs)->isActive()->get(['name', 'id']);

        return view('admin.reports.operations.index', compact('locations', 'employees', 'services', 'regions', 'months', 'years', 'cities', 'appointment_types', 'agents'));
    }

    /*
     * Function for load days
     */
    public function loaddayarray(Request $request)
    {

        $days = cal_days_in_month(CAL_GREGORIAN, $request->month, $request->year);

        return response()->json([
            'daysarray' => view('admin.reports.operations.centertarget.dayarray', compact('days'))->render(),
        ]);
    }

    /**
     * Load Report
     *
     * @return \Illuminate\Http\Response
     */
    public function reportLoad(Request $request)
    {
        ini_set('memory_limit', '-1');

        switch ($request->get('report_type')) {
            case 'dar_report':
                return self::darreport($request);
                break;
            case 'walking_report':
                return self::walkingReport($request);
                break;
            case 'agent_report':
                return self::agentReport($request);
            default:
                return self::darreport($request);
                break;
        }
    }

    /**
     * Center target report
     *
     * @return \Illuminate\Http\Response
     */
    private static function centertargetreport(Request $request)
    {
        if (! Gate::allows('operations_reports_center_target_report')) {
            return abort(401);
        }

        $data = Operations::centertargetreport($request->all(), Auth::User()->account_id);

        $reportData = $data['location_target_data'];
        $start_date = $data['start_date'];
        $end_date = $data['end_date'];

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.operations.centertarget.report', compact('reportData', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.operations.centertarget.reportprint', compact('reportData', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $content = view('admin.reports.operations.centertarget.reportpdf', compact('reportData', 'start_date', 'end_date'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A4', 'landscape');

                return $pdf->stream('CenterTargetReport', 'landscape');
                break;
            case 'excel':
                self::centretargetreportExcel($reportData, $start_date, $end_date);
                break;
            default:
                return view('admin.reports.operations.centertarget.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * General Revnue Report Excel
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $start_date
     * @param  (mixed)  $end_date
     * @return \Illuminate\Http\Response
     */
    private static function centretargetreportExcel($reportData, $start_date, $end_date)
    {

        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From '.$start_date.' to '.$end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', '');

        $activeSheet->setCellValue('A4', 'Sr#')->getStyle('A4')->getFont()->setBold(true);
        $activeSheet->setCellValue('B4', 'Centre')->getStyle('B4')->getFont()->setBold(true);
        $activeSheet->setCellValue('C4', 'Region')->getStyle('C4')->getFont()->setBold(true);
        $activeSheet->setCellValue('D4', 'City')->getStyle('D4')->getFont()->setBold(true);
        $activeSheet->setCellValue('E4', 'Monthly Target')->getStyle('E4')->getFont()->setBold(true);
        $activeSheet->setCellValue('F4', 'Monthly Achieved')->getStyle('F4')->getFont()->setBold(true);
        $activeSheet->setCellValue('G4', 'Percentage')->getStyle('G4')->getFont()->setBold(true);

        $activeSheet->setCellValue('A5', '');

        $counter = 6;
        $co = 1;
        $monthly_target_total = 0;
        $monthly_achived_total = 0;
        if ($reportData) {
            foreach ($reportData as $reportsingle) {

                $monthly_target_total += $reportsingle['monthly_target'];
                $monthly_achived_total += $reportsingle['target_achieved'];

                $activeSheet->setCellValue('A'.$counter, $co++);
                $activeSheet->setCellValue('B'.$counter, $reportsingle['name']);
                $activeSheet->setCellValue('C'.$counter, $reportsingle['region']);
                $activeSheet->setCellValue('D'.$counter, $reportsingle['city']);
                $activeSheet->setCellValue('E'.$counter, number_format($reportsingle['monthly_target'], 2));
                $activeSheet->setCellValue('F'.$counter, number_format($reportsingle['target_achieved'], 2));
                $activeSheet->setCellValue('G'.$counter, number_format($reportsingle['Pecentage'], 2).'%');
                $counter++;
            }
            $activeSheet->setCellValue('A'.$counter, '');
            $counter++;

            $activeSheet->setCellValue('A'.$counter, 'Total')->getStyle('A'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('E'.$counter, number_format($monthly_target_total, 2))->getStyle('E'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('F'.$counter, number_format($monthly_achived_total, 2))->getStyle('F'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('G'.$counter, number_format(($monthly_achived_total / $monthly_target_total) * 100, 2).'%')->getStyle('G'.$counter)->getFont()->setBold(true);
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'CenterTargetReport'.'.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');

    }

    /**
     * Company Health Report
     *
     * @return \Illuminate\Http\Response
     */
    private static function companyHealth(Request $request)
    {
        if (! Gate::allows('operations_reports_operations_company_health')) {
            return abort(401);
        }

        $users = User::getAllRecords(Auth::User()->account_id)->pluck('name', 'id');
        $users->prepend('All', '');

        $data = Operations::companyHealthReport($request->all(), Auth::User()->account_id);

        $reportData = $data['location_target_data'];
        $start_date = $data['start_date'];
        $end_date = $data['end_date'];
        $regions = $data['regions'];
        $remaining_days = $data['remainingDays'];

        $account = Accounts::find(1, ['name']);

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.operations.company_health.report', compact('reportData', 'start_date', 'end_date', 'remaining_days', 'regions', 'account'));
                break;
            case 'print':
                return view('admin.reports.operations.company_health.reportprint', compact('reportData', 'start_date', 'end_date', 'remaining_days', 'regions', 'account'));
                break;
            case 'pdf':
                $content = view('admin.reports.operations.company_health.reportpdf', compact('reportData', 'start_date', 'end_date', 'remaining_days', 'regions', 'account'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A4', 'landscape');

                return $pdf->stream('Company Health Report', 'landscape');
                break;
            case 'excel':
                self::companyHealthExcel($reportData, $request->get('month'), $request->get('year'), $start_date, $end_date, $remaining_days, $regions, $account);
                break;
            default:
                return view('admin.reports.operations.company_health.report', compact('reportData', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * Company Health Report by Month
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $filters
     * @param  (mixed)  $month
     * @param  (mixed)  $year
     * @return \Illuminate\Http\Response
     */
    private static function companyHealthExcel($reportData, $month, $year, $start_date, $end_date, $remaining_days, $regions, $account)
    {
        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From '.$start_date.' to '.$end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $counter = 3;

        if (count($regions)) {
            foreach ($regions as $region) {
                $activeSheet->setCellValue('D'.$counter, $account->name)->getStyle('D'.$counter++)->getFont()->setBold(true);
                $activeSheet->setCellValue('D'.$counter, 'Health of the Company for Month of '.\Carbon\Carbon::parse($start_date)->format('M, Y'))->getStyle('D'.$counter++)->getFont()->setBold(true);
                $activeSheet->setCellValue('D'.$counter, 'Region Wise Monthly Target '.\Carbon\Carbon::parse($start_date)->format('M, Y'))->getStyle('D'.$counter++)->getFont()->setBold(true);
                $activeSheet->setCellValue('D'.$counter, $region['region_name'])->getStyle('D'.$counter++)->getFont()->setBold(true);
                $activeSheet->setCellValue('D'.$counter, $remaining_days.' Days Remaining ')->getStyle('D'.$counter++)->getFont()->setBold(true);

                $activeSheet->setCellValue('A'.$counter, '#')->getStyle('A'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('B'.$counter, 'Centre')->getStyle('B'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('C'.$counter, 'Monthly Target')->getStyle('C'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('D'.$counter, 'Month to Date')->getStyle('D'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('E'.$counter, 'Revenue Still Outstanding to Hit Monthly Target')->getStyle('E'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('F'.$counter, 'Revenue Required Per Day to Hit Target')->getStyle('F'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('G'.$counter, 'Percentage')->getStyle('G'.$counter++)->getFont()->setBold(true);

                if (count($reportData)) {
                    $count = 1;
                    $monthly_target_total = 0;
                    $monthly_achieved_total = 0;
                    $monthly_outstanding_revenue_total = 0;
                    $monthly_per_day_required_total = 0;

                    foreach ($reportData as $reportsingle) {
                        if ($reportsingle['region_id'] === $region['region_id']) {
                            $activeSheet->setCellValue('A'.$counter, $count++);
                            $activeSheet->setCellValue('B'.$counter, $reportsingle['name']);

                            $activeSheet->setCellValue('C'.$counter, number_format($reportsingle['monthly_target'], 2));
                            $activeSheet->setCellValue('D'.$counter, number_format($reportsingle['target_achieved'], 2));
                            $activeSheet->setCellValue('E'.$counter, number_format($reportsingle['revenue_outstanding'], 2));
                            $activeSheet->setCellValue('F'.$counter, number_format($reportsingle['perDayRequired'], 2));
                            $activeSheet->setCellValue('G'.$counter, number_format($reportsingle['Pecentage'], 2).'%');

                            $monthly_target_total += $reportsingle['monthly_target'];
                            $monthly_achieved_total += $reportsingle['target_achieved'];
                            $monthly_outstanding_revenue_total += $reportsingle['revenue_outstanding'];
                            $monthly_per_day_required_total += $reportsingle['perDayRequired'];

                            $counter++;
                        }
                    }

                    $activeSheet->setCellValue('A'.$counter, 'Total Target')->getStyle('A'.$counter)->getFont()->setBold(true);
                    $activeSheet->setCellValue('C'.$counter, number_format($monthly_target_total, 2))->getStyle('C'.$counter++)->getFont()->setBold(true);
                    $activeSheet->setCellValue('A'.$counter, 'Total Month to Date')->getStyle('A'.$counter)->getFont()->setBold(true);
                    $activeSheet->setCellValue('D'.$counter, number_format($monthly_achieved_total, 2))->getStyle('D'.$counter++)->getFont()->setBold(true);
                    $activeSheet->setCellValue('A'.$counter, 'Revenue Still Outstanding to Hit Monthly Target')->getStyle('A'.$counter)->getFont()->setBold(true);
                    $activeSheet->setCellValue('E'.$counter, number_format($monthly_outstanding_revenue_total, 2))->getStyle('E'.$counter++)->getFont()->setBold(true);
                    $activeSheet->setCellValue('A'.$counter, 'Avg. Revenue Required Per Day to Hit Target')->getStyle('A'.$counter)->getFont()->setBold(true);
                    $activeSheet->setCellValue('F'.$counter, number_format($monthly_per_day_required_total, 2))->getStyle('F'.$counter++)->getFont()->setBold(true);
                    $activeSheet->setCellValue('A'.$counter, 'Total Month to Date Revenue %')->getStyle('A'.$counter)->getFont()->setBold(true);
                    $activeSheet->setCellValue('G'.$counter, number_format(($monthly_achieved_total / $monthly_target_total) * 100, 2).'%')->getStyle('G'.$counter++)->getFont()->setBold(true);

                }

                $counter += 2;
            }
        } else {
            $activeSheet->setCellValue('A3', 'No record found');
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'CompanyHealthReport'.'.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');

    }

    /**
     * Company Health Report
     *
     * @return \Illuminate\Http\Response
     */
    private static function Highestpayingclients(Request $request)
    {
        if (! Gate::allows('operations_reports_Highest_paying_clients')) {
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

        $filters = [];
        $filters['regions'] = Regions::getAll(Auth::User()->account_id, 'custom', 'name', 'asc')->getDictionary();
        $filters['cities'] = Cities::getAllRecordsDictionary(Auth::User()->account_id);

        $reportData = Operations::highestpaidclient($request->all(), $filters, Auth::User()->account_id);

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.operations.highestpaidclient.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.operations.highestpaidclient.reportprint', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $content = view('admin.reports.operations.highestpaidclient.reportpdf', compact('reportData', 'start_date', 'end_date'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A4', 'landscape');

                return $pdf->stream('Highest Paid Client Report', 'landscape');
                break;
            case 'excel':
                self::highestpaidclientExcel($reportData, $filters, $request->get('month'), $request->get('year'));
                break;
            default:
                return view('admin.reports.operations.highestpaidclient.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * Higest Paid Client Report Excel
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $filters
     * @param  (mixed)  $start_date
     * @param  (mixed)  $end_date
     * @return \Illuminate\Http\Response
     */
    private static function highestpaidclientExcel($reportData, $filters, $month, $year)
    {

        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'For the month of '.\Carbon\Carbon::createFromDate($year, $month, 1)->format('M Y'));

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', 'ID')->getStyle('A3')->getFont()->setBold(true);
        $activeSheet->setCellValue('B3', 'Client Name')->getStyle('B3')->getFont()->setBold(true);
        $activeSheet->setCellValue('C3', 'Email')->getStyle('C3')->getFont()->setBold(true);
        $activeSheet->setCellValue('D3', 'Gender')->getStyle('D3')->getFont()->setBold(true);
        $activeSheet->setCellValue('E3', 'DOB')->getStyle('E3')->getFont()->setBold(true);
        $activeSheet->setCellValue('F3', 'Revenue')->getStyle('F3')->getFont()->setBold(true);

        $activeSheet->setCellValue('A4', '');

        $counter = 5;
        if (count($reportData)) {
            foreach ($reportData as $reportlocationdata) {

                $activeSheet->setCellValue('A'.$counter, $reportlocationdata['name'])->getStyle('A'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('B'.$counter, $reportlocationdata['region'])->getStyle('B'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('C'.$counter, $reportlocationdata['city'])->getStyle('C'.$counter)->getFont()->setBold(true);
                $counter++;

                $activeSheet->setCellValue('A'.$counter, '');
                $counter++;

                foreach ($reportlocationdata['clients'] as $reportRow) {

                    $activeSheet->setCellValue('A'.$counter, $reportRow['id'])->getStyle('A'.$counter)->getFont();
                    $activeSheet->setCellValue('B'.$counter, $reportRow['name'])->getStyle('B'.$counter)->getFont();
                    $activeSheet->setCellValue('C'.$counter, $reportRow['email'])->getStyle('C'.$counter)->getFont();
                    $activeSheet->setCellValue('D'.$counter, $reportRow['gender'])->getStyle('D'.$counter)->getFont();
                    $activeSheet->setCellValue('E'.$counter, $reportRow['dob'])->getStyle('E'.$counter)->getFont();
                    $activeSheet->setCellValue('F'.$counter, number_format($reportRow['Revenue'], 2))->getStyle('F'.$counter)->getFont();
                    $counter++;
                }
            }
        }
        $activeSheet->setCellValue('A'.$counter, '');
        $counter++;

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'Higest Paid Client'.'.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');

    }

    /**
     * List of Clients who claimed refunds
     *
     * @return \Illuminate\Http\Response
     */
    private static function Listofrefundsforacertainperioddatebased(Request $request)
    {
        if (! Gate::allows('operations_reports_List_of_refunds_for_a_certain_period_date_based')) {
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
        if ($request->type) {
            if ($request->type == 'plan') {

                /*Becasue These both report are same so we same method*/
                $reportData = Finanaces::ListofClientswhoclaimedrefundsdaywise($request->all(), Auth::User()->account_id);

                switch ($request->get('medium_type')) {
                    case 'web':
                        return view('admin.reports.operations.ListofClientswhoclaimedrefundsdaywise.report', compact('reportData', 'start_date', 'end_date'));
                        break;
                    case 'print':
                        return view('admin.reports.operations.ListofClientswhoclaimedrefundsdaywise.reportprint', compact('reportData', 'start_date', 'end_date'));
                        break;
                    case 'pdf':
                        $content = view('admin.reports.operations.ListofClientswhoclaimedrefundsdaywise.reportpdf', compact('reportData', 'start_date', 'end_date'))->render();
                        $pdf = App::make('dompdf.wrapper');
                        $pdf->loadHTML($content);
                        $pdf->setPaper('A4', 'landscape');

                        return $pdf->stream('Claimed refunds days for plans', 'landscape');
                        break;
                    case 'excel':
                        self::ListofClientswhoclaimedrefundsdaysbaseExcel($reportData, $request->get('month'), $request->get('year'));
                        break;
                    default:
                        return view('admin.reports.operations.ListofClientswhoclaimedrefundsdaywise.report', compact('reportData', 'start_date', 'end_date'));
                        break;
                }
            } else {

                $filters['doctors'] = Doctors::getAll(Auth::User()->account_id)->getDictionary();
                $filters['cities'] = Cities::getAllRecordsDictionary(Auth::User()->account_id);
                $filters['locations'] = Locations::getAllRecordsDictionary(Auth::User()->account_id);
                $filters['services'] = Services::getAllRecordsDictionary(Auth::User()->account_id);
                $filters['appointment_statuses'] = AppointmentStatuses::getAllRecordsDictionary(Auth::User()->account_id);
                $filters['appointment_types'] = AppointmentTypes::getAllRecords(Auth::User()->account_id)->getDictionary();
                $filters['users'] = User::getAllRecords(Auth::User()->account_id)->getDictionary();

                $reportData = Finanaces::ListofClientswhoclaimedrefundsdaysbasenonplans($request->all(), $filters, Auth::User()->account_id);

                switch ($request->get('medium_type')) {
                    case 'web':
                        return view('admin.reports.operations.ListofClientswhoclaimedrefundsdaybasenonplans.report', compact('reportData', 'start_date', 'end_date'));
                        break;
                    case 'print':
                        return view('admin.reports.operations.ListofClientswhoclaimedrefundsdaybasenonplans.reportprint', compact('reportData', 'start_date', 'end_date'));
                        break;
                    case 'pdf':
                        $content = view('admin.reports.operations.ListofClientswhoclaimedrefundsdaybasenonplans.reportpdf', compact('reportData', 'start_date', 'end_date'))->render();
                        $pdf = App::make('dompdf.wrapper');
                        $pdf->loadHTML($content);
                        $pdf->setPaper('A3', 'landscape');

                        return $pdf->stream('Claimed refunds days for non plans', 'landscape');
                        break;
                    case 'excel':
                        self::ListofClientswhoclaimedrefundsdaybasenonplansExcel($reportData, $request->get('month'), $request->get('year'));
                        break;
                    default:
                        return view('admin.reports.operations.ListofClientswhoclaimedrefundsdaybasenonplans.report', compact('reportData', 'start_date', 'end_date'));
                        break;
                }
            }
        } else {
            $reportData = [];
        }
    }

    /**
     * List of client who claimed refunds days base for plans excel
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $filters
     * @param  (mixed)  $start_date
     * @param  (mixed)  $end_date
     * @return \Illuminate\Http\Response
     */
    private static function ListofClientswhoclaimedrefundsdaysbaseExcel($reportData, $month, $year)
    {

        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'For the month of '.\Carbon\Carbon::createFromDate($year, $month, 1)->format('M Y'));

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', 'ID')->getStyle('A3')->getFont()->setBold(true);
        $activeSheet->setCellValue('B3', 'Patient Name')->getStyle('B3')->getFont()->setBold(true);
        $activeSheet->setCellValue('C3', 'Centre')->getStyle('C3')->getFont()->setBold(true);
        $activeSheet->setCellValue('D3', 'Refund Note')->getStyle('D3')->getFont()->setBold(true);
        $activeSheet->setCellValue('E3', 'Amount')->getStyle('E3')->getFont()->setBold(true);
        $activeSheet->setCellValue('F3', 'Created At')->getStyle('F3')->getFont()->setBold(true);

        $activeSheet->setCellValue('A4', '');

        $counter = 5;
        if (count($reportData)) {
            $grefund = 0;
            foreach ($reportData as $reportpackagedata) {

                $activeSheet->setCellValue('A'.$counter, $reportpackagedata['name'])->getStyle('A'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('B'.$counter, number_format($reportpackagedata['total_price'], 2))->getStyle('B'.$counter)->getFont()->setBold(true);
                $counter++;

                $activeSheet->setCellValue('A'.$counter, '');
                $counter++;

                $trefund = 0;

                foreach ($reportpackagedata['refunds'] as $reportRow) {

                    $trefund += $reportRow['cash_amount'];

                    $activeSheet->setCellValue('A'.$counter, $reportRow['patient_id'])->getStyle('A'.$counter)->getFont();
                    $activeSheet->setCellValue('B'.$counter, $reportRow['patient'])->getStyle('B'.$counter)->getFont();
                    $activeSheet->setCellValue('C'.$counter, $reportRow['location'])->getStyle('C'.$counter)->getFont();
                    $activeSheet->setCellValue('D'.$counter, $reportRow['refund_note'])->getStyle('D'.$counter)->getFont();
                    $activeSheet->setCellValue('E'.$counter, number_format($reportRow['cash_amount'], 2))->getStyle('E'.$counter)->getFont();
                    $activeSheet->setCellValue('F'.$counter, ($reportRow['created_at']) ? \Carbon\Carbon::parse($reportRow['created_at'], null)->format('M j, Y') : '-')->getStyle('F'.$counter)->getFont();
                    $counter++;
                }
                $grefund += $trefund;
                $activeSheet->setCellValue('A'.$counter, 'Refund Total')->getStyle('A'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('E'.$counter, number_format($trefund, 2))->getStyle('E'.$counter)->getFont()->setBold(true);
                $counter++;
            }
            $activeSheet->setCellValue('A'.$counter, 'Grand Total')->getStyle('A'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('E'.$counter, number_format($grefund, 2))->getStyle('E'.$counter)->getFont()->setBold(true);
            $counter++;

        }
        $activeSheet->setCellValue('A'.$counter, '');
        $counter++;

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'List of client who claimed refund days base against plans'.'.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');

    }

    /**
     * List of client who claimed refunds days base for plans excel
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $filters
     * @param  (mixed)  $start_date
     * @param  (mixed)  $end_date
     * @return \Illuminate\Http\Response
     */
    private static function ListofClientswhoclaimedrefundsdaybasenonplansExcel($reportData, $month, $year)
    {

        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'For the month of '.\Carbon\Carbon::createFromDate($year, $month, 1)->format('M Y'));

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', 'Patient ID')->getStyle('A3')->getFont()->setBold(true);
        $activeSheet->setCellValue('B3', 'Patient Name')->getStyle('B3')->getFont()->setBold(true);
        $activeSheet->setCellValue('C3', 'Email')->getStyle('C3')->getFont()->setBold(true);
        $activeSheet->setCellValue('D3', 'Appointment Scheduled')->getStyle('D3')->getFont()->setBold(true);
        $activeSheet->setCellValue('E3', 'Service')->getStyle('E3')->getFont()->setBold(true);
        $activeSheet->setCellValue('F3', 'Doctor')->getStyle('F3')->getFont()->setBold(true);
        $activeSheet->setCellValue('G3', 'City')->getStyle('G3')->getFont()->setBold(true);
        $activeSheet->setCellValue('H3', 'Centre')->getStyle('H3')->getFont()->setBold(true);
        $activeSheet->setCellValue('I3', 'Refund Note')->getStyle('I3')->getFont()->setBold(true);
        $activeSheet->setCellValue('J3', 'Amount')->getStyle('J3')->getFont()->setBold(true);
        $activeSheet->setCellValue('K3', 'Created At')->getStyle('K3')->getFont()->setBold(true);

        $activeSheet->setCellValue('A4', '');

        $counter = 5;
        if (count($reportData)) {
            $grefund = 0;
            foreach ($reportData as $reportappointmentdata) {

                $activeSheet->setCellValue('A'.$counter, $reportappointmentdata['name'])->getStyle('A'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('B'.$counter, number_format($reportappointmentdata['total_price'], 2))->getStyle('B'.$counter)->getFont()->setBold(true);
                $counter++;

                $activeSheet->setCellValue('A'.$counter, '');
                $counter++;

                $trefund = 0;

                foreach ($reportappointmentdata['refunds'] as $reportRow) {

                    $trefund += $reportRow['cash_amount'];

                    $activeSheet->setCellValue('A'.$counter, $reportRow['patient_id'])->getStyle('A'.$counter)->getFont();
                    $activeSheet->setCellValue('B'.$counter, $reportRow['patient_name'])->getStyle('B'.$counter)->getFont();
                    $activeSheet->setCellValue('C'.$counter, $reportRow['email'])->getStyle('C'.$counter)->getFont();
                    $activeSheet->setCellValue('D'.$counter, $reportRow['schedule'])->getStyle('D'.$counter)->getFont();
                    $activeSheet->setCellValue('E'.$counter, $reportRow['service'])->getStyle('E'.$counter)->getFont();
                    $activeSheet->setCellValue('F'.$counter, $reportRow['doctor'])->getStyle('F'.$counter)->getFont();
                    $activeSheet->setCellValue('G'.$counter, $reportRow['city'])->getStyle('G'.$counter)->getFont();
                    $activeSheet->setCellValue('H'.$counter, $reportRow['location'])->getStyle('H'.$counter)->getFont();
                    $activeSheet->setCellValue('I'.$counter, $reportRow['refund_note'])->getStyle('I'.$counter)->getFont();
                    $activeSheet->setCellValue('J'.$counter, number_format($reportRow['cash_amount'], 2))->getStyle('J'.$counter)->getFont();
                    $activeSheet->setCellValue('K'.$counter, ($reportRow['created_at']) ? \Carbon\Carbon::parse($reportRow['created_at'], null)->format('M j, Y') : '-')->getStyle('K'.$counter)->getFont();
                    $counter++;
                }
                $grefund += $trefund;
                $activeSheet->setCellValue('A'.$counter, 'Refund Total')->getStyle('A'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('K'.$counter, number_format($trefund, 2))->getStyle('K'.$counter)->getFont()->setBold(true);
                $counter++;
            }
            $activeSheet->setCellValue('A'.$counter, 'Grand Total')->getStyle('A'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('K'.$counter, number_format($grefund, 2))->getStyle('K'.$counter)->getFont()->setBold(true);
            $counter++;

        }
        $activeSheet->setCellValue('A'.$counter, '');
        $counter++;

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'List of client who claimed refund day base against non plans'.'.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');

    }

    /**
     * List of service that can be offer as complimentory
     *
     * @return \Illuminate\Http\Response
     */
    private static function ListofservicesthatCANbeofferedComplimentary(Request $request)
    {
        if (! Gate::allows('operations_reports_List_of_services_that_CAN_be_offered_Complimentary')) {
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

        $reportData = Operations::listofservicecanoffercomplimentory($request->all(), Auth::User()->account_id);

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.operations.lsitofservicesthatofferascomplimentory.report', compact('reportData', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.operations.lsitofservicesthatofferascomplimentory.reportprint', compact('reportData', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $content = view('admin.reports.operations.lsitofservicesthatofferascomplimentory.reportpdf', compact('reportData', 'start_date', 'end_date'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A4', 'landscape');

                return $pdf->stream('Highest Paid Client Report', 'landscape');
                break;
            case 'excel':
                self::listofservicethatcanbeofferascomplimentoryExcel($reportData, $request->get('month'), $request->get('year'));
                break;
            default:
                return view('admin.reports.operations.lsitofservicesthatofferascomplimentory.report', compact('reportData', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * List of service that can be offer as complimentory excel
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $year
     * @param  (mixed)  $month
     * @return \Illuminate\Http\Response
     */
    private static function listofservicethatcanbeofferascomplimentoryExcel($reportData, $month, $year)
    {

        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'For the month of '.\Carbon\Carbon::createFromDate($year, $month, 1)->format('M Y'));

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', 'ID')->getStyle('A3')->getFont()->setBold(true);
        $activeSheet->setCellValue('B3', 'Name')->getStyle('B3')->getFont()->setBold(true);
        $activeSheet->setCellValue('C3', 'Duration')->getStyle('C3')->getFont()->setBold(true);
        $activeSheet->setCellValue('D3', 'Complimentory')->getStyle('D3')->getFont()->setBold(true);
        $activeSheet->setCellValue('E3', 'Price')->getStyle('E3')->getFont()->setBold(true);

        $activeSheet->setCellValue('A4', '');

        $counter = 5;
        if (count($reportData)) {
            $grefund = 0;
            foreach ($reportData as $reportrow) {

                $activeSheet->setCellValue('A'.$counter, $reportrow['id'])->getStyle('A'.$counter)->getFont();
                $activeSheet->setCellValue('B'.$counter, $reportrow['name'])->getStyle('B'.$counter)->getFont();
                $activeSheet->setCellValue('C'.$counter, $reportrow['duration'].' min')->getStyle('C'.$counter)->getFont();
                $activeSheet->setCellValue('D'.$counter, $reportrow['complimentory'] == '1' ? 'Yes' : 'NO')->getStyle('D'.$counter)->getFont();
                $activeSheet->setCellValue('E'.$counter, number_format($reportrow['price'], 2))->getStyle('E'.$counter)->getFont();
                $counter++;
            }
        }
        $activeSheet->setCellValue('A'.$counter, '');
        $counter++;

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'List of servies that can be offer as complimentory'.'.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');

    }

    /**
     * List of service that can not be offer as complimentory
     *
     * @return \Illuminate\Http\Response
     */
    private static function ListofservicesthatCANnotbeofferedComplimentary(Request $request)
    {
        if (! Gate::allows('operations_reports_List_of_services_that_CAN_not_be_offered_Complimentary')) {
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

        $reportData = Operations::listofservicecannotoffercomplimentory($request->all(), Auth::User()->account_id);

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.operations.lsitofservicesthatoffernotascomplimentory.report', compact('reportData', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.operations.lsitofservicesthatoffernotascomplimentory.reportprint', compact('reportData', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $content = view('admin.reports.operations.lsitofservicesthatoffernotascomplimentory.reportpdf', compact('reportData', 'start_date', 'end_date'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A4', 'landscape');

                return $pdf->stream('Highest Paid Client Report', 'landscape');
                break;
            case 'excel':
                self::listofservicethatcannotbeofferascomplimentoryExcel($reportData, $request->get('month'), $request->get('year'));
                break;
            default:
                return view('admin.reports.operations.lsitofservicesthatoffernotascomplimentory.report', compact('reportData', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * List of service that can not be offer as complimentory excel
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $year
     * @param  (mixed)  $month
     * @return \Illuminate\Http\Response
     */
    private static function listofservicethatcannotbeofferascomplimentoryExcel($reportData, $month, $year)
    {

        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'For the month of '.\Carbon\Carbon::createFromDate($year, $month, 1)->format('M Y'));

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', 'ID')->getStyle('A3')->getFont()->setBold(true);
        $activeSheet->setCellValue('B3', 'Name')->getStyle('B3')->getFont()->setBold(true);
        $activeSheet->setCellValue('C3', 'Duration')->getStyle('C3')->getFont()->setBold(true);
        $activeSheet->setCellValue('D3', 'Complimentory')->getStyle('D3')->getFont()->setBold(true);
        $activeSheet->setCellValue('E3', 'Price')->getStyle('E3')->getFont()->setBold(true);

        $activeSheet->setCellValue('A4', '');

        $counter = 5;
        if (count($reportData)) {
            $grefund = 0;
            foreach ($reportData as $reportrow) {

                $activeSheet->setCellValue('A'.$counter, $reportrow['id'])->getStyle('A'.$counter)->getFont();
                $activeSheet->setCellValue('B'.$counter, $reportrow['name'])->getStyle('B'.$counter)->getFont();
                $activeSheet->setCellValue('C'.$counter, $reportrow['duration'].' min')->getStyle('C'.$counter)->getFont();
                $activeSheet->setCellValue('D'.$counter, $reportrow['complimentory'] == '1' ? 'Yes' : 'NO')->getStyle('D'.$counter)->getFont();
                $activeSheet->setCellValue('E'.$counter, number_format($reportrow['price'], 2))->getStyle('E'.$counter)->getFont();
                $counter++;
            }
        }
        $activeSheet->setCellValue('A'.$counter, '');
        $counter++;

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'List of servies that can not be offer as complimentory'.'.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');

    }

    /**
     * Conversion report for consultancy
     *
     * @return \Illuminate\Http\Response
     */
    private static function conversionreportconsultancy(Request $request)
    {
        if (! Gate::allows('operations_reports_conversion_report_consultancy')) {
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
        $reportData = Operations::conversionreportconsultancy($request->all(), Auth::User()->account_id);
        //dd($reportData);
        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.operations.conversionreportconsultancy.report', compact('reportData', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.operations.conversionreportconsultancy.reportprint', compact('reportData', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $content = view('admin.reports.operations.conversionreportconsultancy.reportpdf', compact('reportData', 'start_date', 'end_date'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A4', 'landscape');

                return $pdf->stream('Conversion Report', 'landscape');
                break;
            case 'excel':
                self::conversionreportconsultancyExcel($reportData, $start_date, $end_date);
                break;
            default:
                return view('admin.reports.operations.conversionreportconsultancy.report', compact('reportData', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * Conversion report for consultancy
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $year
     * @param  (mixed)  $month
     * @return \Illuminate\Http\Response
     */
    private static function conversionreportconsultancyExcel($reportData, $start_date, $end_date)
    {

        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From '.$start_date.' to '.$end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', '');

        $activeSheet->setCellValue('A4', 'Region')->getStyle('A4')->getFont()->setBold(true);
        $activeSheet->setCellValue('B4', 'Centre')->getStyle('B4')->getFont()->setBold(true);
        $activeSheet->setCellValue('C4', 'Booked GC\'s')->getStyle('C4')->getFont()->setBold(true);
        $activeSheet->setCellValue('D4', 'Arrived GC\'s')->getStyle('D4')->getFont()->setBold(true);
        $activeSheet->setCellValue('E4', 'Arrival Ratio(%)')->getStyle('E4')->getFont()->setBold(true);
        $activeSheet->setCellValue('F4', 'Converted GC\'s')->getStyle('F4')->getFont()->setBold(true);
        $activeSheet->setCellValue('G4', 'Converted Ratio(%)')->getStyle('G4')->getFont()->setBold(true);

        $activeSheet->setCellValue('A5', '');

        $counter = 6;

        if (count($reportData)) {
            $g_total = 0;
            $g_arrived = 0;
            $g_converted = 0;
            foreach ($reportData as $region) {
                $activeSheet->setCellValue('A'.$counter, $region['name'])->getStyle('A'.$counter)->getFont()->setBold(true);
                $counter++;
                $t_total = 0;
                $t_arrived = 0;
                $t_converted = 0;
                foreach ($region['location'] as $centre) {
                    $activeSheet->setCellValue('B'.$counter, $centre['location_name'])->getStyle('B'.$counter)->getFont();
                    $activeSheet->setCellValue('C'.$counter, $centre['booked'])->getStyle('C'.$counter)->getFont();
                    $activeSheet->setCellValue('D'.$counter, $centre['arrived'])->getStyle('D'.$counter)->getFont();
                    $activeSheet->setCellValue('E'.$counter, number_format($centre['arrival_ratio'], 2).'%')->getStyle('E'.$counter)->getFont();
                    $activeSheet->setCellValue('F'.$counter, $centre['converted'])->getStyle('F'.$counter)->getFont();
                    $activeSheet->setCellValue('G'.$counter, number_format($centre['conversion_ratio'], 2).'%')->getStyle('G'.$counter)->getFont();
                    $counter++;

                    $t_total += $centre['booked'];
                    $t_arrived += $centre['arrived'];
                    $t_converted += $centre['converted'];
                    $g_total += $centre['booked'];
                    $g_arrived += $centre['arrived'];
                    $g_converted += $centre['converted'];
                }
                $activeSheet->setCellValue('A'.$counter, 'Total')->getStyle('A'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('C'.$counter, number_format($t_total))->getStyle('C'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('D'.$counter, number_format($t_arrived))->getStyle('D'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('E'.$counter, ($t_total > 0 ? number_format(($t_arrived / $t_total) * 100, 2) : 0).'%')->getStyle('E'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('F'.$counter, number_format($t_converted))->getStyle('F'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('G'.$counter, ($t_arrived > 0 ? number_format(($t_converted / $t_arrived) * 100, 2) : 0).'%')->getStyle('G'.$counter)->getFont()->setBold(true);
                $counter++;
            }
            $activeSheet->setCellValue('A'.$counter, 'Grand Total')->getStyle('A'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('C'.$counter, number_format($g_total))->getStyle('C'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('D'.$counter, number_format($g_arrived))->getStyle('D'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('E'.$counter, ($g_total > 0 ? number_format(($g_arrived / $g_total) * 100, 2) : 0).'%')->getStyle('E'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('F'.$counter, number_format($g_converted))->getStyle('F'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('G'.$counter, ($g_arrived ? number_format(($g_converted / $g_arrived) * 100, 2) : 0).'%')->getStyle('G'.$counter)->getFont()->setBold(true);
            $counter++;
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'Conversion Report For Consultancy'.'.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');
    }

    /**
     * Conversion report for Treatments
     *
     * @return \Illuminate\Http\Response
     */
    private static function conversionreportTreatment(Request $request)
    {
        if (! Gate::allows('operations_reports_conversion_report_treatment')) {
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

        $reportData = Operations::conversionreporttreatment($request->all(), Auth::User()->account_id);

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.operations.conversionreporttreatment.report', compact('reportData', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.operations.conversionreporttreatment.reportprint', compact('reportData', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $content = view('admin.reports.operations.conversionreporttreatment.reportpdf', compact('reportData', 'start_date', 'end_date'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A4', 'landscape');

                return $pdf->stream('Conversion Report', 'landscape');
                break;
            case 'excel':
                self::conversionreporttreatmentExcel($reportData, $start_date, $end_date);
                break;
            default:
                return view('admin.reports.operations.conversionreporttreatment.report', compact('reportData', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * Conversion report for Treatment
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $year
     * @param  (mixed)  $month
     * @return \Illuminate\Http\Response
     */
    private static function conversionreporttreatmentExcel($reportData, $start_date, $end_date)
    {

        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From '.$start_date.' to '.$end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', '');

        $activeSheet->setCellValue('A4', 'Region')->getStyle('A4')->getFont()->setBold(true);
        $activeSheet->setCellValue('B4', 'Centre')->getStyle('B4')->getFont()->setBold(true);
        $activeSheet->setCellValue('C4', 'Booked T\'s')->getStyle('C4')->getFont()->setBold(true);
        $activeSheet->setCellValue('D4', 'Arrived T\'s')->getStyle('D4')->getFont()->setBold(true);
        $activeSheet->setCellValue('E4', 'Arrival Ratio(%)')->getStyle('E4')->getFont()->setBold(true);

        $activeSheet->setCellValue('A5', '');

        $counter = 6;
        if (count($reportData)) {
            $g_total = 0;
            $g_arrived = 0;
            foreach ($reportData as $region) {
                $activeSheet->setCellValue('A'.$counter, $region['name'])->getStyle('A'.$counter)->getFont();
                $counter++;
                $t_total = 0;
                $t_arrived = 0;
                foreach ($region['location'] as $centre) {
                    $activeSheet->setCellValue('B'.$counter, $centre['location_name'])->getStyle('B'.$counter)->getFont();
                    $activeSheet->setCellValue('C'.$counter, $centre['booked'])->getStyle('C'.$counter)->getFont();
                    $activeSheet->setCellValue('D'.$counter, $centre['arrived'])->getStyle('D'.$counter)->getFont();
                    $activeSheet->setCellValue('E'.$counter, number_format($centre['arrival_ratio'], 2).'%')->getStyle('E'.$counter)->getFont();
                    $counter++;

                    $t_total += $centre['booked'];
                    $t_arrived += $centre['arrived'];
                    $g_total += $centre['booked'];
                    $g_arrived += $centre['arrived'];
                }
                $activeSheet->setCellValue('A'.$counter, 'Total')->getStyle('A'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('C'.$counter, number_format($t_total))->getStyle('C'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('D'.$counter, number_format($t_arrived))->getStyle('D'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('E'.$counter, number_format(($t_arrived / $t_total) * 100, 2).'%')->getStyle('E'.$counter)->getFont()->setBold(true);
                $counter++;
            }
            $activeSheet->setCellValue('A'.$counter, 'Grand Total')->getStyle('A'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('C'.$counter, number_format($g_total))->getStyle('C'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('D'.$counter, number_format($g_arrived))->getStyle('D'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('E'.$counter, number_format(($g_arrived / $g_total) * 100, 2).'%')->getStyle('E'.$counter)->getFont()->setBold(true);
            $counter++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'Conversion Report For Treatment'.'.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');

    }

    /**
     * DAR Report
     *
     * @return \Illuminate\Http\Response
     */
    private static function darreport(Request $request)
    {
        if (! Gate::allows('operations_reports_dar_report')) {
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
        [$reportData, $locationData] = Operations::dar_report($request->all(), Auth::User()->account_id);

        $newtreatment = 0;
        $newconsultant = 0;

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.operations.darreport.report', compact('reportData', 'newtreatment', 'newconsultant', 'start_date', 'end_date', 'locationData'));
                break;
            case 'print':
                return view('admin.reports.operations.darreport.reportprint', compact('reportData', 'newtreatment', 'newconsultant', 'start_date', 'end_date', 'locationData'));
                break;
            case 'pdf':
                $content = view('admin.reports.operations.darreport.reportpdf', compact('reportData', 'newtreatment', 'newconsultant', 'start_date', 'end_date', 'locationData'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A2', 'landscape');

                return $pdf->stream('DAR Report', 'landscape');
                break;
            case 'excel':
                self::dar_report_excel($reportData, $start_date, $end_date, $locationData, 'DAR Report', 'dar');
                break;
            default:
                return view('admin.reports.operations.darreport.report', compact('reportData', 'newtreatment', 'newconsultant', 'start_date', 'end_date', 'locationData'));
                break;
        }
    }

    private static function agentReport(Request $request)
    {

        if (! Gate::allows('operations_reports_dar_report')) {
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
        [$reportData, $locationData] = Operations::agent_report($request->all(), Auth::User()->account_id);

        $newtreatment = 0;
        $newconsultant = 0;

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.operations.agentreport.report', compact('reportData', 'newtreatment', 'newconsultant', 'start_date', 'end_date', 'locationData'));
                break;
            case 'print':
                return view('admin.reports.operations.agentreport.reportprint', compact('reportData', 'newtreatment', 'newconsultant', 'start_date', 'end_date', 'locationData'));
                break;
            case 'pdf':
                $content = view('admin.reports.operations.agentreport.reportpdf', compact('reportData', 'newtreatment', 'newconsultant', 'start_date', 'end_date', 'locationData'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A2', 'landscape');

                return $pdf->stream('Agent Report', 'landscape');
                break;
            case 'excel':
                self::dar_report_excel($reportData, $start_date, $end_date, $locationData, 'Agent Report', 'agent');
                break;
            default:
                return view('admin.reports.operations.agentreport.report', compact('reportData', 'newtreatment', 'newconsultant', 'start_date', 'end_date', 'locationData'));
                break;
        }
    }

    private static function walkingReport(Request $request)
    {
        if (! Gate::allows('operations_reports_dar_report')) {
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
        [$reportData, $locationData] = Operations::walking_report($request->all(), Auth::User()->account_id);

        $newtreatment = 0;
        $newconsultant = 0;

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.operations.walkinreport.report', compact('reportData', 'newtreatment', 'newconsultant', 'start_date', 'end_date', 'locationData'));
                break;
            case 'print':
                return view('admin.reports.operations.walkinreport.reportprint', compact('reportData', 'newtreatment', 'newconsultant', 'start_date', 'end_date', 'locationData'));
                break;
            case 'pdf':
                $content = view('admin.reports.operations.walkinreport.reportpdf', compact('reportData', 'newtreatment', 'newconsultant', 'start_date', 'end_date', 'locationData'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A2', 'landscape');

                return $pdf->stream('Walkin Report', 'landscape');
                break;
            case 'excel':
                self::dar_report_excel($reportData, $start_date, $end_date, $locationData, 'Walkin Report', 'walkin');
                break;
            default:
                return view('admin.reports.operations.walkinreport.report', compact('reportData', 'newtreatment', 'newconsultant', 'start_date', 'end_date', 'locationData'));
                break;
        }
    }

    /**
     * Dar Report excel
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $year
     * @param  (mixed)  $month
     * @return \Illuminate\Http\Response
     */
    private static function dar_report_excel($reportData, $start_date, $end_date, $locationData, $reportName = 'DAR Report', $type = 'dar')
    {

        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From '.$start_date.' to '.$end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', '');

        $activeSheet->setCellValue('A5', 'Sr#')->getStyle('A5')->getFont()->setBold(true);
        $activeSheet->setCellValue('B5', 'Scheduled Date')->getStyle('B5')->getFont()->setBold(true);
        $activeSheet->setCellValue('C5', 'Client Id')->getStyle('C5')->getFont()->setBold(true);
        $activeSheet->setCellValue('D5', 'Client Name')->getStyle('D5')->getFont()->setBold(true);
        $activeSheet->setCellValue('E5', 'Appointment Type')->getStyle('E5')->getFont()->setBold(true);
        $activeSheet->setCellValue('F5', 'Practitioner')->getStyle('F5')->getFont()->setBold(true);
        $activeSheet->setCellValue('G5', 'Service')->getStyle('G5')->getFont()->setBold(true);
        $activeSheet->setCellValue('H5', 'Appointment Status Parent')->getStyle('H5')->getFont()->setBold(true);

        $activeSheet->setCellValue('A6', '');

        $counter = 6;
        if (count($reportData)) {
            $count = 1;
            $consultantbooked = 0;
            $treatmentbooked = 0;
            $consultantarrived = 0;
            $treatmentarrived = 0;
            foreach ($reportData as $reportsingle) {
                if ($reportsingle['appointment_slug'] == 'consultancy') {
                    $consultantbooked++;
                } elseif ($reportsingle['appointment_slug'] == 'treatment') {
                    $treatmentbooked++;
                }
                if ($reportsingle['appointment_slug'] == 'consultancy' && $reportsingle['appointment_status_isarrived'] == '1') {
                    $consultantarrived++;
                } elseif ($reportsingle['appointment_slug'] == 'treatment' && $reportsingle['appointment_status_isarrived'] == '1') {
                    $treatmentarrived++;
                }
                $activeSheet->setCellValue('A'.$counter, $count++)->getStyle('A'.$counter)->getFont();
                $activeSheet->setCellValue('B'.$counter, $reportsingle['schedule_date'])->getStyle('B'.$counter)->getFont();
                $activeSheet->setCellValue('C'.$counter, $reportsingle['id'])->getStyle('C'.$counter)->getFont();
                $activeSheet->setCellValue('D'.$counter, $reportsingle['client_name'])->getStyle('D'.$counter)->getFont();
                $activeSheet->setCellValue('E'.$counter, $reportsingle['appointment_type'])->getStyle('E'.$counter)->getFont();
                $activeSheet->setCellValue('F'.$counter, $reportsingle['doctor_name'])->getStyle('F'.$counter)->getFont();
                $activeSheet->setCellValue('G'.$counter, $reportsingle['service'])->getStyle('G'.$counter)->getFont();
                $activeSheet->setCellValue('H'.$counter, $reportsingle['appointment_status_parent'])->getStyle('H'.$counter)->getFont();

                $counter++;
            } /*end loop*/

            $activeSheet->setCellValue('A'.$counter, '');
            $counter++;

            if (isset($locationData) && count($locationData) > 0) {
                foreach ($locationData as $key => $location) {

                    if ($type == 'dar') { /*DAR type*/

                        $counter++;
                        $counter++;
                        $activeSheet->setCellValue('A'.$counter, $key)->getStyle('A'.$counter)->getFont()->setBold(true);
                        $counter++;
                        $counter++;

                        $activeSheet->setCellValue('A'.$counter, 'Consultation Booked')->getStyle('A'.$counter)->getFont()->setBold(true);
                        $activeSheet->setCellValue('B'.$counter, $location['consultantbooked'] ?? '')->getStyle('B'.$counter)->getFont()->setBold(true);
                        $counter++;

                        $activeSheet->setCellValue('A'.$counter, 'Consultation Arrived')->getStyle('A'.$counter)->getFont()->setBold(true);
                        $activeSheet->setCellValue('B'.$counter, $location['consultantarrived'] ?? '')->getStyle('B'.$counter)->getFont()->setBold(true);
                        $counter++;

                        $activeSheet->setCellValue('A'.$counter, 'Total Walkin')->getStyle('A'.$counter)->getFont()->setBold(true);
                        $activeSheet->setCellValue('B'.$counter, $location['walking'] ?? '')->getStyle('B'.$counter)->getFont()->setBold(true);
                        $counter++;

                        if ($consultantbooked > 0) {
                            $ratio = '00.00 %';
                            if (isset($location['consultantbooked']) && isset($location['consultantarrived'])) {
                                $booking_without_walkin = $location['consultantbooked'] - $location['walking'];
                                $arrived_without_walkin = $location['consultantarrived'] - $location['walking'];
                                $ratio = number_format(($arrived_without_walkin / $booking_without_walkin) * 100, 2).'%';
                            }

                            $activeSheet->setCellValue('A'.$counter, 'Consultation Arrival Ratio')->getStyle('A'.$counter)->getFont()->setBold(true);
                            $activeSheet->setCellValue('B'.$counter, $ratio)->getStyle('B'.$counter)->getFont()->setBold(true);
                            $counter++;
                        }

                    } elseif ($type == 'agent') { /*For agent*/

                        $counter++;
                        $counter++;
                        $activeSheet->setCellValue('A'.$counter, $key)->getStyle('A'.$counter)->getFont()->setBold(true);
                        $counter++;
                        $counter++;

                        $activeSheet->setCellValue('A'.$counter, 'Consultation Booked')->getStyle('A'.$counter)->getFont()->setBold(true);
                        $activeSheet->setCellValue('B'.$counter, $location['consultantbooked'] ?? '')->getStyle('B'.$counter)->getFont()->setBold(true);
                        $counter++;

                        $activeSheet->setCellValue('A'.$counter, 'Consultation Arrived')->getStyle('A'.$counter)->getFont()->setBold(true);
                        $activeSheet->setCellValue('B'.$counter, $location['consultantarrived'] ?? '')->getStyle('B'.$counter)->getFont()->setBold(true);
                        $counter++;

                        if ($consultantbooked > 0) {
                            $ratio = '00.00 %';
                            if (isset($location['consultantbooked']) && isset($location['consultantarrived'])) {
                                $ratio = number_format(($location['consultantarrived'] / $location['consultantbooked']) * 100, 2).'%';
                            }

                            $activeSheet->setCellValue('A'.$counter, 'Consultation Arrival Ratio')->getStyle('A'.$counter)->getFont()->setBold(true);
                            $activeSheet->setCellValue('B'.$counter, $ratio)->getStyle('B'.$counter)->getFont()->setBold(true);
                            $counter++;
                        }

                    } elseif ($type == 'walkin') { /*Walkin*/

                        $counter++;
                        $counter++;
                        $activeSheet->setCellValue('A'.$counter, $key)->getStyle('A'.$counter)->getFont()->setBold(true);
                        $counter++;
                        $counter++;

                        $activeSheet->setCellValue('A'.$counter, 'Total Walkin')->getStyle('A'.$counter)->getFont()->setBold(true);
                        $activeSheet->setCellValue('B'.$counter, $location['walkin'] ?? '')->getStyle('B'.$counter)->getFont()->setBold(true);
                        $counter++;

                    }

                }
            }
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.$reportName.'.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');

    }

    /**
     * DAR Report
     *
     * @return \Illuminate\Http\Response
     */
    private static function complimentoryreport(Request $request)
    {
        if (! Gate::allows('operations_reports_complimentory_report')) {
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
        //        $filters['users'] = User::get()->getDictionary();
        $filters['users'] = User::getAllRecords(Auth::User()->account_id)->getDictionary();
        $filters['cities'] = Cities::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['locations'] = Locations::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['appointment_types'] = AppointmentTypes::getAllRecords(Auth::User()->account_id)->getDictionary();
        $filters['appointment_statuses'] = AppointmentStatuses::getAllRecordsDictionary(Auth::User()->account_id);
        $filters['doctors'] = Doctors::getAll(Auth::User()->account_id)->getDictionary();
        $filters['services'] = Services::getAllRecordsDictionary(Auth::User()->account_id);

        $reportData = Operations::complimentoryreport($request->all(), Auth::User()->account_id);

        foreach ($reportData as $key => $reportRow) {

            $users = User::find($reportRow->patient_id);

            $reportData[$key]['Patient_phone'] = $users->phone;
            $reportData[$key]['Patient_email'] = $users->email;

        }

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.operations.complimentorytreatment.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.operations.complimentorytreatment.reportprint', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $content = view('admin.reports.operations.complimentorytreatment.reportpdf', compact('reportData', 'filters', 'start_date', 'end_date'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A3', 'landscape');

                return $pdf->stream('Complimentroy Treatments Report', 'landscape');
                break;
            case 'excel':
                self::complimentorytreatmentexcel($reportData, $start_date, $end_date, $filters);
                break;
            default:
                return view('admin.reports.operations.complimentorytreatment.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * List of service that can be offer as complimentory excel
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $year
     * @param  (mixed)  $month
     * @return \Illuminate\Http\Response
     */
    private static function complimentorytreatmentexcel($reportData, $start_date, $end_date, $filters)
    {

        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', $start_date.' to '.$end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', '');

        $activeSheet->setCellValue('A4', 'ID')->getStyle('A4')->getFont()->setBold(true);
        $activeSheet->setCellValue('B4', 'Client Name')->getStyle('B4')->getFont()->setBold(true);
        $activeSheet->setCellValue('C4', 'Email')->getStyle('C4')->getFont()->setBold(true);
        $activeSheet->setCellValue('D4', 'City')->getStyle('D4')->getFont()->setBold(true);
        $activeSheet->setCellValue('E4', 'Centre')->getStyle('E4')->getFont()->setBold(true);
        $activeSheet->setCellValue('F4', 'Appointment Type')->getStyle('F4')->getFont()->setBold(true);
        $activeSheet->setCellValue('G4', 'Appointment Status')->getStyle('G4')->getFont()->setBold(true);
        $activeSheet->setCellValue('H4', 'Doctor')->getStyle('H4')->getFont()->setBold(true);
        $activeSheet->setCellValue('I4', 'Service')->getStyle('I4')->getFont()->setBold(true);
        $activeSheet->setCellValue('J4', 'Created At')->getStyle('J4')->getFont()->setBold(true);
        $activeSheet->setCellValue('K4', 'Scheduled')->getStyle('K4')->getFont()->setBold(true);
        $activeSheet->setCellValue('L4', 'Invoice  Status')->getStyle('L4')->getFont()->setBold(true);

        $activeSheet->setCellValue('A5', '');

        $counter = 6;
        if (count($reportData)) {
            $grefund = 0;
            foreach ($reportData as $reportRow) {

                $activeSheet->setCellValue('A'.$counter, $reportRow->patient_id)->getStyle('A'.$counter)->getFont();
                $activeSheet->setCellValue('B'.$counter, $reportRow->name)->getStyle('B'.$counter)->getFont();
                $activeSheet->setCellValue('C'.$counter, $reportRow->email)->getStyle('C'.$counter)->getFont();
                $activeSheet->setCellValue('D'.$counter, $filters['cities'][$reportRow->city_id]->name)->getStyle('D'.$counter)->getFont();
                $activeSheet->setCellValue('E'.$counter, $filters['locations'][$reportRow->location_id]->name)->getStyle('E'.$counter)->getFont();
                $activeSheet->setCellValue('F'.$counter, $filters['appointment_types'][$reportRow->appointment_type_id]->name)->getStyle('F'.$counter)->getFont();
                $activeSheet->setCellValue('G'.$counter, $filters['appointment_statuses'][$reportRow->base_appointment_status_id]->name)->getStyle('G'.$counter)->getFont();
                $activeSheet->setCellValue('H'.$counter, $filters['doctors'][$reportRow->doctor_id]->name)->getStyle('H'.$counter)->getFont();
                $activeSheet->setCellValue('I'.$counter, $filters['services'][$reportRow->service_id]->name)->getStyle('I'.$counter)->getFont();
                $activeSheet->setCellValue('J'.$counter, \Carbon\Carbon::parse($reportRow->created_at)->format('M j, Y H:i A'))->getStyle('J'.$counter)->getFont();
                $activeSheet->setCellValue('K'.$counter, ($reportRow->scheduled_date) ? \Carbon\Carbon::parse($reportRow->scheduled_date, null)->format('M j, Y').' at '.\Carbon\Carbon::parse($reportRow->scheduled_time, null)->format('h:i A') : '-')->getStyle('K'.$counter)->getFont();
                $activeSheet->setCellValue('L'.$counter, $reportRow->invoices)->getStyle('L'.$counter)->getFont();
                $counter++;
            }
        }
        $activeSheet->setCellValue('A'.$counter, '');
        $counter++;

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'Complimentory Treatment'.'.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');

    }

    /**
     * DTR report
     *
     * @return \Illuminate\Http\Response
     */
    private static function dtrreport(Request $request)
    {
        if (! Gate::allows('operations_reports_dtr_report')) {
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
        $filters['doctors'] = Doctors::getAll(Auth::User()->account_id)->getDictionary();
        $filters['services'] = Services::getAllRecordsDictionary(Auth::User()->account_id);
        $reportData = Operations::dtrreport($request->all(), Auth::User()->account_id, $filters);

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.operations.dtr_report.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'print':
                return view('admin.reports.operations.dtr_report.reportprint', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
            case 'pdf':
                $content = view('admin.reports.operations.dtr_report.reportpdf', compact('reportData', 'start_date', 'end_date'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A4', 'landscape');

                return $pdf->stream('DTR Report', 'landscape');
                break;
            case 'excel':
                self::dtrreportExcel($reportData, $request->get('month'), $request->get('year'));
                break;
            default:
                return view('admin.reports.operations.conversionreport.report', compact('reportData', 'filters', 'start_date', 'end_date'));
                break;
        }
    }

    /**
     * DTR Report
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $year
     * @param  (mixed)  $month
     * @return \Illuminate\Http\Response
     */
    private static function dtrreportExcel($reportData, $month, $year)
    {

        $spreadsheet = new Spreadsheet();  /*----Spreadsheet object-----*/
        $Excel_writer = new Xlsx($spreadsheet);  /*----- Excel (Xls) Object*/
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'For the month of '.\Carbon\Carbon::createFromDate($year, $month, 1)->format('M Y'));

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', '');

        $activeSheet->setCellValue('A4', 'Centre')->getStyle('A4')->getFont()->setBold(true);
        $activeSheet->setCellValue('B4', 'Region')->getStyle('B4')->getFont()->setBold(true);
        $activeSheet->setCellValue('C4', 'City')->getStyle('C4')->getFont()->setBold(true);
        $activeSheet->setCellValue('D4', 'Doctor')->getStyle('D4')->getFont()->setBold(true);
        $activeSheet->setCellValue('E4', 'Service')->getStyle('E4')->getFont()->setBold(true);
        $activeSheet->setCellValue('F4', 'Target Service')->getStyle('F4')->getFont()->setBold(true);
        $activeSheet->setCellValue('G4', 'Target Service Completed')->getStyle('G4')->getFont()->setBold(true);
        $activeSheet->setCellValue('H4', 'Ratio')->getStyle('H4')->getFont()->setBold(true);
        $activeSheet->setCellValue('I4', 'Remaining Days')->getStyle('I4')->getFont()->setBold(true);

        $activeSheet->setCellValue('A4', '');

        $counter = 5;

        if (count($reportData)) {

            $g_target_service = 0;
            $g_target_service_complete = 0;

            foreach ($reportData as $reportlocationdata) {

                $activeSheet->setCellValue('A'.$counter, $reportlocationdata['location'])->getStyle('A'.$counter)->getFont();
                $activeSheet->setCellValue('B'.$counter, $reportlocationdata['region'])->getStyle('B'.$counter)->getFont();
                $activeSheet->setCellValue('C'.$counter, $reportlocationdata['city'])->getStyle('C'.$counter)->getFont();
                $counter++;

                $target_service = 0;
                $target_service_complete = 0;

                foreach ($reportlocationdata['doctors'] as $reportRow) {

                    $target_service += $reportRow['target_service_count'];
                    $target_service_complete += $reportRow['target_service_done'];
                    $g_target_service += $reportRow['target_service_count'];
                    $g_target_service_complete += $reportRow['target_service_done'];

                    $activeSheet->setCellValue('D'.$counter, $reportRow['doctor'])->getStyle('D'.$counter)->getFont();
                    $activeSheet->setCellValue('E'.$counter, $reportRow['service'])->getStyle('E'.$counter)->getFont();
                    $activeSheet->setCellValue('F'.$counter, $reportRow['target_service_count'])->getStyle('F'.$counter)->getFont();
                    $activeSheet->setCellValue('G'.$counter, $reportRow['target_service_done'])->getStyle('G'.$counter)->getFont();
                    $activeSheet->setCellValue('H'.$counter, number_format($reportRow['target_complete_ratio'], 1).'%')->getStyle('H'.$counter)->getFont();
                    $activeSheet->setCellValue('I'.$counter, $reportRow['remaining_day'])->getStyle('I'.$counter)->getFont();
                    $counter++;
                }

                $activeSheet->setCellValue('A'.$counter, '');
                $counter++;

                $activeSheet->setCellValue('D'.$counter, 'Total')->getStyle('D'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('F'.$counter, $target_service)->getStyle('F'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('G'.$counter, $target_service_complete)->getStyle('G'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('H'.$counter, number_format(($target_service_complete / $target_service) * 100, 1).'%')->getStyle('H'.$counter)->getFont()->setBold(true);
                $counter++;
            }
            $activeSheet->setCellValue('A'.$counter, '');
            $counter++;

            $activeSheet->setCellValue('D'.$counter, 'Grand Total')->getStyle('D'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('F'.$counter, $g_target_service)->getStyle('F'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('G'.$counter, $g_target_service_complete)->getStyle('G'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('H'.$counter, number_format(($g_target_service_complete / $g_target_service) * 100, 1).'%')->getStyle('H'.$counter)->getFont()->setBold(true);
            $counter++;

        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'DTR Report'.'.xlsx"'); /*-- $filename is  xsl filename ---*/
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');

    }

    public function reportLoadConverted(Request $request)
    {
        // Get arrived and converted appointment status IDs
        $arrivedStatus = \App\Models\AppointmentStatuses::where(['account_id' => Auth::User()->account_id, 'is_arrived' => 1])->first();
        $convertedStatus = \App\Models\AppointmentStatuses::where(['account_id' => Auth::User()->account_id, 'is_converted' => 1])->first();
        $arrivedStatusId = $arrivedStatus ? $arrivedStatus->id : 2;
        $convertedStatusId = $convertedStatus ? $convertedStatus->id : null;
        $statusIds = $convertedStatusId ? [$arrivedStatusId, $convertedStatusId] : [$arrivedStatusId];

        $where = [];
        $where[] = [
            'appointments.appointment_type_id',
            '=',
            1,
        ];
        if ($request->location_id && $request->location_id) {
            $where[] = [
                'appointments.location_id',
                '=',
                $request->location_id,
            ];
        }
        if ($request->doctor_id && $request->doctor_id != '') {
            $where[] = [
                'appointments.doctor_id',
                '=',
                $request->doctor_id,
            ];
        }
        if ($request->service_id && $request->service_id != '') {
            $where[] = [
                'appointments.service_id',
                '=',
                $request->service_id,
            ];
        }
        if (isset($request->date_range) && $request->date_range) {
            $date_range = explode(' - ', $request->date_range);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        if (isset($request->date_range) && $request->date_range) {
            $patients = DB::table('users')
                ->select(DB::raw('SUM(package_advances.cash_amount) as cash_amount_test'), 'users.*', 'appointments.doctor_id', 'appointments.location_id', 'appointments.service_id', 'appointments.scheduled_date', 'appointments.id as apt_id')
                ->join('appointments', 'appointments.patient_id', '=', 'users.id')
                ->leftJoin('package_advances', function ($join) {
                    $join->on('package_advances.patient_id', '=', 'users.id');
                    $join->where('package_advances.cash_flow', '=', 'in');
                })
                ->where($where)
                ->whereIn('appointments.appointment_status_id', $statusIds)
                ->whereBetween('appointments.scheduled_date', [$start_date, $end_date])
                ->groupBy('users.id')
                ->havingRaw('cash_amount_test < 1')
                ->orderBy('appointments.scheduled_date', 'desc')
                ->get();
        }

        return view('admin.reports.arrived_not_converted', compact('patients'));
    }
}
