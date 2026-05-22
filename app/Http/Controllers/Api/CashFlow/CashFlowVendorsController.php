<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CashFlow;

use App\Exceptions\CashflowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CashFlow\StoreVendorRequest as StoreVendorFormRequest;
use App\Http\Requests\CashFlow\StoreVendorPurchaseRequest;
use App\Http\Requests\CashFlow\StoreVendorSuggestionRequest;
use App\Http\Requests\CashFlow\UpdateVendorRequest as UpdateVendorFormRequest;
use App\Models\CashFlow\CashflowAuditLog;
use App\Services\CashFlow\CashflowAuditService;
use App\Services\CashFlow\VendorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CashFlowVendorsController extends Controller
{
    public function __construct(
        private readonly VendorService $vendorService,
        private readonly CashflowAuditService $auditService,
    ) {}

    /**
     * Get vendors list (paginated).
     */
    public function vendorsData(Request $request): JsonResponse
    {

        if (! Gate::any(['cashflow.vendor.view', 'cashflow.vendor.manage', 'cashflow.manage'])) { return response()->json(['success' => false, 'message' => 'You are not authorized to access this resource.', 'data' => null], 403); }

        try {

            $accountId = Auth::user()->account_id;

            $filters = $request->only(['search', 'is_active', 'sort']);

            $vendors = $this->vendorService->getVendors($accountId, $filters, (int) $request->input('per_page', 25));



            return response()->json([

                'success' => true,

                'data' => $vendors->items(),

                'meta' => [

                    'current_page' => $vendors->currentPage(),

                    'last_page' => $vendors->lastPage(),

                    'per_page' => $vendors->perPage(),

                    'total' => $vendors->total(),

                ],

            ]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Get vendors overview dashboard (summary stats, top outstanding, recent transactions).
     */
    public function vendorsOverview(Request $request): JsonResponse
    {

        if (! Gate::any(['cashflow.vendor.view', 'cashflow.vendor.manage', 'cashflow.manage'])) { return response()->json(['success' => false, 'message' => 'You are not authorized to access this resource.', 'data' => null], 403); }

        try {

            $accountId = Auth::user()->account_id;

            $params = $request->only(['purchase_page', 'payment_page', 'purchase_status', 'payment_vendor_id']);

            $data = $this->vendorService->getVendorsOverview($accountId, $params);

            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Create a vendor.
     */
    public function vendorsStore(StoreVendorFormRequest $request): JsonResponse
    {

        try {

            $accountId = Auth::user()->account_id;

            $vendor = $this->vendorService->createVendor($request->validated(), $accountId);



            return response()->json(['success' => true, 'data' => $vendor, 'message' => 'Vendor created successfully.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 422);

        }

    }

    /**
     * Update a vendor.
     */
    public function vendorsUpdate(UpdateVendorFormRequest $request, int $id): JsonResponse
    {

        try {

            $accountId = Auth::user()->account_id;

            $vendor = $this->vendorService->updateVendor($id, $request->validated(), $accountId);



            return response()->json(['success' => true, 'data' => $vendor, 'message' => 'Vendor updated successfully.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 422);

        }

    }

    /**
     * Toggle vendor active/inactive.
     */
    public function vendorsToggle(int $id): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow.vendor.toggle')) {

                throw CashflowException::unauthorized('activate/deactivate vendors');

            }



            $accountId = Auth::user()->account_id;

            $vendor = \App\Models\CashFlow\Vendor::forAccount($accountId)->findOrFail($id);

            $oldActive = $vendor->is_active;

            $vendor->update(['is_active' => !$oldActive]);



            $this->auditService->log(

                $oldActive ? CashflowAuditLog::ACTION_DEACTIVATED : CashflowAuditLog::ACTION_UPDATED,

                CashflowAuditLog::ENTITY_VENDOR,

                $vendor->id,

                ['is_active' => $oldActive],

                ['is_active' => !$oldActive]

            );



            return response()->json([

                'success' => true,

                'data' => $vendor->fresh(),

                'message' => $vendor->fresh()->is_active ? 'Vendor activated.' : 'Vendor deactivated.',

            ]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 422);

        }

    }

    /**
     * Get vendor ledger (transactions for a specific vendor).
     */
    /**
     * Unified vendor-transactions list across every vendor (or one, via
     * the optional `vendor_id` query param). Reuses getVendorLedger's
     * tested type/status/date filtering, pagination and period_stats —
     * passing a null vendorId drops the per-vendor scope.
     */
    public function vendorsTransactions(Request $request): JsonResponse
    {
        if (! Gate::any(['cashflow.vendor.view', 'cashflow.vendor.manage', 'cashflow.vendor.ledger.view', 'cashflow.manage'])) { return response()->json(['success' => false, 'message' => 'You are not authorized to access this resource.', 'data' => null], 403); }

        try {
            $accountId = Auth::user()->account_id;
            $filters = $request->only(['type', 'date_from', 'date_to', 'status']);
            $vendorId = $request->filled('vendor_id') ? (int) $request->input('vendor_id') : null;

            $result = $this->vendorService->getVendorLedger(
                $vendorId,
                $accountId,
                $filters,
                (int) $request->input('per_page', 25),
            );

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);
        }
    }

    public function vendorsLedger(Request $request, int $id): JsonResponse
    {

        if (! Gate::any(['cashflow.vendor.ledger.view', 'cashflow.vendor.view', 'cashflow.vendor.manage', 'cashflow.manage'])) { return response()->json(['success' => false, 'message' => 'You are not authorized to access this resource.', 'data' => null], 403); }

        try {

            $accountId = Auth::user()->account_id;

            $filters = $request->only(['type', 'date_from', 'date_to', 'status']);

            $result = $this->vendorService->getVendorLedger($id, $accountId, $filters, (int) $request->input('per_page', 50));



            return response()->json([

                'success' => true,

                'data' => $result,

            ]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Record a purchase for a specific vendor (shortcut endpoint).
     */
    public function vendorsPurchase(StoreVendorPurchaseRequest $request, int $id): JsonResponse
    {

        try {

            $accountId = Auth::user()->account_id;

            $transaction = $this->vendorService->recordTransaction(array_merge(

                $request->validated(),

                ['vendor_id' => $id, 'type' => 'purchase']

            ), $accountId);



            return response()->json(['success' => true, 'data' => $transaction, 'message' => 'Purchase recorded.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Update a standalone vendor transaction (purchase).
     */
    public function vendorsTransactionUpdate(StoreVendorPurchaseRequest $request, int $vendorId, int $txId): JsonResponse
    {

        try {

            $accountId = Auth::user()->account_id;

            $tx = $this->vendorService->updateTransaction($txId, $request->validated(), $accountId, $vendorId);

            return response()->json(['success' => true, 'data' => $tx, 'message' => 'Transaction updated.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Mark an ordered purchase as delivered (attach Drive URL). Gated by create permission.
     */
    public function vendorsTransactionDeliver(Request $request, int $vendorId, int $txId): JsonResponse
    {

        try {

            if (!Auth::user()->can('cashflow.vendor.deliver')) {

                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);

            }

            // Receipt is now an uploaded R2 attachment (mirror of
            // Payments); the legacy Drive URL still works for back-compat
            // and old API clients. At least one of the two is required.
            $request->validate([
                'attachment_url'   => 'nullable|url|max:500',
                'attachment_ids'   => 'required_without:attachment_url|array|min:1',
                'attachment_ids.*' => 'integer',
            ]);

            $accountId = Auth::user()->account_id;

            $tx = $this->vendorService->deliverTransaction(
                $txId,
                $request->input('attachment_url'),
                $accountId,
                $vendorId,
                $request->input('attachment_ids', []),
            );

            return response()->json(['success' => true, 'data' => $tx, 'message' => 'Purchase marked as delivered.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Illuminate\Validation\ValidationException $e) {

            return response()->json(['success' => false, 'message' => 'An invoice / receipt attachment is required to mark as delivered.', 'errors' => $e->errors()], 422);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Delete a standalone vendor transaction (purchase).
     */
    public function vendorsTransactionDelete(Request $request, int $vendorId, int $txId): JsonResponse
    {

        try {

            if (!Auth::user()->can('cashflow.vendor.transaction.delete')) {

                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);

            }

            $accountId = Auth::user()->account_id;

            $this->vendorService->deleteTransaction($txId, $accountId, $vendorId);

            return response()->json(['success' => true, 'message' => 'Transaction deleted.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Get audit trail for a specific vendor transaction.
     */
    public function vendorsTransactionAudit(int $vendorId, int $txId): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow.audit.view')) {

                throw CashflowException::unauthorized('view audit trail');

            }



            $accountId = Auth::user()->account_id;

            $logs = $this->auditService->getEntityLogs('vendor_transaction', $txId, $accountId);



            return response()->json(['success' => true, 'data' => $logs]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Export vendor ledger as CSV.
     */
    public function vendorsLedgerExport(Request $request, int $id): \Symfony\Component\HttpFoundation\Response
    {
        try {
            if (!Gate::allows('cashflow.vendor.ledger.export')) {
                throw CashflowException::unauthorized('export vendor ledger');
            }
            $accountId = Auth::user()->account_id;
            $filters = $request->only(['type', 'date_from', 'date_to', 'status']);
            $data = $this->vendorService->exportVendorLedger($id, $accountId, $filters);

            return $this->streamLedgerCsv($data);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);
        }
    }

    /**
     * Cross-vendor CSV export — the unified "All vendors" panel's
     * Export CSV. Mirrors vendorsLedgerExport with no vendor id;
     * exportVendorLedger(null, …) is the nullable-vendor twin of
     * getVendorLedger that powers /vendors/transactions.
     */
    public function vendorsTransactionsExport(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        try {
            if (!Gate::allows('cashflow.vendor.ledger.export')) {
                throw CashflowException::unauthorized('export vendor ledger');
            }
            $accountId = Auth::user()->account_id;
            $filters = $request->only(['type', 'date_from', 'date_to', 'status']);
            $data = $this->vendorService->exportVendorLedger(null, $accountId, $filters);

            return $this->streamLedgerCsv($data);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);
        }
    }

    /**
     * Shared CSV writer for both the per-vendor and unified exports.
     * `$data['vendor']` is null for the unified export — fall back to a
     * generic name/slug. Formula-injection defanged via fputcsv_safe
     * (vendor name + row fields are user-controlled).
     */
    private function streamLedgerCsv(array $data): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $vendorName = $data['vendor']?->name ?? 'All vendors';
        $slug = $data['vendor'] ? \Illuminate\Support\Str::slug($data['vendor']->name) : 'all_vendors';
        $filename = 'vendor_ledger_' . $slug . '_' . $data['date_from'] . '_to_' . $data['date_to'] . '.csv';
        $headers = ['Content-Type' => 'text/csv', 'Content-Disposition' => 'attachment; filename="' . $filename . '"'];

        $callback = function () use ($data, $vendorName) {
            $out = fopen('php://output', 'w');
            fputcsv_safe($out, ['Vendor: ' . $vendorName]);
            fputcsv_safe($out, ['Period: ' . $data['date_from'] . ' to ' . $data['date_to']]);
            fputcsv_safe($out, ['Opening Balance: ' . $data['opening_balance']]);
            fputcsv($out, []);
            // Header must match exportVendorLedger() row key order exactly
            // (array_values is positional): vendor,date,type,status,desc,
            // reference,branch,purchase,payment,balance,by. Status + Vendor
            // were previously missing → every column shifted.
            fputcsv($out, ['Vendor', 'Date', 'Type', 'Status', 'Description', 'Reference', 'Branch', 'Purchase', 'Payment', 'Balance', 'Recorded By']);
            foreach ($data['rows'] as $row) {
                fputcsv_safe($out, array_values($row));
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Get vendor requests list.
     */
    public function vendorRequestsData(Request $request): JsonResponse
    {

        if (! Gate::any(['cashflow.vendor.manage', 'cashflow.vendor.request', 'cashflow.manage'])) { return response()->json(['success' => false, 'message' => 'You are not authorized to access this resource.', 'data' => null], 403); }

        try {

            $accountId = Auth::user()->account_id;

            $status = $request->input('status');

            $requests = $this->vendorService->getVendorRequests($accountId, $status, (int) $request->input('per_page', 25));



            return response()->json([

                'success' => true,

                'data' => $requests->items(),

                'meta' => [

                    'current_page' => $requests->currentPage(),

                    'last_page' => $requests->lastPage(),

                    'per_page' => $requests->perPage(),

                    'total' => $requests->total(),

                ],

            ]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Create a vendor request.
     */
    public function vendorRequestsStore(StoreVendorSuggestionRequest $request): JsonResponse
    {

        try {

            $accountId = Auth::user()->account_id;

            $vendorRequest = $this->vendorService->createVendorRequest($request->validated(), $accountId);



            return response()->json(['success' => true, 'data' => $vendorRequest, 'message' => 'Vendor request submitted.']);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 422);

        }

    }

    /**
     * Approve a vendor request.
     */
    public function vendorRequestsApprove(int $id): JsonResponse
    {

        try {

            if (!Auth::user()->can('cashflow.vendor.manage')) {

                throw CashflowException::unauthorized('approve vendor requests');

            }



            $accountId = Auth::user()->account_id;

            $vendorRequest = $this->vendorService->approveVendorRequest($id, $accountId);



            return response()->json(['success' => true, 'data' => $vendorRequest, 'message' => 'Vendor request approved and vendor created.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Dismiss a vendor request.
     */
    public function vendorRequestsDismiss(Request $request, int $id): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow.vendor.manage')) {

                throw CashflowException::unauthorized('dismiss vendor requests');

            }



            $accountId = Auth::user()->account_id;

            $vendorRequest = $this->vendorService->dismissVendorRequest($id, $request->input('admin_notes'), $accountId);



            return response()->json(['success' => true, 'data' => $vendorRequest, 'message' => 'Vendor request dismissed.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }
}
