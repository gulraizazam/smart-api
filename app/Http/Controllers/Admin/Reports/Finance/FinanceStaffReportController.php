<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reports\Finance;

use App\Http\Controllers\Controller;
use App\Reports\Finanaces;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class FinanceStaffReportController extends Controller
{
    /**
     * Patner Collection Report.
     *
     * @return \Illuminate\Http\Response
     */
    public function partnercollectionreport(Request $request): \Illuminate\View\View
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

        $reportData = Finanaces::partnercollectionreport($request->all(), Auth::user()->account_id);
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
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $start_date
     * @param  (mixed)  $end_date
     * @return \Illuminate\Http\Response
     */
    private static function partnercollectionreportExcel($reportData, $start_date, $end_date): mixed
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
     *
     * @return \Illuminate\Http\Response
     */
    public function staffwiserevenue(Request $request): \Illuminate\View\View
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

        $report_data = Finanaces::staffwiserevenue($request->all(), Auth::user()->account_id);

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
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $start_date
     * @param  (mixed)  $end_date
     * @return \Illuminate\Http\Response
     */
    private static function staffwiserevenuereportexcel($reportData, $start_date, $end_date): mixed
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
}
