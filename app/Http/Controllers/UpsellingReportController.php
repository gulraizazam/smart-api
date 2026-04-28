<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\ACL;
use App\Models\Locations;
use App\Services\Reports\Concerns\ParsesDateRange;
use App\Services\Upselling\UpsellingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UpsellingReportController extends Controller
{
    use ParsesDateRange;

    public function __construct(
        private readonly UpsellingService $upsellingService
    ) {}

    /**
     * Strip the "all" sentinel option emitted by the multi-select before
     * validation runs. Without this, "All Centres" reaches the integer
     * validator and rejects the request.
     */
    private function stripAllSentinel(Request $request): void
    {
        $raw = $request->input('centre_id');

        if ($raw === null) {
            return;
        }

        $cleaned = array_values(array_filter(
            (array) $raw,
            fn ($value): bool => $value !== 'all' && $value !== '',
        ));

        $request->merge(['centre_id' => $cleaned]);
    }

    /**
     * Normalise the incoming `centre_id` filter into a clean int[]. Empty input
     * falls back to the user's permitted centres so "no selection" means
     * "everywhere I can see" rather than "nowhere".
     *
     * @return int[]
     */
    private function resolveCentreIds(mixed $raw): array
    {
        $ids = empty($raw)
            ? []
            : array_values(array_filter(
                array_map('intval', (array) $raw),
                fn (int $id): bool => $id > 0,
            ));

        if (empty($ids)) {
            $ids = array_map('intval', ACL::getUserCentres());
        }

        return $ids;
    }

    public function index(): View
    {
        $locations = Locations::getActiveRecordsByCity('', ACL::getUserCentres(), Auth::user()->account_id);

        return view('admin.reports.upselling', compact('locations'));
    }

    public function consultantRevenueReport(): View
    {
        $locations = Locations::getActiveRecordsByCity('', ACL::getUserCentres(), Auth::user()->account_id);

        return view('admin.reports.consultant_revenue', compact('locations'));
    }

    public function loadUpsellingReport(Request $request): View|JsonResponse
    {
        $this->stripAllSentinel($request);

        $request->validate([
            'centre_id' => 'nullable|array',
            'centre_id.*' => 'integer|exists:locations,id',
        ]);

        $locationIds = $this->resolveCentreIds($request->input('centre_id'));
        [$startDate, $endDate] = $this->parseDateRangeWithTimeBounds($request->input('date_range'));

        $data = $this->upsellingService->getDoctorUpsellingData($locationIds, $startDate, $endDate);

        if (empty($data)) {
            return response()->json([
                'status' => 200,
                'message' => 'No doctors found for the selected location.',
                'data' => [],
            ]);
        }

        // Map to total_sold_amount for blade template compatibility
        $reportData = collect($data)->map(function ($item) {
            $item = (object) $item;

            return (object) [
                'doctor_id' => $item->doctor_id,
                'doctor_name' => $item->doctor_name,
                'total_sold_amount' => $item->total_upselling_amount,
            ];
        })->sortByDesc('total_sold_amount')->values();

        // Build seller IDs list for session from the returned data
        $allSellerIds = collect($data)->pluck('doctor_id')->toArray();

        session(['upselling_filters' => [
            'location_ids' => $locationIds,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'all_seller_ids' => $allSellerIds,
        ]]);

        return view('admin.reports.upsellingReport', compact('reportData'));
    }

    public function doctorUpsellingDetail(int $doctorId): View|RedirectResponse
    {
        $filters = session('upselling_filters');

        if (! $filters) {
            return redirect()->back()->with('error', 'Session expired. Please reload the report.');
        }

        $data = $this->upsellingService->getDoctorUpsellingDetailData(
            (int) $doctorId,
            (array) $filters['location_ids'],
            $filters['start_date'],
            $filters['end_date']
        );

        return view('admin.reports.doctorUpsellingDetail', $data);
    }

    public function doctorConsultantBreakdown(int $doctorId): View|RedirectResponse
    {
        $filters = session('upselling_filters');

        if (! $filters) {
            return redirect()->back()->with('error', 'Session expired. Please generate the report again.');
        }

        $data = $this->upsellingService->getDoctorConsultantBreakdownData(
            (int) $doctorId,
            (array) $filters['location_ids'],
            $filters['start_date'],
            $filters['end_date']
        );

        if (isset($data['error'])) {
            return redirect()->back()->with('error', $data['error']);
        }

        return view('admin.reports.consultantSellerDetail', $data);
    }

    public function loadConsultantRevenueReport(Request $request): View|JsonResponse
    {
        $this->stripAllSentinel($request);

        $request->validate([
            'centre_id' => 'nullable|array',
            'centre_id.*' => 'integer|exists:locations,id',
        ]);

        $locationIds = $this->resolveCentreIds($request->input('centre_id'));
        [$startDate, $endDate] = $this->parseDateRangeWithTimeBounds($request->input('date_range'));

        $result = $this->upsellingService->getConsultantRevenueReportData($locationIds, $startDate, $endDate);

        if ($result['empty']) {
            return response()->json([
                'status' => 200,
                'message' => $result['message'],
                'data' => [],
            ]);
        }

        $reportData = $result['reportData'];

        // Store filters in session for detail view
        session(['consultant_revenue_filters' => [
            'location_ids' => $locationIds,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'consultant_ids' => $result['consultant_ids'],
            'all_seller_ids' => $result['all_seller_ids'],
        ]]);

        return view('admin.reports.consultantRevenueReport', compact('reportData'));
    }

    public function consultantRevenueDetail(int $consultantId): View|RedirectResponse
    {
        $filters = session('consultant_revenue_filters');

        if (! $filters) {
            return redirect()->back()->with('error', 'Session expired. Please reload the report.');
        }

        $data = $this->upsellingService->getConsultantRevenueDetailData((int) $consultantId, $filters);

        return view('admin.reports.consultantRevenueDetail', $data);
    }

    public function getDoctorUpsellingData(Request $request): JsonResponse
    {
        try {
            $centreId = $request->centre_id;
            $period = $request->period ?: 'thismonth';

            [$startDate, $endDate] = UpsellingService::resolvePeriodDates($period);

            $data = $this->upsellingService->getDoctorUpsellingData($centreId, $startDate, $endDate);

            return response()->json([
                'success' => true,
                'message' => 'Doctor upselling data retrieved successfully.',
                'data' => $data,
            ]);

        } catch (\Exception $e) {
            \Log::error('Doctor Upselling Data Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving doctor upselling data.',
                'data' => [],
            ], 500);
        }
    }

    public function doctorConsultantBreakdown1(int $sellerId): View|RedirectResponse
    {
        $filters = session('upselling_filters');

        if (! $filters) {
            return redirect()->back()->with('error', 'Session expired. Please reload the report.');
        }

        $data = $this->upsellingService->getDoctorConsultantBreakdown1Data((int) $sellerId, $filters);

        if (isset($data['error'])) {
            return redirect()->back()->with('error', $data['error']);
        }

        return view('admin.reports.doctorConsultantBreakdown', $data);
    }

    public function consultantSellerDetail(int $consultantId, int $sellerId): View|RedirectResponse
    {
        $filters = session('upselling_filters');

        if (! $filters) {
            return redirect()->back()->with('error', 'Session expired. Please reload the report.');
        }

        $data = $this->upsellingService->getConsultantSellerDetailData(
            (int) $consultantId,
            (int) $sellerId,
            $filters
        );

        if (isset($data['error'])) {
            return redirect()->back()->with('error', $data['error']);
        }

        return view('admin.reports.consultantSellerDetail', $data);
    }

    public function downloadDoctorUpsellingExcel(Request $request): BinaryFileResponse|RedirectResponse
    {
        try {
            $period = $request->period ?: 'september2025';

            $result = $this->upsellingService->getAllCentreUpsellingDataForExcel($period);

            $fileName = 'Doctor_Upselling_Report_'.$result['currentPeriod']['label'].'_'.now()->format('Y-m-d_H-i-s').'.xlsx';

            $tempFile = $this->upsellingService->generateExcelFile(
                $result['allCentreData'],
                $fileName,
                $result['currentPeriod']
            );

            return response()->download($tempFile, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            \Log::error('Doctor Upselling Excel Download Error: '.$e->getMessage());

            return redirect()->back()->with('error', 'Error generating Excel file.');
        }
    }

    public function getDoctorPaymentBasedUpsellingData(Request $request): JsonResponse
    {
        try {
            $centreId = $request->centre_id;
            $period = $request->period ?: 'thismonth';

            $result = $this->upsellingService->getDoctorPaymentBasedUpsellingData($centreId, $period);

            if ($result['empty']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'data' => [],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Doctor payment-based upselling data retrieved successfully.',
                'data' => $result['data'],
            ]);

        } catch (\Exception $e) {
            \Log::error('Doctor Payment-Based Upselling Data Error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error retrieving doctor payment-based upselling data.',
                'data' => [],
            ], 500);
        }
    }

    /**
     * Display the Doctor Revenue Report page
     */
    public function doctorRevenueReport(): View
    {
        $locations = Locations::getActiveRecordsByCity('', ACL::getUserCentres(), Auth::user()->account_id);

        return view('admin.reports.doctor_revenue', compact('locations'));
    }

    /**
     * Load Doctor Revenue Report data
     * Flow: package_advances (cash_flow='in', cash_amount > 0) -> packages -> appointments -> doctors
     * Date filter applied to package_advances.created_at
     */
    public function loadDoctorRevenueReport(Request $request): View|JsonResponse
    {
        $this->stripAllSentinel($request);

        $request->validate([
            'centre_id' => 'nullable|array',
            'centre_id.*' => 'integer|exists:locations,id',
        ]);

        $locationIds = $this->resolveCentreIds($request->input('centre_id'));
        [$startDate, $endDate] = $this->parseDateRangeWithTimeBounds($request->input('date_range'));

        $result = $this->upsellingService->getDoctorRevenueReportData($locationIds, $startDate, $endDate);

        if ($result['empty']) {
            return response()->json([
                'status' => 200,
                'message' => $result['message'],
                'data' => [],
            ]);
        }

        $reportData = $result['reportData'];

        // Store filters in session for detail view
        session(['doctor_revenue_filters' => [
            'location_ids' => $locationIds,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'doctor_ids' => $result['doctor_ids'],
        ]]);

        return view('admin.reports.doctorRevenueReport', compact('reportData'));
    }

    /**
     * Doctor Revenue Detail - shows individual payments for a specific doctor
     */
    public function doctorRevenueDetail(int $doctorId): View|RedirectResponse
    {
        $filters = session('doctor_revenue_filters');

        if (! $filters) {
            return redirect()->back()->with('error', 'Session expired. Please reload the report.');
        }

        $data = $this->upsellingService->getDoctorRevenueDetailData((int) $doctorId, $filters);

        return view('admin.reports.doctorRevenueDetail', $data);
    }
}
