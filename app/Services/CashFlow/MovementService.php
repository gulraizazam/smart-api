<?php

declare(strict_types=1);

namespace App\Services\CashFlow;

use App\Exceptions\CashflowException;
use App\Models\CashFlow\CashTransfer;
use App\Models\CashFlow\CashflowAuditLog;
use App\Models\CashFlow\StaffAdvance;
use App\Models\CashFlow\StaffReturn;
use App\Models\CashFlow\StaffTransfer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Unified surface over Transfers + StaffAdvances + StaffReturns.
 *
 * No new storage: each method UNIONs across the three existing tables and
 * dispatches creates/voids to the per-kind services that already enforce
 * period-locks, eligibility, threshold, attachment, and audit-log writes.
 * Phase A excludes staff↔staff handovers (rejected with 422 at create time
 * and absent from the dispatch matrix); Phase B will plug in a fourth
 * `staff_transfers` table without changing the public API of this class.
 */
class MovementService
{
    public const KIND_TRANSFER = 'transfer';
    public const KIND_STAFF_ADVANCE = 'staff_advance';
    public const KIND_STAFF_RETURN = 'staff_return';
    public const KIND_STAFF_TRANSFER = 'staff_transfer';

    public const SOURCE_POOL = 'pool';
    public const SOURCE_STAFF = 'staff';

    public function __construct(
        private readonly TransferService $transferService,
        private readonly StaffAdvanceService $staffAdvanceService,
        private readonly StaffTransferService $staffTransferService,
        private readonly CashflowAuditService $auditService,
    ) {}

    /**
     * Paginated list of movements across all three tables.
     *
     * Filters:
     *   - date_from / date_to:    ISO YYYY-MM-DD; filters transfer_date for
     *                             transfers, DATE(created_at) for staff legs.
     *   - source_type / source_id, dest_type / dest_id: scope to a wallet.
     *   - kind:                   transfer | staff_advance | staff_return.
     *   - search:                 description / reference_no LIKE.
     *   - voided:                 'active' | 'voided' (omitted = both).
     *
     * Implementation: each kind is queried independently with relations
     * eager-loaded, then normalised to a Movement DTO array and merged into
     * a single collection sorted by date desc. The volumes in scope here
     * (per-account cash-movement history) are moderate; a raw SQL UNION
     * is reserved for Phase B if profiling shows it's needed.
     */
    public function list(int $accountId, array $filters = [], int $perPage = 25, int $page = 1): LengthAwarePaginator
    {
        $kind = $filters['kind'] ?? null;

        $rows = collect();
        if ($kind === null || $kind === self::KIND_TRANSFER) {
            $rows = $rows->merge($this->queryTransfers($accountId, $filters));
        }
        if ($kind === null || $kind === self::KIND_STAFF_ADVANCE) {
            $rows = $rows->merge($this->queryStaffAdvances($accountId, $filters));
        }
        if ($kind === null || $kind === self::KIND_STAFF_RETURN) {
            $rows = $rows->merge($this->queryStaffReturns($accountId, $filters));
        }
        if ($kind === null || $kind === self::KIND_STAFF_TRANSFER) {
            $rows = $rows->merge($this->queryStaffTransfers($accountId, $filters));
        }

        // Source/dest filters apply after normalisation — the column names
        // differ per table, so filtering uniformly here is simpler than
        // duplicating the conditional in three places.
        $rows = $this->applyWalletFilters($rows, $filters);

        $sorted = $rows
            ->sortByDesc(fn (array $r) => $r['date'] . ' ' . str_pad((string) $r['id'], 10, '0', STR_PAD_LEFT) . '-' . $r['kind'])
            ->values();

        $total = $sorted->count();
        $items = $sorted->forPage($page, $perPage)->values();

        return new LengthAwarePaginator($items, $total, $perPage, $page, [
            'path' => request()->url(),
            'pageName' => 'page',
        ]);
    }

    /**
     * Dispatch a create to the right per-kind service.
     *
     * Required keys in $data: source_type, source_id, dest_type, dest_id, amount.
     * Per-combination keys:
     *   pool→pool:  transfer_date, method, attachment_url, reference_no?, description?
     *   pool→staff: description?
     *   staff→pool: description?
     *   staff→staff: REJECTED (Phase A).
     *
     * Returns a Movement DTO array shaped like `list()` rows.
     */
    public function create(int $accountId, array $data): array
    {
        $sourceType = $data['source_type'] ?? null;
        $destType = $data['dest_type'] ?? null;

        if ($sourceType === self::SOURCE_POOL && $destType === self::SOURCE_POOL) {
            $transfer = $this->transferService->create([
                'transfer_date' => $data['transfer_date'],
                'amount' => $data['amount'],
                'from_pool_id' => (int) $data['source_id'],
                'to_pool_id' => (int) $data['dest_id'],
                'method' => $data['method'],
                'reference_no' => $data['reference_no'] ?? null,
                'attachment_url' => $data['attachment_url'],
                'description' => $data['description'] ?? null,
            ], $accountId);

            return $this->mapTransfer($transfer);
        }

        if ($sourceType === self::SOURCE_POOL && $destType === self::SOURCE_STAFF) {
            $advance = $this->staffAdvanceService->createAdvance([
                'user_id' => (int) $data['dest_id'],
                'pool_id' => (int) $data['source_id'],
                'amount' => $data['amount'],
                'description' => $data['description'] ?? null,
            ], $accountId);

            return $this->mapStaffAdvance($advance);
        }

        if ($sourceType === self::SOURCE_STAFF && $destType === self::SOURCE_POOL) {
            $return = $this->staffAdvanceService->createReturn([
                'user_id' => (int) $data['source_id'],
                'pool_id' => (int) $data['dest_id'],
                'amount' => $data['amount'],
                'description' => $data['description'] ?? null,
            ], $accountId);

            return $this->mapStaffReturn($return);
        }

        if ($sourceType === self::SOURCE_STAFF && $destType === self::SOURCE_STAFF) {
            $transfer = $this->staffTransferService->create([
                'from_user_id' => (int) $data['source_id'],
                'to_user_id' => (int) $data['dest_id'],
                'amount' => $data['amount'],
                'description' => $data['description'] ?? null,
            ], $accountId);

            return $this->mapStaffTransfer($transfer);
        }

        throw new CashflowException('Unsupported source/destination combination.');
    }

    /**
     * Void a movement by kind + id. Dispatches to the underlying voider
     * (each one re-runs its period-lock guard on the original date).
     */
    public function void(int $accountId, string $kind, int $id, string $reason): array
    {
        return match ($kind) {
            self::KIND_TRANSFER => $this->mapTransfer($this->transferService->void($id, $reason, $accountId)),
            self::KIND_STAFF_ADVANCE => $this->mapStaffAdvance($this->staffAdvanceService->voidAdvance($id, $reason, $accountId)),
            self::KIND_STAFF_RETURN => $this->mapStaffReturn($this->staffAdvanceService->voidReturn($id, $reason, $accountId)),
            self::KIND_STAFF_TRANSFER => $this->mapStaffTransfer($this->staffTransferService->void($id, $reason, $accountId)),
            default => throw new CashflowException('Unknown movement kind: ' . $kind),
        };
    }

    /**
     * Resolve the audit-log entity_type string from a movement kind so the
     * SPA can call the existing per-entity audit endpoints without knowing
     * the legacy naming.
     */
    public function auditEntityFor(string $kind): string
    {
        return match ($kind) {
            self::KIND_TRANSFER => CashflowAuditLog::ENTITY_TRANSFER,
            self::KIND_STAFF_ADVANCE => CashflowAuditLog::ENTITY_STAFF_ADVANCE,
            self::KIND_STAFF_RETURN => CashflowAuditLog::ENTITY_STAFF_RETURN,
            self::KIND_STAFF_TRANSFER => CashflowAuditLog::ENTITY_STAFF_TRANSFER,
            default => throw new CashflowException('Unknown movement kind: ' . $kind),
        };
    }

    /**
     * Chronological ledger of inbound + outbound movements touching one pool.
     *
     * Returns a single chronological feed; the caller paginates if needed.
     * Used by the pool-ledger drill-down on the Movements page and the
     * (future) per-pool ledger drawer.
     */
    public function getPoolLedger(int $accountId, int $poolId, array $filters = []): Collection
    {
        $rows = collect();

        // Transfers touching this pool — direction depends on which side.
        $transfers = CashTransfer::forAccount($accountId)
            ->where(function ($q) use ($poolId) {
                $q->where('from_pool_id', $poolId)->orWhere('to_pool_id', $poolId);
            })
            ->with(['fromPool:id,name', 'toPool:id,name', 'creator:id,name'])
            ->when(!empty($filters['date_from']) && !empty($filters['date_to']), fn ($q) =>
                $q->inDateRange($filters['date_from'], $filters['date_to']))
            ->orderBy('transfer_date', 'desc')
            ->get();
        foreach ($transfers as $t) {
            $rows->push($this->mapTransfer($t));
        }

        // Outbound advances from this pool.
        $advances = StaffAdvance::forAccount($accountId)
            ->where('pool_id', $poolId)
            ->with(['staffUser:id,name', 'pool:id,name', 'creator:id,name'])
            ->when(!empty($filters['date_from']) && !empty($filters['date_to']), fn ($q) =>
                $q->whereBetween('created_at', [$filters['date_from'] . ' 00:00:00', $filters['date_to'] . ' 23:59:59']))
            ->orderBy('created_at', 'desc')
            ->get();
        foreach ($advances as $a) {
            $rows->push($this->mapStaffAdvance($a));
        }

        // Inbound returns into this pool.
        $returns = StaffReturn::forAccount($accountId)
            ->where('pool_id', $poolId)
            ->with(['staffUser:id,name', 'pool:id,name', 'creator:id,name'])
            ->when(!empty($filters['date_from']) && !empty($filters['date_to']), fn ($q) =>
                $q->whereBetween('created_at', [$filters['date_from'] . ' 00:00:00', $filters['date_to'] . ' 23:59:59']))
            ->orderBy('created_at', 'desc')
            ->get();
        foreach ($returns as $r) {
            $rows->push($this->mapStaffReturn($r));
        }

        return $rows
            ->sortByDesc(fn (array $r) => $r['date'] . ' ' . str_pad((string) $r['id'], 10, '0', STR_PAD_LEFT) . '-' . $r['kind'])
            ->values();
    }

    // ---------------------------------------------------------------------
    // Per-table query helpers — applied independently before the merge.
    // ---------------------------------------------------------------------

    private function queryTransfers(int $accountId, array $filters): Collection
    {
        $query = CashTransfer::forAccount($accountId)
            ->with(['fromPool:id,name', 'toPool:id,name', 'creator:id,name']);

        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $query->inDateRange($filters['date_from'], $filters['date_to']);
        }
        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('reference_no', 'like', "%{$s}%")->orWhere('description', 'like', "%{$s}%");
            });
        }
        if (($filters['voided'] ?? null) === 'active') {
            $query->whereNull('voided_at');
        } elseif (($filters['voided'] ?? null) === 'voided') {
            $query->whereNotNull('voided_at');
        }

        return $query->get()->map(fn ($t) => $this->mapTransfer($t));
    }

    private function queryStaffAdvances(int $accountId, array $filters): Collection
    {
        $query = StaffAdvance::forAccount($accountId)
            ->with(['staffUser:id,name', 'pool:id,name', 'creator:id,name']);

        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $query->whereBetween('created_at', [$filters['date_from'] . ' 00:00:00', $filters['date_to'] . ' 23:59:59']);
        }
        if (!empty($filters['search'])) {
            $query->where('description', 'like', '%' . $filters['search'] . '%');
        }
        if (($filters['voided'] ?? null) === 'active') {
            $query->whereNull('voided_at');
        } elseif (($filters['voided'] ?? null) === 'voided') {
            $query->whereNotNull('voided_at');
        }

        return $query->get()->map(fn ($a) => $this->mapStaffAdvance($a));
    }

    private function queryStaffReturns(int $accountId, array $filters): Collection
    {
        $query = StaffReturn::forAccount($accountId)
            ->with(['staffUser:id,name', 'pool:id,name', 'creator:id,name']);

        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $query->whereBetween('created_at', [$filters['date_from'] . ' 00:00:00', $filters['date_to'] . ' 23:59:59']);
        }
        if (!empty($filters['search'])) {
            $query->where('description', 'like', '%' . $filters['search'] . '%');
        }
        if (($filters['voided'] ?? null) === 'active') {
            $query->whereNull('voided_at');
        } elseif (($filters['voided'] ?? null) === 'voided') {
            $query->whereNotNull('voided_at');
        }

        return $query->get()->map(fn ($r) => $this->mapStaffReturn($r));
    }

    private function queryStaffTransfers(int $accountId, array $filters): Collection
    {
        $query = StaffTransfer::forAccount($accountId)
            ->with(['fromUser:id,name', 'toUser:id,name', 'creator:id,name']);

        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $query->whereBetween('created_at', [$filters['date_from'] . ' 00:00:00', $filters['date_to'] . ' 23:59:59']);
        }
        if (!empty($filters['search'])) {
            $query->where('description', 'like', '%' . $filters['search'] . '%');
        }
        if (($filters['voided'] ?? null) === 'active') {
            $query->whereNull('voided_at');
        } elseif (($filters['voided'] ?? null) === 'voided') {
            $query->whereNotNull('voided_at');
        }

        return $query->get()->map(fn ($t) => $this->mapStaffTransfer($t));
    }

    /**
     * Source/dest filters applied uniformly after normalisation. Cheaper
     * than threading the conditional through three per-table queries
     * because each kind's column names differ.
     */
    private function applyWalletFilters(Collection $rows, array $filters): Collection
    {
        if (!empty($filters['source_type']) && !empty($filters['source_id'])) {
            $rows = $rows->filter(fn (array $r) =>
                $r['source']['type'] === $filters['source_type']
                && (int) $r['source']['id'] === (int) $filters['source_id']);
        }
        if (!empty($filters['dest_type']) && !empty($filters['dest_id'])) {
            $rows = $rows->filter(fn (array $r) =>
                $r['dest']['type'] === $filters['dest_type']
                && (int) $r['dest']['id'] === (int) $filters['dest_id']);
        }
        return $rows;
    }

    // ---------------------------------------------------------------------
    // Normalisers — one per source table → Movement DTO array.
    // ---------------------------------------------------------------------

    private function mapTransfer(CashTransfer $t): array
    {
        return [
            'id' => $t->id,
            'kind' => self::KIND_TRANSFER,
            'date' => $t->transfer_date->format('Y-m-d'),
            'amount' => (float) $t->amount,
            'source' => [
                'type' => self::SOURCE_POOL,
                'id' => (int) $t->from_pool_id,
                'name' => $t->fromPool?->name ?? '—',
            ],
            'dest' => [
                'type' => self::SOURCE_POOL,
                'id' => (int) $t->to_pool_id,
                'name' => $t->toPool?->name ?? '—',
            ],
            'description' => $t->description,
            'reference_no' => $t->reference_no,
            'method' => $t->method,
            'attachment_url' => $t->attachment_url,
            'status' => $t->voided_at ? 'voided' : 'active',
            'voided_at' => $t->voided_at?->toIso8601String(),
            'void_reason' => $t->void_reason,
            'created_by' => (int) $t->created_by,
            'creator' => $t->creator ? ['id' => $t->creator->id, 'name' => $t->creator->name] : null,
            'created_at' => $t->created_at?->toIso8601String(),
        ];
    }

    private function mapStaffAdvance(StaffAdvance $a): array
    {
        return [
            'id' => $a->id,
            'kind' => self::KIND_STAFF_ADVANCE,
            'date' => $a->created_at->format('Y-m-d'),
            'amount' => (float) $a->amount,
            'source' => [
                'type' => self::SOURCE_POOL,
                'id' => (int) $a->pool_id,
                'name' => $a->pool?->name ?? '—',
            ],
            'dest' => [
                'type' => self::SOURCE_STAFF,
                'id' => (int) $a->user_id,
                'name' => $a->staffUser?->name ?? '—',
            ],
            'description' => $a->description,
            'reference_no' => null,
            'method' => null,
            'attachment_url' => null,
            'status' => $a->voided_at ? 'voided' : 'active',
            'voided_at' => $a->voided_at?->toIso8601String(),
            'void_reason' => $a->void_reason,
            'created_by' => (int) $a->created_by,
            'creator' => $a->creator ? ['id' => $a->creator->id, 'name' => $a->creator->name] : null,
            'created_at' => $a->created_at?->toIso8601String(),
        ];
    }

    private function mapStaffReturn(StaffReturn $r): array
    {
        return [
            'id' => $r->id,
            'kind' => self::KIND_STAFF_RETURN,
            'date' => $r->created_at->format('Y-m-d'),
            'amount' => (float) $r->amount,
            'source' => [
                'type' => self::SOURCE_STAFF,
                'id' => (int) $r->user_id,
                'name' => $r->staffUser?->name ?? '—',
            ],
            'dest' => [
                'type' => self::SOURCE_POOL,
                'id' => (int) $r->pool_id,
                'name' => $r->pool?->name ?? '—',
            ],
            'description' => $r->description,
            'reference_no' => null,
            'method' => null,
            'attachment_url' => null,
            'status' => $r->voided_at ? 'voided' : 'active',
            'voided_at' => $r->voided_at?->toIso8601String(),
            'void_reason' => $r->void_reason,
            'created_by' => (int) $r->created_by,
            'creator' => $r->creator ? ['id' => $r->creator->id, 'name' => $r->creator->name] : null,
            'created_at' => $r->created_at?->toIso8601String(),
        ];
    }

    private function mapStaffTransfer(StaffTransfer $t): array
    {
        return [
            'id' => $t->id,
            'kind' => self::KIND_STAFF_TRANSFER,
            'date' => $t->created_at->format('Y-m-d'),
            'amount' => (float) $t->amount,
            'source' => [
                'type' => self::SOURCE_STAFF,
                'id' => (int) $t->from_user_id,
                'name' => $t->fromUser?->name ?? '—',
            ],
            'dest' => [
                'type' => self::SOURCE_STAFF,
                'id' => (int) $t->to_user_id,
                'name' => $t->toUser?->name ?? '—',
            ],
            'description' => $t->description,
            'reference_no' => null,
            'method' => null,
            'attachment_url' => null,
            'status' => $t->voided_at ? 'voided' : 'active',
            'voided_at' => $t->voided_at?->toIso8601String(),
            'void_reason' => $t->void_reason,
            'created_by' => (int) $t->created_by,
            'creator' => $t->creator ? ['id' => $t->creator->id, 'name' => $t->creator->name] : null,
            'created_at' => $t->created_at?->toIso8601String(),
        ];
    }
}
