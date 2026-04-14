<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\ActivityLogTier;
use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Generic observer writing to the `activities` table. Attached centrally
 * by App\Providers\AppServiceProvider::registerObservedModels() to every
 * model under App\Models (recursive) unless explicitly excluded in
 * config/activity_log.php.
 *
 * Per-model behaviour is resolved in priority order:
 *   1. Trait override methods on the model (if App\Traits\LogsModelActivity
 *      is used) — highest precedence for custom descriptions.
 *   2. Config per-model entries in activity_log.tiers / .whitelists /
 *      .mask_values.
 *   3. Global defaults (activity_log.default_tier, no whitelist, mask=true).
 *
 * Skip paths:
 *   - Update with an empty filtered change-set → no row (prevents noise
 *     from touch-updates on denylisted fields like failed_login_attempts).
 *   - Model returning null from activityLogTier() AND not listed in config
 *     → no row (opts out entirely).
 *
 * Write failures are swallowed to Log::error so an audit-table issue never
 * blocks a legitimate business mutation.
 */
class ActivityLogObserver
{
    public function created(Model $model): void
    {
        $this->record('created', $model, $model->getAttributes());
    }

    public function updated(Model $model): void
    {
        $this->record('updated', $model, $model->getChanges());
    }

    public function deleted(Model $model): void
    {
        $this->record('deleted', $model, ['id' => $model->getKey()]);
    }

    /**
     * @param  array<string, mixed>  $rawChanges
     */
    private function record(string $event, Model $model, array $rawChanges): void
    {
        if (! (bool) config('activity_log.enabled', true)) {
            return;
        }

        $tier = $this->resolveTier($model);
        if ($tier === null) {
            return;
        }

        if (! in_array($event, $this->resolveEvents($model), true)) {
            return;
        }

        if (method_exists($model, 'shouldLogActivityEvent') && ! $model->shouldLogActivityEvent($event)) {
            return;
        }

        $filtered = $this->filterChanges($model, $rawChanges);

        // Skip updates where nothing audit-worthy actually changed (e.g. a
        // User save that only touched `failed_login_attempts` — which is
        // globally denylisted — produces an empty change-set).
        if ($event === 'updated' && $filtered === []) {
            return;
        }

        $description = (method_exists($model, 'activityLogDescription')
            ? $model->activityLogDescription($event, $filtered)
            : null) ?? $this->defaultDescription($event, $model, $filtered);

        try {
            Activity::create([
                'account_id' => $this->resolveAccountId($model),
                'action' => $event,
                'activity_type' => $this->eventType($model, $event),
                'log_tier' => $tier->value,
                'description' => mb_substr($description, 0, 2000),
                'user_id' => Auth::id(),
                'created_by' => Auth::id(),
            ]);
        } catch (\Throwable $e) {
            Log::error('activities.model_observer.write_failed', [
                'event' => 'activities.model_observer.write_failed',
                'model' => $model::class,
                'model_id' => $model->getKey(),
                'action' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveTier(Model $model): ?ActivityLogTier
    {
        // Trait override wins.
        if (method_exists($model, 'activityLogTier')) {
            $tier = $model->activityLogTier();
            if ($tier instanceof ActivityLogTier) {
                return $tier;
            }
        }

        // Per-model config entry.
        $overrides = (array) config('activity_log.tiers', []);
        if (isset($overrides[$model::class])) {
            return ActivityLogTier::tryFrom((string) $overrides[$model::class]);
        }

        // Global default.
        $default = config('activity_log.default_tier');
        if ($default === null) {
            return null;
        }

        return ActivityLogTier::tryFrom((string) $default);
    }

    /**
     * @return array<int, string>
     */
    private function resolveEvents(Model $model): array
    {
        if (method_exists($model, 'activityLogEvents')) {
            return (array) $model->activityLogEvents();
        }

        return ['created', 'updated', 'deleted'];
    }

    /**
     * @return array<int, string>
     */
    private function resolveWhitelist(Model $model): array
    {
        if (method_exists($model, 'activityLogAttributes')) {
            $traitList = (array) $model->activityLogAttributes();
            if ($traitList !== []) {
                return $traitList;
            }
        }

        $configMap = (array) config('activity_log.whitelists', []);

        return (array) ($configMap[$model::class] ?? []);
    }

    private function resolveMaskValues(Model $model): bool
    {
        if (method_exists($model, 'activityLogMaskValues')) {
            return (bool) $model->activityLogMaskValues();
        }

        $configMap = (array) config('activity_log.mask_values', []);

        return (bool) ($configMap[$model::class] ?? true);
    }

    /**
     * @param  array<string, mixed>  $rawChanges
     * @return array<string, mixed>
     */
    private function filterChanges(Model $model, array $rawChanges): array
    {
        $denylist = (array) config('activity_log.denylist', []);
        $whitelist = $this->resolveWhitelist($model);

        $out = [];
        foreach ($rawChanges as $attr => $value) {
            if (in_array($attr, $denylist, true)) {
                continue;
            }
            if ($whitelist !== [] && ! in_array($attr, $whitelist, true)) {
                continue;
            }
            $out[$attr] = $value;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function defaultDescription(string $event, Model $model, array $changes): string
    {
        $label = class_basename($model::class);
        $id = $model->getKey() ?? '?';

        if ($event === 'deleted') {
            return "{$label}#{$id} deleted";
        }

        if ($event === 'created') {
            return "{$label}#{$id} created";
        }

        if ($this->resolveMaskValues($model)) {
            return "{$label}#{$id} updated fields: ".implode(', ', array_keys($changes));
        }

        $parts = [];
        foreach ($changes as $attr => $value) {
            $parts[] = "{$attr}=".$this->stringifyValue($value);
        }

        return "{$label}#{$id} updated: ".implode(', ', $parts);
    }

    private function stringifyValue(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            default => '(complex)',
        };
    }

    private function resolveAccountId(Model $model): ?int
    {
        $modelAccount = $model->getAttribute('account_id');
        if (is_numeric($modelAccount)) {
            return (int) $modelAccount;
        }

        $userAccount = Auth::user()?->getAttribute('account_id');
        if (is_numeric($userAccount)) {
            return (int) $userAccount;
        }

        return null;
    }

    private function eventType(Model $model, string $event): string
    {
        return strtolower(class_basename($model::class)).'_'.$event;
    }
}
