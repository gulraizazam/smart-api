<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /*
    |--------------------------------------------------------------------------
    | Authentication helpers
    |--------------------------------------------------------------------------
    |
    | Opt-in helpers — only invoked by tests that actually need them. Each
    | one creates a fresh user via the appropriate factory state and calls
    | actingAs() so the rest of the test runs inside that identity. The
    | created user is returned so the test can capture its id for later
    | assertions.
    |
    | The pre-existing security regression tests (tests/Unit/Security/*) do
    | NOT need these helpers and continue to work without paying for them.
    */

    /**
     * Act as a Super-Admin user. Use for tests of broad management screens
     * that should not be gated by per-resource permissions.
     *
     * Ensures the `Super-Admin` Spatie role exists and is assigned, so
     * code that checks `hasRole('Super-Admin')` (e.g. PatientAccessScope,
     * the global Gate::before bypass in AppServiceProvider) treats this
     * user as super. Otherwise the test user gets an auto-incremented id
     * that's not 1, and any id=1-style fallback misses them.
     */
    protected function actingAsAdmin(): User
    {
        $user = User::factory()->admin()->create();

        // Spatie's Role::findOrCreate uses Eloquent fillable, but this
        // codebase's `roles` table has a custom non-nullable `commission`
        // column with no default. Insert via DB::table so we can supply
        // the value the schema requires.
        $roleId = (int) DB::table('roles')
            ->where(['name' => 'Super-Admin', 'guard_name' => 'web'])
            ->value('id');
        if ($roleId === 0) {
            $roleId = (int) DB::table('roles')->insertGetId([
                'name' => 'Super-Admin',
                'guard_name' => 'web',
                'commission' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        // Clear Spatie's cached registrar so the just-created role is
        // visible to hasRole() in this request.
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user->assignRole('Super-Admin');

        $this->actingAs($user);

        return $user;
    }

    /**
     * Act as a user holding the named Spatie role. The test must have
     * already created the role (or seeded it) — this helper does NOT
     * create roles to keep the surface narrow.
     */
    protected function actingAsRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->assignRole($roleName);
        $this->actingAs($user);

        return $user;
    }

    /**
     * Act as a doctor (user_type_id = 5) optionally tied to a specific
     * location via doctor_has_locations. Pass `null` for an unscoped doctor.
     */
    protected function actingAsDoctor(?int $locationId = null): User
    {
        $user = User::factory()->doctor()->create();

        if ($locationId !== null) {
            DB::table('doctor_has_locations')->insert([
                'user_id' => $user->id,
                'location_id' => $locationId,
                'is_allocated' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($user);

        return $user;
    }

    /**
     * Act as a patient (user_type_id = 3). Patients are stored in the
     * `users` table behind a global scope on the Patients model.
     */
    protected function actingAsPatient(): User
    {
        $user = User::factory()->patient()->create();
        $this->actingAs($user);

        return $user;
    }

    /*
    |--------------------------------------------------------------------------
    | RBAC helpers
    |--------------------------------------------------------------------------
    |
    | The production `roles` table has been extended beyond Spatie's default
    | schema with a NOT NULL `commission decimal(11,2)` column (used by the
    | doctor-payout machinery). The Spatie Role model has no knowledge of
    | that column, so calling `Role::create([...])` raises a 1364 NOT NULL
    | violation. These helpers insert via DB::table to bypass the
    | mass-assignment filter, then return the corresponding Eloquent model
    | so callers can use the standard Spatie API for the rest.
    */

    /**
     * Create a Spatie Role row, satisfying the production-only `commission`
     * NOT NULL column. Idempotent — re-uses an existing role if one with
     * the same (name, guard) tuple exists.
     */
    protected function createRole(string $name, string $guard = 'web'): \Spatie\Permission\Models\Role
    {
        $existing = \Spatie\Permission\Models\Role::query()
            ->where('name', $name)
            ->where('guard_name', $guard)
            ->first();

        if ($existing) {
            return $existing;
        }

        DB::table('roles')->insert([
            'name' => $name,
            'guard_name' => $guard,
            'commission' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return \Spatie\Permission\Models\Role::query()
            ->where('name', $name)
            ->where('guard_name', $guard)
            ->firstOrFail();
    }

    /**
     * Create a Spatie Permission row. Idempotent.
     */
    protected function createPermission(string $name, string $guard = 'web'): \Spatie\Permission\Models\Permission
    {
        return \Spatie\Permission\Models\Permission::query()->firstOrCreate([
            'name' => $name,
            'guard_name' => $guard,
        ]);
    }

    /**
     * Assign a role to a user the way production does it: write to BOTH
     * pivots. Spatie's stock `assignRole()` only populates `model_has_roles`,
     * but `User::user_roles()` reads from the custom `role_has_users` pivot.
     * Production's ApplicationUserService::syncRoleHasUsers() mirrors every
     * assignment into the custom table; tests must do the same or any
     * privileged-role gate is bypassed.
     */
    protected function assignRoleWithPivot(User $user, \Spatie\Permission\Models\Role $role): void
    {
        $user->assignRole($role);

        DB::table('role_has_users')->updateOrInsert(
            ['role_id' => $role->id, 'user_id' => $user->id],
            ['deleted_at' => null]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Assertion helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Assert that a JSON response indicates success in the project's
     * standard envelope (`{status: 200, success: true, data: ..., message: ...}`).
     * Use in API feature tests.
     */
    protected function assertJsonApiSuccess(\Illuminate\Testing\TestResponse $response): void
    {
        $response->assertOk();
        $response->assertJson(fn ($json) => $json
            ->where('status', 200)
            ->where('success', true)
            ->etc()
        );
    }

    /**
     * Run a callback while counting the queries it issues, and fail if
     * the count exceeds `$maxQueries`. The single highest-leverage tool
     * for catching N+1 regressions — wrap the controller call (or the
     * service call) in this and pin the expected query count.
     *
     * Example:
     *   $this->assertQueryCountAtMost(5, fn () => $this->get('/admin/patients'));
     */
    protected function assertQueryCountAtMost(int $maxQueries, callable $callback): void
    {
        $count = 0;
        DB::listen(static function () use (&$count): void {
            $count++;
        });

        $callback();

        $this->assertLessThanOrEqual(
            $maxQueries,
            $count,
            "Expected at most {$maxQueries} queries, observed {$count}. "
            . 'A higher count usually means a relationship is being lazily loaded inside a loop. '
            . 'Add the relationship to the controller eager-load list (->with([...])) or the model.'
        );
    }
}
