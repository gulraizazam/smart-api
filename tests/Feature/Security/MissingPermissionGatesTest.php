<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Invoices;
use App\Models\Patients;
use App\Models\Settings;
use App\Models\SMSLogs;
use App\Models\User;
use App\Models\UserOperatorSettings;
use Illuminate\Support\Facades\DB;
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

    // ── Re-send invoice SMS (POST /api/invoices/send_logged_sms) ──
    // Security audit 2026-06: sendLogSMS was ungated AND looked the SMS log up
    // with an unscoped findOrFail, so any authenticated user could re-send any
    // tenant's invoice SMS (IDOR). These pin the gate + account scope.

    public function test_resend_invoice_sms_requires_permission(): void
    {
        // No permission → 403, even with a real SMS log id present.
        $log = $this->smsLogForAccount(1);
        $this->actingAs(User::factory()->create(['account_id' => 1]));

        $this->postJson('/api/invoices/send_logged_sms', ['id' => $log->id])
            ->assertStatus(403);
    }

    public function test_resend_invoice_sms_rejects_other_account(): void
    {
        // Permitted, but the SMS log's invoice belongs to another tenant → 404,
        // never a re-send of another tenant's message.
        $foreign = $this->smsLogForAccount(2);
        $this->actingAsUserWith('invoices.sms_log.view');

        $this->postJson('/api/invoices/send_logged_sms', ['id' => $foreign->id])
            ->assertStatus(404);
    }

    public function test_resend_invoice_sms_works_for_own_account(): void
    {
        // Permitted + own-account log → reaches the send path (status 1 in the
        // operator test-mode), proving the scope does NOT block legit access.
        $this->seedSmsOperatorTestMode(accountId: 1);
        $log = $this->smsLogForAccount(1);
        $this->actingAsUserWith('invoices.sms_log.view');

        $this->postJson('/api/invoices/send_logged_sms', ['id' => $log->id])
            ->assertOk()
            ->assertJson(['status' => 1]);
    }

    /**
     * An SMS log whose invoice belongs to $accountId. Built logged-out so
     * GuardsTenantBoundary doesn't restamp the foreign account back to 1.
     */
    private function smsLogForAccount(int $accountId): SMSLogs
    {
        $this->seedSecondAccount();

        $patient = Patients::factory()->create(['account_id' => $accountId]);
        $invoice = Invoices::factory()->create([
            'account_id' => $accountId,
            'patient_id' => $patient->id,
        ]);

        return SMSLogs::create([
            'to' => '03001234567',
            'text' => 'Your invoice is ready.',
            'invoice_id' => $invoice->id,
            'status' => 0,
        ]);
    }

    /** Operator config so resendSMS returns status 1 without real HTTP. */
    private function seedSmsOperatorTestMode(int $accountId): void
    {
        // resendSMS picks the operator row by id == settings.data and
        // account == invoice.account; test_mode short-circuits the send so
        // no real Telenor/Jazz HTTP call is made.
        $operator = UserOperatorSettings::create([
            'operator_id' => 2,
            'operator_name' => 'Jazz',
            'username' => 'test-user',
            'password' => 'test-pass',
            'mask' => 'CUTERA',
            'test_mode' => 1,
            'account_id' => $accountId,
        ]);

        // data != 1 → Jazz branch (which honours test_mode); point it at the
        // operator row just created.
        Settings::create([
            'name' => 'Current SMS Operator',
            'slug' => 'sys-current-sms-operator',
            'data' => (string) $operator->id,
            'account_id' => $accountId,
            'active' => 1,
        ]);
    }

    private function seedSecondAccount(): void
    {
        DB::table('accounts')->updateOrInsert(
            ['id' => 2],
            [
                'name' => 'Second Account',
                'email' => 'second@example.com',
                'contact' => '0000000001',
                'suspended' => '0',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
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
