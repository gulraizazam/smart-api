<?php

declare(strict_types=1);

namespace Tests\Unit\Observers;

use App\Models\CashFlow\Vendor;
use App\Models\CashFlow\VendorTransaction;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * VendorTransactionObserver maintains the per-vendor outstanding balance.
 * The audit caught a critical bug where 'ordered' (not yet delivered)
 * purchases were being added to outstanding balance — these tests pin the
 * fixed behaviour:
 *   - Delivered purchase  → balance increases (we owe more)
 *   - Ordered  purchase   → balance unchanged (delivery hasn't happened)
 *   - Payment            → balance decreases (we owe less)
 */
class VendorTransactionObserverTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_delivered_purchase_increases_vendor_outstanding_balance(): void
    {
        $vendor = Vendor::factory()->create(['cached_balance' => 0]);

        VendorTransaction::factory()->delivered()->create([
            'vendor_id' => $vendor->id,
            'amount' => 5000,
        ]);

        $this->assertSame(5000.00, (float) $vendor->fresh()->cached_balance);
    }

    public function test_ordered_purchase_does_not_yet_change_outstanding_balance(): void
    {
        $vendor = Vendor::factory()->create(['cached_balance' => 0]);

        VendorTransaction::factory()->ordered()->create([
            'vendor_id' => $vendor->id,
            'amount' => 5000,
        ]);

        $this->assertSame(
            0.00,
            (float) $vendor->fresh()->cached_balance,
            'Ordered (not yet delivered) purchases must not affect outstanding balance.'
        );
    }

    public function test_payment_to_vendor_decreases_outstanding_balance(): void
    {
        $vendor = Vendor::factory()->create(['cached_balance' => 8000]);

        VendorTransaction::factory()->payment()->create([
            'vendor_id' => $vendor->id,
            'amount' => 3000,
        ]);

        $this->assertSame(5000.00, (float) $vendor->fresh()->cached_balance);
    }

    public function test_purchase_then_payment_nets_correctly(): void
    {
        $vendor = Vendor::factory()->create(['cached_balance' => 0]);

        VendorTransaction::factory()->delivered()->create([
            'vendor_id' => $vendor->id, 'amount' => 10000,
        ]);
        VendorTransaction::factory()->payment()->create([
            'vendor_id' => $vendor->id, 'amount' => 4000,
        ]);

        $this->assertSame(6000.00, (float) $vendor->fresh()->cached_balance);
    }
}
