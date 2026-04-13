<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function foreignKeyExists(string $table, string $name): bool
    {
        $rows = DB::select(
            "SELECT 1 FROM information_schema.table_constraints WHERE constraint_schema = DATABASE() AND table_name = ? AND constraint_name = ? AND constraint_type = 'FOREIGN KEY' LIMIT 1",
            [$table, $name]
        );
        return ! empty($rows);
    }

    private function addFk(string $table, string $column, string $refTable, string $name, string $onDelete): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasTable($refTable) || ! Schema::hasColumn($table, $column)) {
            return;
        }
        if ($this->foreignKeyExists($table, $name)) {
            return;
        }
        try {
            Schema::table($table, function (Blueprint $t) use ($column, $refTable, $name, $onDelete) {
                $t->foreign($column, $name)->references('id')->on($refTable)->onDelete($onDelete);
            });
        } catch (\Throwable $e) {
            \Log::warning("Skipping FK {$name} on {$table}.{$column}: " . $e->getMessage());
        }
    }

    private function dropFk(string $table, string $name): void
    {
        if (! Schema::hasTable($table) || ! $this->foreignKeyExists($table, $name)) {
            return;
        }
        try {
            Schema::table($table, fn (Blueprint $t) => $t->dropForeign($name));
        } catch (\Throwable $e) {
            \Log::warning("Skipping dropForeign {$name} on {$table}: " . $e->getMessage());
        }
    }

    private function safeStatement(string $sql): void
    {
        try {
            DB::statement($sql);
        } catch (\Throwable $e) {
            \Log::warning('Statement failed: ' . $e->getMessage());
        }
    }

    public function up(): void
    {
        $this->safeStatement('UPDATE invoices SET appointment_id = NULL WHERE appointment_id IS NOT NULL AND appointment_id NOT IN (SELECT id FROM appointments)');
        $this->safeStatement('UPDATE package_bundles SET package_id = NULL WHERE package_id IS NOT NULL AND package_id NOT IN (SELECT id FROM packages)');
        $this->safeStatement('UPDATE package_bundles SET bundle_id = NULL WHERE bundle_id IS NOT NULL AND bundle_id NOT IN (SELECT id FROM bundles)');
        $this->safeStatement('UPDATE package_services SET package_id = NULL WHERE package_id IS NOT NULL AND package_id NOT IN (SELECT id FROM packages)');
        $this->safeStatement('UPDATE sms_logs SET appointment_id = NULL WHERE appointment_id IS NOT NULL AND appointment_id NOT IN (SELECT id FROM appointments)');
        $this->safeStatement("DELETE FROM invoice_details WHERE invoice_id NOT IN (SELECT id FROM invoices)");
        $this->safeStatement("DELETE FROM lead_comments WHERE lead_id NOT IN (SELECT id FROM leads)");

        $this->addFk('invoice_details', 'invoice_id', 'invoices', 'fk_invoice_details_invoice', 'cascade');
        $this->addFk('lead_comments', 'lead_id', 'leads', 'fk_lead_comments_lead', 'cascade');
        $this->addFk('invoices', 'appointment_id', 'appointments', 'fk_invoices_appointment', 'set null');
        $this->addFk('invoices', 'patient_id', 'users', 'fk_invoices_patient', 'set null');
        $this->addFk('package_advances', 'package_id', 'packages', 'fk_pkg_advances_package', 'set null');
        $this->addFk('package_bundles', 'package_id', 'packages', 'fk_pkg_bundles_package', 'set null');
        $this->addFk('package_services', 'package_id', 'packages', 'fk_pkg_services_package', 'set null');
        $this->addFk('leads', 'patient_id', 'users', 'fk_leads_patient', 'set null');
        $this->addFk('sms_logs', 'appointment_id', 'appointments', 'fk_sms_logs_appointment', 'set null');
    }

    public function down(): void
    {
        $this->dropFk('invoice_details', 'fk_invoice_details_invoice');
        $this->dropFk('lead_comments', 'fk_lead_comments_lead');
        $this->dropFk('invoices', 'fk_invoices_appointment');
        $this->dropFk('invoices', 'fk_invoices_patient');
        $this->dropFk('package_advances', 'fk_pkg_advances_package');
        $this->dropFk('package_bundles', 'fk_pkg_bundles_package');
        $this->dropFk('package_services', 'fk_pkg_services_package');
        $this->dropFk('leads', 'fk_leads_patient');
        $this->dropFk('sms_logs', 'fk_sms_logs_appointment');
    }
};
