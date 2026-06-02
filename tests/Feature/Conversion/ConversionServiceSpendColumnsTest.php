<?php

declare(strict_types=1);

namespace Tests\Feature\Conversion;

use App\Helpers\GeneralFunctions;
use App\Models\PackageAdvances;
use App\Models\Patients;
use App\Services\Conversion\ConversionService;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the column projection used by
 * ConversionService::getValidatedConversions().
 *
 * The branch leaderboard used to hydrate the FULL PackageAdvances model for
 * every advance in the window (~221MB on a busy month, 500ing at the 128MB
 * limit). It now projects only ConversionService::SPEND_ADVANCE_COLUMNS and
 * eager-loads the patient. But conversion spend runs through
 * GeneralFunctions::genericfunctionforstaffwiserevenue(), which reads the
 * cash-flow flags AND dereferences $advance->user (via patient_id) — so if
 * the projection ever drops one of those columns the revenue/refund numbers
 * go silently wrong instead of throwing.
 *
 * These tests re-fetch advances through the EXACT production projection and
 * push them back through the helper, so an over-trimmed select fails loudly.
 */
class ConversionServiceSpendColumnsTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_projection_keeps_every_column_the_spend_helper_reads(): void
    {
        foreach ([
            'patient_id', 'cash_flow', 'cash_amount',
            'is_refund', 'is_tax', 'is_cancel', 'is_adjustment',
            'created_at', 'package_id',
        ] as $column) {
            $this->assertContains(
                $column,
                ConversionService::SPEND_ADVANCE_COLUMNS,
                "getValidatedConversions() select must keep `{$column}` — genericfunctionforstaffwiserevenue() reads it.",
            );
        }
    }

    public function test_helper_computes_revenue_refund_and_exclusions_through_the_projection(): void
    {
        $patient = Patients::factory()->create([
            'name' => 'Spend Test Patient',
            'phone' => '03001234567',
        ]);

        // A clean inbound payment, a refund, and a tax row that must be excluded.
        $inflow = PackageAdvances::factory()->create([
            'patient_id' => $patient->id,
            'cash_flow' => 'in',
            'cash_amount' => 5000,
            'is_adjustment' => 0, 'is_tax' => 0, 'is_cancel' => 0, 'is_refund' => 0,
        ]);
        $refund = PackageAdvances::factory()->out()->create([
            'patient_id' => $patient->id,
            'cash_amount' => 2000,
            'is_adjustment' => 0, 'is_tax' => 0, 'is_cancel' => 0, 'is_refund' => 1,
        ]);
        $tax = PackageAdvances::factory()->create([
            'patient_id' => $patient->id,
            'cash_flow' => 'in',
            'cash_amount' => 1000,
            'is_adjustment' => 0, 'is_tax' => 1, 'is_cancel' => 0, 'is_refund' => 0,
        ]);

        // Re-fetch through the EXACT production projection (incl. eager user).
        $rows = PackageAdvances::query()
            ->select(ConversionService::SPEND_ADVANCE_COLUMNS)
            ->with('user')
            ->whereIn('id', [$inflow->id, $refund->id, $tax->id])
            ->get()
            ->keyBy('id');

        // Inbound revenue: a successful return already proves the user relation
        // resolved (the helper dereferences $advance->user->name to build the
        // row) — i.e. patient_id survived the projection.
        $in = GeneralFunctions::genericfunctionforstaffwiserevenue($rows[$inflow->id]);
        $this->assertNotNull($in);
        $this->assertSame($patient->name, $in['patient']);
        $this->assertEqualsWithDelta(5000, (float) $in['revenue'], 0.01);
        $this->assertSame('', $in['refund_out']);

        // Refund out: proves is_refund + cash_flow survive the projection.
        $out = GeneralFunctions::genericfunctionforstaffwiserevenue($rows[$refund->id]);
        $this->assertNotNull($out);
        $this->assertSame('', $out['revenue']);
        $this->assertEqualsWithDelta(2000, (float) $out['refund_out'], 0.01);

        // Tax row excluded: proves is_tax survives. A dropped is_tax would read
        // null and break this branch (and drop real revenue elsewhere).
        $taxResult = GeneralFunctions::genericfunctionforstaffwiserevenue($rows[$tax->id]);
        $this->assertNull($taxResult);
    }
}
