<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Enums\VendorTransactionStatus;
use App\Enums\VendorTransactionType;
use App\Exceptions\CashflowException;
use App\Models\CashFlow\PeriodLock;
use App\Models\CashFlow\Vendor;
use App\Models\CashFlow\VendorTransaction;
use App\Services\CashFlow\VendorService;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Phase-6 contract: vendor transactions (purchase create / edit / mark-delivered
 * / delete) refuse to write into a closed period. Mirrors `PeriodLockEnforcementTest`
 * for expenses.
 *
 * Pre-fix, `VendorService` had no period-lock guard anywhere, so closed months
 * could silently grow new vendor purchases / balance changes.
 */
class VendorPeriodLockEnforcementTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private VendorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->service = app(VendorService::class);
    }

    public function test_recording_a_vendor_purchase_in_a_locked_period_is_rejected(): void
    {
        $previous = now()->subMonthNoOverflow();
        PeriodLock::factory()->create([
            'account_id' => 1,
            'month' => $previous->month,
            'year' => $previous->year,
        ]);

        $vendor = Vendor::factory()->create();

        $this->expectException(CashflowException::class);
        $this->service->recordTransaction([
            'vendor_id' => $vendor->id,
            'type' => VendorTransactionType::Purchase,
            'amount' => 1000,
            'transaction_date' => $previous->copy()->day(15)->toDateString(),
            'description' => 'Backdated purchase',
            'attachment_url' => 'https://drive.google.com/file/d/abc/view',
        ], accountId: 1);
    }

    public function test_recording_a_vendor_purchase_today_succeeds_when_only_past_periods_locked(): void
    {
        $previous = now()->subMonthNoOverflow();
        PeriodLock::factory()->create([
            'account_id' => 1,
            'month' => $previous->month,
            'year' => $previous->year,
        ]);

        $vendor = Vendor::factory()->create();
        $tx = $this->service->recordTransaction([
            'vendor_id' => $vendor->id,
            'type' => VendorTransactionType::Purchase,
            'amount' => 1000,
            'transaction_date' => now()->toDateString(),
            'description' => 'Today\'s purchase',
        ], accountId: 1);

        $this->assertNotNull($tx->id);
    }

    public function test_updating_a_vendor_transaction_in_a_locked_period_is_rejected(): void
    {
        $previous = now()->subMonthNoOverflow();
        $vendor = Vendor::factory()->create();
        $tx = VendorTransaction::factory()->create([
            'vendor_id' => $vendor->id,
            'transaction_date' => $previous->copy()->day(15)->toDateString(),
            'type' => VendorTransactionType::Purchase,
            'status' => VendorTransactionStatus::Delivered,
        ]);

        // Lock the period AFTER the txn was created so the OLD date is locked.
        PeriodLock::factory()->create([
            'account_id' => 1,
            'month' => $previous->month,
            'year' => $previous->year,
        ]);

        $this->expectException(CashflowException::class);
        $this->service->updateTransaction($tx->id, [
            'amount' => 2000,
        ], accountId: 1);
    }

    public function test_moving_a_vendor_transaction_into_a_locked_period_is_rejected(): void
    {
        $previous = now()->subMonthNoOverflow();
        PeriodLock::factory()->create([
            'account_id' => 1,
            'month' => $previous->month,
            'year' => $previous->year,
        ]);

        $vendor = Vendor::factory()->create();
        $tx = VendorTransaction::factory()->create([
            'vendor_id' => $vendor->id,
            'transaction_date' => now()->toDateString(),
            'type' => VendorTransactionType::Purchase,
            'status' => VendorTransactionStatus::Delivered,
        ]);

        $this->expectException(CashflowException::class);
        $this->service->updateTransaction($tx->id, [
            'transaction_date' => $previous->copy()->day(15)->toDateString(),
        ], accountId: 1);
    }

    public function test_marking_delivered_inside_a_locked_period_is_rejected(): void
    {
        $previous = now()->subMonthNoOverflow();
        $vendor = Vendor::factory()->create();
        $tx = VendorTransaction::factory()->create([
            'vendor_id' => $vendor->id,
            'transaction_date' => $previous->copy()->day(15)->toDateString(),
            'type' => VendorTransactionType::Purchase,
            'status' => VendorTransactionStatus::Ordered,
        ]);

        PeriodLock::factory()->create([
            'account_id' => 1,
            'month' => $previous->month,
            'year' => $previous->year,
        ]);

        $this->expectException(CashflowException::class);
        $this->service->deliverTransaction(
            $tx->id,
            'https://drive.google.com/file/d/x/view',
            accountId: 1,
        );
    }

    public function test_deleting_a_vendor_transaction_in_a_locked_period_is_rejected(): void
    {
        $previous = now()->subMonthNoOverflow();
        $vendor = Vendor::factory()->create();
        $tx = VendorTransaction::factory()->create([
            'vendor_id' => $vendor->id,
            'transaction_date' => $previous->copy()->day(15)->toDateString(),
            'type' => VendorTransactionType::Purchase,
            'status' => VendorTransactionStatus::Delivered,
        ]);

        PeriodLock::factory()->create([
            'account_id' => 1,
            'month' => $previous->month,
            'year' => $previous->year,
        ]);

        $this->expectException(CashflowException::class);
        $this->service->deleteTransaction($tx->id, accountId: 1);
    }
}
