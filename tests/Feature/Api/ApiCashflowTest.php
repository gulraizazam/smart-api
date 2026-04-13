<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Smoke coverage for the CashFlow API surface. The cashflow module
 * routes live inside `routes/cashflow.php` under the `auth.common`
 * middleware group and the `cashflow/` prefix. The entry-level lookup
 * endpoint returns active pools / branches / payment modes — a cheap
 * read that exercises the auth layer, the envelope shape, and the
 * `CashflowHelper::get*` lookup stack that every cashflow write path
 * depends on.
 *
 * Pins:
 *   1. Unauthenticated hits do not leak the lookup payload.
 *   2. Authenticated hits return a 200 success envelope with the
 *      three expected keys under `data`.
 *   3. The seeded fixture pool + payment mode are visible in the
 *      response (proves the helper is account-scoped correctly).
 */
class ApiCashflowTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_unauthenticated_lookups_call_does_not_leak(): void
    {
        $response = $this->getJson('/api/cashflow/lookups');

        $this->assertNotSame(200, $response->status());
        $this->assertContains($response->status(), [302, 401, 403, 419]);
    }

    public function test_authenticated_lookups_returns_success_envelope(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/cashflow/lookups');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'pools',
                'branches',
                'payment_modes',
            ],
        ]);
    }

    public function test_authenticated_lookups_surfaces_seeded_pool_and_payment_mode(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/cashflow/lookups');

        $response->assertStatus(200);

        $pools = $response->json('data.pools');
        $paymentModes = $response->json('data.payment_modes');

        $this->assertIsArray($pools);
        $this->assertIsArray($paymentModes);

        $this->assertNotEmpty(
            $pools,
            'seedFinancialFixtures() seeds at least one active cash pool; '
            . 'if this array is empty the fixture seeding or the helper scope '
            . 'has regressed.',
        );
        $this->assertNotEmpty(
            $paymentModes,
            'seedFinancialFixtures() seeds at least one payment mode; '
            . 'if this array is empty the helper lookup is broken.',
        );
    }

    public function test_lookups_contain_branches_scoped_to_account(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/cashflow/lookups');

        $response->assertStatus(200);

        $branches = $response->json('data.branches');
        $this->assertIsArray($branches);
        $this->assertNotEmpty(
            $branches,
            'seedFinancialFixtures() seeds at least one location; '
            . 'branches list must include it.',
        );
    }
}
