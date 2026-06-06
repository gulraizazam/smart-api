<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use App\Helpers\Widgets\LocationsWidget;
use App\Models\Locations;
use App\Models\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The treatment vs consultation doctor lists differ on Aesthetic Doctors:
 *   - Treatments: Consultant + Lifestyle Consultant + ALL Aesthetic Doctors.
 *   - Consultations: same, but Aesthetic Doctors need can_perform_consultation.
 *
 * Pre-fix the treatment form reused the consultant list, so Aesthetic Doctors
 * without the consultation flag were silently dropped from the treatment
 * doctor dropdown even though they're allocated to the centre.
 */
class TreatmentDoctorListTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private Locations $location;

    private int $serviceId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->location = Locations::factory()->create();
        $this->serviceId = Services::factory()->create(['account_id' => 1])->id;
    }

    private function makeDoctor(string $roleName, bool $canPerformConsultation = false): int
    {
        $user = User::factory()->create(['account_id' => 1, 'active' => 1]);
        DB::table('users')->where('id', $user->id)
            ->update(['can_perform_consultation' => $canPerformConsultation ? 1 : 0]);

        $this->assignRoleWithPivot($user, $this->createRole($roleName));

        DB::table('doctor_has_locations')->insert([
            'user_id' => $user->id,
            'location_id' => $this->location->id,
            'service_id' => $this->serviceId,
            'end_node' => 1,
            'is_allocated' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user->id;
    }

    public function test_treatment_list_includes_all_aesthetic_doctors(): void
    {
        $consultant = $this->makeDoctor('Consultant');
        $lifestyle = $this->makeDoctor('Lifestyle Consultant');
        $aestheticFlagged = $this->makeDoctor('Aesthetic Doctor', true);
        $aestheticUnflagged = $this->makeDoctor('Aesthetic Doctor', false);
        $csr = $this->makeDoctor('CSR');

        $ids = array_keys(LocationsWidget::loadTreatmentDoctorByLocation($this->location->id, 1)->toArray());

        $this->assertContains($consultant, $ids);
        $this->assertContains($lifestyle, $ids);
        $this->assertContains($aestheticFlagged, $ids);
        $this->assertContains($aestheticUnflagged, $ids, 'Aesthetic doctor WITHOUT the consultation flag must still appear for treatments.');
        $this->assertNotContains($csr, $ids, 'Non-doctor roles (e.g. CSR) must not appear.');
    }

    public function test_consultant_list_still_requires_consultation_flag_for_aesthetic(): void
    {
        $aestheticFlagged = $this->makeDoctor('Aesthetic Doctor', true);
        $aestheticUnflagged = $this->makeDoctor('Aesthetic Doctor', false);

        $ids = array_keys(LocationsWidget::loadConsultantDoctorByLocation($this->location->id, 1)->toArray());

        $this->assertContains($aestheticFlagged, $ids);
        $this->assertNotContains($aestheticUnflagged, $ids, 'Consultation list still excludes aesthetic doctors who cannot perform consultations.');
    }
}
