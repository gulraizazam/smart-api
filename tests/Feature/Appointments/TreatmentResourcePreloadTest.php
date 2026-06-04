<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use App\Http\Resources\Treatment\TreatmentResource;
use App\Models\Appointments;
use App\Models\Invoices;
use App\Models\Measurement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * TreatmentResource::preload() — same batch flag resolution the
 * consultancy resource uses (via the shared ResolvesAppointmentRowFlags
 * trait), pinned independently against the treatment resource so a
 * future divergence in either resource is caught.
 *
 * Pins: (1) the batched `has_paid_invoice` / `has_children` flags equal
 * the per-row fallback, and (2) serialising a preloaded page fires zero
 * per-row queries.
 */
class TreatmentResourcePreloadTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const EAGER = [
        'appointment_type', 'appointment_status', 'service',
        'location.city', 'doctor', 'patient', 'lead', 'user',
        'user_converted_by', 'user_updated_by',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    private function seedMeasurement(Appointments $appointment): void
    {
        $formId = DB::table('custom_forms')->insertGetId([
            'name' => 'Before/After Form',
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $feedbackId = (int) DB::table('custom_form_feedbacks')->insertGetId([
            'form_name' => 'Before/After Submission',
            'custom_form_id' => $formId,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Measurement::create([
            'user_id' => auth()->id(),
            'patient_id' => $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'custom_form_feedback_id' => $feedbackId,
            'service_id' => $appointment->service_id,
            'priority' => 'Low priority',
            'type' => 'Before Appointment',
            'date' => now()->toDateString(),
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    private function serialize(iterable $collection): array
    {
        $request = Request::create('/api/treatment');
        $out = [];
        foreach ($collection as $model) {
            $out[(int) $model->id] = (new TreatmentResource($model))->toArray($request);
        }

        return $out;
    }

    public function test_preload_resolves_both_flags_correctly(): void
    {
        $paid = Appointments::factory()->create(['account_id' => 1, 'appointment_type_id' => 1]);
        Invoices::factory()->create([
            'appointment_id' => $paid->id,
            'patient_id' => $paid->patient_id,
            'location_id' => $paid->location_id,
            'invoice_status_id' => 3, // 'paid'
        ]);

        $childOnly = Appointments::factory()->create(['account_id' => 1, 'appointment_type_id' => 1]);
        $this->seedMeasurement($childOnly);

        $clean = Appointments::factory()->create(['account_id' => 1, 'appointment_type_id' => 1]);

        $collection = Appointments::with(self::EAGER)
            ->whereIn('id', [$paid->id, $childOnly->id, $clean->id])
            ->get();

        TreatmentResource::preload($collection);
        $rows = $this->serialize($collection);

        $this->assertTrue($rows[$paid->id]['has_paid_invoice']);
        $this->assertTrue($rows[$paid->id]['has_children']);

        $this->assertFalse($rows[$childOnly->id]['has_paid_invoice']);
        $this->assertTrue($rows[$childOnly->id]['has_children']);

        $this->assertFalse($rows[$clean->id]['has_paid_invoice']);
        $this->assertFalse($rows[$clean->id]['has_children']);
    }

    public function test_preloaded_flags_match_the_per_row_fallback(): void
    {
        $paid = Appointments::factory()->create(['account_id' => 1, 'appointment_type_id' => 1]);
        Invoices::factory()->create([
            'appointment_id' => $paid->id,
            'patient_id' => $paid->patient_id,
            'location_id' => $paid->location_id,
            'invoice_status_id' => 3,
        ]);
        $clean = Appointments::factory()->create(['account_id' => 1, 'appointment_type_id' => 1]);

        $fallback = $this->serialize(
            Appointments::with(self::EAGER)->whereIn('id', [$paid->id, $clean->id])->get(),
        );

        $batchCollection = Appointments::with(self::EAGER)
            ->whereIn('id', [$paid->id, $clean->id])->get();
        TreatmentResource::preload($batchCollection);
        $batched = $this->serialize($batchCollection);

        foreach ([$paid->id, $clean->id] as $id) {
            $this->assertSame($fallback[$id]['has_paid_invoice'], $batched[$id]['has_paid_invoice']);
            $this->assertSame($fallback[$id]['has_children'], $batched[$id]['has_children']);
        }
    }

    public function test_serialisation_after_preload_fires_no_per_row_queries(): void
    {
        $appointments = collect(range(1, 4))->map(function () {
            $a = Appointments::factory()->create(['account_id' => 1, 'appointment_type_id' => 1]);
            $this->seedMeasurement($a);

            return $a;
        });
        $ids = $appointments->pluck('id')->all();

        \Illuminate\Support\Facades\Gate::allows('treatments.list.view_contact');

        $collection = Appointments::with(self::EAGER)->whereIn('id', $ids)->get();
        TreatmentResource::preload($collection);

        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        $this->serialize($collection);
        $afterPreload = count(DB::connection()->getQueryLog());

        $this->assertSame(
            0,
            $afterPreload,
            'Serialising preloaded treatments must not fire any per-row queries.',
        );
    }
}
