<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\GeneralSalesReportRequest;
use App\Services\Reports\Enums\MediumType;
use App\Services\Reports\Enums\ReportType;
use App\Services\Reports\GeneralSalesReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;

class GeneralSalesReportController extends Controller
{
    public function __construct(
        private readonly GeneralSalesReportService $reportService,
    ) {}

    public function reportLoad(GeneralSalesReportRequest $request): mixed
    {
        $reportType = $request->reportType();
        $mediumType = $request->mediumType();

        // Gate check
        $gate = $reportType->gate();
        if ($gate && ! Gate::allows($gate)) {
            abort(401);
        }

        // Build request data with resolved location IDs for detail report
        $requestData = $request->validated();

        if ($reportType === ReportType::GeneralRevenueDetail) {
            $requestData['_resolved_location_com_ids'] = $request->resolvedLocationComIds();

            // If no locations and medium is web, return empty view
            $locationCom = $request->input('location_id_com', []);
            $hasLocations = is_array($locationCom) && count(array_filter($locationCom, fn ($v) => $v !== '' && $v !== null && $v !== 'all')) > 0;

            if ($mediumType === MediumType::Web && ! $hasLocations) {
                // No locations selected for web view — use all user centres via resolvedLocationComIds
            }
        }

        $result = $this->reportService->generate(
            reportType: $reportType,
            requestData: $requestData,
            startDate: $request->startDate(),
            endDate: $request->endDate(),
        );

        return $this->respond($mediumType, $reportType, $result);
    }

    private function respond(MediumType $medium, ReportType $reportType, array $result): mixed
    {
        return match ($medium) {
            MediumType::Web => $this->renderView($reportType, $result),
            MediumType::Print => $this->renderPrint($reportType, $result),
            MediumType::Pdf => $this->renderPdf($reportType, $result),
            MediumType::Excel => $this->renderExcel($reportType, $result),
        };
    }

    private function renderView(ReportType $reportType, array $result): mixed
    {
        // For GenderWiseRevenue, use the view with() pattern as original
        if ($reportType === ReportType::GenderWiseRevenue) {
            return view($reportType->viewPath(), [
                'start_date' => $result['start_date'],
                'end_date' => $result['end_date'],
            ])->with([
                'reportData' => $result['reportData'],
                'isLocationWise' => $result['isLocationWise'],
                'selectedLocations' => $result['selectedLocations'],
                'locationIds' => $result['locationIds'],
            ]);
        }

        return view($reportType->viewPath(), $result);
    }

    private function renderPrint(ReportType $reportType, array $result): mixed
    {
        $printView = $reportType->printViewPath();

        if (! $printView) {
            return $this->renderView($reportType, $result);
        }

        return view($printView, $result);
    }

    private function renderPdf(ReportType $reportType, array $result): mixed
    {
        $pdfView = $reportType->pdfViewPath();

        if (! $pdfView) {
            return $this->renderView($reportType, $result);
        }

        // Use the same PDF generation pattern as the original code
        if (in_array($reportType, [ReportType::DailyEmployeeStats, ReportType::CollectionByService], true)) {
            $pdf = Pdf::loadView($pdfView, $result);
        } else {
            $content = view($pdfView, $result)->render();
            $pdf = App::make('dompdf.wrapper');
            $pdf->loadHTML($content);
        }

        $pdf->setPaper($reportType->pdfPaper(), $reportType->pdfOrientation());

        return $pdf->stream($reportType->pdfFilename());
    }

    private function renderExcel(ReportType $reportType, array $result): void
    {
        if (! $reportType->supportsExcel()) {
            return;
        }

        $report = $this->reportService->getReportInstance($reportType);
        $report->generateExcel($result);
    }
}
