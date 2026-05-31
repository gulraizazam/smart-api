<?php

declare(strict_types=1);

namespace Tests\Feature\Cutover;

use App\Models\User;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Cutover regression guard — dangling Blade-route redirects on /api.
 *
 * Several KEPT controllers (still mounted on /api after the Blade UI was
 * deleted) had branches that did `redirect()->route('admin.<X>.index')`
 * — or returned a now-missing Blade view. Those named routes / views no
 * longer exist, so on an /api request Laravel threw
 * RouteNotFoundException / View-not-found → HTTP 500 instead of a proper
 * JSON envelope.
 *
 * Each method was rewritten to return JSON:
 *   - permission/guard branch  -> 403 JSON
 *   - not-found / invalid      -> 404 / 422 JSON
 *   - happy path               -> 200 JSON {success:true,...}
 *
 * This test pins the contract at the HTTP boundary: hit each endpoint
 * and assert it never 500s on a dangling redirect, and that the
 * unauthorized branch returns a 403 JSON envelope (not a 500, not an
 * HTML page). It deliberately does NOT re-test the full CRUD behaviour
 * of each controller — that lives in the per-feature suites.
 */
class DanglingAdminRedirectTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    /**
     * A plain user with no permissions hits the guard branch of every
     * touched endpoint. Before the fix, several of these branches did
     * `redirect()->route('admin.*')` and returned 500; they must now
     * return a 403 JSON envelope.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function guardedEndpoints(): array
    {
        return [
            'custom_form_feedbacks create' => ['GET', '/api/custom_form_feedbacks/create'],
            'town status'                  => ['POST', '/api/towns/status'],
            'package advance cancel'       => ['POST', '/api/packagesadvances/cancel/1'],
            'package advance destroy'      => ['DELETE', '/api/packagesadvances/1'],
            'lead create popup'            => ['GET', '/api/lead_Create_popup'],
        ];
    }

    /**
     * @dataProvider guardedEndpoints
     */
    public function test_guard_branch_returns_403_json_not_500(string $method, string $url): void
    {
        // A user with no roles/permissions: every Gate::allows() denies.
        $this->actingAs(User::factory()->create());

        $response = $this->json($method, $url);

        $this->assertNotSame(
            500,
            $response->getStatusCode(),
            "{$method} {$url} 500'd — a dangling admin.* redirect or missing Blade view leaked through.",
        );
        $response->assertStatus(403);
        $response->assertJson(['success' => false]);
    }

    public function test_town_update_guard_returns_403_json_not_500(): void
    {
        // Town@update runs StoreUpdateTownRequest first (FormRequest
        // validation), so the controller's towns_edit guard is only
        // reached with a valid body. Supply one, then assert the guard
        // branch — which used to flash()+JSON — now returns a 403
        // envelope for a user without the permission.
        $this->actingAs(User::factory()->create());

        $response = $this->putJson('/api/towns/1', ['name' => 'X', 'city_id' => 1]);

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertStatus(403);
        $response->assertJson(['success' => false]);
    }

    public function test_lead_create_popup_returns_json_payload_for_admin(): void
    {
        // The happy path used to `return view('admin.leads.createTo', ...)`
        // — a Blade view deleted at cutover, so it 500'd. It now returns
        // the form lookups as a JSON envelope.
        $this->actingAsAdmin();

        $response = $this->getJson('/api/lead_Create_popup');

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure([
            'data' => ['cities', 'towns', 'lead_sources', 'lead_statuses', 'services', 'employees'],
        ]);
    }

    public function test_settings_edit_returns_404_json_for_missing_id_not_500(): void
    {
        // Settings edit/update already returned JSON, but pin that the
        // not-found branch is a clean 404 envelope (no redirect lurking).
        $this->actingAsAdmin();

        $response = $this->getJson('/api/settings/999999/edit');

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertStatus(404);
        $response->assertJson(['success' => false]);
    }

    public function test_town_edit_returns_json_for_missing_id_not_500(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/towns/999999/edit');

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertJson(['success' => false]);
    }

    public function test_package_advance_cancel_returns_404_json_for_missing_id_not_500(): void
    {
        // The cancel happy path previously redirected to the deleted
        // admin.packagesadvances.index route. The not-found branch also
        // used to dereference a null model (->toArray() on null) → 500.
        // Both are now JSON.
        $this->actingAsAdmin();

        $response = $this->postJson('/api/packagesadvances/cancel/999999');

        $this->assertNotSame(500, $response->getStatusCode());
        $response->assertStatus(404);
        $response->assertJson(['success' => false]);
    }
}
