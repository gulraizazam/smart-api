<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONSULTATION_CHILD_NAMES = [
        'appointments_consultancy',
        'appointments_services',
        'appointments_edit',
        'appointments_destroy',
        'appointments_invoice',
        'appointments_invoice_display',
        'appointments_patient_card',
        'appointments_appointment_status',
        'appointments_plans_create',
        'appointments_export',
        'appointments_export_today',
        'appointments_export_this_month',
        'appointments_export_all',
        'appointments_log',
        'appointments_log_excel',
        'edit_after_arrived',
        'update_consultation_doctor',
        'update_consultation_schedule',
        'update_consultation_service',
    ];

    private const TREATMENT_CHILD_NAMES_MISPLACED = [
        'treatments_services',
        'edit_doctor_after_arrived_treatment',
        'edit_service_after_arrived_treatment',
        'edit_schedule_after_arrived_treatment',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            $appointmentsParentId = DB::table('permissions')
                ->where('name', 'appointments_manage')
                ->value('id');

            $treatmentsParentId = DB::table('permissions')
                ->where('name', 'treatments_manage')
                ->value('id');

            if ($appointmentsParentId === null || $treatmentsParentId === null) {
                return;
            }

            $consultationsParentId = DB::table('permissions')
                ->where('name', 'consultations_manage')
                ->value('id');

            if ($consultationsParentId === null) {
                $consultationsParentId = DB::table('permissions')->insertGetId([
                    'name' => 'consultations_manage',
                    'title' => 'Consultations',
                    'guard_name' => 'web',
                    'parent_id' => 0,
                    'main_group' => 1,
                    'status' => 1,
                    'sort_order' => 0,
                    'category' => 'Appointments',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('permissions')
                    ->where('id', $consultationsParentId)
                    ->update([
                        'title' => 'Consultations',
                        'parent_id' => 0,
                        'main_group' => 1,
                        'status' => 1,
                        'category' => 'Appointments',
                        'updated_at' => now(),
                    ]);
            }

            DB::table('permissions')
                ->whereIn('name', self::CONSULTATION_CHILD_NAMES)
                ->update([
                    'parent_id' => $consultationsParentId,
                    'updated_at' => now(),
                ]);

            DB::table('permissions')
                ->whereIn('name', self::TREATMENT_CHILD_NAMES_MISPLACED)
                ->update([
                    'parent_id' => $treatmentsParentId,
                    'updated_at' => now(),
                ]);

            DB::table('permissions')
                ->where('name', 'appointments_manage')
                ->update([
                    'title' => 'Appointment Attachments',
                    'updated_at' => now(),
                ]);
        });
    }

    public function down(): void
    {
        // No-op: reversing this would re-pollute appointments_manage with
        // consultation + misplaced-treatment children and revert the clearer
        // title. Use the pre-migration dump at storage/backups/ to restore.
    }
};
