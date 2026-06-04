<?php

declare(strict_types=1);

namespace App\Services\Schedule;

use App\Models\ResourceHasRotaDays;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Auto-builds doctor/resource rota for a newly-working exception date by
 * cloning each resource's MOST RECENT working day onto it — account-wide
 * (every centre, every resource).
 *
 * Why: when an operator flips a normally-off day (e.g. Sunday) to working
 * via a Business-Working-Days exception, the clinic still can't take
 * bookings until every doctor's rota exists for that date. Rebuilding all
 * of those by hand is the slow step this removes — "make Sunday working"
 * now duplicates the latest working schedule each resource had.
 *
 * Each rota walks back independently to the most recent day it actually
 * worked (within a bounded window), so a resource that doesn't work the
 * immediately-previous day — or a clinic closed both weekend days — still
 * gets a sensible schedule rather than nothing.
 *
 * Safety invariants (the "don't disturb any other day" requirement):
 *   - Only the exception date's rota_days are ever written. No source
 *     day or any other date is read-modified.
 *   - A resource that already has a REAL schedule on the exception date
 *     is left exactly as-is — never clobbered. Only empty/off placeholder
 *     rows (null times, the rows an off-weekday materialises as) are
 *     filled, and genuinely missing rows are created.
 *   - Only working blocks (both start and end present) are copied, so a
 *     resource with no recent working day simply stays off.
 */
class ExceptionDayRotaCloner
{
    /**
     * How far back to search for a resource's most recent working day.
     * Bounds pathological cases (a resource whose rota ended long ago)
     * without missing the common weekly / occasional-closure patterns.
     */
    private const LOOKBACK_DAYS = 31;

    /**
     * @return int number of rota_days created or filled on the exception date
     */
    public function cloneFromMostRecentWorkingDay(int $accountId, Carbon $exceptionDate): int
    {
        $exceptionStr = $exceptionDate->copy()->toDateString();
        $windowStart = $exceptionDate->copy()->subDays(self::LOOKBACK_DAYS)->toDateString();
        $windowEnd = $exceptionDate->copy()->subDay()->toDateString();

        // Every active working rota_day in the look-back window, scoped to
        // this account via the parent rota, grouped per rota. Off rows
        // (null/empty times) are excluded — there's nothing to carry over.
        $byRota = ResourceHasRotaDays::query()
            ->join('resource_has_rota', 'resource_has_rota.id', '=', 'resource_has_rota_days.resource_has_rota_id')
            ->where('resource_has_rota.account_id', $accountId)
            ->where('resource_has_rota.active', 1)
            ->whereNull('resource_has_rota.deleted_at')
            ->whereBetween('resource_has_rota_days.date', [$windowStart, $windowEnd])
            ->where('resource_has_rota_days.active', 1)
            ->whereNull('resource_has_rota_days.deleted_at')
            ->whereNotNull('resource_has_rota_days.start_time')
            ->where('resource_has_rota_days.start_time', '!=', '')
            ->whereNotNull('resource_has_rota_days.end_time')
            ->select(
                'resource_has_rota_days.resource_has_rota_id',
                'resource_has_rota_days.date',
                'resource_has_rota_days.start_time',
                'resource_has_rota_days.end_time',
                'resource_has_rota_days.start_off',
                'resource_has_rota_days.end_off',
            )
            ->get()
            ->groupBy('resource_has_rota_id');

        if ($byRota->isEmpty()) {
            return 0;
        }

        $touched = 0;

        DB::transaction(function () use ($byRota, $exceptionStr, &$touched): void {
            foreach ($byRota as $rotaId => $rows) {
                // The resource's MOST RECENT working day in the window, and
                // every shift it worked that day (split shifts included).
                $sourceDate = $rows->max(fn ($r) => $this->dateKey($r->date));
                $shifts = $rows
                    ->filter(fn ($r) => $this->dateKey($r->date) === $sourceDate)
                    ->values();

                // Never touch a rota that already holds a real schedule on
                // the exception date (a pre-existing, possibly booking-
                // bearing row) — decided once, before writing, so a shift we
                // add this run can't be mistaken for one.
                $hasRealSchedule = ResourceHasRotaDays::where('resource_has_rota_id', $rotaId)
                    ->whereDate('date', $exceptionStr)
                    ->whereNotNull('start_time')
                    ->where('start_time', '!=', '')
                    ->exists();
                if ($hasRealSchedule) {
                    continue;
                }

                // Reuse an existing off/empty placeholder row for the first
                // shift (the row an off-weekday materialises as); create the
                // rest. Leftover placeholders are harmless — the availability
                // widgets skip null-time rows.
                $placeholder = ResourceHasRotaDays::where('resource_has_rota_id', $rotaId)
                    ->whereDate('date', $exceptionStr)
                    ->first();

                foreach ($shifts as $index => $shift) {
                    $payload = [
                        'date' => $exceptionStr,
                        'start_time' => $shift->start_time,
                        'end_time' => $shift->end_time,
                        'start_off' => $shift->start_off,
                        'end_off' => $shift->end_off,
                        'start_timestamp' => $this->timestamp($exceptionStr, $shift->start_time),
                        'end_timestamp' => $this->timestamp($exceptionStr, $shift->end_time),
                        'active' => 1,
                        'resource_has_rota_id' => $rotaId,
                    ];

                    if ($index === 0 && $placeholder) {
                        $placeholder->update($payload);
                    } else {
                        ResourceHasRotaDays::create($payload);
                    }

                    $touched++;
                }
            }
        });

        return $touched;
    }

    /** Normalise a date/datetime value to its `Y-m-d` key. */
    private function dateKey(mixed $date): string
    {
        return substr((string) $date, 0, 10);
    }

    private function timestamp(string $date, ?string $time): ?string
    {
        if (! $time) {
            return null;
        }

        return Carbon::parse($date.' '.$time)->format('Y-m-d H:i:00');
    }
}
