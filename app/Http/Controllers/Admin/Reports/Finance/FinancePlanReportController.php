<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reports\Finance;

use App\Helpers\ACL;
use App\Http\Controllers\Controller;
use App\Models\AppointmentStatuses;
use App\Models\Locations;
use App\Models\Services;
use App\Reports\Finanaces;
use App\Services\Reports\Concerns\ParsesDateRange;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class FinancePlanReportController extends Controller
{
    use ParsesDateRange;

    /**
     * Consume Revenie of plan Report.
     *
     * @return Response
     */
    public function consumeplanrevenuereport(Request $request): View
    {
        if (! Gate::allows('finance_general_revenue_reports_consume_plan_revenue_report')) {
            return abort(401);
        }
        [$start_date, $end_date] = $this->parseDateRange($request->get('date_range'));
        $reportData = Finanaces::consumeplanrevenue($request->all(), Auth::user()->account_id);

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
     *
     * @param  (mixed)  $reportData
     * @param  (mixed)  $start_date
     * @param  (mixed)  $end_date
     * @return Response
     */
    private static function consumeplanrevenueExcel($reportData, $start_date, $end_date): mixed
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

            $activeSheet->setCellValue('A'.$counter, $reportRow['plan_id']);
            $activeSheet->setCellValue('B'.$counter, $reportRow['service']);
            $activeSheet->setCellValue('C'.$counter, $reportRow['location']);
            $activeSheet->setCellValue('D'.$counter, number_format($reportRow['service_price']));
            $activeSheet->setCellValue('E'.$counter, $reportRow['disocunt_name'] ? $reportRow['disocunt_name'] : '-');
            $activeSheet->setCellValue('F'.$counter, $reportRow['discount_type'] ? $reportRow['discount_type'] : '-');
            $activeSheet->setCellValue('G'.$counter, $reportRow['discount_amount'] ? number_format($reportRow['discount_amount']) : '-');
            $activeSheet->setCellValue('H'.$counter, number_format($reportRow['amount']));
            $activeSheet->setCellValue('I'.$counter, $reportRow['tax'].'%');
            $activeSheet->setCellValue('J'.$counter, $reportRow['is_exclusive'] == 1 ? number_format($reportRow['tax_value']) : number_format($reportRow['tax_amount'] - $reportRow['amount']));
            $activeSheet->setCellValue('K'.$counter, number_format($reportRow['tax_amount']));
            $activeSheet->setCellValue('L'.$counter, $reportRow['is_exclusive'] == 1 ? 'Yes' : 'No');
            $counter++;

            $amount_t += $reportRow['amount'];
            $tax_price_t += $reportRow['is_exclusive'] == 1 ? $reportRow['tax_value'] : $reportRow['tax_amount'] - $reportRow['amount'];
            $total_amount_t += $reportRow['tax_amount'];
        }
        $activeSheet->setCellValue('A'.$counter, '');
        $counter++;

        $activeSheet->setCellValue('A'.$counter, 'Total')->getStyle('A'.$counter)->getFont()->setBold(true);
        $activeSheet->setCellValue('H'.$counter, number_format($amount_t))->getStyle('H'.$counter)->getFont()->setBold(true);
        $activeSheet->setCellValue('J'.$counter, number_format($tax_price_t))->getStyle('J'.$counter)->getFont()->setBold(true);
        $activeSheet->setCellValue('K'.$counter, number_format($total_amount_t))->getStyle('K'.$counter)->getFont()->setBold(true);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="'.'Consume Plan Revenue'.'.xlsx"'); /* -- $filename is  xsl filename --- */
        header('Cache-Control: max-age=0');
        $Excel_writer->save('php://output');
    }

    /** @deprecated Moved to App\Services\Reports\Revenue\ServicesSoldReport. Called via GeneralSalesReportController. */
    public function serviceSoldreport(Request $request): View
    {
        [$start_date, $end_date] = $this->parseDateRangeWithTimeBounds($request->get('date_range'));

        // Determine locations
        $locationId = (! empty($request->location_id) && $request->location_id[0] !== null)
            ? $request->location_id
            : ACL::getUserCentres();

        $isAllCentres = ($request->location_id[0] == null); // All Centres selected
        $serviceId = $request->service_id;

        // Get arrived and converted appointment status IDs
        $arrivedStatus = AppointmentStatuses::where(['account_id' => Auth::user()->account_id, 'is_arrived' => 1])->first();
        $convertedStatus = AppointmentStatuses::where(['account_id' => Auth::user()->account_id, 'is_converted' => 1])->first();
        $arrivedStatusId = $arrivedStatus ? $arrivedStatus->id : 2;
        $convertedStatusId = $convertedStatus?->id;
        $statusIds = $convertedStatusId ? [$arrivedStatusId, $convertedStatusId] : [$arrivedStatusId];

        // Build query
        $soldServicesQuery = DB::table('appointments')
            ->join('invoices', 'invoices.appointment_id', '=', 'appointments.id')
            ->where('appointments.appointment_type_id', 2)
            ->whereIn('appointments.appointment_status_id', $statusIds)
            ->when(! $isAllCentres, fn ($query) => $query->whereIn('appointments.location_id', $locationId))
            ->when($start_date && $end_date, fn ($query) => $query->whereBetween('appointments.scheduled_date', [$start_date, $end_date]))
            ->when($serviceId, fn ($query) => $query->where('appointments.service_id', $serviceId));

        // Grouping
        if ($isAllCentres) {
            $soldServicesQuery->select(
                'appointments.service_id',
                DB::raw('COUNT(appointments.id) as total_sold')
            )->groupBy('appointments.service_id');
        } else {
            $soldServicesQuery->select(
                'appointments.service_id',
                'appointments.location_id',
                DB::raw('COUNT(appointments.id) as total_sold')
            )->groupBy('appointments.service_id', 'appointments.location_id');
        }

        $soldServices = $soldServicesQuery->get();

        // Summary stats
        $grouped = $isAllCentres
            ? $soldServices
            : $soldServices->groupBy('service_id')->map(fn ($group) => (object) [
                'service_id' => $group->first()->service_id,
                'total_sold' => $group->sum('total_sold'),
            ]);

        $mostSold = $grouped->sortByDesc('total_sold')->first();
        $leastSold = $grouped->sortBy('total_sold')->first();

        // Services and locations
        $serviceIds = $soldServices->pluck('service_id')->unique();
        $services = Services::whereIn('id', $serviceIds)->get()->keyBy('id');

        $locationIds = $soldServices->pluck('location_id')->filter()->unique();
        $locations = Locations::whereIn('id', $locationIds)->get()->keyBy('id');

        return view('admin.reports.accountsalesreport.serviceSoldreport', compact(
            'soldServices',
            'start_date',
            'end_date',
            'locationId',
            'serviceId',
            'mostSold',
            'leastSold',
            'services',
            'locations'
        ));
    }
}
