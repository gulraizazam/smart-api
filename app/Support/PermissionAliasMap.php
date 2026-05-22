<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Many-to-many alias map between the legacy snake_case permission catalog
 * (`plans_cash_edit_amount`, `patients_plan_log`, `packages_manage`, …) and
 * the new dotted catalog (`plans.cash.edit_amount`, `plans.log.view`,
 * `packages.detail.view`, …) that the role editor surfaces.
 *
 * Some dotted slugs collapse two legacy gates that historically guarded the
 * same UI action from different angles — `plans.log.view` grants both
 * `plans_log` (the response-payload flag) and `patients_plan_log` (the
 * endpoint gate). One key, many values, so the alias data structure has to
 * support 1-to-many in both directions.
 *
 * Why this exists: the role editor only renders / persists the dotted
 * catalog (id 909 / 901 / 978 etc., `status=1`). Every `Gate::allows()` in
 * the codebase still references the legacy slug. Without this bridge,
 * granting either side via the editor leaves the other side's checks
 * failing. `AppServiceProvider::boot` wires this into a `Gate::before`
 * callback that re-runs the check against every aliased name when the
 * primary check would otherwise fail.
 */
final class PermissionAliasMap
{
    /**
     * Canonical map: each dotted slug → list of legacy slugs it aliases.
     * Single source of truth — both directions derive from this. The
     * inverse map (legacy → dotted) is built on first access.
     *
     * @var array<string, list<string>>
     */
    private const DOTTED_TO_LEGACY = [
        // ── Plans ─────────────────────────────────────────
        'plans.list.view'              => ['plans_manage'],
        'plans.list.view_inactive'     => ['view_inactive_plans'],
        'plans.create'                 => ['plans_create'],
        'plans.edit'                   => ['plans_edit'],
        'plans.destroy'                => ['plans_destroy'],
        'plans.activate'               => ['plans_active'],
        'plans.deactivate'             => ['plans_inactive'],
        // `plans_log` is the response-payload flag the SPA reads;
        // `patients_plan_log` is the actual endpoint gate in
        // Api\PlansController::planLog + Admin\Patients\PackagesController.
        // Granting the dotted slug must satisfy both.
        'plans.log.view'               => ['plans_log', 'patients_plan_log'],
        'plans.log.export'             => ['plans_log_excel', 'patients_plan_log_excel'],
        'plans.sms_log.view'           => ['plans_sms_log', 'patients_plan_sms_log'],
        'plans.cash.edit'              => ['plans_cash_edit'],
        'plans.cash.delete'            => ['plans_cash_delete'],
        'plans.cash.edit_amount'       => ['plans_cash_edit_amount'],
        'plans.cash.edit_date'         => ['plans_cash_edit_date'],
        'plans.cash.edit_payment_mode' => ['plans_cash_edit_payment_mode'],
        'plans.sold_by.edit'           => ['plans_edit_sold_by'],
        'plans.service.delete'         => ['plans_service_delete'],

        // ── Packages ──────────────────────────────────────
        'packages.detail.view' => ['packages_manage'],
        'packages.create'      => ['packages_create'],
        'packages.edit'        => ['packages_edit'],
        'packages.delete'      => ['packages_destroy'],
        'packages.activate'    => ['packages_active'],
        'packages.deactivate'  => ['packages_inactive'],

        // ── Discounts ─────────────────────────────────────
        'discounts.list.view'          => ['discounts_manage'],
        'discounts.list.view_inactive' => ['view_inactive_discounts'],
        'discounts.create'             => ['discounts_create'],
        'discounts.edit'               => ['discounts_edit'],
        'discounts.destroy'            => ['discounts_destroy'],
        'discounts.activate'           => ['discounts_active'],
        'discounts.deactivate'         => ['discounts_inactive'],
        'discounts.allocate'           => ['discounts_allocate'],
    ];

    /**
     * Lazy inverse: legacy slug → dotted slug. Built once per request.
     *
     * @var array<string, string>|null
     */
    private static ?array $legacyToDotted = null;

    /**
     * Return every other permission name that should be checked against
     * $user when `$ability` is requested. Empty list = no aliases.
     *
     *   aliasesFor('plans_cash_edit')   → ['plans.cash.edit']
     *   aliasesFor('plans.cash.edit')   → ['plans_cash_edit']
     *   aliasesFor('plans.log.view')    → ['plans_log', 'patients_plan_log']
     *   aliasesFor('patients_plan_log') → ['plans.log.view']
     *
     * The returned list never includes $ability itself, so the caller can
     * iterate without worrying about an infinite Gate::before loop.
     *
     * @return list<string>
     */
    public static function aliasesFor(string $ability): array
    {
        if (isset(self::DOTTED_TO_LEGACY[$ability])) {
            return self::DOTTED_TO_LEGACY[$ability];
        }

        if (self::$legacyToDotted === null) {
            $legacyToDotted = [];
            foreach (self::DOTTED_TO_LEGACY as $dotted => $legacies) {
                foreach ($legacies as $legacy) {
                    $legacyToDotted[$legacy] = $dotted;
                }
            }
            self::$legacyToDotted = $legacyToDotted;
        }

        return isset(self::$legacyToDotted[$ability])
            ? [self::$legacyToDotted[$ability]]
            : [];
    }
}
