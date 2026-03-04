<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Locations;
use App\Models\User;
use App\Services\InvoiceGenerationService;
use Barryvdh\DomPDF\Facade as PDF;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use ZipStream\ZipStream;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class InvoiceGenerationController extends Controller
{
    protected $invoiceService;

    public function __construct(InvoiceGenerationService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    /**
     * Calculate amounts and generate exempt invoices
     * Returns JSON with calculations and invoice data
     */
    public function calculateAmounts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_range' => 'required|string',
            'location_ids' => 'required|array',
            'location_ids.*' => 'integer',
            'bank_taxable' => 'required|numeric|min:0|max:100',
            'cash_percent' => 'required|numeric|min:0|max:100',
            'consultation_amount' => 'required|numeric|in:1500,2000',
            'tax_percent' => 'nullable|numeric|min:0|max:100',
            'max_invoices_per_day' => 'nullable|integer|min:1|max:10',
        ]);

        // Parse date range
        $dates = $this->parseDateRange($validated['date_range']);

        $params = [
            'date_from' => $dates['from'],
            'date_to' => $dates['to'],
            'location_ids' => $validated['location_ids'],
            'bank_taxable' => $validated['bank_taxable'],
            'cash_percent' => $validated['cash_percent'],
            'consultation_amount' => $validated['consultation_amount'],
            'tax_percent' => $validated['tax_percent'] ?? 13,
            'max_invoices_per_day' => (int) ($validated['max_invoices_per_day'] ?? 2),
        ];

        try {
            $result = $this->invoiceService->generateExemptInvoices($params);

            // Cache result in session so Excel and ZIP downloads use the same data
            session(['invoice_generation_result' => $result]);

            return response()->json([
                'success' => true,
                'message' => 'Calculations completed successfully',
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error calculating amounts: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export exempt invoices to Excel
     */
    public function exportExemptInvoices(Request $request)
    {
        $validated = $request->validate([
            'date_range' => 'required|string',
            'location_ids' => 'required|array',
            'location_ids.*' => 'integer',
            'bank_taxable' => 'required|numeric|min:0|max:100',
            'cash_percent' => 'required|numeric|min:0|max:100',
            'consultation_amount' => 'required|numeric|in:1500,2000',
            'tax_percent' => 'nullable|numeric|min:0|max:100',
            'max_invoices_per_day' => 'nullable|integer|min:1|max:10',
        ]);

        // Parse date range
        $dates = $this->parseDateRange($validated['date_range']);

        $params = [
            'date_from' => $dates['from'],
            'date_to' => $dates['to'],
            'location_ids' => $validated['location_ids'],
            'bank_taxable' => $validated['bank_taxable'],
            'cash_percent' => $validated['cash_percent'],
            'consultation_amount' => $validated['consultation_amount'],
            'tax_percent' => $validated['tax_percent'] ?? 13,
            'max_invoices_per_day' => (int) ($validated['max_invoices_per_day'] ?? 2),
        ];

        try {
            // Use cached result from calculateAmounts to ensure consistency
            $result = session('invoice_generation_result');
            if (!$result) {
                $result = $this->invoiceService->generateExemptInvoices($params);
                session(['invoice_generation_result' => $result]);
            }

            // Create Excel file
            $spreadsheet = new Spreadsheet();
            
            // Sheet 1: Summary
            $summarySheet = $spreadsheet->getActiveSheet();
            $summarySheet->setTitle('Summary');
            $this->createSummarySheet($summarySheet, $result);

            // Sheet 2: Exempt Invoices
            $exemptSheet = $spreadsheet->createSheet();
            $exemptSheet->setTitle('Exempt Invoices');
            $this->createInvoiceSheet($exemptSheet, $result['exempt_invoices']);

            // Sheet 3: Taxable Invoices
            $taxableSheet = $spreadsheet->createSheet();
            $taxableSheet->setTitle('Taxable Invoices');
            $this->createInvoiceSheet($taxableSheet, $result['taxable_invoices']);

            // Sheet 4: Patient Distribution
            $patientSheet = $spreadsheet->createSheet();
            $patientSheet->setTitle('Patient Distribution');
            $this->createPatientSheet($patientSheet, $result['patient_distribution']);

            // Set active sheet to first
            $spreadsheet->setActiveSheetIndex(0);

            // Generate filename
            $filename = 'exempt_invoices_' . $dates['from'] . '_to_' . $dates['to'] . '.xlsx';

            // Output to browser
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating Excel: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download all invoices (exempt + taxable) as a streamed ZIP of PDFs.
     * Uses ZipStream to avoid writing anything to disk.
     * Processes invoices in chunks to handle 1000+ invoices without memory issues.
     */
    public function downloadInvoicesZip(Request $request)
    {
        $validated = $request->validate([
            'date_range' => 'required|string',
            'location_ids' => 'required|array',
            'location_ids.*' => 'integer',
            'bank_taxable' => 'required|numeric|min:0|max:100',
            'cash_percent' => 'required|numeric|min:0|max:100',
            'consultation_amount' => 'required|numeric|in:1500,2000',
            'tax_percent' => 'nullable|numeric|min:0|max:100',
            'max_invoices_per_day' => 'nullable|integer|min:1|max:10',
        ]);

        // Parse date range
        $dates = $this->parseDateRange($validated['date_range']);

        $params = [
            'date_from' => $dates['from'],
            'date_to' => $dates['to'],
            'location_ids' => $validated['location_ids'],
            'bank_taxable' => $validated['bank_taxable'],
            'cash_percent' => $validated['cash_percent'],
            'consultation_amount' => $validated['consultation_amount'],
            'tax_percent' => $validated['tax_percent'] ?? 13,
            'max_invoices_per_day' => (int) ($validated['max_invoices_per_day'] ?? 2),
        ];

        // Increase limits for large invoice sets
        set_time_limit(600);
        ini_set('memory_limit', '512M');

        try {
            // Use cached result from calculateAmounts to ensure consistency
            $result = session('invoice_generation_result');
            if (!$result) {
                $result = $this->invoiceService->generateExemptInvoices($params);
                session(['invoice_generation_result' => $result]);
            }

            // Get location info (use first selected location)
            $location = Locations::find($validated['location_ids'][0]);

            // Pre-fetch all patient names in one query
            $allPatientIds = array_unique(array_merge(
                array_column($result['exempt_invoices'], 'patient_id'),
                array_column($result['taxable_invoices'], 'patient_id')
            ));
            $patientNames = [];
            foreach (array_chunk($allPatientIds, 500) as $chunk) {
                $rows = DB::table('users')->whereIn('id', $chunk)->pluck('name', 'id');
                foreach ($rows as $id => $name) {
                    $patientNames[$id] = ucfirst($name);
                }
            }

            // Stream ZIP directly to browser — ZipStream v3 handles headers
            $filename = 'invoices_' . $dates['from'] . '_to_' . $dates['to'] . '.zip';

            $zip = new ZipStream(
                outputName: $filename,
                sendHttpHeaders: true,
            );

            $chunkSize = 50;

            // Merge all invoices into one list with their type
            $allInvoices = [];
            foreach ($result['exempt_invoices'] as $inv) {
                $inv['_type'] = 'exempt';
                $allInvoices[] = $inv;
            }
            foreach ($result['taxable_invoices'] as $inv) {
                $inv['_type'] = 'taxable';
                $allInvoices[] = $inv;
            }

            // Sort by date ascending, then by type (exempt first)
            usort($allInvoices, function ($a, $b) {
                $dateCmp = strcmp($a['invoice_date'], $b['invoice_date']);
                if ($dateCmp !== 0) return $dateCmp;
                return strcmp($a['_type'], $b['_type']);
            });

            // Build sequential filenames: YYYYMMDD0001.pdf per date
            $dateSequence = [];
            foreach ($allInvoices as &$inv) {
                $dateKey = str_replace('-', '', $inv['invoice_date']); // e.g. 20210701
                if (!isset($dateSequence[$dateKey])) {
                    $dateSequence[$dateKey] = 0;
                }
                $dateSequence[$dateKey]++;
                $inv['_pdf_name'] = $dateKey . str_pad($dateSequence[$dateKey], 4, '0', STR_PAD_LEFT) . '.pdf';
            }
            unset($inv);

            // Process all invoices in chunks (single folder, no subfolders)
            $allChunks = array_chunk($allInvoices, $chunkSize);
            foreach ($allChunks as $chunk) {
                foreach ($chunk as $invoice) {
                    $type = $invoice['_type'];
                    $pdfContent = $this->generateInvoicePdf($invoice, $location, $patientNames, $type, $dates['to'], $params['tax_percent']);
                    $zip->addFile(fileName: $invoice['_pdf_name'], data: $pdfContent);
                    unset($pdfContent);
                }
                gc_collect_cycles();
            }

            $zip->finish();
            exit;

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating invoices ZIP: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate a single invoice PDF and return its content as a string.
     *
     * @param array $invoice Invoice data array
     * @param \App\Models\Locations $location Location model
     * @param array $patientNames Pre-fetched patient names keyed by ID
     * @param string $type 'exempt' or 'taxable'
     * @param string $dateTo End date of the report range (Y-m-d)
     * @param float $userTaxPercent
     * @return string PDF content
     */
    protected function generateInvoicePdf(array $invoice, $location, array $patientNames, string $type, string $dateTo, float $userTaxPercent = 13): string
    {
        $patientName = $patientNames[$invoice['patient_id']] ?? 'Patient C-' . $invoice['patient_id'];

        if ($type === 'exempt') {
            $serviceName = 'Medical Consultation';
            $taxPercent = 0;
            $taxAmount = 0;
            $totalAmount = $invoice['amount'];
        } else {
            $serviceName = 'Aesthetic Procedure';
            $taxPercent = $userTaxPercent;
            $taxAmount = round($invoice['amount'] * $taxPercent / 100, 2);
            $totalAmount = $invoice['amount'] + $taxAmount;
        }

        $servicePrice = $invoice['amount'];

        $pdf = PDF::loadView('admin.reports.taxcalculationreport.invoice-pdf', [
            'invoice' => $invoice,
            'location' => $location,
            'patient_name' => $patientName,
            'service_label' => $serviceName,
            'service_name' => $serviceName,
            'service_price' => $servicePrice,
            'tax_percent' => $taxPercent,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
        ]);

        return $pdf->output();
    }

    /**
     * Create Summary sheet
     */
    protected function createSummarySheet($sheet, array $result): void
    {
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1a365d']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ];

        $subHeaderStyle = [
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'e2e8f0']],
        ];

        $row = 1;

        // Title
        $sheet->setCellValue('A' . $row, 'Invoice Generation Summary Report');
        $sheet->mergeCells('A' . $row . ':D' . $row);
        $sheet->getStyle('A' . $row)->applyFromArray($headerStyle);
        $sheet->getStyle('A' . $row)->getFont()->setSize(14);
        $row += 2;

        // Parameters Section
        $sheet->setCellValue('A' . $row, 'Parameters');
        $sheet->mergeCells('A' . $row . ':D' . $row);
        $sheet->getStyle('A' . $row)->applyFromArray($subHeaderStyle);
        $row++;

        $params = $result['parameters'];
        $sheet->setCellValue('A' . $row, 'Date Range:');
        $sheet->setCellValue('B' . $row, $params['date_from'] . ' to ' . $params['date_to']);
        $row++;
        $sheet->setCellValue('A' . $row, 'Bank Taxable %:');
        $sheet->setCellValue('B' . $row, $params['bank_taxable_percent'] . '% (Exempt: ' . (100 - $params['bank_taxable_percent']) . '%)');
        $row++;
        $sheet->setCellValue('A' . $row, 'Cash %:');
        $sheet->setCellValue('B' . $row, $params['cash_percent'] . '%');
        $row++;
        $sheet->setCellValue('A' . $row, 'Consultation Amount:');
        $sheet->setCellValue('B' . $row, number_format($params['consultation_amount'], 2));
        $row += 2;

        // Capacity Section
        $sheet->setCellValue('A' . $row, 'Capacity');
        $sheet->mergeCells('A' . $row . ':D' . $row);
        $sheet->getStyle('A' . $row)->applyFromArray($subHeaderStyle);
        $row++;

        $capacity = $result['capacity'];
        $sheet->setCellValue('A' . $row, 'Working Days:');
        $sheet->setCellValue('B' . $row, $capacity['working_days']);
        $row++;
        $sheet->setCellValue('A' . $row, 'Invoice Days per Patient:');
        $sheet->setCellValue('B' . $row, $capacity['invoice_days_per_patient']);
        $row++;
        $sheet->setCellValue('A' . $row, 'Max Invoices per Patient:');
        $sheet->setCellValue('B' . $row, $capacity['max_invoices_per_patient']);
        $row++;
        $sheet->setCellValue('A' . $row, 'Max Exempt per Patient:');
        $sheet->setCellValue('B' . $row, number_format($capacity['max_exempt_per_patient'], 2));
        $row += 2;

        // Payment Totals Section
        $sheet->setCellValue('A' . $row, 'Payment Totals');
        $sheet->mergeCells('A' . $row . ':D' . $row);
        $sheet->getStyle('A' . $row)->applyFromArray($subHeaderStyle);
        $row++;

        $totals = $result['totals'];
        $sheet->setCellValue('A' . $row, 'Bank Total:');
        $sheet->setCellValue('B' . $row, number_format($totals['bank']['total'], 2));
        $sheet->setCellValue('C' . $row, '(' . $totals['bank']['count'] . ' records)');
        $row++;
        $sheet->setCellValue('A' . $row, 'Card Total:');
        $sheet->setCellValue('B' . $row, number_format($totals['card']['total'], 2));
        $sheet->setCellValue('C' . $row, '(' . $totals['card']['count'] . ' records)');
        $row++;
        $sheet->setCellValue('A' . $row, 'Cash Total:');
        $sheet->setCellValue('B' . $row, number_format($totals['cash']['total'], 2));
        $sheet->setCellValue('C' . $row, '(' . $totals['cash']['count'] . ' records)');
        $row++;
        $sheet->setCellValue('A' . $row, 'Cash Used (' . $totals['cash']['percent_used'] . '%):');
        $sheet->setCellValue('B' . $row, number_format($totals['cash']['amount_used'], 2));
        $row++;
        $sheet->setCellValue('A' . $row, 'Grand Total:');
        $sheet->setCellValue('B' . $row, number_format($totals['grand_total'], 2));
        $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
        $row += 2;

        // Pool Section
        $sheet->setCellValue('A' . $row, 'Pool Calculation');
        $sheet->mergeCells('A' . $row . ':D' . $row);
        $sheet->getStyle('A' . $row)->applyFromArray($subHeaderStyle);
        $row++;

        $pool = $result['pool'];
        $sheet->setCellValue('A' . $row, 'Total Pool (Bank + Card + Cash%):');
        $sheet->setCellValue('B' . $row, number_format($pool['total'], 2));
        $row++;
        $sheet->setCellValue('A' . $row, 'Target Exempt (' . $pool['exempt_percent'] . '%):');
        $sheet->setCellValue('B' . $row, number_format($pool['target_exempt'], 2));
        $row++;
        $sheet->setCellValue('A' . $row, 'Target Taxable (' . $pool['taxable_percent'] . '%):');
        $sheet->setCellValue('B' . $row, number_format($pool['target_taxable'], 2));
        $row++;
        $sheet->setCellValue('A' . $row, 'Target Range:');
        $sheet->setCellValue('B' . $row, $pool['target_range']['min_percent'] . '-' . $pool['target_range']['max_percent'] . '% (' . number_format($pool['target_range']['min'], 2) . ' - ' . number_format($pool['target_range']['max'], 2) . ')');
        $row += 2;

        // Feasibility Section
        $sheet->setCellValue('A' . $row, 'Feasibility Check');
        $sheet->mergeCells('A' . $row . ':D' . $row);
        $sheet->getStyle('A' . $row)->applyFromArray($subHeaderStyle);
        $row++;

        $feasibility = $result['feasibility'];
        $sheet->setCellValue('A' . $row, 'Max Possible Exempt:');
        $sheet->setCellValue('B' . $row, number_format($feasibility['max_possible_exempt'], 2));
        $sheet->setCellValue('C' . $row, '(' . $feasibility['max_possible_percent'] . '%)');
        $row++;
        $sheet->setCellValue('A' . $row, 'Target Achievable:');
        $sheet->setCellValue('B' . $row, $feasibility['is_achievable'] ? 'YES' : 'NO');
        $sheet->getStyle('B' . $row)->getFont()->setColor(
            new \PhpOffice\PhpSpreadsheet\Style\Color($feasibility['is_achievable'] ? '276749' : 'c53030')
        );
        $row += 2;

        // Final Summary Section
        $sheet->setCellValue('A' . $row, 'Final Summary');
        $sheet->mergeCells('A' . $row . ':D' . $row);
        $sheet->getStyle('A' . $row)->applyFromArray($subHeaderStyle);
        $row++;

        $summary = $result['summary'];
        $sheet->setCellValue('A' . $row, 'Total Patients:');
        $sheet->setCellValue('B' . $row, $summary['total_patients']);
        $row++;
        $sheet->setCellValue('A' . $row, 'Total Pool:');
        $sheet->setCellValue('B' . $row, number_format($summary['total_pool'], 2));
        $row++;
        $sheet->setCellValue('A' . $row, 'Total Exempt Invoiced:');
        $sheet->setCellValue('B' . $row, number_format($summary['total_exempt_invoiced'], 2));
        $sheet->setCellValue('C' . $row, '(' . $summary['total_exempt_invoices'] . ' invoices)');
        $row++;
        $sheet->setCellValue('A' . $row, 'Total Taxable Invoiced:');
        $sheet->setCellValue('B' . $row, number_format($summary['total_taxable_invoiced'], 2));
        $sheet->setCellValue('C' . $row, '(' . $summary['total_taxable_invoices'] . ' invoices)');
        $row++;
        $sheet->setCellValue('A' . $row, 'Exempt Percentage:');
        $sheet->setCellValue('B' . $row, $summary['exempt_percent'] . '%');
        $row++;
        $sheet->setCellValue('A' . $row, 'Taxable Percentage:');
        $sheet->setCellValue('B' . $row, $summary['taxable_percent'] . '%');
        $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);
        $row++;
        $sheet->setCellValue('A' . $row, 'Total Invoices:');
        $sheet->setCellValue('B' . $row, $summary['total_invoices']);
        $row++;
        $sheet->setCellValue('A' . $row, 'Verification Match:');
        $sheet->setCellValue('B' . $row, $summary['verification']['match'] ? 'YES' : 'NO');

        // Auto-size columns
        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Create Invoice sheet
     */
    protected function createInvoiceSheet($sheet, array $invoices): void
    {
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1a365d']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];

        $dataStyle = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];

        // Headers
        $sheet->setCellValue('A1', 'Invoice Number');
        $sheet->setCellValue('B1', 'Patient ID');
        $sheet->setCellValue('C1', 'Plan ID');
        $sheet->setCellValue('D1', 'Invoice Date');
        $sheet->setCellValue('E1', 'Amount');
        $sheet->getStyle('A1:E1')->applyFromArray($headerStyle);

        // Data
        $row = 2;
        foreach ($invoices as $invoice) {
            $sheet->setCellValue('A' . $row, $invoice['invoice_number']);
            $sheet->setCellValue('B' . $row, $invoice['patient_id']);
            $sheet->setCellValue('C' . $row, $invoice['plan_id']);
            // Format date as 1-DEC-25
            $formattedDate = \Carbon\Carbon::parse($invoice['invoice_date'])->format('j-M-y');
            $sheet->setCellValue('D' . $row, strtoupper($formattedDate));
            $sheet->setCellValue('E' . $row, $invoice['amount']);
            $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            $row++;
        }

        // Apply borders to data
        if (count($invoices) > 0) {
            $sheet->getStyle('A2:E' . ($row - 1))->applyFromArray($dataStyle);
        }

        // Total row
        $sheet->setCellValue('A' . $row, 'TOTAL');
        $sheet->setCellValue('E' . $row, '=SUM(E2:E' . ($row - 1) . ')');
        $sheet->getStyle('A' . $row . ':E' . $row)->getFont()->setBold(true);
        $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0.00');

        // Auto-size columns
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(12);
        $sheet->getColumnDimension('C')->setWidth(12);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(15);
    }

    /**
     * Create Patient Distribution sheet
     */
    protected function createPatientSheet($sheet, array $distribution): void
    {
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1a365d']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];

        $dataStyle = [
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];

        // Headers
        $headers = ['Patient ID', 'Pool Share', 'Category', 'Exempt %', 'Exempt Amount', 'Taxable Amount'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '1', $header);
            $col++;
        }
        $sheet->getStyle('A1:F1')->applyFromArray($headerStyle);

        // Data
        $row = 2;
        foreach ($distribution as $patient) {
            $sheet->setCellValue('A' . $row, $patient['patient_id']);
            $sheet->setCellValue('B' . $row, $patient['pool_share']);
            $sheet->setCellValue('C' . $row, ucfirst($patient['category']));
            $sheet->setCellValue('D' . $row, $patient['exempt_percent'] . '%');
            $sheet->setCellValue('E' . $row, $patient['exempt_amount']);
            $sheet->setCellValue('F' . $row, $patient['taxable_amount']);

            // Format numbers
            $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode('#,##0.00');

            // Color by category
            $categoryColors = [
                'capped' => 'ffcccc',
                'medium' => 'ffeeba',
                'small' => 'c6f6d5',
            ];
            if (isset($categoryColors[$patient['category']])) {
                $sheet->getStyle('C' . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB($categoryColors[$patient['category']]);
            }

            $row++;
        }

        // Apply borders to data
        if (count($distribution) > 0) {
            $sheet->getStyle('A2:F' . ($row - 1))->applyFromArray($dataStyle);
        }

        // Total row
        $sheet->setCellValue('A' . $row, 'TOTAL');
        $sheet->setCellValue('B' . $row, '=SUM(B2:B' . ($row - 1) . ')');
        $sheet->setCellValue('E' . $row, '=SUM(E2:E' . ($row - 1) . ')');
        $sheet->setCellValue('F' . $row, '=SUM(F2:F' . ($row - 1) . ')');
        $sheet->getStyle('A' . $row . ':F' . $row)->getFont()->setBold(true);
        $sheet->getStyle('B' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('E' . $row)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle('F' . $row)->getNumberFormat()->setFormatCode('#,##0.00');

        // Auto-size columns
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    /**
     * Parse date range string into from and to dates
     */
    protected function parseDateRange(string $dateRange): array
    {
        $parts = explode(' - ', $dateRange);

        return [
            'from' => \Carbon\Carbon::createFromFormat('m/d/Y', trim($parts[0]))->toDateString(),
            'to' => \Carbon\Carbon::createFromFormat('m/d/Y', trim($parts[1]))->toDateString(),
        ];
    }
}
