<?php

declare(strict_types=1);

namespace Tests\Feature\Membership;

use App\Models\Membership;
use App\Services\Membership\MembershipService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Membership consumption history — which services a member's discount was
 * applied to (for tracking Gold/Student benefit). Attribution follows the
 * memberships row: patient_id + membership_type_id → the discounts tied to
 * that type → that patient's discounted plan rows. Account-scoped.
 */
class MembershipConsumptionTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private MembershipService $service;

    private int $locationId;

    private int $patientId;

    private int $membershipTypeId;

    private int $discountId;

    private int $adminId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $admin = $this->actingAsAdmin();
        $this->adminId = $admin->id;
        $this->service = app(MembershipService::class);

        $this->locationId = (int) DB::table('locations')->insertGetId([
            'name' => 'Test Centre', 'city_id' => 1, 'region_id' => 1, 'address' => '1 Test St',
            'tax_percentage' => 0, 'account_id' => 1, 'active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->patientId = (int) DB::table('users')->insertGetId([
            'name' => 'Gold Patient', 'email' => 'gold-'.uniqid().'@test.local',
            'password' => bcrypt('test'), 'phone' => '+923000000070',
            'user_type_id' => 3, 'account_id' => 1, 'active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->membershipTypeId = (int) DB::table('membership_types')->insertGetId([
            'name' => 'Gold Membership', 'period' => 365, 'amount' => 0, 'active' => 1,
            'created_by' => $admin->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // The member discount is tied to the membership type via customer_type_id.
        $this->discountId = (int) DB::table('discounts')->insertGetId([
            'name' => 'Gold Member Discount', 'slug' => 'gold-'.uniqid(), 'type' => 'Fixed',
            'amount' => 1000, 'discount_type' => 'Treatment', 'pre_days' => 0, 'post_days' => 0,
            'start' => now()->subDay(), 'end' => now()->addMonth(), 'active' => 1,
            'account_id' => 1, 'customer_type_id' => $this->membershipTypeId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function seedMembership(?int $patientId): int
    {
        return (int) DB::table('memberships')->insertGetId([
            'code' => 'GOLD-'.strtoupper(substr(uniqid(), -6)),
            'membership_type_id' => $this->membershipTypeId,
            'patient_id' => $patientId,
            'start_date' => $patientId ? now()->subDay() : null,
            'end_date' => $patientId ? now()->addMonth() : null,
            'assigned_at' => $patientId ? now()->subDay() : null,
            'active' => 1, 'created_by' => $this->adminId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * A plan row where the member discount was applied to a service.
     * `$storedDiscount` is the raw discounts/package_bundles value (a PERCENT
     * for percentage discounts); `$netAmount` is what the patient actually
     * paid — so the money saved is price − net, independent of the raw value.
     */
    private function seedDiscountedPlanRow(float $servicePrice, float $storedDiscount, float $netAmount, bool $consumed): void
    {
        // Replicate the production id-collision: a plan row's bundle_id points
        // at the SERVICES table, while the BUNDLES table holds an unrelated row
        // at the SAME id. The consumption view must show the services name (the
        // one the plan displays), not the bundles name.
        $sharedId = 990001;
        DB::table('bundles')->insert([
            'id' => $sharedId, 'name' => 'CoolGlide - Underarms', // wrong name (bundles table)
            'price' => $servicePrice, 'services_price' => $servicePrice,
            'total_services' => 1, 'type' => 'single', 'active' => 1, 'tax_treatment_type_id' => 1,
            'account_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('services')->insert([
            'id' => $sharedId, 'name' => 'CoolGlide - Face', // real service (services table)
            'price' => $servicePrice, 'end_node' => 1, 'tax_treatment_type_id' => 1,
            'account_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $bundleId = $sharedId;
        $randomId = 'MC-'.uniqid();
        $packageId = (int) DB::table('packages')->insertGetId([
            'patient_id' => $this->patientId, 'location_id' => $this->locationId,
            'name' => 'Plan', 'plan_type' => 'plan', 'total_price' => $servicePrice,
            'account_id' => 1, 'active' => 1, 'random_id' => $randomId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $bundleRowId = (int) DB::table('package_bundles')->insertGetId([
            'package_id' => $packageId, 'random_id' => $randomId, 'is_allocate' => 1, 'qty' => 1,
            'service_price' => $servicePrice, 'net_amount' => $netAmount,
            'tax_exclusive_net_amount' => $netAmount, 'tax_percentage' => 0, 'tax_price' => 0,
            'tax_including_price' => $netAmount, 'location_id' => $this->locationId,
            'discount_id' => $this->discountId, 'discount_name' => 'Gold Member Discount',
            'discount_type' => 'Percentage', 'discount_price' => $storedDiscount, 'bundle_id' => $bundleId,
            'account_id' => 1, 'active' => 1, 'is_exclusive' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('package_services')->insert([
            'package_id' => $packageId, 'package_bundle_id' => $bundleRowId,
            'service_id' => $bundleId, 'is_consumed' => $consumed ? 1 : 0,
            'consumed_at' => $consumed ? now() : null, 'consumption_order' => 0,
            'price' => $netAmount, 'orignal_price' => $servicePrice,
            'tax_including_price' => $netAmount, 'tax_price' => 0, 'tax_exclusive_price' => $netAmount,
            'tax_percentage' => 0, 'random_id' => $randomId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * A plan row where the member discount was applied to a BUNDLE
     * (source_type 'service_bundle' — "N× of one service"). bundle_id points at
     * `service_bundles`; the usage view must show the UNDERLYING service prefixed
     * by qty ("3x Laser Hair Removal"), NOT the unrelated bundles/services rows
     * that share the same id (the collision the de-overload fixes).
     */
    private function seedServiceBundlePlanRow(): void
    {
        $sharedId = 991001;
        // Collision bait at the SAME id in the other catalogs.
        DB::table('bundles')->insert([
            'id' => $sharedId, 'name' => 'WRONG Bundle Name', 'price' => 100, 'services_price' => 100,
            'total_services' => 1, 'type' => 'single', 'active' => 1, 'tax_treatment_type_id' => 1,
            'account_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('services')->insert([
            'id' => $sharedId, 'name' => 'WRONG Service Name', 'price' => 100, 'end_node' => 1,
            'tax_treatment_type_id' => 1, 'account_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        // The underlying service the bundle is N× of (a DIFFERENT id).
        $underlyingServiceId = 991002;
        DB::table('services')->insert([
            'id' => $underlyingServiceId, 'name' => 'Laser Hair Removal', 'price' => 100, 'end_node' => 1,
            'tax_treatment_type_id' => 1, 'account_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('service_bundles')->insert([
            'id' => $sharedId, 'service_id' => $underlyingServiceId, 'sessions' => 3, 'price' => 300,
            'account_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $randomId = 'MCSB-'.uniqid();
        $packageId = (int) DB::table('packages')->insertGetId([
            'patient_id' => $this->patientId, 'location_id' => $this->locationId,
            'name' => 'Plan', 'plan_type' => 'plan', 'total_price' => 300,
            'account_id' => 1, 'active' => 1, 'random_id' => $randomId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $bundleRowId = (int) DB::table('package_bundles')->insertGetId([
            'package_id' => $packageId, 'random_id' => $randomId, 'is_allocate' => 1, 'qty' => 3,
            'service_price' => 300, 'net_amount' => 200, 'tax_exclusive_net_amount' => 200,
            'tax_percentage' => 0, 'tax_price' => 0, 'tax_including_price' => 200,
            'location_id' => $this->locationId, 'discount_id' => $this->discountId,
            'discount_name' => 'Gold Member Discount', 'discount_type' => 'Percentage', 'discount_price' => 10,
            'bundle_id' => $sharedId, 'source_type' => 'service_bundle',
            'catalog_service_bundle_id' => $sharedId, // backfilled/mirrored typed column
            'account_id' => 1, 'active' => 1, 'is_exclusive' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('package_services')->insert([
            'package_id' => $packageId, 'package_bundle_id' => $bundleRowId,
            'service_id' => $underlyingServiceId, 'is_consumed' => 0, 'consumption_order' => 0,
            'price' => 200, 'orignal_price' => 300, 'tax_including_price' => 200, 'tax_price' => 0,
            'tax_exclusive_price' => 200, 'tax_percentage' => 0, 'random_id' => $randomId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_service_bundle_row_shows_underlying_service_name_with_qty(): void
    {
        $membershipId = $this->seedMembership($this->patientId);
        $this->seedServiceBundlePlanRow();

        $data = $this->service->getConsumptionHistory($membershipId);

        $this->assertSame(1, $data['total_services']);
        // A service_bundle line shows the UNDERLYING service prefixed by qty —
        // NOT the colliding bundles ("WRONG Bundle Name") or services
        // ("WRONG Service Name") rows that share the same id.
        $this->assertSame('3x Laser Hair Removal', $data['services'][0]['service_name']);
    }

    public function test_returns_member_discounted_services_with_total_saved(): void
    {
        $membershipId = $this->seedMembership($this->patientId);
        // A 35% discount on a 5,995 service → paid 3,896.75, saved 2,098.25.
        // The raw stored value is the PERCENT (35), not the rupees saved.
        $this->seedDiscountedPlanRow(servicePrice: 5995, storedDiscount: 35, netAmount: 3896.75, consumed: true);

        $data = $this->service->getConsumptionHistory($membershipId);

        $this->assertSame('Gold Membership', $data['membership_type']);
        $this->assertSame('Gold Patient', $data['patient_name']);
        $this->assertSame(1, $data['total_services']);
        $this->assertSame(2098.25, (float) $data['total_discount_saved']);

        $service = $data['services'][0];
        // Name resolves from the SERVICES table (what the plan shows), not the
        // unrelated BUNDLES row at the same id.
        $this->assertSame('CoolGlide - Face', $service['service_name']);
        // discount_saved is the real money (2,098.25), NOT the raw percent (35).
        $this->assertSame(2098.25, (float) $service['discount_saved']);
        $this->assertSame(35.0, (float) $service['discount_amount']);
        $this->assertTrue($service['is_consumed']);
    }

    public function test_card_with_no_usage_returns_empty(): void
    {
        $membershipId = $this->seedMembership($this->patientId);

        $data = $this->service->getConsumptionHistory($membershipId);

        $this->assertSame(0, $data['total_services']);
        $this->assertSame([], $data['services']);
    }

    public function test_unassigned_stock_card_returns_empty_without_error(): void
    {
        $membershipId = $this->seedMembership(patientId: null);

        $data = $this->service->getConsumptionHistory($membershipId);

        $this->assertNull($data['patient_name']);
        $this->assertSame(0, $data['total_services']);
        $this->assertSame([], $data['services']);
    }

    public function test_is_account_scoped(): void
    {
        // Ownership is derived from the membership's patient OR creator being in
        // the caller's account. Put both in another account so it's invisible.
        $otherUser = (int) DB::table('users')->insertGetId([
            'name' => 'Other Account User', 'email' => 'other-'.uniqid().'@test.local',
            'password' => bcrypt('test'), 'phone' => '+923000000099',
            'user_type_id' => 3, 'account_id' => 999, 'active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $otherMembership = (int) DB::table('memberships')->insertGetId([
            'code' => 'OTHER-'.strtoupper(substr(uniqid(), -6)),
            'membership_type_id' => $this->membershipTypeId, 'patient_id' => $otherUser,
            'start_date' => now()->subDay(), 'end_date' => now()->addMonth(),
            'active' => 1, 'created_by' => $otherUser,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(ModelNotFoundException::class);
        $this->service->getConsumptionHistory($otherMembership);
    }
}
