<?php

namespace App\Services\CashFlow;

use App\Exceptions\CashflowException;
use App\Models\CashFlow\CashflowAuditLog;
use App\Models\CashFlow\Vendor;
use App\Models\CashFlow\VendorRequest;
use App\Models\CashFlow\VendorTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class VendorService
{
    private CashflowAuditService $auditService;
    private NotificationService $notificationService;

    public function __construct(CashflowAuditService $auditService, NotificationService $notificationService)
    {
        $this->auditService = $auditService;
        $this->notificationService = $notificationService;
    }

    // ===================== VENDORS =====================

    /**
     * Get all vendors for account (paginated).
     */
    public function getVendors(int $accountId, array $filters = [], int $perPage = 25)
    {
        $query = Vendor::forAccount($accountId)
            ->with('creator:id,name')
            ->orderBy('name');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a vendor.
     */
    public function createVendor(array $data, int $accountId): Vendor
    {
        $vendor = Vendor::create([
            'account_id' => $accountId,
            'name' => $data['name'],
            'contact_person' => $data['contact_person'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'payment_terms' => $data['payment_terms'] ?? 'upfront',
            'category' => $data['category'] ?? null,
            'category_id' => !empty($data['category_id']) ? (int) $data['category_id'] : null,
            'opening_balance' => $data['opening_balance'] ?? 0,
            'cached_balance' => $data['opening_balance'] ?? 0,
            'is_active' => 1,
            'notes' => $data['notes'] ?? null,
            'created_by' => Auth::id(),
        ]);

        $this->auditService->log(
            CashflowAuditLog::ACTION_CREATED,
            CashflowAuditLog::ENTITY_VENDOR,
            $vendor->id,
            null,
            $vendor->toArray()
        );

        $this->clearCache($accountId);
        return $vendor;
    }

    /**
     * Update a vendor.
     */
    public function updateVendor(int $vendorId, array $data, int $accountId): Vendor
    {
        $vendor = Vendor::forAccount($accountId)->findOrFail($vendorId);
        $oldValues = $vendor->toArray();

        $allowed = ['name', 'contact_person', 'phone', 'email', 'address', 'payment_terms', 'category', 'category_id', 'notes', 'is_active', 'opening_balance'];
        $updateData = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        // Coerce category_id: empty string → null
        if (array_key_exists('category_id', $updateData)) {
            $updateData['category_id'] = !empty($updateData['category_id']) ? (int) $updateData['category_id'] : null;
        }

        // If opening_balance changed, adjust cached_balance by the same delta
        if (array_key_exists('opening_balance', $updateData)) {
            $oldOpening = (float) $vendor->opening_balance;
            $newOpening = (float) $updateData['opening_balance'];
            $delta = $newOpening - $oldOpening;
            if ($delta != 0) {
                $updateData['cached_balance'] = (float) $vendor->cached_balance + $delta;
            }
        }

        $vendor->update($updateData);

        $this->auditService->log(
            CashflowAuditLog::ACTION_UPDATED,
            CashflowAuditLog::ENTITY_VENDOR,
            $vendor->id,
            $oldValues,
            $vendor->fresh()->toArray()
        );

        $this->clearCache($accountId);
        return $vendor->fresh();
    }

    // ===================== VENDOR OVERVIEW =====================

    /**
     * Get vendors overview dashboard data: summary stats, top outstanding, recent transactions.
     */
    public function getVendorsOverview(int $accountId): array
    {
        $vendors = Vendor::forAccount($accountId)->where('is_active', true)->get();

        $totalStaticOpening = (float) $vendors->sum('opening_balance');
        $totalOutstanding = $vendors->sum('cached_balance');
        $vendorCount = $vendors->count();
        $vendorsWithBalance = $vendors->where('cached_balance', '>', 0)->count();

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->toDateString();
        $dateExpr = "COALESCE(transaction_date, DATE(created_at))";

        // Combined opening balance at start of month (same logic as per-vendor ledger):
        // sum(opening_balance) + pre-month purchases - pre-month payments
        $prePeriod = VendorTransaction::forAccount($accountId)
            ->whereRaw("{$dateExpr} < ?", [$monthStart]);
        $prePurchases = (float) (clone $prePeriod)->where('type', 'purchase')->sum('amount');
        $prePayments = (float) (clone $prePeriod)->where('type', 'payment')->sum('amount');
        $totalOpeningBalance = $totalStaticOpening + $prePurchases - $prePayments;

        // This month's purchases & payments
        $monthBase = VendorTransaction::forAccount($accountId)
            ->whereRaw("{$dateExpr} >= ?", [$monthStart])
            ->whereRaw("{$dateExpr} <= ?", [$monthEnd]);

        $monthPurchases = (clone $monthBase)->where('type', 'purchase')->sum('amount');
        $monthPayments = (clone $monthBase)->where('type', 'payment')->sum('amount');

        // Top 5 vendors by outstanding balance
        $topVendors = Vendor::forAccount($accountId)
            ->where('is_active', true)
            ->where('cached_balance', '>', 0)
            ->orderByDesc('cached_balance')
            ->limit(5)
            ->get(['id', 'name', 'cached_balance', 'payment_terms']);

        // Recent 10 purchases
        $recentPurchases = VendorTransaction::forAccount($accountId)
            ->where('type', 'purchase')
            ->with(['vendor:id,name'])
            ->orderByRaw("{$dateExpr} DESC, created_at DESC")
            ->limit(10)
            ->get(['id', 'vendor_id', 'amount', 'description', 'transaction_date', 'created_at']);

        // Recent 10 payments
        $recentPayments = VendorTransaction::forAccount($accountId)
            ->where('type', 'payment')
            ->with(['vendor:id,name', 'expense:id,description'])
            ->orderByRaw("{$dateExpr} DESC, created_at DESC")
            ->limit(10)
            ->get(['id', 'vendor_id', 'expense_id', 'amount', 'description', 'transaction_date', 'created_at']);

        return [
            'total_opening_balance' => round((float) $totalOpeningBalance, 2),
            'total_outstanding' => round((float) $totalOutstanding, 2),
            'vendor_count' => $vendorCount,
            'vendors_with_balance' => $vendorsWithBalance,
            'month_purchases' => round((float) $monthPurchases, 2),
            'month_payments' => round((float) $monthPayments, 2),
            'top_vendors' => $topVendors,
            'recent_purchases' => $recentPurchases,
            'recent_payments' => $recentPayments,
        ];
    }

    // ===================== VENDOR LEDGER =====================

    /**
     * Get vendor ledger (transactions) with date filtering, computed opening balance, period stats, and running balance.
     */
    public function getVendorLedger(int $vendorId, int $accountId, array $filters = [], int $perPage = 25)
    {
        $vendor = Vendor::forAccount($accountId)->findOrFail($vendorId);

        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;

        // If no date filter, default to current month
        if (!$dateFrom && !$dateTo) {
            $dateFrom = now()->startOfMonth()->toDateString();
            $dateTo = now()->toDateString();
        }

        $dateExpr = "COALESCE(transaction_date, DATE(created_at))";

        // Compute opening balance at period start:
        // vendor.opening_balance + SUM(purchases before date_from) - SUM(payments before date_from)
        $prePeriod = VendorTransaction::forAccount($accountId)
            ->where('vendor_id', $vendorId)
            ->whereRaw("{$dateExpr} < ?", [$dateFrom]);

        $prePurchases = (clone $prePeriod)->where('type', 'purchase')->sum('amount');
        $prePayments = (clone $prePeriod)->where('type', 'payment')->sum('amount');
        $openingBalance = (float) $vendor->opening_balance + (float) $prePurchases - (float) $prePayments;

        // Query for filtered period (DESC for latest-first display)
        $query = VendorTransaction::forAccount($accountId)
            ->where('vendor_id', $vendorId)
            ->with(['expense:id,description,expense_date,attachment_url', 'creator:id,name', 'forBranch:id,name'])
            ->whereRaw("{$dateExpr} >= ?", [$dateFrom])
            ->whereRaw("{$dateExpr} <= ?", [$dateTo])
            ->orderByRaw("{$dateExpr} DESC, created_at DESC");

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        // Period stats (on unfiltered-by-type query for the date range)
        $periodBase = VendorTransaction::forAccount($accountId)
            ->where('vendor_id', $vendorId)
            ->whereRaw("{$dateExpr} >= ?", [$dateFrom])
            ->whereRaw("{$dateExpr} <= ?", [$dateTo]);

        $periodPurchases = (clone $periodBase)->where('type', 'purchase')->sum('amount');
        $periodPayments = (clone $periodBase)->where('type', 'payment')->sum('amount');
        $periodCount = (clone $periodBase)->count();

        // Get paginated transactions (latest first)
        $transactions = $query->paginate($perPage);

        // Compute running balance: we need the cumulative balance up to each row.
        // Strategy: find the closing balance for the LAST item on this page,
        // then work backwards. The closing balance for the entire period is:
        // openingBalance + periodPurchases - periodPayments (unfiltered by type).
        // For page N (DESC), we skip the first (N-1)*perPage newest items.
        $closingBalance = $openingBalance + (float) $periodPurchases - (float) $periodPayments;

        // If not page 1, subtract the newer transactions that come before this page (in DESC order)
        if ($transactions->currentPage() > 1) {
            $skipCount = ($transactions->currentPage() - 1) * $perPage;
            $newerTxs = VendorTransaction::forAccount($accountId)
                ->where('vendor_id', $vendorId)
                ->whereRaw("{$dateExpr} >= ?", [$dateFrom])
                ->whereRaw("{$dateExpr} <= ?", [$dateTo]);
            if (!empty($filters['type'])) {
                $newerTxs->where('type', $filters['type']);
            }
            $newerTxs = $newerTxs->orderByRaw("{$dateExpr} DESC, created_at DESC")
                ->limit($skipCount)->get();
            foreach ($newerTxs as $ntx) {
                $closingBalance -= ($ntx->type === 'purchase') ? (float) $ntx->amount : -(float) $ntx->amount;
            }
        }

        // Attach running_balance: iterate in DESC order, closingBalance is the balance
        // AFTER the first (newest) item on this page. Subtract each item going backwards.
        $runBal = $closingBalance;
        $items = $transactions->getCollection()->map(function ($tx) use (&$runBal) {
            $arr = $tx->toArray();
            $arr['running_balance'] = round($runBal, 2);
            // Move backwards: undo this transaction to get balance before it
            $runBal -= ($tx->type === 'purchase') ? (float) $tx->amount : -(float) $tx->amount;
            return $arr;
        });
        $transactions->setCollection($items);

        return [
            'vendor' => $vendor,
            'transactions' => $transactions,
            'opening_balance' => round($openingBalance, 2),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'period_stats' => [
                'total_purchases' => round((float) $periodPurchases, 2),
                'total_payments' => round((float) $periodPayments, 2),
                'net' => round((float) $periodPurchases - (float) $periodPayments, 2),
                'count' => $periodCount,
            ],
        ];
    }

    /**
     * Mark an "ordered" purchase as "delivered", optionally attaching a Drive URL.
     * This is a restricted update — only status and attachment_url can change.
     * Gated by the create (cashflow_vendor_transaction) permission at controller level.
     */
    public function deliverTransaction(int $transactionId, ?string $attachmentUrl, int $accountId): VendorTransaction
    {
        $tx = VendorTransaction::forAccount($accountId)->findOrFail($transactionId);

        if ($tx->type !== VendorTransaction::TYPE_PURCHASE) {
            throw new CashflowException('Only purchase records can be marked as delivered.');
        }
        if ($tx->status === VendorTransaction::STATUS_DELIVERED) {
            throw new CashflowException('This purchase is already marked as delivered.');
        }

        $oldValues = $tx->toArray();

        DB::transaction(function () use ($tx, $attachmentUrl) {
            $tx->update([
                'status'         => VendorTransaction::STATUS_DELIVERED,
                'attachment_url' => $attachmentUrl ?: $tx->attachment_url,
            ]);
            // Ordered purchases were excluded from balance at create time — add now
            DB::table('cashflow_vendors')
                ->where('id', $tx->vendor_id)
                ->increment('cached_balance', $tx->amount);
        });

        $this->auditService->log(
            CashflowAuditLog::ACTION_UPDATED,
            CashflowAuditLog::ENTITY_VENDOR_TRANSACTION,
            $tx->id,
            $oldValues,
            $tx->fresh()->toArray()
        );

        $this->clearCache($accountId);
        return $tx->fresh();
    }

    /**
     * Update a standalone purchase transaction (not linked to an expense).
     */
    public function updateTransaction(int $transactionId, array $data, int $accountId): VendorTransaction
    {
        $tx = VendorTransaction::forAccount($accountId)->findOrFail($transactionId);

        if ($tx->expense_id) {
            throw new CashflowException('Cannot edit a payment linked to an expense. Edit the expense instead.');
        }

        $oldValues = $tx->toArray();
        $oldAmount = (float) $tx->amount;

        $tx = DB::transaction(function () use ($tx, $data, $oldAmount) {
            $updateData = [];
            $allowed = ['description', 'amount', 'reference_no', 'attachment_url', 'transaction_date', 'for_branch_id', 'is_for_general', 'status'];
            foreach ($allowed as $field) {
                if (array_key_exists($field, $data)) {
                    $updateData[$field] = $data[$field];
                }
            }

            // Handle branch/general
            if (!empty($data['is_for_general'])) {
                $updateData['for_branch_id'] = null;
                $updateData['is_for_general'] = 1;
            } elseif (isset($data['for_branch_id'])) {
                $updateData['is_for_general'] = 0;
            }

            $oldStatus = $tx->status;
            $tx->update($updateData);
            $fresh = $tx->fresh();
            $newAmount = (float) $fresh->amount;
            $newStatus = $fresh->status;

            if ($tx->type === 'purchase') {
                $oldDelivered = ($oldStatus === VendorTransaction::STATUS_DELIVERED);
                $newDelivered = ($newStatus === VendorTransaction::STATUS_DELIVERED);

                if (!$oldDelivered && $newDelivered) {
                    // ordered → delivered: add full new amount to balance
                    DB::table('cashflow_vendors')->where('id', $tx->vendor_id)->increment('cached_balance', $newAmount);
                } elseif ($oldDelivered && !$newDelivered) {
                    // delivered → ordered: remove old amount from balance
                    DB::table('cashflow_vendors')->where('id', $tx->vendor_id)->decrement('cached_balance', $oldAmount);
                } elseif ($oldDelivered && $newDelivered && $oldAmount != $newAmount) {
                    // delivered → delivered, amount changed: adjust by diff
                    $diff = $newAmount - $oldAmount;
                    if ($diff > 0) {
                        DB::table('cashflow_vendors')->where('id', $tx->vendor_id)->increment('cached_balance', $diff);
                    } else {
                        DB::table('cashflow_vendors')->where('id', $tx->vendor_id)->decrement('cached_balance', abs($diff));
                    }
                }
                // ordered → ordered: no balance change regardless of amount
            } else {
                // Payment: balance adjustment is always active (not status-gated)
                if ($oldAmount != $newAmount) {
                    $diff = $newAmount - $oldAmount;
                    if ($diff > 0) {
                        DB::table('cashflow_vendors')->where('id', $tx->vendor_id)->decrement('cached_balance', $diff);
                    } else {
                        DB::table('cashflow_vendors')->where('id', $tx->vendor_id)->increment('cached_balance', abs($diff));
                    }
                }
            }

            return $fresh;
        });

        $this->auditService->log(
            CashflowAuditLog::ACTION_UPDATED,
            CashflowAuditLog::ENTITY_VENDOR_TRANSACTION,
            $tx->id,
            $oldValues,
            $tx->toArray()
        );

        $this->clearCache($accountId);
        return $tx;
    }

    /**
     * Delete a standalone purchase transaction (not linked to an expense).
     */
    public function deleteTransaction(int $transactionId, int $accountId): void
    {
        $tx = VendorTransaction::forAccount($accountId)->findOrFail($transactionId);

        if ($tx->expense_id) {
            throw new CashflowException('Cannot delete a payment linked to an expense. Void the expense instead.');
        }

        $oldValues = $tx->toArray();

        DB::transaction(function () use ($tx) {
            // Reverse vendor cached_balance
            if ($tx->type === 'purchase') {
                // Only reverse balance for delivered purchases (ordered ones never touched the balance)
                if ($tx->status === VendorTransaction::STATUS_DELIVERED) {
                    DB::table('cashflow_vendors')->where('id', $tx->vendor_id)->decrement('cached_balance', $tx->amount);
                }
            } else {
                DB::table('cashflow_vendors')->where('id', $tx->vendor_id)->increment('cached_balance', $tx->amount);
            }
            $tx->delete();
        });

        $this->auditService->log(
            CashflowAuditLog::ACTION_DELETED,
            CashflowAuditLog::ENTITY_VENDOR_TRANSACTION,
            $tx->id,
            $oldValues,
            null
        );

        $this->clearCache($accountId);
    }

    /**
     * Export vendor ledger as array (for CSV generation).
     */
    public function exportVendorLedger(int $vendorId, int $accountId, array $filters = []): array
    {
        $vendor = Vendor::forAccount($accountId)->findOrFail($vendorId);

        $dateFrom = $filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateTo = $filters['date_to'] ?? now()->toDateString();
        $dateExpr = "COALESCE(transaction_date, DATE(created_at))";

        // Opening balance at period start
        $prePeriod = VendorTransaction::forAccount($accountId)
            ->where('vendor_id', $vendorId)
            ->whereRaw("{$dateExpr} < ?", [$dateFrom]);
        $prePurchases = (clone $prePeriod)->where('type', 'purchase')->sum('amount');
        $prePayments = (clone $prePeriod)->where('type', 'payment')->sum('amount');
        $openingBalance = (float) $vendor->opening_balance + (float) $prePurchases - (float) $prePayments;

        $query = VendorTransaction::forAccount($accountId)
            ->where('vendor_id', $vendorId)
            ->with(['expense:id,description,expense_date', 'forBranch:id,name', 'creator:id,name'])
            ->whereRaw("{$dateExpr} >= ?", [$dateFrom])
            ->whereRaw("{$dateExpr} <= ?", [$dateTo])
            ->orderByRaw("{$dateExpr} ASC, created_at ASC");

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $txs = $query->get();

        $rows = [];
        $running = $openingBalance;
        foreach ($txs as $tx) {
            $running += ($tx->type === 'purchase') ? (float) $tx->amount : -(float) $tx->amount;
            $desc = ($tx->expense && $tx->expense->description) ? $tx->expense->description : ($tx->description ?? '');
            $branchName = $tx->is_for_general ? 'General' : ($tx->forBranch ? $tx->forBranch->name : '');
            $rows[] = [
                'date' => $tx->transaction_date ?? $tx->created_at->toDateString(),
                'type' => ucfirst($tx->type),
                'description' => $desc,
                'reference' => $tx->reference_no ?? '',
                'branch' => $branchName,
                'purchase' => $tx->type === 'purchase' ? (float) $tx->amount : '',
                'payment' => $tx->type === 'payment' ? (float) $tx->amount : '',
                'balance' => round($running, 2),
                'by' => $tx->creator ? $tx->creator->name : '',
            ];
        }

        return [
            'vendor' => $vendor,
            'opening_balance' => round($openingBalance, 2),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'rows' => $rows,
        ];
    }

    /**
     * Record a standalone vendor transaction (purchase without expense link).
     */
    public function recordTransaction(array $data, int $accountId): VendorTransaction
    {
        $vendor = Vendor::forAccount($accountId)->findOrFail($data['vendor_id']);

        $transaction = DB::transaction(function () use ($data, $accountId) {
            // Observer handles vendor balance updates
            return VendorTransaction::create([
                'account_id' => $accountId,
                'vendor_id' => $data['vendor_id'],
                'type' => $data['type'],
                'status' => $data['status'] ?? VendorTransaction::STATUS_DELIVERED,
                'amount' => $data['amount'],
                'expense_id' => $data['expense_id'] ?? null,
                'description' => $data['description'] ?? null,
                'reference_no' => $data['reference_no'] ?? null,
                'attachment_url' => $data['attachment_url'] ?? null,
                'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
                'for_branch_id' => !empty($data['is_for_general']) ? null : ($data['for_branch_id'] ?? null),
                'is_for_general' => !empty($data['is_for_general']) ? 1 : 0,
                'created_by' => Auth::id(),
            ]);
        });

        $this->auditService->log(
            CashflowAuditLog::ACTION_CREATED,
            CashflowAuditLog::ENTITY_VENDOR_TRANSACTION,
            $transaction->id,
            null,
            $transaction->toArray()
        );

        $this->clearCache($accountId);
        return $transaction;
    }

    // ===================== VENDOR REQUESTS =====================

    /**
     * Get vendor requests.
     */
    public function getVendorRequests(int $accountId, ?string $status = null, int $perPage = 25)
    {
        $query = VendorRequest::forAccount($accountId)
            ->with('requester:id,name')
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a vendor request (staff can request new vendors).
     */
    public function createVendorRequest(array $data, int $accountId): VendorRequest
    {
        $request = VendorRequest::create([
            'account_id' => $accountId,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'note' => $data['note'] ?? null,
            'requested_by' => Auth::id(),
            'status' => VendorRequest::STATUS_PENDING,
        ]);

        $this->auditService->log(
            CashflowAuditLog::ACTION_CREATED,
            CashflowAuditLog::ENTITY_VENDOR_REQUEST,
            $request->id,
            null,
            $request->toArray()
        );

        $this->notificationService->notifyVendorRequest(
            $data['name'],
            Auth::user()->name,
            $accountId
        );

        return $request;
    }

    /**
     * Approve a vendor request — create the vendor and link it.
     */
    public function approveVendorRequest(int $requestId, int $accountId): VendorRequest
    {
        $vendorRequest = VendorRequest::forAccount($accountId)->findOrFail($requestId);

        if ($vendorRequest->status !== VendorRequest::STATUS_PENDING) {
            throw new CashflowException('Only pending requests can be approved.');
        }

        return DB::transaction(function () use ($vendorRequest, $accountId) {
            $vendor = $this->createVendor([
                'name' => $vendorRequest->name,
                'phone' => $vendorRequest->phone,
                'notes' => 'Created from vendor request #' . $vendorRequest->id,
            ], $accountId);

            $vendorRequest->update([
                'status' => VendorRequest::STATUS_APPROVED,
                'vendor_id' => $vendor->id,
            ]);

            $this->auditService->log(
                CashflowAuditLog::ACTION_APPROVED,
                CashflowAuditLog::ENTITY_VENDOR_REQUEST,
                $vendorRequest->id,
                ['status' => 'pending'],
                ['status' => 'approved', 'vendor_id' => $vendor->id]
            );

            return $vendorRequest->fresh()->load('vendor:id,name');
        });
    }

    /**
     * Dismiss a vendor request.
     */
    public function dismissVendorRequest(int $requestId, ?string $adminNotes, int $accountId): VendorRequest
    {
        $vendorRequest = VendorRequest::forAccount($accountId)->findOrFail($requestId);

        if ($vendorRequest->status !== VendorRequest::STATUS_PENDING) {
            throw new CashflowException('Only pending requests can be dismissed.');
        }

        $vendorRequest->update([
            'status' => VendorRequest::STATUS_DISMISSED,
            'admin_notes' => $adminNotes,
        ]);

        $this->auditService->log(
            CashflowAuditLog::ACTION_REJECTED,
            CashflowAuditLog::ENTITY_VENDOR_REQUEST,
            $vendorRequest->id,
            ['status' => 'pending'],
            ['status' => 'dismissed', 'admin_notes' => $adminNotes],
            $adminNotes
        );

        return $vendorRequest->fresh();
    }

    private function clearCache(int $accountId): void
    {
        Cache::forget("cashflow_vendors_{$accountId}");
    }
}
