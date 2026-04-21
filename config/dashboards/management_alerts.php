<?php

declare(strict_types=1);

/**
 * Threshold defaults for the Management Dashboard alert engine. These defaults
 * ship with the app; per-account overrides live in the `settings` table under
 * slug `mgmt_dashboard_alerts` and are deep-merged on top at read time (see
 * AlertEngine::thresholds).
 */
return [
    // Branch target risk: flag a branch if its MTD revenue is below this
    // percent of target AND fewer than `working_days_threshold` remain.
    'target_risk_threshold_pct' => 60,
    'working_days_threshold' => 7,

    // Refund spike: today's refund % > multiplier × 90-day average refund %.
    'refund_spike_multiplier' => 2.0,
    'refund_min_volume' => 5,

    // No-show spike: today's no-show % > multiplier × 90-day average no-show %.
    'no_show_spike_multiplier' => 2.0,
    'no_show_min_volume' => 10,

    // Conversion drop: resource's weekly conversion rate dropped by N
    // percentage points vs the prior week.
    'conversion_drop_pp' => 10,
    'conversion_min_consultations' => 5,
];
