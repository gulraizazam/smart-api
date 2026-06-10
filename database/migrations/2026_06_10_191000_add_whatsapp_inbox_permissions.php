<?php

declare(strict_types=1);

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * WhatsApp inbox (Phase 2) — permission catalog (`whatsapp.inbox.<action>`).
 *
 * Two grants: `view` gates the inbox page + read endpoints; `reply` gates
 * sending. Granted to admin roles only at rollout — the social-media role
 * gets them via the role editor when the team is onboarded. Mirrors the
 * structure of 2026_05_22_120000 (consultations).
 *
 * COEXISTENCE: additive permission rows only — no legacy permission is
 * renamed, deleted, or re-granted; crm2's catalog is untouched.
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
            ['name' => 'whatsapp.inbox.view',  'title' => 'Inbox · View'],
            ['name' => 'whatsapp.inbox.reply', 'title' => 'Inbox · Reply'],
        ];
    }

    public function up(): void
    {
        DB::transaction(function (): void {
            $group = Permission::updateOrCreate(
                ['name' => 'whatsapp'],
                [
                    'title' => 'WhatsApp',
                    'main_group' => 1,
                    'parent_id' => 0,
                    'status' => 1,
                    'category' => 'WhatsApp',
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

            $newNames = array_map(static fn (array $r) => $r['name'], $this->newPerms());
            foreach (Role::whereIn('name', self::ADMIN_ROLES)->get() as $role) {
                $role->givePermissionTo($newNames);
            }
        });

        $this->flushPermissionCaches();
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            $names = array_map(static fn (array $r) => $r['name'], $this->newPerms());
            $names[] = 'whatsapp';

            foreach (Permission::whereIn('name', $names)->get() as $perm) {
                $perm->roles()->detach();
                $perm->delete();
            }
        });

        $this->flushPermissionCaches();
    }

    private function flushPermissionCaches(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (self::ROLE_SERVICE_CACHE_KEYS as $key) {
            Cache::forget($key);
        }
    }
};
