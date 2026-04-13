<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $guardName = 'web';

        // Create the HRM parent group permission
        $hrmGroup = Permission::firstOrCreate(
            ['name' => 'hr_manage'],
            [
                'title' => 'HRM',
                'main_group' => 1,
                'parent_id' => 0,
                'status' => 1,
                'guard_name' => $guardName,
            ]
        );

        $children = [
            ['name' => 'hr_dashboard_view', 'title' => 'Dashboard'],
            ['name' => 'hr_employees_view', 'title' => 'View Employees'],
            ['name' => 'hr_employees_create', 'title' => 'Create Employee Details'],
            ['name' => 'hr_employees_edit', 'title' => 'Edit Employee Details'],
            ['name' => 'hr_employees_delete', 'title' => 'Delete Employee Records'],
            ['name' => 'hr_departments_manage', 'title' => 'Manage Departments'],
            ['name' => 'hr_designations_manage', 'title' => 'Manage Designations'],
            ['name' => 'hr_leave_view', 'title' => 'View Leave Requests'],
            ['name' => 'hr_leave_apply', 'title' => 'Apply for Leave'],
            ['name' => 'hr_leave_approve', 'title' => 'Approve/Reject Leave'],
            ['name' => 'hr_leave_manage', 'title' => 'Manage Leave Types & Balances'],
            ['name' => 'hr_recruitment_view', 'title' => 'View Candidates'],
            ['name' => 'hr_recruitment_create', 'title' => 'Create Candidate/Interview'],
            ['name' => 'hr_recruitment_edit', 'title' => 'Edit Candidate'],
            ['name' => 'hr_recruitment_delete', 'title' => 'Delete Candidate'],
            ['name' => 'hr_documents_manage', 'title' => 'Manage Employee Documents'],
            ['name' => 'hr_documents_view', 'title' => 'View Own Documents'],
        ];

        foreach ($children as $child) {
            Permission::firstOrCreate(
                ['name' => $child['name']],
                [
                    'title' => $child['title'],
                    'main_group' => 0,
                    'parent_id' => $hrmGroup->id,
                    'status' => 1,
                    'guard_name' => $guardName,
                ]
            );
        }
    }

    public function down(): void
    {
        $names = [
            'hr_manage', 'hr_dashboard_view', 'hr_employees_view', 'hr_employees_create',
            'hr_employees_edit', 'hr_employees_delete', 'hr_departments_manage',
            'hr_designations_manage', 'hr_leave_view', 'hr_leave_apply', 'hr_leave_approve',
            'hr_leave_manage', 'hr_recruitment_view', 'hr_recruitment_create',
            'hr_recruitment_edit', 'hr_recruitment_delete', 'hr_documents_manage',
            'hr_documents_view',
        ];

        Permission::whereIn('name', $names)->delete();
    }
};
