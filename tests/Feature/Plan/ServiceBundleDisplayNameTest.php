<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Services\Plan\PlanService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * `package_bundles.bundle_id` is a polymorphic FK: for a `service_bundle`
 * row it points at `service_bundles.id`, NOT `bundles.id`. When an unrelated
 * `bundles` record happens to share that id, the eager-loaded `bundle`
 * relation resolves to the WRONG record — and the plan View/Edit surfaces
 * rendered that bundle's name (e.g. plan #50321 showed "Ultraformer - Double
 * Chin HIFU" over its 3× Mesotherapy sessions). Print was always correct
 * because it reads `serviceBundle.service` directly.
 *
 * Pins that both the Edit (`service_name`) and View (`bundle` relation) names
 * resolve to the repeated service prefixed with qty, regardless of the
 * colliding `bundles` row.
 */
class ServiceBundleDisplayNameTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private PlanService $service;

    private int $patientId;

    private int $locationId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
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
            'email' => 'svc-bundle-'.uniqid().'@test.local',
            'password' => bcrypt('test'),
            'phone' => '+923000000004',
            'user_type_id' => 3,
            'account_id' => 1,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Build a plan with one `service_bundle` row (3× a single service) whose
     * `bundle_id` is ALSO a live `bundles.id` with a different name — the
     * exact id-collision behind the bug.
     */
    private function makeCollidingServiceBundlePlan(): int
    {
        $serviceId = (int) DB::table('services')->insertGetId([
            'name' => 'Mesotherapy Face',
            'price' => 7997,
            'end_node' => 1,
            'tax_treatment_type_id' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // The decoy bundle. Its id is forced to equal the service_bundle id
        // below so `bundle()` (BelongsTo Bundles via bundle_id) mis-resolves.
        $collidingId = (int) DB::table('bundles')->insertGetId([
            'name' => 'Ultraformer - Double Chin HIFU',
            'price' => 25189.50,
            'services_price' => 25189.50,
            'total_services' => 1,
            'tax_treatment_type_id' => 1,
            'active' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Force the service_bundle to share the decoy bundle's id.
        DB::table('service_bundles')->insert([
            'id' => $collidingId,
            'service_id' => $serviceId,
            'sessions' => 3,
            'price' => 23991,
            'active' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $packageId = (int) DB::table('packages')->insertGetId([
            'patient_id' => $this->patientId,
            'location_id' => $this->locationId,
            'name' => 'Test Plan '.uniqid(),
            'plan_type' => 'plan',
            'total_price' => 25189.50,
            'account_id' => 1,
            'active' => 1,
            'random_id' => 'TEST-SB-'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $bundleRowId = (int) DB::table('package_bundles')->insertGetId([
            'package_id' => $packageId,
            'random_id' => 'TEST-PB-'.uniqid(),
            'bundle_id' => $collidingId,        // collides with bundles.id
            'source_type' => 'service_bundle',
            'qty' => 3,
            'is_allocate' => 1,
            'service_price' => 25189.50,
            'net_amount' => 23991,
            'tax_exclusive_net_amount' => 23991,
            'tax_percentage' => 5,
            'tax_price' => 1200,
            'tax_including_price' => 25189.50,
            'location_id' => $this->locationId,
            'active' => 1,
            'is_exclusive' => 0,
            'discount_name' => '-',
            'discount_type' => '-',
            'discount_price' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (range(1, 3) as $i) {
            DB::table('package_services')->insert([
                'package_id' => $packageId,
                'package_bundle_id' => $bundleRowId,
                'service_id' => $serviceId,
                'sold_by' => $this->patientId,
                'consumption_order' => 0,
                'is_consumed' => 0,
                'price' => 7997,
                'orignal_price' => 7997,
                'tax_including_price' => 8396.50,
                'tax_price' => 400,
                'tax_exclusive_price' => 7997,
                'tax_percentage' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $packageId;
    }

    public function test_edit_form_data_uses_the_service_name_not_the_colliding_bundle(): void
    {
        $packageId = $this->makeCollidingServiceBundlePlan();

        $pb = $this->service->getEditFormData($packageId)['packagebundles']->first();

        $this->assertSame('3x Mesotherapy Face', $pb->service_name);
        $this->assertStringNotContainsString('Ultraformer', (string) $pb->service_name);
    }

    public function test_display_data_uses_the_service_name_not_the_colliding_bundle(): void
    {
        $packageId = $this->makeCollidingServiceBundlePlan();

        $pb = $this->service->getDisplayData($packageId)['packagebundles']->first();

        // The SPA View dialog reads `pb.bundle?.name` — it must be the
        // repeated service, never the colliding decoy bundle.
        $this->assertSame('3x Mesotherapy Face', $pb->bundle?->name);
        $this->assertStringNotContainsString('Ultraformer', (string) $pb->bundle?->name);
    }
}
