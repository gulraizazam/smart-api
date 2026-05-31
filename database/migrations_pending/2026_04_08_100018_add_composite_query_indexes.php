<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

    private function addIndex(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table) || $this->indexExists($table, $name)) {
            return;
        }
        Schema::table($table, fn (Blueprint $t) => $t->index($columns, $name));
    }

    private function dropIndex(string $table, string $name): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $name)) {
            return;
        }
        Schema::table($table, fn (Blueprint $t) => $t->dropIndex($name));
    }

    public function up(): void
    {
        $this->addIndex('package_advances', ['patient_id', 'account_id'], 'idx_pa_patient_account');
        $this->addIndex('package_advances', ['appointment_id', 'patient_id', 'cash_flow'], 'idx_pa_appt_patient_cashflow');
        $this->addIndex('appointments', ['account_id', 'appointment_type_id'], 'idx_appt_account_type');
        $this->addIndex('appointments', ['account_id', 'base_appointment_status_id'], 'idx_appt_account_basestatus');
        $this->addIndex('appointments', ['scheduled_date', 'appointment_type_id', 'account_id'], 'idx_appt_date_type_account');
        $this->addIndex('appointments', ['patient_id', 'appointment_type_id', 'base_appointment_status_id'], 'idx_appt_patient_type_status');
        $this->addIndex('leads', ['account_id', 'city_id'], 'idx_leads_account_city');
        $this->addIndex('leads', ['account_id', 'lead_status_id'], 'idx_leads_account_status');
        $this->addIndex('leads', ['phone', 'account_id'], 'idx_leads_phone_account');
        $this->addIndex('activities', ['account_id', 'created_at'], 'idx_activities_account_created');
        $this->addIndex('activities', ['account_id', 'activity_type'], 'idx_activities_account_type');
    }

    public function down(): void
    {
        $this->dropIndex('package_advances', 'idx_pa_patient_account');
        $this->dropIndex('package_advances', 'idx_pa_appt_patient_cashflow');
        $this->dropIndex('appointments', 'idx_appt_account_type');
        $this->dropIndex('appointments', 'idx_appt_account_basestatus');
        $this->dropIndex('appointments', 'idx_appt_date_type_account');
        $this->dropIndex('appointments', 'idx_appt_patient_type_status');
        $this->dropIndex('leads', 'idx_leads_account_city');
        $this->dropIndex('leads', 'idx_leads_account_status');
        $this->dropIndex('leads', 'idx_leads_phone_account');
        $this->dropIndex('activities', 'idx_activities_account_created');
        $this->dropIndex('activities', 'idx_activities_account_type');
    }
};
