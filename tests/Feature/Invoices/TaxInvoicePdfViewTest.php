<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use App\Models\Locations;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * The "Download Invoices ZIP" action on the Tax Calculation Report renders one
 * PDF per generated invoice from the `admin.reports.taxcalculationreport.invoice-pdf`
 * Blade view (InvoiceGenerationController::generateInvoicePdf). That view was
 * left behind when the controller was migrated from crm2, so the ZIP failed
 * with "View [admin.reports.taxcalculationreport.invoice-pdf] not found."
 *
 * Pins that the view exists and renders with exactly the variables the
 * controller passes — red again if the view goes missing.
 */
class TaxInvoicePdfViewTest extends TestCase
{
    private const VIEW = 'admin.reports.taxcalculationreport.invoice-pdf';

    public function test_invoice_pdf_view_exists(): void
    {
        $this->assertTrue(
            View::exists(self::VIEW),
            'The per-invoice PDF view must exist or the Download Invoices ZIP action 500s.',
        );
    }

    public function test_invoice_pdf_view_renders_with_the_controller_variables(): void
    {
        $location = new Locations([
            'address' => '1 Test Street',
            'fdo_phone' => '021-111-000',
            'ntn' => 'NTN-123',
            'stn' => 'STN-456',
        ]);

        $html = View::make(self::VIEW, [
            'invoice' => [
                'invoice_number' => '12345-0-06-1',
                'invoice_date' => '2026-05-15',
                'patient_id' => 12345,
                'amount' => 1500,
            ],
            'location' => $location,
            'patient_name' => 'Test Patient',
            'service_label' => 'Aesthetic Procedure',
            'service_name' => 'Aesthetic Procedure',
            'service_price' => 5000,
            'tax_percent' => 13,
            'tax_amount' => 650,
            'total_amount' => 5650,
        ])->render();

        $this->assertStringContainsString('12345-0-06-1', $html);   // invoice number
        $this->assertStringContainsString('Aesthetic Procedure', $html); // service line
        $this->assertStringContainsString('NTN-123', $html);        // location info wired in
        $this->assertStringContainsString('May 15, 2026', $html);   // formatted invoice date
    }
}
