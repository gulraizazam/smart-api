<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $guardName = 'web';

        $hrmGroup = Permission::where('name', 'hr_manage')->firstOrFail();

        // Final set of HRM child permissions with sort order
        $children = [
            ['name' => 'hr_dashboard_view',               'title' => 'Dashboard',                      'sort_order' => 1],
            ['name' => 'hr_employees_view',               'title' => 'View Employees',                 'sort_order' => 2],
            ['name' => 'hr_employees_manage',             'title' => 'Manage Employees',               'sort_order' => 3],
            ['name' => 'hr_departments_view',             'title' => 'View Departments',               'sort_order' => 4],
            ['name' => 'hr_departments_manage',           'title' => 'Manage Departments',             'sort_order' => 5],
            ['name' => 'hr_designations_view',            'title' => 'View Designations',              'sort_order' => 6],
            ['name' => 'hr_designations_manage',          'title' => 'Manage Designations',            'sort_order' => 7],
            ['name' => 'hr_leave_view',                   'title' => 'View Leaves',                    'sort_order' => 8],
            ['name' => 'hr_leave_manage',                 'title' => 'Manage Leaves (Types/Balances)', 'sort_order' => 9],
            ['name' => 'hr_leave_approve',                'title' => 'Approve/Reject Leave',           'sort_order' => 10],
            ['name' => 'hr_recruitment_view',             'title' => 'View Candidates',                'sort_order' => 11],
            ['name' => 'hr_recruitment_create',           'title' => 'Add Candidate',                  'sort_order' => 12],
            ['name' => 'hr_recruitment_edit',             'title' => 'Edit Candidate',                 'sort_order' => 13],
            ['name' => 'hr_recruitment_status_update',    'title' => 'Update Candidate Status',        'sort_order' => 14],
            ['name' => 'hr_recruitment_interview_manage', 'title' => 'Manage Interviews',              'sort_order' => 15],
            ['name' => 'hr_recruitment_convert',          'title' => 'Convert Candidate to Employee',  'sort_order' => 16],
            ['name' => 'hr_recruitment_delete',           'title' => 'Delete Candidate',               'sort_order' => 17],
            ['name' => 'hr_documents_view',               'title' => 'View Employee Documents',        'sort_order' => 18],
            ['name' => 'hr_documents_manage',             'title' => 'Manage Employee Documents',     'sort_order' => 19],
        ];

        DB::transaction(function () use ($children, $hrmGroup, $guardName) {
            foreach ($children as $child) {
                Permission::updateOrCreate(
                    ['name' => $child['name']],
                    [
                        'title'      => $child['title'],
                        'main_group' => 0,
                        'parent_id'  => $hrmGroup->id,
                        'status'     => 1,
                        'guard_name' => $guardName,
                        'sort_order' => $child['sort_order'],
                        'category'   => 'HRM',
                    ]
                );
            }

            // Tag parent with HRM category + resolved sort bucket
            Permission::where('name', 'hr_manage')->update(['category' => 'HRM']);

            // Remove deprecated permissions + their role grants
            $deprecated = [
                'hr_employees_create',
                'hr_employees_edit',
                'hr_employees_delete',
                'hr_leave_apply',
            ];

            $deprecatedIds = Permission::whereIn('name', $deprecated)->pluck('id')->all();

            if (!empty($deprecatedIds)) {
                DB::table('role_has_permissions')->whereIn('permission_id', $deprecatedIds)->delete();
                DB::table('model_has_permissions')->whereIn('permission_id', $deprecatedIds)->delete();
                Permission::whereIn('id', $deprecatedIds)->delete();
            }
        });
    }

    public function down(): void
    {
        $hrmGroup = Permission::where('name', 'hr_manage')->first();
        if (!$hrmGroup) {
            return;
        }

        $guardName = 'web';

        // Re-create previously deprecated permissions
        $reinstate = [
            ['name' => 'hr_employees_create', 'title' => 'Create Employee Details'],
            ['name' => 'hr_employees_edit',   'title' => 'Edit Employee Details'],
            ['name' => 'hr_employees_delete', 'title' => 'Delete Employee Records'],
            ['name' => 'hr_leave_apply',      'title' => 'Apply for Leave'],
        ];

        foreach ($reinstate as $p) {
            Permission::firstOrCreate(
                ['name' => $p['name']],
                [
                    'title'      => $p['title'],
                    'main_group' => 0,
                    'parent_id'  => $hrmGroup->id,
                    'status'     => 1,
                    'guard_name' => $guardName,
                ]
            );
        }

        // Remove permissions introduced by this migration
        Permission::whereIn('name', [
            'hr_employees_manage',
            'hr_departments_view',
            'hr_designations_view',
            'hr_recruitment_status_update',
            'hr_recruitment_interview_manage',
            'hr_recruitment_convert',
        ])->delete();
    }
};
