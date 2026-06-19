<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\TestCase;

/**
 * Pins that PermissionSeeder does NOT re-create the perms the Tier P / P-R catalog
 * cleanup physically deleted — a `db:seed` must never resurrect the removed
 * duplicates. The test DB is hydrated from the schema dump (0 seeded perms), so a
 * plain seeder run is a faithful "fresh install" — no destructive setup needed.
 * Teeth: drop the RETIRED_PERMISSIONS skip and a deleted slug reappears.
 */
class PermissionSeederRetiredTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_does_not_resurrect_retired_permissions(): void
    {
        (new PermissionSeeder())->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $sampleRetired = [
            'services_manage', 'leads_manage', 'plans_manage', 'packages_manage',
            'treatments_manage', 'treatments_services', 'consultations_manage',
            'update_consultation_doctor', 'resource_types_manage', 'staff_targets_manage',
            'membershiptypes_manage', 'doctor_dashboard',
        ];
        foreach ($sampleRetired as $retired) {
            $this->assertFalse(
                DB::table('permissions')->where('name', $retired)->exists(),
                "PermissionSeeder resurrected retired perm '{$retired}' — would re-introduce a deleted duplicate.",
            );
        }

        // Sanity: the seeder still creates the (non-retired) catalog bulk.
        $this->assertGreaterThan(
            100,
            (int) DB::table('permissions')->count(),
            'seeder should still create the non-retired permission catalog',
        );
    }
}
