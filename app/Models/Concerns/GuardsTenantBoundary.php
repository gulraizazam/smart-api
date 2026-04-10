<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Tenant-boundary guard.
 *
 * Defends against multi-tenant escape via mass-assignment by:
 *   1. On creating: forcing `account_id` to the authenticated user's account
 *      (when the column is fillable and a user is authenticated).
 *   2. On updating: reverting any dirty change to a guarded column back to
 *      its original value, and logging the attempt as a security event.
 *
 * Models can extend the guarded set by overriding the static
 * `$tenantGuardedColumns` array. The default protects `account_id`; the
 * `User` model extends it to also protect `user_type_id` and `is_admin`,
 * because mutating those is a privilege escalation, not a tenant escape.
 *
 * Why a trait and not a base-class hook only:
 *   `User` extends `Illuminate\Foundation\Auth\User` (Authenticatable), not
 *   `BaseModel`, so it cannot inherit guards from BaseModel. The trait lets
 *   both BaseModel and User share identical enforcement.
 */
trait GuardsTenantBoundary
{
    /**
     * Columns whose values can never be changed via mass-assignment after
     * the row exists. Override `tenantGuardedColumns()` in the consuming
     * model to expand the list.
     *
     * Implemented as a static method (not a property) to avoid PHP 8.3+
     * trait-property composition errors when consuming models override
     * the default value.
     *
     * @return array<int, string>
     */
    protected static function tenantGuardedColumns(): array
    {
        return ['account_id'];
    }

    public static function bootGuardsTenantBoundary(): void
    {
        // Preserves the existing BaseModel behavior: force account_id to the
        // authenticated user's account on insert when the column is fillable.
        // This is unconditional — if calling code tries to set a different
        // account_id (cross-tenant write), it gets overwritten.
        static::creating(function (Model $model): void {
            if (Auth::check() && in_array('account_id', $model->getFillable(), true)) {
                $model->account_id = Auth::user()->account_id;
            }
        });

        static::updating(function (Model $model): void {
            foreach (static::tenantGuardedColumns() as $column) {
                if ($model->isDirty($column)) {
                    $original = $model->getOriginal($column);
                    $attempted = $model->getAttribute($column);

                    Log::warning('GuardsTenantBoundary: blocked mass-assignment of guarded column', [
                        'model' => static::class,
                        'id' => $model->getKey(),
                        'column' => $column,
                        'original' => $original,
                        'attempted' => $attempted,
                        'user_id' => Auth::id(),
                    ]);

                    $model->setAttribute($column, $original);
                }
            }
        });
    }
}
