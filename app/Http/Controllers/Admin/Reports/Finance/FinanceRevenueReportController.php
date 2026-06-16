<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reports\Finance;

use App\Helpers\ACL;
use App\Helpers\Explode_Multi_select;
use App\Helpers\NodesTree;
use App\Http\Controllers\Controller;
use App\Models\AppointmentStatuses;
use App\Models\AppointmentTypes;
use App\Models\Cities;
use App\Models\Discounts;
use App\Models\Doctors;
use App\Models\Locations;
use App\Models\Regions;
use App\Models\Services;
use App\Models\User;
use App\Reports\Finanaces;
use App\Services\Reports\Concerns\ParsesDateRange;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class FinanceRevenueReportController extends Controller
{
    use ParsesDateRange;

    /**
     * Display a listing filter for finanace report.
     */
    public function report(): View
    {
        if (! Gate::allows('finance_general_revenue_reports_manage')) {
            return abort(401);
        }
        // The "all" slug is a system-managed service used to represent
        // "every service" in legacy flows. It may be missing (deleted or
        // never seeded on this account) — guard before dereferencing.
        $allserviceslug = Services::where(['slug' => 'all'])->isActive()->first();
        $allServiceName = $allserviceslug?->name;

        $parentGroups = new NodesTree;
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::user()->account_id);
        $parentGroups->toList($parentGroups, -1);
        $services = $parentGroups->nodeList;

        // Drop the synthetic "all" entry from the dropdown (it's only used
        // internally to represent "every service"). Also drop any orphaned
        // nodes the tree produced with an empty name (skipped rows — see
        // NodesTree::build null-guard) so they don't render blank options.
        foreach ($services as $key => $ser) {
            if (! $key) {
                continue;
            }
            $name = $ser['name'] ?? null;
            if ($name === null || trim((string) $name) === '') {
                unset($services[$key]);
                continue;
            }
            if ($allServiceName !== null && $name === $allServiceName) {
                unset($services[$key]);
            }
        }

        $employees = User::getAllActiveEmployeeRecords(Auth::user()->account_id, ACL::getUserCentres())->pluck('name', 'id');

        $operators = User::getAllActivePractionersRecords(Auth::user()->account_id, ACL::getUserCentres())->pluck('name', 'id');

        $select_All = ['' => 'All'];

        $users = array_merge($select_All, $employees->toArray(), $operators->toArray());

        $operators->prepend('All', '');

        $locations = Locations::getActiveSorted(ACL::getUserCentres());
        if (! Auth::user()->hasRole('FDM')) {
            $locations->prepend('All', '');
        }

        $locations_com = Locations::getActiveSorted(ACL::getUserCentres());
        if (! Auth::user()->hasRole('FDM')) {
            $locations_com->prepend('All', '');
        }

        $appointment_types = AppointmentTypes::getAllRecords(Auth::user()->account_id)->pluck('name', 'id');
        $appointment_types->prepend('All', '');

        $regions = Regions::getActiveSorted(ACL::getUserRegions());
        $regions->prepend('All', '');

        $cities = Cities::getActiveSorted(ACL::getUserCities());
        $cities->prepend('All', '');

        return view('admin.reports.accountsalesreport.index', compact('locations', 'services', 'users', 'appointment_types', 'regions', 'locations_com', 'operators', 'cities'));
    }

    public function serviceBarChart(Request $request, $service_id): View
    {
        $service = Services::findOrFail($service_id);
        $start_date = $request->query('start_date'); // e.g. '2025-05-20'
        $end_date = $request->query('end_date'); // e.g. '2025-05-21'
        $locationId = $request->location_id;

        // Get arrived and converted appointment status IDs
        $arrivedStatus = AppointmentStatuses::where(['account_id' => Auth::user()->account_id, 'is_arrived' => 1])->first();
        $convertedStatus = AppointmentStatuses::where(['account_id' => Auth::user()->account_id, 'is_converted' => 1])->first();
        $arrivedStatusId = $arrivedStatus ? $arrivedStatus->id : 2;
        $convertedStatusId = $convertedStatus?->id;
        $statusIds = $convertedStatusId ? [$arrivedStatusId, $convertedStatusId] : [$arrivedStatusId];

        $query = DB::table('appointments')
            ->join('invoices', 'invoices.appointment_id', '=', 'appointments.id')
            ->where('appointments.appointment_type_id', 2)
            ->whereIn('appointments.appointment_status_id', $statusIds)

            ->where('appointments.service_id', $service_id);
        if ($start_date && $end_date) {
            $query->whereBetween('appointments.scheduled_date', [$start_date, $end_date]);
        }
        if ($locationId) {
            $query->whereIn('appointments.location_id', $locationId);
        }
        $soldServicesQuery = $query
            ->select('appointments.location_id', DB::raw('COUNT(*) as total_sold'))
            ->groupBy('appointments.location_id')
            ->get();

        $locations = Locations::whereIn('id', $soldServicesQuery->pluck('location_id'))->get()->keyBy('id');

        $labels = [];
        $values = [];

        foreach ($soldServicesQuery as $data) {
            $locationName = $locations[$data->location_id]->name ?? 'Unknown';

            // Remove the word "ALLURA" (case-insensitive)
            $cleanName = preg_replace('/\bALLURA\b/i', '', $locationName);

            // Optionally trim whitespace
            $labels[] = trim($cleanName);
            $values[] = $data->total_sold;
        }

        return view('admin.reports.service_barchart', compact('service', 'labels', 'values'));
    }

    /**
     * Load Sales By Services category.
     */
    public function salesbyservicecategory(Request $request): View
    {

        if (! Gate::allows('finance_general_revenue_reports_sales_by_service_category')) {
            return abort(401);
        }

        [$start_date, $end_date] = $this->parseDateRange($request->get('date_range'));
        $filters = [];

        $users = User::getAllRecords(Auth::user()->account_id)->pluck('name', 'id');
        $users->prepend('All', '');

        $filters['locations'] = Locations::getAllRecordsDictionary(Auth::user()->account_id);
        $filters['services'] = Services::getAllRecordsDictionaryWithoutAll(Auth::user()->account_id);
        $filters['users'] = User::getAllRecords(Auth::user()->account_id)->getDictionary();
        $filters['servicesheads'] = Services::where([['active', '=', '1'], ['parent_id', '=', '0'], ['slug', '!=', 'all']])->orderBy('name', 'asc')->get()->getDictionary();
        $filters['doctors'] = Doctors::getAll(Auth::user()->account_id)->getDictionary();

        $reportData = \App\Reports\Invoices::getSalesbyServiceCategory($request->all(), $filters);
        foreach ($reportData as $rowpackage) {
            $filters['reportData'] = $reportData;
        }
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
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $start_date
     * @param  (mixed)  $end_date
     * @return Response
     */
    private static function salesByServiceCategoryExcel($reportData, $start_date, $end_date): mixed
    {
        $spreadsheet = new Spreadsheet;  /* ----Spreadsheet object----- */
        $Excel_writer = new Xlsx($spreadsheet);  /* ----- Excel (Xls) Object */
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From '.$start_date.' to '.$end_date);

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
                $activeSheet->setCellValue('A'.$counter, $reportpackagedata['name'])->getStyle('A'.$counter)->getFont()->setBold(true);
                $counter++;
                $qty = 0;
                $serviceheadtotal = 0;
                foreach ($reportpackagedata['records'] as $reportRow) {
                    $qty += $reportRow['qty'];
                    $serviceheadtotal += $reportRow['amount'];

                    $activeSheet->setCellValue('B'.$counter, $reportRow['name']);
                    $activeSheet->setCellValue('C'.$counter, number_format($reportRow['qty']));
                    $activeSheet->setCellValue('D'.$counter, number_format($reportRow['amount'], 2));
                    $counter++;
                }
                $grandqty += $qty;
                $servicegrandtotal += $serviceheadtotal;

                $activeSheet->setCellValue('A'.$counter, $reportpackagedata['name'])->getStyle('A'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('B'.$counter, 'Total')->getStyle('B'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('C'.$counter, number_format($qty))->getStyle('C'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('D'.$counter, number_format($serviceheadtotal, 2))->getStyle('D'.$counter)->getFont()->setBold(true);
                $counter++;
            }
            $activeSheet->setCellValue('A'.$counter, '');
            $activeSheet->setCellValue('B'.$counter, '');
            $activeSheet->setCellValue('C'.$counter, '');
            $activeSheet->setCellValue('D'.$counter, '');

            $counter++;
            $activeSheet->setCellValue('A'.$counter, '');
            $activeSheet->setCellValue('B'.$counter, 'Grand Total')->getStyle('B'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('C'.$counter, number_format($grandqty))->getStyle('C'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('D'.$counter, number_format($servicegrandtotal, 2))->getStyle('D'.$counter)->getFont()->setBold(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'Sales By Service Category'.'.xlsx"'); /* -- $filename is  xsl filename --- */
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');
    }

    /**
     * Load General Revenue Report.
     */
    /** @deprecated Moved to App\Services\Reports\Revenue\GeneralRevenueDetailReport. Called via GeneralSalesReportController. */
    public function generalrevenuereportdetail(Request $request): View
    {

        if (is_array($request->location_id_com) && count($request->location_id_com) > 1) {
            $location[] = implode(',', $request->location_id_com);
        } else {
            $location = $request->location_id_com;
        }

        if (! Gate::allows('finance_general_revenue_reports_general_revenue__detail_report')) {
            return abort(401);
        }
        [$start_date, $end_date] = $this->parseDateRange($request->get('date_range'));

        if ($request->medium_type == 'web' && $location && ! empty($location)) {

            $report_data = Finanaces::generalrevenuereportdetail($request->all(), Auth::user()->account_id);
        } elseif ($request->medium_type != 'web' && $location) {

            $location_id_com = Explode_Multi_select::explode($location);
            $request->merge([
                'location_id_com' => $location_id_com,
            ]);
            $report_data = Finanaces::generalrevenuereportdetail($request->all(), Auth::user()->account_id);
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
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $start_date
     * @param  (mixed)  $end_date
     * @return Response
     */
    private static function GeneralRevenueReportExcel($report_data, $total_revenue_cash_in, $total_revenue_card_in, $total_revenue_bank_in, $total_refund, $total_revenue, $start_date, $end_date): mixed
    {

        $spreadsheet = new Spreadsheet;  /* ----Spreadsheet object----- */
        $Excel_writer = new Xlsx($spreadsheet);  /* ----- Excel (Xls) Object */
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From '.$start_date.' to '.$end_date);

        $activeSheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $activeSheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));

        $activeSheet->setCellValue('A3', '');

        $activeSheet->setCellValue('A4', 'ID')->getStyle('A4')->getFont()->setBold(true);
        $activeSheet->setCellValue('B4', 'Patient Name')->getStyle('B4')->getFont()->setBold(true);
        $activeSheet->setCellValue('C4', 'Gender')->getStyle('C4')->getFont()->setBold(true);
        $activeSheet->setCellValue('D4', 'Transaction Type')->getStyle('D4')->getFont()->setBold(true);
        $activeSheet->setCellValue('E4', 'Revenue Cash In')->getStyle('E4')->getFont()->setBold(true);
        $activeSheet->setCellValue('F4', 'Revenue Card In')->getStyle('F4')->getFont()->setBold(true);
        $activeSheet->setCellValue('G4', 'Revenue Bank/Wire In')->getStyle('G4')->getFont()->setBold(true);
        $activeSheet->setCellValue('H4', 'Refund/Out')->getStyle('H4')->getFont()->setBold(true);
        $activeSheet->setCellValue('I4', 'Created At')->getStyle('I4')->getFont()->setBold(true);

        $activeSheet->setCellValue('A5', '');

        $total_revenue_cash_location = 0;
        $total_revenue_card_location = 0;
        $total_revenue_bank_location = 0;
        $total_refund_location = 0;

        $counter = 6;
        if ($report_data) {
            foreach ($report_data as $reportlocation) {

                $activeSheet->setCellValue('A'.$counter, $reportlocation['name'])->getStyle('A'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('B'.$counter, $reportlocation['city'])->getStyle('B'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('C'.$counter, $reportlocation['region'])->getStyle('C'.$counter)->getFont()->setBold(true);
                $counter++;

                $activeSheet->setCellValue('A'.$counter, '');
                $counter++;

                foreach ($reportlocation['revenue_data'] as $reportRow) {

                    $total_revenue_cash_location += $reportRow['revenue_cash_in'] ? $reportRow['revenue_cash_in'] : 0;
                    $total_revenue_card_location += $reportRow['revenue_card_in'] ? $reportRow['revenue_card_in'] : 0;
                    $total_revenue_bank_location += $reportRow['revenue_bank_in'] ? $reportRow['revenue_bank_in'] : 0;
                    $total_refund_location += $reportRow['refund_out'] ? $reportRow['refund_out'] : 0;

                    $activeSheet->setCellValue('A'.$counter, $reportRow['patient_id']);
                    $activeSheet->setCellValue('B'.$counter, $reportRow['patient']);
                    $activeSheet->setCellValue('C'.$counter, $reportRow['gender']);
                    $activeSheet->setCellValue('D'.$counter, $reportRow['transtype']);
                    if ($reportRow['revenue_cash_in']) {
                        $activeSheet->setCellValue('E'.$counter, number_format($reportRow['revenue_cash_in'], 2));
                    }
                    if ($reportRow['revenue_card_in']) {
                        $activeSheet->setCellValue('F'.$counter, number_format($reportRow['revenue_card_in'], 2));
                    }
                    if ($reportRow['revenue_bank_in']) {
                        $activeSheet->setCellValue('G'.$counter, number_format($reportRow['revenue_bank_in'], 2));
                    }
                    if ($reportRow['refund_out']) {
                        $activeSheet->setCellValue('H'.$counter, number_format($reportRow['refund_out'], 2));
                    }
                    $activeSheet->setCellValue('I'.$counter, $reportRow['created_at']);
                    $counter++;
                }
                $activeSheet->setCellValue('A'.$counter, '');
                $counter++;

                $activeSheet->setCellValue('A'.$counter, $reportlocation['name'])->getStyle('A'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('B'.$counter, 'Total')->getStyle('B'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('D'.$counter, number_format($total_revenue_cash_location, 2))->getStyle('D'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('E'.$counter, number_format($total_revenue_card_location, 2))->getStyle('E'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('F'.$counter, number_format($total_revenue_bank_location, 2))->getStyle('F'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('G'.$counter, number_format($total_refund_location, 2))->getStyle('G'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('H'.$counter, number_format(($total_revenue_cash_location + $total_revenue_card_location + $total_revenue_bank_location) - $total_refund_location, 2))->getStyle('H'.$counter)->getFont()->setBold(true);

                $counter++;

                $total_revenue_cash_location = 0;
                $total_revenue_card_location = 0;
                $total_revenue_bank_location = 0;
                $total_refund_location = 0;
            }
            $activeSheet->setCellValue('A'.$counter, '');
            $counter++;

            $activeSheet->setCellValue('A'.$counter, 'Revenue Cash In')->getStyle('A'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('B'.$counter, number_format($total_revenue_cash_in, 2));
            $counter++;

            $activeSheet->setCellValue('A'.$counter, 'Revenue Card In')->getStyle('A'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('B'.$counter, number_format($total_revenue_card_in, 2));
            $counter++;

            $activeSheet->setCellValue('A'.$counter, 'Revenue Bank/Wire In')->getStyle('A'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('B'.$counter, number_format($total_revenue_bank_in, 2));
            $counter++;

            $activeSheet->setCellValue('A'.$counter, 'Total Revenue')->getStyle('A'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('B'.$counter, number_format($total_revenue, 2));
            $counter++;

            $activeSheet->setCellValue('A'.$counter, 'Refund')->getStyle('A'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('B'.$counter, number_format($total_refund, 2));
            $counter++;

            $activeSheet->setCellValue('A'.$counter, 'In Hand Balance')->getStyle('A'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('B'.$counter, number_format(($total_revenue - $total_refund), 2));
            $counter++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'GeneralRevenueReport'.'.xlsx"'); /* -- $filename is  xsl filename --- */
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');
    }

    /**
     * Load General Revenue Report.
     */
    /** @deprecated Moved to App\Services\Reports\Revenue\GeneralRevenueSummaryReport. Called via GeneralSalesReportController. */
    public function generalrevenuereportsummary(Request $request): View
    {
        if (! Gate::allows('finance_general_revenue_reports_general_revenue__summary_report')) {
            return abort(401);
        }
        [$start_date, $end_date] = $this->parseDateRange($request->get('date_range'));
        $report_data = Finanaces::generalrevenuereportsummary($request->all(), Auth::user()->account_id);

        $total_revenue_cash_in = 0;
        $total_revenue_card_in = 0;
        $total_revenue_bank_in = 0;
        $total_revenue_male_in = 0;
        $total_revenue_female_in = 0;
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
                if ($reportrevenue['male_revenue']) {
                    $total_revenue_male_in += $reportrevenue['male_revenue'];
                }
                if ($reportrevenue['female_revenue']) {
                    $total_revenue_female_in += $reportrevenue['female_revenue'];
                }
            }
        }
        $total_revenue = $total_revenue_cash_in + $total_revenue_card_in + $total_revenue_bank_in;

        switch ($request->get('medium_type')) {
            case 'web':
                return view('admin.reports.generalrevenuesummaryreport.report', compact('report_data', 'total_revenue_cash_in', 'total_revenue_card_in', 'total_revenue_bank_in', 'total_refund', 'total_revenue', 'start_date', 'end_date', 'total_revenue_male_in', 'total_revenue_female_in'));
                break;
            case 'print':
                return view('admin.reports.generalrevenuesummaryreport.reportprint', compact('report_data', 'total_revenue_cash_in', 'total_revenue_card_in', 'total_revenue_bank_in', 'total_refund', 'total_revenue', 'start_date', 'end_date', 'total_revenue_male_in', 'total_revenue_female_in'));
                break;
            case 'pdf':
                $content = view('admin.reports.generalrevenuesummaryreport.reportpdf', compact('report_data', 'total_revenue_cash_in', 'total_revenue_card_in', 'total_revenue_bank_in', 'total_refund', 'total_revenue', 'start_date', 'end_date', 'total_revenue_male_in', 'total_revenue_female_in'))->render();
                $pdf = App::make('dompdf.wrapper');
                $pdf->loadHTML($content);
                $pdf->setPaper('A4', 'landscape');

                return $pdf->stream('General Revenue Report', 'landscape');
                break;
            case 'excel':
                self::GeneralRevenueSummaryReportExcel($report_data, $total_revenue_cash_in, $total_revenue_card_in, $total_revenue_bank_in, $total_refund, $total_revenue, $start_date, $end_date, $total_revenue_male_in, $total_revenue_female_in);
                break;
            default:
                return view('admin.reports.generalrevenuesummaryreport.report', compact('report_data', 'total_revenue_cash_in', 'total_revenue_card_in', 'total_revenue_bank_in', 'total_refund', 'total_revenue', 'start_date', 'end_date', 'total_revenue_male_in', 'total_revenue_female_in'));
                break;
        }
    }

    /**
     * General Revnue Report Excel
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $start_date
     * @param  (mixed)  $end_date
     * @return Response
     */
    private static function GeneralRevenueSummaryReportExcel($report_data, $total_revenue_cash_in, $total_revenue_card_in, $total_revenue_bank_in, $total_refund, $total_revenue, $start_date, $end_date): mixed
    {

        $spreadsheet = new Spreadsheet;  /* ----Spreadsheet object----- */
        $Excel_writer = new Xlsx($spreadsheet);  /* ----- Excel (Xls) Object */
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From '.$start_date.' to '.$end_date);

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

                $activeSheet->setCellValue('A'.$counter, $reportRow['name']);
                $activeSheet->setCellValue('B'.$counter, $reportRow['city']);
                $activeSheet->setCellValue('C'.$counter, $reportRow['region']);
                $activeSheet->setCellValue('D'.$counter, number_format($reportRow['revenue_cash_in'], 2));
                $activeSheet->setCellValue('E'.$counter, number_format($reportRow['revenue_card_in'], 2));
                $activeSheet->setCellValue('F'.$counter, number_format($reportRow['revenue_bank_in'], 2));
                $activeSheet->setCellValue('G'.$counter, number_format($reportRow['refund_out'], 2));
                $activeSheet->setCellValue('H'.$counter, number_format($reportRow['in_hand'], 2));
                $counter++;
            }
            $activeSheet->setCellValue('A'.$counter, '');
            $counter++;

            $activeSheet->setCellValue('A'.$counter, 'Total')->getStyle('A'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('D'.$counter, number_format($total_revenue_cash_in, 2))->getStyle('D'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('E'.$counter, number_format($total_revenue_card_in, 2))->getStyle('E'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('F'.$counter, number_format($total_revenue_bank_in, 2))->getStyle('F'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('G'.$counter, number_format($total_refund, 2))->getStyle('G'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('H'.$counter, number_format(($total_revenue_cash_in + $total_revenue_card_in + $total_revenue_bank_in) - $total_refund, 2))->getStyle('H'.$counter)->getFont()->setBold(true);

            $counter++;

            $activeSheet->setCellValue('A'.$counter, '');
            $counter++;

            $activeSheet->setCellValue('A'.$counter, 'Revenue Cash In')->getStyle('A'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('B'.$counter, number_format($total_revenue_cash_in, 2));
            $counter++;

            $activeSheet->setCellValue('A'.$counter, 'Revenue Card In')->getStyle('A'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('B'.$counter, number_format($total_revenue_card_in, 2));
            $counter++;

            $activeSheet->setCellValue('A'.$counter, 'Revenue Bank/Wire In')->getStyle('A'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('B'.$counter, number_format($total_revenue_bank_in, 2));
            $counter++;

            $activeSheet->setCellValue('A'.$counter, 'Total Revenue')->getStyle('A'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('B'.$counter, number_format($total_revenue, 2));
            $counter++;

            $activeSheet->setCellValue('A'.$counter, 'Refund')->getStyle('A'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('B'.$counter, number_format($total_refund, 2));
            $counter++;

            $activeSheet->setCellValue('A'.$counter, 'In Hand Balance')->getStyle('A'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('B'.$counter, number_format(($total_revenue - $total_refund), 2));
            $counter++;
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'GeneralRevenueReport'.'.xlsx"'); /* -- $filename is  xsl filename --- */
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');
    }

    /**
     * Load Discount Report.
     */
    public function discountReport(Request $request): View
    {

        if (! Gate::allows('finance_general_revenue_reports_discount_report')) {
            return abort(401);
        }

        [$start_date, $end_date] = $this->parseDateRange($request->get('date_range'));

        $filters = [];

        $users = User::getAllRecords(Auth::user()->account_id)->pluck('name', 'id');
        $users->prepend('All', '');

        $reportData = \App\Reports\Invoices::getdiscountReport($request->all());

        $filters['reportData'] = $reportData;
        $filters['locations'] = Locations::getAllRecordsDictionary(Auth::user()->account_id);
        $filters['services'] = Services::getAllRecordsDictionaryWithoutAll(Auth::user()->account_id);
        $filters['discounts'] = Discounts::getDiscountforreport(Auth::user()->account_id)->getDictionary();

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
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $start_date
     * @param  (mixed)  $end_date
     * @return Response
     */
    private static function discountreportexcel($reportData, $filters, $start_date, $end_date): mixed
    {

        $spreadsheet = new Spreadsheet;  /* ----Spreadsheet object----- */
        $Excel_writer = new Xlsx($spreadsheet);  /* ----- Excel (Xls) Object */
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From '.$start_date.' to '.$end_date);

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

            $activeSheet->setCellValue('A'.$counter, $reportRow->id);
            $activeSheet->setCellValue('B'.$counter, (array_key_exists($reportRow->location_id, $filters['locations'])) ? $filters['locations'][$reportRow->location_id]->name : '-');
            $activeSheet->setCellValue('C'.$counter, (array_key_exists($reportRow->service_id, $filters['services'])) ? $filters['services'][$reportRow->service_id]->name : '-');
            $activeSheet->setCellValue('D'.$counter, $reportRow->patient->name);
            $activeSheet->setCellValue('E'.$counter, $reportRow->user->name);
            $activeSheet->setCellValue('F'.$counter, number_format((array_key_exists($reportRow->service_id, $filters['services'])) ? $filters['services'][$reportRow->service_id]->price : 0, 2));
            $activeSheet->setCellValue('G'.$counter, (array_key_exists($reportRow->discount_id, $filters['discounts'])) ? $filters['discounts'][$reportRow->discount_id]->name : '-');
            $activeSheet->setCellValue('H'.$counter, $reportRow->discount_type == null ? '-' : $reportRow->discount_type);
            $activeSheet->setCellValue('I'.$counter, number_format($reportRow->discount_price == null ? '0' : $reportRow->discount_price, 2));
            $activeSheet->setCellValue('J'.$counter, number_format($reportRow->tax_exclusive_serviceprice == null ? 0 : $reportRow->tax_exclusive_serviceprice, 2));
            $activeSheet->setCellValue('K'.$counter, $reportRow->tax_percentage.'%');
            $activeSheet->setCellValue('L'.$counter, number_format($reportRow->tax_price == null ? 0 : $reportRow->tax_price, 2));
            $activeSheet->setCellValue('M'.$counter, number_format($reportRow->tax_including_price == null ? 0 : $reportRow->tax_including_price, 2));
            $activeSheet->setCellValue('N'.$counter, ($reportRow->is_exclusive) ? 'Yes' : 'No');
            $activeSheet->setCellValue('O'.$counter, ($reportRow->created_at) ? Carbon::parse($reportRow->created_at, null)->format('M j, Y').' at '.Carbon::parse($reportRow->created_at, null)->format('h:i A') : '-');

            $counter++;
        }
        $activeSheet->setCellValue('A'.$counter, '');
        $counter++;

        $activeSheet->setCellValue('A'.$counter, 'Grand Total')->getStyle('A'.$counter)->getFont()->setBold(true);
        $activeSheet->setCellValue('F'.$counter, number_format($grandserviceprice, 2))->getStyle('F'.$counter)->getFont()->setBold(true);
        $activeSheet->setCellValue('J'.$counter, number_format($totalAmount, 2))->getStyle('J'.$counter)->getFont()->setBold(true);
        $activeSheet->setCellValue('M'.$counter, number_format($totalTaxAmount, 2))->getStyle('M'.$counter)->getFont()->setBold(true);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'Discount Report'.'.xlsx"'); /* -- $filename is  xsl filename --- */
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');
    }

    public function getDiscounts(Request $request): JsonResponse
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

        return response()->json([
            'discounts' => view('admin.reports.accountsalesreport.discounts', compact('discounts'))->render(),
        ]);
    }

    /** @deprecated Moved to App\Services\Reports\Revenue\ConversionReport. Called via GeneralSalesReportController. */
    public function conversionreport(Request $request): View
    {
        if (! Gate::allows('finance_general_revenue_reports_conversion_report')) {
            return abort(404);
        }

        [$start_date, $end_date] = $this->parseDateRange($request->get('date_range'));

        [$report_data, $locationData] = Finanaces::conversion_report($request->all(), Auth::user()->account_id);

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

    private static function conversionreportexcel($reportData, $start_date, $end_date, $converted): mixed
    {
        $spreadsheet = new Spreadsheet;  /* ----Spreadsheet object----- */
        $Excel_writer = new Xlsx($spreadsheet);  /* ----- Excel (Xls) Object */
        $Excel_writer->setPreCalculateFormulas(false);

        $spreadsheet->setActiveSheetIndex(0);
        $activeSheet = $spreadsheet->getActiveSheet();

        $activeSheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $activeSheet->setCellValue('B1', 'From '.$start_date.' to '.$end_date);

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
                    $activeSheet->setCellValue('A'.$counter, $appointment['patient_id']);
                    $activeSheet->setCellValue('B'.$counter, $appointment['doctor']);
                    $activeSheet->setCellValue('C'.$counter, $appointment['doi']);
                    $activeSheet->setCellValue('D'.$counter, $appointment['client']);
                    $activeSheet->setCellValue('E'.$counter, 'Consultancy');
                    $activeSheet->setCellValue('F'.$counter, $appointment['service']);
                    $activeSheet->setCellValue('G'.$counter, $appointment['converted']);
                    $activeSheet->setCellValue('H'.$counter, number_format($appointment['conversion_spend'], 2));
                    $activeSheet->setCellValue('I'.$counter, Carbon::parse($appointment['conversion_date'])->format('F j,Y'));
                    $activeSheet->setCellValue('J'.$counter, $appointment['region']);
                    $activeSheet->setCellValue('K'.$counter, $appointment['city']);
                    $activeSheet->setCellValue('L'.$counter, $appointment['centre']);

                    $total += $appointment['conversion_spend'] ? $appointment['conversion_spend'] : 0;
                    $count++;
                    $counter++;
                }
            }
            $counter++;
            $activeSheet->setCellValue('A'.$counter, 'Total')->getStyle('A'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('H'.$counter, number_format($total, 2));
            $counter++;
            $activeSheet->setCellValue('A'.$counter, 'Total Count')->getStyle('A'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('H'.$counter, count($reportData));
            $counter++;
            $activeSheet->setCellValue('A'.$counter, 'Converted Count')->getStyle('A'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('H'.$counter, $count);
            $counter++;
            $activeSheet->setCellValue('A'.$counter, 'Converted Ration')->getStyle('A'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('H'.$counter, $count > 0 ? number_format($count / count($reportData) * 100, 2) : 0 .'%');
            $counter++;
            $activeSheet->setCellValue('A'.$counter, 'Conversion Average')->getStyle('A'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('H'.$counter, $total > 0 ? number_format($total / $count, 2) : 0);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'conversionreport'.'.xlsx"'); /* -- $filename is  xsl filename --- */
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');
    }
}
