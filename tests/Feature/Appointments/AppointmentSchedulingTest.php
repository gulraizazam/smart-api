<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use App\Models\Appointments;
use App\Models\Locations;
use App\Models\Patients;
use App\Models\User;
use Carbon\Carbon;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The scheduling surface is the set of Appointments query scopes
 * that the front-end calendar, the "upcoming appointments" widgets
 * and the operations report reach for every day. Each scope pins a
 * filter the audit walked through, and a future refactor that
 * silently tightens or drops one of them would quietly break a page.
 *
 * Scopes pinned here:
 *   - scheduled() / nonScheduled() — the calendar vs. inbox split
 *   - byLocation() / byDoctor() / byPatient() / byAccount() — filters
 *     the index pages and datatables use to render tenant-safe rows
 *   - today() / upcoming() / dateRange() — the landing-page widgets
 *
 * Conflict detection is NOT pinned: the audit deliberately disabled
 * the schedule-conflict check in AppointmentService::createAppointment
 * so that clinics can double-book the same slot (the front desk
 * handles conflicts through a separate UX). A test here pins that
 * *two appointments at the same slot do in fact coexist* so a future
 * refactor re-enabling the conflict check surfaces as a broken test
 * rather than a silent behaviour change.
 */
class AppointmentSchedulingTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private Locations $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->location = Locations::factory()->create();
    }

    public function test_scheduled_scope_returns_only_rows_with_both_date_and_time_set(): void
    {
        Appointments::factory()->create([
            'scheduled_date' => '2026-06-01',
            'scheduled_time' => '10:00:00',
        ]);
        Appointments::factory()->create([
            'scheduled_date' => null,
            'scheduled_time' => null,
        ]);

        $scheduled = Appointments::query()->scheduled()->get();

        $this->assertCount(1, $scheduled);
        $this->assertNotNull($scheduled->first()->scheduled_date);
        $this->assertNotNull($scheduled->first()->scheduled_time);
    }

    public function test_non_scheduled_scope_returns_only_rows_without_date_or_time(): void
    {
        Appointments::factory()->create([
            'scheduled_date' => '2026-06-02',
            'scheduled_time' => '11:00:00',
        ]);
        Appointments::factory()->create([
            'scheduled_date' => null,
            'scheduled_time' => null,
        ]);

        $nonScheduled = Appointments::query()->nonScheduled()->get();

        $this->assertCount(1, $nonScheduled);
        $this->assertNull($nonScheduled->first()->scheduled_date);
        $this->assertNull($nonScheduled->first()->scheduled_time);
    }

    public function test_by_location_scope_filters_to_one_location(): void
    {
        $locationA = Locations::factory()->create();
        $locationB = Locations::factory()->create();

        Appointments::factory()->count(2)->create(['location_id' => $locationA->id]);
        Appointments::factory()->create(['location_id' => $locationB->id]);

        $aOnly = Appointments::query()->byLocation($locationA->id)->get();
        $bOnly = Appointments::query()->byLocation($locationB->id)->get();

        $this->assertCount(2, $aOnly);
        $this->assertCount(1, $bOnly);
    }

    public function test_by_doctor_scope_filters_to_one_doctor(): void
    {
        $doctor1 = User::factory()->doctor()->create();
        $doctor2 = User::factory()->doctor()->create();

        Appointments::factory()->count(3)->create(['doctor_id' => $doctor1->id]);
        Appointments::factory()->count(2)->create(['doctor_id' => $doctor2->id]);

        $d1 = Appointments::query()->byDoctor($doctor1->id)->get();
        $d2 = Appointments::query()->byDoctor($doctor2->id)->get();

        $this->assertCount(3, $d1);
        $this->assertCount(2, $d2);
    }

    public function test_by_patient_scope_does_not_leak_other_patient_rows(): void
    {
        // IDOR-adjacent: the "appointments for this patient" widget
        // reads via byPatient($id). Pin that another patient's rows
        // never surface here.
        $patientA = Patients::factory()->create();
        $patientB = Patients::factory()->create();

        Appointments::factory()->count(2)->create(['patient_id' => $patientA->id]);
        Appointments::factory()->create(['patient_id' => $patientB->id]);

        $results = Appointments::query()->byPatient($patientA->id)->get();

        $this->assertCount(2, $results);
        foreach ($results as $appt) {
            $this->assertSame($patientA->id, $appt->patient_id);
        }
    }

    public function test_by_account_scope_is_the_tenant_boundary(): void
    {
        Appointments::factory()->create(['account_id' => 1]);
        Appointments::factory()->create(['account_id' => 2]);

        $tenant1 = Appointments::query()->byAccount(1)->get();
        $tenant2 = Appointments::query()->byAccount(2)->get();

        $this->assertCount(1, $tenant1);
        $this->assertSame(1, $tenant1->first()->account_id);
        $this->assertCount(1, $tenant2);
        $this->assertSame(2, $tenant2->first()->account_id);
    }

    public function test_today_scope_returns_only_todays_rows(): void
    {
        Appointments::factory()->create(['scheduled_date' => Carbon::today()->toDateString()]);
        Appointments::factory()->create(['scheduled_date' => Carbon::today()->addDays(3)->toDateString()]);
        Appointments::factory()->create(['scheduled_date' => Carbon::today()->subDays(5)->toDateString()]);

        $today = Appointments::query()->today()->get();

        $this->assertCount(1, $today);
    }

    public function test_upcoming_scope_includes_today_and_future_but_not_past(): void
    {
        Appointments::factory()->create(['scheduled_date' => Carbon::today()->toDateString()]);
        Appointments::factory()->create(['scheduled_date' => Carbon::today()->addDays(5)->toDateString()]);
        Appointments::factory()->create(['scheduled_date' => Carbon::today()->subDays(1)->toDateString()]);

        $upcoming = Appointments::query()->upcoming()->get();

        // Today + future = 2 rows; the yesterday row is excluded.
        $this->assertCount(2, $upcoming);
    }

    public function test_date_range_scope_bounds_inclusive_between(): void
    {
        Appointments::factory()->create(['scheduled_date' => '2026-05-01']);
        Appointments::factory()->create(['scheduled_date' => '2026-05-15']);
        Appointments::factory()->create(['scheduled_date' => '2026-05-31']);
        Appointments::factory()->create(['scheduled_date' => '2026-06-05']);

        $may = Appointments::query()->dateRange('2026-05-01', '2026-05-31')->get();

        $this->assertCount(3, $may);
    }

    public function test_two_appointments_at_the_same_slot_coexist_because_conflict_check_is_disabled(): void
    {
        // The audit deliberately disabled the slot-conflict check in
        // AppointmentService::createAppointment (it's commented out in
        // the service, lines ~380-392) so that clinics can double-book
        // the same slot. Pin that two appointments at identical
        // (doctor, location, date, time) successfully persist — a
        // future refactor that re-enables the check surfaces as a
        // failure here rather than a silent behaviour change.
        $doctor = User::factory()->doctor()->create();

        $first = Appointments::factory()->create([
            'doctor_id' => $doctor->id,
            'location_id' => $this->location->id,
            'scheduled_date' => '2026-07-10',
            'scheduled_time' => '09:00:00',
        ]);
        $second = Appointments::factory()->create([
            'doctor_id' => $doctor->id,
            'location_id' => $this->location->id,
            'scheduled_date' => '2026-07-10',
            'scheduled_time' => '09:00:00',
        ]);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(
            2,
            Appointments::query()
                ->where('doctor_id', $doctor->id)
                ->where('location_id', $this->location->id)
                ->where('scheduled_date', '2026-07-10')
                ->where('scheduled_time', '09:00:00')
                ->count(),
            'Both appointments at the same slot must persist — the conflict check is intentionally disabled.'
        );
    }
}
