<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Tier P — P3 prerequisite migration
 * (2026_06_18_120000_backfill_contact_dotted_grants_before_bridge_removal).
 *
 * Pins: a role holding legacy `contact` without a dotted view_contact twin gains
 * the twin (so it keeps phone visibility once the bridge is gone); it's
 * idempotent; a role WITHOUT `contact` is untouched; down() reverses exactly.
 */
class ContactDottedBackfillP3Test extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const FILE = 'migrations/2026_06_18_120000_backfill_contact_dotted_grants_before_bridge_removal.php';

    private const DOTTED = ['leads.list.view_contact', 'consultations.list.view_contact', 'patients.list.view_contact'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->createPermission('contact');
        foreach (self::DOTTED as $d) {
            $this->createPermission($d);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function runUp(): void
    {
        (require database_path(self::FILE))->up();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_grants_every_dotted_twin_to_a_contact_only_role(): void
    {
        $role = $this->createRole('TestContactOnly');
        $role->givePermissionTo('contact'); // legacy only — no dotted twins
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::DOTTED as $d) {
            $this->assertFalse($role->fresh()->hasPermissionTo($d), "precondition: lacks {$d}");
        }

        $this->runUp();

        foreach (self::DOTTED as $d) {
            $this->assertTrue(
                $role->fresh()->hasPermissionTo($d),
                "P3 backfill must grant {$d} to a contact-only role",
            );
        }
    }

    public function test_grants_only_the_missing_twin_and_is_idempotent(): void
    {
        // Mirrors prod "Lead Uploader": holds contact + 2 of 3 dotted, missing patients.
        $role = $this->createRole('TestPartial');
        $role->givePermissionTo(['contact', 'leads.list.view_contact', 'consultations.list.view_contact']);

        $this->runUp();
        $this->runUp(); // twice — must not error or duplicate

        $this->assertTrue($role->fresh()->hasPermissionTo('patients.list.view_contact'), 'grants the one missing twin');
        $patientsId = DB::table('permissions')->where('name', 'patients.list.view_contact')->value('id');
        $this->assertSame(
            1,
            (int) DB::table('role_has_permissions')->where('role_id', $role->id)->where('permission_id', $patientsId)->count(),
            'must not duplicate the grant',
        );
    }

    public function test_does_not_grant_to_a_role_without_contact(): void
    {
        $role = $this->createRole('TestNoContact');
        $role->givePermissionTo('leads.list.view_contact'); // a dotted twin but NOT contact

        $this->runUp();

        $this->assertFalse(
            $role->fresh()->hasPermissionTo('patients.list.view_contact'),
            'a role without `contact` must not be backfilled',
        );
    }

    public function test_down_removes_the_backfilled_grants(): void
    {
        $role = $this->createRole('TestContactDown');
        $role->givePermissionTo('contact');

        $this->runUp();
        $this->assertTrue($role->fresh()->hasPermissionTo('patients.list.view_contact'));

        (require database_path(self::FILE))->down();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertFalse(
            $role->fresh()->hasPermissionTo('patients.list.view_contact'),
            'down() must remove exactly the backfilled grants',
        );
    }
}
