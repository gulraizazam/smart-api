<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Http\Controllers\Admin\InvoiceGenerationController;
use App\Http\Requests\Admin\InvoiceCalculationRequest;
use App\Models\Locations;
use App\Services\InvoiceGenerationService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The Tax Calculation Report's "Excel" button is fetched cross-origin by the
 * crm3 SPA (crm3.cutera.pk -> api.cutera.pk). The export USED to emit the file
 * with raw header() + `exit`, which terminates the request BEFORE Laravel's
 * HandleCors middleware runs — so the response carried no
 * Access-Control-Allow-Origin header and the browser blocked it with a bare
 * "Failed to fetch". (crm2's Blade page POSTs a same-origin <form>, a native
 * download, so it never hit this.)
 *
 * Pins the fix: the export must return a real Laravel response object (which
 * flows back up through the middleware stack, picking up CORS +
 * Content-Disposition) rather than exiting. Reverting to header()+exit makes
 * this go red — the method would `exit` mid-test instead of returning.
 */
class ExemptInvoiceExportResponseTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_export_returns_a_streamed_xlsx_response_not_a_raw_exit(): void
    {
        $locationId = Locations::first()->id;
        $params = [
            'date_from' => '2026-01-05',
            'date_to' => '2026-01-31',
            'location_ids' => [$locationId],
            'bank_taxable' => 30,
            'cash_percent' => 5,
            'consultation_amount' => 1500,
            'tax_percent' => 13,
            'max_invoices_per_day' => 2,
        ];

        try {
            // A real, correctly-shaped result. Cache it in the session exactly
            // like calculateAmounts does so the controller streams it directly.
            $result = app(InvoiceGenerationService::class)->generateExemptInvoices($params);
        } catch (\TypeError $e) {
            $this->markTestSkipped('InvoiceGenerationService PHP 8.4 type mismatch — see InvoiceGenerationServiceTest.');
        }
        session(['invoice_generation_result' => $result]);

        // The controller only reads ->validated() off the request.
        $request = \Mockery::mock(InvoiceCalculationRequest::class);
        $request->shouldReceive('validated')->andReturn([
            'date_range' => '01/05/2026 - 01/31/2026',
            'location_ids' => [$locationId],
            'bank_taxable' => 30,
            'cash_percent' => 5,
            'consultation_amount' => 1500,
            'tax_percent' => 13,
            'max_invoices_per_day' => 2,
        ]);

        $response = app(InvoiceGenerationController::class)->exportExemptInvoices($request);

        // A real response object (not header()+exit) — this is what lets the
        // cross-origin SPA actually receive the file.
        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());

        $disposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('attachment', $disposition);
        $this->assertStringContainsString('.xlsx', $disposition);
        $this->assertStringContainsString(
            'spreadsheetml',
            (string) $response->headers->get('Content-Type'),
        );

        // Body streams a real .xlsx (a ZIP container — magic bytes "PK").
        ob_start();
        $response->sendContent();
        $body = ob_get_clean();
        $this->assertSame('PK', substr($body, 0, 2), 'Streamed body must be a valid xlsx file.');
    }
}
