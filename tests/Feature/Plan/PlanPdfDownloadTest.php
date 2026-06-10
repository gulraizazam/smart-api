<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Happy-path contract for GET /api/packages/pdf/{id} — the endpoint behind
 * the SPA plan dialog's "Save PDF" button (wired 2026-06-10; before that the
 * button opened the print-styled tab and never downloaded a file). The
 * cross-tenant 404 side is pinned in PlanIdorAttackVectorsTest; this suite
 * pins that a same-account plan actually renders to PDF bytes with the
 * download filename the SPA saves under, so a Blade-view / dompdf regression
 * can't silently break the download.
 */
class PlanPdfDownloadTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin(); // account_id = 1
    }

    public function test_same_account_plan_downloads_as_a_real_pdf(): void
    {
        [$packageId, $patientId] = $this->seedOwnPlan();

        $response = $this->get("/api/packages/pdf/{$packageId}");

        $response->assertOk();
        $this->assertStringContainsString(
            'application/pdf',
            (string) $response->headers->get('content-type'),
        );
        // dompdf's stream() names the file (unquoted); downloadBlob() in the
        // SPA saves under this Content-Disposition filename. Convention
        // (user-specified 2026-06-10): C-{patient id}-{plan id}.pdf, no prefix.
        $this->assertStringContainsString(
            "filename=C-{$patientId}-{$packageId}.pdf",
            (string) $response->headers->get('content-disposition'),
        );
        $this->assertStringStartsWith(
            '%PDF',
            $response->getContent(),
            'Body must be actual PDF bytes, not an HTML error page.',
        );
    }

    /* ===================== fixtures ===================== */

    /**
     * Seed an account-1 plan (patient + appointment + one service line).
     *
     * @return array{0:int, 1:int} [package_id, patient_id]
     */
    private function seedOwnPlan(): array
    {
        // The packagepdf Blade view reads $company_phone_number->data
        // unguarded — prod always carries the sys-headoffice row, so the
        // fixture must too.
        DB::table('settings')->insertOrIgnore([
            'name' => 'Head Office Contact', 'data' => '0311 111 33 55',
            'slug' => 'sys-headoffice', 'account_id' => 1, 'active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $locId = (int) $this->defaultLocation->id;
        $patientId = DB::table('users')->insertGetId([
            'account_id' => 1, 'name' => 'A-patient',
            'email' => 'ap+'.uniqid().'@x.test', 'password' => bcrypt('x'),
            'user_type_id' => 3, 'active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $appointmentId = DB::table('appointments')->insertGetId([
            'account_id' => 1, 'patient_id' => $patientId, 'location_id' => $locId,
            'lead_id' => 0, 'doctor_id' => 0, 'region_id' => 1, 'city_id' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $packageId = DB::table('packages')->insertGetId([
            'random_id' => (string) Str::uuid(), 'account_id' => 1,
            'name' => 'A-plan', 'plan_name' => 'a', 'sessioncount' => 1,
            'total_price' => 1000, 'is_exclusive' => 0, 'plan_type' => 'plan',
            'patient_id' => $patientId, 'location_id' => $locId, 'active' => 1,
            'appointment_id' => $appointmentId, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('package_bundles')->insert([
            'account_id' => 1,
            'package_id' => $packageId,
            'bundle_id' => 1,
            'qty' => 1,
            'source_type' => 'service',
            'service_price' => 1000,
            'tax_including_price' => 1000,
            'net_amount' => 1000,
            'random_id' => (string) Str::uuid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [(int) $packageId, (int) $patientId];
    }
}
