<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the backfill that RESTORES brand-status-toggle access after the #20 gate
 * fix (BrandsController::status: product_active -> brand_active). brand_active had
 * 0 role grants, so the slug fix alone locked every brand-managing role out of the
 * toggle. up() grants brand_active to brand_manage holders (and only them).
 *
 * Teeth: revert the migration's up() loop and the "manager gains brand_active"
 * assertion reddens.
 */
class GrantBrandActiveToBrandManagersTest extends TestCase
{
    use RefreshDatabase;

    private const FILE = 'migrations/2026_06_20_120000_grant_brand_active_to_brand_managers.php';

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function runUp(): void
    {
        (require database_path(self::FILE))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_brand_managers_gain_brand_active_but_non_managers_do_not(): void
    {
        $this->createPermission('brand_manage');
        $this->createPermission('brand_active');
        $manager = $this->createRole('BrandManager');
        $manager->givePermissionTo('brand_manage');
        $other = $this->createRole('NoBrandRole'); // holds neither
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertFalse(
            $manager->fresh()->hasPermissionTo('brand_active'),
            'precondition: brand_active must start ungranted (mirrors prod where it had 0 grants).',
        );

        $this->runUp();

        $this->assertTrue(
            $manager->fresh()->hasPermissionTo('brand_active'),
            'a brand_manage role must gain brand_active so it keeps the status-toggle access it had via the old product_active gate.',
        );
        $this->assertFalse(
            $other->fresh()->hasPermissionTo('brand_active'),
            'a role that does NOT manage brands must not be granted brand_active.',
        );
    }

    public function test_down_revokes_the_backfilled_grant(): void
    {
        $this->createPermission('brand_manage');
        $this->createPermission('brand_active');
        $manager = $this->createRole('BrandManager');
        $manager->givePermissionTo('brand_manage');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->runUp();
        $this->assertTrue($manager->fresh()->hasPermissionTo('brand_active'));

        (require database_path(self::FILE))->down();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertFalse(
            $manager->fresh()->hasPermissionTo('brand_active'),
            'down() must revoke exactly the backfilled grant (brand_active returns to 0).',
        );
    }
}
