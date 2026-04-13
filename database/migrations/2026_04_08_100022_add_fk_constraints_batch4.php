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
            \Log::warning("Skipping FK {$name}: " . $e->getMessage());
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
            \Log::warning("Skipping dropForeign {$name}: " . $e->getMessage());
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
        $this->safeStatement('UPDATE package_advances SET payment_mode_id = NULL WHERE payment_mode_id = 0');
        $this->safeStatement('UPDATE package_advances SET appointment_id = NULL WHERE appointment_id IS NOT NULL AND appointment_id NOT IN (SELECT id FROM appointments)');
        $this->safeStatement('UPDATE package_advances SET invoice_id = NULL WHERE invoice_id IS NOT NULL AND invoice_id NOT IN (SELECT id FROM invoices)');
        $this->safeStatement('UPDATE invoice_details SET package_service_id = NULL WHERE package_service_id IS NOT NULL AND package_service_id NOT IN (SELECT id FROM package_services)');

        $this->addFk('package_advances', 'patient_id', 'users', 'fk_pa_patient', 'set null');
        $this->addFk('package_advances', 'account_id', 'accounts', 'fk_pa_account', 'set null');
        $this->addFk('package_advances', 'appointment_id', 'appointments', 'fk_pa_appointment', 'set null');
        $this->addFk('package_advances', 'location_id', 'locations', 'fk_pa_location', 'set null');
        $this->addFk('package_advances', 'invoice_id', 'invoices', 'fk_pa_invoice', 'set null');
        $this->addFk('package_advances', 'payment_mode_id', 'payment_modes', 'fk_pa_payment_mode', 'set null');
        $this->addFk('package_advances', 'appointment_type_id', 'appointment_types', 'fk_pa_appt_type', 'set null');
        $this->addFk('package_advances', 'created_by', 'users', 'fk_pa_creator', 'set null');

        $this->addFk('invoices', 'account_id', 'accounts', 'fk_invoices_account', 'restrict');
        $this->addFk('invoices', 'invoice_status_id', 'invoice_statuses', 'fk_invoices_status', 'set null');
        $this->addFk('invoices', 'created_by', 'users', 'fk_invoices_creator', 'set null');
        $this->addFk('invoices', 'location_id', 'locations', 'fk_invoices_location', 'restrict');
        $this->addFk('invoices', 'doctor_id', 'users', 'fk_invoices_doctor', 'restrict');

        $this->addFk('invoice_details', 'package_id', 'packages', 'fk_inv_details_package', 'set null');
        $this->addFk('invoice_details', 'package_service_id', 'package_services', 'fk_inv_details_pkg_svc', 'set null');
    }

    public function down(): void
    {
        $this->dropFk('package_advances', 'fk_pa_patient');
        $this->dropFk('package_advances', 'fk_pa_account');
        $this->dropFk('package_advances', 'fk_pa_appointment');
        $this->dropFk('package_advances', 'fk_pa_location');
        $this->dropFk('package_advances', 'fk_pa_invoice');
        $this->dropFk('package_advances', 'fk_pa_payment_mode');
        $this->dropFk('package_advances', 'fk_pa_appt_type');
        $this->dropFk('package_advances', 'fk_pa_creator');

        $this->dropFk('invoices', 'fk_invoices_account');
        $this->dropFk('invoices', 'fk_invoices_status');
        $this->dropFk('invoices', 'fk_invoices_creator');
        $this->dropFk('invoices', 'fk_invoices_location');
        $this->dropFk('invoices', 'fk_invoices_doctor');

        $this->dropFk('invoice_details', 'fk_inv_details_package');
        $this->dropFk('invoice_details', 'fk_inv_details_pkg_svc');
    }
};
