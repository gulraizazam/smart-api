<?php

declare(strict_types=1);

namespace Tests\Feature\Treatments;

use App\Models\Appointments;
use App\Models\UserHasLocations;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Treatment invoice dialog (`GET /api/treatment/invoice/{id}/plan/{packageId}`
 * → TreatmentController::invoicePlanInfo).
 *
 * A `service_bundle` row's `bundle_id` is a polymorphic FK pointing at
 * `service_bundles`, NOT `bundles`. When an unrelated `bundles` record shares
 * that id (and lists the same service), the endpoint's `bundles.name` JOIN
 * labelled the row with the wrong bundle (e.g. "Aqualyx - Double chin 2ml"
 * over 3× Mesotherapy). Pins that the row now shows the repeated service,
 * prefixed with qty — without disturbing the consumption/pricing fields.
 */
class TreatmentInvoiceServiceBundleNameTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $admin = $this->actingAsAdmin();

        // The invoice endpoint scopes the appointment to the caller's
        // assigned centres (ACL::getUserCentres). Only user id 1 sees all,
        // and the suite's auto-increment means this admin rarely lands on 1 —
        // so assign it to the test centre explicitly or the lookup 404s.
        UserHasLocations::create([
            'user_id' => $admin->id,
            'region_id' => 1,
            'location_id' => $this->defaultLocation->id,
        ]);
    }

    public function test_service_bundle_row_shows_the_service_name_not_the_colliding_bundle(): void
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

        // Decoy bundle that ALSO lists the service — so the path-1
        // `bundles.name` JOIN catches the service_bundle row and mislabels it.
        $collidingId = (int) DB::table('bundles')->insertGetId([
            'name' => 'Aqualyx - Double chin 2ml',
            'price' => 25190,
            'services_price' => 25190,
            'total_services' => 1,
            'tax_treatment_type_id' => 1,
            'active' => 1,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('bundle_has_services')->insert([
            'bundle_id' => $collidingId,
            'service_id' => $serviceId,
            'service_price' => 7997,
            'calculated_price' => 7997,
            'end_node' => 1,
        ]);

        // The real source — forced to share the decoy bundle's id.
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

        $appointment = Appointments::factory()->create([
            'account_id' => 1,
            'appointment_type_id' => 2, // Treatment
            'service_id' => $serviceId,
            'location_id' => $this->defaultLocation->id,
        ]);

        $packageId = (int) DB::table('packages')->insertGetId([
            'patient_id' => $appointment->patient_id,
            'location_id' => $this->defaultLocation->id,
            'name' => 'Test Plan '.uniqid(),
            'plan_type' => 'plan',
            'total_price' => 25190,
            'account_id' => 1,
            'active' => 1,
            'random_id' => 'TEST-TI-'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $bundleRowId = (int) DB::table('package_bundles')->insertGetId([
            'package_id' => $packageId,
            'random_id' => 'TEST-PB-'.uniqid(),
            'bundle_id' => $collidingId,
            'source_type' => 'service_bundle',
            'qty' => 3,
            'is_allocate' => 1,
            'service_price' => 25190,
            'net_amount' => 23991,
            'tax_including_price' => 25190,
            'location_id' => $this->defaultLocation->id,
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
                'price' => 7997,
                'orignal_price' => 7997,
                'tax_including_price' => 8397,
                'is_consumed' => 0,
                'consumption_order' => 0,
                'random_id' => 'TEST-PS-'.uniqid(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $response = $this->getJson("/api/treatment/invoice/{$appointment->id}/plan/{$packageId}");

        $response->assertOk();
        $name = $response->json('data.package_bundles.0.bundlename');

        $this->assertSame('3x Mesotherapy Face', $name);
        $this->assertStringNotContainsString('Aqualyx', (string) $name);
    }
}
