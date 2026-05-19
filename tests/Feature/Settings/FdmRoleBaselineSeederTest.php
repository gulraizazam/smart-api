<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use Database\Seeders\FdmRoleBaselineSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the FDM cash-on-plan rule: FDM may ADD a payment to a plan
 * (`plans_cash_edit`) but must NOT be able to edit or delete a recorded
 * payment (no `plans_cash_delete` / `plans_cash_edit_amount|date|
 * payment_mode`). The SPA membership dialog gates add vs. edit/delete
 * separately; this pins the backend half.
 */
class FdmRoleBaselineSeederTest extends TestCase
{
    use RefreshDatabase;

    private const FORBIDDEN = [
        'plans_cash_delete',
        'plans_cash_edit_amount',
        'plans_cash_edit_date',
        'plans_cash_edit_payment_mode',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure the perms exist so Spatie's givePermissionTo can resolve them.
        Permission::firstOrCreate(['name' => 'plans_cash_edit', 'guard_name' => 'web']);
        foreach (self::FORBIDDEN as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        $this->createRole('FDM');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function fdmPermNames(): array
    {
        return Role::where('name', 'FDM')->where('guard_name', 'web')
            ->firstOrFail()
            ->permissions->pluck('name')->all();
    }

    public function test_grants_plans_cash_edit_only(): void
    {
        $this->seed(FdmRoleBaselineSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $perms = $this->fdmPermNames();

        $this->assertContains('plans_cash_edit', $perms, 'FDM must be able to add a plan payment');
        foreach (self::FORBIDDEN as $name) {
            $this->assertNotContains(
                $name,
                $perms,
                "FDM must NOT hold {$name} — they cannot modify/delete recorded payments",
            );
        }
    }

    public function test_is_idempotent(): void
    {
        $this->seed(FdmRoleBaselineSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $first = count($this->fdmPermNames());

        $this->seed(FdmRoleBaselineSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertSame($first, count($this->fdmPermNames()), 'Re-run must not duplicate grants');
    }

    public function test_missing_fdm_role_is_a_safe_no_op(): void
    {
        Role::where('name', 'FDM')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->seed(FdmRoleBaselineSeeder::class); // must not throw

        $this->assertNull(Role::where('name', 'FDM')->first());
    }
}
