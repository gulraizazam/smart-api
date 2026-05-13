<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Activity;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * `Activity::$fillable` regression pin.
 *
 * The user reported the dashboard activity feed showing
 * "RCVD Rs. 0 received from <patient> · Plan #<id>" for plan payments
 * that were actually for non-zero amounts. Root cause: `amount` (along
 * with `location`, `invoice_id`, and the timestamp columns) was missing
 * from `$fillable`, so every `Activity::create([..., 'amount' => X])`
 * call silently dropped the value via Laravel's mass-assignment guard.
 * The renderer then formatted NULL → 0.
 *
 * Pinned at the model layer because every writer in the codebase
 * (`ActivityLogger`, `PlanService::logPaymentActivity`, …) funnels
 * through `Activity::create` — losing a column at the fillable layer
 * is silent and reaches the dashboard with no other warning.
 */
class ActivityFillableTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_amount_persists_through_mass_assignment(): void
    {
        // The exact shape ActivityLogger::logPaymentReceived uses. Without
        // 'amount' in $fillable, the cast-decimal column lands NULL.
        $row = Activity::create([
            'account_id' => 1,
            'action' => 'Payment Received',
            'activity_type' => 'payment_received',
            'description' => 'Test row',
            'patient' => 'Test Patient',
            'patient_id' => 1,
            'plan_id' => null,
            'package_id' => null,
            'amount' => 40487.00,
            'location' => 'Test-Centre',
            'centre_id' => 1,
            'created_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $persisted = DB::table('activities')->where('id', $row->id)->first();

        $this->assertNotNull($persisted, 'Activity row must exist after create()');
        $this->assertNotNull(
            $persisted->amount,
            'amount must NOT be NULL — if this fails, the fillable list is missing the "amount" column again.',
        );
        $this->assertSame(40487.0, (float) $persisted->amount);
        $this->assertSame('Test-Centre', $persisted->location);
    }
}
