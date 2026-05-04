<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use App\Models\AppointmentStatuses;
use App\Models\Appointments;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the dropdown filter behind the SPA's status-change dialog.
 *
 * `GET /api/appointments/show/status?id={id}` returns the list of
 * status options the operator can pick. Auto-only statuses
 * (`is_arrived`, `is_converted`, `is_unscheduled`) MUST NOT appear in
 * that list — they are set by system events (invoice paid, package
 * payment, missing schedule) and cannot be selected manually.
 *
 * Pre-fix the legacy `getBaseActiveSorted` model method excluded
 * `Arrived` by name and `is_converted=1` by flag, but missed
 * `is_unscheduled` and was brittle to renames. The controller now
 * filters by the three flag fields explicitly. This test pins the
 * contract so a future refactor can't quietly leak them back in.
 */
class AppointmentStatusDropdownTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_base_status_dropdown_excludes_auto_only_statuses(): void
    {
        $admin = $this->actingAsAdmin();

        $manual = AppointmentStatuses::create([
            'name' => 'Manual Pending',
            'slug' => 'manual-pending-test',
            'account_id' => $admin->account_id,
            'active' => 1,
            'sort_no' => 1,
        ]);
        $arrived = AppointmentStatuses::create([
            'name' => 'Arrived (test)',
            'slug' => 'arrived-test-dropdown',
            'account_id' => $admin->account_id,
            'active' => 1,
            'is_arrived' => 1,
            'sort_no' => 2,
        ]);
        $converted = AppointmentStatuses::create([
            'name' => 'Converted (test)',
            'slug' => 'converted-test-dropdown',
            'account_id' => $admin->account_id,
            'active' => 1,
            'is_converted' => 1,
            'sort_no' => 3,
        ]);
        $unscheduled = AppointmentStatuses::create([
            'name' => 'Un-Scheduled (test)',
            'slug' => 'unscheduled-test-dropdown',
            'account_id' => $admin->account_id,
            'active' => 1,
            'is_unscheduled' => 1,
            'sort_no' => 4,
        ]);
        $cancelled = AppointmentStatuses::create([
            'name' => 'Cancelled (test)',
            'slug' => 'cancelled-test-dropdown',
            'account_id' => $admin->account_id,
            'active' => 1,
            'is_cancelled' => 1,
            'sort_no' => 5,
        ]);

        $appointment = Appointments::factory()->create([
            'appointment_status_id' => $manual->id,
            'account_id' => $admin->account_id,
        ]);

        $response = $this->getJson("/api/appointments/show/status?id={$appointment->id}");

        $response->assertOk();
        $bases = $response->json('data.base_appointment_statuses') ?? [];

        $this->assertArrayHasKey(
            (string) $manual->id,
            $bases,
            'Manual statuses must appear in the dropdown.',
        );
        $this->assertArrayHasKey(
            (string) $cancelled->id,
            $bases,
            'is_cancelled is operator-initiated — must remain selectable.',
        );

        $this->assertArrayNotHasKey(
            (string) $arrived->id,
            $bases,
            'is_arrived statuses are auto-only — must NOT appear in the manual dropdown.',
        );
        $this->assertArrayNotHasKey(
            (string) $converted->id,
            $bases,
            'is_converted statuses are auto-only — must NOT appear in the manual dropdown.',
        );
        $this->assertArrayNotHasKey(
            (string) $unscheduled->id,
            $bases,
            'is_unscheduled statuses are auto-only — must NOT appear in the manual dropdown.',
        );
    }

    public function test_inactive_statuses_remain_excluded(): void
    {
        // Existing safety net — orthogonal to the auto-only filter but
        // worth pinning together so the dropdown contract is one place.
        $admin = $this->actingAsAdmin();

        $inactive = AppointmentStatuses::create([
            'name' => 'Inactive Test',
            'slug' => 'inactive-test-dropdown',
            'account_id' => $admin->account_id,
            'active' => 0,
            'sort_no' => 99,
        ]);
        $manual = AppointmentStatuses::create([
            'name' => 'Active Manual Test',
            'slug' => 'active-manual-test-dropdown',
            'account_id' => $admin->account_id,
            'active' => 1,
            'sort_no' => 1,
        ]);

        $appointment = Appointments::factory()->create([
            'appointment_status_id' => $manual->id,
            'account_id' => $admin->account_id,
        ]);

        $response = $this->getJson("/api/appointments/show/status?id={$appointment->id}");
        $bases = $response->json('data.base_appointment_statuses') ?? [];

        $this->assertArrayNotHasKey((string) $inactive->id, $bases);
        $this->assertArrayHasKey((string) $manual->id, $bases);
    }
}
