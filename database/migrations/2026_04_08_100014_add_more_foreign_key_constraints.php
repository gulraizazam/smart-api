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
        $this->safeStatement('UPDATE packages SET appointment_id = NULL WHERE appointment_id IS NOT NULL AND appointment_id NOT IN (SELECT id FROM appointments)');
        $this->safeStatement('UPDATE leads SET location_id = NULL WHERE location_id IS NOT NULL AND location_id NOT IN (SELECT id FROM locations)');
        $this->safeStatement('ALTER TABLE leads MODIFY location_id INT(10) UNSIGNED NULL');

        $this->addFk('packages', 'patient_id', 'users', 'fk_packages_patient', 'set null');
        $this->addFk('packages', 'location_id', 'locations', 'fk_packages_location', 'set null');
        $this->addFk('packages', 'appointment_id', 'appointments', 'fk_packages_appointment', 'set null');

        $this->addFk('leads', 'service_id', 'services', 'fk_leads_service', 'set null');
        $this->addFk('leads', 'location_id', 'locations', 'fk_leads_location', 'set null');
        $this->addFk('leads', 'lead_source_id', 'lead_sources', 'fk_leads_source', 'set null');
        $this->addFk('leads', 'lead_status_id', 'lead_statuses', 'fk_leads_status', 'set null');
        $this->addFk('leads', 'created_by', 'users', 'fk_leads_creator', 'set null');

        $this->addFk('invoice_details', 'service_id', 'services', 'fk_invoice_details_service', 'cascade');
        $this->addFk('package_bundles', 'bundle_id', 'bundles', 'fk_pkg_bundles_bundle', 'set null');
    }

    public function down(): void
    {
        $this->dropFk('packages', 'fk_packages_patient');
        $this->dropFk('packages', 'fk_packages_location');
        $this->dropFk('packages', 'fk_packages_appointment');
        $this->dropFk('leads', 'fk_leads_service');
        $this->dropFk('leads', 'fk_leads_location');
        $this->dropFk('leads', 'fk_leads_source');
        $this->dropFk('leads', 'fk_leads_status');
        $this->dropFk('leads', 'fk_leads_creator');
        $this->dropFk('invoice_details', 'fk_invoice_details_service');
        $this->dropFk('package_bundles', 'fk_pkg_bundles_bundle');

        $this->safeStatement('ALTER TABLE leads MODIFY location_id INT(11) NULL');
    }
};
