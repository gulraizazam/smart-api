<?php

declare(strict_types=1);

/**
 * Retention policy for high-volume log/audit tables on the shared crm DB.
 *
 * The `logs:prune` command (App\Console\Commands\PruneLogTables) reads this:
 * for each table it exports rows older than `months` to a compressed CSV
 * file under `export_path` (the keep-copy), then deletes them from the live
 * table. Deletes are chunked; `max_rows_per_run` bounds the load of any one
 * run (0 = unbounded). Tables flagged `authorized` go through the
 * tamper-evident mutation gate (UnlocksActivitiesMutation) so the delete is
 * logged, not blocked — see project_db_change_audit_spy.
 *
 * audit_trails / audit_trail_changes are intentionally NOT listed here: they
 * carry a parent/child FK (changes belong to trails) and are handled by the
 * existing `audit:archive` command. This command covers the flat, date-keyed
 * log tables only.
 */
return [

    'months' => (int) env('LOG_RETENTION_MONTHS', 6),

    'export_path' => env('LOG_RETENTION_EXPORT_PATH', storage_path('app/log-archive')),

    'chunk' => (int) env('LOG_RETENTION_CHUNK', 2000),

    // Safety ceiling per run so a single invocation can't hammer the shared
    // box. 0 = unbounded (the one-time backlog clear). The monthly job only
    // ever sweeps one fresh month, so the default is comfortably above it.
    'max_rows_per_run' => (int) env('LOG_RETENTION_MAX_ROWS_PER_RUN', 0),

    'tables' => [

        'sms_logs' => [
            'date' => 'created_at',
            'authorized' => false,
        ],

        'activities' => [
            'date' => 'created_at',
            // Tamper-evident triggers block direct DELETE; route through the
            // authorized mutation gate so the removal is logged.
            'authorized' => true,
        ],

    ],

];
