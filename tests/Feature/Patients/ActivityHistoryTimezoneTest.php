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
 * The patient-card Activity tab renders times in Asia/Karachi.
 *
 * Storage convention (shared by crm2 + crm3): activity timestamps are kept in
 * UTC and read back as UTC→Asia/Karachi for display. A writer supplies the PKT
 * wall-clock it observed; the Activity model normalises it to UTC on write;
 * PatientService::getActivityHistory converts it back to PKT. This pins that
 * round-trip end-to-end.
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

    public function test_activity_is_stored_utc_and_renders_back_to_pkt(): void
    {
        $patient = Patients::factory()->create();

        // Writer logs at 1:20 PM PKT (the wall-clock it observed).
        $activity = Activity::create([
            'account_id' => (int) Auth::user()->account_id,
            'patient_id' => $patient->id,
            'action' => 'created',
            // Unknown type → compact renderer returns null → description path;
            // keeps the test about time, not the renderer.
            'activity_type' => 'test_event',
            'description' => 'Test activity',
            'created_by' => (int) Auth::id(),
            'created_at' => '2026-05-18 13:20:27',
            'updated_at' => '2026-05-18 13:20:27',
        ]);

        // The model hook normalised both to UTC on write (1:20 PM PKT → 8:20 AM
        // UTC). The feed displays updated_at (falling back to created_at).
        $this->assertDatabaseHas('activities', [
            'id' => $activity->id,
            'created_at' => '2026-05-18 08:20:27',
            'updated_at' => '2026-05-18 08:20:27',
        ]);

        // …and the patient-card read converts UTC→PKT back to the original 1:20 PM.
        // (Creating the patient also auto-logs a "created" activity at now(), so
        // select our seeded test_event explicitly rather than the latest row.)
        $logs = app(PatientService::class)->getActivityHistory($patient->id);
        $row = collect($logs)->firstWhere('type', 'test_event');
        $this->assertNotNull($row, 'The seeded test_event activity must be in the history.');

        $this->assertSame(
            'May 18, 2026 1:20 PM',
            $row['time_formatted'],
            'Stored UTC must render back to the original PKT wall-clock — 8:20 AM '
            .'(no conversion) or 6:20 PM (double +5h) are the bugs.',
        );
        $this->assertSame('05-18-2026 13:20', $row['time_short']);
    }
}
