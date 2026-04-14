<?php

declare(strict_types=1);

/**
 * Feature flags for staged rollouts and strict-mode toggles.
 *
 * Each entry should have an owner and a removal date — flags are not
 * permanent. Once a feature is fully shipped (or rejected), delete the
 * flag and the dead branch.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | activities_strict_descriptions
    |--------------------------------------------------------------------------
    |
    | When true, ActivityLogService::formatActivities() throws if it
    | encounters an activity row with a null description, instead of
    | silently rendering one on the fly. Use in dev/staging to catch
    | producer bugs early — every Activity write must populate the
    | `description` column (the migration
    | 2026_04_13_180000_make_activity_descriptions_not_null enforces it
    | at the DB level once it has run, but the runtime check surfaces
    | violations during development before the row is even inserted).
    |
    | Owner: backend
    | Remove after: 2026-07-01 (or once the NOT NULL migration has been
    | live in production for one full release cycle).
    |
    */
    'activities_strict_descriptions' => env('FEATURE_ACTIVITIES_STRICT_DESCRIPTIONS', false),

];
