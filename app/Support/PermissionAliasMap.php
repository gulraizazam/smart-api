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
        //
        // THREE legacy catalogs feed into the dotted plans catalog:
        //   • `plans_*`         (parent 157, status=0) — global plans page
        //   • `patients_plan_*` (parent 169, status=1) — patient-detail
        //     "Plans" tab (Admin/Patients/PackagesController gates here)
        //   • `view_inactive_plans` (parent 457)       — inactive-rows filter
        //
        // Each dotted slug below lists every legacy slug it should satisfy
        // when granted, so a role with the dotted perm passes every
        // controller's `Gate::allows()` regardless of which legacy slug
        // that controller historically targeted.
        'plans.list.view'              => ['plans_manage', 'patients_plan_manage'],
        'plans.list.view_inactive'     => ['view_inactive_plans'],
        'plans.create'                 => ['plans_create', 'patients_plan_create'],
        'plans.edit'                   => ['plans_edit', 'patients_plan_edit'],
        'plans.destroy'                => ['plans_destroy', 'patients_plan_destroy'],
        'plans.activate'               => ['plans_active', 'patients_plan_active'],
        'plans.deactivate'             => ['plans_inactive', 'patients_plan_inactive'],
        // `plans_log` is the response-payload flag the SPA reads;
        // `patients_plan_log` is the actual endpoint gate in
        // Api\PlansController::planLog + Admin\Patients\PackagesController.
        // Granting the dotted slug must satisfy both.
        'plans.log.view'               => ['plans_log', 'patients_plan_log'],
        'plans.log.export'             => ['plans_log_excel', 'patients_plan_log_excel'],
        'plans.sms_log.view'           => ['plans_sms_log', 'patients_plan_sms_log'],
        'plans.cash.edit'              => ['plans_cash_edit', 'patients_plan_cash_edit'],
        'plans.cash.delete'            => ['plans_cash_delete', 'patients_plan_cash_delete'],
        'plans.cash.edit_amount'       => ['plans_cash_edit_amount', 'patients_plan_cash_edit_amount'],
        'plans.cash.edit_date'         => ['plans_cash_edit_date', 'patients_plan_cash_edit_date'],
        'plans.cash.edit_payment_mode' => ['plans_cash_edit_payment_mode', 'patients_plan_cash_edit_payment_mode'],
        'plans.sold_by.edit'           => ['plans_edit_sold_by'],
        'plans.service.delete'         => ['plans_service_delete', 'patients_plan_service_delete'],

        // ── Packages ──────────────────────────────────────
        'packages.detail.view' => ['packages_manage'],
        'packages.create'      => ['packages_create'],
        'packages.edit'        => ['packages_edit'],
        'packages.destroy'     => ['packages_destroy'],
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

        // ── Leads ─────────────────────────────────────────
        // Bridges Api\LeadsController's legacy Gate::allows() slugs to the new
        // dotted catalog. 2026_05_21_130000 deletes the legacy leads_* rows and
        // re-grants the dotted equivalents (its own authoritative map, lines
        // 58-68 of that migration). Without this bridge a role migrated to dotted
        // slugs 403s on the still-legacy controller gates — and during the
        // coexistence window a role still on legacy slugs passes the (few) dotted
        // gates the controller already uses. leads_manage historically guarded
        // BOTH the list and the detail view (needs the 1-to-many inverse below).
        'leads.list.view'          => ['leads_manage'],
        'leads.detail.view'        => ['leads_manage'],
        'leads.create'             => ['leads_create'],
        'leads.edit'               => ['leads_edit'],
        'leads.delete'             => ['leads_destroy'],
        'leads.import'             => ['leads_import'],
        'leads.export'             => ['leads_export'],
        'leads.update_status'      => ['leads_lead_status'],
        'leads.update_city'        => ['leads_city'],
        'leads.convert'            => ['leads_convert'],
        'leads.list.view_junk'     => ['leads_junk'],
        'leads.list.view_inactive' => ['view_inactive_leads'],
        // Legacy crm2 reveals the lead phone under the broad `contact` perm
        // (Blade `@can('contact')`); crm3 gates it on the granular
        // `leads.list.view_contact` (LeadResource). The module migration only
        // mirrored the grant ONCE at run time, so a `contact` grant added via
        // the role editor afterwards never reaches the lead phone. Bridge it
        // at run time, exactly like `patients.list.view_contact` below.
        'leads.list.view_contact'  => ['contact'],

        // ── Consultations ─────────────────────────────────
        // Same story as leads/patients: crm2's Blade appointment views reveal
        // the patient phone under the broad `contact` perm, while crm3 gates it
        // on `consultations.list.view_contact` (ConsultancyResource). Bridge the
        // legacy grant so a `contact` holder sees the consultation phone too.
        'consultations.list.view_contact' => ['contact'],

        // ── Blade-route bridges (go-live §5.4) ────────────
        // The legacy Blade admin routes (routes/web/admin-*.php) were re-pointed
        // to dotted slugs by the permission rollout. A live Blade user whose role
        // still holds only the legacy umbrella (X_manage) would 403 on those pages
        // until the rollout's mirror grants the dotted slug — and during the
        // coexistence window a dotted-only role would 403 on any still-legacy gate.
        // These bridges keep both directions working. All legacy umbrellas below
        // were verified present in the catalog.
        // NOTE: `business_closures.list.view` has NO legacy umbrella
        // (`business_closures_manage` does not exist — the Blade route was
        // previously UNGATED), so it CANNOT be bridged. Non-admins who relied on
        // the ungated page must be granted the dotted slug via the role editor /
        // rollout mirror. Flagged for go-live, not fixable here.
        'services.list.view'          => ['services_manage'],
        'packages.list.view'          => ['packages_manage'],
        'packages.sort'               => ['packages_edit'],
        'bundles.list.view'           => ['packages_manage'],
        'bundles.sort'                => ['packages_edit'],
        'invoices.list.view'          => ['invoices_manage'],
        'patients.list.view'          => ['patients_manage'],
        // Legacy crm2 grants the broad `contact` perm for phone/PII visibility;
        // crm3 gates patient-list phone on the granular `patients.list.view_contact`.
        // Bridge them so a crm2 `contact` grant still reveals patient phone in
        // crm3 (PatientContactPermissionTest pins this). Added 2026-06-03.
        'patients.list.view_contact'  => ['contact'],
        'scheduling_shifts.list.view' => ['resourcerotas_manage'],
        'voucher_types.list.view'     => ['voucher_types_manage'],
        'vouchers.list.view'          => ['vouchers_manage'],
        'cashflow.manage'             => ['cashflow_manage'],

        // ── Cashflow (granular) ───────────────────────────
        // crm3 gates on the dotted slugs; crm2's full cashflow module gates on
        // the snake_case ones from the legacy CashflowPermissionsSeeder. Mapped
        // PAIR-BY-PAIR against both live catalogs (NOT a blind dot->underscore
        // transform — four pairs differ structurally, flagged inline). This lets
        // a crm2 (snake) grant satisfy the matching crm3 (dotted) gate + the SPA
        // auth payload, so cashflow behaves like every other module during
        // coexistence. NOTE: cashflow.staff_transfer.* (staff<->staff handovers)
        // is a crm3-only feature with NO snake_case counterpart on prod, so it
        // is intentionally left unmapped (a bridge to a non-existent slug would
        // be inert anyway — Gate::before swallows PermissionDoesNotExist).
        'cashflow.audit.view'                => ['cashflow_audit_view'],
        'cashflow.category.manage'           => ['cashflow_category_manage'],
        'cashflow.dashboard.view'            => ['cashflow_dashboard'],            // snake has no _view suffix
        'cashflow.expense.approve'           => ['cashflow_expense_approve'],
        'cashflow.expense.create'            => ['cashflow_expense_create'],
        'cashflow.expense.duplicate'         => ['cashflow_expense_duplicate'],
        'cashflow.expense.edit'              => ['cashflow_expense_edit'],
        'cashflow.expense.export'            => ['cashflow_expense_export'],
        'cashflow.expense.reject'            => ['cashflow_expense_reject'],
        'cashflow.expense.resubmit'          => ['cashflow_expense_resubmit'],
        'cashflow.expense.unflag'            => ['cashflow_expense_unflag'],
        'cashflow.expense.view'              => ['cashflow_expense_view'],
        'cashflow.expense.void'              => ['cashflow_expense_void'],
        'cashflow.fdm.view'                  => ['cashflow_fdm_view'],
        'cashflow.period.lock'               => ['cashflow_period_lock'],
        'cashflow.pool.manage'               => ['cashflow_pool_manage'],
        'cashflow.reports.export'            => ['cashflow_reports_export'],
        'cashflow.reports.view'              => ['cashflow_reports'],              // snake has no _view suffix
        'cashflow.settings.manage'           => ['cashflow_settings'],             // snake has no _manage suffix
        'cashflow.staff_advance.create'      => ['cashflow_staff_advance_create'],
        'cashflow.staff_advance.edit'        => ['cashflow_staff_advance_edit'],
        'cashflow.staff_advance.view'        => ['cashflow_staff_advance_view'],
        'cashflow.staff_advance.void'        => ['cashflow_staff_advance_void'],
        'cashflow.staff_return.create'       => ['cashflow_staff_return_create'],
        'cashflow.staff_return.void'         => ['cashflow_staff_return_void'],
        'cashflow.transfer.create'           => ['cashflow_transfer_create'],
        'cashflow.transfer.edit'             => ['cashflow_transfer_edit'],
        'cashflow.transfer.view'             => ['cashflow_transfer_view'],
        'cashflow.transfer.void'             => ['cashflow_transfer_void'],
        'cashflow.vendor.create'             => ['cashflow_vendor_create'],
        'cashflow.vendor.deliver'            => ['cashflow_vendor_deliver'],
        'cashflow.vendor.edit'               => ['cashflow_vendor_edit'],
        'cashflow.vendor.ledger.export'      => ['cashflow_vendor_ledger_export'],
        'cashflow.vendor.ledger.view'        => ['cashflow_vendor_ledger_view'],
        'cashflow.vendor.manage'             => ['cashflow_vendor_manage'],
        'cashflow.vendor.request'            => ['cashflow_vendor_request'],
        'cashflow.vendor.toggle'             => ['cashflow_vendor_toggle'],
        'cashflow.vendor.transaction.create' => ['cashflow_vendor_transaction'],   // bare = "Record Vendor Purchases"
        'cashflow.vendor.transaction.delete' => ['cashflow_vendor_transaction_delete'],
        'cashflow.vendor.transaction.edit'   => ['cashflow_vendor_transaction_edit'],
        'cashflow.vendor.view'               => ['cashflow_vendor_view'],
    ];

    /**
     * Lazy inverse: legacy slug → list of dotted slugs. Built once per request.
     *
     * @var array<string, list<string>>|null
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
                    // 1-to-many: a single legacy slug can alias to MORE than one
                    // dotted slug (e.g. leads_manage → leads.list.view +
                    // leads.detail.view). The docblock always promised this; the
                    // earlier `= $dotted` kept only the last and silently dropped
                    // the rest, so a role holding only the OTHER dotted slug 403'd.
                    $legacyToDotted[$legacy][] = $dotted;
                }
            }
            self::$legacyToDotted = $legacyToDotted;
        }

        return self::$legacyToDotted[$ability] ?? [];
    }
}
