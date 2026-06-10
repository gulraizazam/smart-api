<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Doctor Ratings detail (by-service) — clicking a doctor in the report opens a
 * category/service breakdown. It must honour the date range carried from the
 * report (?date_from/&date_to); previously it was all-time and ignored it.
 */
class DoctorRatingsByServiceTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_by_service_scopes_aggregates_to_the_given_date_range(): void
    {
        $doctor = User::factory()->doctor()->create();

        // One feedback today (in range), one 60 days ago (out of range).
        Feedback::create(['doctor_id' => $doctor->id, 'rating' => 8, 'created_by' => Auth::id()]);
        $old = Feedback::create(['doctor_id' => $doctor->id, 'rating' => 2, 'created_by' => Auth::id()]);
        Feedback::where('id', $old->id)->update(['created_at' => now()->subDays(60)]);

        $today = now()->format('Y-m-d');

        // Range = today only → just the in-range feedback counts (the 60-day-old
        // rating-2 feedback is excluded, so the average reflects only today's 8).
        $todayResponse = $this->getJson("/api/reports/doctor-ratings/{$doctor->id}/by-service?date_from={$today}&date_to={$today}")
            ->assertOk()
            ->assertJsonPath('data.total_feedbacks', 1);
        $this->assertSame(8.0, (float) $todayResponse->json('data.overall_avg_rating'));

        // No range → all-time, both feedbacks count.
        $this->getJson("/api/reports/doctor-ratings/{$doctor->id}/by-service")
            ->assertOk()
            ->assertJsonPath('data.total_feedbacks', 2);
    }
}
