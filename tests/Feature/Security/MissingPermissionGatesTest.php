<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Security audit 2026-06 — two endpoints were missing an authorization gate.
 *
 *   - DELETE /api/orders/{id}  (OrdersController::destroy) had no permission
 *     check — any authenticated user could delete orders.
 *   - GET /api/invoices/sms_logs/{id} (Admin\InvoicesController::showSMSLogs)
 *     had no gate AND no account scope — any user could read any tenant's
 *     invoice SMS history.
 */
class MissingPermissionGatesTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_order_destroy_requires_order_manage_permission(): void
    {
        // A user with no permissions (and not super-admin) must be refused.
        $this->actingAs(User::factory()->create(['account_id' => 1]));

        $this->deleteJson('/api/orders/1')->assertStatus(403);
    }

    public function test_invoice_sms_logs_requires_permission(): void
    {
        $this->actingAs(User::factory()->create(['account_id' => 1]));

        $this->getJson('/api/invoices/sms_logs/1')->assertStatus(403);
    }

    public function test_invoice_sms_logs_is_account_scoped(): void
    {
        // Permitted, but the invoice isn't in the caller's account → 404,
        // never a 200 with another tenant's SMS bodies.
        $this->actingAsUserWith('invoices.sms_log.view');

        $this->getJson('/api/invoices/sms_logs/999999')->assertStatus(404);
    }

    private function actingAsUserWith(string $permission): void
    {
        $this->createPermission($permission);
        $role = $this->createRole('GatedRole');
        $role->givePermissionTo($permission);

        $user = User::factory()->create(['account_id' => 1]);
        $this->assignRoleWithPivot($user, $role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user);
    }
}
