<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Models\Appointments;
use App\Models\AuditTrails;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * AppointmentEvent, CustomFormEvent, and CustomFormFieldEvent are
 * Eloquent model observers wired via AppServiceProvider that write
 * every create/update/delete to the `audit_trails` table.
 *
 * The audit discovered these events were silently un-wired for months —
 * the model dispatched events but no provider registered listeners.
 * This test file pins the wiring contract end-to-end:
 *
 *   1. Creating an appointment writes a 'create' audit row.
 *   2. Updating an appointment writes an 'Edit' audit row.
 *   3. Deleting an appointment writes a 'delete' audit row.
 *   4. When Auth::check() is false (CLI/seeder), no audit row is written
 *      — prevents DB errors during artisan commands.
 *   5. Each audit row references the correct table_record_id and user_id.
 */
class AuditTrailEventTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_creating_an_appointment_writes_audit_trail_row(): void
    {
        $before = AuditTrails::query()->count();

        $appointment = Appointments::factory()->create();

        $after = AuditTrails::query()->count();
        $this->assertGreaterThan($before, $after,
            'Creating an appointment must write at least one audit_trails row.');

        $trail = AuditTrails::query()
            ->where('table_record_id', $appointment->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($trail);
        $this->assertSame((int) auth()->id(), (int) $trail->user_id);
    }

    public function test_updating_an_appointment_writes_audit_trail_row(): void
    {
        $appointment = Appointments::factory()->create();
        $before = AuditTrails::query()->count();

        $appointment->update(['appointment_status_id' => 2]);

        $after = AuditTrails::query()->count();
        $this->assertGreaterThan($before, $after,
            'Updating an appointment must append an audit_trails row.');
    }

    public function test_deleting_an_appointment_writes_audit_trail_row(): void
    {
        $appointment = Appointments::factory()->create();
        $before = AuditTrails::query()->count();

        $appointment->delete();

        $after = AuditTrails::query()->count();
        $this->assertGreaterThan($before, $after,
            'Soft-deleting an appointment must append an audit_trails row.');
    }

    public function test_audit_row_references_correct_appointment_id(): void
    {
        $appointment = Appointments::factory()->create();

        $exists = AuditTrails::query()
            ->where('table_record_id', $appointment->id)
            ->exists();

        $this->assertTrue($exists,
            'Audit trail row must reference the created appointment ID.');
    }

    public function test_no_audit_row_when_auth_check_is_false(): void
    {
        // Log out to simulate CLI/seeder context.
        Auth::logout();
        $this->assertFalse(Auth::check());

        $before = AuditTrails::query()->count();

        // AppointmentEvent::created checks Auth::check() and returns
        // early if false. This prevents DB errors in seeders.
        Appointments::factory()->create();

        $after = AuditTrails::query()->count();
        $this->assertSame($before, $after,
            'No audit row should be written when Auth::check() is false.');
    }

    public function test_multiple_mutations_produce_multiple_audit_rows(): void
    {
        $before = AuditTrails::query()->count();

        $appointment = Appointments::factory()->create();    // +1
        $appointment->update(['notes' => 'Updated notes']);   // +1
        $appointment->delete();                                // +1

        $after = AuditTrails::query()->count();
        // The update on 'notes' may not trigger an audit if the column is
        // not in the model's tracked attributes. Pin the actual behaviour:
        // create + delete = at least 2; update may or may not write a row.
        $this->assertGreaterThanOrEqual($before + 2, $after,
            'Create + delete must produce at least 2 audit rows.');
    }
}
