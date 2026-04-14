<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\ActivityLogTier;
use App\Models\Activity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Events\PermissionAttached;
use Spatie\Permission\Events\PermissionDetached;
use Spatie\Permission\Events\RoleAttached;
use Spatie\Permission\Events\RoleDetached;

/**
 * Security-tier audit trail for Spatie permission mutations.
 *
 * Captures role grants/revocations and per-user permission grants/
 * revocations — the admin actions that actually change who can do what.
 * Without this, a Super-Admin giving another user elevated access leaves
 * no trace in the audit log.
 *
 * Registered in App\Providers\AppServiceProvider::registerAuthEventListeners().
 *
 * Role/permission names are NOT PII and ARE logged as values (unlike
 * the model observer which masks by default). Investigators need to know
 * "Alice was granted the `billing.manage` permission" — the name is the
 * whole point of the audit entry.
 */
class PermissionActivityListener
{
    public function onRoleAttached(RoleAttached $event): void
    {
        $this->write('role_attached', $event->model, $this->resolveRoleNames($event->rolesOrIds));
    }

    public function onRoleDetached(RoleDetached $event): void
    {
        $this->write('role_detached', $event->model, $this->resolveRoleNames($event->rolesOrIds));
    }

    public function onPermissionAttached(PermissionAttached $event): void
    {
        $this->write('permission_attached', $event->model, $this->resolvePermissionNames($event->permissionsOrIds));
    }

    public function onPermissionDetached(PermissionDetached $event): void
    {
        $this->write('permission_detached', $event->model, $this->resolvePermissionNames($event->permissionsOrIds));
    }

    /**
     * @param  array<int, string>  $targetNames
     */
    private function write(string $action, Model $subject, array $targetNames): void
    {
        try {
            $subjectLabel = class_basename($subject::class).'#'.($subject->getKey() ?? '?');
            $targets = $targetNames === [] ? 'none' : implode(', ', $targetNames);

            $verb = match ($action) {
                'role_attached' => 'granted role(s)',
                'role_detached' => 'revoked role(s)',
                'permission_attached' => 'granted permission(s)',
                'permission_detached' => 'revoked permission(s)',
                default => $action,
            };

            Activity::create([
                'account_id' => $this->resolveAccountId($subject),
                'action' => $action,
                'activity_type' => $action,
                'log_tier' => ActivityLogTier::Security->value,
                'description' => mb_substr("{$subjectLabel} {$verb}: {$targets}", 0, 2000),
                'user_id' => $subject->getKey(),
                'created_by' => auth()->id(),
            ]);
        } catch (\Throwable $e) {
            Log::error('activities.permission_listener.write_failed', [
                'event' => 'activities.permission_listener.write_failed',
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Normalises Spatie's loose type (Role|int|string|array|Collection) into
     * a flat array of human-readable role names.
     *
     * @return array<int, string>
     */
    private function resolveRoleNames(mixed $rolesOrIds): array
    {
        return $this->resolveNames($rolesOrIds, config('permission.table_names.roles', 'roles'));
    }

    /**
     * @return array<int, string>
     */
    private function resolvePermissionNames(mixed $permissionsOrIds): array
    {
        return $this->resolveNames($permissionsOrIds, config('permission.table_names.permissions', 'permissions'));
    }

    /**
     * @return array<int, string>
     */
    private function resolveNames(mixed $rawTargets, string $table): array
    {
        if ($rawTargets instanceof Collection) {
            $rawTargets = $rawTargets->all();
        }

        $targets = is_array($rawTargets) ? $rawTargets : [$rawTargets];
        $names = [];
        $ids = [];

        foreach ($targets as $t) {
            if ($t instanceof Model) {
                $name = $t->getAttribute('name');
                if (is_string($name)) {
                    $names[] = $name;

                    continue;
                }
                if ($t->getKey() !== null) {
                    $ids[] = $t->getKey();
                }
            } elseif (is_numeric($t) || is_string($t)) {
                $ids[] = $t;
            }
        }

        if ($ids !== []) {
            $resolved = DB::table($table)->whereIn('id', $ids)->pluck('name')->all();
            foreach ($resolved as $n) {
                if (is_string($n)) {
                    $names[] = $n;
                }
            }
        }

        return $names;
    }

    private function resolveAccountId(Model $model): ?int
    {
        $value = $model->getAttribute('account_id');
        if (is_numeric($value)) {
            return (int) $value;
        }

        $actor = auth()->user();
        if ($actor !== null) {
            $actorAccount = $actor->getAttribute('account_id');
            if (is_numeric($actorAccount)) {
                return (int) $actorAccount;
            }
        }

        return null;
    }
}
