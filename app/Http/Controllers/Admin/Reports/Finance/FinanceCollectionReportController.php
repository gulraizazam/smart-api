<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reports\Finance;

use App\Http\Controllers\Controller;
use App\Models\MachineType;
use App\Models\Resources;
use App\Reports\Finanaces;
use App\Reports\Invoices;
use App\Services\Reports\Concerns\ParsesDateRange;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class FinanceCollectionReportController extends Controller
{
    use ParsesDateRange;

    /*
     *  Collection by Serivce Report
     */
    /** @deprecated Moved to App\Services\Reports\Revenue\CollectionByServiceReport. Called via GeneralSalesReportController. */
    public function collectionbyservice(Request $request): View
    {

        if (! Gate::allows('finance_general_revenue_reports_collection_by_service')) {
            return abort(401);
        }

        [$start_date, $end_date] = $this->parseDateRange($request->get('date_range'));

        $reportData = Invoices::collectionbyservice($request->all(), Auth::user()->account_id);

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
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $start_date
     * @param  (mixed)  $end_date
     * @return Response
     */
    private static function collectionbyservuiceExcel($reportData, $start_date, $end_date): mixed
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

        $activeSheet->setCellValue('A3', 'Service')->getStyle('A3')->getFont()->setBold(true);
        $activeSheet->setCellValue('B3', 'Total')->getStyle('B3')->getFont()->setBold(true);

        $counter = 4;
        $total = 0;

        foreach ($reportData as $row) {
            if ($row['amount'] > 0) {
                $total = $total + $row['amount'];
                $activeSheet->setCellValue('A'.$counter, $row['name']);
                $activeSheet->setCellValue('B'.$counter, number_format($row['amount'], 2));
                $counter++;
            }
        }

        $activeSheet->setCellValue('A'.$counter, '');
        $activeSheet->setCellValue('B'.$counter, '');
        $counter++;

        $activeSheet->setCellValue('A'.$counter, 'Grand Total')->getStyle('A'.$counter)->getFont()->setBold(true);
        $activeSheet->setCellValue('B'.$counter, number_format($total, 2))->getStyle('B'.$counter)->getFont()->setBold(true);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'Collectionbyservice'.'.xlsx"'); /* -- $filename is  xsl filename --- */
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');
    }

    /**
     * Machine wise Collection Report.
     *
     * @return Response
     */
    public function machinewisecollectionreport(Request $request): View
    {
        if (! Gate::allows('finance_general_revenue_reports_machine_wise_collection_report')) {
            return abort(401);
        }
        [$start_date, $end_date] = $this->parseDateRange($request->get('date_range'));

        $reportData = Finanaces::machinewisecollectionreport($request->all(), Auth::user()->account_id);

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
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $start_date
     * @param  (mixed)  $end_date
     * @return Response
     */
    private static function machinewisecollectionsseportExcel($reportData, $start_date, $end_date): mixed
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

                $activeSheet->setCellValue('A'.$counter, $reportlocation['name'])->getStyle('A'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('B'.$counter, $reportlocation['region'])->getStyle('B'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('C'.$counter, $reportlocation['city'])->getStyle('C'.$counter)->getFont()->setBold(true);

                $counter++;

                $machinetotal_in_t = 0;
                $machinetotal_out_t = 0;
                foreach ($reportlocation['machine_types'] as $reportmachine) {

                    $activeSheet->setCellValue('D'.$counter, $reportmachine['name']);
                    $counter++;
                    $activeSheet->setCellValue('A'.$counter, '');
                    $counter++;

                    $machinetotal_in = 0;
                    $machinetotal_out = 0;
                    foreach ($reportmachine['transaction'] as $paymentrecord) {

                        $activeSheet->setCellValue('E'.$counter, $paymentrecord['name']);
                        $activeSheet->setCellValue('F'.$counter, $paymentrecord['flow']);
                        $activeSheet->setCellValue('G'.$counter, $paymentrecord['amount_in'] ? number_format($paymentrecord['amount_in'], 2) : '');
                        $activeSheet->setCellValue('H'.$counter, $paymentrecord['amount_out'] ? number_format($paymentrecord['amount_out'], 2) : '');
                        $counter++;

                        $machinetotal_in += $paymentrecord['amount_in'] ? $paymentrecord['amount_in'] : 0;
                        $machinetotal_out += $paymentrecord['amount_out'] ? $paymentrecord['amount_out'] : 0;

                        $machinetotal_in_t += $paymentrecord['amount_in'] ? $paymentrecord['amount_in'] : 0;
                        $machinetotal_out_t += $paymentrecord['amount_out'] ? $paymentrecord['amount_out'] : 0;

                        $machinetotal_in_g += $paymentrecord['amount_in'] ? $paymentrecord['amount_in'] : 0;
                        $machinetotal_out_g += $paymentrecord['amount_out'] ? $paymentrecord['amount_out'] : 0;
                    }
                    $activeSheet->setCellValue('A'.$counter, '');
                    $counter++;

                    $activeSheet->setCellValue('D'.$counter, 'Total')->getStyle('D'.$counter)->getFont()->setBold(true);
                    $activeSheet->setCellValue('G'.$counter, number_format($machinetotal_in, 2))->getStyle('G'.$counter)->getFont()->setBold(true);
                    $activeSheet->setCellValue('H'.$counter, number_format($machinetotal_out, 2))->getStyle('H'.$counter)->getFont()->setBold(true);
                    $activeSheet->setCellValue('I'.$counter, number_format($machinetotal_in - $machinetotal_out, 2))->getStyle('I'.$counter)->getFont()->setBold(true);
                    $counter++;

                    $activeSheet->setCellValue('A'.$counter, '');
                    $counter++;
                }
                $activeSheet->setCellValue('A'.$counter, 'Total')->getStyle('A'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('G'.$counter, number_format($machinetotal_in_t, 2))->getStyle('G'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('H'.$counter, number_format($machinetotal_out_t, 2))->getStyle('H'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('I'.$counter, number_format($machinetotal_in_t - $machinetotal_out_t, 2))->getStyle('I'.$counter)->getFont()->setBold(true);
                $counter++;

                $activeSheet->setCellValue('A'.$counter, '');
                $counter++;
            }
            $activeSheet->setCellValue('A'.$counter, 'Grand Total')->getStyle('A'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('G'.$counter, number_format($machinetotal_in_g, 2))->getStyle('G'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('H'.$counter, number_format($machinetotal_out_g, 2))->getStyle('H'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('I'.$counter, number_format($machinetotal_in_g - $machinetotal_out_g, 2))->getStyle('I'.$counter)->getFont()->setBold(true);
            $counter++;
        }
        $activeSheet->setCellValue('A'.$counter, '');
        $counter++;

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'machinewisecollectionreport'.'.xlsx"'); /* -- $filename is  xsl filename --- */
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');
    }

    /**
     * Machine wise Invoice Revenue.
     *
     * @return Response
     */
    public function machinewiseinvoicerevenuereport(Request $request): View
    {
        if (! Gate::allows('finance_general_revenue_reports_machine_wise_invoice_revenue_report')) {
            return abort(401);
        }
        [$start_date, $end_date] = $this->parseDateRange($request->get('date_range'));

        $reportData = Finanaces::machinewiseinvoicerevenuereport($request->all(), Auth::user()->account_id);

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
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $start_date
     * @param  (mixed)  $end_date
     * @return Response
     */
    private static function machinewiseinvoicerevenuereportExcel($reportData, $start_date, $end_date): mixed
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

                $activeSheet->setCellValue('A'.$counter, $reportlocation['name'])->getStyle('A'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('B'.$counter, $reportlocation['region'])->getStyle('B'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('C'.$counter, $reportlocation['city'])->getStyle('C'.$counter)->getFont()->setBold(true);

                $counter++;

                $centotal = 0;
                foreach ($reportlocation['machine'] as $reportmachine) {

                    $activeSheet->setCellValue('D'.$counter, $reportmachine['name']);
                    $counter++;
                    $activeSheet->setCellValue('A'.$counter, '');
                    $counter++;

                    $machinetotal = 0;
                    foreach ($reportmachine['machine_array'] as $paymentrecord) {

                        $activeSheet->setCellValue('E'.$counter, $paymentrecord['client']);
                        $activeSheet->setCellValue('F'.$counter, number_format($paymentrecord['service_price'], 2));
                        $activeSheet->setCellValue('G'.$counter, $paymentrecord['discount_name']);
                        $activeSheet->setCellValue('H'.$counter, $paymentrecord['discount_type']);
                        $activeSheet->setCellValue('I'.$counter, number_format($paymentrecord['discount_price'], 2));
                        $activeSheet->setCellValue('J'.$counter, number_format($paymentrecord['amount'], 2));
                        $activeSheet->setCellValue('K'.$counter, number_format($paymentrecord['tax_value'], 2));
                        $activeSheet->setCellValue('L'.$counter, number_format($paymentrecord['net_amount'], 2));
                        $activeSheet->setCellValue('M'.$counter, Carbon::parse($paymentrecord['created_at'])->format('M j, Y H:i A'));
                        $activeSheet->setCellValue('N'.$counter, $paymentrecord['is_exclusive'] ? 'Yes' : 'NO');
                        $counter++;

                        $machinetotal += $paymentrecord['net_amount'];
                        $centotal += $paymentrecord['net_amount'];
                        $grantotal += $paymentrecord['net_amount'];
                    }
                    $activeSheet->setCellValue('A'.$counter, '');
                    $counter++;

                    $activeSheet->setCellValue('D'.$counter, 'Total')->getStyle('D'.$counter)->getFont()->setBold(true);
                    $activeSheet->setCellValue('L'.$counter, number_format($machinetotal))->getStyle('L'.$counter)->getFont()->setBold(true);
                    $counter++;

                    $activeSheet->setCellValue('A'.$counter, '');
                    $counter++;
                }
                $activeSheet->setCellValue('A'.$counter, 'Total')->getStyle('A'.$counter)->getFont()->setBold(true);
                $activeSheet->setCellValue('L'.$counter, number_format($centotal))->getStyle('L'.$counter)->getFont()->setBold(true);
                $counter++;

                $activeSheet->setCellValue('A'.$counter, '');
                $counter++;
            }

            $activeSheet->setCellValue('A'.$counter, 'Grand Total')->getStyle('A'.$counter)->getFont()->setBold(true);
            $activeSheet->setCellValue('L'.$counter, number_format($grantotal))->getStyle('L'.$counter)->getFont()->setBold(true);
            $counter++;
        }
        $activeSheet->setCellValue('A'.$counter, '');
        $counter++;

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'machinewiseinvoicerevenuereport'.'.xlsx"'); /* -- $filename is  xsl filename --- */
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');
    }

    /*
     * Function to lead machine
     */
    public function loadmachine(Request $request): JsonResponse
    {
        if ($request->location_id) {
            $machines = Resources::where('location_id', '=', $request->location_id)->get();
            $mahinetypeids = [];
            foreach ($machines as $machine) {
                if (! in_array($machine->machine_type_id, $mahinetypeids, true)) {
                    $mahinetypeids[] = $machine->machine_type_id;
                }
            }
            $machinetype = MachineType::whereIn('id', $mahinetypeids)->get();
        } else {
            $machinetype = [];
        }

        return response()->json([
            'machinearray' => view('admin.reports.partnercollectionreport.loadmachine', compact('machinetype'))->render(),
        ]);
    }
}
