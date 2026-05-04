<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reports;

use App\Exports\GenericTableExport;
use App\Helpers\ACL;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\DoctorRevenueReportRequest;
use App\Models\Locations;
use App\Services\Upselling\UpsellingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Doctor Revenue report — JSON bridge over UpsellingService.
 *
 *   GET  /api/reports/doctor-revenue/filters → centres
 *   POST /api/reports/doctor-revenue         → per-doctor totals
 *   POST /api/reports/doctor-revenue/detail  → transaction list for one doctor
 *   POST /api/reports/doctor-revenue/export  → PDF / XLSX of per-doctor totals
 *
 * Pulls revenue from `package_advances` (cash_flow=in, non-cancelled,
 * non-tax, non-adjustment) joined through packages → appointments → doctor.
 * Refunds are subtracted. Payments without a doctor (NULL doctor_id) bucket
 * into a synthetic "Unassigned" row keyed as doctor_id 0.
 */
class DoctorRevenueReportController extends Controller
{
    public function __construct(
        private readonly UpsellingService $upsellingService,
    ) {}

    public function __invoke(DoctorRevenueReportRequest $request): JsonResponse
    {
        try {
            $centreIds = $request->centreIds();
            if (empty($centreIds)) {
                $centreIds = array_map('intval', ACL::getUserCentres());
            }

            $result = $this->upsellingService->getDoctorRevenueReportData(
                $centreIds,
                $request->startDate(),
                $request->endDate(),
            );

            if (! empty($result['empty'])) {
                return $this->successResponse('Doctor revenue report generated', [
                    'rows' => [],
                    'totals' => ['total_revenue' => 0, 'doctor_count' => 0],
                    'meta' => [
                        'start_date' => $request->startDate(),
                        'end_date' => $request->endDate(),
                        'message' => $result['message'] ?? null,
                    ],
                ]);
            }

            $rows = collect($result['reportData'])
                ->map(fn ($r) => [
                    'doctor_id' => (int) $r->doctor_id,
                    'doctor_name' => (string) $r->doctor_name,
                    'total_revenue' => (float) $r->total_revenue,
                ])
                ->values()
                ->all();

            $total = array_sum(array_column($rows, 'total_revenue'));

            return $this->successResponse('Doctor revenue report generated', [
                'rows' => $rows,
                'totals' => [
                    'total_revenue' => $total,
                    'doctor_count' => count($rows),
                ],
                'meta' => [
                    'start_date' => $request->startDate(),
                    'end_date' => $request->endDate(),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'DoctorRevenueReport');
        }
    }

    public function detail(DoctorRevenueReportRequest $request): JsonResponse
    {
        try {
            $doctorId = $request->doctorId();
            if ($doctorId === null) {
                return $this->errorResponse('doctor_id is required for detail', 422);
            }

            $centreIds = $request->centreIds();
            if (empty($centreIds)) {
                $centreIds = array_map('intval', ACL::getUserCentres());
            }

            $data = $this->upsellingService->getDoctorRevenueDetailData($doctorId, [
                'location_ids' => $centreIds,
                'start_date' => $request->startDate(),
                'end_date' => $request->endDate(),
            ]);

            $tx = collect($data['allTransactions'] ?? [])
                ->map(fn ($t) => [
                    'id' => (int) $t->id,
                    'created_at' => (string) $t->created_at,
                    'cash_flow' => (string) $t->cash_flow,
                    'cash_amount' => (float) $t->cash_amount,
                    'package_id' => isset($t->package_id) ? (int) $t->package_id : null,
                    'package_name' => $t->package_name ?? null,
                    'patient_id' => isset($t->patient_id) ? (int) $t->patient_id : null,
                    'patient_name' => $t->patient_name ?? null,
                    'payment_mode' => $t->payment_mode ?? null,
                ])
                ->values()
                ->all();

            return $this->successResponse('Doctor revenue detail generated', [
                'doctor_name' => $data['doctorName'] ?? '',
                'transactions' => $tx,
                'totals' => [
                    'total_revenue' => (float) ($data['totalRevenue'] ?? 0),
                    'total_payments' => (int) ($data['totalPayments'] ?? 0),
                    'total_refunds' => (float) ($data['totalRefunds'] ?? 0),
                    'unique_packages' => (int) ($data['uniquePackages'] ?? 0),
                ],
                'meta' => [
                    'start_date' => $request->startDate(),
                    'end_date' => $request->endDate(),
                    'doctor_id' => $doctorId,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'DoctorRevenueReport.detail');
        }
    }

    public function export(DoctorRevenueReportRequest $request): SymfonyResponse
    {
        $format = $request->input('format');
        if (! in_array($format, ['pdf', 'excel'], true)) {
            abort(422, 'format must be pdf or excel');
        }

        $centreIds = $request->centreIds();
        if (empty($centreIds)) {
            $centreIds = array_map('intval', ACL::getUserCentres());
        }

        $result = $this->upsellingService->getDoctorRevenueReportData(
            $centreIds,
            $request->startDate(),
            $request->endDate(),
        );

        $rows = empty($result['empty'])
            ? collect($result['reportData'])
            : collect();

        $headings = ['Doctor', 'Revenue'];
        $flat = $rows->map(fn ($r) => [
            (string) $r->doctor_name,
            number_format((float) $r->total_revenue, 2, '.', ''),
        ])->all();

        $stamp = '-'.date('Ymd-His');
        $title = 'Doctor Revenue Report';
        $subtitle = sprintf(
            'Range: %s → %s',
            substr($request->startDate(), 0, 10),
            substr($request->endDate(), 0, 10),
        );

        if ($format === 'excel') {
            return Excel::download(new GenericTableExport($headings, $flat), "doctor-revenue{$stamp}.xlsx");
        }

        $pdf = Pdf::loadView('admin.reports.exports.generic-table', [
            'title' => $title,
            'subtitle' => $subtitle,
            'headings' => $headings,
            'rows' => $flat,
        ])->setPaper('A4', 'portrait');

        return $pdf->download("doctor-revenue{$stamp}.pdf");
    }

    public function filters(): JsonResponse
    {
        try {
            $accountId = Auth::user()->account_id;
            $locations = collect(Locations::getActiveRecordsByCity('', ACL::getUserCentres(), $accountId))
                ->map(fn ($l) => ['id' => (int) $l->id, 'name' => (string) $l->name])
                ->values()
                ->all();

            return $this->successResponse('OK', [
                'locations' => $locations,
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'DoctorRevenueReport.filters');
        }
    }
}
