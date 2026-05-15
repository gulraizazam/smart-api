<?php

declare(strict_types=1);

namespace App\Observers\Schedule;

use App\Support\OperatingDays;

/**
 * Bumps OperatingDays' per-account version counter whenever a closure,
 * working-day exception, or business-working-days setting row changes.
 *
 * The counter is a freshness signal for downstream caches (currently
 * BenchmarkCalculator's per-doctor revenue pool). Cached results that
 * embed the version in their key are effectively invalidated the moment
 * a new closure / exception lands — operationally important because
 * holidays and "open this Sunday" decisions are usually made hours
 * before the dashboard is consulted, not days in advance.
 *
 * Observed models:
 *   - BusinessClosure         (created / updated / deleted / restored)
 *   - WorkingDayException     (created / updated / deleted)
 *   - Settings (via slug)     (caller bumps manually — Settings model is
 *                              org-wide and not all slugs are relevant)
 */
class OperatingDaysVersionObserver
{
    public function created(\Illuminate\Database\Eloquent\Model $model): void
    {
        $this->bump($model);
    }

    public function updated(\Illuminate\Database\Eloquent\Model $model): void
    {
        $this->bump($model);
    }

    public function deleted(\Illuminate\Database\Eloquent\Model $model): void
    {
        $this->bump($model);
    }

    public function restored(\Illuminate\Database\Eloquent\Model $model): void
    {
        $this->bump($model);
    }

    private function bump(\Illuminate\Database\Eloquent\Model $model): void
    {
        $accountId = (int) ($model->account_id ?? 0);
        if ($accountId > 0) {
            OperatingDays::bumpVersion($accountId);
        }
    }
}
