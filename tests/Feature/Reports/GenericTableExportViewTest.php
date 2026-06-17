<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Every SPA-driven report PDF export (DoctorRatings, GeneralSales, Memberships,
 * DoctorRevenue, Conversion, …) renders through the shared Blade
 * `admin.reports.exports.generic-table`. The Blade-retirement sweep (076a552f4)
 * deleted it as "dead legacy" — but it is LIVE crm3 code, so every report PDF
 * export 500-ed ("something went wrong"). These tests pin that the view exists
 * and renders for the variable sets the controllers actually pass — including a
 * report that omits the optional totalsRow / summaryBlock (DoctorRatings) and an
 * empty result set. Goes red if the view is removed again or stops guarding the
 * optional vars.
 */
class GenericTableExportViewTest extends TestCase
{
    private const VIEW = 'admin.reports.exports.generic-table';

    public function test_the_shared_export_view_exists(): void
    {
        $this->assertTrue(
            View::exists(self::VIEW),
            'The SPA report PDF export view must exist — report PDF exports 500 without it.',
        );
    }

    public function test_renders_for_a_report_without_totals_or_summary(): void
    {
        // The exact variable set DoctorRatingsDetailApiController::export passes:
        // no totalsRow, no summaryBlock. The view must guard those with `?? null`
        // or dompdf throws "Undefined variable" and the export 500s.
        $html = View::make(self::VIEW, [
            'title' => 'Doctor Ratings Report',
            'subtitle' => 'Period: 2026-06-01 to 2026-06-17',
            'headings' => ['Doctor', 'Average rating (/10)', 'Total feedbacks'],
            'rows' => [['Dr Zehra Batool', '8.50', 12]],
        ])->render();

        $this->assertStringContainsString('Doctor Ratings Report', $html);
        $this->assertStringContainsString('Dr Zehra Batool', $html);
    }

    public function test_renders_with_an_empty_result_set(): void
    {
        $html = View::make(self::VIEW, [
            'title' => 'Doctor Ratings Report',
            'subtitle' => '',
            'headings' => ['Doctor', 'Average rating (/10)', 'Total feedbacks'],
            'rows' => [],
        ])->render();

        $this->assertStringContainsString('No rows', $html);
    }
}
