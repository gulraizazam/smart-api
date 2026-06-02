<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the API contract of the custom `permission` middleware
 * (App\Http\Middleware\CheckPermission).
 *
 * Regression: on denial it unconditionally `redirect()->route('unauthorized')`
 * — a 302 to a Blade page. SPA fetch() can't follow that, so cashflow panels
 * (e.g. FDM cash) silently broke ("No branches assigned") instead of hitting
 * their explicit 403 handling. API / XHR callers must now get JSON 403; legacy
 * Blade web routes keep the human-facing redirect.
 */
class CheckPermissionMiddlewareTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeds accounts(1) + user_types so User::factory() satisfies its FKs
        // (the pipe tests create real authenticated users; the guest tests
        // below don't need it, but it's harmless and keeps setUp uniform).
        $this->seedFinancialFixtures();

        // Throwaway routes guarded by the real alias. A guest holds no
        // permission, so Gate::allows() is false → the denial branch runs.
        Route::middleware('permission:cf_mw_probe_perm')
            ->get('/api/_probe/check-permission', static fn () => response()->json(['ok' => true]));

        Route::middleware('permission:cf_mw_probe_perm')
            ->get('/_probe/check-permission-web', static fn () => 'ok');

        // Pipe-delimited "any of these" route — the shape every
        // routes/cashflow.php group middleware uses.
        Route::middleware('permission:cf_mw_alpha|cf_mw_beta')
            ->get('/api/_probe/check-permission-pipe', static fn () => response()->json(['ok' => true]));
    }

    /**
     * @return User a non-super-admin user holding exactly the given permissions
     */
    private function userWithPermissions(array $permissions): User
    {
        foreach ($permissions as $perm) {
            $this->createPermission($perm);
        }
        $role = $this->createRole('cf-mw-role-' . uniqid());
        if ($permissions !== []) {
            $role->givePermissionTo($permissions);
        }
        $user = User::factory()->create(['account_id' => 1]);
        $user->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    public function test_pipe_admits_a_non_super_admin_holding_any_listed_permission(): void
    {
        // Holds only the SECOND slug in `a|b` — must still be admitted.
        // Regression: the middleware used to pass the whole "a|b" string to
        // Gate::allows() as one ability, so only Super-Admins (via Gate::before)
        // ever passed and every Administrator/Finance user was 403'd.
        $this->createPermission('cf_mw_alpha');
        $this->actingAs($this->userWithPermissions(['cf_mw_beta']));

        $this->getJson('/api/_probe/check-permission-pipe')->assertOk();
    }

    public function test_pipe_denies_a_user_holding_none_of_the_listed_permissions(): void
    {
        $this->createPermission('cf_mw_alpha');
        $this->createPermission('cf_mw_beta');
        $this->actingAs($this->userWithPermissions(['cf_mw_unrelated']));

        $this->getJson('/api/_probe/check-permission-pipe')->assertStatus(403);
    }

    public function test_super_admin_passes_pipe_route_without_holding_any_slug(): void
    {
        $this->createPermission('cf_mw_alpha');
        $this->createPermission('cf_mw_beta');
        $superRole = $this->createRole('Super-Admin');
        $user = User::factory()->create(['account_id' => 1]);
        $user->assignRole($superRole);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user)->getJson('/api/_probe/check-permission-pipe')->assertOk();
    }

    public function test_api_request_gets_json_403_not_redirect(): void
    {
        $response = $this->getJson('/api/_probe/check-permission');

        $response->assertStatus(403);
        $response->assertJson(['success' => false]);
    }

    public function test_api_path_gets_json_403_even_without_json_accept_header(): void
    {
        // No Accept: application/json — still under api/*, so must be JSON 403,
        // never a 302 (this is exactly the shape the SPA XHR hit on staging).
        $response = $this->get('/api/_probe/check-permission');

        $response->assertStatus(403);
        $response->assertJson(['success' => false]);
    }

    public function test_legacy_web_request_still_redirects_to_unauthorized(): void
    {
        $response = $this->get('/_probe/check-permission-web');

        $response->assertStatus(302);
        $response->assertRedirect(route('unauthorized'));
    }
}
