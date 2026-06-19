<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hide the 109 "decorative" permissions from the role editor (user decision,
 * 2026-06-20). Each is a switch that does NOTHING when toggled — no backend gate
 * and no SPA reference (verified by a quoted-token scan of backend/app +
 * frontend/src; the only 2 the scan flagged, appointment_reports_manage and
 * activity_logs_report, were made live by the report fix and are NOT in this
 * list). Container GROUPS that hold live children (dashboard/HR/voucher-type/
 * scheduling/management-dashboard) are deliberately EXCLUDED so their working
 * children stay grantable.
 *
 * "Hide" = status=0. It does NOT affect enforcement (Spatie checks by name, not
 * status) — existing role grants keep working; the switch just stops cluttering
 * the editor. Children are dropped by the matching status filter added to
 * RoleService::buildPermissionGroup in the same change.
 *
 * Fully reversible: snapshot the prior status, restore in down() (->delete(),
 * never truncate — truncate breaks migrate:rollback).
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private const HIDE = [
        'dashboard_appointment_by_status', 'dashboard_appointment_by_type', 'roles_destroy_bulk',
        'appointments_services', 'resourcerotas_manage', 'resourcerotas_create', 'resourcerotas_edit',
        'resourcerotas_active', 'resourcerotas_inactive', 'resourcerotas_destroy', 'resourcerotas_calender',
        'appointment_reports_general_report', 'appointment_reports_staff_appointment', 'operations_reports_dtr_report',
        'finance_general_revenue_reports_staff_wise_revenue', 'appointment_reports_general_summary_report',
        'product_transfer_manage', 'view_inactive_discounts', 'view_inactive_leads', 'view_inactive_patients',
        'view_inactive_payment_modes', 'view_inactive_plans', 'view_inactive_services', 'hr_documents_view',
        'leads.send_sms', 'business_closures.delete', 'voucher_types.list.view_inactive', 'cashflow.expense.resubmit',
        'inventory.brand.create', 'inventory.brand.edit', 'inventory.brand.delete', 'inventory.brand.toggle',
        'inventory.product.create', 'inventory.product.edit', 'inventory.product.delete', 'inventory.product.toggle',
        'inventory.product.sale_price', 'inventory.product.add_stock', 'inventory.product.stock_detail',
        'inventory.product.transfer', 'inventory.product.log', 'inventory.order.create', 'inventory.order.edit',
        'inventory.order.delete', 'inventory.order.refund', 'staff_targets.list.view', 'staff_targets.create',
        'staff_targets.edit', 'staff_targets.destroy', 'machine_types.list.view', 'machine_types.list.view_inactive',
        'machine_types.create', 'machine_types.activate', 'machine_types.deactivate', 'resource_types.list.view',
        'resource_types.create', 'resource_types.edit', 'resource_types.destroy', 'resource_types.activate',
        'resource_types.deactivate', 'pabao_records', 'pabao_records.view', 'logs', 'logs.view', 'activity_logs.view',
        'contact.view', 'leads_reports', 'leads_reports.view', 'hr_reports', 'hr_reports.view', 'staff_listing_reports',
        'staff_listing_reports.view', 'staff_revenue_reports', 'staff_revenue_reports.view', 'upselling_report.view',
        'consultant_revenue_report.view', 'finance_revenue_breakup_reports', 'finance_revenue_breakup_reports.view',
        'finance_ledger_reports', 'finance_ledger_reports.view', 'centers_reports', 'centers_reports.view',
        'marketing_reports', 'marketing_reports.view', 'consultations.image.view', 'consultations.image.upload',
        'consultations.image.destroy', 'consultations.measurement.view', 'consultations.measurement.create',
        'consultations.measurement.edit', 'consultations.medical_form.view', 'consultations.medical_form.create',
        'consultations.medical_form.edit', 'consultations.export_activity', 'consultations.patient_card.view',
        'consultations.plans.create', 'treatments.image.view', 'treatments.image.upload', 'treatments.image.destroy',
        'treatments.measurement.view', 'treatments.measurement.create', 'treatments.measurement.edit',
        'treatments.medical_form.view', 'treatments.medical_form.create', 'treatments.medical_form.edit',
        'treatments.view_activity', 'treatments.export_activity', 'treatments.patient_card.view', 'treatments.plans.create',
    ];

    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('permission_decorative_hide_backup')) {
            DB::statement('CREATE TABLE permission_decorative_hide_backup (
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

            foreach (self::HIDE as $name) {
                $row = DB::table('permissions')
                    ->where('name', $name)->where('guard_name', 'web')->where('status', 1)->first();
                if ($row === null) {
                    continue; // already hidden / absent on this DB — idempotent
                }

                DB::table('permission_decorative_hide_backup')->insert([
                    'permission_id' => $row->id,
                    'permission_name' => $name,
                    'prior_status' => (int) $row->status,
                    'ran_at' => $runAt,
                ]);
                DB::table('permissions')->where('id', $row->id)->update(['status' => 0]);
                $hidden++;
            }

            Log::info('decorative-perms: hidden from role editor', ['hidden' => $hidden]);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('permission_decorative_hide_backup')) {
            return;
        }

        DB::transaction(function (): void {
            foreach (DB::table('permission_decorative_hide_backup')->get() as $r) {
                DB::table('permissions')->where('id', $r->permission_id)->update(['status' => $r->prior_status]);
            }
            DB::table('permission_decorative_hide_backup')->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
