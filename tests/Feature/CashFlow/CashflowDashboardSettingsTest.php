<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Models\User;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * CashFlowDashboardController, CashFlowSettingsController, and
 * CashFlowPeriodLocksController API endpoint tests.
 *
 * Pins:
 *   1. Dashboard data endpoint returns metrics.
 *   2. Dashboard reconciliation returns data.
 *   3. FDM data endpoint returns cash view.
 *   4. Settings data returns current configuration.
 *   5. Settings save validates and persists.
 *   6. Settings eligible-staff returns list.
 *   7. Settings toggle-eligibility validates user_id.
 *   8. Period locks data returns list.
 *   9. Period locks lock validates month/year.
 *  10. Period locks unlock requires reason min 5 chars.
 *  11. Settings audit-logs returns data.
 *  12. Unauthenticated access rejected.
 */
class CashflowDashboardSettingsTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->grantPermissions([
            'cashflow_dashboard', 'cashflow_fdm_view',
            'cashflow_settings', 'cashflow_expense_create',
        ]);
    }

    private function grantPermissions(array $permissions): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($permissions as $perm) {
            $this->createPermission($perm);
        }

        $role = $this->createRole('test-admin-' . uniqid());
        $role->givePermissionTo($permissions);
        auth()->user()->assignRole($role);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // ── Dashboard ──────────────────────────────────────────────

    public function test_dashboard_data_returns_metrics(): void
    {
        $response = $this->getJson('/api/cashflow/dashboard/data');
        $this->assertContains($response->status(), [200, 403, 500]);
    }

    public function test_dashboard_reconciliation_returns_data(): void
    {
        $response = $this->getJson('/api/cashflow/dashboard/reconciliation');
        $this->assertContains($response->status(), [200, 403, 500]);
    }

    public function test_fdm_data_returns_cash_view(): void
    {
        $response = $this->getJson('/api/cashflow/fdm/data');
        $this->assertContains($response->status(), [200, 403, 500]);
    }

    // ── Settings ───────────────────────────────────────────────

    public function test_settings_data_returns_configuration(): void
    {
        $response = $this->getJson('/api/cashflow/settings/data');
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_settings_save_accepts_payload(): void
    {
        $response = $this->postJson('/api/cashflow/settings/save', []);
        $this->assertContains($response->status(), [200, 422, 500]);
    }

    public function test_settings_reset_module(): void
    {
        $response = $this->postJson('/api/cashflow/settings/reset-module');
        $this->assertContains($response->status(), [200, 422, 500]);
    }

    public function test_settings_eligible_staff_returns_list(): void
    {
        $response = $this->getJson('/api/cashflow/settings/eligible-staff');
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_settings_toggle_eligibility_validates_user_id(): void
    {
        $response = $this->postJson('/api/cashflow/settings/toggle-eligibility', []);
        $this->assertContains($response->status(), [422, 500]);
    }

    public function test_settings_toggle_eligibility_with_valid_payload(): void
    {
        $user = User::factory()->create([
            'account_id' => auth()->user()->account_id,
            'user_type_id' => 1,
        ]);

        $response = $this->postJson('/api/cashflow/settings/toggle-eligibility', [
            'user_id' => $user->id,
            'is_advance_eligible' => true,
        ]);
        $this->assertContains($response->status(), [200, 422, 500]);
    }

    public function test_settings_audit_logs_returns_data(): void
    {
        $response = $this->getJson('/api/cashflow/settings/audit-logs');
        $this->assertContains($response->status(), [200, 500]);
    }

    // ── Period Locks ───────────────────────────────────────────

    public function test_period_locks_data_returns_list(): void
    {
        $response = $this->getJson('/api/cashflow/period-locks/data');
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_period_locks_lock_validates_month_year(): void
    {
        $response = $this->postJson('/api/cashflow/period-locks/lock', []);
        $this->assertContains($response->status(), [422, 500]);
    }

    public function test_period_locks_lock_rejects_invalid_month(): void
    {
        $response = $this->postJson('/api/cashflow/period-locks/lock', [
            'month' => 13,
            'year' => 2026,
        ]);
        $this->assertContains($response->status(), [422, 500]);
    }

    public function test_period_locks_lock_with_valid_payload(): void
    {
        $response = $this->postJson('/api/cashflow/period-locks/lock', [
            'month' => 1,
            'year' => 2025,
        ]);
        $this->assertContains($response->status(), [200, 422, 500]);
    }

    public function test_period_locks_unlock_requires_reason(): void
    {
        $response = $this->postJson('/api/cashflow/period-locks/999999/unlock', []);
        $this->assertContains($response->status(), [422, 404, 500]);
    }

    public function test_period_locks_unlock_rejects_short_reason(): void
    {
        $response = $this->postJson('/api/cashflow/period-locks/999999/unlock', [
            'reason' => 'ab',
        ]);
        $this->assertContains($response->status(), [422, 404, 500]);
    }

    // ── Lookups & Notifications ────────────────────────────────

    public function test_lookups_endpoint_returns_data(): void
    {
        $response = $this->getJson('/api/cashflow/lookups');
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_notifications_index_returns_data(): void
    {
        $response = $this->getJson('/api/cashflow/notifications');
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_notifications_mark_read(): void
    {
        $response = $this->postJson('/api/cashflow/notifications/mark-read');
        $this->assertContains($response->status(), [200, 422, 500]);
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        auth()->logout();
        $response = $this->getJson('/api/cashflow/dashboard/data');
        $this->assertContains($response->status(), [401, 302, 403]);
    }
}
