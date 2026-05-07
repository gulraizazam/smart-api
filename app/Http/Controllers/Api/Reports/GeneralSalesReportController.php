<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reports;

use App\Exports\GenericTableExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\GeneralSalesReportRequest;
use App\Http\Resources\Reports\CollectionByServiceResource;
use App\Http\Resources\Reports\ConversionReportResource;
use App\Http\Resources\Reports\DailyEmployeeStatsResource;
use App\Http\Resources\Reports\GenderWiseRevenueResource;
use App\Http\Resources\Reports\GeneralRevenueDetailResource;
use App\Http\Resources\Reports\GeneralRevenueSummaryResource;
use App\Http\Resources\Reports\ServicesSoldResource;
use App\Services\Reports\Enums\ReportType;
use App\Services\Reports\GeneralSalesReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class GeneralSalesReportController extends Controller
{
    public function __construct(
        private readonly GeneralSalesReportService $reportService,
    ) {}

    public function __invoke(GeneralSalesReportRequest $request): JsonResponse
    {
        $reportType = $request->reportType();

        $gate = $reportType->gate();
        if ($gate && ! Gate::allows($gate)) {
            return $this->errorResponse('Unauthorized', 403);
        }

        try {
            $requestData = $request->validated();

            if ($reportType === ReportType::GeneralRevenueDetail) {
                $requestData['_resolved_location_com_ids'] = $request->resolvedLocationComIds();
            }

            $result = $this->reportService->generate(
                reportType: $reportType,
                requestData: $requestData,
                startDate: $request->startDate(),
                endDate: $request->endDate(),
            );

            $rows = $this->transformRows($reportType, $result);

            return $this->successResponse('Report generated successfully', [
                'rows' => $rows,
                'totals' => $this->extractTotals($reportType, $result),
                'meta' => [
                    'report_type' => $reportType->value,
                    'start_date' => $request->startDate(),
                    'end_date' => $request->endDate(),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'GeneralSalesReport');
        }
    }

    private function transformRows(ReportType $reportType, array $result): mixed
    {
        return match ($reportType) {
            ReportType::CollectionByService => CollectionByServiceResource::collection(
                collect($result['reportData'] ?? [])
            ),
            ReportType::DailyEmployeeStats => DailyEmployeeStatsResource::collection(
                collect($result['reportData'] ?? [])
            ),
            ReportType::GeneralRevenueDetail => GeneralRevenueDetailResource::collection(
                collect($result['report_data'] ?? [])
            ),
            ReportType::GeneralRevenueSummary => GeneralRevenueSummaryResource::collection(
                collect($result['report_data'] ?? [])
            ),
            ReportType::ConversionReport => ConversionReportResource::collection(
                collect($result['report_data'] ?? [])
            ),
            // Bypass the resource for services_sold so we can enrich each
            // row with the service name + centre name + price (the lookups
            // already exist in $result; the legacy view used them in Blade).
            ReportType::ServicesSold => $this->buildServicesSoldRows($result),
            ReportType::GenderWiseRevenue => GenderWiseRevenueResource::collection(
                collect($result['reportData'] ?? [])
            ),
        };
    }

    private function buildServicesSoldRows(array $result): array
    {
        $sold = $result['soldServices'] ?? collect();
        $services = $result['services'] ?? collect();
        $locations = $result['locations'] ?? collect();

        return collect($sold)->map(function ($row) use ($services, $locations) {
            $serviceId = (int) ($row->service_id ?? 0);
            $locationId = isset($row->location_id) ? (int) $row->location_id : null;
            $service = $services->get($serviceId);
            $location = $locationId ? $locations->get($locationId) : null;
            return [
                'service_id' => $serviceId,
                'service_name' => $service?->name,
                'service_price' => $service ? round((float) ($service->price ?? 0), 2) : 0,
                'location_id' => $locationId,
                'centre_name' => $location?->name,
                'total_sold' => (int) ($row->total_sold ?? 0),
            ];
        })->values()->all();
    }

    private function extractTotals(ReportType $reportType, array $result): array
    {
        return match ($reportType) {
            ReportType::GeneralRevenueDetail, ReportType::GeneralRevenueSummary => [
                'total_revenue_cash_in' => $result['total_revenue_cash_in'] ?? 0,
                'total_revenue_card_in' => $result['total_revenue_card_in'] ?? 0,
                'total_revenue_bank_in' => $result['total_revenue_bank_in'] ?? 0,
                'total_refund' => $result['total_refund'] ?? 0,
                'total_revenue' => $result['total_revenue'] ?? 0,
            ],
            // Most-sold and least-sold are pre-computed by the report
            // service; surface them in the totals so the SPA's hero cards
            // can render them without rescanning the rows.
            ReportType::ServicesSold => [
                'most_sold' => $this->mostLeastTotal($result['mostSold'] ?? null, $result['services'] ?? collect()),
                'least_sold' => $this->mostLeastTotal($result['leastSold'] ?? null, $result['services'] ?? collect()),
            ],
            default => [],
        };
    }

    private function mostLeastTotal(mixed $row, mixed $services): ?array
    {
        if ($row === null) {
            return null;
        }
        $serviceId = (int) ($row->service_id ?? 0);
        $service = is_object($services) && method_exists($services, 'get') ? $services->get($serviceId) : null;
        return [
            'service_id' => $serviceId,
            'service_name' => $service?->name,
            'total_sold' => (int) ($row->total_sold ?? 0),
        ];
    }

    /**
     * Stream the active report as PDF or Excel. Re-runs the same service
     * pipeline as `__invoke` so the file mirrors the on-screen table; row
     * shape is flattened per report type into a [headings, rows] pair that
     * the shared GenericTableExport / generic Blade can consume.
     */
    public function export(GeneralSalesReportRequest $request): SymfonyResponse
    {
        $reportType = $request->reportType();
        $gate = $reportType->gate();
        if ($gate && ! Gate::allows($gate)) {
            abort(403, 'Unauthorized.');
        }

        $format = $request->input('format');
        if (! in_array($format, ['pdf', 'excel'], true)) {
            abort(422, 'format must be pdf or excel.');
        }

        $requestData = $request->validated();
        if ($reportType === ReportType::GeneralRevenueDetail) {
            $requestData['_resolved_location_com_ids'] = $request->resolvedLocationComIds();
        }

        $result = $this->reportService->generate(
            reportType: $reportType,
            requestData: $requestData,
            startDate: $request->startDate(),
            endDate: $request->endDate(),
        );

        $flat = $this->flattenForExport($reportType, $result);
        $title = $flat['title'];
        $headings = $flat['headings'];
        $rows = $flat['rows'];
        $totalsRow = $flat['totalsRow'] ?? null;
        $summaryBlock = $flat['summaryBlock'] ?? null;

        $stamp = ($request->startDate() && $request->endDate())
            ? '-'.$request->startDate().'-to-'.$request->endDate()
            : '';
        $base = strtolower(str_replace(' ', '-', $reportType->value));

        if ($format === 'excel') {
            return Excel::download(
                new GenericTableExport($headings, $rows, $totalsRow, $summaryBlock),
                "{$base}{$stamp}.xlsx",
            );
        }

        $subtitle = ($request->startDate() && $request->endDate())
            ? "Period: {$request->startDate()} → {$request->endDate()}"
            : 'All dates';

        $pdf = Pdf::loadView('admin.reports.exports.generic-table', [
            'title' => $title,
            'subtitle' => $subtitle,
            'headings' => $headings,
            'rows' => $rows,
            'totalsRow' => $totalsRow,
            'summaryBlock' => $summaryBlock,
        ])->setPaper($reportType->pdfPaper(), $reportType->pdfOrientation());

        return $pdf->download("{$base}{$stamp}.pdf");
    }

    /**
     * Flatten one report's result for export. Returns title + headings + rows
     * (in heading order), plus optional totalsRow / summaryBlock for the
     * sales reports — those mirror the on-screen footer and per-mode
     * breakdown panel so the file matches what the user sees.
     *
     * @return array{
     *   title: string,
     *   headings: string[],
     *   rows: array<int, array<int, scalar|null>>,
     *   totalsRow?: array<int, scalar|null>,
     *   summaryBlock?: array<int, array{label: string, value: scalar|null, bold?: bool}>,
     * }
     */
    private function flattenForExport(ReportType $reportType, array $result): array
    {
        return match ($reportType) {
            ReportType::GeneralRevenueSummary => [
                'title' => 'Sales Summary',
                'headings' => ['Centre', 'City', 'Region', 'Cash in', 'Card in', 'Bank in', 'Refund out', 'In hand'],
                'rows' => collect($result['report_data'] ?? [])->map(fn ($r) => [
                    $r['name'] ?? '—',
                    $r['city'] ?? '—',
                    $r['region'] ?? '—',
                    round((float) ($r['revenue_cash_in'] ?? 0), 2),
                    round((float) ($r['revenue_card_in'] ?? 0), 2),
                    round((float) ($r['revenue_bank_in'] ?? 0), 2),
                    round((float) ($r['refund_out'] ?? 0), 2),
                    round((float) ($r['in_hand'] ?? 0), 2),
                ])->values()->all(),
                'totalsRow' => $this->salesTotalsRow($result, 3),
                'summaryBlock' => $this->salesSummaryBlock($result),
            ],
            ReportType::GeneralRevenueDetail => [
                'title' => 'Sales Detail',
                'headings' => ['Centre', 'Date', 'Patient', 'Gender', 'Phone', 'Type', 'Mode', 'Cash in', 'Card in', 'Bank in', 'Refund out', 'Balance'],
                'rows' => collect($result['report_data'] ?? [])->flatMap(function ($centre) {
                    return collect($centre['revenue_data'] ?? [])->map(fn ($tx) => [
                        $centre['name'] ?? '—',
                        isset($tx['created_at']) ? substr((string) $tx['created_at'], 0, 10) : '—',
                        $tx['patient'] ?? '—',
                        $tx['gender'] ?? '—',
                        $tx['phone'] ?? '—',
                        $tx['transtype'] ?? '—',
                        $tx['payment_mode'] ?? '—',
                        round((float) ($tx['revenue_cash_in'] ?? 0), 2),
                        round((float) ($tx['revenue_card_in'] ?? 0), 2),
                        round((float) ($tx['revenue_bank_in'] ?? 0), 2),
                        round((float) ($tx['refund_out'] ?? 0), 2),
                        round((float) ($tx['Balance'] ?? 0), 2),
                    ])->values();
                })->values()->all(),
                'totalsRow' => $this->salesTotalsRow($result, 7),
                'summaryBlock' => $this->salesSummaryBlock($result),
            ],
            ReportType::DailyEmployeeStats => [
                'title' => 'Doctor-wise Summary',
                'headings' => ['Doctor', 'Service', 'Amount'],
                'rows' => collect($result['reportData'] ?? [])->flatMap(function ($doc) {
                    $records = $doc['records'] ?? [];
                    if (empty($records)) {
                        return collect([[$doc['name'] ?? '—', '—', 0]]);
                    }
                    return collect($records)->map(fn ($r) => [
                        $doc['name'] ?? '—',
                        $r['name'] ?? '—',
                        round((float) ($r['amount'] ?? 0), 2),
                    ]);
                })->values()->all(),
            ],
            ReportType::GenderWiseRevenue => [
                'title' => 'Gender-wise Revenue',
                'headings' => ['Centre', 'Male revenue', 'Female revenue', 'Unknown revenue', 'Total revenue', 'Male count', 'Female count', 'Unknown count', 'Total count'],
                'rows' => collect($result['reportData'] ?? [])->map(fn ($r) => [
                    $r['name'] ?? '—',
                    round((float) ($r['male_revenue'] ?? 0), 2),
                    round((float) ($r['female_revenue'] ?? 0), 2),
                    round((float) ($r['unknown_gender_revenue'] ?? 0), 2),
                    round((float) ($r['total_revenue'] ?? 0), 2),
                    (int) ($r['male_count'] ?? 0),
                    (int) ($r['female_count'] ?? 0),
                    (int) ($r['unknown_gender_count'] ?? 0),
                    (int) ($r['total_count'] ?? 0),
                ])->values()->all(),
            ],
            ReportType::ServicesSold => [
                'title' => 'Services Sold',
                'headings' => ['Service', 'Centre', 'Sold', 'Service price'],
                'rows' => $this->buildServicesSoldExportRows($result),
            ],
            ReportType::CollectionByService => [
                'title' => 'Collection by Service',
                'headings' => ['Service', 'Total'],
                'rows' => collect($result['reportData'] ?? [])->map(fn ($r) => [
                    $r['name'] ?? $r['service_name'] ?? '—',
                    round((float) ($r['total'] ?? $r['amount'] ?? 0), 2),
                ])->values()->all(),
            ],
            ReportType::ConversionReport => [
                'title' => 'Conversion Report',
                'headings' => array_keys((array) (collect($result['report_data'] ?? [])->first() ?? [])),
                'rows' => collect($result['report_data'] ?? [])->map(fn ($r) => array_values((array) $r))->values()->all(),
            ],
        };
    }

    /**
     * Bold totals row for the sales summary / detail tables. `$labelSpan` is
     * how many leading text columns to skip before the numeric totals start
     * (3 for summary, 7 for detail) — keeps the row aligned with the headings
     * even though the row geometry differs between the two reports.
     *
     * @return array<int, scalar|null>
     */
    private function salesTotalsRow(array $result, int $labelSpan): array
    {
        $row = array_fill(0, $labelSpan, '');
        $row[0] = 'Totals';
        $cash = round((float) ($result['total_revenue_cash_in'] ?? 0), 2);
        $card = round((float) ($result['total_revenue_card_in'] ?? 0), 2);
        $bank = round((float) ($result['total_revenue_bank_in'] ?? 0), 2);
        $refund = round((float) ($result['total_refund'] ?? 0), 2);
        // Last column is "In hand" (summary) / "Balance" (detail) — both
        // represented by Gross − Refund, matching the per-row math.
        $netOrInHand = round($cash + $card + $bank - $refund, 2);
        return array_merge($row, [$cash, $card, $bank, $refund, $netOrInHand]);
    }

    /**
     * Per-mode breakdown panel mirroring the on-screen TotalsBreakdownPanel
     * (Cash, Card, Bank/Wire Transfer, Gross Sales, Refund Out, Net Sales).
     *
     * @return array<int, array{label: string, value: scalar, bold?: bool}>
     */
    private function salesSummaryBlock(array $result): array
    {
        $cash = round((float) ($result['total_revenue_cash_in'] ?? 0), 2);
        $card = round((float) ($result['total_revenue_card_in'] ?? 0), 2);
        $bank = round((float) ($result['total_revenue_bank_in'] ?? 0), 2);
        $gross = round($cash + $card + $bank, 2);
        $refund = round((float) ($result['total_refund'] ?? 0), 2);
        $net = round($gross - $refund, 2);

        return [
            ['label' => 'Cash', 'value' => number_format($cash, 2)],
            ['label' => 'Card', 'value' => number_format($card, 2)],
            ['label' => 'Bank/Wire Transfer', 'value' => number_format($bank, 2)],
            ['label' => 'Gross Sales', 'value' => number_format($gross, 2), 'bold' => true],
            ['label' => 'Refund Out', 'value' => '('.number_format($refund, 2).')'],
            ['label' => 'Net Sales', 'value' => number_format($net, 2), 'bold' => true],
        ];
    }

    /**
     * Helper for ServicesSold — joins each row to its lookup name + price.
     *
     * @return array<int, array<int, scalar|null>>
     */
    private function buildServicesSoldExportRows(array $result): array
    {
        $sold = $result['soldServices'] ?? collect();
        $services = $result['services'] ?? collect();
        $locations = $result['locations'] ?? collect();
        return collect($sold)->map(function ($row) use ($services, $locations) {
            $serviceId = (int) ($row->service_id ?? 0);
            $locationId = isset($row->location_id) ? (int) $row->location_id : null;
            $service = $services->get($serviceId);
            $location = $locationId ? $locations->get($locationId) : null;
            return [
                $service?->name ?? "Service #{$serviceId}",
                $location?->name ?? ($locationId === null ? 'All centres' : "Centre #{$locationId}"),
                (int) ($row->total_sold ?? 0),
                $service ? round((float) ($service->price ?? 0), 2) : 0,
            ];
        })->values()->all();
    }
}
