<?php

declare(strict_types=1);

namespace Tests\Feature\Resources;

use App\Http\Resources\Consultancy\ConsultancyResource;
use App\Http\Resources\Treatment\TreatmentDatatableResource;
use App\Models\Appointments;
use App\Models\InvoiceStatuses;
use App\Models\Invoices;
use Illuminate\Http\Request;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the lock-state signals exposed on the Consultancy and Treatment
 * datatable resources so the SPA's status-dialog Option A pattern stays
 * in lockstep with the server-side guards.
 *
 *   - has_paid_invoice mirrors AppointmentService::updateAppointmentStatus
 *     paid-invoice lock at app/Services/Appointment/AppointmentService.php
 *   - has_children mirrors AppointmentHelper::isChildExists at
 *     app/Helpers/AppointmentHelper.php
 *
 * If a refactor ever drops these fields from the resources the SPA
 * dialog quietly stops locking — and we're back to the user clicking
 * through to a 422 generic toast. Pin the contracts here.
 */
class StatusLockSignalsTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_consultancy_resource_has_paid_invoice_true_when_paid_invoice_exists(): void
    {
        $admin = $this->actingAsAdmin();
        $appointment = Appointments::factory()->create();
        $paidId = (int) InvoiceStatuses::where('slug', '=', 'paid')->value('id');
        $this->assertGreaterThan(0, $paidId, 'Test fixtures must seed an InvoiceStatus with slug=paid.');

        Invoices::factory()->create([
            'appointment_id' => $appointment->id,
            'invoice_status_id' => $paidId,
            'account_id' => $admin->account_id,
        ]);

        $payload = (new ConsultancyResource($appointment))->toArray(new Request());

        $this->assertTrue(
            $payload['has_paid_invoice'],
            'has_paid_invoice must be true when an invoice with slug=paid exists for the appointment.',
        );
    }

    public function test_consultancy_resource_has_paid_invoice_false_when_no_invoice(): void
    {
        $this->actingAsAdmin();
        $appointment = Appointments::factory()->create();

        $payload = (new ConsultancyResource($appointment))->toArray(new Request());

        $this->assertFalse($payload['has_paid_invoice']);
    }

    public function test_consultancy_resource_has_paid_invoice_false_when_invoice_is_cancelled(): void
    {
        $admin = $this->actingAsAdmin();
        $appointment = Appointments::factory()->create();
        // Cancelled invoice → invoice_status_id != paid → has_paid_invoice false.
        Invoices::factory()->cancelled()->create([
            'appointment_id' => $appointment->id,
            'account_id' => $admin->account_id,
        ]);

        $payload = (new ConsultancyResource($appointment))->toArray(new Request());

        $this->assertFalse($payload['has_paid_invoice']);
    }

    public function test_consultancy_resource_has_children_true_when_non_cancelled_invoice_exists(): void
    {
        $admin = $this->actingAsAdmin();
        $appointment = Appointments::factory()->create();
        // Default factory invoice_status_id = 1 → not the cancelled id (4)
        // per AppointmentHelper::isChildExists's `!= 4` filter.
        Invoices::factory()->create([
            'appointment_id' => $appointment->id,
            'account_id' => $admin->account_id,
        ]);

        $payload = (new ConsultancyResource($appointment))->toArray(new Request());

        $this->assertTrue($payload['has_children']);
    }

    public function test_consultancy_resource_has_children_false_when_no_child_records(): void
    {
        $this->actingAsAdmin();
        $appointment = Appointments::factory()->create();

        $payload = (new ConsultancyResource($appointment))->toArray(new Request());

        $this->assertFalse($payload['has_children']);
    }

    public function test_treatment_datatable_resource_exposes_has_paid_invoice_and_has_children(): void
    {
        // The datatable resource expects an Appointments row with the
        // aliased `app_id` column from TreatmentService::getAppointments
        // and the `invoice` relation eager-loaded. Stub both manually so
        // the contract test doesn't depend on the full service pipeline.
        $admin = $this->actingAsAdmin();
        $appointment = Appointments::factory()->create();

        $row = Appointments::with('invoice')->findOrFail($appointment->id);
        $row->app_id = $row->id;

        $resource = (new TreatmentDatatableResource($row))
            ->setLookupData(true, [], [], [], null, null);
        $payload = $resource->toArray(new Request());

        $this->assertArrayHasKey('has_paid_invoice', $payload);
        $this->assertArrayHasKey('has_children', $payload);
        $this->assertFalse($payload['has_paid_invoice']);
        $this->assertFalse($payload['has_children']);
    }

    public function test_treatment_datatable_resource_has_paid_invoice_true_when_invoice_paid(): void
    {
        $admin = $this->actingAsAdmin();
        $appointment = Appointments::factory()->create();
        $paidId = (int) InvoiceStatuses::where('slug', '=', 'paid')->value('id');
        Invoices::factory()->create([
            'appointment_id' => $appointment->id,
            'invoice_status_id' => $paidId,
            'account_id' => $admin->account_id,
        ]);

        // Re-fetch with the eager relation.
        $row = Appointments::with('invoice')->findOrFail($appointment->id);
        $row->app_id = $row->id;

        $resource = (new TreatmentDatatableResource($row))
            ->setLookupData(true, [], [], [], null, null);
        $payload = $resource->toArray(new Request());

        $this->assertTrue($payload['has_paid_invoice']);
        $this->assertTrue($payload['has_children']);
    }

    public function test_consultancy_resource_appointment_status_carries_flag_fields(): void
    {
        // Pin the row-level flag emission. Pre-fix the SPA had to
        // cross-reference /api/appointment_statuses/dropdown to read
        // is_arrived/is_converted/is_unscheduled — that endpoint is
        // permission-gated (`appointment_statuses_manage`) and 403s
        // for ordinary operators, silently breaking the dialog lock.
        // Putting the flags on every appointment_status object the SPA
        // already receives bypasses that gate.
        $this->actingAsAdmin();
        $arrivedStatus = \App\Models\AppointmentStatuses::create([
            'name' => 'Arrived',
            'slug' => 'arrived-test',
            'account_id' => 1,
            'is_arrived' => 1,
            'is_converted' => 0,
            'is_unscheduled' => 0,
            'is_cancelled' => 0,
        ]);
        $appointment = Appointments::factory()->create([
            'appointment_status_id' => $arrivedStatus->id,
        ]);

        $payload = (new ConsultancyResource(
            $appointment->load('appointment_status'),
        ))->toArray(new Request());

        $this->assertIsArray($payload['appointment_status']);
        $this->assertSame($arrivedStatus->id, $payload['appointment_status']['id']);
        $this->assertTrue($payload['appointment_status']['is_arrived']);
        $this->assertFalse($payload['appointment_status']['is_converted']);
        $this->assertFalse($payload['appointment_status']['is_unscheduled']);
        $this->assertFalse($payload['appointment_status']['is_cancelled']);
    }

    public function test_treatment_resource_appointment_status_carries_flag_fields(): void
    {
        $this->actingAsAdmin();
        $convertedStatus = \App\Models\AppointmentStatuses::create([
            'name' => 'Converted',
            'slug' => 'converted-test',
            'account_id' => 1,
            'is_arrived' => 0,
            'is_converted' => 1,
            'is_unscheduled' => 0,
            'is_cancelled' => 0,
        ]);
        $appointment = Appointments::factory()->create([
            'appointment_status_id' => $convertedStatus->id,
        ]);

        $payload = (new \App\Http\Resources\Treatment\TreatmentResource(
            $appointment->load('appointment_status'),
        ))->toArray(new Request());

        $this->assertIsArray($payload['appointment_status']);
        $this->assertTrue($payload['appointment_status']['is_converted']);
        $this->assertFalse($payload['appointment_status']['is_arrived']);
    }

    /**
     * Pins `has_feedback` on the SPA treatment resource. Drives the
     * yellow star icon on the treatments listing so operators can see
     * which arrived rows already have feedback recorded. The list query
     * (TreatmentService::getTreatmentList) eager-loads
     * `withCount('feedback')`; the resource reads `feedback_count`.
     */
    public function test_treatment_resource_exposes_has_feedback(): void
    {
        $this->actingAsAdmin();
        $appointment = Appointments::factory()->create();

        $rowWithout = Appointments::withCount('feedback')->findOrFail($appointment->id);
        $payload = (new \App\Http\Resources\Treatment\TreatmentResource($rowWithout))
            ->toArray(new Request());

        $this->assertArrayHasKey('has_feedback', $payload);
        $this->assertFalse($payload['has_feedback']);

        \App\Models\Feedback::create([
            'appointment_id' => $appointment->id,
            'rating'         => 5,
            'comment'        => 'great',
        ]);

        $rowWith = Appointments::withCount('feedback')->findOrFail($appointment->id);
        $payload = (new \App\Http\Resources\Treatment\TreatmentResource($rowWith))
            ->toArray(new Request());

        $this->assertTrue($payload['has_feedback']);
    }
}
