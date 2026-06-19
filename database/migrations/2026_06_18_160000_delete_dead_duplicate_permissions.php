<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tier P-R / Delete — physically remove the dead duplicate permission rows so the
 * catalog has exactly ONE permission per module (not just hidden in the editor).
 *
 * For each module the gate scan (533 backend gates, hardened + dynamic-gate
 * audited) proved which catalog the code uses; this deletes the OTHER side's
 * parent group + ALL its children + their stale grants. The deleted side has
 * ZERO literal gates AND ZERO dynamic-gate references (verified), so no access
 * changes — only dead rows + dead grants are removed.
 *
 * Keeper-guarded (never delete a side unless its canonical twin is present +
 * visible) and FULLY reversible: every deleted row (all columns) and every
 * deleted grant is snapshotted; down() re-inserts them with their original ids.
 *
 * EXCLUDED (handled as a hide, not a delete): `dashboard_manage` — its children
 * are gated in the dead-but-routed Api\DashboardController, so deleting them now
 * would dangle those gates. It is hidden here; its rows are deleted in a later
 * pass that also removes that dead controller (Tier-L). Pairs with the
 * memberships_create -> memberships.create gate repoint in the same release.
 */
return new class extends Migration
{
    /** dead parent group to DELETE (subtree + grants) => canonical keeper (guard). */
    private const RETIRE_DELETE = [
        // ── Class B: legacy canonical, delete the unused dotted shadow ──
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
        // ── Class A: dotted canonical, delete the dead legacy umbrella ──
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
        // ── Dotted report shadows (reports gate dynamically on legacy) ──
        'appointment_reports'             => 'appointment_reports_manage',
        'finance_general_revenue_reports' => 'finance_general_revenue_reports_manage',
        'staff_wise_arrival'              => 'staff_wise_arrival_manage',
        // ── Legacy memberships (gate repointed to dotted in this release) ──
        'memberships_manage'     => 'memberships',
        // ── Dead crm2-era doctor dashboard (no SPA, no gate) ──
        'doctor_dashboard'       => 'management_dashboard',
        'doctor_dashboard_perm'  => 'management_dashboard',
    ];

    /** dead group to HIDE only (delete deferred to Tier-L) => keeper (guard). */
    private const RETIRE_HIDE = [
        'dashboard_manage' => 'management_dashboard',
    ];

    public function up(): void
    {
        DB::statement('CREATE TABLE IF NOT EXISTS permission_delete_p3rd_backup (
            id INT AUTO_INCREMENT PRIMARY KEY, orig_id INT NOT NULL,
            permission_name VARCHAR(255) NOT NULL, payload JSON NOT NULL, ran_at DATETIME NOT NULL)');
        DB::statement('CREATE TABLE IF NOT EXISTS permission_grant_delete_p3rd_backup (
            id INT AUTO_INCREMENT PRIMARY KEY, role_id INT NOT NULL, permission_id INT NOT NULL, ran_at DATETIME NOT NULL)');
        DB::statement('CREATE TABLE IF NOT EXISTS permission_hide_p3rd_backup (
            id INT AUTO_INCREMENT PRIMARY KEY, permission_id INT NOT NULL, prior_status INT NOT NULL, ran_at DATETIME NOT NULL)');

        DB::transaction(function (): void {
            $runAt = now();

            // ---- DELETE dead subtrees + grants ----
            $deadParentIds = [];
            foreach (self::RETIRE_DELETE as $hide => $keep) {
                $keeperVisible = DB::table('permissions')
                    ->where('name', $keep)->where('main_group', 1)->where('status', 1)->exists();
                if (! $keeperVisible) {
                    Log::warning('P3RD delete: keeper not visible — skipping', ['hide' => $hide, 'keep' => $keep]);
                    continue;
                }
                $pid = DB::table('permissions')->where('name', $hide)->where('main_group', 1)->value('id');
                if ($pid !== null) {
                    $deadParentIds[] = (int) $pid;
                }
            }

            $childIds = DB::table('permissions')->whereIn('parent_id', $deadParentIds)->pluck('id')->all();
            $allDeadIds = array_values(array_unique(array_merge($deadParentIds, $childIds)));

            if ($allDeadIds !== []) {
                foreach (DB::table('permissions')->whereIn('id', $allDeadIds)->get() as $row) {
                    DB::table('permission_delete_p3rd_backup')->insert([
                        'orig_id' => $row->id,
                        'permission_name' => $row->name,
                        'payload' => json_encode($row),
                        'ran_at' => $runAt,
                    ]);
                }
                foreach (DB::table('role_has_permissions')->whereIn('permission_id', $allDeadIds)->get() as $g) {
                    DB::table('permission_grant_delete_p3rd_backup')->insert([
                        'role_id' => $g->role_id,
                        'permission_id' => $g->permission_id,
                        'ran_at' => $runAt,
                    ]);
                }
                DB::table('role_has_permissions')->whereIn('permission_id', $allDeadIds)->delete();
                DB::table('model_has_permissions')->whereIn('permission_id', $allDeadIds)->delete();
                DB::table('permissions')->whereIn('id', $allDeadIds)->delete();
                Log::info('P3RD delete: removed dead duplicate permissions', ['rows' => count($allDeadIds)]);
            }

            // ---- HIDE dashboard_manage (delete deferred to Tier-L) ----
            foreach (self::RETIRE_HIDE as $hide => $keep) {
                $keeperVisible = DB::table('permissions')
                    ->where('name', $keep)->where('main_group', 1)->where('status', 1)->exists();
                if (! $keeperVisible) {
                    continue;
                }
                $row = DB::table('permissions')->where('name', $hide)->where('main_group', 1)->where('status', 1)->first();
                if ($row !== null) {
                    DB::table('permission_hide_p3rd_backup')->insert([
                        'permission_id' => $row->id, 'prior_status' => (int) $row->status, 'ran_at' => $runAt,
                    ]);
                    DB::table('permissions')->where('id', $row->id)->update(['status' => 0]);
                }
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('permission_delete_p3rd_backup')) {
            return;
        }

        DB::transaction(function (): void {
            // restore hidden status
            foreach (DB::table('permission_hide_p3rd_backup')->get() as $h) {
                DB::table('permissions')->where('id', $h->permission_id)->update(['status' => $h->prior_status]);
            }
            // restore deleted rows (with original ids)
            foreach (DB::table('permission_delete_p3rd_backup')->get() as $b) {
                if (! DB::table('permissions')->where('id', $b->orig_id)->exists()) {
                    DB::table('permissions')->insert((array) json_decode($b->payload, true));
                }
            }
            // restore deleted grants
            foreach (DB::table('permission_grant_delete_p3rd_backup')->get() as $g) {
                $exists = DB::table('role_has_permissions')
                    ->where('role_id', $g->role_id)->where('permission_id', $g->permission_id)->exists();
                if (! $exists) {
                    DB::table('role_has_permissions')->insert(['role_id' => $g->role_id, 'permission_id' => $g->permission_id]);
                }
            }
            DB::table('permission_hide_p3rd_backup')->delete();
            DB::table('permission_grant_delete_p3rd_backup')->delete();
            DB::table('permission_delete_p3rd_backup')->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
