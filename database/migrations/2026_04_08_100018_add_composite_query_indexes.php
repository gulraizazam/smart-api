<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add composite indexes for the most common multi-column query patterns.
     *
     * Based on EXPLAIN ANALYZE and codebase query pattern analysis.
     * Each index targets a verified high-frequency WHERE clause combination.
     */
    public function up(): void
    {
        // ─── package_advances (826K rows) ───
        // patient_id+account_id: used in filters_packageAdvances(), getAppointmentPackage()
        DB::statement('CREATE INDEX idx_pa_patient_account ON package_advances(patient_id, account_id)');

        // appointment_id+patient_id+cash_flow: used in getAppointmentPackage()
        DB::statement('CREATE INDEX idx_pa_appt_patient_cashflow ON package_advances(appointment_id, patient_id, cash_flow)');

        // ─── appointments (394K rows) ───
        // account_id+appointment_type_id: used in getAppointmentsList(), buildBaseQuery()
        DB::statement('CREATE INDEX idx_appt_account_type ON appointments(account_id, appointment_type_id)');

        // account_id+base_appointment_status_id: used in ExcludeCancelled scope, status filtering
        DB::statement('CREATE INDEX idx_appt_account_basestatus ON appointments(account_id, base_appointment_status_id)');

        // scheduled_date+appointment_type_id+account_id: used in date range + type queries
        DB::statement('CREATE INDEX idx_appt_date_type_account ON appointments(scheduled_date, appointment_type_id, account_id)');

        // patient_id+appointment_type_id+base_appointment_status_id: used in latest arrived lookup
        DB::statement('CREATE INDEX idx_appt_patient_type_status ON appointments(patient_id, appointment_type_id, base_appointment_status_id)');

        // ─── leads (515K rows) ───
        // account_id+city_id: used in buildCountQuery(), buildOptimizedResultQuery()
        DB::statement('CREATE INDEX idx_leads_account_city ON leads(account_id, city_id)');

        // account_id+lead_status_id: used in junk status filtering
        DB::statement('CREATE INDEX idx_leads_account_status ON leads(account_id, lead_status_id)');

        // phone+account_id: used in createLead() duplicate checking
        DB::statement('CREATE INDEX idx_leads_phone_account ON leads(phone, account_id)');

        // ─── activities (374K rows) ───
        // account_id+created_at: used in getActivityLogs() with date range
        DB::statement('CREATE INDEX idx_activities_account_created ON activities(account_id, created_at)');

        // account_id+activity_type: used in getActivityLogs() with type filter
        DB::statement('CREATE INDEX idx_activities_account_type ON activities(account_id, activity_type)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX idx_pa_patient_account ON package_advances');
        DB::statement('DROP INDEX idx_pa_appt_patient_cashflow ON package_advances');
        DB::statement('DROP INDEX idx_appt_account_type ON appointments');
        DB::statement('DROP INDEX idx_appt_account_basestatus ON appointments');
        DB::statement('DROP INDEX idx_appt_date_type_account ON appointments');
        DB::statement('DROP INDEX idx_appt_patient_type_status ON appointments');
        DB::statement('DROP INDEX idx_leads_account_city ON leads');
        DB::statement('DROP INDEX idx_leads_account_status ON leads');
        DB::statement('DROP INDEX idx_leads_phone_account ON leads');
        DB::statement('DROP INDEX idx_activities_account_created ON activities');
        DB::statement('DROP INDEX idx_activities_account_type ON activities');
    }
};
