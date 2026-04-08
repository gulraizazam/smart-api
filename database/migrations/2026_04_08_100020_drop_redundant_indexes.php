<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Drop redundant single-column indexes that are leftmost prefixes of composite indexes.
     *
     * InnoDB can use a composite index for single-column lookups and FK checks
     * when the FK column is the leftmost column of the composite index.
     *
     * Redundant → Covered by:
     *   package_advances.patient_id_foreign → idx_pa_patient_account (patient_id, account_id)
     *   package_advances.account_id_foreign → idx_pa_account_created (account_id, created_at)
     *   package_advances.appointment → idx_pa_appt_patient_cashflow (appointment_id, patient_id, cash_flow)
     *   leads_services.lead_id_foreign → idx_leads_services_lead_service (lead_id, service_id)
     *   appointments.patient_id_foreign → idx_appointments_patient_deleted (patient_id, deleted_at)
     *   appointments.location_id_foreign → idx_appointments_location_deleted (location_id, deleted_at)
     *   appointments.appointment_status_id → idx_appointment_status_type_location (...)
     *   appointments.account → idx_appointments_account_deleted (account_id, deleted_at)
     *   package_services.package_id_foreign → idx_package_services_pkg_svc (package_id, service_id)
     *   users.account → idx_users_account_active_deleted (account_id, active, deleted_at)
     *   packages.account_id_foreign → idx_packages_account_location_active (account_id, ...)
     */
    public function up(): void
    {
        // Non-FK indexes (safe to drop directly)
        DB::statement('DROP INDEX package_advances_patient_id_foreign ON package_advances');
        DB::statement('DROP INDEX package_advances_account_id_foreign ON package_advances');
        DB::statement('DROP INDEX patient_balances_appointment ON package_advances');
        DB::statement('DROP INDEX leads_services_lead_id_foreign ON leads_services');
        DB::statement('DROP INDEX package_services_package_id_foreign ON package_services');
        DB::statement('DROP INDEX users_account ON users');
        DB::statement('DROP INDEX packages_account_id_foreign ON packages');

        // FK indexes — composite index covers the FK column as leftmost prefix
        DB::statement('DROP INDEX appointments_patient_id_foreign ON appointments');
        DB::statement('DROP INDEX appointments_location_id_foreign ON appointments');
        DB::statement('DROP INDEX appointments_appointment_status_id_foreign ON appointments');
        DB::statement('DROP INDEX appointments_account ON appointments');
    }

    public function down(): void
    {
        DB::statement('CREATE INDEX package_advances_patient_id_foreign ON package_advances(patient_id)');
        DB::statement('CREATE INDEX package_advances_account_id_foreign ON package_advances(account_id)');
        DB::statement('CREATE INDEX patient_balances_appointment ON package_advances(appointment_id)');
        DB::statement('CREATE INDEX leads_services_lead_id_foreign ON leads_services(lead_id)');
        DB::statement('CREATE INDEX package_services_package_id_foreign ON package_services(package_id)');
        DB::statement('CREATE INDEX users_account ON users(account_id)');
        DB::statement('CREATE INDEX packages_account_id_foreign ON packages(account_id)');
        DB::statement('CREATE INDEX appointments_patient_id_foreign ON appointments(patient_id)');
        DB::statement('CREATE INDEX appointments_location_id_foreign ON appointments(location_id)');
        DB::statement('CREATE INDEX appointments_appointment_status_id_foreign ON appointments(appointment_status_id)');
        DB::statement('CREATE INDEX appointments_account ON appointments(account_id)');
    }
};
