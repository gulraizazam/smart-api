<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Services\Plan\PlanService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Sold-by eligibility — the dropdown of users who can be credited
 * with a sale on a plan. Two surfaces share the SAME rule and must
 * stay in lockstep:
 *
 *   1. Add-row dropdown (`PlanService::getAppointmentInfo`) — shown
 *      while creating a plan, before the package row exists.
 *   2. Reassign-pencil dropdown (`PlanService::getSoldByData`) —
 *      shown after the plan has been saved, when an admin needs
 *      to correct the attribution.
 *
 * The rule, agreed 2026-05-02 after the Reassign dropdown was
 * found offering ANY active centre doctor + FDM users (way too
 * broad — the auto-pick only ever picks the consulting doctor,
 * so the override pool was contradicting the policy):
 *
 *   ELIGIBLE = (most-recent arrived/converted consultancy doctor
 *               at the centre, for THIS patient)
 *            ∪ (doctors who treated THIS patient at the centre
 *               in the last 30 days)
 *            + current sold_by (Reassign only — for audit
 *              continuity on legacy/transferred plans)
 *
 * Pinned here so any future "let's also include all centre
 * doctors" or "let FDMs sell too" PR fails this suite loudly
 * before it ships and the user has to flag it via screenshot
 * (which has already happened twice).
 *
 * Implementation notes:
 *   • We bypass the model factories and write rows directly via
 *     `DB::table` because the repo's factories are out-of-sync
 *     with the production schema in several places (address,
 *     PackageBundles factory missing, doctor_has_locations.
 *     service_id NOT NULL, etc.). Direct inserts give us full
 *     control over the fixture shape.
 *   • `seedFinancialFixtures()` already seeds appointment status
 *     ids 1..5 and types 1..2, plus a default account, location,
 *     and service we can reuse for FK satisfaction.
 */
class SoldByEligibilityTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private PlanService $service;

    private int $centreId;

    private int $otherCentreId;

    private int $patientId;

    private int $consultingDoctorId;

    private int $treatingDoctorId;

    private int $unrelatedDoctorId;

    /** Seeded by `seedDefaultService` — used only to satisfy FKs on
     *  appointment / doctor_has_locations rows. The eligibility logic
     *  itself doesn't care which service the appointment was for. */
    private int $defaultServiceId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        // getSoldByData is an authenticated endpoint — it now resolves the
        // plan through an account_id-scoped guard (cross-tenant IDOR fix),
        // so the service needs an authenticated account-1 caller exactly as
        // production always has. Without this the guard reads a null
        // Auth::user(). All fixtures below are account_id = 1.
        $this->actingAsAdmin();
        $this->service = app(PlanService::class);

        // Patch appointment_statuses with the is_arrived/is_converted
        // flags the consultancy filter looks for. The financial-spine
        // seeder creates the rows but leaves both flags zero.
        DB::table('appointment_statuses')->where('id', 2)->update(['is_arrived' => 1]);
        DB::table('appointment_statuses')->updateOrInsert(
            ['id' => 6],
            [
                'name' => 'Converted',
                'sort_no' => 6,
                'active' => 1,
                'account_id' => 1,
                'is_default' => 0,
                'is_cancelled' => 0,
                'is_arrived' => 0,
                'is_converted' => 1,
                'is_unscheduled' => 0,
            ],
        );

        $this->centreId = $this->makeCentre('Test Centre A');
        $this->otherCentreId = $this->makeCentre('Test Centre B');
        $this->patientId = $this->makeUser('Test Patient', userTypeId: 3);
        $this->consultingDoctorId = $this->makeUser('Dr Consulting', userTypeId: 5);
        $this->treatingDoctorId = $this->makeUser('Dr Treating', userTypeId: 5);
        $this->unrelatedDoctorId = $this->makeUser('Dr Unrelated', userTypeId: 5);

        $this->defaultServiceId = (int) DB::table('services')->insertGetId([
            'name' => 'Test Service',
            'price' => 1000,
            'tax_treatment_type_id' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeCentre(string $name): int
    {
        return (int) DB::table('locations')->insertGetId([
            'name' => $name,
            'city_id' => 1,
            'region_id' => 1,
            'address' => '123 Test St',
            'account_id' => 1,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeUser(string $name, int $userTypeId): int
    {
        return (int) DB::table('users')->insertGetId([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@test.local',
            'password' => bcrypt('test'),
            'phone' => '+923000000000',
            'user_type_id' => $userTypeId,
            'account_id' => 1,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeAppointment(int $doctorId, int $typeId, int $statusId, string $when, ?int $locationId = null): void
    {
        // `appointments.lead_id` is NOT NULL — every appointment in
        // the production schema is bound to a lead row. Re-use a
        // single sentinel lead per test case so the appointments we
        // insert satisfy the FK without polluting the patient's
        // history with N synthetic leads.
        static $sentinelLeadId = null;
        if ($sentinelLeadId === null) {
            $sentinelLeadId = (int) DB::table('leads')->insertGetId([
                'name' => 'Sentinel Lead',
                'phone' => '+923000000099',
                'patient_id' => $this->patientId,
                'account_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $dt = Carbon::parse($when);
        DB::table('appointments')->insert([
            'name' => 'Test Appointment',
            'patient_id' => $this->patientId,
            'lead_id' => $sentinelLeadId,
            'doctor_id' => $doctorId,
            'service_id' => $this->defaultServiceId,
            'location_id' => $locationId ?? $this->centreId,
            'region_id' => 1,
            'city_id' => 1,
            'appointment_type_id' => $typeId,
            'appointment_status_id' => $statusId,
            'scheduled_date' => $dt->format('Y-m-d'),
            'scheduled_time' => $dt->format('H:i:s'),
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Build a saved plan + package_service row so getSoldByData has a
     * package_service_id to look up.
     */
    private function makeSavedPlanRow(int $soldBy): int
    {
        $packageId = (int) DB::table('packages')->insertGetId([
            'patient_id' => $this->patientId,
            'location_id' => $this->centreId,
            'name' => 'Test Plan',
            'plan_type' => 'plan',
            'total_price' => 1000,
            'account_id' => 1,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $bundleId = (int) DB::table('package_bundles')->insertGetId([
            'package_id' => $packageId,
            'random_id' => 'TEST-'.uniqid(),
            'is_allocate' => 1,
            'qty' => 1,
            'service_price' => 1000,
            'net_amount' => 1000,
            'tax_exclusive_net_amount' => 854,
            'tax_percentage' => 17,
            'tax_price' => 146,
            'tax_including_price' => 1000,
            'location_id' => $this->centreId,
            'package_id' => $packageId,
            'active' => 1,
            'is_exclusive' => 0,
            'discount_name' => '-',
            'discount_type' => '-',
            'discount_price' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return (int) DB::table('package_services')->insertGetId([
            'package_id' => $packageId,
            'package_bundle_id' => $bundleId,
            'service_id' => $this->defaultServiceId,
            'sold_by' => $soldBy,
            'price' => 1000,
            'orignal_price' => 1000,
            'tax_including_price' => 1000,
            'tax_price' => 146,
            'tax_exclusive_price' => 854,
            'tax_percentage' => 17,
            'is_consumed' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // =========================================================================
    // getAppointmentInfo — Add-row dropdown
    // =========================================================================

    public function test_add_row_returns_the_consulting_doctor_when_only_a_consultancy_exists(): void
    {
        $this->makeAppointment($this->consultingDoctorId, typeId: 2, statusId: 6, when: Carbon::now()->subDays(2)->format('Y-m-d').' 17:00:00');

        $result = $this->service->getAppointmentInfo($this->patientId, $this->centreId);

        $this->assertSame([$this->consultingDoctorId => 'Dr Consulting'], $result['users']);
        $this->assertSame($this->consultingDoctorId, $result['selected_doctor_id']);
    }

    public function test_add_row_includes_recent_treatment_doctors_alongside_the_consulting_doctor(): void
    {
        // Anchor case from plan #47384 (DR Rabel): consulting
        // doctor PLUS recent treatment doctor at the same centre
        // on the same day — both eligible.
        $this->makeAppointment($this->consultingDoctorId, typeId: 2, statusId: 6, when: Carbon::now()->subDays(2)->format('Y-m-d').' 17:00:00');
        $this->makeAppointment($this->treatingDoctorId, typeId: 2, statusId: 2, when: Carbon::now()->subDays(2)->format('Y-m-d').' 16:45:00');

        $result = $this->service->getAppointmentInfo($this->patientId, $this->centreId);

        $this->assertEqualsCanonicalizing(
            [$this->consultingDoctorId, $this->treatingDoctorId],
            array_keys($result['users']),
        );
    }

    public function test_add_row_excludes_doctors_who_never_touched_this_patient_even_if_allocated_to_the_centre(): void
    {
        // The whole point of the rule rewrite. Allocating a doctor
        // to the centre is no longer enough — they need a clinical
        // touchpoint with THIS patient. Pinning this guards against
        // future "let's add all centre doctors" PRs.
        DB::table('doctor_has_locations')->insert([
            'user_id' => $this->unrelatedDoctorId,
            'location_id' => $this->centreId,
            'service_id' => $this->defaultServiceId,
            'end_node' => 1,
            'is_allocated' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->makeAppointment($this->consultingDoctorId, typeId: 2, statusId: 6, when: Carbon::now()->subDays(2)->format('Y-m-d').' 17:00:00');

        $result = $this->service->getAppointmentInfo($this->patientId, $this->centreId);

        $this->assertArrayNotHasKey($this->unrelatedDoctorId, $result['users']);
    }

    public function test_add_row_excludes_recent_treatments_outside_the_centre(): void
    {
        // The recent-treatment branch is centre-scoped — a doctor
        // who treated the patient at a different centre does NOT
        // become eligible for this centre's plan.
        $this->makeAppointment($this->consultingDoctorId, typeId: 2, statusId: 6, when: Carbon::now()->subDays(2)->format('Y-m-d').' 17:00:00');
        $this->makeAppointment($this->treatingDoctorId, typeId: 2, statusId: 2, when: Carbon::now()->subDays(1)->format('Y-m-d').' 10:00:00', locationId: $this->otherCentreId);

        $result = $this->service->getAppointmentInfo($this->patientId, $this->centreId);

        $this->assertArrayNotHasKey($this->treatingDoctorId, $result['users']);
    }

    public function test_add_row_excludes_treatments_older_than_30_days(): void
    {
        // The 30-day window is load-bearing — a doctor who treated
        // the patient 31 days ago must NOT appear, otherwise the
        // pool grows unbounded over time and stale doctors get
        // attributed to fresh sales.
        $this->makeAppointment($this->consultingDoctorId, typeId: 2, statusId: 6, when: Carbon::now()->subDays(2)->format('Y-m-d').' 17:00:00');
        $this->makeAppointment($this->treatingDoctorId, typeId: 2, statusId: 2, when: Carbon::now()->subDays(31)->format('Y-m-d').' 10:00:00');

        $result = $this->service->getAppointmentInfo($this->patientId, $this->centreId);

        $this->assertArrayNotHasKey($this->treatingDoctorId, $result['users']);
    }

    public function test_add_row_returns_an_empty_pool_when_no_consultation_and_no_recent_treatment_exists(): void
    {
        // No consultancy + no recent treatment → no eligible
        // doctors. The dialog must NOT silently fall back to "all
        // centre doctors" (which was the old broken behaviour).
        $result = $this->service->getAppointmentInfo($this->patientId, $this->centreId);

        $this->assertSame([], $result['users']);
        $this->assertNull($result['selected_doctor_id']);
    }

    // =========================================================================
    // getSoldByData — Reassign-pencil dropdown
    // =========================================================================

    public function test_reassign_pencil_returns_only_patient_context_doctors_plus_current_sold_by(): void
    {
        $this->makeAppointment($this->consultingDoctorId, typeId: 2, statusId: 6, when: Carbon::now()->subDays(2)->format('Y-m-d').' 17:00:00');
        $packageServiceId = $this->makeSavedPlanRow($this->consultingDoctorId);

        $result = $this->service->getSoldByData(
            packageServiceId: $packageServiceId,
            bundleId: null,
            locationId: $this->centreId,
        );

        $this->assertTrue($result['success']);
        $this->assertSame([$this->consultingDoctorId => 'Dr Consulting'], $result['data']['users']);
        $this->assertSame($this->consultingDoctorId, $result['data']['current_sold_by']);
    }

    public function test_reassign_pencil_does_NOT_include_unrelated_centre_doctors(): void
    {
        // The exact regression we just fixed. Allocating a doctor
        // to the centre must NOT make them appear in the Reassign
        // pool. If this test fails, someone has re-added the
        // `doctor_has_locations` filter — undo that change.
        DB::table('doctor_has_locations')->insert([
            'user_id' => $this->unrelatedDoctorId,
            'location_id' => $this->centreId,
            'service_id' => $this->defaultServiceId,
            'end_node' => 1,
            'is_allocated' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->makeAppointment($this->consultingDoctorId, typeId: 2, statusId: 6, when: Carbon::now()->subDays(2)->format('Y-m-d').' 17:00:00');
        $packageServiceId = $this->makeSavedPlanRow($this->consultingDoctorId);

        $result = $this->service->getSoldByData(
            packageServiceId: $packageServiceId,
            bundleId: null,
            locationId: $this->centreId,
        );

        $this->assertArrayNotHasKey($this->unrelatedDoctorId, $result['data']['users']);
    }

    public function test_reassign_pencil_does_NOT_include_FDM_role_users(): void
    {
        // Another regression bait. The old logic added active
        // FDM-role users at the centre as a separate union — that
        // is now removed. FDMs are NOT clinical attribution; if
        // Product wants front-desk attribution, that's a different
        // field. This test pins the removal.
        $fdmUserId = $this->makeUser('FDM Front Desk', userTypeId: 1);
        DB::table('user_has_locations')->insert([
            'user_id' => $fdmUserId,
            'location_id' => $this->centreId,
            'region_id' => 1,
        ]);
        $fdmRoleId = (int) DB::table('roles')->insertGetId([
            'name' => 'FDM',
            'guard_name' => 'web',
            'commission' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('role_has_users')->insert([
            'role_id' => $fdmRoleId,
            'user_id' => $fdmUserId,
        ]);
        $this->makeAppointment($this->consultingDoctorId, typeId: 2, statusId: 6, when: Carbon::now()->subDays(2)->format('Y-m-d').' 17:00:00');
        $packageServiceId = $this->makeSavedPlanRow($this->consultingDoctorId);

        $result = $this->service->getSoldByData(
            packageServiceId: $packageServiceId,
            bundleId: null,
            locationId: $this->centreId,
        );

        $this->assertArrayNotHasKey($fdmUserId, $result['data']['users']);
    }

    public function test_reassign_pencil_keeps_the_current_sold_by_in_the_pool_even_when_no_patient_context_exists(): void
    {
        // Audit continuity branch — a legacy plan whose patient
        // has no recent appointments at the centre would otherwise
        // present an EMPTY dropdown, locking the operator out of
        // the existing attribution. The current sold_by is always
        // included so the saved value can survive a re-open.
        $packageServiceId = $this->makeSavedPlanRow($this->treatingDoctorId);

        $result = $this->service->getSoldByData(
            packageServiceId: $packageServiceId,
            bundleId: null,
            locationId: $this->centreId,
        );

        $this->assertSame([$this->treatingDoctorId => 'Dr Treating'], $result['data']['users']);
    }

    public function test_add_and_reassign_endpoints_return_the_same_set_modulo_the_current_sold_by_inclusion(): void
    {
        // Cross-endpoint invariant — the whole point of the
        // 2026-05-02 rule clarification. If the Add pool diverges
        // from the Reassign pool (other than the current_sold_by
        // audit-continuity inclusion), the auto-pick policy and
        // the override pool no longer agree, which is the bug we
        // were trying to eliminate.
        $this->makeAppointment($this->consultingDoctorId, typeId: 2, statusId: 6, when: Carbon::now()->subDays(2)->format('Y-m-d').' 17:00:00');
        $this->makeAppointment($this->treatingDoctorId, typeId: 2, statusId: 2, when: Carbon::now()->subDays(5)->format('Y-m-d').' 10:00:00');
        $packageServiceId = $this->makeSavedPlanRow($this->consultingDoctorId);

        $addResult = $this->service->getAppointmentInfo($this->patientId, $this->centreId);
        $reassignResult = $this->service->getSoldByData(
            packageServiceId: $packageServiceId,
            bundleId: null,
            locationId: $this->centreId,
        );

        $this->assertEqualsCanonicalizing(
            array_keys($addResult['users']),
            array_keys($reassignResult['data']['users']),
        );
    }
}
