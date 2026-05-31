<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

class AddTreatmentPermissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // First, create the main "Treatments" permission group
        $treatmentsMain = Permission::create([
            'name' => 'treatments_manage',
            'title' => 'Treatments',
            'main_group' => 1,
            'parent_id' => 0,
            'guard_name' => 'web',
        ]);

        // Get the parent_id for the new treatments group
        $parentId = $treatmentsMain->id;

        // Create all treatment permissions matching the appointments permissions
        $treatmentPermissions = [
            ['name' => 'treatments_display', 'title' => 'Display'],
            ['name' => 'treatments_consultancy', 'title' => 'Manage Consultancy'],
            ['name' => 'treatments_services', 'title' => 'Manage Services'],
            ['name' => 'treatments_edit', 'title' => 'Edit'],
            ['name' => 'treatments_export', 'title' => 'Export'],
            ['name' => 'treatments_appointment_status', 'title' => 'Update Appointment Status'],
            ['name' => 'treatments_invoice', 'title' => 'Appointment Invoice'],
            ['name' => 'treatments_patient_card', 'title' => 'Patient Card'],
            ['name' => 'treatments_invoice_display', 'title' => 'Invoice Display'],
            ['name' => 'treatments_medical_form_manage', 'title' => 'Medical History Form'],
            ['name' => 'treatments_edit_after_arrived', 'title' => 'Edit Appointment After Arrived'],
            ['name' => 'treatments_destroy', 'title' => 'Delete'],
            ['name' => 'treatments_plans_create', 'title' => 'Plan Create'],
            ['name' => 'treatments_today', 'title' => 'Today\'s Appointments'],
            
            // Additional permissions that exist for appointments
            ['name' => 'treatments_image_manage', 'title' => 'Images'],
            ['name' => 'treatments_image_upload', 'title' => 'Images Upload'],
            ['name' => 'treatments_image_destroy', 'title' => 'Images Delete'],
            ['name' => 'treatments_measurement_manage', 'title' => 'Measurement'],
            ['name' => 'treatments_measurement_create', 'title' => 'Measurements Create'],
            ['name' => 'treatments_measurement_edit', 'title' => 'Measurements Edit'],
            ['name' => 'treatments_medical_create', 'title' => 'Medical Form Create'],
            ['name' => 'treatments_medical_edit', 'title' => 'Medical Form Edit'],
            ['name' => 'treatments_export_today', 'title' => 'Today'],
            ['name' => 'treatments_export_this_month', 'title' => 'This Month'],
            ['name' => 'treatments_export_all', 'title' => 'All'],
            ['name' => 'treatments_log', 'title' => 'Log'],
            ['name' => 'treatments_log_excel', 'title' => 'Log Excel'],
        ];

        foreach ($treatmentPermissions as $permission) {
            Permission::create([
                'name' => $permission['name'],
                'title' => $permission['title'],
                'main_group' => 0,
                'parent_id' => $parentId,
                'guard_name' => 'web',
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Delete all treatment permissions
        Permission::where('name', 'LIKE', 'treatments_%')->delete();
    }
}
