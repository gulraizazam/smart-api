<?php

declare(strict_types=1);
namespace App\Services\CashFlow;

use App\Exceptions\CashflowException;
use App\Helpers\CashflowHelper;
use App\Models\CashFlow\CashflowAuditLog;
use App\Models\CashFlow\StaffTransfer;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Phase B of Cash Movements — peer handovers between two staff members.
 *
 * Mechanics:
 *   - One ledger row in `staff_transfers`. Pool balances are untouched.
 *   - Source staff's outstanding decreases by amount; destination's
 *     increases by the same amount. Total outstanding across the tenant
 *     is conserved.
 *   - Period-lock guard runs against today (no `transfer_date` column —
 *     handovers are physical, can't be backdated).
 *   - No eligibility check (the cash already exists outside the pool
 *     system once it's in a staff member's hand). No cumulative threshold
 *     either — total exposure doesn't grow.
 *   - Outstanding cap: the source's window-independent outstanding must
 *     cover the amount, otherwise we'd silently mint cash on the dest side.
 */
class StaffTransferService
{
    public function __construct(
        private readonly CashflowAuditService $auditService,
        private readonly StaffAdvanceService $staffAdvanceService,
    ) {}

    public function create(array $data, int $accountId): StaffTransfer
    {
        $fromUserId = (int) ($data['from_user_id'] ?? 0);
        $toUserId = (int) ($data['to_user_id'] ?? 0);
        $amount = (float) ($data['amount'] ?? 0);

        if ($fromUserId <= 0 || $toUserId <= 0) {
            throw new CashflowException('Both source and destination staff are required.');
        }
        if ($fromUserId === $toUserId) {
            throw new CashflowException('Source and destination staff must be different.');
        }

        // Tenant guard — neither user is allowed to belong to another account.
        $fromUser = User::where('account_id', $accountId)->findOrFail($fromUserId);
        $toUser = User::where('account_id', $accountId)->findOrFail($toUserId);

        $today = now()->toDateString();
        if (CashflowHelper::isDateInLockedPeriod($today, $accountId)) {
            throw CashflowException::periodLocked(
                (int) now()->month,
                (int) now()->year,
            );
        }

        // Source must have enough outstanding to give away. Without this
        // a handover would silently mint cash on the destination side.
        $outstanding = $this->staffAdvanceService->getOutstanding($fromUserId, $accountId);
        if ($amount > $outstanding) {
            throw new CashflowException(
                'Handover amount (PKR ' . number_format($amount, 2) .
                ') exceeds source outstanding balance (PKR ' . number_format($outstanding, 2) . ').'
            );
        }

        return DB::transaction(function () use ($accountId, $fromUserId, $toUserId, $amount, $data) {
            $transfer = StaffTransfer::create([
                'account_id' => $accountId,
                'from_user_id' => $fromUserId,
                'to_user_id' => $toUserId,
                'amount' => $amount,
                'description' => $data['description'] ?? null,
                'created_by' => Auth::id(),
            ]);

            $this->auditService->log(
                CashflowAuditLog::ACTION_CREATED,
                CashflowAuditLog::ENTITY_STAFF_TRANSFER,
                $transfer->id,
                null,
                $transfer->toArray()
            );

            return $transfer->load(['fromUser:id,name', 'toUser:id,name', 'creator:id,name']);
        });
    }

    public function void(int $transferId, string $reason, int $accountId): StaffTransfer
    {
        $transfer = StaffTransfer::forAccount($accountId)->findOrFail($transferId);

        if ($transfer->isVoided()) {
            throw new CashflowException('This handover is already voided.');
        }

        // Period-lock guard — voiding rewinds both ledger sides, which counts
        // as a write against the original creation month.
        $createdAt = $transfer->created_at->format('Y-m-d');
        if (CashflowHelper::isDateInLockedPeriod($createdAt, $accountId)) {
            throw CashflowException::periodLocked(
                (int) $transfer->created_at->month,
                (int) $transfer->created_at->year,
            );
        }

        return DB::transaction(function () use ($transfer, $reason) {
            $oldValues = $transfer->only(['voided_at', 'voided_by', 'void_reason']);

            $transfer->update([
                'voided_at' => now(),
                'void_reason' => $reason,
                'voided_by' => Auth::id(),
            ]);

            $this->auditService->log(
                CashflowAuditLog::ACTION_VOIDED,
                CashflowAuditLog::ENTITY_STAFF_TRANSFER,
                $transfer->id,
                $oldValues,
                ['voided_at' => now()->toDateTimeString(), 'void_reason' => $reason],
                $reason
            );

            return $transfer->fresh();
        });
    }
}
