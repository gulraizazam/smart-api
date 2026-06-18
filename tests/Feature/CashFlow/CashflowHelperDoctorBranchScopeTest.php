<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Helpers\CashflowHelper;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins CashflowHelper::getUserBranches() doctor fallback.
 *
 * A doctor's clinic lives in doctor_has_locations, not user_has_locations.
 * getUserBranches() read only user_has_locations, so a doctor resolved to an
 * empty branch set — and the FDM cash endpoint (which 403s "No branch assigned"
 * on empty) locked them out. The fallback resolves their allocated clinic when
 * user_has_locations is empty, without widening anyone who already has one.
 */
class CashflowHelperDoctorBranchScopeTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    private function allocateDoctorClinic(int $userId, int $locationId, bool $allocated = true): void
    {
        // service_id/end_node are NOT NULL with FK constraints irrelevant to
        // branch scoping; bypass FK checks for the fixture insert.
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            DB::table('doctor_has_locations')->insert([
                'user_id' => $userId,
                'location_id' => $locationId,
                'service_id' => 0,
                'end_node' => 0,
                'is_allocated' => $allocated ? 1 : 0,
            ]);
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public function test_get_user_branches_falls_back_to_doctor_clinic(): void
    {
        $user = User::factory()->create(['account_id' => 1]);
        $location = $this->defaultLocation; // active, account 1

        $this->allocateDoctorClinic($user->id, (int) $location->id);

        $branches = CashflowHelper::getUserBranches($user->id);

        $this->assertCount(1, $branches);
        $this->assertTrue($branches->contains('id', $location->id));
    }

    public function test_unallocated_doctor_clinic_yields_no_branches(): void
    {
        $user = User::factory()->create(['account_id' => 1]);
        $location = $this->defaultLocation;

        $this->allocateDoctorClinic($user->id, (int) $location->id, allocated: false);

        $branches = CashflowHelper::getUserBranches($user->id);

        $this->assertCount(0, $branches);
    }

    public function test_user_has_locations_is_not_widened_by_doctor_clinic(): void
    {
        $user = User::factory()->create(['account_id' => 1]);
        $location = $this->defaultLocation;

        DB::table('user_has_locations')->insert([
            'user_id' => $user->id,
            'location_id' => $location->id,
            'region_id' => 1,
        ]);
        // A different (non-existent) doctor clinic must never be unioned in.
        $this->allocateDoctorClinic($user->id, 999999);

        $branches = CashflowHelper::getUserBranches($user->id);

        $this->assertCount(1, $branches);
        $this->assertTrue($branches->contains('id', $location->id));
        $this->assertFalse($branches->contains('id', 999999));
    }
}
