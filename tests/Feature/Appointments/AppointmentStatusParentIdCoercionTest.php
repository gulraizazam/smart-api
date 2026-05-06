<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use App\Models\AppointmentStatuses;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * `appointment_statuses.parent_id` carries a self-referencing FK
 * (`fk_appointment_statuses_parent_id`). 0 is not a valid id, so the
 * model's createRecord/updateRecord MUST coerce empty / missing /
 * literal-0 to NULL — pre-fix it stored 0 and every top-level status
 * insert failed with SQLSTATE[23000] integrity-constraint-violation.
 *
 * The SPA already sends `parent_id: null` for "no parent", but the
 * coercion has to also handle '' (legacy callers) and missing-key
 * (partial form payloads) safely.
 */
class AppointmentStatusParentIdCoercionTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_create_with_null_parent_id_persists_null_not_zero(): void
    {
        $accountId = (int) Auth::user()->account_id;

        $request = new Request([
            'name' => 'Top-Level Null',
            'parent_id' => null,
            'is_default' => 0, 'is_arrived' => 0, 'is_converted' => 0,
            'is_cancelled' => 0, 'is_unscheduled' => 0,
            'is_comment' => 0, 'allow_message' => 0, 'active' => 1,
        ]);

        $record = AppointmentStatuses::createRecord($request, $accountId);

        $this->assertNotNull($record);
        $this->assertNull(
            $record->fresh()->parent_id,
            'parent_id must be NULL — storing 0 violates fk_appointment_statuses_parent_id.',
        );
    }

    public function test_create_with_empty_string_parent_id_persists_null(): void
    {
        $accountId = (int) Auth::user()->account_id;

        $request = new Request([
            'name' => 'Top-Level Empty',
            'parent_id' => '',
            'is_default' => 0, 'is_arrived' => 0, 'is_converted' => 0,
            'is_cancelled' => 0, 'is_unscheduled' => 0,
            'is_comment' => 0, 'allow_message' => 0, 'active' => 1,
        ]);

        $record = AppointmentStatuses::createRecord($request, $accountId);

        $this->assertNotNull($record);
        $this->assertNull($record->fresh()->parent_id);
    }

    public function test_create_with_real_parent_id_persists_it(): void
    {
        $accountId = (int) Auth::user()->account_id;

        $parent = AppointmentStatuses::createRecord(
            new Request([
                'name' => 'Booked',
                'parent_id' => null,
                'is_default' => 0, 'is_arrived' => 0, 'is_converted' => 0,
                'is_cancelled' => 0, 'is_unscheduled' => 0,
                'is_comment' => 0, 'allow_message' => 0, 'active' => 1,
            ]),
            $accountId,
        );

        $child = AppointmentStatuses::createRecord(
            new Request([
                'name' => 'Booked — Confirmed',
                'parent_id' => $parent->id,
                'is_default' => 0, 'is_arrived' => 0, 'is_converted' => 0,
                'is_cancelled' => 0, 'is_unscheduled' => 0,
                'is_comment' => 0, 'allow_message' => 0, 'active' => 1,
            ]),
            $accountId,
        );

        $this->assertSame((int) $parent->id, (int) $child->fresh()->parent_id);
    }

    public function test_update_to_no_parent_clears_to_null(): void
    {
        $accountId = (int) Auth::user()->account_id;

        $parent = AppointmentStatuses::createRecord(
            new Request([
                'name' => 'Booked',
                'parent_id' => null,
                'is_default' => 0, 'is_arrived' => 0, 'is_converted' => 0,
                'is_cancelled' => 0, 'is_unscheduled' => 0,
                'is_comment' => 0, 'allow_message' => 0, 'active' => 1,
            ]),
            $accountId,
        );

        $child = AppointmentStatuses::createRecord(
            new Request([
                'name' => 'Booked — Follow Up',
                'parent_id' => $parent->id,
                'is_default' => 0, 'is_arrived' => 0, 'is_converted' => 0,
                'is_cancelled' => 0, 'is_unscheduled' => 0,
                'is_comment' => 0, 'allow_message' => 0, 'active' => 1,
            ]),
            $accountId,
        );

        $updated = AppointmentStatuses::updateRecord(
            (int) $child->id,
            new Request([
                'name' => $child->name,
                'parent_id' => '',
                'is_default' => 0, 'is_arrived' => 0, 'is_converted' => 0,
                'is_cancelled' => 0, 'is_unscheduled' => 0,
                'is_comment' => 0, 'allow_message' => 0, 'active' => 1,
            ]),
            $accountId,
        );

        $this->assertNotNull($updated);
        $this->assertNull($updated->fresh()->parent_id);
    }
}
