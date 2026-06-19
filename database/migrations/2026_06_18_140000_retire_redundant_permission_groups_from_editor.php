<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tier P-R / R1 — retire the redundant duplicate permission GROUP from the role
 * editor for 40 of the 48 legacy `X_manage` ↔ dotted `X` module pairs.
 *
 * The role editor renders every parent group with `main_group=1 AND status=1`
 * (RoleService::buildPermissionGroup) plus its children. A full parallel dotted
 * catalog was built beside the legacy one, so 48 modules show TWO groups each.
 * For 40 of them the code uses exactly one side; the other is dead.
 *
 * Evidence (no guess): a hardened 533-slug scan of every backend gate
 * (Gate::allows/denies/any/check, ->can/cannot, hasPermissionTo, authorize,
 * policies, `permission:`/`role_or_permission:` middleware, @can/@haspermission)
 * + the SPA permission checks classified each module. The side hidden here has
 * **ZERO backend gates AND ZERO SPA references** (proven, robust to the hardened
 * re-scan), so hiding it changes NO access — it only removes the duplicate group
 * from the editor. The 3 "mixed" (memberships/treatments/consultations) and 5
 * "neither-gated" report modules are intentionally NOT touched (separate pass).
 *
 * Reversible: only flips status 1→0 (no rows/grants deleted); snapshot-backed
 * down() restores the prior status. Guard: a group is hidden ONLY if its KEEPER
 * twin is present + visible, so no module is ever left with zero groups.
 */
return new class extends Migration
{
    /**
     * redundant group to HIDE => its canonical KEEPER (must remain visible).
     * Class A (dotted canonical → hide legacy) + Class B (legacy canonical → hide
     * the empty dotted shadow). Derived programmatically from the gate scan.
     *
     * @var array<string, string>
     */
    private const RETIRE = [
        // ── Class B: legacy canonical, hide the unused dotted shadow ──
        'permissions'            => 'permissions_manage',
        'roles'                  => 'roles_manage',
        'user_types'             => 'user_types_manage',
        'users'                  => 'users_manage',
        'regions'                => 'regions_manage',
        'cities'                 => 'cities_manage',
        'locations'              => 'locations_manage',
        'appointment_statuses'   => 'appointment_statuses_manage',
        'lead_sources'           => 'lead_sources_manage',
        'lead_statuses'          => 'lead_statuses_manage',
        'settings'               => 'settings_manage',
        'sms_templates'          => 'sms_templates_manage',
        'doctors'                => 'doctors_manage',
        'resources'              => 'resources_manage',
        'custom_forms'           => 'custom_forms_manage',
        'custom_form_feedbacks'  => 'custom_form_feedbacks_manage',
        'user_operator_settings' => 'user_operator_settings_manage',
        'operations_reports'     => 'operations_reports_manage',
        'centre_targets'         => 'centre_targets_manage',
        'towns'                  => 'towns_manage',
        'conversion_report'      => 'conversion_report_manage',
        'non_converted_customers' => 'non_converted_customers_manage',
        'follow_up'              => 'follow_up_manage',
        'inventory_report'       => 'inventory_report_manage',
        'feedbacks'              => 'feedbacks_manage',
        'google_reviews'         => 'google_reviews_manage',
        'hr'                     => 'hr_manage',
        // ── Class A: dotted canonical, hide the dead legacy umbrella ──
        'services_manage'        => 'services',
        'leads_manage'           => 'leads',
        'discounts_manage'       => 'discounts',
        'payment_modes_manage'   => 'payment_modes',
        'plans_manage'           => 'plans',
        'invoices_manage'        => 'invoices',
        'refunds_manage'         => 'refunds',
        'patients_manage'        => 'patients',
        'packages_manage'        => 'packages',
        'inventory_manage'       => 'inventory',
        'voucher_types_manage'   => 'voucher_types',
        'vouchers_manage'        => 'vouchers',
        'cashflow_manage'        => 'cashflow',
    ];

    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('permission_group_retire_p3r_backup')) {
            DB::statement('CREATE TABLE permission_group_retire_p3r_backup (
                id INT AUTO_INCREMENT PRIMARY KEY,
                permission_id INT NOT NULL,
                permission_name VARCHAR(255) NOT NULL,
                prior_status INT NOT NULL,
                ran_at DATETIME NOT NULL,
                INDEX (permission_id)
            )');
        }

        DB::transaction(function (): void {
            $runAt = now();
            $hidden = 0;

            foreach (self::RETIRE as $hide => $keep) {
                // GUARD: never hide a group unless its canonical twin stays visible.
                $keeperVisible = DB::table('permissions')
                    ->where('name', $keep)->where('main_group', 1)->where('status', 1)->exists();
                if (! $keeperVisible) {
                    Log::warning('P3R: keeper not visible — skipping hide', ['hide' => $hide, 'keep' => $keep]);
                    continue;
                }

                $row = DB::table('permissions')
                    ->where('name', $hide)->where('main_group', 1)->where('status', 1)->first();
                if ($row === null) {
                    continue; // already hidden / absent on this DB — idempotent
                }

                DB::table('permission_group_retire_p3r_backup')->insert([
                    'permission_id' => $row->id,
                    'permission_name' => $hide,
                    'prior_status' => (int) $row->status,
                    'ran_at' => $runAt,
                ]);
                DB::table('permissions')->where('id', $row->id)->update(['status' => 0]);
                $hidden++;
            }

            Log::info('P3R: retired redundant permission groups from the editor', ['hidden' => $hidden]);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** Restore the prior status of exactly the groups this migration hid. */
    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('permission_group_retire_p3r_backup')) {
            return;
        }

        DB::transaction(function (): void {
            foreach (DB::table('permission_group_retire_p3r_backup')->get() as $r) {
                DB::table('permissions')->where('id', $r->permission_id)->update(['status' => $r->prior_status]);
            }
            DB::table('permission_group_retire_p3r_backup')->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
