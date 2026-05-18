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
     *   - date_from / date_to:    ISO YYYY-MM-DD, applied via the shared
     *                             null-safe FiltersByDateRange scope on
     *                             each kind's own date column (transfer_date
     *                             / advance_date / return_date). Either
     *                             bound may be omitted (open-ended range).
     *   - source_type / source_id, dest_type / dest_id: scope to a wallet.
     *   - kind:                   transfer | staff_advance | staff_return
     *                             | staff_transfer.
     *   - search:                 LIKE across description, reference_no
     *                             (transfers), counterparty pool/staff
     *                             names, and amount.
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
            // method + reference_no + attachment_url are nullable since
            // 2026-05-15 (SPA dropped them; backend may still receive
            // them from legacy callers). New SPA uploads come through
            // `attachment_ids` and bind below.
            $transfer = $this->transferService->create([
                'transfer_date' => $data['transfer_date'],
                'amount' => $data['amount'],
                'from_pool_id' => (int) $data['source_id'],
                'to_pool_id' => (int) $data['dest_id'],
                'method' => $data['method'] ?? null,
                'reference_no' => $data['reference_no'] ?? null,
                'attachment_url' => $data['attachment_url'] ?? null,
                'description' => $data['description'] ?? null,
            ], $accountId);

            $attachmentIds = $data['attachment_ids'] ?? [];
            if (is_array($attachmentIds) && count($attachmentIds) > 0) {
                // Bind orphan attachments to this transfer. Scoped to
                // account + still-orphan + not-deleted so a malicious
                // payload can't steal another tenant's attachment or
                // re-bind one that already belongs to a different
                // transfer.
                \App\Models\CashFlow\CashTransferAttachment::query()
                    ->where('account_id', $accountId)
                    ->whereIn('id', $attachmentIds)
                    ->whereNull('cash_transfer_id')
                    ->whereNull('deleted_at')
                    ->update(['cash_transfer_id' => $transfer->id]);
            }

            return $this->mapTransfer($transfer);
        }

        // The SPA ships a single `transfer_date` field for every combo;
        // we route it to the appropriate per-table column. Default to
        // today so legacy callers that never send a date keep working.
        $movementDate = $data['transfer_date'] ?? now()->toDateString();

        // Staff-side attachments — orphan rows in `movement_attachments`.
        // We bind them to the freshly-created row's (kind, id) after each
        // per-kind service call. Tenant + orphan checks already ran in
        // StoreMovementRequest, so the update here is just a kind+id flip.
        $attachmentIds = is_array($data['attachment_ids'] ?? null) ? $data['attachment_ids'] : [];

        if ($sourceType === self::SOURCE_POOL && $destType === self::SOURCE_STAFF) {
            $advance = $this->staffAdvanceService->createAdvance([
                'user_id' => (int) $data['dest_id'],
                'pool_id' => (int) $data['source_id'],
                'amount' => $data['amount'],
                'advance_date' => $movementDate,
                'description' => $data['description'] ?? null,
            ], $accountId);

            $this->bindStaffAttachments($attachmentIds, $accountId, self::KIND_STAFF_ADVANCE, (int) $advance->id);

            return $this->mapStaffAdvance($advance);
        }

        if ($sourceType === self::SOURCE_STAFF && $destType === self::SOURCE_POOL) {
            $return = $this->staffAdvanceService->createReturn([
                'user_id' => (int) $data['source_id'],
                'pool_id' => (int) $data['dest_id'],
                'amount' => $data['amount'],
                'return_date' => $movementDate,
                'description' => $data['description'] ?? null,
            ], $accountId);

            $this->bindStaffAttachments($attachmentIds, $accountId, self::KIND_STAFF_RETURN, (int) $return->id);

            return $this->mapStaffReturn($return);
        }

        if ($sourceType === self::SOURCE_STAFF && $destType === self::SOURCE_STAFF) {
            $transfer = $this->staffTransferService->create([
                'from_user_id' => (int) $data['source_id'],
                'to_user_id' => (int) $data['dest_id'],
                'amount' => $data['amount'],
                'transfer_date' => $movementDate,
                'description' => $data['description'] ?? null,
            ], $accountId);

            $this->bindStaffAttachments($attachmentIds, $accountId, self::KIND_STAFF_TRANSFER, (int) $transfer->id);

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
            ->with(['fromPool:id,name', 'toPool:id,name', 'creator:id,name', 'attachments'])
            ->inDateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null)
            ->orderBy('transfer_date', 'desc')
            ->get();
        foreach ($transfers as $t) {
            $rows->push($this->mapTransfer($t));
        }

        // Outbound advances from this pool. advance_date / return_date
        // were added 2026-05-15 — sort by them so a backdated entry
        // doesn't jump to "now" in the ledger; date-range filters use
        // the same column for the same reason.
        $advances = StaffAdvance::forAccount($accountId)
            ->where('pool_id', $poolId)
            ->with(['staffUser:id,name', 'pool:id,name', 'creator:id,name', 'attachments'])
            ->inDateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null)
            ->orderBy('advance_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
        foreach ($advances as $a) {
            $rows->push($this->mapStaffAdvance($a));
        }

        // Inbound returns into this pool.
        $returns = StaffReturn::forAccount($accountId)
            ->where('pool_id', $poolId)
            ->with(['staffUser:id,name', 'pool:id,name', 'creator:id,name', 'attachments'])
            ->inDateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null)
            ->orderBy('return_date', 'desc')
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

    /**
     * Shared movement search predicate: matches `description` + `amount`
     * (CAST so "5000" finds 5000.00) + each given counterparty-name
     * relation, plus `reference_no` when the table has one. One place so
     * the columns the search spans can't drift per kind. No-op on an
     * empty term.
     *
     * @param  list<string>  $nameRelations  belongsTo relations matched on `name`
     */
    private function applyMovementSearch(
        $query,
        ?string $s,
        array $nameRelations,
        bool $hasReference = false,
    ): void {
        if (empty($s)) {
            return;
        }
        $query->where(function ($q) use ($s, $nameRelations, $hasReference) {
            if ($hasReference) {
                $q->orWhere('reference_no', 'like', "%{$s}%");
            }
            $q->orWhere('description', 'like', "%{$s}%")
                ->orWhereRaw('CAST(amount AS CHAR) LIKE ?', ["%{$s}%"]);
            foreach ($nameRelations as $rel) {
                $q->orWhereHas($rel, fn ($r) => $r->where('name', 'like', "%{$s}%"));
            }
        });
    }

    private function queryTransfers(int $accountId, array $filters): Collection
    {
        $query = CashTransfer::forAccount($accountId)
            ->with(['fromPool:id,name', 'toPool:id,name', 'creator:id,name', 'attachments']);

        $query->inDateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null);
        $this->applyMovementSearch($query, $filters['search'] ?? null, ['fromPool', 'toPool'], true);
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
            ->with(['staffUser:id,name', 'pool:id,name', 'creator:id,name', 'attachments']);

        // Date filter runs against `advance_date` (added 2026-05-15) —
        // backdated rows must show up in their target month, not their
        // bookkeeping month.
        $query->inDateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null);
        $this->applyMovementSearch($query, $filters['search'] ?? null, ['staffUser', 'pool']);
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
            ->with(['staffUser:id,name', 'pool:id,name', 'creator:id,name', 'attachments']);

        $query->inDateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null);
        $this->applyMovementSearch($query, $filters['search'] ?? null, ['staffUser', 'pool']);
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
            ->with(['fromUser:id,name', 'toUser:id,name', 'creator:id,name', 'attachments']);

        $query->inDateRange($filters['date_from'] ?? null, $filters['date_to'] ?? null);
        $this->applyMovementSearch($query, $filters['search'] ?? null, ['fromUser', 'toUser']);
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

    /**
     * Bind orphan staff-side attachments (movement_attachments rows with
     * NULL kind+id) to the freshly-created movement row. Scoped to the
     * caller's tenant + still-orphan so a hostile payload can't steal
     * another tenant's attachment or re-bind one that already belongs
     * somewhere. The StoreMovementRequest's `exists` rule already does
     * this check up-front; the WHERE clauses here are defense in depth.
     */
    private function bindStaffAttachments(array $ids, int $accountId, string $kind, int $movementId): void
    {
        if (empty($ids)) {
            return;
        }
        \App\Models\CashFlow\MovementAttachment::query()
            ->where('account_id', $accountId)
            ->whereIn('id', $ids)
            ->whereNull('movement_id')
            ->whereNull('deleted_at')
            ->update([
                'movement_kind' => $kind,
                'movement_id' => $movementId,
            ]);
    }

    /**
     * Lightweight attachment summary for a Movement DTO. The `attachments`
     * relation on each staff model already scopes to the right kind, so
     * reading from `$row->attachments` (eager-loaded by list queries) is
     * safe — falls back to a single fetch when the relation isn't loaded.
     */
    private function summariseAttachments($row): array
    {
        $attachments = $row->relationLoaded('attachments')
            ? $row->attachments
            : $row->attachments()->get();
        return $attachments
            ->map(fn ($a) => [
                'id' => (int) $a->id,
                'file_name' => $a->file_name,
                'mime_type' => $a->mime_type,
                'file_size' => (int) $a->file_size,
            ])
            ->values()
            ->all();
    }

    // ---------------------------------------------------------------------
    // Normalisers — one per source table → Movement DTO array.
    // ---------------------------------------------------------------------

    private function mapTransfer(CashTransfer $t): array
    {
        // Drop-zone attachments live here as a lightweight summary —
        // signed URLs are short-lived (15 min) so we mint them on demand
        // via the per-attachment signed-url endpoint, not on every list
        // serialise. The legacy `attachment_url` column stays in the
        // payload so pre-cutover rows keep rendering their drive link
        // alongside any new attachments.
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
            'attachments' => $this->summariseAttachments($t),
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
        // advance_date is the persisted movement date (added 2026-05-15);
        // pre-cutover rows backfilled to DATE(created_at) by migration,
        // but defend against any NULL stragglers.
        $date = $a->advance_date
            ? $a->advance_date->format('Y-m-d')
            : ($a->created_at?->format('Y-m-d') ?? '');
        $attachments = $this->summariseAttachments($a);
        return [
            'id' => $a->id,
            'kind' => self::KIND_STAFF_ADVANCE,
            'attachments' => $attachments,
            'date' => $date,
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
        $date = $r->return_date
            ? $r->return_date->format('Y-m-d')
            : ($r->created_at?->format('Y-m-d') ?? '');
        $attachments = $this->summariseAttachments($r);
        return [
            'id' => $r->id,
            'kind' => self::KIND_STAFF_RETURN,
            'attachments' => $attachments,
            'date' => $date,
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
        $date = $t->transfer_date
            ? $t->transfer_date->format('Y-m-d')
            : ($t->created_at?->format('Y-m-d') ?? '');
        $attachments = $this->summariseAttachments($t);
        return [
            'id' => $t->id,
            'kind' => self::KIND_STAFF_TRANSFER,
            'attachments' => $attachments,
            'date' => $date,
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
