<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Models\Membership;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the Membership::saving guard (app/Models/Membership.php):
 * an ASSIGNED membership (patient_id set) must have an end_date, while
 * an unassigned STOCK card (patient_id NULL) may keep a NULL end_date.
 */
class MembershipAssignedEndDateGuardTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private int $goldTypeId;

    private int $patientId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->goldTypeId = (int) DB::table('membership_types')->insertGetId([
            'name' => 'Gold Membership',
            'period' => 12,
            'amount' => 0,
            'active' => 1,
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->patientId = (int) DB::table('users')->insertGetId([
            'name' => 'Guard Patient',
            'email' => 'guard.patient@test.local',
            'password' => bcrypt('test'),
            'phone' => '+923008880001',
            'user_type_id' => 3,
            'account_id' => 1,
            'active' => 1,
            'main_account' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function base(array $attrs): array
    {
        return array_merge([
            'code' => 'CA-GUARD',
            'membership_type_id' => $this->goldTypeId,
            'active' => 1,
            'created_by' => 1,
            'is_referral' => 0,
        ], $attrs);
    }

    public function test_assigned_membership_without_end_date_is_rejected(): void
    {
        $this->expectException(\RuntimeException::class);

        Membership::create($this->base([
            'patient_id' => $this->patientId,
            'start_date' => '2026-01-01',
            'end_date' => null,
        ]));
    }

    public function test_unassigned_stock_card_with_null_end_date_is_allowed(): void
    {
        $m = Membership::create($this->base([
            'patient_id' => null,
            'start_date' => null,
            'end_date' => null,
        ]));

        $this->assertNotNull($m->id);
        $this->assertNull($m->fresh()->end_date);
    }

    public function test_assigned_membership_with_end_date_saves_fine(): void
    {
        $m = Membership::create($this->base([
            'patient_id' => $this->patientId,
            'start_date' => '2026-01-01',
            'end_date' => '2027-01-01',
        ]));

        $this->assertNotNull($m->id);
        $this->assertSame('2027-01-01', $m->fresh()->end_date);
    }

    public function test_updating_an_assigned_membership_to_a_null_end_date_is_rejected(): void
    {
        $m = Membership::create($this->base([
            'patient_id' => $this->patientId,
            'start_date' => '2026-01-01',
            'end_date' => '2027-01-01',
        ]));

        $this->expectException(\RuntimeException::class);
        $m->update(['end_date' => null]);
    }
}
