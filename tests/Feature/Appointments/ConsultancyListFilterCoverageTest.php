<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use App\Models\Appointments;
use App\Models\AppointmentStatuses;
use App\Models\UserHasLocations;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Consultancy list — coverage for the remaining filters the SPA's filter
 * shelf sends (status, doctor, service, scheduled-date range, created-date
 * range). Pairs with ConsultancyListActorFilterTest (created/updated/
 * rescheduled by). Each test creates two rows differing only in the filtered
 * attribute and asserts the filter returns exactly the matching one, so the
 * suite goes red if any clause in AppointmentService::applyFilters is dropped.
 */
class ConsultancyListFilterCoverageTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const CONSULTANCY_TYPE_ID = 2; // seeded by UsesFinancialFixtures

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $admin = $this->actingAsAdmin();

        UserHasLocations::create([
            'user_id' => $admin->id,
            'region_id' => 1,
            'location_id' => $this->defaultLocation->id,
        ]);
    }

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

    public function test_status_filter_narrows_to_the_chosen_status(): void
    {
        $statusA = AppointmentStatuses::factory()->create();
        $statusB = AppointmentStatuses::factory()->create();

        $mine = $this->makeConsultancy(['appointment_status_id' => $statusA->id]);
        $this->makeConsultancy(['appointment_status_id' => $statusB->id]);

        $this->assertSame(
            [$mine->id],
            $this->listIds(['appointment_status_id' => $statusA->id]),
        );
    }

    public function test_doctor_filter_narrows_to_the_chosen_doctor(): void
    {
        $mine = $this->makeConsultancy();
        $this->makeConsultancy();

        $this->assertSame(
            [$mine->id],
            $this->listIds(['doctor_id' => $mine->doctor_id]),
        );
    }

    public function test_service_filter_narrows_to_the_chosen_service(): void
    {
        $mine = $this->makeConsultancy();
        $this->makeConsultancy();

        $this->assertSame(
            [$mine->id],
            $this->listIds(['service_id' => $mine->service_id]),
        );
    }

    public function test_scheduled_date_range_filter_narrows_to_the_window(): void
    {
        $inside = $this->makeConsultancy(['scheduled_date' => '2026-01-15']);
        $this->makeConsultancy(['scheduled_date' => '2026-03-15']);

        $this->assertSame(
            [$inside->id],
            $this->listIds([
                'scheduled_date_from' => '2026-01-01',
                'scheduled_date_to' => '2026-01-31',
            ]),
        );
    }

    public function test_created_date_range_filter_narrows_to_the_window(): void
    {
        $inside = $this->makeConsultancy(['created_at' => '2026-01-15 10:00:00']);
        $this->makeConsultancy(['created_at' => '2026-03-15 10:00:00']);

        $this->assertSame(
            [$inside->id],
            $this->listIds([
                'created_date_from' => '2026-01-01',
                'created_date_to' => '2026-01-31',
            ]),
        );
    }
}
