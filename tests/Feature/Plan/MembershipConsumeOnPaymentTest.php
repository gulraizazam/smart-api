<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Models\Membership;
use App\Services\Plan\PlanService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins PlanService::updateMembershipRecord (the membership-plan save seam):
 *
 *   - NOT fully paid  → the code stays unassigned stock (patient_id NULL,
 *     no dates). It must NOT stamp patient_id without an end_date, which
 *     previously tripped the Membership::booted() guard and surfaced as
 *     "Failed to save package: A membership assigned to a patient must
 *     have an end_date".
 *   - Fully paid (incl. student docs, folded into the flag by the caller)
 *     → the card activates: patient_id set, start_date = today, end_date =
 *     today + the membership type's duration in days.
 */
class MembershipConsumeOnPaymentTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private int $typeId;

    private int $patientId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->typeId = (int) DB::table('membership_types')->insertGetId([
            'name' => 'Student Membership',
            'period' => 30,
            'amount' => 0,
            'active' => 1,
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->patientId = (int) DB::table('users')->insertGetId([
            'name' => 'Consume Patient',
            'email' => 'consume.patient@test.local',
            'password' => bcrypt('test'),
            'phone' => '+923008880002',
            'user_type_id' => 3,
            'account_id' => 1,
            'active' => 1,
            'main_account' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function stockCard(): Membership
    {
        return Membership::create([
            'code' => 'CA-CONSUME',
            'membership_type_id' => $this->typeId,
            'patient_id' => null,
            'start_date' => null,
            'end_date' => null,
            'active' => 1,
            'created_by' => 1,
            'is_referral' => 0,
        ]);
    }

    private function callUpdate(int $membershipId, bool $isFullyPaid): void
    {
        $svc = app(PlanService::class);
        $ref = new \ReflectionMethod($svc, 'updateMembershipRecord');
        $ref->setAccessible(true);
        $ref->invoke(
            $svc,
            $membershipId,
            ['membershipId' => $this->typeId],
            ['patient_id' => $this->patientId],
            $isFullyPaid,
        );
    }

    public function test_partial_payment_leaves_code_unassigned_and_does_not_throw(): void
    {
        $card = $this->stockCard();

        $this->callUpdate($card->id, false);

        $fresh = $card->fresh();
        $this->assertNull($fresh->patient_id, 'Half-paid membership must stay unassigned.');
        $this->assertNull($fresh->end_date);
    }

    public function test_full_payment_assigns_patient_and_sets_duration_dates(): void
    {
        $card = $this->stockCard();

        $this->callUpdate($card->id, true);

        $fresh = $card->fresh();
        $this->assertSame($this->patientId, (int) $fresh->patient_id);
        $this->assertSame(now()->toDateString(), $fresh->start_date);
        // period = 30 days
        $this->assertSame(now()->addDays(30)->toDateString(), $fresh->end_date);
    }
}
