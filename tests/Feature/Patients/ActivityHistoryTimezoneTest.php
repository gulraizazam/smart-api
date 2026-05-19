<?php

declare(strict_types=1);

namespace Tests\Feature\Patients;

use App\Models\Activity;
use App\Models\Patients;
use App\Services\PatientManagement\PatientService;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Regression: the patient-card Activity tab showed times 5 hours ahead.
 *
 * `activities.created_at/updated_at` are written by Eloquent under
 * config('app.timezone') = Asia/Karachi (the DB connection does no UTC
 * conversion), so the stored value is already local wall-clock time.
 * ActivityLogService parsed it AS UTC and then setTimezone()-ed it to
 * Karachi, double-applying the +5h offset (a 1:20 PM event rendered as
 * 6:20 PM). This pins the stored-local timestamp through the real
 * patient-card path (PatientService::getActivityHistory →
 * ActivityLogService) and asserts it is rendered unshifted.
 */
class ActivityHistoryTimezoneTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_activity_time_is_not_shifted_by_the_app_utc_offset(): void
    {
        $patient = Patients::factory()->create();

        // A known wall-clock instant in the app timezone, exactly as
        // Eloquent would have persisted it. timestamps=false so save()
        // does not overwrite it with now().
        $stored = '2026-05-18 13:20:27';

        $activity = new Activity();
        $activity->account_id = (int) Auth::user()->account_id;
        $activity->patient_id = $patient->id;
        $activity->action = 'created';
        // Unknown type → compact renderer returns null → description path;
        // keeps the test about time, not the renderer. Not 'lead_converted'
        // (the only display-suppressed type).
        $activity->activity_type = 'test_event';
        $activity->description = 'Test activity';
        $activity->created_by = (int) Auth::id();
        $activity->timestamps = false;
        $activity->created_at = $stored;
        $activity->updated_at = $stored;
        $activity->save();

        $logs = app(PatientService::class)->getActivityHistory($patient->id);

        $this->assertNotEmpty($logs, 'The patient must have at least the seeded activity.');
        $row = collect($logs)->firstWhere('created_at', $stored)
            ?? collect($logs)->first();

        $this->assertSame(
            'May 18, 2026 1:20 PM',
            $row['time_formatted'],
            'Stored local wall-clock time must render unshifted — a +5h '
            .'result (6:20 PM) is the timezone double-apply bug.',
        );
        $this->assertSame('05-18-2026 13:20', $row['time_short']);
    }
}
