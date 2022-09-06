<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class CustomFormFeedbacksSeeder extends Seeder
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
            'title' => 'Custom Form Feedbacks',
            'name' => 'custom_form_feedbacks_manage',
            'guard_name' => 'web',
            'main_group' => 1,
            'parent_id' => 0,
        ]);

        Permission::insert([
            [
                'title' => 'Edit',
                'name' => 'custom_form_feedbacks_edit',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
            [
                'title' => 'Preview',
                'name' => 'custom_form_feedbacks_preview',
                'guard_name' => 'web',
                'main_group' => 0,
                'created_at' => \Carbon\Carbon::now(),
                'updated_at' => \Carbon\Carbon::now(),
                'parent_id' => $MainPermission->id,
            ],
        ]);

        $role = Role::findOrFail(1);

        // Assign Permission to 'administrator' role
        $role->givePermissionTo('custom_form_feedbacks_manage');
        $role->givePermissionTo('custom_form_feedbacks_edit');
        $role->givePermissionTo('custom_form_feedbacks_preview');
    }
}
