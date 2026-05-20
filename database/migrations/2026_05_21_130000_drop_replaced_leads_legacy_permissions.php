<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Drop the legacy snake_case leads-module permissions whose call sites have
 * been fully rewritten to the new `leads.<area>.<action>` catalog.
 *
 * Companion to 2026_05_21_120000_add_leads_module_permissions (created the
 * new catalog + mirrored grants from the legacy rows).
 *
 * Backend rewrites completed prior to this migration:
 *   - app/Http/Controllers/Api/LeadsController.php       (15 gates)
 *   - app/Http/Controllers/Admin/LeadsController.php     ( 4 gates)
 *   - app/Http/Controllers/Admin/PatientsController.php  ( 2 gates — leads_view fallback)
 *   - app/Http/Requests/Admin/StoreUpdateLeadCommentsRequest.php
 *   - app/Http/Requests/Lead/{ImportLeads,StoreLead,UpdateLead,UpdateLeadStatus}Request.php
 *   - app/Policies/LeadPolicy.php                        ( 8 gates)
 *   - app/Exports/ExportLead.php                         ( 1 gate )
 *   - app/Services/UserManagement/RoleService.php        ( 1 entry — denylist name)
 *   - resources/views/admin/partials/{sidebar,sidebar-v2}.blade.php
 *   - resources/views/partials/sidebar.blade.php
 *   - resources/views/admin/leads/{city,index,lead_status,view}.blade.php
 *
 * Rows being dropped (12 total):
 *   leads_manage, leads_create, leads_edit, leads_destroy,
 *   leads_import, leads_export, leads_lead_status, leads_city,
 *   leads_junk, leads_convert, view_inactive_leads, leads_view (if present)
 *
 * NOT dropped (separate sub-modules, will be audited in their own pass):
 *   lead_sources_* (7 rows) and lead_statuses_* (7 rows) and leads_reports_*
 *
 * Snapshot of pre-delete title / parent_id / status saved to
 * storage/app/migrations/2026_05_21_130000_legacy_leads_perms.json so the
 * down() handler can restore them exactly. Grants are restored by mapping
 * back from the new perms (mirror of the up() mapping in the add migration).
 */
return new class extends Migration
{
    private const GUARD = 'web';

    /**
     * Legacy → new map (mirror of what the add migration used). Down()
     * uses this to figure out which roles should regain each legacy
     * grant.
     *
     * @return array<string, list<string>>
     */
    private function reverseMap(): array
    {
        return [
            'leads_manage' => ['leads.list.view', 'leads.detail.view'],
            'leads_create' => ['leads.create'],
            'leads_edit' => ['leads.edit'],
            'leads_destroy' => ['leads.delete'],
            'leads_import' => ['leads.import'],
            'leads_export' => ['leads.export'],
            'leads_lead_status' => ['leads.update_status'],
            'leads_city' => ['leads.update_city'],
            'leads_convert' => ['leads.convert'],
            'leads_junk' => ['leads.list.view_junk'],
            'view_inactive_leads' => ['leads.list.view_inactive'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $names = array_keys($this->reverseMap());

            $snapshot = Permission::whereIn('name', $names)
                ->get(['name', 'title', 'parent_id', 'status'])
                ->keyBy('name')
                ->map(fn ($p) => [
                    'title' => (string) $p->title,
                    'parent_id' => (int) $p->parent_id,
                    'status' => (int) $p->status,
                ])
                ->all();

            $this->writeSnapshot($snapshot);

            Permission::whereIn('name', $names)->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (
                [
                    'roles.permissions_mapping.v2.super',
                    'roles.permissions_mapping.v2.normal',
                    'roles.permissions_mapping.super',
                    'roles.permissions_mapping.normal',
                ] as $key
            ) {
                Cache::forget($key);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $snapshot = $this->readSnapshot();

            foreach ($this->reverseMap() as $name => $newPerms) {
                $meta = $snapshot[$name] ?? null;

                $perm = Permission::firstOrCreate(
                    ['name' => $name],
                    [
                        'title' => $meta['title'] ?? $name,
                        'main_group' => 0,
                        'parent_id' => $meta['parent_id'] ?? 0,
                        'status' => $meta['status'] ?? 1,
                        'guard_name' => self::GUARD,
                    ],
                );

                if ($newPerms === []) {
                    continue;
                }

                $roleIds = DB::table('role_has_permissions as rhp')
                    ->join('permissions as p', 'p.id', '=', 'rhp.permission_id')
                    ->whereIn('p.name', $newPerms)
                    ->distinct()
                    ->pluck('rhp.role_id')
                    ->all();

                if ($roleIds === []) {
                    continue;
                }

                $rows = array_map(
                    static fn (int $rid) => ['role_id' => $rid, 'permission_id' => $perm->id],
                    array_map('intval', $roleIds),
                );
                DB::table('role_has_permissions')->insertOrIgnore($rows);
            }

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            foreach (
                [
                    'roles.permissions_mapping.v2.super',
                    'roles.permissions_mapping.v2.normal',
                    'roles.permissions_mapping.super',
                    'roles.permissions_mapping.normal',
                ] as $key
            ) {
                Cache::forget($key);
            }
        });
    }

    /**
     * @param array<string, array{title: string, parent_id: int, status: int}> $snapshot
     */
    private function writeSnapshot(array $snapshot): void
    {
        $dir = storage_path('app/migrations');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        file_put_contents(
            $dir.'/2026_05_21_130000_legacy_leads_perms.json',
            json_encode($snapshot, JSON_PRETTY_PRINT),
        );
    }

    /**
     * @return array<string, array{title: string, parent_id: int, status: int}>
     */
    private function readSnapshot(): array
    {
        $path = storage_path('app/migrations/2026_05_21_130000_legacy_leads_perms.json');
        if (! is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }
};
