<?php

declare(strict_types=1);

namespace App\Services\CashFlow;

use App\Enums\RequestStatus;
use App\Enums\VendorTransactionStatus;
use App\Enums\VendorTransactionType;
use App\Exceptions\CashflowException;
use App\Helpers\CashflowHelper;
use App\Models\CashFlow\CashflowAuditLog;
use App\Models\CashFlow\Vendor;
use App\Models\CashFlow\VendorRequest;
use App\Models\CashFlow\VendorTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class VendorService
{
    public function __construct(
        private readonly CashflowAuditService $auditService,
        private readonly NotificationService $notificationService,
    ) {}

    // ===================== VENDORS =====================

    /**
     * Get all vendors for account (paginated).
     */
    public function getVendors(int $accountId, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = Vendor::forAccount($accountId)
            ->with('creator:id,name');

        // Sort
        $sort = $filters['sort'] ?? 'name';
        match ($sort) {
            'outstanding_desc' => $query->orderByDesc('cached_balance'),
            'outstanding_asc' => $query->orderBy('cached_balance'),
            default => $query->orderBy('name'),
        };

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
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
            'category_id' => ! empty($data['category_id']) ? (int) $data['category_id'] : null,
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
            $updateData['category_id'] = ! empty($updateData['category_id']) ? (int) $updateData['category_id'] : null;
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
    public function getVendorsOverview(int $accountId, array $params = []): array
    {
        $vendors = Vendor::forAccount($accountId)->where('is_active', true)->get();

        $totalStaticOpening = (float) $vendors->sum('opening_balance');
        $totalOutstanding = $vendors->sum('cached_balance');
        $vendorCount = $vendors->count();
        $vendorsWithBalance = $vendors->where('cached_balance', '>', 0)->count();

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->toDateString();
        $dateExpr = 'COALESCE(transaction_date, DATE(created_at))';

        // Combined opening balance at start of month (same logic as per-vendor ledger):
        // sum(opening_balance) + pre-month purchases - pre-month payments
        $prePeriod = VendorTransaction::forAccount($accountId)
            ->whereRaw("{$dateExpr} < ?", [$monthStart]);
        $prePurchases = (float) (clone $prePeriod)->where('type', VendorTransactionType::Purchase)->where('status', VendorTransactionStatus::Delivered)->sum('amount');
        $prePayments = (float) (clone $prePeriod)->where('type', VendorTransactionType::Payment)->sum('amount');
        $totalOpeningBalance = $totalStaticOpening + $prePurchases - $prePayments;

        // This month's purchases & payments
        $monthBase = VendorTransaction::forAccount($accountId)
            ->whereRaw("{$dateExpr} >= ?", [$monthStart])
            ->whereRaw("{$dateExpr} <= ?", [$monthEnd]);

        $monthPurchases = (clone $monthBase)->where('type', VendorTransactionType::Purchase)->where('status', VendorTransactionStatus::Delivered)->sum('amount');
        $monthPayments = (clone $monthBase)->where('type', VendorTransactionType::Payment)->sum('amount');

        $perPage = 20;

        // Paginated recent purchases (full data with relations for ledger-style display)
        $purchasePage = (int) ($params['purchase_page'] ?? 1);
        $purchaseQuery = VendorTransaction::forAccount($accountId)
            ->where('type', VendorTransactionType::Purchase)
            ->with(['vendor:id,name', 'expense:id,description,expense_date,attachment_url', 'creator:id,name', 'forBranch:id,name'])
            ->orderByRaw("{$dateExpr} DESC, created_at DESC");

        if (! empty($params['purchase_status'])) {
            $purchaseQuery->where('status', $params['purchase_status']);
        }

        $recentPurchases = $purchaseQuery->paginate($perPage, ['*'], 'purchase_page', $purchasePage);

        // Paginated recent payments (full data with relations for ledger-style display)
        $paymentPage = (int) ($params['payment_page'] ?? 1);
        $paymentQuery = VendorTransaction::forAccount($accountId)
            ->where('type', VendorTransactionType::Payment)
            ->with(['vendor:id,name', 'expense:id,description,expense_date,attachment_url', 'creator:id,name', 'forBranch:id,name'])
            ->orderByRaw("{$dateExpr} DESC, created_at DESC");

        if (! empty($params['payment_vendor_id'])) {
            $paymentQuery->where('vendor_id', $params['payment_vendor_id']);
        }

        $recentPayments = $paymentQuery->paginate($perPage, ['*'], 'payment_page', $paymentPage);

        // Active vendors list for the payment vendor filter dropdown
        $activeVendors = $vendors->map(fn ($v) => ['id' => $v->id, 'name' => $v->name])->sortBy('name')->values();

        return [
            'total_opening_balance' => round((float) $totalOpeningBalance, 2),
            'total_outstanding' => round((float) $totalOutstanding, 2),
            'vendor_count' => $vendorCount,
            'vendors_with_balance' => $vendorsWithBalance,
            'month_purchases' => round((float) $monthPurchases, 2),
            'month_payments' => round((float) $monthPayments, 2),
            'recent_purchases' => $recentPurchases,
            'recent_payments' => $recentPayments,
            'active_vendors' => $activeVendors,
        ];
    }

    // ===================== VENDOR LEDGER =====================

    /**
     * Get vendor ledger (transactions) with date filtering, computed opening balance, period stats, and running balance.
     */
    public function getVendorLedger(int $vendorId, int $accountId, array $filters = [], int $perPage = 25): array
    {
        $vendor = Vendor::forAccount($accountId)->findOrFail($vendorId);

        [$dateFrom, $dateTo] = CashflowHelper::defaultDateRange($filters);

        $dateExpr = 'COALESCE(transaction_date, DATE(created_at))';

        // Compute opening balance at period start:
        // vendor.opening_balance + SUM(delivered purchases before date_from) - SUM(payments before date_from)
        $prePeriod = VendorTransaction::forAccount($accountId)
            ->where('vendor_id', $vendorId);

        $prePeriod->whereRaw("{$dateExpr} < ?", [$dateFrom]);

        $prePurchases = (clone $prePeriod)->where('type', VendorTransactionType::Purchase)->where('status', VendorTransactionStatus::Delivered)->sum('amount');
        $prePayments = (clone $prePeriod)->where('type', VendorTransactionType::Payment)->sum('amount');
        $openingBalance = (float) $vendor->opening_balance + (float) $prePurchases - (float) $prePayments;

        // Query for filtered period (DESC for latest-first display)
        $query = VendorTransaction::forAccount($accountId)
            ->where('vendor_id', $vendorId)
            ->with(['expense:id,description,expense_date,attachment_url', 'creator:id,name', 'forBranch:id,name'])
            ->orderByRaw("{$dateExpr} DESC, created_at DESC");

        if ($dateFrom) {
            $query->whereRaw("{$dateExpr} >= ?", [$dateFrom]);
        }
        if ($dateTo) {
            $query->whereRaw("{$dateExpr} <= ?", [$dateTo]);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Period stats (on unfiltered-by-type query for the date range)
        $periodBase = VendorTransaction::forAccount($accountId)
            ->where('vendor_id', $vendorId);

        if ($dateFrom) {
            $periodBase->whereRaw("{$dateExpr} >= ?", [$dateFrom]);
        }
        if ($dateTo) {
            $periodBase->whereRaw("{$dateExpr} <= ?", [$dateTo]);
        }

        $periodPurchases = (clone $periodBase)->where('type', VendorTransactionType::Purchase)->where('status', VendorTransactionStatus::Delivered)->sum('amount');
        $periodPayments = (clone $periodBase)->where('type', VendorTransactionType::Payment)->sum('amount');
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
            if (! empty($filters['type'])) {
                $newerTxs->where('type', $filters['type']);
            }
            $newerTxs = $newerTxs->orderByRaw("{$dateExpr} DESC, created_at DESC")
                ->limit($skipCount)->get();
            foreach ($newerTxs as $ntx) {
                if ($ntx->type === VendorTransactionType::Purchase && $ntx->status !== VendorTransactionStatus::Delivered) {
                    continue;
                }
                $closingBalance -= ($ntx->type === VendorTransactionType::Purchase) ? (float) $ntx->amount : -(float) $ntx->amount;
            }
        }

        // Attach running_balance: iterate in DESC order, closingBalance is the balance
        // AFTER the first (newest) item on this page. Subtract each item going backwards.
        $runBal = $closingBalance;
        $items = $transactions->getCollection()->map(function ($tx) use (&$runBal) {
            $arr = $tx->toArray();
            // Ordered purchases don't affect balance — show null running_balance for them
            if ($tx->type === VendorTransactionType::Purchase && $tx->status !== VendorTransactionStatus::Delivered) {
                $arr['running_balance'] = null;

                return $arr;
            }
            $arr['running_balance'] = round($runBal, 2);
            // Move backwards: undo this transaction to get balance before it
            $runBal -= ($tx->type === VendorTransactionType::Purchase) ? (float) $tx->amount : -(float) $tx->amount;

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

        if ($tx->type !== VendorTransactionType::Purchase) {
            throw new CashflowException('Only purchase records can be marked as delivered.');
        }
        if ($tx->status === VendorTransactionStatus::Delivered) {
            throw new CashflowException('This purchase is already marked as delivered.');
        }

        // Period-lock guard — marking delivered credits the vendor balance,
        // which counts as a state change against the transaction's date. If
        // the period is closed, block.
        $txDate = $tx->transaction_date?->format('Y-m-d') ?? $tx->created_at->format('Y-m-d');
        if (CashflowHelper::isDateInLockedPeriod($txDate, $accountId)) {
            throw CashflowException::periodLocked(
                (int) date('n', strtotime($txDate)),
                (int) date('Y', strtotime($txDate)),
            );
        }

        $oldValues = $tx->toArray();

        DB::transaction(function () use ($tx, $attachmentUrl) {
            $tx->update([
                'status' => VendorTransactionStatus::Delivered,
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

        // Period-lock guard — block edits whose CURRENT date OR NEW date falls
        // in a locked period. The "old" check catches "edit a row from a
        // closed month"; the "new" check catches "move a row's date INTO a
        // locked month".
        $oldDate = $tx->transaction_date?->format('Y-m-d') ?? $tx->created_at->format('Y-m-d');
        $newDate = $data['transaction_date'] ?? $oldDate;
        foreach (array_unique([$oldDate, $newDate]) as $checkDate) {
            if (CashflowHelper::isDateInLockedPeriod($checkDate, $accountId)) {
                throw CashflowException::periodLocked(
                    (int) date('n', strtotime($checkDate)),
                    (int) date('Y', strtotime($checkDate)),
                );
            }
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
            if (! empty($data['is_for_general'])) {
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

            if ($tx->type === VendorTransactionType::Purchase) {
                $oldDelivered = ($oldStatus === VendorTransactionStatus::Delivered);
                $newDelivered = ($newStatus === VendorTransactionStatus::Delivered);

                if (! $oldDelivered && $newDelivered) {
                    // ordered → delivered: add full new amount to balance
                    DB::table('cashflow_vendors')->where('id', $tx->vendor_id)->increment('cached_balance', $newAmount);
                } elseif ($oldDelivered && ! $newDelivered) {
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

        // `data['edit_reason']` is now required on the API edge (see
        // StoreVendorPurchaseRequest::rules()); pass it through so the audit
        // log captures the explanation alongside the diff.
        $this->auditService->log(
            CashflowAuditLog::ACTION_UPDATED,
            CashflowAuditLog::ENTITY_VENDOR_TRANSACTION,
            $tx->id,
            $oldValues,
            $tx->toArray(),
            $data['edit_reason'] ?? null,
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

        // Period-lock guard — deleting reverses the vendor balance change
        // booked against the transaction date. If that month is closed,
        // block.
        $txDate = $tx->transaction_date?->format('Y-m-d') ?? $tx->created_at->format('Y-m-d');
        if (CashflowHelper::isDateInLockedPeriod($txDate, $accountId)) {
            throw CashflowException::periodLocked(
                (int) date('n', strtotime($txDate)),
                (int) date('Y', strtotime($txDate)),
            );
        }

        $oldValues = $tx->toArray();

        DB::transaction(function () use ($tx, $oldValues) {
            // Reverse vendor cached_balance
            if ($tx->type === VendorTransactionType::Purchase) {
                // Only reverse balance for delivered purchases (ordered ones never touched the balance)
                if ($tx->status === VendorTransactionStatus::Delivered) {
                    DB::table('cashflow_vendors')->where('id', $tx->vendor_id)->decrement('cached_balance', $tx->amount);
                }
            } else {
                DB::table('cashflow_vendors')->where('id', $tx->vendor_id)->increment('cached_balance', $tx->amount);
            }
            $tx->delete();

            // Audit inside the transaction so the delete + audit trail are
            // atomic — if the audit write fails we rollback the delete too,
            // instead of leaving the row gone while the client sees an error.
            $this->auditService->log(
                CashflowAuditLog::ACTION_DELETED,
                CashflowAuditLog::ENTITY_VENDOR_TRANSACTION,
                $tx->id,
                $oldValues,
                null
            );
        });

        $this->clearCache($accountId);
    }

    /**
     * Export vendor ledger as array (for CSV generation).
     */
    public function exportVendorLedger(int $vendorId, int $accountId, array $filters = []): array
    {
        $vendor = Vendor::forAccount($accountId)->findOrFail($vendorId);

        $dateFrom = $filters['date_from'] ?? null;
        $dateTo = $filters['date_to'] ?? null;
        $dateExpr = 'COALESCE(transaction_date, DATE(created_at))';

        // Opening balance at period start
        $prePeriod = VendorTransaction::forAccount($accountId)
            ->where('vendor_id', $vendorId);

        if ($dateFrom) {
            $prePeriod->whereRaw("{$dateExpr} < ?", [$dateFrom]);
        } else {
            $prePeriod->whereRaw('1 = 0');
        }

        $prePurchases = (clone $prePeriod)->where('type', VendorTransactionType::Purchase)->where('status', VendorTransactionStatus::Delivered)->sum('amount');
        $prePayments = (clone $prePeriod)->where('type', VendorTransactionType::Payment)->sum('amount');
        $openingBalance = (float) $vendor->opening_balance + (float) $prePurchases - (float) $prePayments;

        $query = VendorTransaction::forAccount($accountId)
            ->where('vendor_id', $vendorId)
            ->with(['expense:id,description,expense_date', 'forBranch:id,name', 'creator:id,name'])
            ->orderByRaw("{$dateExpr} ASC, created_at ASC");

        if ($dateFrom) {
            $query->whereRaw("{$dateExpr} >= ?", [$dateFrom]);
        }
        if ($dateTo) {
            $query->whereRaw("{$dateExpr} <= ?", [$dateTo]);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        $txs = $query->get();

        $rows = [];
        $running = $openingBalance;
        foreach ($txs as $tx) {
            $affectsBalance = $tx->type !== VendorTransactionType::Purchase || $tx->status === VendorTransactionStatus::Delivered;
            if ($affectsBalance) {
                $running += ($tx->type === VendorTransactionType::Purchase) ? (float) $tx->amount : -(float) $tx->amount;
            }
            $desc = ($tx->expense && $tx->expense->description) ? $tx->expense->description : ($tx->description ?? '');
            $branchName = $tx->is_for_general ? 'General' : ($tx->forBranch?->name ?? '');
            $rows[] = [
                'date' => $tx->transaction_date ?? $tx->created_at->toDateString(),
                'type' => ucfirst($tx->type),
                'status' => $tx->status ?? '',
                'description' => $desc,
                'reference' => $tx->reference_no ?? '',
                'branch' => $branchName,
                'purchase' => $tx->type === VendorTransactionType::Purchase ? (float) $tx->amount : '',
                'payment' => $tx->type === VendorTransactionType::Payment ? (float) $tx->amount : '',
                'balance' => $affectsBalance ? round($running, 2) : null,
                'by' => $tx->creator?->name ?? '',
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

        // Period-lock guard — refuse to write into a closed month. Mirrors the
        // same gate Expense and Transfer enforce; without it, vendor purchases
        // can silently sneak into locked periods and corrupt reconciliation.
        $txDate = $data['transaction_date'] ?? now()->toDateString();
        if (CashflowHelper::isDateInLockedPeriod($txDate, $accountId)) {
            throw CashflowException::periodLocked(
                (int) date('n', strtotime($txDate)),
                (int) date('Y', strtotime($txDate)),
            );
        }

        $transaction = DB::transaction(function () use ($data, $accountId) {
            // Observer handles vendor balance updates
            return VendorTransaction::create([
                'account_id' => $accountId,
                'vendor_id' => $data['vendor_id'],
                'type' => $data['type'],
                'status' => $data['status'] ?? VendorTransactionStatus::Delivered,
                'amount' => $data['amount'],
                'expense_id' => $data['expense_id'] ?? null,
                'description' => $data['description'] ?? null,
                'reference_no' => $data['reference_no'] ?? null,
                'attachment_url' => $data['attachment_url'] ?? null,
                'transaction_date' => $data['transaction_date'] ?? now()->toDateString(),
                'for_branch_id' => ! empty($data['is_for_general']) ? null : ($data['for_branch_id'] ?? null),
                'is_for_general' => ! empty($data['is_for_general']) ? 1 : 0,
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
    public function getVendorRequests(int $accountId, ?string $status = null, int $perPage = 25): LengthAwarePaginator
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
            'contact_person' => $data['contact_person'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'payment_terms' => $data['payment_terms'] ?? null,
            'category_id' => $data['category_id'] ?? null,
            'opening_balance' => $data['opening_balance'] ?? 0,
            'address' => $data['address'] ?? null,
            'note' => $data['notes'] ?? ($data['note'] ?? null),
            'requested_by' => Auth::id(),
            'status' => RequestStatus::Pending,
        ]);

        $this->auditService->log(
            CashflowAuditLog::ACTION_CREATED,
            CashflowAuditLog::ENTITY_VENDOR_REQUEST,
            $request->id,
            null,
            $request->toArray()
        );

        // Admin notification is best-effort — if a permission name referenced
        // by the notifier hasn't been seeded yet (Spatie throws PermissionDoesNotExist
        // on lookup of unknown names), we still want the request to land. Log
        // the failure for ops to follow up, then return the saved request.
        try {
            $this->notificationService->notifyVendorRequest(
                $data['name'],
                Auth::user()->name,
                $accountId
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning(
                'notifyVendorRequest failed: ' . $e->getMessage(),
                ['file' => $e->getFile(), 'line' => $e->getLine(), 'request_id' => $request->id]
            );
        }

        return $request;
    }

    /**
     * Approve a vendor request — create the vendor and link it.
     */
    public function approveVendorRequest(int $requestId, int $accountId): VendorRequest
    {
        $vendorRequest = VendorRequest::forAccount($accountId)->findOrFail($requestId);

        if ($vendorRequest->status !== RequestStatus::Pending) {
            throw new CashflowException('Only pending requests can be approved.');
        }

        return DB::transaction(function () use ($vendorRequest, $accountId) {
            $vendor = $this->createVendor([
                'name' => $vendorRequest->name,
                'contact_person' => $vendorRequest->contact_person,
                'phone' => $vendorRequest->phone,
                'email' => $vendorRequest->email,
                'payment_terms' => $vendorRequest->payment_terms ?? 'upfront',
                'category_id' => $vendorRequest->category_id,
                'opening_balance' => $vendorRequest->opening_balance ?? 0,
                'address' => $vendorRequest->address,
                'notes' => 'Created from vendor request #'.$vendorRequest->id.($vendorRequest->note ? "\n".$vendorRequest->note : ''),
            ], $accountId);

            $vendorRequest->update([
                'status' => RequestStatus::Approved,
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

        if ($vendorRequest->status !== RequestStatus::Pending) {
            throw new CashflowException('Only pending requests can be dismissed.');
        }

        $vendorRequest->update([
            'status' => RequestStatus::Dismissed,
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
