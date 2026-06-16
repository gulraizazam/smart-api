<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reports;

use App\Exports\GenericTableExport;
use App\Helpers\ACL;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\DoctorHasLocations;
use App\Models\Locations;
use App\Models\User;
use App\Services\Inventory\InventoryReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * SPA bridge for the four Inventory reports —
 *   - Stock Report          (per-product opening / additions / sold / remaining)
 *   - Sales Report          (orders + payment-mode totals)
 *   - Doctor Sales Report   (sales grouped by doctor with product breakdown)
 *   - Addition Report       (stock-IN events)
 *
 * All four share centre + date filters; stock/addition add brand; doctor-sales
 * adds a doctor narrowing. JSON endpoints return SPA-friendly shapes; export
 * endpoints stream PDF / XLSX via the shared `GenericTableExport` and
 * `admin.reports.exports.generic-table` blade.
 */
class InventoryReportsApiController extends Controller
{
    private const DOCTOR_USER_TYPE_ID = 5;

    public function __construct(private readonly InventoryReportService $service) {}

    // ---------------------------------------------------------------------
    // Filters (centres + brands + doctors)
    // ---------------------------------------------------------------------

    public function filters(): JsonResponse
    {
        if (! Gate::allows('inventory_report_manage')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        try {
            $accountId = Auth::user()->account_id;

            $locations = collect(Locations::getActiveRecordsByCity('', ACL::getUserCentres(), $accountId))
                ->map(fn ($l) => ['id' => (int) $l->id, 'name' => $l->name])
                ->values()
                ->all();

            $brands = Brand::where('status', 1)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($b) => ['id' => (int) $b->id, 'name' => $b->name])
                ->all();

            $doctors = User::getAllRecords($accountId)
                ->where('user_type_id', self::DOCTOR_USER_TYPE_ID)
                ->where('active', 1)
                ->values()
                ->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name])
                ->all();

            return $this->successResponse('OK', [
                'locations' => $locations,
                'brands' => $brands,
                'doctors' => $doctors,
            ]);
        } catch (\Throwable $e) {
            Log::error('InventoryReports filters failed: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorResponse('An error occurred. Please try again.', 500);
        }
    }

    public function doctorsForCentres(Request $request): JsonResponse
    {
        if (! Gate::allows('inventory_report_manage')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        try {
            $request->validate([
                'centre_ids' => 'nullable|array',
                'centre_ids.*' => 'integer|exists:locations,id',
            ]);

            $accountId = Auth::user()->account_id;
            $centreIds = $this->normaliseCentres($request->input('centre_ids', []));

            if (empty($centreIds)) {
                $doctors = User::getAllRecords($accountId)
                    ->where('user_type_id', self::DOCTOR_USER_TYPE_ID)
                    ->where('active', 1)
                    ->values()
                    ->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name])
                    ->all();

                return $this->successResponse('OK', ['doctors' => $doctors]);
            }

            $allocated = DoctorHasLocations::where('is_allocated', 1)
                ->whereIn('location_id', $centreIds)
                ->distinct()
                ->pluck('user_id')
                ->all();

            $doctors = empty($allocated)
                ? []
                : User::whereIn('id', $allocated)
                    ->where('account_id', $accountId)
                    ->where('user_type_id', self::DOCTOR_USER_TYPE_ID)
                    ->where('active', 1)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name])
                    ->all();

            return $this->successResponse('OK', ['doctors' => $doctors]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('InventoryReports doctorsForCentres failed: '.$e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->errorResponse('An error occurred. Please try again.', 500);
        }
    }

    // ---------------------------------------------------------------------
    // Stock Report — opening / additions / sold / remaining per product
    // ---------------------------------------------------------------------

    public function stock(Request $request): JsonResponse
    {
        if (! Gate::allows('inventory_report_manage')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        try {
            $params = $this->validateStockParams($request);
            $started = microtime(true);
            $result = $this->service->loadStockReport($params);
            $rows = collect($result['report'] ?? [])->values()->all();

            return $this->successResponse('Stock report generated successfully', [
                'rows' => $rows,
                'meta' => [
                    'count' => count($rows),
                    'elapsed_ms' => round((microtime(true) - $started) * 1000, 1),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'InventoryStockReport');
        }
    }

    public function stockExport(Request $request): SymfonyResponse
    {
        if (! Gate::allows('inventory_report_manage')) {
            abort(403, 'Unauthorized.');
        }

        $request->validate(['format' => 'required|in:pdf,excel']);
        $params = $this->validateStockParams($request);

        $result = $this->service->loadStockReport($params);
        $headings = ['Product', 'Opening', 'Addition', 'Total stock', 'Sold', 'Remaining'];
        $flat = collect($result['report'] ?? [])->map(fn ($r) => [
            (string) ($r['product_name'] ?? ''),
            (int) ($r['opening_stock'] ?? 0),
            (int) ($r['addition'] ?? 0),
            (int) ($r['total_stock'] ?? 0),
            (int) ($r['sold_stock'] ?? 0),
            (int) ($r['remaining_stock'] ?? 0),
        ])->values()->all();

        return $this->stream(
            $request->input('format'),
            'stock-report',
            'Stock Report',
            $params['date_range'] ?? null,
            $headings,
            $flat,
        );
    }

    // ---------------------------------------------------------------------
    // Sales Report — orders + payment-mode totals
    // ---------------------------------------------------------------------

    public function sales(Request $request): JsonResponse
    {
        if (! Gate::allows('inventory_report_manage')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        try {
            $params = $this->validateSalesParams($request);
            $started = microtime(true);
            $result = $this->service->loadSalesReport($params);

            $rows = collect($result['reportData'] ?? [])->map(fn ($r) => [
                'order_id' => (int) ($r['order_id'] ?? 0),
                'location_name' => (string) ($r['location_name'] ?? ''),
                'order_date' => $r['order_date'] ?? null,
                'patient_id' => $r['patient_id'] ?? null,
                'patient_phone' => $r['patient_phone'] ?? null,
                'purchased_by' => (string) ($r['purchased_by'] ?? ''),
                'product_name' => (string) ($r['product_name'] ?? ''),
                'quantity' => (string) ($r['quantity'] ?? ''),
                'payment_mode' => $r['payment_mode'] ?? null,
                'total_revenue' => (float) ($r['total_revenue'] ?? 0),
            ])->values()->all();

            return $this->successResponse('Sales report generated successfully', [
                'rows' => $rows,
                'totals' => [
                    'cash' => (float) ($result['cashTotal'] ?? 0),
                    'card' => (float) ($result['cardTotal'] ?? 0),
                    'bank_transfer' => (float) ($result['bankTransferTotal'] ?? 0),
                    'overall' => (float) ($result['overallTotal'] ?? 0),
                ],
                'meta' => [
                    'count' => count($rows),
                    'elapsed_ms' => round((microtime(true) - $started) * 1000, 1),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'InventorySalesReport');
        }
    }

    public function salesExport(Request $request): SymfonyResponse
    {
        if (! Gate::allows('inventory_report_manage')) {
            abort(403, 'Unauthorized.');
        }

        $request->validate(['format' => 'required|in:pdf,excel']);
        $params = $this->validateSalesParams($request);

        $result = $this->service->loadSalesReport($params);
        $headings = ['Order #', 'Date', 'Centre', 'Purchased by', 'Product', 'Qty', 'Payment', 'Revenue'];
        $flat = collect($result['reportData'] ?? [])->map(fn ($r) => [
            (int) ($r['order_id'] ?? 0),
            $r['order_date'] ? date('Y-m-d', strtotime((string) $r['order_date'])) : '',
            (string) ($r['location_name'] ?? ''),
            (string) ($r['purchased_by'] ?? ''),
            (string) ($r['product_name'] ?? ''),
            (string) ($r['quantity'] ?? ''),
            $this->paymentModeLabel($r['payment_mode'] ?? null),
            number_format((float) ($r['total_revenue'] ?? 0), 2, '.', ''),
        ])->values()->all();

        return $this->stream(
            $request->input('format'),
            'sales-report',
            'Sales Report',
            $params['date_range'] ?? null,
            $headings,
            $flat,
        );
    }

    // ---------------------------------------------------------------------
    // Doctor Sales Report — sales grouped by doctor + product breakdown
    // ---------------------------------------------------------------------

    public function doctorSales(Request $request): JsonResponse
    {
        if (! Gate::allows('inventory_report_manage')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        try {
            $params = $this->validateDoctorSalesParams($request);
            $started = microtime(true);
            $result = $this->service->loadDoctorSalesReport($params);

            $rows = collect($result['report'] ?? [])->values()->map(fn ($d) => [
                'doctor_name' => (string) ($d['doctor_name'] ?? ''),
                'grand_total' => (float) ($d['grand_total'] ?? 0),
                'product_sales' => collect($d['product_sales'] ?? [])->values()->map(fn ($p) => [
                    'product_name' => (string) ($p['product_name'] ?? ''),
                    'total_quantity' => (int) ($p['total_quantity'] ?? 0),
                    'subtotal' => (float) ($p['subtotal'] ?? 0),
                    'order_dates' => collect($p['order_dates'] ?? [])->values()->all(),
                ])->all(),
            ])->all();

            return $this->successResponse('Doctor sales report generated successfully', [
                'rows' => $rows,
                'totals' => [
                    'overall' => (float) ($result['overallTotal'] ?? 0),
                ],
                'meta' => [
                    'count' => count($rows),
                    'elapsed_ms' => round((microtime(true) - $started) * 1000, 1),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'InventoryDoctorSalesReport');
        }
    }

    public function doctorSalesExport(Request $request): SymfonyResponse
    {
        if (! Gate::allows('inventory_report_manage')) {
            abort(403, 'Unauthorized.');
        }

        $request->validate(['format' => 'required|in:pdf,excel']);
        $params = $this->validateDoctorSalesParams($request);

        $result = $this->service->loadDoctorSalesReport($params);
        $headings = ['Doctor', 'Product', 'Qty', 'Subtotal', 'Order dates'];
        $flat = [];
        foreach ($result['report'] ?? [] as $doctor) {
            $name = (string) ($doctor['doctor_name'] ?? '');
            foreach ($doctor['product_sales'] ?? [] as $p) {
                $flat[] = [
                    $name,
                    (string) ($p['product_name'] ?? ''),
                    (int) ($p['total_quantity'] ?? 0),
                    number_format((float) ($p['subtotal'] ?? 0), 2, '.', ''),
                    collect($p['order_dates'] ?? [])->values()->implode(', '),
                ];
            }
        }

        return $this->stream(
            $request->input('format'),
            'doctor-sales-report',
            'Doctor Sales Report',
            $params['date_range'] ?? null,
            $headings,
            $flat,
        );
    }

    // ---------------------------------------------------------------------
    // Addition Report — stock-IN events
    // ---------------------------------------------------------------------

    public function addition(Request $request): JsonResponse
    {
        if (! Gate::allows('inventory_report_manage')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        try {
            $params = $this->validateAdditionParams($request);
            $started = microtime(true);
            $result = $this->service->loadAdditionReport($params);

            $rows = collect($result['stocks'] ?? [])->map(fn ($s) => [
                'product_name' => (string) ($s->product_name ?? ''),
                'location_name' => (string) ($s->location_name ?? ''),
                'quantity' => (int) ($s->quantity ?? 0),
                'created_at' => $s->created_at,
            ])->values()->all();

            return $this->successResponse('Addition report generated successfully', [
                'rows' => $rows,
                'meta' => [
                    'count' => count($rows),
                    'elapsed_ms' => round((microtime(true) - $started) * 1000, 1),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'InventoryAdditionReport');
        }
    }

    public function additionExport(Request $request): SymfonyResponse
    {
        if (! Gate::allows('inventory_report_manage')) {
            abort(403, 'Unauthorized.');
        }

        $request->validate(['format' => 'required|in:pdf,excel']);
        $params = $this->validateAdditionParams($request);

        $result = $this->service->loadAdditionReport($params);
        $headings = ['Product', 'Centre', 'Qty added', 'Date'];
        $flat = collect($result['stocks'] ?? [])->map(fn ($s) => [
            (string) ($s->product_name ?? ''),
            (string) ($s->location_name ?? ''),
            (int) ($s->quantity ?? 0),
            $s->created_at ? date('Y-m-d', strtotime((string) $s->created_at)) : '',
        ])->values()->all();

        return $this->stream(
            $request->input('format'),
            'addition-report',
            'Stock Addition Report',
            $params['date_range'] ?? null,
            $headings,
            $flat,
        );
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function validateStockParams(Request $request): array
    {
        $validated = $request->validate([
            'centre_id' => 'nullable|array',
            'centre_id.*' => 'integer|exists:locations,id',
            'brand_id' => 'nullable|integer|exists:brands,id',
            'date_range' => 'required|string',
        ]);

        return [
            'centre_id' => $this->normaliseCentres($validated['centre_id'] ?? []),
            'brand_id' => $validated['brand_id'] ?? null,
            'date_range' => $validated['date_range'],
        ];
    }

    private function validateSalesParams(Request $request): array
    {
        $validated = $request->validate([
            'centre_id' => 'nullable|array',
            'centre_id.*' => 'integer|exists:locations,id',
            'date_range' => 'required|string',
        ]);

        return [
            'centre_id' => $this->normaliseCentres($validated['centre_id'] ?? []),
            'date_range' => $validated['date_range'],
        ];
    }

    private function validateDoctorSalesParams(Request $request): array
    {
        $validated = $request->validate([
            'centre_id' => 'nullable|array',
            'centre_id.*' => 'integer|exists:locations,id',
            'doctor_id' => 'nullable|integer|exists:users,id',
            'date_range' => 'required|string',
        ]);

        return [
            'centre_id' => $this->normaliseCentres($validated['centre_id'] ?? []),
            'doctor_id' => $validated['doctor_id'] ?? null,
            'date_range' => $validated['date_range'],
        ];
    }

    private function validateAdditionParams(Request $request): array
    {
        $validated = $request->validate([
            'centre_id' => 'nullable|array',
            'centre_id.*' => 'integer|exists:locations,id',
            'brand_id' => 'nullable|integer|exists:brands,id',
            'date_range' => 'required|string',
        ]);

        return [
            'centre_id' => $this->normaliseCentres($validated['centre_id'] ?? []),
            'brand_id' => $validated['brand_id'] ?? null,
            'date_range' => $validated['date_range'],
        ];
    }

    /**
     * @return int[]
     */
    private function normaliseCentres(mixed $raw): array
    {
        return array_values(array_filter(
            array_map('intval', (array) $raw),
            fn (int $id): bool => $id > 0,
        ));
    }

    private function paymentModeLabel(mixed $mode): string
    {
        return match ((int) $mode) {
            1 => 'Cash',
            2 => 'Card',
            3 => 'Bank transfer',
            default => '—',
        };
    }

    private function stream(
        string $format,
        string $slug,
        string $title,
        ?string $dateRange,
        array $headings,
        array $rows,
    ): SymfonyResponse {
        $stamp = '-'.date('Ymd-His');
        $subtitle = $dateRange ? "Period: {$dateRange}" : '';

        if ($format === 'excel') {
            return Excel::download(new GenericTableExport($headings, $rows), "{$slug}{$stamp}.xlsx");
        }

        $pdf = Pdf::loadView('admin.reports.exports.generic-table', [
            'title' => $title,
            'subtitle' => $subtitle,
            'headings' => $headings,
            'rows' => $rows,
        ])->setPaper('A4', 'landscape');

        return $pdf->download("{$slug}{$stamp}.pdf");
    }
}
