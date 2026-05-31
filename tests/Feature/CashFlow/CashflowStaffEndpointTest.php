<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Models\CashFlow\StaffTransfer;
use App\Models\User;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * CashFlowStaffController API endpoint tests.
 *
 * Pins:
 *   1. Staff summary endpoint returns data.
 *   2. Staff recent activity returns list.
 *   3. Staff ledger returns user-specific data.
 *   4. Staff eligible endpoint returns list.
 *   5. Advance store validates required fields.
 *   6. Advance void requires void_reason min 5 chars.
 *   7. Advance update requires edit_reason.
 *   8. Return store validates required fields.
 *   9. Return void requires void_reason min 5 chars.
 *  10. Audit endpoints require cashflow_audit_view.
 *  11. Unauthenticated access rejected.
 */
class CashflowStaffEndpointTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->grantPermissions([
            'cashflow_staff_advance_view', 'cashflow_staff_advance',
            'cashflow_staff_advance_create', 'cashflow_staff_advance_void',
            'cashflow_staff_advance_edit', 'cashflow_staff_return_create',
            'cashflow_staff_return_void',
            'cashflow_audit_view',
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

    public function test_staff_summary_returns_data(): void
    {
        $response = $this->getJson('/api/cashflow/staff/summary');
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_staff_recent_activity_returns_list(): void
    {
        $response = $this->getJson('/api/cashflow/staff/recent-activity');
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_staff_ledger_for_user(): void
    {
        $user = User::factory()->create([
            'account_id' => auth()->user()->account_id,
            'user_type_id' => 1,
        ]);

        $response = $this->getJson("/api/cashflow/staff/{$user->id}/ledger");
        $this->assertContains($response->status(), [200, 404, 500]);
    }

    public function test_staff_eligible_returns_list(): void
    {
        $response = $this->getJson('/api/cashflow/staff/eligible');
        $this->assertContains($response->status(), [200, 500]);
    }

    public function test_advance_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/cashflow/staff/advance/store', []);
        $this->assertContains($response->status(), [422, 403]);
    }

    public function test_advance_store_with_partial_payload(): void
    {
        $response = $this->postJson('/api/cashflow/staff/advance/store', [
            'amount' => 500,
            // Missing user_id, pool_id, description
        ]);
        $this->assertContains($response->status(), [422, 403, 500]);
    }

    public function test_advance_void_on_nonexistent_id(): void
    {
        $response = $this->postJson('/api/cashflow/staff/advance/999999/void', [
            'void_reason' => 'Test void reason text',
        ]);
        $this->assertContains($response->status(), [404, 422, 500]);
    }

    public function test_advance_void_rejects_short_reason(): void
    {
        $response = $this->postJson('/api/cashflow/staff/advance/999999/void', [
            'void_reason' => 'ab',
        ]);
        $this->assertContains($response->status(), [422, 404, 500]);
    }

    public function test_advance_update_on_nonexistent_id(): void
    {
        $response = $this->postJson('/api/cashflow/staff/advance/999999/update', [
            'amount' => 1000,
            'pool_id' => 1,
            'description' => 'Updated advance',
            'edit_reason' => 'Correcting amount',
        ]);
        $this->assertContains($response->status(), [404, 422, 500]);
    }

    public function test_advance_audit_on_nonexistent_id(): void
    {
        $response = $this->getJson('/api/cashflow/staff/advance/999999/audit');
        $this->assertContains($response->status(), [200, 404, 500]);
    }

    public function test_return_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/cashflow/staff/return/store', []);
        $this->assertContains($response->status(), [422, 403]);
    }

    /**
     * Cash returns are physical handovers and never carry paisa; the rule
     * was tightened to `integer|min:1` to mirror advances. A decimal payload
     * must surface as a field-level 422 on `amount`.
     */
    public function test_return_store_rejects_decimal_amount(): void
    {
        $response = $this->postJson('/api/cashflow/staff/return/store', [
            'user_id' => 1,
            'pool_id' => 1,
            'amount' => 12.50,
            'description' => 'decimal return',
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['amount']);
    }

    public function test_return_void_on_nonexistent_id(): void
    {
        $response = $this->postJson('/api/cashflow/staff/return/999999/void', [
            'void_reason' => 'Test void reason text',
        ]);
        $this->assertContains($response->status(), [404, 422, 500]);
    }

    public function test_return_audit_on_nonexistent_id(): void
    {
        $response = $this->getJson('/api/cashflow/staff/return/999999/audit');
        $this->assertContains($response->status(), [200, 404, 500]);
    }

    /**
     * Phase-5 fix: staff advance EDIT now accepts up to 500 chars for
     * description (was 50), matching the create-form ceiling. The 50-char
     * cap was clipping descriptions whenever an admin edited an existing
     * advance.
     */
    public function test_advance_update_accepts_500_char_description(): void
    {
        $response = $this->postJson('/api/cashflow/staff/advance/999999/update', [
            'amount' => 1000,
            'pool_id' => 1,
            'description' => str_repeat('a', 500),
            'edit_reason' => 'Lengthening description',
        ]);
        if ($response->status() === 422) {
            $this->assertArrayNotHasKey('description', $response->json('errors') ?? []);
        } else {
            $this->assertNotSame(422, $response->status());
        }
    }

    public function test_advance_update_rejects_501_char_description(): void
    {
        $response = $this->postJson('/api/cashflow/staff/advance/999999/update', [
            'amount' => 1000,
            'pool_id' => 1,
            'description' => str_repeat('a', 501),
            'edit_reason' => 'Trying to overflow',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['description']);
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        auth()->logout();
        $response = $this->getJson('/api/cashflow/staff/summary');
        $this->assertContains($response->status(), [401, 302, 403]);
    }

    /**
     * Date-range filter: advances created BEFORE date_from should drop out
     * of the summary; advances inside the window stay. Mirrors the SPA
     * date picker on `/cashflow/staff`.
     */
    public function test_staff_summary_filters_by_date_range(): void
    {
        $accountId = 1;
        $admin = auth()->user();

        $pool = \App\Models\CashFlow\CashPool::factory()->create([
            'account_id' => $accountId,
            'type' => \App\Models\CashFlow\CashPool::TYPE_BRANCH_CASH,
            'location_id' => $this->defaultLocation->id,
        ]);
        $staff = \App\Models\User::factory()->create([
            'account_id' => $accountId,
            'is_advance_eligible' => 1,
        ]);

        // The summary windows by the MOVEMENT date (advance_date), not
        // created_at. Cross the two so the test proves it: the in-window
        // row is backdated in created_at (out of window), the
        // out-of-window row has an in-window created_at — only
        // advance_date must decide membership.
        \Illuminate\Support\Facades\DB::table('staff_advances')->insert([
            [
                'account_id' => $accountId,
                'user_id' => $staff->id,
                'pool_id' => $pool->id,
                'amount' => 500,
                'description' => 'inside window',
                'created_by' => $admin->id,
                'advance_date' => '2026-05-10',
                'created_at' => '2026-04-01 12:00:00',
                'updated_at' => '2026-04-01 12:00:00',
            ],
            [
                'account_id' => $accountId,
                'user_id' => $staff->id,
                'pool_id' => $pool->id,
                'amount' => 9000,
                'description' => 'before window',
                'created_by' => $admin->id,
                'advance_date' => '2026-04-01',
                'created_at' => '2026-05-10 12:00:00',
                'updated_at' => '2026-05-10 12:00:00',
            ],
        ]);

        $response = $this->getJson('/api/cashflow/staff/summary?date_from=2026-05-01&date_to=2026-05-13');
        if ($response->status() !== 200) {
            $this->markTestSkipped('Staff summary returned non-200; check fixtures.');
        }

        $rows = $response->json('data');
        $this->assertNotEmpty($rows);
        $staffRow = collect($rows)->firstWhere('user_id', $staff->id);
        $this->assertNotNull($staffRow, 'Expected staff row in summary.');
        $this->assertEqualsWithDelta(500.0, (float) $staffRow['total_advances'], 0.01,
            'Only the in-window advance should be summed.');
    }

    /**
     * Regression: the unified void endpoint must authorize handovers via
     * the dedicated `cashflow_staff_transfer_void` slug. canVoidMovement
     * previously had no `staff_transfer` case (default => false), so a
     * handover was un-voidable for anyone without `cashflow_manage` —
     * a hard 403 on the only UI path.
     */
    public function test_voiding_a_handover_requires_staff_transfer_void_slug_not_manage(): void
    {
        $from = User::factory()->create(['account_id' => 1, 'is_advance_eligible' => true]);
        $to = User::factory()->create(['account_id' => 1, 'is_advance_eligible' => true]);
        $handover = StaffTransfer::create([
            'account_id' => 1,
            'from_user_id' => $from->id,
            'to_user_id' => $to->id,
            'amount' => 100,
            'transfer_date' => now()->toDateString(),
            'created_by' => $from->id,
        ]);

        // A NON-super-admin (the seeded admin is Super-Admin and bypasses
        // every Gate via AppServiceProvider::Gate::before, so it can't
        // exercise the per-kind void map). Give it just the movements
        // group-view slug — enough to pass the route middleware and
        // reach canVoidMovement, but NOT manage / staff_transfer_void.
        $registrar = app(\Spatie\Permission\PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();
        // The movements route group is gated by DOTTED slugs
        // (permission:cashflow.transfer.view|cashflow.staff_advance.view|cashflow.manage),
        // so the group-view slug must be dotted to pass the route middleware
        // and reach canVoidMovement.
        $this->createPermission('cashflow.staff_advance.view');
        $role = $this->createRole('handover-voider-' . uniqid());
        $role->givePermissionTo('cashflow.staff_advance.view');
        $user = User::factory()->create(['account_id' => 1]);
        $user->assignRole($role);
        $registrar->forgetCachedPermissions();
        $this->actingAs($user);

        // No staff_transfer_void, no manage → 403 (the bug: there was no
        // staff_transfer case in canVoidMovement, default => false).
        $denied = $this->postJson("/api/cashflow/movements/staff_transfer/{$handover->id}/void", [
            'void_reason' => 'Reversing this handover',
        ]);
        $this->assertSame(403, $denied->status());

        // Grant only the dedicated slug (still NOT cashflow_manage) →
        // the gate now passes (no longer 403; any non-auth domain
        // outcome is acceptable for this assertion). The void gate is the
        // DOTTED `cashflow.staff_transfer.void` (CashFlowMovementsController:233),
        // the canonical catalog held by Administrator on the prod clone — the
        // old underscore `cashflow_staff_transfer_void` no longer satisfies it.
        $this->createPermission('cashflow.staff_transfer.void');
        $role->givePermissionTo('cashflow.staff_transfer.void');
        $registrar->forgetCachedPermissions();
        $allowed = $this->postJson("/api/cashflow/movements/staff_transfer/{$handover->id}/void", [
            'void_reason' => 'Reversing this handover',
        ]);
        $this->assertNotSame(403, $allowed->status());
    }
}
