<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\User;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The Doctor Incentive report is gated on its own dedicated
 * `doctor_incentive_report` permission so a role's access to it can be
 * toggled independently of Staff Wise Arrival. It previously shared the
 * `staff_wise_arrival_manage` gate, which meant a role granted Staff Wise
 * Arrival automatically saw Doctor Incentive too.
 *
 * Pins both directions on the two GET endpoints (which check the gate as
 * their first statement, with no FormRequest that could pre-empt with a
 * 422):
 *   1. A role with the OLD shared gate (`staff_wise_arrival_manage`) but
 *      not the new one is forbidden — goes red if the controller is
 *      reverted to the old slug.
 *   2. A role with `doctor_incentive_report` is allowed.
 *
 * A fresh (non Super-Admin) user is used because Super-Admin bypasses
 * every gate via the AppServiceProvider `Gate::before`.
 */
class DoctorIncentivePermissionTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    private function actingAsUserWith(string ...$permissions): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($permissions as $perm) {
            $this->createPermission($perm);
        }

        $role = $this->createRole('di-test-' . uniqid());
        $role->givePermissionTo($permissions);

        $user = User::factory()->create();
        $this->assignRoleWithPivot($user, $role);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user);
    }

    public function test_filters_forbidden_for_staff_wise_arrival_holder_without_new_gate(): void
    {
        $this->actingAsUserWith('staff_wise_arrival_manage');

        $this->getJson('/api/reports/doctor-incentive/filters')->assertForbidden();
    }

    public function test_doctors_forbidden_for_staff_wise_arrival_holder_without_new_gate(): void
    {
        $this->actingAsUserWith('staff_wise_arrival_manage');

        $this->getJson('/api/reports/doctor-incentive/doctors')->assertForbidden();
    }

    public function test_filters_allowed_with_doctor_incentive_report_permission(): void
    {
        $this->actingAsUserWith('doctor_incentive_report');

        $this->getJson('/api/reports/doctor-incentive/filters')->assertOk();
    }
}
