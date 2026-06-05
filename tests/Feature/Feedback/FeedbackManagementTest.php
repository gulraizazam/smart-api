<?php

declare(strict_types=1);

namespace Tests\Feature\Feedback;

use App\Models\Appointments;
use App\Models\Feedback;
use App\Models\Patients;
use App\Services\Feedback\FeedbackService;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Doctor Ratings management — the feedback `update` now persists the comment
 * as well as the rating (the management screen lets operators edit both).
 * Previously update() saved only the rating, silently dropping comment edits.
 */
class FeedbackManagementTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_update_persists_both_rating_and_comment(): void
    {
        $feedback = Feedback::create([
            'rating' => 5,
            'comment' => 'original note',
            'created_by' => Auth::id(),
        ]);

        app(FeedbackService::class)->update($feedback, 8, 'revised note');

        $feedback->refresh();
        $this->assertSame(8, (int) $feedback->rating);
        $this->assertSame('revised note', $feedback->comment);
    }

    public function test_update_can_clear_the_comment(): void
    {
        $feedback = Feedback::create([
            'rating' => 5,
            'comment' => 'will be cleared',
            'created_by' => Auth::id(),
        ]);

        app(FeedbackService::class)->update($feedback, 6, null);

        $feedback->refresh();
        $this->assertSame(6, (int) $feedback->rating);
        $this->assertNull($feedback->comment);
    }

    /**
     * The "Add feedback" treatment picker must list ALL un-rated arrived
     * treatments for a patient — not just the last 7 days. (The store
     * endpoint has no date limit, so the picker shouldn't either.) Rated
     * treatments are excluded.
     */
    public function test_available_treatments_lists_all_unrated_regardless_of_age(): void
    {
        $patient = Patients::factory()->create();

        $recent = Appointments::factory()->arrived()->create([
            'patient_id' => $patient->id,
            'appointment_type_id' => 2,
            'scheduled_date' => now()->subDays(3)->format('Y-m-d'),
        ]);
        $old = Appointments::factory()->arrived()->create([
            'patient_id' => $patient->id,
            'appointment_type_id' => 2,
            'scheduled_date' => now()->subDays(60)->format('Y-m-d'),
        ]);
        $rated = Appointments::factory()->arrived()->create([
            'patient_id' => $patient->id,
            'appointment_type_id' => 2,
            'scheduled_date' => now()->subDays(5)->format('Y-m-d'),
        ]);
        Feedback::create([
            'appointment_id' => $rated->id,
            'rating' => 8,
            'created_by' => Auth::id(),
        ]);

        $ids = app(FeedbackService::class)->getAvailableTreatments($patient->id)->pluck('id')->all();

        $this->assertContains($recent->id, $ids);
        $this->assertContains($old->id, $ids, '60-day-old un-rated treatment must still be listed.');
        $this->assertNotContains($rated->id, $ids, 'Already-rated treatment must be excluded.');
        $this->assertCount(2, $ids);
    }
}
