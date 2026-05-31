<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

/**
 * Adds activity-log viewer permissions. Previously the /admin/reports/activity_logs
 * route had NO permission middleware, so anyone with admin access could view
 * every activity row across the tenant. HIPAA §164.312(a)(1) requires access
 * controls on audit trails — split the capability into two permissions:
 *
 *   activity_logs_manage   — can reach the viewer page and see OWN actions
 *   activity_logs_view_all — can see ALL actions across the tenant (compliance)
 *
 * Enforcement lives in App\Http\Controllers\Admin\Reports\ActivitylogsReportController:
 *   - `activity_logs_manage` gates the route (added to routes/web/admin-reports.php)
 *   - `activity_logs_view_all` is checked inside the controller to decide whether
 *     to row-filter by created_by. Super-Admin is granted automatically via
 *     the global Gate::before in AppServiceProvider.
 *
 * Both permissions are created as children of the existing "Reports" parent
 * group for placement in the roles UI.
 */
return new class extends Migration
{
    public function up(): void
    {
        $guardName = 'web';

        $reportsGroup = Permission::where('name', 'reports_manage')
            ->orWhere('name', 'reports_view')
            ->orWhere('title', 'Reports')
            ->first();

        $parentId = $reportsGroup?->id ?? 0;

        Permission::firstOrCreate(
            ['name' => 'activity_logs_manage'],
            [
                'title' => 'View Activity Logs',
                'main_group' => 0,
                'parent_id' => $parentId,
                'status' => 1,
                'guard_name' => $guardName,
            ]
        );

        Permission::firstOrCreate(
            ['name' => 'activity_logs_view_all'],
            [
                'title' => 'View All Activity Logs (Compliance)',
                'main_group' => 0,
                'parent_id' => $parentId,
                'status' => 1,
                'guard_name' => $guardName,
            ]
        );

        // Preserve previous access semantics: before this PR the activity-logs
        // route had no permission middleware, so any admin could reach it.
        // Auto-grant both permissions to roles named 'Administrator',
        // 'Super-Admin', or 'Super Admin' so existing users keep their view.
        // Compliance owners can revoke `activity_logs_view_all` from specific
        // roles later (to enforce row-level filtering for non-compliance staff).
        $adminRoleNames = ['Administrator', 'Super-Admin', 'Super Admin'];
        foreach (Role::whereIn('name', $adminRoleNames)->get() as $role) {
            $role->givePermissionTo(['activity_logs_manage', 'activity_logs_view_all']);
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', ['activity_logs_manage', 'activity_logs_view_all'])->delete();
    }
};
