<?php

declare(strict_types=1);

namespace Tests\Feature\Memberships;

use App\Http\Resources\MembershipResource;
use App\Models\Membership;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The membership datatable resource must expose the patient's phone and
 * referral flag — the Memberships list renders the phone in the patient cell
 * and a "Ref" badge for referrals. The phone was previously absent from the
 * resource, so the cell had nothing to show.
 */
class MembershipResourceTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $admin = $this->actingAsAdmin();
        $this->adminId = $admin->id;
    }

    private int $adminId;

    public function test_resource_exposes_patient_phone_and_referral_flag(): void
    {
        $typeId = (int) DB::table('membership_types')->insertGetId([
            'name' => 'Gold Membership', 'period' => 365, 'amount' => 0, 'active' => 1,
            'created_by' => $this->adminId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $patientId = (int) DB::table('users')->insertGetId([
            'name' => 'Phone Patient', 'email' => 'ph-'.uniqid().'@test.local',
            'password' => bcrypt('test'), 'phone' => '+923001234567',
            'user_type_id' => 3, 'account_id' => 1, 'active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $membershipId = (int) DB::table('memberships')->insertGetId([
            'code' => 'GOLD-PH', 'membership_type_id' => $typeId, 'patient_id' => $patientId,
            'start_date' => now()->subDay(), 'end_date' => now()->addMonth(),
            'active' => 1, 'is_referral' => 1, 'parent_membership_code' => 'GOLD-PH',
            'created_by' => $this->adminId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $membership = Membership::with(['patient', 'membershipType'])->findOrFail($membershipId);
        $arr = (new MembershipResource($membership))->toArray(request());

        $this->assertSame('+923001234567', $arr['patient_phone']);
        $this->assertTrue($arr['is_referral']);
    }
}
