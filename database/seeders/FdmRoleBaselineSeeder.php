<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Non-dashboard operational permissions the FDM role is meant to hold.
 *
 * Idempotent + standalone:
 *   php artisan db:seed --class=FdmRoleBaselineSeeder
 *
 * `plans_cash_edit` lets an FDM *add* a payment to a plan they manage
 * (PlansController gates the cash-add path on it). FDM is intentionally
 * NOT granted `plans_cash_delete` / `plans_cash_edit_amount|date|
 * payment_mode` — they can record a payment but must not alter or remove
 * an already-recorded one. The SPA mirrors this split (the membership
 * edit dialog now gates add vs. edit/delete-existing separately).
 *
 * Spatie's givePermissionTo is a no-op when already assigned, so re-runs
 * never duplicate the pivot row.
 */
class FdmRoleBaselineSeeder extends Seeder
{
    /** Permissions FDM must have. Keep this the *only* place that grows. */
    private const FDM_GRANTS = [
        'plans_cash_edit',
    ];

    public function run(): void
    {
        $fdm = Role::where('name', 'FDM')->where('guard_name', 'web')->first();

        if (! $fdm) {
            $this->command?->warn('FDM role not found — skipped. Create it / assign via the role editor.');

            return;
        }

        $fdm->givePermissionTo(self::FDM_GRANTS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info(sprintf(
            'FDM baseline granted: %s. Spatie cache cleared. Affected users must re-login or refresh /api/user.',
            implode(', ', self::FDM_GRANTS),
        ));
    }
}
