<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the three one-off membership data-cleanup migrations
 * (2026_06_04_1200xx) that repair historical residue in the
 * `memberships` table:
 *
 *   120000 realign_referral_end_dates_to_parent      — F2
 *   120100 retire_duplicate_stock_membership_parents — F3
 *   120200 null_orphan_referral_parent_code          — F1
 *
 * Each migration is pure DML, so the per-test transaction rolls the
 * inserts + up() back — no DDL scrub needed. Seed pattern mirrors
 * MembershipReferralExpiryTest (so user id 1 + account 1 exist).
 */
class MembershipDataCleanupMigrationsTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private int $goldTypeId;

    private int $patientA;

    private int $patientB;

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

        $this->patientA = $this->makePatient('Cleanup Patient A', '+923009990001');
        $this->patientB = $this->makePatient('Cleanup Patient B', '+923009990002');
    }

    private function makePatient(string $name, string $phone): int
    {
        return (int) DB::table('users')->insertGetId([
            'name' => $name,
            'email' => strtolower(str_replace([' ', '+'], ['.', ''], $name)).'@test.local',
            'password' => bcrypt('test'),
            'phone' => $phone,
            'user_type_id' => 3,
            'account_id' => 1,
            'active' => 1,
            'main_account' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertMembership(array $attrs): int
    {
        return (int) DB::table('memberships')->insertGetId(array_merge([
            'code' => 'CA-X',
            'membership_type_id' => $this->goldTypeId,
            'patient_id' => null,
            'start_date' => null,
            'end_date' => null,
            'active' => 1,
            'created_by' => 1,
            'is_referral' => 0,
            'parent_membership_code' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));
    }

    private function runMigration(string $file): void
    {
        $migration = require database_path("migrations/{$file}");
        $migration->up();
    }

    private const M_REALIGN = '2026_06_04_120000_realign_referral_end_dates_to_parent.php';

    private const M_RETIRE = '2026_06_04_120100_retire_duplicate_stock_membership_parents.php';

    private const M_ORPHAN = '2026_06_04_120200_null_orphan_referral_parent_code.php';

    // ── 120000 realign ────────────────────────────────────────────

    public function test_realign_sets_active_referral_end_date_to_assigned_parent(): void
    {
        $parentEnd = '2026-07-04';
        $this->insertMembership(['code' => 'CA-R1', 'patient_id' => $this->patientA, 'is_referral' => 0, 'active' => 1, 'end_date' => $parentEnd]);
        $refId = $this->insertMembership(['code' => 'CA-R1', 'parent_membership_code' => 'CA-R1', 'patient_id' => $this->patientB, 'is_referral' => 1, 'active' => 1, 'end_date' => '2027-04-23']);

        $this->runMigration(self::M_REALIGN);

        $this->assertSame($parentEnd, DB::table('memberships')->where('id', $refId)->value('end_date'));

        // Idempotent — a second run is a clean no-op.
        $this->runMigration(self::M_REALIGN);
        $this->assertSame($parentEnd, DB::table('memberships')->where('id', $refId)->value('end_date'));
    }

    public function test_realign_uses_ASSIGNED_parent_not_stock_duplicate(): void
    {
        // The disambiguation: a code with both an assigned parent and a
        // leftover stock parent (different date) must align to the
        // ASSIGNED one — never the stock card.
        $assignedEnd = '2026-07-04';
        $stockEnd = '2026-08-12';
        $this->insertMembership(['code' => 'CA-R3', 'patient_id' => $this->patientA, 'is_referral' => 0, 'active' => 1, 'end_date' => $assignedEnd]);
        $this->insertMembership(['code' => 'CA-R3', 'patient_id' => null, 'is_referral' => 0, 'active' => 1, 'end_date' => $stockEnd]);
        $refId = $this->insertMembership(['code' => 'CA-R3', 'parent_membership_code' => 'CA-R3', 'patient_id' => $this->patientB, 'is_referral' => 1, 'active' => 1, 'end_date' => '2027-01-01']);

        $this->runMigration(self::M_REALIGN);

        $this->assertSame($assignedEnd, DB::table('memberships')->where('id', $refId)->value('end_date'));
    }

    public function test_realign_leaves_an_already_aligned_referral_untouched(): void
    {
        $end = '2026-09-01';
        $this->insertMembership(['code' => 'CA-R2', 'patient_id' => $this->patientA, 'is_referral' => 0, 'active' => 1, 'end_date' => $end]);
        $refId = $this->insertMembership(['code' => 'CA-R2', 'parent_membership_code' => 'CA-R2', 'patient_id' => $this->patientB, 'is_referral' => 1, 'active' => 1, 'end_date' => $end]);
        $updatedAtBefore = DB::table('memberships')->where('id', $refId)->value('updated_at');

        sleep(1);
        $this->runMigration(self::M_REALIGN);

        $this->assertSame($end, DB::table('memberships')->where('id', $refId)->value('end_date'));
        // No write happened — updated_at is unchanged.
        $this->assertSame($updatedAtBefore, DB::table('memberships')->where('id', $refId)->value('updated_at'));
    }

    // ── 120100 retire duplicate stock parents ─────────────────────

    public function test_retire_deactivates_unassigned_stock_duplicate_only(): void
    {
        $assignedId = $this->insertMembership(['code' => 'CA-D1', 'patient_id' => $this->patientA, 'is_referral' => 0, 'active' => 1, 'end_date' => '2027-02-23']);
        $stockId = $this->insertMembership(['code' => 'CA-D1', 'patient_id' => null, 'is_referral' => 0, 'active' => 1, 'end_date' => '2026-08-12']);

        $this->runMigration(self::M_RETIRE);

        $this->assertSame(1, (int) DB::table('memberships')->where('id', $assignedId)->value('active'));
        $this->assertSame(0, (int) DB::table('memberships')->where('id', $stockId)->value('active'));
        $this->assertSame(
            1,
            DB::table('memberships')->where('code', 'CA-D1')->where('is_referral', 0)->where('active', 1)->count(),
            'Exactly one active parent must remain for the code'
        );
    }

    public function test_retire_skips_code_with_only_stock_parents(): void
    {
        // Guard: never deactivate the LAST active parent. Two stock
        // parents, no assigned — both must stay active (skipped).
        $this->insertMembership(['code' => 'CA-D3', 'patient_id' => null, 'is_referral' => 0, 'active' => 1, 'end_date' => '2026-08-12']);
        $this->insertMembership(['code' => 'CA-D3', 'patient_id' => null, 'is_referral' => 0, 'active' => 1, 'end_date' => '2026-09-12']);

        $this->runMigration(self::M_RETIRE);

        $this->assertSame(2, DB::table('memberships')->where('code', 'CA-D3')->where('active', 1)->count());
    }

    public function test_retire_is_a_no_op_for_a_single_parent_code(): void
    {
        $id = $this->insertMembership(['code' => 'CA-D2', 'patient_id' => $this->patientA, 'is_referral' => 0, 'active' => 1, 'end_date' => '2027-01-01']);

        $this->runMigration(self::M_RETIRE);

        $this->assertSame(1, (int) DB::table('memberships')->where('id', $id)->value('active'));
    }

    // ── 120200 null orphan referral parent code ───────────────────

    public function test_orphan_referral_parent_code_is_cleared_when_no_parent_exists(): void
    {
        $refId = $this->insertMembership(['code' => 'CA-O1', 'parent_membership_code' => 'CA-O1', 'patient_id' => $this->patientB, 'is_referral' => 1, 'active' => 0, 'end_date' => '2026-04-08']);

        $this->runMigration(self::M_ORPHAN);

        $this->assertNull(DB::table('memberships')->where('id', $refId)->value('parent_membership_code'));
    }

    public function test_orphan_cleanup_leaves_referrals_with_a_real_parent_intact(): void
    {
        $this->insertMembership(['code' => 'CA-O2', 'patient_id' => $this->patientA, 'is_referral' => 0, 'active' => 1, 'end_date' => '2027-01-01']);
        $refId = $this->insertMembership(['code' => 'CA-O2', 'parent_membership_code' => 'CA-O2', 'patient_id' => $this->patientB, 'is_referral' => 1, 'active' => 1, 'end_date' => '2027-01-01']);

        $this->runMigration(self::M_ORPHAN);

        $this->assertSame('CA-O2', DB::table('memberships')->where('id', $refId)->value('parent_membership_code'));
    }
}
