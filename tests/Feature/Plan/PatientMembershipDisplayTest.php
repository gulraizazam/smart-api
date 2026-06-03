<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Models\Membership;
use App\Services\Plan\PlanService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * getPatientMembershipDisplay() powers the "patient_membership" line on the
 * patient-create form (PlanService::getCreateFormDataForPatient, called at
 * PlanService.php:1058). It MUST read membership assignments from the real
 * `memberships` table, keyed by `patient_id` — there is no `user_memberships`
 * table. The original query joined that phantom table on `user_id` and threw
 * "SQLSTATE[42S02] ... Table 'user_memberships' doesn't exist", 500'ing the
 * patient-create form on production (2026-06-03). This pins the corrected
 * source table + the active/expired/soft-delete display semantics so the join
 * can't regress back to the phantom table.
 */
class PatientMembershipDisplayTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private PlanService $planService;

    private int $goldTypeId;

    protected function setUp(): void
    {
        parent::setUp();
        // Seeds canonical user_types (1/3/5) + account so the admin factory
        // and patient inserts satisfy their FKs.
        $this->seedFinancialFixtures();
        // Memberships carry a NOT NULL created_by + a MembershipObserver that
        // reads Auth::id() on write — act as an admin so seeding rows is clean.
        $this->actingAsAdmin();
        $this->planService = app(PlanService::class);

        $this->goldTypeId = (int) DB::table('membership_types')->insertGetId([
            'name' => 'Gold Membership',
            'period' => 12,
            'amount' => 0,
            'active' => 1,
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makePatient(string $name): int
    {
        return (int) DB::table('users')->insertGetId([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@test.local',
            'password' => bcrypt('test'),
            'user_type_id' => 3, // patient
            'account_id' => 1,
            'active' => 1,
            'main_account' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeMembership(int $patientId, string $code, Carbon $end, bool $active = true): Membership
    {
        return Membership::create([
            'code' => $code,
            'membership_type_id' => $this->goldTypeId,
            'patient_id' => $patientId,
            'start_date' => Carbon::now()->subMonth()->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'active' => $active ? 1 : 0,
            'created_by' => 1,
            'is_referral' => 0,
            'parent_membership_code' => null,
            'assigned_at' => Carbon::now()->format('Y-m-d'),
        ]);
    }

    /** Invoke the private display helper the patient-create form depends on. */
    private function display(int $patientId): string
    {
        $method = new ReflectionMethod(PlanService::class, 'getPatientMembershipDisplay');
        $method->setAccessible(true);

        return (string) $method->invoke($this->planService, $patientId);
    }

    public function test_reads_active_membership_from_the_memberships_table(): void
    {
        $patientId = $this->makePatient('Active Member');
        $this->makeMembership($patientId, 'M-ACTIVE', Carbon::now()->addYear());

        // Regression: this used to throw "Table 'user_memberships' doesn't exist".
        $this->assertSame('Gold Membership (Active)', $this->display($patientId));
    }

    public function test_returns_no_membership_when_patient_has_none(): void
    {
        $patientId = $this->makePatient('No Member');

        $this->assertSame('No Membership', $this->display($patientId));
    }

    public function test_reports_expired_when_end_date_is_in_the_past(): void
    {
        $patientId = $this->makePatient('Expired Member');
        $this->makeMembership($patientId, 'M-EXPIRED', Carbon::now()->subDay());

        $this->assertSame('Gold Membership (Expired)', $this->display($patientId));
    }

    public function test_ignores_soft_deleted_memberships(): void
    {
        $patientId = $this->makePatient('Deleted Member');
        $membership = $this->makeMembership($patientId, 'M-DELETED', Carbon::now()->addYear());
        DB::table('memberships')->where('id', $membership->id)->update(['deleted_at' => now()]);

        $this->assertSame('No Membership', $this->display($patientId));
    }

    public function test_prefers_the_active_non_expired_membership_over_an_expired_one(): void
    {
        $patientId = $this->makePatient('Two Memberships');
        // Older, expired card + a current active card — the display must surface
        // the active one (mirrors getMembershipForLocation's ordering).
        $this->makeMembership($patientId, 'M-OLD', Carbon::now()->subMonth());
        $this->makeMembership($patientId, 'M-NEW', Carbon::now()->addYear());

        $this->assertSame('Gold Membership (Active)', $this->display($patientId));
    }
}
