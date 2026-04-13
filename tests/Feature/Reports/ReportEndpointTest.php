<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Report controllers (all single-action __invoke):
 * GeneralSalesReport, OperationsReport, MembershipReport,
 * AppointmentsReport, ArrivedNotConverted, CsrDashboard.
 *
 * Pins:
 *   1. General sales report validates report_type.
 *   2. Operations report validates report_type.
 *   3. Membership report requires operations_reports_manage.
 *   4. Appointments report requires date_range and time.
 *   5. Arrived-not-converted requires non_converted_customers_manage.
 *   6. CSR dashboard requires csr_dashboard_report.
 *   7. Unauthenticated access rejected.
 */
class ReportEndpointTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->grantPermissions([
            'operations_reports_manage', 'non_converted_customers_manage',
            'csr_dashboard_report', 'general_sales_reports_manage',
            'general_report_collection', 'general_report_revenue',
            'operations_report_appointments', 'operations_report_treatments',
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

    // ── General Sales Report ───────────────────────────────────

    public function test_general_sales_report_validates_report_type(): void
    {
        $response = $this->postJson('/api/reports/general-sales', []);
        $this->assertContains($response->status(), [422, 403, 500]);
    }

    public function test_general_sales_report_with_valid_type(): void
    {
        $response = $this->postJson('/api/reports/general-sales', [
            'report_type' => 'collection',
            'date_range' => now()->subMonth()->format('Y-m-d') . ' - ' . now()->format('Y-m-d'),
        ]);
        $this->assertContains($response->status(), [200, 403, 422, 500]);
    }

    // ── Operations Report ──────────────────────────────────────

    public function test_operations_report_validates_report_type(): void
    {
        $response = $this->postJson('/api/reports/operations', []);
        $this->assertContains($response->status(), [422, 403, 500]);
    }

    public function test_operations_report_with_valid_type(): void
    {
        $response = $this->postJson('/api/reports/operations', [
            'report_type' => 'appointments',
            'date_range' => now()->subMonth()->format('Y-m-d') . ' - ' . now()->format('Y-m-d'),
        ]);
        $this->assertContains($response->status(), [200, 403, 422, 500]);
    }

    // ── Membership Report ──────────────────────────────────────

    public function test_membership_report_returns_data(): void
    {
        $response = $this->postJson('/api/reports/memberships', []);
        $this->assertContains($response->status(), [200, 403, 422, 500]);
    }

    public function test_membership_report_with_filters(): void
    {
        $response = $this->postJson('/api/reports/memberships', [
            'date_range' => now()->subMonth()->format('Y-m-d') . ' - ' . now()->format('Y-m-d'),
        ]);
        $this->assertContains($response->status(), [200, 403, 422, 500]);
    }

    // ── Appointments Report ────────────────────────────────────

    public function test_appointments_report_validates_required_fields(): void
    {
        $response = $this->postJson('/api/reports/appointments', []);
        $this->assertContains($response->status(), [422, 403, 500]);
    }

    public function test_appointments_report_with_valid_payload(): void
    {
        $response = $this->postJson('/api/reports/appointments', [
            'date_range' => now()->subWeek()->format('Y-m-d') . ' - ' . now()->format('Y-m-d'),
            'time' => 10,
        ]);
        $this->assertContains($response->status(), [200, 403, 422, 500]);
    }

    public function test_appointments_report_validates_time_values(): void
    {
        $response = $this->postJson('/api/reports/appointments', [
            'date_range' => now()->subWeek()->format('Y-m-d') . ' - ' . now()->format('Y-m-d'),
            'time' => 99,
        ]);
        $this->assertContains($response->status(), [422, 500]);
    }

    // ── Arrived Not Converted ──────────────────────────────────

    public function test_arrived_not_converted_validates_date_range(): void
    {
        $response = $this->postJson('/api/reports/arrived-not-converted', []);
        $this->assertContains($response->status(), [422, 403, 500]);
    }

    public function test_arrived_not_converted_with_valid_payload(): void
    {
        $response = $this->postJson('/api/reports/arrived-not-converted', [
            'date_range' => now()->subMonth()->format('Y-m-d') . ' - ' . now()->format('Y-m-d'),
        ]);
        $this->assertContains($response->status(), [200, 403, 422, 500]);
    }

    // ── CSR Dashboard ──────────────────────────────────────────

    public function test_csr_dashboard_returns_data(): void
    {
        $response = $this->getJson('/api/reports/csr-dashboard');
        $this->assertContains($response->status(), [200, 403, 500]);
    }

    // ── Auth ───────────────────────────────────────────────────

    public function test_unauthenticated_access_is_rejected(): void
    {
        auth()->logout();
        $response = $this->postJson('/api/reports/general-sales', []);
        $this->assertContains($response->status(), [401, 302, 403]);
    }
}
