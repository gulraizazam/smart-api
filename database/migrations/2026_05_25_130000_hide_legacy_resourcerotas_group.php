<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hide the legacy `resourcerotas_manage` permission group + drop dangling
 * rows that have zero remaining code references.
 *
 * Companion to 2026_05_25_120000_add_scheduling_shifts_module_permissions
 * (creates the new dotted catalog under category='Clinic').
 *
 * Three rows in the legacy group are dangling — no code references:
 *   resourcerotas_active, resourcerotas_inactive, resourcerotas_calender
 * Dropped here. (resourcerotas_calender is a typo for "calendar" — never
 * actually wired in any controller or blade view.)
 *
 * The remaining live rows — `resourcerotas_manage`, `resourcerotas_create`,
 * `resourcerotas_edit`, `resourcerotas_destroy` — still have admin-v1
 * Blade view references that are being phased out. Setting status=0 on
 * the umbrella hides the group from the role editor; Gate::allows() keeps
 * resolving them (Spatie does not filter by our `status` column) so the
 * admin-v1 surfaces keep working until they retire.
 *
 * Reversible: down() flips status back to 1 and restores the dropped
 * rows from the in-migration snapshot.
 */
return new class extends Migration
{
    private const DROP_LIST = [
        'resourcerotas_active',
        'resourcerotas_inactive',
        'resourcerotas_calender',
    ];

    public function up(): void
    {
        DB::transaction(function (): void {
            // Snapshot the dropped rows for down().
            $snapshot = Permission::whereIn('name', self::DROP_LIST)
                ->get(['name', 'title', 'parent_id', 'status', 'main_group', 'guard_name'])
                ->keyBy('name')
                ->map(fn ($p) => [
                    'title' => (string) $p->title,
                    'parent_id' => (int) $p->parent_id,
                    'status' => (int) $p->status,
                    'main_group' => (int) $p->main_group,
                    'guard_name' => (string) $p->guard_name,
                ])
                ->all();
            $this->writeSnapshot($snapshot);

            Permission::whereIn('name', self::DROP_LIST)->delete();

            // Hide the legacy umbrella from the role editor.
            Permission::where('name', 'resourcerotas_manage')->update(['status' => 0]);

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
            foreach ($snapshot as $name => $row) {
                Permission::firstOrCreate(
                    ['name' => $name],
                    [
                        'title' => $row['title'] ?? $name,
                        'parent_id' => $row['parent_id'] ?? 0,
                        'status' => $row['status'] ?? 1,
                        'main_group' => $row['main_group'] ?? 0,
                        'guard_name' => $row['guard_name'] ?? 'web',
                    ],
                );
            }

            Permission::where('name', 'resourcerotas_manage')->update(['status' => 1]);

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
     * @param array<string, array<string, mixed>> $snapshot
     */
    private function writeSnapshot(array $snapshot): void
    {
        $dir = storage_path('app/migrations');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        file_put_contents(
            $dir.'/2026_05_25_130000_legacy_resourcerotas_perms.json',
            json_encode($snapshot, JSON_PRETTY_PRINT),
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function readSnapshot(): array
    {
        $path = storage_path('app/migrations/2026_05_25_130000_legacy_resourcerotas_perms.json');
        if (! is_file($path)) {
            return [];
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }
};
