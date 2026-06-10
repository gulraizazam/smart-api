<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Models\PackageBundles;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The single structural guard against the recurring Plans "wrong-record" class.
 *
 * `package_bundles.bundle_id` is an OVERLOADED FK whose meaning depends on
 * `source_type` (service → services.id, bundle → bundles.id, service_bundle →
 * service_bundles.id). Those tables share id ranges, so a row written WITHOUT a
 * valid source_type silently resolves the WRONG catalog downstream (wrong
 * name/price on the plan dialog, invoices, reports). The model `creating`
 * guard makes that a LOUD write-time error instead. Membership lines are keyed
 * by their own `membership_type_id` column and intentionally leave source_type
 * NULL (matches crm2) — exempt (covered by the membership suites).
 */
class PackageBundleSourceTypeGuardTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    /** @param array<string,mixed> $over @return array<string,mixed> */
    private function row(array $over = []): array
    {
        return array_merge([
            'account_id' => 1,
            'random_id' => 'GUARD-'.uniqid(),
            'qty' => 1,
            'service_price' => 100,
            'net_amount' => 100,
            'active' => 1,
        ], $over);
    }

    public function test_rejects_a_non_membership_row_with_null_source_type(): void
    {
        $this->expectException(\RuntimeException::class);
        PackageBundles::create($this->row()); // no source_type → loud error
    }

    public function test_rejects_an_invalid_source_type(): void
    {
        $this->expectException(\RuntimeException::class);
        PackageBundles::create($this->row(['source_type' => 'nonsense']));
    }

    public function test_allows_each_valid_source_type(): void
    {
        foreach (['service', 'bundle', 'service_bundle'] as $type) {
            $row = PackageBundles::create($this->row(['source_type' => $type]));
            $this->assertSame($type, $row->fresh()->source_type);
        }
    }
}
