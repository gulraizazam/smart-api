<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * HR module — fine-grained permission catalog (`hr.<area>.<action>`).
 *
 * Folds the existing 19 legacy children of `hr_manage` into one dotted
 * catalog, sub-namespaced per HR sub-module (dashboard / employees /
 * departments / designations / leave / recruitment / documents) so the
 * role editor groups actions cleanly and the controllers can be
 * re-pointed to the dotted names in lockstep.
 *
 * Catalog (group head `hr` + 19 children):
 *   Dashboard
 *     - hr.dashboard.view
 *   Employees
 *     - hr.employees.view
 *     - hr.employees.manage           grants edit + sensitive-field visibility (see EmployeeResource)
 *   Departments
 *     - hr.departments.view / .manage
 *   Designations
 *     - hr.designations.view / .manage
 *   Leave
 *     - hr.leave.view                 own + (with .manage) all-employees
 *     - hr.leave.manage               types / balances / admin CRUD
 *     - hr.leave.approve              approve / reject application
 *   Recruitment
 *     - hr.recruitment.view
 *     - hr.recruitment.create
 *     - hr.recruitment.edit
 *     - hr.recruitment.status_update
 *     - hr.recruitment.interview_manage
 *     - hr.recruitment.convert        convert candidate → employee
 *     - hr.recruitment.delete
 *   Documents
 *     - hr.documents.view / .manage
 *
 * Mirror is 1-to-1 — the legacy names already follow the
 * `hr_<area>_<action>` shape, just with underscores instead of dots.
 *
 * IMPORTANT — partial migration: legacy `hr_*` rows stay at status=1
 * because every consumer (Api\HR\DashboardController,
 * EmployeeController, EmployeeDatatableController, DepartmentController,
 * DesignationController, LeaveBalanceController, LeaveApplicationController,
 * EmployeeResource) still calls the underscore names. Companion
 * `hide_legacy_hr_group` migration runs after the controller rewrite.
 */
return new class extends Migration
{
    private const GUARD = 'web';

    private const ADMIN_ROLES = ['Administrator', 'Super-Admin', 'Super Admin'];

    private const ROLE_SERVICE_CACHE_KEYS = [
        'roles.permissions_mapping.v2.super',
        'roles.permissions_mapping.v2.normal',
        'roles.permissions_mapping.super',
        'roles.permissions_mapping.normal',
    ];

    /**
     * @return list<array{name: string, title: string}>
     */
    private function newPerms(): array
    {
        return [
            ['name' => 'hr.dashboard.view',              'title' => 'Dashboard'],

            ['name' => 'hr.employees.view',              'title' => 'Employees · View'],
            ['name' => 'hr.employees.manage',            'title' => 'Employees · Manage'],

            ['name' => 'hr.departments.view',            'title' => 'Departments · View'],
            ['name' => 'hr.departments.manage',          'title' => 'Departments · Manage'],

            ['name' => 'hr.designations.view',           'title' => 'Designations · View'],
            ['name' => 'hr.designations.manage',         'title' => 'Designations · Manage'],

            ['name' => 'hr.leave.view',                  'title' => 'Leave · View'],
            ['name' => 'hr.leave.manage',                'title' => 'Leave · Manage (Types/Balances)'],
            ['name' => 'hr.leave.approve',               'title' => 'Leave · Approve / Reject'],

            ['name' => 'hr.recruitment.view',            'title' => 'Recruitment · View'],
            ['name' => 'hr.recruitment.create',          'title' => 'Recruitment · Add Candidate'],
            ['name' => 'hr.recruitment.edit',            'title' => 'Recruitment · Edit Candidate'],
            ['name' => 'hr.recruitment.status_update',   'title' => 'Recruitment · Update Candidate Status'],
            ['name' => 'hr.recruitment.interview_manage','title' => 'Recruitment · Manage Interviews'],
            ['name' => 'hr.recruitment.convert',         'title' => 'Recruitment · Convert to Employee'],
            ['name' => 'hr.recruitment.delete',          'title' => 'Recruitment · Delete Candidate'],

            ['name' => 'hr.documents.view',              'title' => 'Documents · View'],
            ['name' => 'hr.documents.manage',            'title' => 'Documents · Manage'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function legacyToNewMap(): array
    {
        return [
            'hr_dashboard_view'               => ['hr.dashboard.view'],

            'hr_employees_view'               => ['hr.employees.view'],
            'hr_employees_manage'             => ['hr.employees.manage'],

            'hr_departments_view'             => ['hr.departments.view'],
            'hr_departments_manage'           => ['hr.departments.manage'],

            'hr_designations_view'            => ['hr.designations.view'],
            'hr_designations_manage'          => ['hr.designations.manage'],

            'hr_leave_view'                   => ['hr.leave.view'],
            'hr_leave_manage'                 => ['hr.leave.manage'],
            'hr_leave_approve'                => ['hr.leave.approve'],

            'hr_recruitment_view'             => ['hr.recruitment.view'],
            'hr_recruitment_create'           => ['hr.recruitment.create'],
            'hr_recruitment_edit'             => ['hr.recruitment.edit'],
            'hr_recruitment_status_update'    => ['hr.recruitment.status_update'],
            'hr_recruitment_interview_manage' => ['hr.recruitment.interview_manage'],
            'hr_recruitment_convert'          => ['hr.recruitment.convert'],
            'hr_recruitment_delete'           => ['hr.recruitment.delete'],

            'hr_documents_view'               => ['hr.documents.view'],
            'hr_documents_manage'             => ['hr.documents.manage'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $group = Permission::updateOrCreate(
                ['name' => 'hr'],
                [
                    'title' => 'HRM',
                    'main_group' => 1,
                    'parent_id' => 0,
                    'status' => 1,
                    'category' => 'People',
                    'guard_name' => self::GUARD,
                ],
            );

            foreach ($this->newPerms() as $i => $row) {
                Permission::updateOrCreate(
                    ['name' => $row['name']],
                    [
                        'title' => $row['title'],
                        'main_group' => 0,
                        'parent_id' => $group->id,
                        'status' => 1,
                        'category' => null,
                        'guard_name' => self::GUARD,
                        'sort_order' => $i + 1,
                    ],
                );
            }

            $newNames = array_map(static fn ($r) => $r['name'], $this->newPerms());

            foreach (Role::whereIn('name', self::ADMIN_ROLES)->get() as $role) {
                $role->givePermissionTo($newNames);
            }

            $perms = DB::table('permissions')
                ->whereIn('name', array_keys($this->legacyToNewMap()))
                ->get(['id', 'name'])
                ->keyBy('name');

            $newPermIds = DB::table('permissions')
                ->whereIn('name', $newNames)
                ->pluck('id', 'name');

            foreach ($this->legacyToNewMap() as $legacy => $news) {
                if (! isset($perms[$legacy])) {
                    continue;
                }
                $legacyId = (int) $perms[$legacy]->id;
                $rolesWithLegacy = DB::table('role_has_permissions')
                    ->where('permission_id', $legacyId)
                    ->pluck('role_id');
                $usersWithLegacy = DB::table('model_has_permissions')
                    ->where('permission_id', $legacyId)
                    ->get(['model_id', 'model_type']);

                foreach ($news as $newName) {
                    $newId = (int) ($newPermIds[$newName] ?? 0);
                    if ($newId <= 0) {
                        continue;
                    }
                    foreach ($rolesWithLegacy as $roleId) {
                        DB::table('role_has_permissions')->updateOrInsert(
                            ['role_id' => $roleId, 'permission_id' => $newId],
                            [],
                        );
                    }
                    foreach ($usersWithLegacy as $assoc) {
                        DB::table('model_has_permissions')->updateOrInsert(
                            [
                                'permission_id' => $newId,
                                'model_id'      => $assoc->model_id,
                                'model_type'    => $assoc->model_type,
                            ],
                            [],
                        );
                    }
                }
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $names = array_map(static fn ($r) => $r['name'], $this->newPerms());
            Permission::whereIn('name', $names)->delete();

            Permission::where('name', 'hr')
                ->where('main_group', 1)
                ->where('category', 'People')
                ->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
                Cache::forget($key);
            }
        });
    }
};
