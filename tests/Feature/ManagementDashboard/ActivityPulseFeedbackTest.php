<?php

declare(strict_types=1);

namespace Tests\Feature\ManagementDashboard;

use App\Helpers\ActivityLogRenderer;
use App\Models\Activity;
use App\Models\Feedback;
use App\Services\Dashboard\Metrics\ActivityPulseMetric;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\TestCase;

/**
 * The Overview / FDM "Today's Activities" feed leads a feedback row with the
 * client and the rating they gave — "<patient> rated their visit N/10" — read
 * from the linked feedback row via the eager-loaded feedbackR relation.
 *
 * Reverting the renderer wording, dropping the eager-load in
 * ActivityPulseMetric, or removing the Activity::feedbackR relation each makes
 * the dashboard fall back to the old rating-less phrasing, which this fails on.
 */
class ActivityPulseFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_feed_shows_client_and_their_rating(): void
    {
        // The renderer matches the feedback to its activity on appointment_id;
        // only the column value matters, so skip the appointments FK rather
        // than build a full appointment graph (users/user_types/etc.).
        Schema::withoutForeignKeyConstraints(function (): void {
            Feedback::create([
                'appointment_id' => 9001,
                'rating' => 8,
            ]);

            Activity::create([
                'account_id' => 1,
                'activity_type' => 'feedback_added',
                'action' => 'Feedback Added',
                // The feed line comes from renderCompact, not this stored
                // column; set it only because activities.description is NOT NULL.
                'description' => 'Feedback added',
                'appointment_id' => 9001,
                'patient' => 'Jane Doe',
                'service' => 'Laser Hair Removal',
                'created_at' => '2026-06-06 10:00:00',
            ]);
        });

        $out = app(ActivityPulseMetric::class)->fetch(
            MetricScope::company(1),
            DateRange::fromStrings('2026-06-06', '2026-06-06'),
        );

        $this->assertNotEmpty($out['rows'], 'the feedback activity must be in the feed');
        $html = (string) $out['rows'][0]['description_html'];
        $this->assertStringContainsString('Client Jane Doe rated their visit 8/10', $html);
        $this->assertStringNotContainsString('Feedback added for', $html);
    }

    public function test_renderer_falls_back_when_feedback_relation_not_loaded(): void
    {
        // The audit-log report path does NOT eager-load feedbackR. The renderer
        // must keep the plain phrasing and never lazy-load (which would be an
        // N+1 across the report) — the relationLoaded() guard enforces this.
        $activity = new Activity([
            'activity_type' => 'feedback_added',
            'patient' => 'Jane Doe',
        ]);

        $html = (string) ActivityLogRenderer::renderCompact($activity);

        $this->assertStringContainsString('Feedback added for Jane Doe', $html);
        $this->assertStringNotContainsString('rated their visit', $html);
    }
}
