<?php

declare(strict_types=1);

namespace Tests\Feature\Appointments;

use App\Http\Resources\Consultancy\ConsultancyResource;
use App\Models\Appointments;
use App\Models\Invoices;
use App\Models\Measurement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * ConsultancyResource::preload() — batch resolution of the two per-row
 * flags the list endpoint exposes (`has_paid_invoice`, `has_children`).
 *
 * Before preload existed, serialising a page of N consultancies fired
 * five EXISTS queries per row (1 paid-invoice + the four child-table
 * lookups in AppointmentHelper::isChildExists) — an N+1 that made the
 * consultations list page slow. preload() collapses that to a fixed
 * handful of `whereIn` queries for the whole page and stashes each
 * result on its model; toArray() then reads the attribute.
 *
 * These tests pin two things that must both hold:
 *   1. Correctness — the batched flags equal the per-row fallback.
 *   2. No N+1 — once preloaded, serialisation fires zero further
 *      queries regardless of row count.
 */
class ConsultancyResourcePreloadTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    /** Relations the list endpoint eager-loads — replicated so the
     *  query-count assertions only measure the flag resolution, not
     *  incidental relation lazy-loading. */
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

    /** @return array<int,array<string,mixed>> id => flag row */
    private function serialize(iterable $collection): array
    {
        $request = Request::create('/api/consultancy');
        $out = [];
        foreach ($collection as $model) {
            $out[(int) $model->id] = (new ConsultancyResource($model))->toArray($request);
        }

        return $out;
    }

    public function test_preload_resolves_both_flags_correctly(): void
    {
        // Paid invoice → both has_paid_invoice and has_children true
        // (a non-cancelled invoice is a child).
        $paid = Appointments::factory()->create(['account_id' => 1]);
        Invoices::factory()->create([
            'appointment_id' => $paid->id,
            'patient_id' => $paid->patient_id,
            'location_id' => $paid->location_id,
            'invoice_status_id' => 3, // 'paid'
        ]);

        // Measurement only → has_children true, has_paid_invoice false.
        $childOnly = Appointments::factory()->create(['account_id' => 1]);
        $this->seedMeasurement($childOnly);

        // Nothing attached → both false.
        $clean = Appointments::factory()->create(['account_id' => 1]);

        $collection = Appointments::with(self::EAGER)
            ->whereIn('id', [$paid->id, $childOnly->id, $clean->id])
            ->get();

        ConsultancyResource::preload($collection);
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
        $paid = Appointments::factory()->create(['account_id' => 1]);
        Invoices::factory()->create([
            'appointment_id' => $paid->id,
            'patient_id' => $paid->patient_id,
            'location_id' => $paid->location_id,
            'invoice_status_id' => 3,
        ]);
        $clean = Appointments::factory()->create(['account_id' => 1]);

        // Fallback path (no preload) — fresh models so no precomputed
        // attribute is present.
        $fallback = $this->serialize(
            Appointments::with(self::EAGER)->whereIn('id', [$paid->id, $clean->id])->get(),
        );

        // Batched path — fresh models, preloaded.
        $batchCollection = Appointments::with(self::EAGER)
            ->whereIn('id', [$paid->id, $clean->id])->get();
        ConsultancyResource::preload($batchCollection);
        $batched = $this->serialize($batchCollection);

        foreach ([$paid->id, $clean->id] as $id) {
            $this->assertSame(
                $fallback[$id]['has_paid_invoice'],
                $batched[$id]['has_paid_invoice'],
                "has_paid_invoice diverged for #{$id}",
            );
            $this->assertSame(
                $fallback[$id]['has_children'],
                $batched[$id]['has_children'],
                "has_children diverged for #{$id}",
            );
        }
    }

    public function test_serialisation_after_preload_fires_no_per_row_queries(): void
    {
        // Build a page of rows that each have children, so the fallback
        // path would fire the maximum number of EXISTS queries.
        $appointments = collect(range(1, 4))->map(function () {
            $a = Appointments::factory()->create(['account_id' => 1]);
            $this->seedMeasurement($a);

            return $a;
        });
        $ids = $appointments->pluck('id')->all();

        // Warm the permission cache so Gate::allows inside toArray()
        // doesn't add a query mid-measurement.
        \Illuminate\Support\Facades\Gate::allows('consultations.list.view_contact');

        $collection = Appointments::with(self::EAGER)->whereIn('id', $ids)->get();
        ConsultancyResource::preload($collection);

        DB::connection()->enableQueryLog();
        DB::connection()->flushQueryLog();
        $this->serialize($collection);
        $afterPreload = count(DB::connection()->getQueryLog());

        $this->assertSame(
            0,
            $afterPreload,
            'Serialising preloaded consultancies must not fire any per-row queries.',
        );
    }
}
