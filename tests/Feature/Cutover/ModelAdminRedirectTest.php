<?php

declare(strict_types=1);

namespace Tests\Feature\Cutover;

use App\Models\Centertarget;
use App\Models\Resources;
use App\Models\User;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Cutover regression guard.
 *
 * `Resources::inactiveRecord` and `Centertarget::deleteRecord` previously
 * returned `redirect()->route('admin.resources.index' / 'admin.centre_targets.index')`
 * on their not-found branch. Those Blade route names are deleted at the cutover,
 * so `redirect()->route()` would throw RouteNotFoundException (500) — and both
 * methods are reachable from surviving /api code (ResourceService::changeStatus,
 * CentreTargetService bulk-delete). They now return `false`. This test fails the
 * moment the Blade-route redirect is reintroduced.
 */
class ModelAdminRedirectTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        // Seed reference data (user_types etc.) so the factory user inserts cleanly,
        // then act as a user — getData()/find() are account-scoped and the missing-id
        // lookup must resolve to null without tripping on a null account.
        $this->seedFinancialFixtures();
        $this->actingAs(User::factory()->create());
    }

    public function test_resources_inactive_record_returns_false_for_missing_id(): void
    {
        $this->assertFalse(Resources::inactiveRecord(999999));
    }

    public function test_centertarget_delete_record_returns_false_for_missing_id(): void
    {
        $this->assertFalse(Centertarget::deleteRecord(999999));
    }
}
