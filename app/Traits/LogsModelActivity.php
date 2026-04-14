<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\ActivityLogTier;

/**
 * Opt-in audit-log hook for Eloquent models.
 *
 * Attach this trait to any model whose mutations should land in the
 * `activities` table via App\Observers\ActivityLogObserver. The observer
 * is registered automatically by the `bootLogsModelActivity()` hook and
 * fires on the `created` / `updated` / `deleted` lifecycle events.
 *
 * Overrides a model can provide (all optional except activityLogTier,
 * which must return non-null for the observer to record anything):
 *
 *   activityLogTier()        → ActivityLogTier enum. Return null to skip.
 *   activityLogEvents()      → array of lifecycle events to observe.
 *   activityLogAttributes()  → whitelist of attribute names. Empty = all
 *                              attributes minus the global denylist in
 *                              config/activity_log.php. A non-empty list
 *                              restricts further.
 *   activityLogMaskValues()  → true (default) means descriptions list the
 *                              names of changed fields but not the values.
 *                              Set false for models where the values are
 *                              safe to record (e.g. role names).
 *   activityLogDescription() → custom description. Return null to fall
 *                              back to the observer's default formatter.
 *
 * Design intent: this trait should never duplicate rows that the manual
 * App\Helpers\ActivityLogger already writes. Apply it only to models
 * that currently have ZERO coverage (User, Role, Permission) or at
 * lifecycle events not handled by the manual helper.
 */
trait LogsModelActivity
{
    // NOTE: No bootLogsModelActivity() hook — attachment is done centrally
    // by App\Providers\AppServiceProvider::registerObservedModels() for
    // every model discovered under App\Models. Double-registration would
    // cause each write to produce two audit rows; keeping attachment in
    // one place avoids that.

    public function activityLogTier(): ?ActivityLogTier
    {
        return null;
    }

    /**
     * @return array<int, string>
     */
    public function activityLogEvents(): array
    {
        return ['created', 'updated', 'deleted'];
    }

    /**
     * @return array<int, string>
     */
    public function activityLogAttributes(): array
    {
        return [];
    }

    public function activityLogMaskValues(): bool
    {
        return true;
    }

    public function shouldLogActivityEvent(string $event): bool
    {
        return true;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function activityLogDescription(string $event, array $changes): ?string
    {
        return null;
    }
}
