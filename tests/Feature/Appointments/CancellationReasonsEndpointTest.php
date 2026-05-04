<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use App\Models\CancellationReasons;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * GET /api/cancellation-reasons — feeds the consultation + treatment
 * status-update dialogs' Cancellation reason dropdown. The backend's
 * status-update flow rejects with 422 when an `is_cancelled` status is
 * picked without a `cancellation_reason_id`, so this endpoint is the
 * SPA's only way to source valid ids.
 */
class CancellationReasonsEndpointTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_returns_active_reasons_only(): void
    {
        CancellationReasons::create([
            'name' => 'Patient Rescheduled',
            'sort_no' => 1,
            'active' => 1,
            'account_id' => 1,
        ]);
        CancellationReasons::create([
            'name' => 'Inactive Reason',
            'sort_no' => 2,
            'active' => 0,
            'account_id' => 1,
        ]);

        $response = $this->getJson('/api/cancellation-reasons');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Patient Rescheduled', $names);
        $this->assertNotContains(
            'Inactive Reason',
            $names,
            'Inactive cancellation reasons must NOT be exposed to operators.',
        );
    }

    public function test_scopes_to_callers_account(): void
    {
        CancellationReasons::create([
            'name' => 'Account-1 Reason',
            'sort_no' => 1,
            'active' => 1,
            'account_id' => 1,
        ]);

        // Bypass GuardsTenantBoundary: the trait force-rewrites
        // `account_id` to the auth user's account on `creating`, so a
        // direct ::create([... account_id => 999]) silently lands as
        // account_id=1. DB::table writes raw and lets us test the
        // controller's WHERE filter.
        DB::table('cancellation_reasons')->insert([
            'name' => 'Other Account Reason',
            'sort_no' => 1,
            'active' => 1,
            'account_id' => 999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/cancellation-reasons');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Account-1 Reason', $names);
        $this->assertNotContains(
            'Other Account Reason',
            $names,
            'Cancellation reasons must be account-scoped to prevent cross-tenant leakage.',
        );
    }

    public function test_filters_by_appointment_type_id_when_provided(): void
    {
        CancellationReasons::create([
            'name' => 'Consultancy-only Reason',
            'sort_no' => 1,
            'active' => 1,
            'account_id' => 1,
            'appointment_type_id' => 2,
        ]);
        CancellationReasons::create([
            'name' => 'Treatment-only Reason',
            'sort_no' => 1,
            'active' => 1,
            'account_id' => 1,
            'appointment_type_id' => 1,
        ]);

        $response = $this->getJson('/api/cancellation-reasons?appointment_type_id=2');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->all();
        $this->assertContains('Consultancy-only Reason', $names);
        $this->assertNotContains('Treatment-only Reason', $names);
    }
}
