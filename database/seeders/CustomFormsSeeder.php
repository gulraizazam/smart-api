<?php

use App\Models\CustomForms;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomFormsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {

        // Permissions has been added
        $MainPermission = Permission::create([
            'title' => 'Custom Forms',
            'name' => 'custom_forms_manage',
            'guard_name' => 'web',
            'main_group' => 1,
            'parent_id' => 0,
        ]);

        Permission::insert([
            [
                'title' => 'Create General',
                'name' => 'custom_forms_create_general',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Create Measurement',
                'name' => 'custom_forms_create_measurement',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Create Medical History Form',
                'name' => 'custom_forms_create_medical_history_form',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Edit',
                'name' => 'custom_forms_edit',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Activate',
                'name' => 'custom_forms_active',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Inactivate',
                'name' => 'custom_forms_inactive',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Delete',
                'name' => 'custom_forms_destroy',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Preview',
                'name' => 'custom_forms_preview',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Submit',
                'name' => 'custom_forms_submit',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
        ]);

        $role = Role::findOrFail(1);

        // Assign Permission to 'administrator' role
        $role->givePermissionTo('custom_forms_manage');
        $role->givePermissionTo('custom_forms_create_general');
        $role->givePermissionTo('custom_forms_create_measurement');
        $role->givePermissionTo('custom_forms_create_medical_history_form');
        $role->givePermissionTo('custom_forms_edit');
        $role->givePermissionTo('custom_forms_active');
        $role->givePermissionTo('custom_forms_inactive');
        $role->givePermissionTo('custom_forms_destroy');
        $role->givePermissionTo('custom_forms_preview');
        $role->givePermissionTo('custom_forms_submit');

        DB::unprepared("INSERT INTO `custom_forms` (`id`, `name`, `description`, `form_type`, `content`, `active`, `sort_number`, `account_id`, `created_by`, `updated_by`, `custom_form_type`, `created_at`, `updated_at`, `deleted_at`) VALUES
(3, 'Aesthetic Operator - Post Treatment', '', 1, '', 1, 3, 1, 1, 1, 1, '2018-11-01 07:47:25', '2018-11-01 07:50:58', NULL),
(4, 'Aesthetic Operator - Pre Treatment', '', 1, '', 1, 4, 1, 1, 1, 1, '2018-11-01 07:53:37', '2018-11-01 07:53:57', NULL),
(5, 'Lifestyle Consultancy - Follow Up', '', 1, '', 1, 5, 1, 1, 1, 1, '2018-11-01 07:55:16', '2018-11-01 07:55:49', NULL),
(6, 'Lifestyle Consultancy Form', '', 1, '', 1, 6, 1, 1, 1, 1, '2018-11-01 08:11:49', '2018-11-01 08:11:53', NULL),
(7, 'Treatment Plan', '', 1, '', 1, 7, 1, 1, 1, 1, '2018-11-01 08:23:22', '2018-11-01 08:23:26', NULL),
(8, 'Trilogy Ice Consultation Form', NULL, 1, '', 1, 8, 1, 1, 1, 1, '2018-11-01 08:27:36', '2018-11-01 08:27:48', NULL),
(9, 'Trilogy Ice Treatment Note', '', 1, '', 1, 9, 1, 1, 1, 1, '2018-11-01 08:57:55', '2018-11-01 08:58:55', NULL);");
    }
}
