<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * One null-safe date-range scope shared by the cash-flow models that
 * filter on a date column.
 *
 * Each bound is applied independently, so an open-ended range
 * ("from May 1" with no end, or "until May 1" with no start) filters
 * correctly. A bare `whereBetween($col, [$from, $to])` needs BOTH sides
 * — so every caller used to gate on `!empty($from) && !empty($to)` and
 * silently apply *nothing* for a one-sided range (the "From May 1 still
 * shows April" bug). With both bounds set the behaviour is identical to
 * the old `whereBetween` (`>= from AND <= to`, inclusive, sargable on a
 * DATE column), so existing callers are unaffected.
 *
 * The model picks the column by overriding the `dateRangeColumn()`
 * method (defaults to `created_at`); empty/null bounds are ignored, so
 * callers no longer need their own "both present" guard. Mirrors the SPA's
 * client-side `ledgerRowMatches`, which already applies each bound
 * independently — this makes the server agree with it.
 */
trait FiltersByDateRange
{
    /**
     * Column the range filters. Override per model. (A method, not a
     * property — a trait property whose default a model overrides with
     * a different value is a PHP composition fatal.)
     */
    protected function dateRangeColumn(): string
    {
        return 'created_at';
    }

    public function scopeInDateRange(
        Builder $query,
        ?string $from,
        ?string $to,
    ): Builder {
        $column = $this->dateRangeColumn();

        return $query
            ->when(! empty($from), fn (Builder $q) => $q->where($column, '>=', $from))
            ->when(! empty($to), fn (Builder $q) => $q->where($column, '<=', $to));
    }
}
