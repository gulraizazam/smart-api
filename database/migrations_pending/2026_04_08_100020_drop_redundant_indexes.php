<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $index]
        );
        return ! empty($rows);
    }

    private function dropRaw(string $table, string $index): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $index)) {
            return;
        }
        try {
            DB::statement("DROP INDEX `{$index}` ON `{$table}`");
        } catch (\Throwable $e) {
            \Log::warning("Skipping DROP INDEX {$index} on {$table}: " . $e->getMessage());
        }
    }

    private function addRaw(string $table, string $index, string $cols): void
    {
        if (! Schema::hasTable($table) || $this->indexExists($table, $index)) {
            return;
        }
        try {
            DB::statement("CREATE INDEX `{$index}` ON `{$table}`({$cols})");
        } catch (\Throwable $e) {
            \Log::warning("Skipping CREATE INDEX {$index} on {$table}: " . $e->getMessage());
        }
    }

    public function up(): void
    {
        $this->dropRaw('package_advances', 'package_advances_patient_id_foreign');
        $this->dropRaw('package_advances', 'package_advances_account_id_foreign');
        $this->dropRaw('package_advances', 'patient_balances_appointment');
        $this->dropRaw('leads_services', 'leads_services_lead_id_foreign');
        $this->dropRaw('package_services', 'package_services_package_id_foreign');
        $this->dropRaw('users', 'users_account');
        $this->dropRaw('packages', 'packages_account_id_foreign');
        $this->dropRaw('appointments', 'appointments_patient_id_foreign');
        $this->dropRaw('appointments', 'appointments_location_id_foreign');
        $this->dropRaw('appointments', 'appointments_appointment_status_id_foreign');
        $this->dropRaw('appointments', 'appointments_account');
    }

    public function down(): void
    {
        $this->addRaw('package_advances', 'package_advances_patient_id_foreign', 'patient_id');
        $this->addRaw('package_advances', 'package_advances_account_id_foreign', 'account_id');
        $this->addRaw('package_advances', 'patient_balances_appointment', 'appointment_id');
        $this->addRaw('leads_services', 'leads_services_lead_id_foreign', 'lead_id');
        $this->addRaw('package_services', 'package_services_package_id_foreign', 'package_id');
        $this->addRaw('users', 'users_account', 'account_id');
        $this->addRaw('packages', 'packages_account_id_foreign', 'account_id');
        $this->addRaw('appointments', 'appointments_patient_id_foreign', 'patient_id');
        $this->addRaw('appointments', 'appointments_location_id_foreign', 'location_id');
        $this->addRaw('appointments', 'appointments_appointment_status_id_foreign', 'appointment_status_id');
        $this->addRaw('appointments', 'appointments_account', 'account_id');
    }
};
