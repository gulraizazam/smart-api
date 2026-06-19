<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the report-endpoint gates found in the 2026-06-19 QA (#15 open report
 * APIs + #16 hidden reports). Each of these report data endpoints either had no
 * server gate at all (`authorize(): return true`) or checked a slug that does
 * not exist in the catalog, so the data was reachable by any authenticated user
 * (or, for activity-logs, locked to Super-Admin via a wrong slug). The fix gates
 * each on its REAL catalog slug:
 *   - appointments  → appointment_reports_manage   (route middleware)
 *   - staff-arrival → staff_wise_arrival_manage    (route middleware)
 *   - tax calc      → operations_reports_operations_tax_calculation_report (route mw)
 *   - activity-logs → activity_logs_report          (controller slug fix; was activity_logs_manage)
 *
 * Teeth: drop a route's permission middleware (or revert the activity-logs slug)
 * and that row's "requires permission" / "holder reaches it" case goes red.
 */
class ReportGateTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    /** route URI | the real catalog slug that should gate it */
    public static function reportEndpoints(): array
    {
        return [
            'appointments' => ['api/reports/appointments', 'appointment_reports_manage'],
            'staff_arrival' => ['api/reports/staff-wise-arrival', 'staff_wise_arrival_manage'],
            'tax_calculation' => ['api/invoices/calculate-amounts', 'operations_reports_operations_tax_calculation_report'],
            'activity_logs' => ['api/reports/activity-logs', 'activity_logs_report'],
            'doctor_revenue' => ['api/reports/doctor-revenue', 'doctor_revenue_manage'],
            'doctor_incentive' => ['api/reports/doctor-incentive', 'doctor_incentive_report'],
            'wrong_conversions' => ['api/wrong-conversions/reset-all', 'wrong_conversions_manage'],
        ];
    }

    private function actAs(array $perms): void
    {
        foreach ($perms as $p) {
            $this->createPermission($p);
        }
        $role = $this->createRole('rpt_'.uniqid());
        if ($perms !== []) {
            $role->givePermissionTo($perms);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create();
        $this->assignRoleWithPivot($user, $role);
        $this->actingAs($user);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    #[DataProvider('reportEndpoints')]
    public function test_report_requires_its_permission(string $route, string $slug): void
    {
        $this->createPermission($slug); // exists in catalog, but NOT granted
        $this->actAs([]);

        $this->postJson('/'.$route, [])->assertStatus(403);
    }

    #[DataProvider('reportEndpoints')]
    public function test_permission_holder_is_not_blocked(string $route, string $slug): void
    {
        // A holder of the real slug must pass the gate. Body is empty, so the
        // request may 422 on validation — we only assert the gate does not 403.
        $this->actAs([$slug]);

        $response = $this->postJson('/'.$route, []);

        $this->assertNotSame(403, $response->status(), "holder of {$slug} must not be 403'd on {$route}");
    }
}
