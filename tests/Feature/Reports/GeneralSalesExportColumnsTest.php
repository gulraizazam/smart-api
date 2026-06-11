<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Http\Controllers\Api\Reports\GeneralSalesReportController;
use App\Services\Reports\Enums\ReportType;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Pins the Sales Detail export column set: Patient ID sits right after the
 * Patient name (so an operator can cross-reference the row in the patient
 * record), Phone is still present, and the numeric totals stay aligned under
 * "Cash in" despite the extra leading column. The on-screen table mirrors
 * this in reports-general-sales.tsx (DetailTable).
 */
class GeneralSalesExportColumnsTest extends TestCase
{
    public function test_sales_detail_export_includes_patient_id_beside_name(): void
    {
        $controller = app(GeneralSalesReportController::class);
        $flatten = new ReflectionMethod($controller, 'flattenForExport');
        $flatten->setAccessible(true);

        $result = [
            'report_data' => [
                [
                    'name' => 'Lahore',
                    'revenue_data' => [
                        [
                            'created_at' => '2026-06-01 10:00:00',
                            'patient' => 'Ayesha Khan',
                            'patient_id' => 4567,
                            'gender' => 'Female',
                            'phone' => '03001234567',
                            'transtype' => 'Plan',
                            'payment_mode' => 'Cash',
                            'revenue_cash_in' => 1000,
                            'revenue_card_in' => 0,
                            'revenue_bank_in' => 0,
                            'refund_out' => 0,
                            'Balance' => 0,
                        ],
                    ],
                ],
            ],
            'total_revenue_cash_in' => 1000,
            'total_revenue_card_in' => 0,
            'total_revenue_bank_in' => 0,
            'total_refund' => 0,
        ];

        $flat = $flatten->invoke($controller, ReportType::GeneralRevenueDetail, $result);

        // Patient ID heading present and positioned directly after Patient.
        $patientIdx = array_search('Patient', $flat['headings'], true);
        $this->assertNotFalse($patientIdx);
        $this->assertSame('Patient ID', $flat['headings'][$patientIdx + 1]);

        // Phone is still part of the report (id + name + phone together).
        $this->assertContains('Phone', $flat['headings']);

        // The data row carries the id and phone in their heading's column.
        $row = $flat['rows'][0];
        $this->assertSame(4567, $row[$patientIdx + 1]);
        $phoneIdx = array_search('Phone', $flat['headings'], true);
        $this->assertSame('03001234567', $row[$phoneIdx]);

        // Totals must line up under "Cash in" — the extra Patient ID column
        // means the label span grew from 7 to 8. If that offset is wrong the
        // totals print under the wrong heading.
        $cashIdx = array_search('Cash in', $flat['headings'], true);
        $this->assertSame(1000.0, $flat['totalsRow'][$cashIdx]);
    }
}
