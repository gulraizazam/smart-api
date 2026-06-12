<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use App\Models\Appointments;
use App\Models\User;
use App\Models\UserHasLocations;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Consultancy list — "Created by / Updated by / Rescheduled by" advanced
 * filters. The SPA's filter shelf sends `created_by` / `updated_by` /
 * `rescheduled_by`; the controller forwards them and
 * AppointmentService::applyFilters must narrow the query to matching rows.
 *
 * `rescheduled_by` is the SPA label for the legacy `converted_by` column
 * (the same source ConsultancyResource / TreatmentResource read for their
 * `rescheduled_by` field), so the filter targets `appointments.converted_by`.
 *
 * Regression guard: before applyFilters handled these keys they were
 * silently dropped — the list came back unfiltered. Each test fails if the
 * corresponding clause is removed.
 */
class ConsultancyListActorFilterTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const CONSULTANCY_TYPE_ID = 2; // seeded by UsesFinancialFixtures

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $admin = $this->actingAsAdmin();

        // The list endpoint scopes rows to the acting user's assigned
        // centres (ACL::getUserCentres). Bind the admin to the fixture
        // location so the seeded consultancies are in scope.
        UserHasLocations::create([
            'user_id' => $admin->id,
            'region_id' => 1,
            'location_id' => $this->defaultLocation->id,
        ]);
    }

    /** Create a consultancy row pinned to the in-scope fixture location. */
    private function makeConsultancy(array $attributes = []): Appointments
    {
        return Appointments::factory()->create(array_merge([
            'account_id' => 1,
            'appointment_type_id' => self::CONSULTANCY_TYPE_ID,
            'location_id' => $this->defaultLocation->id,
        ], $attributes));
    }

    /** @return array<int,int> ids returned by the list for the given query */
    private function listIds(array $query): array
    {
        $response = $this->getJson(
            '/api/consultancy?' . http_build_query($query + ['paginate' => 'false']),
        );
        $response->assertOk();

        return array_map(
            static fn (array $row): int => (int) $row['id'],
            $response->json('data'),
        );
    }

    public function test_created_by_filter_narrows_to_the_chosen_creator(): void
    {
        $creator = User::factory()->create(['account_id' => 1]);
        $other = User::factory()->create(['account_id' => 1]);

        $mine = $this->makeConsultancy(['created_by' => $creator->id]);
        $this->makeConsultancy(['created_by' => $other->id]);

        $ids = $this->listIds(['created_by' => $creator->id]);

        $this->assertSame([$mine->id], $ids);
    }

    public function test_updated_by_filter_narrows_to_the_chosen_editor(): void
    {
        $editor = User::factory()->create(['account_id' => 1]);
        $other = User::factory()->create(['account_id' => 1]);

        $mine = $this->makeConsultancy(['updated_by' => $editor->id]);
        $this->makeConsultancy(['updated_by' => $other->id]);

        $ids = $this->listIds(['updated_by' => $editor->id]);

        $this->assertSame([$mine->id], $ids);
    }

    public function test_rescheduled_by_filter_targets_the_converted_by_column(): void
    {
        $rescheduler = User::factory()->create(['account_id' => 1]);
        $other = User::factory()->create(['account_id' => 1]);

        $mine = $this->makeConsultancy(['converted_by' => $rescheduler->id]);
        $this->makeConsultancy(['converted_by' => $other->id]);

        $ids = $this->listIds(['rescheduled_by' => $rescheduler->id]);

        $this->assertSame([$mine->id], $ids);
    }
}
