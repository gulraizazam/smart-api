<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reports;

use App\Exports\GenericTableExport;
use App\Helpers\ACL;
use App\Http\Controllers\Controller;
use App\Services\Feedback\FeedbackService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Future Treatments report — SPA bridge over `FeedbackService`.
 * Lists treatment-type appointments scheduled in the next 7 days,
 * optionally narrowed to specific centres/services.
 *
 *   GET  /api/reports/future-treatments/filters → centres + services
 *   POST /api/reports/future-treatments         → appointment list
 *   POST /api/reports/future-treatments/export  → PDF / XLSX
 */
class FutureTreatmentsReportApiController extends Controller
{
    public function __construct(private readonly FeedbackService $feedbackService) {}

    public function __invoke(Request $request): JsonResponse
    {
        if (! Gate::allows('followuppatient_manage')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        try {
            $validated = $request->validate([
                'location_id' => 'nullable|array',
                'location_id.*' => 'integer|exists:locations,id',
                'service_id' => 'nullable',
            ]);

            [$centreIds, $serviceId] = $this->normaliseInput($validated);

            $started = microtime(true);
            $result = $this->feedbackService->getFutureTreatmentsData($centreIds, $serviceId);
            $elapsed = round((microtime(true) - $started) * 1000, 1);

            $rows = collect($result['appointments'])->map(fn ($a) => [
                'patient_name' => (string) ($a->patient_name ?? ''),
                'service_name' => (string) ($a->service_name ?? ''),
                'scheduled_date' => $a->scheduled_date,
                'appointment_status' => (string) ($a->appointment_status ?? ''),
            ])->all();

            return $this->successResponse('Future treatments fetched successfully', [
                'rows' => $rows,
                'meta' => [
                    'start_date' => $result['filters']['start_date'] ?? null,
                    'end_date' => $result['filters']['end_date'] ?? null,
                    'count' => count($rows),
                    'elapsed_ms' => $elapsed,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'FutureTreatmentsReport');
        }
    }

    public function filters(): JsonResponse
    {
        if (! Gate::allows('followuppatient_manage')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        try {
            $data = $this->feedbackService->getFutureTreatmentsPageData();
            $locations = collect($data['locations'])
                ->map(fn ($l) => ['id' => (int) $l->id, 'name' => $l->name])
                ->values()
                ->all();
            $services = collect($data['services'])
                ->map(fn ($s) => ['id' => (int) $s->id, 'name' => $s->name])
                ->values()
                ->all();

            return $this->successResponse('OK', [
                'locations' => $locations,
                'services' => $services,
            ]);
        } catch (\Throwable $e) {
            Log::error('FutureTreatmentsReport filters failed: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorResponse('An error occurred. Please try again.', 500);
        }
    }

    public function export(Request $request): SymfonyResponse
    {
        if (! Gate::allows('followuppatient_manage')) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'format' => 'required|in:pdf,excel',
            'location_id' => 'nullable|array',
            'location_id.*' => 'integer|exists:locations,id',
            'service_id' => 'nullable',
        ]);

        [$centreIds, $serviceId] = $this->normaliseInput($validated);

        $result = $this->feedbackService->getFutureTreatmentsData($centreIds, $serviceId);
        $startDate = $result['filters']['start_date'] ?? '';
        $endDate = $result['filters']['end_date'] ?? '';

        $headings = ['Patient', 'Service', 'Scheduled date', 'Status'];
        $rows = collect($result['appointments'])->map(fn ($a) => [
            (string) ($a->patient_name ?? ''),
            (string) ($a->service_name ?? ''),
            $a->scheduled_date ? date('Y-m-d H:i', strtotime((string) $a->scheduled_date)) : '',
            (string) ($a->appointment_status ?? ''),
        ])->all();

        $title = 'Future Treatments Report';
        $subtitle = "Period: {$startDate} → {$endDate}";
        $stamp = '-'.date('Ymd-His');

        if ($validated['format'] === 'excel') {
            return Excel::download(new GenericTableExport($headings, $rows), "future-treatments{$stamp}.xlsx");
        }

        $pdf = Pdf::loadView('admin.reports.exports.generic-table', [
            'title' => $title,
            'subtitle' => $subtitle,
            'headings' => $headings,
            'rows' => $rows,
        ])->setPaper('A4', 'landscape');

        return $pdf->download("future-treatments{$stamp}.pdf");
    }

    /**
     * @return array{0: int[], 1: ?string}
     */
    private function normaliseInput(array $validated): array
    {
        $centreIds = $validated['location_id'] ?? [];
        $centreIds = array_values(array_filter(array_map('intval', (array) $centreIds), fn ($id) => $id > 0));
        if (empty($centreIds)) {
            $centreIds = array_map('intval', (array) ACL::getUserCentres());
        }

        $serviceId = $validated['service_id'] ?? null;
        if ($serviceId === '' || $serviceId === 'all') {
            $serviceId = null;
        }

        return [$centreIds, $serviceId !== null ? (string) $serviceId : null];
    }
}
