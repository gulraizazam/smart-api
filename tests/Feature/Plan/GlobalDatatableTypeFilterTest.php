<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Models\Packages;
use App\Models\Patients;
use App\Services\Plan\PlanService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the global /plans datatable "Type" pill (plan | bundle | membership).
 *
 * The SPA forwards the selected type as `plan_type` inside `query.search`.
 * PlanDatatableRequest::filters() carries it through, but
 * PlanService::buildWhereConditionsBase() used to build no WHERE for it —
 * so the filter was silently dropped and every type returned the full list.
 *
 * These tests assert the type filter now scopes the result set, and that an
 * empty filter still returns all types.
 */
class GlobalDatatableTypeFilterTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $admin = $this->actingAsAdmin();

        // Global datatable centre-scopes via ACL::getUserCentres(); a
        // non-id-1 user resolves centres from user_has_locations, so the
        // acting admin must be attached to the location its packages sit on.
        DB::table('user_has_locations')->updateOrInsert(
            ['user_id' => $admin->id, 'location_id' => $this->defaultLocation->id],
            ['region_id' => (int) ($this->defaultLocation->region_id ?? 1)],
        );

        $patient = Patients::factory()->create();
        foreach (['plan', 'bundle', 'membership'] as $type) {
            Packages::factory()->create([
                'patient_id' => $patient->id,
                'location_id' => $this->defaultLocation->id,
                'account_id' => Auth::user()->account_id,
                'plan_type' => $type,
            ]);
        }
    }

    private function totalFor(array $filters): int
    {
        return app(PlanService::class)->getGlobalDatatableData($filters)['total'];
    }

    public function test_no_type_filter_returns_every_plan_type(): void
    {
        $this->assertSame(3, $this->totalFor([]));
    }

    public function test_each_type_filter_returns_only_that_type(): void
    {
        $this->assertSame(1, $this->totalFor(['plan_type' => 'plan']));
        $this->assertSame(1, $this->totalFor(['plan_type' => 'bundle']));
        $this->assertSame(1, $this->totalFor(['plan_type' => 'membership']));
    }

    public function test_type_filter_only_returns_rows_of_that_type(): void
    {
        $data = app(PlanService::class)->getGlobalDatatableData(['plan_type' => 'membership']);
        $rows = $data['query']->get();

        $this->assertCount(1, $rows);
        // packages.plan_type is cast to the PlanType backed enum.
        $this->assertSame('membership', $rows->first()->plan_type->value);
    }
}
