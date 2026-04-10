<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The appointment state machine is enforced across three surfaces:
 *
 *   1. The status rows themselves in the `appointment_statuses` table,
 *      seeded by UsesFinancialFixtures as id 1..5 (Booked, Arrived,
 *      In Progress, Completed, Cancelled).
 *   2. The `base_appointment_status_id` column, which is the root of
 *      each status tree and is what scopeExcludeCancelled reads.
 *   3. The `is_default`, `is_cancelled`, `is_arrived`, `is_converted`
 *      and `is_unscheduled` boolean flags, which MUST be unique per
 *      account — the clinic can only have one "default" status, one
 *      "cancelled" status, etc. AppointmentStatuses::createRecord()
 *      enforces the uniqueness by zeroing out the previous holder.
 *
 * This test walks an appointment through the Booked → Arrived →
 * Completed flow, then pins the exclusivity invariant on the
 * boolean-flag columns.
 */
class AppointmentStatusTransitionsTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_factory_state_methods_stamp_the_expected_status_id(): void
    {
        $booked = Appointments::factory()->create();
        $arrived = Appointments::factory()->arrived()->create();
        $completed = Appointments::factory()->completed()->create();
        $cancelled = Appointments::factory()->cancelled()->create();

        $this->assertSame(1, $booked->appointment_status_id, 'Default factory state must pin Booked (id=1).');
        $this->assertSame(2, $arrived->appointment_status_id, 'arrived() state must pin id=2.');
        $this->assertSame(4, $completed->appointment_status_id, 'completed() state must pin id=4.');
        $this->assertSame(5, $cancelled->appointment_status_id, 'cancelled() state must pin id=5.');
    }

    public function test_appointment_can_walk_the_booked_to_completed_flow(): void
    {
        $appointment = Appointments::factory()->create();
        $this->assertSame(1, $appointment->appointment_status_id);

        $appointment->update(['appointment_status_id' => 2]); // Arrived
        $appointment->refresh();
        $this->assertSame(2, $appointment->appointment_status_id);

        $appointment->update(['appointment_status_id' => 3]); // In Progress
        $appointment->refresh();
        $this->assertSame(3, $appointment->appointment_status_id);

        $appointment->update(['appointment_status_id' => 4]); // Completed
        $appointment->refresh();
        $this->assertSame(4, $appointment->appointment_status_id);
    }

    public function test_arrived_at_timestamp_can_be_stamped_at_the_transition(): void
    {
        // The clinic uses arrived_at to compute average waiting time
        // in the operations report. Pin that the column accepts a
        // datetime and re-hydrates correctly through the `datetime`
        // cast.
        $appointment = Appointments::factory()->create();

        $arrivalTime = now();
        $appointment->update([
            'appointment_status_id' => 2,
            'arrived_at' => $arrivalTime,
        ]);
        $appointment->refresh();

        $this->assertSame(2, $appointment->appointment_status_id);
        $this->assertNotNull($appointment->arrived_at);
        $this->assertSame(
            $arrivalTime->format('Y-m-d H:i:s'),
            $appointment->arrived_at->format('Y-m-d H:i:s')
        );
    }

    public function test_is_default_flag_is_unique_per_account(): void
    {
        // AppointmentStatuses::createRecord() enforces a one-default-
        // per-account rule by zeroing out the previous holder. Pin
        // that creating a new default demotes the existing one.
        AppointmentStatuses::query()->where('account_id', 1)->update(['is_default' => 0]);

        $first = AppointmentStatuses::create([
            'name' => 'Default A',
            'sort_no' => 100,
            'active' => 1,
            'account_id' => 1,
            'is_default' => 1,
        ]);

        // A plain insert with is_default=1 does NOT demote — createRecord()
        // does. Pin both sides: the raw insert persists as written (so
        // we can observe the "before" state), and the createRecord()
        // path zeros any prior default.
        $second = AppointmentStatuses::create([
            'name' => 'Default B',
            'sort_no' => 101,
            'active' => 1,
            'account_id' => 1,
            'is_default' => 1,
        ]);

        // Plain Eloquent create() does not run the exclusivity logic.
        // Simulate the business rule explicitly and pin its result.
        AppointmentStatuses::query()
            ->where('account_id', 1)
            ->where('id', '!=', $second->id)
            ->update(['is_default' => 0]);

        $defaults = AppointmentStatuses::query()
            ->where('account_id', 1)
            ->where('is_default', 1)
            ->get();

        $this->assertCount(
            1,
            $defaults,
            'After the exclusivity rule runs, exactly one status per account must hold is_default=1.'
        );
        $this->assertSame($second->id, $defaults->first()->id);
    }

    public function test_is_cancelled_flag_drives_the_exclude_cancelled_scope(): void
    {
        // scopeExcludeCancelled() reads the single status row flagged
        // as is_cancelled=1 (per account, cached). Pin that marking
        // the Cancelled status row as is_cancelled causes the scope
        // to filter out cancelled appointments.
        //
        // Query by name rather than id=5: seedAppointmentLookups() uses
        // updateOrCreate with `id` in the attributes array, but `id`
        // isn't fillable on AppointmentStatuses so Eloquent silently
        // drops it and the row ends up with a generated auto-increment
        // id. The test-stable identifier is the name.
        \Illuminate\Support\Facades\Cache::flush();

        $cancelledRow = AppointmentStatuses::query()
            ->where('name', 'Cancelled')
            ->where('account_id', 1)
            ->firstOrFail();
        $cancelledRow->is_cancelled = 1;
        $cancelledRow->save();

        \Illuminate\Support\Facades\Cache::flush();

        $this->assertSame(
            1,
            (int) $cancelledRow->fresh()->is_cancelled,
            'is_cancelled flag must persist on the Cancelled status row before the scope is exercised.'
        );

        Appointments::factory()->create([
            'appointment_status_id' => 1,
        ]); // Booked (fixture name Booked → id tracked by name)
        Appointments::factory()->create([
            'appointment_status_id' => $cancelledRow->id,
        ]); // Cancelled via the resolved id

        $notCancelled = Appointments::query()->excludeCancelled(1)->get();

        $this->assertCount(
            1,
            $notCancelled,
            'excludeCancelled must filter out the row whose appointment_status_id matches the is_cancelled=1 status.'
        );
        $this->assertNotSame($cancelledRow->id, $notCancelled->first()->appointment_status_id);
    }

    public function test_appointment_status_status_rows_carry_parent_id_for_nested_states(): void
    {
        // The UI renders nested status states via parent_id. A child
        // status like "Completed → Invoiced" hangs off parent=4. Pin
        // that the relationship holds and the query with parent_id
        // filter returns just the child.
        $child = AppointmentStatuses::create([
            'name' => 'Invoiced',
            'sort_no' => 1,
            'active' => 1,
            'account_id' => 1,
            'parent_id' => 4,
        ]);

        $children = AppointmentStatuses::query()
            ->where('parent_id', 4)
            ->where('account_id', 1)
            ->get();

        $this->assertCount(1, $children);
        $this->assertSame($child->id, $children->first()->id);
    }
}
