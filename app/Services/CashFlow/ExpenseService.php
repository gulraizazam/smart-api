<?php

declare(strict_types=1);
namespace App\Services\CashFlow;

use App\Enums\ExpenseStatus;
use App\Enums\VendorTransactionType;
use App\Exceptions\CashflowException;
use App\Helpers\CashflowHelper;
use App\Models\CashFlow\CashflowAuditLog;
use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\Expense;
use App\Models\CashFlow\ExpenseAttachment;
use App\Models\CashFlow\PeriodLock;
use App\Models\CashFlow\VendorTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExpenseService
{
    public function __construct(
        private readonly CashflowAuditService $auditService,
        private readonly CashflowSettingService $settingService,
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Get paginated expenses with filters for datatable.
     */
    public function getExpenses(int $accountId, array $filters = [], int $perPage = 25): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Expense::forAccount($accountId)
            ->with([
                'category:id,name',
                'paidFromPool:id,name,type',
                'forBranch:id,name',
                'paymentMethod:id,name',
                'vendor:id,name',
                'staff:id,name',
                'creator:id,name',
                'verifier:id,name',
                'lastEditLog',
                // Lightweight attachment metadata — the SPA uses just
                // enough to render the paperclip indicator + filename
                // popover. Signed URLs are minted on demand via the
                // dedicated endpoint to keep them short-lived.
                'attachments:id,expense_id,file_name,mime_type,file_size,sha256',
            ])
            ->orderBy('expense_date', 'desc')
            ->orderBy('id', 'desc');

        // ID filter — drives the "open this specific payment" jumps from
        // the view drawer (e.g. clicking #234 in a Reused-receipt flag
        // badge). Per-account scoping is already applied above, so passing
        // an ID from another tenant returns an empty result rather than
        // a leak.
        if (!empty($filters['id'])) {
            $query->where('id', (int) $filters['id']);
        }

        // Status filter — the SPA quick-filter chips drive this. Personal
        // "my pending" / "my rejected" pseudo-statuses were retired 2026-05-13
        // along with their chips; unknown statuses fall through to a literal
        // column match (which yields an empty set, the safest default).
        if (!empty($filters['status'])) {
            $status = $filters['status'];
            if ($status === 'flagged') {
                $query->where('is_flagged', true)->whereNull('voided_at');
            } elseif ($status === 'voided') {
                $query->whereNotNull('voided_at');
            } elseif (str_starts_with($status, 'flag:')) {
                // `flag:<reason>` — drill into one specific flag rule.
                // The reason ride alongside others in a `;`-separated
                // string, so we match via LIKE. Same filter could be
                // expressed as flagged + flag_reason filter, but
                // tunneling through the single status slot keeps the
                // SPA chip dispatcher uniform.
                $reason = substr($status, 5);
                $query->where('is_flagged', true)
                    ->whereNull('voided_at')
                    ->where('flag_reason', 'like', "%{$reason}%");
            } else {
                $query->where('status', $status)->whereNull('voided_at');
            }
        }

        // Branch filter
        if (!empty($filters['branch_id'])) {
            if ($filters['branch_id'] === 'general') {
                $query->where('is_for_general', 1);
            } else {
                $query->where('for_branch_id', $filters['branch_id']);
            }
        }

        // Pool filter (paid from)
        if (!empty($filters['pool_id'])) {
            $query->where('paid_from_pool_id', $filters['pool_id']);
        }

        // Category filter
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        // Date range — null-safe scope: either bound may be omitted
        // (open-ended ranges filter correctly; no both-bound guard).
        $query->inDateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null);

        // Flagged only
        if (!empty($filters['flagged'])) {
            $query->flagged();
        }

        // Voided filter
        if (isset($filters['voided'])) {
            if ($filters['voided']) {
                $query->voided();
            } else {
                $query->notVoided();
            }
        }

        // Search — multi-field match across the payment row's own columns
        // and its related lookups (category / pool / vendor / staff /
        // creator / payment method). Two opt-in signals switch the matcher
        // from substring to word-boundary, so typing "pens " (with trailing
        // space) or `"pens"` (quoted) only matches the word "pens", not
        // "Dispenser" / "Expense":
        //
        //   • trailing whitespace on the raw query
        //   • the trimmed query wrapped in single or double quotes
        //
        // Without either signal, substring LIKE is used. Pure-numeric input
        // takes an exact-amount match either way.
        if (!empty($filters['search'])) {
            $raw = (string) $filters['search'];
            $hasTrailingSpace = preg_match('/\s$/', $raw) === 1;
            $term = trim($raw);
            // `#234` is the canonical payment-ID syntax used throughout
            // the SPA (row badge, view-drawer kicker, Reused-receipt
            // flag matches). Strip the leading `#` so the search treats
            // it as a numeric ID lookup.
            $idOnly = false;
            if ($term !== '' && str_starts_with($term, '#')) {
                $term = substr($term, 1);
                $idOnly = true;
            }
            $isQuoted = strlen($term) >= 2 && (
                (str_starts_with($term, '"') && str_ends_with($term, '"')) ||
                (str_starts_with($term, "'") && str_ends_with($term, "'"))
            );
            if ($isQuoted) {
                $term = substr($term, 1, -1);
            }
            $wordMatch = ($hasTrailingSpace || $isQuoted) && $term !== '';
            $isNumeric = $term !== '' && preg_match('/^\d+(\.\d+)?$/', $term) === 1;

            // Build the per-column constraint list once and reuse for every
            // searched field. Word-boundary mode emits four patterns so a
            // standard B-tree index on `name` still helps the leading-anchor
            // ones.
            $applyLike = function ($q, string $column) use ($term, $wordMatch): void {
                if ($wordMatch) {
                    $q->where(function ($w) use ($column, $term) {
                        $w->where($column, '=', $term)
                            ->orWhere($column, 'like', "{$term} %")
                            ->orWhere($column, 'like', "% {$term}")
                            ->orWhere($column, 'like', "% {$term} %");
                    });
                } else {
                    $q->where($column, 'like', "%{$term}%");
                }
            };

            if ($term !== '') {
                // `#234` syntax — narrow to a single ID exact-match.
                // Skips all the relation joins / LIKE scans since the
                // user is asking for one specific payment.
                if ($idOnly && $isNumeric) {
                    $query->where('id', (int) $term);
                } else {
                    $query->where(function ($q) use ($applyLike, $term, $isNumeric) {
                        // Same-table columns. The first call uses `where`
                        // because we're inside a fresh group, then we OR
                        // the rest in.
                        $q->where(function ($w) use ($applyLike) { $applyLike($w, 'description'); })
                            ->orWhere(function ($w) use ($applyLike) { $applyLike($w, 'reference_no'); })
                            ->orWhere(function ($w) use ($applyLike) { $applyLike($w, 'notes'); });
                        if ($isNumeric) {
                            // Pure-numeric input matches either an
                            // amount or an ID — both common reasons a
                            // user would type a number into search.
                            $q->orWhere('amount', '=', $term)
                                ->orWhere('id', '=', (int) $term);
                        }
                        foreach ([
                            'category', 'paidFromPool', 'vendor', 'staff', 'creator', 'paymentMethod',
                        ] as $relation) {
                            $q->orWhereHas($relation, function ($rq) use ($applyLike) {
                                $applyLike($rq, 'name');
                            });
                        }
                    });
                }
            }
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a new expense.
     */
    public function create(array $data, int $accountId): Expense
    {
        $user = Auth::user();

        // Check period lock
        if (CashflowHelper::isDateInLockedPeriod($data['expense_date'], $accountId)) {
            throw CashflowException::periodLocked(
                (int) date('n', strtotime($data['expense_date'])),
                (int) date('Y', strtotime($data['expense_date']))
            );
        }

        // 2026-05-13 — Pending workflow retired (Option A). Every payment
        // posts as Approved at create time; the flag system carries the
        // "needs admin review" signal instead. The approval_threshold
        // setting still drives the "Above threshold" flag rule inside
        // checkAndFlag(), so the meaning of the number is preserved —
        // it just flags rather than gates.
        $status = ExpenseStatus::Approved;

        return DB::transaction(function () use ($data, $accountId, $user, $status) {
            $expense = Expense::create([
                'account_id' => $accountId,
                'expense_date' => $data['expense_date'],
                'amount' => $data['amount'],
                'category_id' => $data['category_id'],
                'paid_from_pool_id' => $data['paid_from_pool_id'] ?? null,
                'for_branch_id' => !empty($data['is_for_general']) ? null : ($data['for_branch_id'] ?? null),
                'payment_method_id' => $data['payment_method_id'],
                'vendor_id' => $data['vendor_id'] ?? null,
                'staff_id' => $data['staff_id'] ?? null,
                'description' => $data['description'],
                'reference_no' => $data['reference_no'] ?? null,
                'attachment_url' => $data['attachment_url'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => $status,
                'verified_by' => $status === ExpenseStatus::Approved ? $user->id : null,
                'is_flagged' => 0,
                'is_for_general' => !empty($data['is_for_general']) ? 1 : 0,
                'created_by' => $user->id,
            ]);

            // Auto-create vendor payment transaction if vendor is selected and expense is upfront
            if ($expense->vendor_id) {
                VendorTransaction::create([
                    'account_id' => $accountId,
                    'vendor_id' => $expense->vendor_id,
                    'type' => VendorTransactionType::Payment,
                    'amount' => $expense->amount,
                    'expense_id' => $expense->id,
                    'description' => 'Payment via expense #' . $expense->id,
                    'reference_no' => $expense->reference_no,
                    'transaction_date' => $expense->expense_date->format('Y-m-d'),
                    'for_branch_id' => $expense->for_branch_id,
                    'is_for_general' => $expense->is_for_general ? 1 : 0,
                    'created_by' => $user->id,
                ]);
            }

            // Bind newly-uploaded attachments BEFORE the flag check runs —
            // the No-receipt rule looks at the attachments collection too,
            // so an uploaded receipt should suppress the flag immediately.
            $this->bindAttachments($expense, $data['attachment_ids'] ?? []);

            // Run flagging checks
            $expense->refresh();
            $this->checkAndFlag($expense, $accountId);

            $this->auditService->log(
                CashflowAuditLog::ACTION_CREATED,
                CashflowAuditLog::ENTITY_EXPENSE,
                $expense->id,
                null,
                $expense->toArray()
            );

            // Notify admins if pending
            if ($status === ExpenseStatus::Pending) {
                $this->notificationService->notifyExpensePending($expense, $accountId);
            }

            // Notify branch manager when expense recorded for their branch
            $this->notificationService->notifyExpenseForBranch($expense, $accountId);

            // Check for negative pool and notify
            $pool = $expense->paid_from_pool_id ? \App\Models\CashFlow\CashPool::find($expense->paid_from_pool_id) : null;
            if ($pool && (float) $pool->cached_balance < 0) {
                $this->notificationService->notifyNegativePool(
                    $pool->name,
                    (float) $pool->cached_balance,
                    $pool->location_id,
                    $accountId
                );
            }

            return $expense->load([
                'category:id,name', 'paidFromPool:id,name', 'forBranch:id,name',
                'paymentMethod:id,name', 'vendor:id,name', 'creator:id,name',
            ]);
        });
    }

    // approve() / reject() / resubmit() retired 2026-05-13 (Option A).
    // The Pending-status workflow was collapsed into the flag system:
    // every payment posts as Approved, "Above threshold" / suspicious rows
    // are surfaced via flags, and admin's only undo path is Void. The
    // old methods + their controller endpoints + routes are deleted.


    /**
     * Admin edit of an expense (requires reason).
     *
     * The expense row is fetched with `lockForUpdate()` INSIDE the
     * transaction so two concurrent edits can't both compute their
     * pool delta from the same baseline `amount`. Without the lock,
     * the observer's `$expense->getOriginal('amount')` returns the
     * pre-edit value seen by EACH request — so two edits would both
     * subtract their own delta from the same starting point, leaving
     * the pool double-debited.
     */
    public function adminEdit(int $expenseId, array $data, int $accountId): Expense
    {
        $auditRelations = ['category:id,name', 'paidFromPool:id,name', 'paymentMethod:id,name', 'forBranch:id,name', 'vendor:id,name', 'staff:id,name'];

        // Cheap pre-checks before opening the transaction. These can
        // run on a non-locked read because the answers don't change
        // under concurrent edits (voided is sticky; period-lock is
        // per-date).
        $existing = Expense::forAccount($accountId)->findOrFail($expenseId);

        if ($existing->isVoided()) {
            throw new CashflowException('Voided expenses cannot be edited.');
        }

        if (CashflowHelper::isDateInLockedPeriod($existing->expense_date->format('Y-m-d'), $accountId)) {
            throw CashflowException::periodLocked($existing->expense_date->month, $existing->expense_date->year);
        }

        $allowed = ['expense_date', 'amount', 'category_id', 'paid_from_pool_id', 'payment_method_id', 'description', 'reference_no', 'attachment_url', 'notes', 'vendor_id', 'staff_id'];
        $updateData = ['edit_reason' => $data['edit_reason']];

        // Editing a Rejected row submits the fix back to admin for
        // re-approval — status flips to Pending, NOT Approved. Pool
        // stays refunded (still uncounted) until admin approves the
        // fixed version. Clears rejection_reason since the cycle moved
        // forward; verified_by stays null until the approval lands.
        if ($existing->status === ExpenseStatus::Rejected) {
            $updateData['status'] = ExpenseStatus::Pending;
            $updateData['rejection_reason'] = null;
        }

        // Handle merged branch/general field
        if (!empty($data['is_for_general'])) {
            $updateData['is_for_general'] = true;
            $updateData['for_branch_id'] = null;
        } elseif (isset($data['for_branch_id'])) {
            $updateData['is_for_general'] = false;
            $updateData['for_branch_id'] = $data['for_branch_id'] ?: null;
        }

        // Handle vendor_id (allow clearing)
        if (array_key_exists('vendor_id', $data)) {
            $updateData['vendor_id'] = $data['vendor_id'] ?: null;
        }

        // Handle staff_id (allow clearing when switching to pool)
        if (array_key_exists('staff_id', $data)) {
            $updateData['staff_id'] = $data['staff_id'] ?: null;
        }
        // If staff is selected, clear pool; if pool is selected, clear staff
        if (!empty($data['staff_id'])) {
            $updateData['paid_from_pool_id'] = null;
        }

        foreach ($allowed as $field) {
            if ($field === 'vendor_id' || $field === 'staff_id') continue; // handled above
            if (array_key_exists($field, $data)) {
                $updateData[$field] = ($data[$field] !== '' && $data[$field] !== null) ? $data[$field] : null;
            }
        }

        // Explicit "drop the legacy URL" signal from the SPA's Remove
        // button on the legacy-link badge.
        if (! empty($data['remove_attachment_url'])) {
            $updateData['attachment_url'] = null;
        }

        return DB::transaction(function () use ($expenseId, $updateData, $auditRelations, $data, $accountId) {
            // Lock the expense row inside the transaction so concurrent
            // admin-edits to the same row serialise. The second request
            // waits on this lock and re-reads $oldAmount AFTER the first
            // commit, so the observer's amount-delta math stays correct.
            $expense = Expense::forAccount($accountId)
                ->with($auditRelations)
                ->lockForUpdate()
                ->findOrFail($expenseId);

            // Re-check inside the lock — another request could have
            // voided the row between the pre-check and now.
            if ($expense->isVoided()) {
                throw new CashflowException('Voided expenses cannot be edited.');
            }

            $oldValues = $expense->toArray();
            $oldVendorId = $expense->vendor_id;
            $oldAmount = (float) $expense->amount;

            $expense->update($updateData);

            $newVendorId = $expense->fresh()->vendor_id;
            $newAmount = (float) $expense->fresh()->amount;

            // Sync vendor transaction when vendor or amount changes
            $existingVendorTx = VendorTransaction::where('expense_id', $expense->id)->whereNull('deleted_at')->first();

            if ($oldVendorId && !$newVendorId) {
                // Vendor removed: reverse old vendor balance and delete transaction
                if ($existingVendorTx) {
                    DB::table('cashflow_vendors')->where('id', $oldVendorId)
                        ->increment('cached_balance', $existingVendorTx->amount);
                    $existingVendorTx->delete();
                }
            } elseif (!$oldVendorId && $newVendorId) {
                // Vendor added: create new vendor transaction
                $freshExpense = $expense->fresh();
                VendorTransaction::create([
                    'account_id' => $accountId,
                    'vendor_id' => $newVendorId,
                    'type' => VendorTransactionType::Payment,
                    'amount' => $newAmount,
                    'expense_id' => $expense->id,
                    'description' => 'Payment via expense #' . $expense->id,
                    'reference_no' => $freshExpense->reference_no,
                    'transaction_date' => $freshExpense->expense_date->format('Y-m-d'),
                    'for_branch_id' => $freshExpense->for_branch_id,
                    'is_for_general' => $freshExpense->is_for_general ? 1 : 0,
                    'created_by' => \Illuminate\Support\Facades\Auth::id(),
                ]);
                // Observer handles balance decrement for new vendor
            } elseif ($oldVendorId && $newVendorId) {
                if ($oldVendorId != $newVendorId) {
                    // Vendor changed: reverse old, apply to new
                    if ($existingVendorTx) {
                        // Reverse old vendor balance
                        DB::table('cashflow_vendors')->where('id', $oldVendorId)
                            ->increment('cached_balance', $existingVendorTx->amount);

                        // Update transaction to new vendor with new amount
                        $existingVendorTx->update([
                            'vendor_id' => $newVendorId,
                            'amount' => $newAmount,
                            'description' => 'Payment via expense #' . $expense->id,
                            'reference_no' => $expense->fresh()->reference_no,
                        ]);

                        // Decrement new vendor balance
                        DB::table('cashflow_vendors')->where('id', $newVendorId)
                            ->decrement('cached_balance', $newAmount);
                    }
                } elseif ($oldAmount != $newAmount) {
                    // Same vendor, amount changed: adjust balance delta
                    if ($existingVendorTx) {
                        $amountDiff = $newAmount - (float) $existingVendorTx->amount;
                        $existingVendorTx->update(['amount' => $newAmount]);

                        if ($amountDiff > 0) {
                            DB::table('cashflow_vendors')->where('id', $newVendorId)
                                ->decrement('cached_balance', $amountDiff);
                        } elseif ($amountDiff < 0) {
                            DB::table('cashflow_vendors')->where('id', $newVendorId)
                                ->increment('cached_balance', abs($amountDiff));
                        }
                    }
                }
            }

            $newValues = $expense->fresh()->load($auditRelations)->toArray();

            $this->auditService->log(
                CashflowAuditLog::ACTION_UPDATED,
                CashflowAuditLog::ENTITY_EXPENSE,
                $expense->id,
                $oldValues,
                $newValues,
                $data['edit_reason']
            );

            // Bind freshly-uploaded attachments before re-flagging so
            // the No-receipt rule sees the new files.
            $this->bindAttachments($expense->fresh(), $data['attachment_ids'] ?? []);

            // Re-run auto-flag checks after admin edits — clears flags whose
            // cause has been fixed, sets fresh flags if the edit introduced
            // a new problem (e.g. switched payment method to cash without
            // attaching a receipt). `checkAndFlag` is idempotent.
            $reloaded = $expense->fresh()->load($auditRelations);
            $this->checkAndFlag($reloaded, $accountId);

            return $expense->fresh();
        });
    }

    /**
     * Void an expense (requires reason, min 10 chars).
     */
    public function void(int $expenseId, string $reason, int $accountId): Expense
    {
        // Pre-check (cheap, no lock).
        $preCheck = Expense::forAccount($accountId)->findOrFail($expenseId);

        if (CashflowHelper::isDateInLockedPeriod($preCheck->expense_date->format('Y-m-d'), $accountId)) {
            throw CashflowException::periodLocked($preCheck->expense_date->month, $preCheck->expense_date->year);
        }

        return DB::transaction(function () use ($expenseId, $reason, $accountId) {
            // Lock the row inside the transaction so two concurrent voids
            // can't both refund the pool. The second request waits on
            // this lock, then re-reads voided_at AFTER the first commit
            // and short-circuits on the isVoided() check.
            $expense = Expense::forAccount($accountId)
                ->lockForUpdate()
                ->findOrFail($expenseId);

            if ($expense->isVoided()) {
                throw new CashflowException('Expense is already voided.');
            }

            // Separation of duties on void — same rationale as approve.
            // void() refunds the pool, so an attacker who could both
            // create and self-void would have a primitive for churning
            // pool balance through "shell" expenses without an audit
            // co-signer. Block it.
            if ((int) $expense->created_by === (int) Auth::id()) {
                throw new CashflowException(
                    'You cannot void an expense you created. Another admin must void it.',
                );
            }

            $oldValues = $expense->only(['voided_at', 'voided_by', 'void_reason', 'is_flagged', 'flag_reason']);
            // Reverse pool balance: increment pool (give money back) — UNLESS
            // the row is already Rejected. Under Model A, reject() already
            // reversed the deduction, so refunding again on void would
            // double-credit the pool. Approved/Pending rows still carry the
            // debit and ARE refunded here.
            if ($expense->paid_from_pool_id && $expense->status !== ExpenseStatus::Rejected) {
                DB::table('cash_pools')
                    ->where('id', $expense->paid_from_pool_id)
                    ->increment('cached_balance', $expense->amount);
            }

            // Reverse vendor transaction if exists
            $vendorTx = $expense->vendorTransaction;
            if ($vendorTx) {
                DB::table('cashflow_vendors')
                    ->where('id', $expense->vendor_id)
                    ->increment('cached_balance', $vendorTx->amount);
                $vendorTx->delete();
            }

            // Void is the terminal "this didn't happen" state — clear the
            // flag alongside it. Matches approve / reject behaviour.
            $expense->update([
                'voided_at' => now(),
                'voided_by' => Auth::id(),
                'void_reason' => $reason,
                'is_flagged' => false,
                'flag_reason' => null,
            ]);

            $this->auditService->log(
                CashflowAuditLog::ACTION_VOIDED,
                CashflowAuditLog::ENTITY_EXPENSE,
                $expense->id,
                $oldValues,
                ['voided_at' => now()->toDateTimeString(), 'void_reason' => $reason],
                $reason
            );

            return $expense->fresh();
        });
    }

    /**
     * Approve a Pending or Rejected expense.
     *
     * Two entry paths converge here:
     *   • Pending → Approved (accountant submitted a fix; admin
     *     signed off on the revision).
     *   • Rejected → Approved (admin is overriding the reject — e.g.
     *     they rejected by mistake, or they decided no accountant
     *     intervention is needed).
     *
     * Pool balance is NOT touched (Model B / substance-over-form):
     * the cash event happened at creation time; status reflects
     * record-validity, not whether the spend occurred. Use `void`
     * when the entry shouldn't exist and the cash should be
     * reversed. `rejection_reason` is cleared if the row was rejected.
     */
    public function approve(int $expenseId, int $accountId): Expense
    {
        $expense = Expense::forAccount($accountId)->findOrFail($expenseId);

        if ($expense->isVoided()) {
            throw new CashflowException('Voided expenses cannot be approved.');
        }
        if ($expense->status === ExpenseStatus::Approved) {
            throw new CashflowException('Expense is already approved.');
        }
        if (! in_array($expense->status, [ExpenseStatus::Pending, ExpenseStatus::Rejected], true)) {
            throw new CashflowException('Only pending or rejected expenses can be approved.');
        }

        // Separation of duties: an approver MUST be a different user
        // than the creator on the post-reject path. Otherwise an admin
        // who created and self-approved an expense, then had it
        // rejected by a second admin, could just edit + re-approve
        // their own row and undo the rejection without any second
        // review. The audit log would show "approved by A" both times,
        // hiding the bypass.
        if ((int) $expense->created_by === (int) Auth::id()) {
            throw new CashflowException(
                'You cannot approve an expense you created. Another admin must approve it.',
            );
        }

        $oldValues = $expense->only(['status', 'verified_by', 'rejection_reason']);

        return DB::transaction(function () use ($expense, $oldValues) {
            $expense->update([
                'status' => ExpenseStatus::Approved,
                'verified_by' => Auth::id(),
                'rejection_reason' => null, // clear stale reason if coming from Rejected
            ]);

            $this->auditService->log(
                CashflowAuditLog::ACTION_APPROVED,
                CashflowAuditLog::ENTITY_EXPENSE,
                $expense->id,
                $oldValues,
                [
                    'status' => ExpenseStatus::Approved->value,
                    'verified_by' => Auth::id(),
                ],
                null,
            );

            return $expense->fresh();
        });
    }

    /**
     * Reject an Approved-or-Pending expense and send it back to its
     * creator for fixes (2026-05-14, Model B). This is a record-
     * correction workflow — pool balance is intentionally NOT
     * touched because the cash event already happened. Use `void`
     * when the entry shouldn't exist on the books at all.
     *
     * The row's creator then gets to edit it without needing the
     * global `cashflow_expense_edit` slug (see `UpdateExpenseRequest`
     * + `adminEdit`). On successful save the row moves to Pending
     * (awaiting admin approval), and `approve()` closes the loop by
     * flipping back to Approved.
     */
    public function reject(int $expenseId, string $reason, int $accountId): Expense
    {
        $expense = Expense::forAccount($accountId)->findOrFail($expenseId);

        if ($expense->isVoided()) {
            throw new CashflowException('Voided expenses cannot be rejected.');
        }
        if ($expense->status === ExpenseStatus::Rejected) {
            throw new CashflowException('Expense is already rejected.');
        }
        if (! in_array($expense->status, [ExpenseStatus::Approved, ExpenseStatus::Pending], true)) {
            throw new CashflowException('Only approved or pending revisions can be rejected.');
        }

        // Separation of duties — same rule that approve and void enforce.
        // Without this, a single admin could create → reject → edit →
        // re-approve a row entirely on their own, with the audit trail
        // showing two normal-looking state changes by the same user.
        // Reject reverses the pool deduction (Model A, applied by
        // ExpenseObserver::updated when status flips to Rejected) and kicks
        // the row back to the creator for re-edit; if that creator is the
        // SAME person who rejected, the SoD bypass on approve (which we DO
        // block) becomes reachable via this loop.
        if ((int) $expense->created_by === (int) Auth::id()) {
            throw new CashflowException(
                'You cannot reject an expense you created. Another admin must review it.',
            );
        }

        $oldValues = $expense->only(['status', 'rejection_reason', 'verified_by']);

        return DB::transaction(function () use ($expense, $reason, $oldValues) {
            $expense->update([
                'status' => ExpenseStatus::Rejected,
                'rejection_reason' => $reason,
                // Clear the approver — re-approval happens on the
                // creator's edit-save, with a new verified_by stamp.
                'verified_by' => null,
            ]);

            $this->auditService->log(
                CashflowAuditLog::ACTION_REJECTED,
                CashflowAuditLog::ENTITY_EXPENSE,
                $expense->id,
                $oldValues,
                [
                    'status' => ExpenseStatus::Rejected->value,
                    'rejection_reason' => $reason,
                ],
                $reason,
            );

            return $expense->fresh();
        });
    }

    /**
     * Re-run auto-flag rules against an existing row. Public entry point
     * used by the `cashflow:rebuild-flags` backfill command — wraps the
     * private `checkAndFlag()` so callers don't need to know the row's
     * account_id (we pull it off the model itself). Returns the array of
     * triggered reasons (empty if the row is clean).
     */
    public function rebuildFlagsForRow(Expense $expense): array
    {
        return $this->checkAndFlag($expense, $expense->account_id);
    }

    /**
     * Maximum attachments per payment (matches the SPA drop-zone cap).
     * Enforced server-side because the SPA can be bypassed via curl.
     */
    private const MAX_ATTACHMENTS_PER_EXPENSE = 10;

    /**
     * Bind a list of pre-uploaded attachment rows to an expense. Called
     * from create / adminEdit / resubmit after the parent row exists.
     *
     * Contract: `attachment_ids` carries the FRESH IDs to attach this
     * request (typically files just uploaded via the attachments endpoint
     * while the form was open). Already-attached files keep their
     * binding — removal is a separate DELETE endpoint call.
     *
     * Safety:
     *  - Only rows in the same account are touched.
     *  - Only rows that are still orphans (expense_id IS NULL) are
     *    bound — this prevents a malicious caller from stealing an
     *    attachment off another expense by guessing its id.
     *  - Total count after binding cannot exceed MAX_ATTACHMENTS_PER_EXPENSE.
     */
    private function bindAttachments(Expense $expense, array $attachmentIds): void
    {
        $attachmentIds = array_values(array_filter(
            array_map('intval', $attachmentIds),
            static fn (int $id): bool => $id > 0,
        ));
        if ($attachmentIds === []) {
            return;
        }

        $existing = $expense->attachments()->count();
        $projected = $existing + count($attachmentIds);
        if ($projected > self::MAX_ATTACHMENTS_PER_EXPENSE) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'attachment_ids' => [sprintf(
                    'A payment can have at most %d attachments (this would make it %d).',
                    self::MAX_ATTACHMENTS_PER_EXPENSE,
                    $projected,
                )],
            ]);
        }

        ExpenseAttachment::forAccount((int) $expense->account_id)
            ->whereIn('id', $attachmentIds)
            ->whereNull('expense_id')
            ->update([
                'expense_id' => $expense->id,
                'updated_at' => now(),
            ]);
    }

    /**
     * Tolerance (in major currency units, e.g. PKR) below which a negative
     * vendor or pool balance is treated as a rounding artefact rather than
     * a real overpayment / overdraft. Anything within ±1 of zero is clean.
     */
    private const BALANCE_EPSILON = 1.0;

    /**
     * Window (days) for the vendor + amount duplicate rule. Widened from
     * 24h → 7d on 2026-05-13: a week catches split-fraud reliably while
     * staying short enough that legitimate monthly recurring spend
     * (rent, retainers) doesn't false-positive. Pivots on
     * `$expense->created_at`, not `now()`, so the rule stays re-run-safe
     * on edits / resubmits of older rows.
     */
    private const DUPLICATE_VENDOR_AMOUNT_WINDOW_DAYS = 7;

    /**
     * Window (days) for the description-only duplicate rule. Tighter than
     * the vendor+amount window (3d, widened 2026-05-13 from 24h) because
     * descriptions repeat more naturally — labels like "Rent", "Salary",
     * "Utility bill" appear on legit non-fraud rows month after month
     * and would generate too much noise at a wider window.
     */
    private const DUPLICATE_DESCRIPTION_WINDOW_DAYS = 3;

    /**
     * Window (days) for the voided-then-recreated rule. Widened from
     * 7d → 30d on 2026-05-13 so a full month of re-creation attempts
     * after a void still surface. Hunts for "void it and quietly re-create"
     * patterns.
     */
    private const VOIDED_RECREATED_WINDOW_DAYS = 30;

    /**
     * Check and apply auto-flags to an expense. Backdated / self-approval /
     * daily-splitting / perfect-match-advance rules were retired 2026-05-13
     * (too noisy for the signal they carried). Remaining rules target hard
     * data-integrity issues only.
     *
     * Always writes the flag state — sets `is_flagged` + `flag_reason` when
     * any rule fires, and CLEARS them when none do. Callers can therefore
     * re-run this after any mutation (edit / resubmit / void-vendor-fix)
     * and trust the row to reflect current reality.
     *
     * Returns the array of triggered reasons (empty if clean).
     */
    private function checkAndFlag(Expense $expense, int $accountId): array
    {
        $flags = [];

        // Above threshold — a payment above the account's approval ceiling
        // (default Rs 10,000, configurable per account). The label is
        // intentionally subtle: an amount that lands one rupee over the
        // threshold isn't "large", it's just over the line that the org
        // wants admins to eyeball. Scoped to CASH-style payment methods
        // only: bank / wire / card transfers leave their own auditable
        // trail via statements, so flagging them adds noise without
        // signal. A method whose name contains "bank" is exempt (mirrors
        // the No-receipt rule's bank-exemption logic).
        $paymentMethodForLarge = $expense->paymentMethod;
        $isBankMode = $paymentMethodForLarge
            && str_contains(strtolower($paymentMethodForLarge->name), 'bank');
        if (! $isBankMode) {
            $approvalThreshold = (float) $this->settingService->get(
                'approval_threshold',
                $accountId,
                10000,
            );
            if ((float) $expense->amount > $approvalThreshold) {
                $flags[] = 'Above threshold';
            }
        }

        // No invoice. An invoice (legacy URL or uploaded attachment) is
        // mandatory for every payment EXCEPT bank transfers — bank
        // statements already provide an auditable trail. Any payment
        // mode whose name contains "bank" (Bank, HBL Bank, Bank Transfer,
        // etc.) is exempt; everything else (cash, card, cheque, mobile
        // wallet…) must carry one.
        $paymentMethod = $expense->paymentMethod;
        $hasAnyReceipt = ! empty($expense->attachment_url)
            || $expense->attachments()->exists();
        if ($paymentMethod
            && ! str_contains(strtolower($paymentMethod->name), 'bank')
            && ! $hasAnyReceipt) {
            $flags[] = 'No invoice';
        }

        // Reused attachment — same receipt on another live payment. Two
        // signals trigger this:
        //  (a) URL equality on the legacy `attachment_url` column.
        //  (b) SHA-256 equality across `expense_attachments` rows — any
        //      uploaded file on this expense shares its content with an
        //      attachment on another live (non-voided) expense.
        // Time-unbounded by design — a year-old receipt re-used today is
        // just as suspicious as one re-used the same day.
        //
        // We also collect the matching expense IDs so the flag reason can
        // point investigators straight at the conflicting payment(s) —
        // the SPA renders the suffix inline in the reason badge.
        $matchingExpenseIds = [];

        if (! empty($expense->attachment_url)) {
            $matchingByUrl = Expense::forAccount($accountId)
                ->where('id', '!=', $expense->id)
                ->where('attachment_url', $expense->attachment_url)
                ->whereNull('voided_at')
                ->pluck('id')
                ->all();
            $matchingExpenseIds = array_merge($matchingExpenseIds, $matchingByUrl);
        }

        $shaList = ExpenseAttachment::forAccount($accountId)
            ->forExpense((int) $expense->id)
            ->pluck('sha256')
            ->all();
        if ($shaList !== []) {
            $matchingByHash = ExpenseAttachment::forAccount($accountId)
                ->whereIn('sha256', $shaList)
                ->whereNotNull('expense_id')
                ->where('expense_id', '!=', $expense->id)
                ->whereHas('expense', fn ($q) => $q->whereNull('voided_at'))
                ->pluck('expense_id')
                ->all();
            $matchingExpenseIds = array_merge($matchingExpenseIds, $matchingByHash);
        }

        $matchingExpenseIds = array_values(array_unique(array_filter(
            $matchingExpenseIds,
            static fn ($v) => $v !== null,
        )));
        sort($matchingExpenseIds);

        if ($matchingExpenseIds !== []) {
            // Cap the inline list so a runaway match doesn't blow out the
            // badge. Three IDs covers the realistic investigation case;
            // anything beyond gets the "+N more" suffix.
            $shown = array_slice($matchingExpenseIds, 0, 3);
            $suffix = '#'.implode(', #', $shown);
            $extra = count($matchingExpenseIds) - count($shown);
            if ($extra > 0) {
                $suffix .= ", +{$extra} more";
            }
            $flags[] = "Reused receipt (matches {$suffix})";
        }

        // Duplicate vendor payment: same vendor + same amount within ±7d
        // of this row's creation. Window widened from 24h on 2026-05-13
        // (see DUPLICATE_VENDOR_AMOUNT_WINDOW_DAYS).
        if ($expense->vendor_id) {
            $vaStart = $expense->created_at->copy()->subDays(self::DUPLICATE_VENDOR_AMOUNT_WINDOW_DAYS);
            $vaEnd   = $expense->created_at->copy()->addDays(self::DUPLICATE_VENDOR_AMOUNT_WINDOW_DAYS);
            $duplicateExists = Expense::forAccount($accountId)
                ->where('vendor_id', $expense->vendor_id)
                ->where('amount', $expense->amount)
                ->where('id', '!=', $expense->id)
                ->whereBetween('created_at', [$vaStart, $vaEnd])
                ->whereNull('voided_at')
                ->exists();

            if ($duplicateExists) {
                $flags[] = 'Duplicate vendor + amount';
            }
        }

        // Description-only duplicate — catches copy-paste creation patterns
        // even when the amount or vendor differs. Compared case-insensitively
        // and trimmed. Window tightened to 3d (vs the 7d vendor+amount one)
        // because labels like "Rent" / "Salary" / "Utility bill" repeat
        // naturally on legit recurring rows and would over-flag at wider
        // windows. See DUPLICATE_DESCRIPTION_WINDOW_DAYS.
        $descriptionKey = trim((string) $expense->description);
        if ($descriptionKey !== '') {
            $descStart = $expense->created_at->copy()->subDays(self::DUPLICATE_DESCRIPTION_WINDOW_DAYS);
            $descEnd   = $expense->created_at->copy()->addDays(self::DUPLICATE_DESCRIPTION_WINDOW_DAYS);
            $descKeyLower = strtolower($descriptionKey);
            $descDuplicate = Expense::forAccount($accountId)
                ->where('id', '!=', $expense->id)
                ->whereBetween('created_at', [$descStart, $descEnd])
                ->whereNull('voided_at')
                ->whereRaw('LOWER(TRIM(description)) = ?', [$descKeyLower])
                ->exists();
            if ($descDuplicate) {
                $flags[] = 'Duplicate description';
            }
        }

        // Voided-then-recreated: another row matching this one by vendor or
        // description was voided within the last 30 days before this row's
        // creation (widened from 7d on 2026-05-13 so a full month of
        // re-creation attempts after a void still surface). Hunts for
        // "void it and quietly re-create" patterns.
        $hasVendorMatch = (bool) $expense->vendor_id;
        $hasDescriptionMatch = $descriptionKey !== '';
        if ($hasVendorMatch || $hasDescriptionMatch) {
            $voidedWindowStart = $expense->created_at->copy()->subDays(self::VOIDED_RECREATED_WINDOW_DAYS);
            $voidedTwin = Expense::forAccount($accountId)
                ->where('id', '!=', $expense->id)
                ->whereNotNull('voided_at')
                ->whereBetween('voided_at', [$voidedWindowStart, $expense->created_at])
                ->where(function ($q) use ($expense, $hasVendorMatch, $hasDescriptionMatch, $descriptionKey) {
                    if ($hasVendorMatch) {
                        $q->where('vendor_id', $expense->vendor_id);
                    }
                    if ($hasDescriptionMatch) {
                        $callback = $hasVendorMatch ? 'orWhereRaw' : 'whereRaw';
                        $q->$callback('LOWER(TRIM(description)) = ?', [strtolower($descriptionKey)]);
                    }
                })
                ->exists();
            if ($voidedTwin) {
                $flags[] = 'Voided twin';
            }
        }

        // Vendor overpayment: payment exceeds outstanding balance (Sec 11.2)
        if ($expense->vendor_id) {
            $vendor = \App\Models\CashFlow\Vendor::find($expense->vendor_id);
            if ($vendor && (float) $vendor->cached_balance < -self::BALANCE_EPSILON) {
                $flags[] = 'Vendor overpaid';
            }
        }

        // Vendor Pending: high-vendor category but no vendor selected (Sec 5.4/11.2)
        if (!$expense->vendor_id && $expense->category) {
            $cat = $expense->category;
            if ($cat->vendor_emphasis) {
                $flags[] = 'Vendor missing';
            }
        }

        // Negative pool balance (Sec 11.2)
        $pool = $expense->paid_from_pool_id ? CashPool::find($expense->paid_from_pool_id) : null;
        if ($pool && (float) $pool->cached_balance < -self::BALANCE_EPSILON) {
            $flags[] = 'Pool negative';
        }

        // Always sync the row — sets when flags fire, clears when stale flags
        // would otherwise linger after the cause was fixed.
        $expense->update([
            'is_flagged' => !empty($flags) ? 1 : 0,
            'flag_reason' => !empty($flags) ? implode('; ', $flags) : null,
        ]);

        return $flags;
    }

    /**
     * Get expense counts by status for dashboard widgets.
     */
    public function getStatusCounts(int $accountId): array
    {
        return [
            'pending' => Expense::forAccount($accountId)->pending()->notVoided()->count(),
            'approved' => Expense::forAccount($accountId)->approved()->notVoided()->count(),
            'rejected' => Expense::forAccount($accountId)->rejected()->notVoided()->count(),
            'flagged' => Expense::forAccount($accountId)->flagged()->notVoided()->count(),
        ];
    }
}
