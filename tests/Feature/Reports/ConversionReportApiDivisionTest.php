<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Regression: POST /api/reports/conversion → 500 {message:"Division by zero"}.
 *
 * `FinanceConversionReport::LoadConversionReport` guarded the
 * average-client-value division with `! empty($conversionsByPatient)`.
 * That value is an Illuminate Collection, and `empty()` on an object is
 * ALWAYS false — so a period with no converted patients fell into the
 * branch and divided sum() by count()=0 (DivisionByZeroError). Pins that
 * an empty period now returns 200 with avg_client_value = 0.
 */
class ConversionReportApiDivisionTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        // Super-Admin bypasses the conversion_report_manage gate (Gate::before)
        // — same convention as ConversionReportTest / ReportEndpointTest. The
        // bug under test lives in LoadConversionReport, independent of caller.
        $this->actingAsAdmin();
    }

    public function test_empty_period_does_not_divide_by_zero(): void
    {
        // A valid range (as the SPA always sends) so parseDateRange yields
        // real dates — the empty-result division-by-zero path the user hit.
        $response = $this->postJson('/api/reports/conversion', [
            'date_range' => '2026-05-01 - 2026-05-18',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        // The crux: no converted patients ⇒ average is 0, not a 500.
        $response->assertJsonPath('data.stats.avg_client_value', 0);
        $response->assertJsonPath('data.stats.average_client_conversion', 0);
        $response->assertJsonPath('data.stats.arrival_to_conversion_ratio', 0);
    }
}
