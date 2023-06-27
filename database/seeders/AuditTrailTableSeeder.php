<?php

namespace Database\Seeders;

use App\Models\AuditTrailTables;
use Illuminate\Database\Seeder;

class AuditTrailTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $table_data = $this->tableData();
        foreach ($table_data as $table) {
            if (! AuditTrailTables::where($table)->exists()) {
                AuditTrailTables::create($table);
            }
        }
    }

    private function tableData()
    {
        return [
            ['name' => 'cities', 'screen' => 'City'],
            ['name' => 'locations', 'screen' => 'Centres'],
            ['name' => 'lead_sources', 'screen' => 'Lead Source'],
            ['name' => 'lead_statuses', 'screen' => 'Lead Status'],
            ['name' => 'resource_types', 'screen' => 'Resource Type'],
            ['name' => 'service_has_locations', 'screen' => 'Service Has Location'],
            ['name' => 'cancellation_reasons', 'screen' => 'Cancellation Reason'],
            ['name' => 'resources', 'screen' => 'Resource'],
            ['name' => 'appointment_statuses', 'screen' => 'Appointment Status'],
            ['name' => 'payment_modes', 'screen' => 'Payment Mode'],
            ['name' => 'settings', 'screen' => 'Setting'],
            ['name' => 'sms_templates', 'screen' => 'SMS Templates'],
            ['name' => 'services', 'screen' => 'Service'],
            ['name' => 'user_types', 'screen' => 'User Types'],
            ['name' => 'resource_has_rota', 'screen' => 'Resource Has Rota'],
            ['name' => 'resource_has_rota_days', 'screen' => 'Resource Has Rota Days'],
            ['name' => 'users', 'screen' => 'Users'],
            ['name' => 'leads', 'screen' => 'Lead'],
            ['name' => 'role_has_users', 'screen' => 'Role Has User'],
            ['name' => 'user_has_locations', 'screen' => 'User Has Location'],
            ['name' => 'discounts', 'screen' => 'Discount'],
            ['name' => 'user_operator_settings', 'screen' => 'Operator Settings'],
            ['name' => 'packages', 'screen' => 'Plans'],
            ['name' => 'package_bundles', 'screen' => 'Plan Bundle'],
            ['name' => 'package_advances', 'screen' => 'Finances'],
            ['name' => 'invoices', 'screen' => 'Invoice'],
            ['name' => 'invoice_details', 'screen' => 'Invoice Detail'],
            ['name' => 'resource_has_services', 'screen' => 'Resource Has Services'],
            ['name' => 'documents', 'screen' => 'Documents upload'],
            ['name' => 'regions', 'screen' => 'Regions'],
            ['name' => 'appointmentimages', 'screen' => 'Appointment Images'],
            ['name' => 'bundles', 'screen' => 'Packages'],
            ['name' => 'bundle_has_services', 'screen' => 'Package Has Services'],
            ['name' => 'package_services', 'screen' => 'Plan Services'],
            ['name' => 'custom_forms', 'screen' => 'Custom Forms'],
            ['name' => 'custom_form_fields', 'screen' => 'Custom Form Fields'],
            ['name' => 'custom_form_feedbacks', 'screen' => 'Custom Form Feedbacks'],
            ['name' => 'custom_form_feedback_details', 'screen' => 'Custom Form Feedback Details'],
            ['name' => 'appointments', 'screen' => 'Appointments'],
            ['name' => 'staff_targets', 'screen' => 'Staff Targets'],
            ['name' => 'staff_target_services', 'screen' => 'Staff Target Services'],
            ['name' => 'measurements', 'screen' => 'Measurements'],
            ['name' => 'medicals', 'screen' => 'Medicals History'],
            ['name' => 'centertarget', 'screen' => 'Centre Target'],
            ['name' => 'centretargetmeta', 'screen' => 'Centre Target Meta'],
            ['name' => 'machine_types', 'screen' => 'Machine Type'],
            ['name' => 'machine_type_has_services', 'screen' => 'Machine Type Has Resource'],
            ['name' => 'towns', 'screen' => 'Town'],
            ['name' => 'consultancy', 'screen' => 'Consultancy'],
            ['name' => 'treatment', 'screen' => 'Treatment'],
        ];
    }
}
