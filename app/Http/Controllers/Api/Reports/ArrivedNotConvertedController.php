<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reports;

use App\Exports\GenericTableExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ArrivedNotConvertedRequest;
use App\Http\Resources\Reports\ArrivedNotConvertedResource;
use App\Services\Reports\ArrivedNotConvertedService;
use App\Services\Reports\Concerns\ParsesDateRange;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ArrivedNotConvertedController extends Controller
{
    use ParsesDateRange;

    public function __construct(
        private readonly ArrivedNotConvertedService $reportService,
    ) {}

    public function __invoke(ArrivedNotConvertedRequest $request): JsonResponse
    {
        if (! Gate::allows('non_converted_customers_manage')) {
            return $this->errorResponse('Unauthorized', 403);
        }

        try {
            $data = $this->reportService->generate(
                startDate: $request->startDate(),
                endDate: $request->endDate(),
                locationIds: $request->locationIds(),
                doctorId: $request->doctorId(),
                serviceId: $request->serviceId(),
            );

            return $this->successResponse('Arrived not converted report generated successfully', [
                'rows' => ArrivedNotConvertedResource::collection($data),
                'meta' => [
                    'total' => $data->count(),
                    'start_date' => $request->startDate(),
                    'end_date' => $request->endDate(),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'ArrivedNotConverted');
        }
    }

    public function export(Request $request): SymfonyResponse
    {
        if (! Gate::allows('non_converted_customers_manage')) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'format' => 'required|in:pdf,excel',
            'date_range' => 'required|string',
            'location_id' => 'nullable|array',
            'location_id.*' => 'integer|exists:locations,id',
            'doctor_id' => 'nullable|integer',
            'service_id' => 'nullable|integer',
        ]);

        [$startDate, $endDate] = self::parseDateRange($validated['date_range']);

        $locationIds = $validated['location_id'] ?? null;
        if (is_array($locationIds)) {
            $locationIds = array_values(array_filter(
                array_map('intval', $locationIds),
                fn ($id) => $id > 0,
            ));
            if (empty($locationIds)) {
                $locationIds = null;
            }
        }

        $rows = $this->reportService->generate(
            startDate: $startDate,
            endDate: $endDate,
            locationIds: $locationIds,
            doctorId: isset($validated['doctor_id']) ? (int) $validated['doctor_id'] : null,
            serviceId: isset($validated['service_id']) ? (int) $validated['service_id'] : null,
        );

        $showPhone = Gate::allows('contact');
        $headings = $showPhone
            ? ['Patient ID', 'Patient', 'Phone', 'Service', 'Doctor', 'Centre', 'Scheduled Date']
            : ['Patient ID', 'Patient', 'Service', 'Doctor', 'Centre', 'Scheduled Date'];

        $flatRows = [];
        foreach ($rows as $r) {
            $row = [
                $r->id ?? null,
                $r->name ?? 'N/A',
            ];
            if ($showPhone) {
                $row[] = $r->phone ?? '';
            }
            $row[] = $r->service_name ?? 'N/A';
            $row[] = $r->doctor_name ?? 'N/A';
            $row[] = $r->location_name ?? 'N/A';
            $row[] = $r->scheduled_date
                ? date('Y-m-d', strtotime((string) $r->scheduled_date))
                : '';
            $flatRows[] = $row;
        }

        $stamp = "-{$startDate}-to-{$endDate}";

        if ($validated['format'] === 'excel') {
            return Excel::download(
                new GenericTableExport($headings, $flatRows),
                "non-converted-report{$stamp}.xlsx",
            );
        }

        $pdf = Pdf::loadView('admin.reports.exports.generic-table', [
            'title' => 'Non-Converted Customer Report',
            'subtitle' => "Period: {$startDate} → {$endDate}",
            'headings' => $headings,
            'rows' => $flatRows,
        ])->setPaper('A4', 'landscape');

        return $pdf->download("non-converted-report{$stamp}.pdf");
    }
}
