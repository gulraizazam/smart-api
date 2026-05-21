<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CashFlow;

use App\Exceptions\CashflowException;
use App\Http\Controllers\Controller;
use App\Services\CashFlow\ExportService;
use App\Services\CashFlow\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CashFlowReportsController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
        private readonly ExportService $exportService,
    ) {}

    /**
     * Cash Flow Statement (primary report).
     */
    public function reportCashFlowStatement(Request $request): JsonResponse
    {

        if (Gate::denies('cashflow.reports.view')) { return response()->json(['success' => false, 'message' => 'You are not authorized to access this resource.'], 403); }

        try {

            $accountId = Auth::user()->account_id;

            $filters = $request->only(['date_from', 'date_to', 'branch_id', 'pool_id']);

            $data = $this->reportService->cashFlowStatement($accountId, $filters);



            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Branch comparison report.
     */
    public function reportBranchComparison(Request $request): JsonResponse
    {

        if (Gate::denies('cashflow.reports.view')) { return response()->json(['success' => false, 'message' => 'You are not authorized to access this resource.'], 403); }

        try {

            $accountId = Auth::user()->account_id;

            $data = $this->reportService->branchComparison($accountId, $request->only(['date_from', 'date_to']));



            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Category trend report.
     */
    public function reportCategoryTrend(Request $request): JsonResponse
    {

        if (Gate::denies('cashflow.reports.view')) { return response()->json(['success' => false, 'message' => 'You are not authorized to access this resource.'], 403); }

        try {

            $accountId = Auth::user()->account_id;

            $data = $this->reportService->categoryTrend($accountId, $request->only(['months']));



            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Vendor outstanding report.
     */
    public function reportVendorOutstanding(): JsonResponse
    {

        if (Gate::denies('cashflow.reports.view')) { return response()->json(['success' => false, 'message' => 'You are not authorized to access this resource.'], 403); }

        try {

            $accountId = Auth::user()->account_id;

            $data = $this->reportService->vendorOutstanding($accountId);



            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Staff advance summary report.
     */
    public function reportStaffAdvance(): JsonResponse
    {

        if (Gate::denies('cashflow.reports.view')) { return response()->json(['success' => false, 'message' => 'You are not authorized to access this resource.'], 403); }

        try {

            $accountId = Auth::user()->account_id;

            $data = $this->reportService->staffAdvanceSummary($accountId);



            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Daily cash movement report.
     */
    public function reportDailyMovement(Request $request): JsonResponse
    {

        if (Gate::denies('cashflow.reports.view')) { return response()->json(['success' => false, 'message' => 'You are not authorized to access this resource.'], 403); }

        try {

            $accountId = Auth::user()->account_id;

            $data = $this->reportService->dailyMovement($accountId, $request->only(['date_from', 'date_to', 'pool_id']));



            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Transfer log report.
     */
    public function reportTransferLog(Request $request): JsonResponse
    {

        if (Gate::denies('cashflow.reports.view')) { return response()->json(['success' => false, 'message' => 'You are not authorized to access this resource.'], 403); }

        try {

            $accountId = Auth::user()->account_id;

            $data = $this->reportService->transferLog($accountId, $request->only(['date_from', 'date_to', 'pool_id']));



            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Flagged entries report.
     */
    public function reportFlaggedEntries(Request $request): JsonResponse
    {

        if (Gate::denies('cashflow.reports.view')) { return response()->json(['success' => false, 'message' => 'You are not authorized to access this resource.'], 403); }

        try {

            $accountId = Auth::user()->account_id;

            $data = $this->reportService->flaggedEntries($accountId, $request->only(['date_from', 'date_to']));



            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Dormant vendors report.
     */
    public function reportDormantVendors(): JsonResponse
    {

        if (Gate::denies('cashflow.reports.view')) { return response()->json(['success' => false, 'message' => 'You are not authorized to access this resource.'], 403); }

        try {

            $accountId = Auth::user()->account_id;

            $data = $this->reportService->dormantVendors($accountId);



            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Export report as CSV.
     */
    public function reportExport(Request $request, string $type): \Illuminate\Http\JsonResponse
    {

        try {

            if (!Gate::allows('cashflow.reports.export')) {

                throw CashflowException::unauthorized('export reports');

            }



            $accountId = Auth::user()->account_id;

            $filters = $request->only(['date_from', 'date_to', 'branch_id', 'pool_id', 'months']);



            return $this->exportService->exportCsv($type, $accountId, $filters);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }
}
