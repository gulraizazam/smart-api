<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Services\Plan\PlanService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Eligible-discounts list for the plan-create dialog (the discount
 * dropdown that opens after a service is picked).
 *
 * Vouchers live in the same `discounts` table as regular discounts but
 * are gated very differently:
 *   - regular discount → tier / role / date window
 *   - voucher          → must have a `user_vouchers` row for THIS
 *                        patient (the assignment IS the gate)
 *
 * That divergence has burned us before — vouchers leaking into the
 * pre-patient call would let an operator pick a voucher before the
 * patient has been assigned one, then the save endpoint would silently
 * fail to decrement `user_vouchers.amount` because there was nothing
 * to decrement. These tests pin the two-track filter so a future
 * refactor can't quietly collapse it.
 *
 * Also pinned: each row carries `discount_type` so the SPA can render
 * the "(voucher)" / "(discount)" suffix that lets operators tell them
 * apart in the dropdown.
 */
class PlanDiscountCatalogTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private PlanService $service;

    private int $patientId;

    private int $serviceId;

    private int $locationId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();

        // Acting user — bypass the staff-role gate in
        // buildEligibleDiscounts by giving the test user role_id = 1
        // (the "Administrator" privileged role the gate special-cases).
        $admin = $this->actingAsAdmin();

        DB::table('roles')->updateOrInsert(
            ['id' => 1],
            ['name' => 'Administrator', 'guard_name' => 'web', 'commission' => 0, 'created_at' => now(), 'updated_at' => now()],
        );
        DB::table('model_has_roles')->updateOrInsert(
            ['role_id' => 1, 'model_id' => $admin->id, 'model_type' => 'App\\Models\\User'],
            [],
        );

        $this->service = app(PlanService::class);

        $this->locationId = (int) DB::table('locations')->insertGetId([
            'name' => 'Test Centre',
            'city_id' => 1,
            'region_id' => 1,
            'address' => '1 Test St',
            'account_id' => 1,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->patientId = (int) DB::table('users')->insertGetId([
            'name' => 'Test Patient',
            'email' => 'plan-discount-patient-'.uniqid().'@test.local',
            'password' => bcrypt('test'),
            'phone' => '+923000000001',
            'user_type_id' => 3,
            'account_id' => 1,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->serviceId = (int) DB::table('services')->insertGetId([
            'name' => 'Test Service',
            'price' => 1000,
            // end_node=1 makes this a leaf — discounts surface against
            // leaves, never categories, so the test fixtures need to
            // be leaves or service_ids hydration will leave them out.
            'end_node' => 1,
            'tax_treatment_type_id' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeDiscount(string $name, string $discountType): int
    {
        $id = (int) DB::table('discounts')->insertGetId([
            'name' => $name,
            'slug' => 'slug-'.uniqid(),
            'type' => 'Fixed',
            'amount' => 100,
            'discount_type' => $discountType,
            'pre_days' => 0,
            'post_days' => 0,
            'start' => now()->subDay(),
            'end' => now()->addMonth(),
            'active' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Allocation row — buildEligibleDiscounts filters out orphan
        // discounts with no rows in discount_has_locations.
        DB::table('discount_has_locations')->insert([
            'discount_id' => $id,
            'location_id' => $this->locationId,
            'service_id' => $this->serviceId,
            'type' => 'Fixed',
            'amount' => 100,
            'slug' => 'default',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $id;
    }

    private function assignVoucher(int $voucherId): void
    {
        DB::table('user_vouchers')->insert([
            'user_id' => $this->patientId,
            'voucher_id' => $voucherId,
            'amount' => 500,
            'total_amount' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_pre_patient_call_excludes_vouchers_even_when_active_and_allocated(): void
    {
        $regularId = $this->makeDiscount('Regular Promo', 'Treatment');
        $voucherId = $this->makeDiscount('Gift Voucher 500', 'voucher');

        $data = $this->service->getCreateFormData([$this->locationId]);

        $ids = collect($data['discounts'])->pluck('id')->all();
        $this->assertContains($regularId, $ids);
        $this->assertNotContains(
            $voucherId,
            $ids,
            'Pre-patient call must not surface vouchers — assignment lives on user_vouchers.',
        );
    }

    public function test_patient_call_includes_only_vouchers_assigned_to_that_patient(): void
    {
        $regularId = $this->makeDiscount('Regular Promo', 'Treatment');
        $assignedVoucherId = $this->makeDiscount('Assigned Voucher', 'voucher');
        $unassignedVoucherId = $this->makeDiscount('Unassigned Voucher', 'voucher');

        $this->assignVoucher($assignedVoucherId);

        $data = $this->service->getCreateFormDataForPatient([$this->locationId], $this->patientId);

        $ids = collect($data['discounts'])->pluck('id')->all();
        $this->assertContains($regularId, $ids);
        $this->assertContains(
            $assignedVoucherId,
            $ids,
            'Patient-assigned voucher must appear in the dropdown.',
        );
        $this->assertNotContains(
            $unassignedVoucherId,
            $ids,
            'A voucher type without a matching user_vouchers row must NOT leak in — '
            . 'assignment is the only gate that protects against picking a balance the patient has not been given.',
        );
    }

    public function test_each_row_carries_discount_type_for_the_voucher_vs_discount_suffix(): void
    {
        $regularId = $this->makeDiscount('Regular Promo', 'Treatment');
        $voucherId = $this->makeDiscount('Birthday Voucher', 'voucher');
        $this->assignVoucher($voucherId);

        $data = $this->service->getCreateFormDataForPatient([$this->locationId], $this->patientId);

        $byId = collect($data['discounts'])->keyBy('id');
        $this->assertSame('Treatment', $byId[$regularId]->discount_type);
        $this->assertSame('voucher', $byId[$voucherId]->discount_type);
    }

    public function test_assigned_voucher_still_shows_after_its_end_date_expires(): void
    {
        // Vouchers are different from regular discounts: once a balance
        // has been handed to a patient (user_vouchers row exists), the
        // patient owns the balance. Hiding the voucher from the dropdown
        // after the voucher TYPE's end date would strand that
        // unredeemed balance and contradict the legacy admin's
        // behaviour (where the voucher half of the discount union skips
        // active/date checks entirely).
        $expiredVoucherId = (int) DB::table('discounts')->insertGetId([
            'name' => 'Expired but Assigned',
            'slug' => 'expired-'.uniqid(),
            'type' => 'Fixed',
            'amount' => 0,
            'discount_type' => 'voucher',
            'pre_days' => 0,
            'post_days' => 0,
            // End date in the past — would fail applicableNow's date
            // window, but the patient already holds the balance.
            'start' => now()->subMonths(2),
            'end' => now()->subMonth(),
            'active' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('discount_has_locations')->insert([
            'discount_id' => $expiredVoucherId,
            'location_id' => $this->locationId,
            'service_id' => $this->serviceId,
            'type' => 'Fixed',
            'amount' => 100,
            'slug' => 'default',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assignVoucher($expiredVoucherId);

        $data = $this->service->getCreateFormDataForPatient([$this->locationId], $this->patientId);

        $ids = collect($data['discounts'])->pluck('id')->all();
        $this->assertContains(
            $expiredVoucherId,
            $ids,
            'An expired voucher with an outstanding user_vouchers row must still appear — '
            . 'hiding it strands the balance.',
        );
    }

    public function test_general_discount_outside_the_date_window_is_still_excluded(): void
    {
        // The active/date gate still has to apply to regular discounts —
        // the voucher bypass is specific to vouchers. Without this guard
        // an expired sale would keep showing up indefinitely.
        $expiredRegularId = (int) DB::table('discounts')->insertGetId([
            'name' => 'Expired Sale',
            'slug' => 'expired-sale-'.uniqid(),
            'type' => 'Fixed',
            'amount' => 100,
            'discount_type' => 'Treatment',
            'pre_days' => 0,
            'post_days' => 0,
            'start' => now()->subMonths(2),
            'end' => now()->subMonth(),
            'active' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('discount_has_locations')->insert([
            'discount_id' => $expiredRegularId,
            'location_id' => $this->locationId,
            'service_id' => $this->serviceId,
            'type' => 'Fixed',
            'amount' => 100,
            'slug' => 'default',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $data = $this->service->getCreateFormDataForPatient([$this->locationId], $this->patientId);

        $ids = collect($data['discounts'])->pluck('id')->all();
        $this->assertNotContains(
            $expiredRegularId,
            $ids,
            'Expired regular discounts must NOT leak through — the voucher bypass is voucher-only.',
        );
    }

    public function test_voucher_service_allocation_is_resolved_via_discount_has_locations(): void
    {
        // Vouchers are stored with type='Fixed' so they take the simple
        // (discount_has_locations) branch when service_ids is hydrated,
        // not the configurable (base_discount_services) branch. Pin
        // this so a future "all vouchers are configurable" refactor
        // doesn't silently wipe service eligibility.
        $voucherId = $this->makeDiscount('Service-Locked Voucher', 'voucher');
        $this->assignVoucher($voucherId);

        $data = $this->service->getCreateFormDataForPatient([$this->locationId], $this->patientId);

        $voucher = collect($data['discounts'])->firstWhere('id', $voucherId);
        $this->assertNotNull($voucher);
        $this->assertContains(
            $this->serviceId,
            $voucher->service_ids,
            'Voucher service eligibility must come from discount_has_locations — same path as simple discounts.',
        );
    }

    public function test_category_allocation_expands_to_every_leaf_descendant(): void
    {
        // Allocate a discount against a category (end_node=0). The SPA
        // filters by `service_ids.includes(picked)`, so the backend has
        // to flatten the category into its leaf descendants — otherwise
        // picking a leaf under the category would never match the
        // literal category id in the allocation row.
        // This is the body-contouring → CoolSculpt-large case from the
        // production screenshot.
        $categoryId = (int) DB::table('services')->insertGetId([
            'name' => 'Test Category',
            'price' => 0,
            'parent_id' => 0,
            'end_node' => 0, // category, not a leaf
            'tax_treatment_type_id' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $leafA = (int) DB::table('services')->insertGetId([
            'name' => 'Leaf A',
            'price' => 100,
            'parent_id' => $categoryId,
            'end_node' => 1,
            'tax_treatment_type_id' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $leafB = (int) DB::table('services')->insertGetId([
            'name' => 'Leaf B',
            'price' => 200,
            'parent_id' => $categoryId,
            'end_node' => 1,
            'tax_treatment_type_id' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $discountId = (int) DB::table('discounts')->insertGetId([
            'name' => 'Category Discount',
            'slug' => 'cat-'.uniqid(),
            'type' => 'Fixed',
            'amount' => 50,
            'discount_type' => 'Treatment',
            'pre_days' => 0,
            'post_days' => 0,
            'start' => now()->subDay(),
            'end' => now()->addMonth(),
            'active' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // Allocation row points at the category — what the legacy admin
        // writes when you tick "Body Contouring".
        DB::table('discount_has_locations')->insert([
            'discount_id' => $discountId,
            'location_id' => $this->locationId,
            'service_id' => $categoryId,
            'type' => 'Fixed',
            'amount' => 50,
            'slug' => 'default',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $data = $this->service->getCreateFormDataForPatient([$this->locationId], $this->patientId);

        $discount = collect($data['discounts'])->firstWhere('id', $discountId);
        $this->assertNotNull($discount);
        $this->assertContains($leafA, $discount->service_ids);
        $this->assertContains($leafB, $discount->service_ids);
        $this->assertNotContains(
            $categoryId,
            $discount->service_ids,
            'Categories (end_node=0) should not appear in service_ids — they expand into their leaves so the SPA filter can do a literal id match.',
        );
    }

    public function test_all_services_sentinel_expands_to_every_leaf_in_the_account(): void
    {
        // Some allocations point at a special "All Services" row with
        // slug='all' (the legacy admin's "All Services" option). The
        // SPA filter only sees `service_ids`, so the backend has to
        // substitute the sentinel with every leaf in the account —
        // otherwise the discount would never match anything.
        $allServicesId = (int) DB::table('services')->insertGetId([
            'name' => 'All Services',
            'price' => 0,
            'parent_id' => null,
            'end_node' => 1,
            'slug' => 'all',
            'tax_treatment_type_id' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $unrelatedLeaf = (int) DB::table('services')->insertGetId([
            'name' => 'Unrelated Leaf',
            'price' => 100,
            'parent_id' => null,
            'end_node' => 1,
            'tax_treatment_type_id' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $voucherId = (int) DB::table('discounts')->insertGetId([
            'name' => 'Sentinel Voucher',
            'slug' => 'sentinel-'.uniqid(),
            'type' => 'Fixed',
            'amount' => 0,
            'discount_type' => 'voucher',
            'pre_days' => 0,
            'post_days' => 0,
            'start' => now()->subDay(),
            'end' => now()->addMonth(),
            'active' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('discount_has_locations')->insert([
            'discount_id' => $voucherId,
            'location_id' => $this->locationId,
            'service_id' => $allServicesId,
            'type' => 'Fixed',
            'amount' => 0,
            'slug' => 'default',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->assignVoucher($voucherId);

        $data = $this->service->getCreateFormDataForPatient([$this->locationId], $this->patientId);

        $voucher = collect($data['discounts'])->firstWhere('id', $voucherId);
        $this->assertNotNull($voucher);
        $this->assertContains(
            $this->serviceId,
            $voucher->service_ids,
            'All-Services sentinel must expand to include the setup-created leaf service.',
        );
        $this->assertContains(
            $unrelatedLeaf,
            $voucher->service_ids,
            'All-Services sentinel must include every leaf in the account — not just the one the test happens to use.',
        );
    }
}
