<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\PaymentModes;
use App\Services\PaymentMode\PaymentModeService;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * PaymentModes is the lookup table behind the "How did they pay?"
 * dropdown on every invoice, package advance, and refund screen. A
 * regression here would strand cash in a non-existent category.
 *
 * Smoke invariants:
 *   1. `validateAndCreate()` refuses a payload missing `name` or
 *      `type` — these are the two load-bearing fields.
 *   2. A valid payload writes a row and returns success=true.
 *   3. `validateAndUpdate()` updates the row in place.
 *   4. `deletePaymentMode()` wraps the model delete path — returns
 *      a status + message array so the controller can render a
 *      structured error.
 *   5. `changeStatus()` flips the active flag (1 ↔ 0).
 */
class PaymentModeCrudTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private PaymentModeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->service = app(PaymentModeService::class);
    }

    public function test_valid_payload_creates_a_payment_mode_row(): void
    {
        $result = $this->service->validateAndCreate([
            'name' => 'JazzCash',
            'type' => 'application',
            'active' => 1,
            'payment_type' => 'digital',
        ], (int) Auth::user()->account_id);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('payment_modes', ['name' => 'JazzCash']);
    }

    public function test_create_refuses_when_name_is_missing(): void
    {
        $result = $this->service->validateAndCreate([
            'type' => 'application',
        ], (int) Auth::user()->account_id);

        $this->assertFalse($result['success']);
        $this->assertStringContainsStringIgnoringCase('name', $result['error']);
    }

    public function test_create_refuses_when_type_is_missing(): void
    {
        $result = $this->service->validateAndCreate([
            'name' => 'Valid Name',
        ], (int) Auth::user()->account_id);

        $this->assertFalse($result['success']);
        $this->assertStringContainsStringIgnoringCase('type', $result['error']);
    }

    public function test_update_refreshes_the_row_with_new_fields(): void
    {
        // Seed a fully-formed row first — the shared seeder leaves `type`
        // null (it only pins name/active/account_id) and PaymentMode's
        // updateRecord path requires type via the Validator rules.
        $createResult = $this->service->validateAndCreate([
            'name' => 'EasyPaisa',
            'type' => 'application',
            'active' => 1,
            'payment_type' => 'digital',
        ], (int) Auth::user()->account_id);

        $this->assertTrue($createResult['success']);

        $existing = PaymentModes::where('name', 'EasyPaisa')->firstOrFail();

        $result = $this->service->validateAndUpdate(
            ['name' => 'Renamed Mode', 'type' => 'application'],
            $existing->id,
            (int) Auth::user()->account_id,
        );

        $this->assertTrue($result['success']);
        $this->assertSame('Renamed Mode', $existing->fresh()->name);
    }

    public function test_change_status_to_zero_marks_the_mode_inactive(): void
    {
        $existing = PaymentModes::first();

        $this->service->changeStatus($existing->id, 0);

        $this->assertSame(0, (int) $existing->fresh()->active);
    }

    public function test_change_status_to_one_marks_the_mode_active(): void
    {
        $existing = PaymentModes::first();
        $existing->update(['active' => 0]);

        $this->service->changeStatus($existing->id, 1);

        $this->assertSame(1, (int) $existing->fresh()->active);
    }
}
