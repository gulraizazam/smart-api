<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\PaymentModes;
use App\Models\User;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the order-form payment-mode lookup (`/api/orders/getpaymentmodes`).
 *
 * The SPA order dialog used to reuse the admin `/payment_modes/datatable`
 * endpoint, which is gated on `payment_modes.list.view`. An operator who can
 * create orders but doesn't administer payment modes (e.g. FDM) got a 403 and
 * an empty payment-mode dropdown — they couldn't complete an order.
 *
 * The lookup mirrors getProducts/getDiscounts: login-only, account-scoped,
 * active modes only.
 */
class OrderPaymentModesLookupTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_lookup_returns_active_account_modes_without_admin_permission(): void
    {
        // A plain order-creating user — NO payment_modes.list.view.
        $this->actingAs(User::factory()->create(['account_id' => 1]));

        $card = PaymentModes::factory()->create(['account_id' => 1, 'name' => 'CardX', 'active' => 1, 'sort_number' => 2]);
        $retired = PaymentModes::factory()->create(['account_id' => 1, 'name' => 'Retired', 'active' => 0]); // inactive → excluded

        $res = $this->getJson('/api/orders/getpaymentmodes');

        $res->assertOk();
        $ids = collect($res->json('data.payment_modes'))->pluck('id')->all();

        $this->assertContains($card->id, $ids, 'Active account mode must be returned.');
        $this->assertNotContains($retired->id, $ids, 'Inactive modes must be excluded.');
    }

    public function test_legacy_admin_datatable_forbids_the_same_user(): void
    {
        // Documents WHY the lookup exists: the previously-reused admin endpoint
        // 403s a user lacking payment_modes.list.view.
        $this->actingAs(User::factory()->create(['account_id' => 1]));

        $this->postJson('/api/payment_modes/datatable', ['pagination' => ['page' => 1, 'perpage' => 100]])
            ->assertStatus(403);
    }
}
