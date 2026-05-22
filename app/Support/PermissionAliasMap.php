<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Bidirectional alias map between the legacy snake_case permission catalog
 * (`plans_cash_edit_amount`, `packages_manage`, …) and the new dotted
 * catalog (`plans.cash.edit_amount`, `packages.detail.view`, …) that the
 * role editor surfaces.
 *
 * The catalogs were introduced incrementally and now coexist in the DB:
 *   • Legacy: parent `plans_manage` (id 157), `status=0` — hidden from the
 *     role editor but every `Gate::allows('plans_*')` in the codebase still
 *     reads these names.
 *   • New:    parent `plans` (id 909),         `status=1` — what users see
 *     and toggle in the role editor today; no controller reads them.
 *
 * Granting one side should satisfy gate checks against the other side, so
 * the role editor's dotted catalog actually works without touching every
 * scattered `Gate::allows` site. `AppServiceProvider::boot` wires this into
 * a single `Gate::before` callback that re-checks the aliased name when the
 * primary check would otherwise fail.
 */
final class PermissionAliasMap
{
    /**
     * Each entry maps both directions — holding key OR value satisfies a
     * check for the other. Add new aliases here as catalogs converge.
     *
     * @var array<string, string>
     */
    private const MAP = [
        // ── Plans ─────────────────────────────────────────
        'plans_manage'                  => 'plans.list.view',
        'plans_create'                  => 'plans.create',
        'plans_edit'                    => 'plans.edit',
        'plans_destroy'                 => 'plans.destroy',
        'plans_active'                  => 'plans.activate',
        'plans_inactive'                => 'plans.deactivate',
        'plans_log'                     => 'plans.log.view',
        'plans_log_excel'               => 'plans.log.export',
        'plans_sms_log'                 => 'plans.sms_log.view',
        'plans_cash_edit'               => 'plans.cash.edit',
        'plans_cash_delete'             => 'plans.cash.delete',
        'plans_cash_edit_amount'        => 'plans.cash.edit_amount',
        'plans_cash_edit_date'          => 'plans.cash.edit_date',
        'plans_cash_edit_payment_mode'  => 'plans.cash.edit_payment_mode',
        'plans_edit_sold_by'            => 'plans.sold_by.edit',
        'plans_service_delete'          => 'plans.service.delete',
        'view_inactive_plans'           => 'plans.list.view_inactive',

        // ── Packages ──────────────────────────────────────
        'packages_manage'  => 'packages.detail.view',
        'packages_create'  => 'packages.create',
        'packages_edit'    => 'packages.edit',
        'packages_destroy' => 'packages.delete',
        'packages_active'  => 'packages.activate',
        'packages_inactive'=> 'packages.deactivate',

        // ── Discounts ─────────────────────────────────────
        // Master legacy gate (`discounts_manage`) maps to list-view because
        // that is the page-entry gate the SPA reads via usePermissions().has().
        'discounts_manage'        => 'discounts.list.view',
        'discounts_create'        => 'discounts.create',
        'discounts_edit'          => 'discounts.edit',
        'discounts_destroy'       => 'discounts.destroy',
        'discounts_active'        => 'discounts.activate',
        'discounts_inactive'      => 'discounts.deactivate',
        'discounts_allocate'      => 'discounts.allocate',
        'view_inactive_discounts' => 'discounts.list.view_inactive',
    ];

    /**
     * Lazy reverse-lookup cache. Built on first access so the constant
     * MAP above stays declarative.
     *
     * @var array<string, string>|null
     */
    private static ?array $reverse = null;

    /**
     * Return the aliased permission name for $ability, or null if there is
     * no alias. Lookup is bidirectional: legacy → dotted and dotted →
     * legacy both resolve.
     */
    public static function aliasFor(string $ability): ?string
    {
        if (array_key_exists($ability, self::MAP)) {
            return self::MAP[$ability];
        }

        if (self::$reverse === null) {
            self::$reverse = array_flip(self::MAP);
        }

        return self::$reverse[$ability] ?? null;
    }
}
