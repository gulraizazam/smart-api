<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\OperationsReportRequest;
use App\Services\Reports\Enums\MediumType;
use App\Services\Reports\Enums\OperationsReportType;
use App\Services\Reports\OperationsReportService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;

class OperationsReportNewController extends Controller
{
    public function __construct(
        private readonly OperationsReportService $reportService,
    ) {}

    public function reportLoad(OperationsReportRequest $request): mixed
    {
        $reportType = $request->reportType();
        $mediumType = $request->mediumType();

        if (! Gate::allows($reportType->gate())) {
            abort(401);
        }

        $result = $this->reportService->generate(
            reportType: $reportType,
            requestData: $request->validated(),
            startDate: $request->startDate(),
            endDate: $request->endDate(),
        );

        return $this->respond($mediumType, $reportType, $result);
    }

    private function respond(MediumType $medium, OperationsReportType $reportType, array $result): mixed
    {
        return match ($medium) {
            MediumType::Web => view($reportType->viewPath(), $result),
            MediumType::Print => view($reportType->printViewPath(), $result),
            MediumType::Pdf => $this->renderPdf($reportType, $result),
            MediumType::Excel => $this->renderExcel($reportType, $result),
        };
    }

    private function renderPdf(OperationsReportType $reportType, array $result): mixed
    {
        $content = view($reportType->pdfViewPath(), $result)->render();
        $pdf = App::make('dompdf.wrapper');
        $pdf->loadHTML($content);
        $pdf->setPaper($reportType->pdfPaper(), $reportType->pdfOrientation());

        return $pdf->stream($reportType->pdfFilename());
    }

    private function renderExcel(OperationsReportType $reportType, array $result): void
    {
        $report = $this->reportService->getReportInstance($reportType);
        $report->generateExcel($result);
    }
}
