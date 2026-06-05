<?php

declare(strict_types=1);

namespace Tests\Feature\Plan;

use App\Helpers\ActivityLogger;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\Packages;
use App\Models\Patients;
use App\Services\PatientManagement\PatientService;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Plans-module happenings must land on the patient's Activity tab.
 *
 * Two events previously left no trail at all: creating a plan/membership
 * (logPackageCreated was dead code) and recording a payment on a membership
 * edit (the membership edit path logged nothing). These pin that both event
 * types surface through the real patient-card feed path
 * (PatientService::getActivityHistory → ActivityLogService).
 */
class PlanActivityLoggingTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    private function makePackage(Patients $patient, Locations $location): Packages
    {
        return Packages::create([
            'random_id' => 'test-'.$patient->id,
            'patient_id' => $patient->id,
            'location_id' => $location->id,
            'total_price' => 10000,
            'sessioncount' => '1',
            'account_id' => (int) Auth::user()->account_id,
            'plan_type' => 'membership',
            'name' => '00001',
        ]);
    }

    public function test_plan_creation_shows_on_the_patient_activity_feed(): void
    {
        $patient = Patients::factory()->create();
        $location = Locations::first();
        $package = $this->makePackage($patient, $location);

        ActivityLogger::logPackageCreated($package, $patient, $location);

        $logs = app(PatientService::class)->getActivityHistory($patient->id);
        $types = collect($logs)->pluck('type');

        $this->assertContains('package_created', $types->all(), 'Creating a plan must log a patient activity.');
    }

    public function test_payment_received_shows_on_the_patient_activity_feed(): void
    {
        $patient = Patients::factory()->create();
        $location = Locations::first();
        $package = $this->makePackage($patient, $location);

        // logPaymentReceived only reads $payment->cash_amount — an unsaved
        // row is enough to exercise the logger + feed contract.
        $payment = new PackageAdvances(['cash_amount' => 6000]);

        ActivityLogger::logPaymentReceived($payment, $package, $patient, $location);

        $logs = app(PatientService::class)->getActivityHistory($patient->id);
        $row = collect($logs)->firstWhere('type', 'payment_received');

        $this->assertNotNull($row, 'Recording a payment must log a patient activity.');
    }
}
