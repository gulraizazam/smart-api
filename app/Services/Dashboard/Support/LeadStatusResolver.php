<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Support;

use Illuminate\Support\Facades\DB;

/**
 * Resolves lead-status IDs into semantic buckets (converted / arrived / booked / junk).
 *
 * Extracted from LeadGenderDeepDiveMetric so the new leads dashboard metrics
 * and the existing gender-splits panel share ONE source of truth. Two rules
 * matter:
 *
 * 1. **Flags are hierarchical.** A row with `is_converted=1` also counts as
 *    Arrived and Booked. A row with `is_arrived=1` also counts as Booked.
 *    Junk is a separate axis (`is_junk=1`) and is orthogonal — a status
 *    can be Junk without being Booked/Arrived/Converted.
 *
 * 2. **Legacy fallback IDs.** If a tenant hasn't yet backfilled the flags,
 *    we fall back to the historical hard-coded IDs from before the flags
 *    existed. Preserves behaviour for old tenants; new tenants set the
 *    flags in `lead_statuses` and drive everything from there.
 *
 * Per-account results are cached in-request (each call to this class is a
 * dashboard request; caching across requests would risk staleness after an
 * admin edits a status row).
 */
final class LeadStatusResolver
{
    /** Legacy fallback IDs — see LeadGenderDeepDiveMetric for provenance. */
    private const LEGACY_BOOKED_OR_BEYOND = [4, 6, 10, 9, 11];

    private const LEGACY_ARRIVED_OR_BEYOND = [6, 10, 9, 11];

    private const LEGACY_CONVERTED = [9, 11];

    /** @var array<int, array{converted:list<int>, arrived:list<int>, booked:list<int>, junk:list<int>}> */
    private array $cache = [];

    /**
     * @return array{converted:list<int>, arrived:list<int>, booked:list<int>, junk:list<int>}
     */
    public function statusIds(int $accountId): array
    {
        if (isset($this->cache[$accountId])) {
            return $this->cache[$accountId];
        }

        $rows = DB::table('lead_statuses')
            ->where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->selectRaw(
                'id, COALESCE(is_converted, 0) AS is_converted, '.
                'COALESCE(is_arrived, 0) AS is_arrived, '.
                'COALESCE(is_booked, 0) AS is_booked, '.
                'COALESCE(is_junk, 0) AS is_junk'
            )
            ->get();

        $converted = [];
        $arrived = [];
        $booked = [];
        $junk = [];
        foreach ($rows as $r) {
            if ((int) $r->is_converted === 1) {
                $converted[] = (int) $r->id;
            }
            if ((int) $r->is_arrived === 1 || (int) $r->is_converted === 1) {
                $arrived[] = (int) $r->id;
            }
            if ((int) $r->is_booked === 1 || (int) $r->is_arrived === 1 || (int) $r->is_converted === 1) {
                $booked[] = (int) $r->id;
            }
            if ((int) $r->is_junk === 1) {
                $junk[] = (int) $r->id;
            }
        }

        if ($converted === []) {
            $converted = self::LEGACY_CONVERTED;
        }
        if ($arrived === []) {
            $arrived = self::LEGACY_ARRIVED_OR_BEYOND;
        }
        if ($booked === []) {
            $booked = self::LEGACY_BOOKED_OR_BEYOND;
        }
        // No sensible legacy junk fallback — leave empty; the "junk" metric
        // will report 0 in the (unlikely) case a tenant has no is_junk flag set.

        return $this->cache[$accountId] = [
            'converted' => $converted,
            'arrived' => $arrived,
            'booked' => $booked,
            'junk' => $junk,
        ];
    }

    public function converted(int $accountId): array
    {
        return $this->statusIds($accountId)['converted'];
    }

    public function arrivedOrBeyond(int $accountId): array
    {
        return $this->statusIds($accountId)['arrived'];
    }

    public function bookedOrBeyond(int $accountId): array
    {
        return $this->statusIds($accountId)['booked'];
    }

    public function junk(int $accountId): array
    {
        return $this->statusIds($accountId)['junk'];
    }
}
