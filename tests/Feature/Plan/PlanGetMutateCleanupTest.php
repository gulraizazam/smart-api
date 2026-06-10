<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Models\PackageAdvances;
use App\Models\Packages;
use App\Models\PaymentModes;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Phase 5 — GET-mutate cleanup.
 *
 * Two plan mutations used to be reachable by GET (CSRF-exposed, since
 * VerifyCsrfToken only guards non-GET): staging a row
 * (savepackages_service_for_plan) and updating a plan (updatepackages).
 * They now have POST twins that the SPA uses; the GET aliases stay for
 * legacy callers. And getgrandtotal_update — which WRITES packages.total_price
 * — used to accept the read-only plans.list.view; it now requires plans.edit
 * and refuses a settled plan.
 */
class PlanGetMutateCleanupTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_post_twins_exist_for_the_former_get_mutates(): void
    {
        foreach (['admin.packages.savepackages_service_for_plan_post', 'admin.packages.updatepackages_post'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route, "POST twin {$name} must be registered so the SPA never GET-mutates.");
            $this->assertContains('POST', $route->methods(), "{$name} must accept POST.");
        }
    }

    public function test_getgrandtotal_update_rejects_a_user_without_plans_edit(): void
    {
        // A plain authenticated user (no plans.edit) must not be able to
        // rewrite a plan total through this write-behind-GET endpoint.
        $user = User::factory()->create(['account_id' => 1]);
        $this->actingAs($user);

        $response = $this->getJson('/api/packages/getgrandtotal_update?random_id=whatever&total=5000&cash_amount=0');

        $response->assertStatus(403);
    }

    public function test_getgrandtotal_update_refuses_a_settled_plan(): void
    {
        $this->actingAsAdmin(); // has plans.edit via Super-Admin

        $plan = Packages::factory()->create([
            'account_id' => 1,
            'location_id' => $this->defaultLocation->id,
            'random_id' => 'SETTLED-RID',
        ]);
        $cash = PaymentModes::query()->where('name', 'Cash')->firstOrFail();
        PackageAdvances::factory()->create([
            'package_id' => $plan->id,
            'account_id' => 1,
            'location_id' => $this->defaultLocation->id,
            'payment_mode_id' => $cash->id,
            'cash_flow' => 'out',
            'cash_amount' => 1000,
            'is_setteled' => 1,
        ]);

        $response = $this->getJson('/api/packages/getgrandtotal_update?random_id=SETTLED-RID&total=9999&cash_amount=0');

        $response->assertStatus(409);
        $this->assertNotSame(
            9999.0,
            (float) $plan->fresh()->total_price,
            'A settled plan total must not be rewritten.',
        );
    }
}
