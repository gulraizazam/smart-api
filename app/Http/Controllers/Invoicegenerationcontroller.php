<?php

namespace App\Http\Controllers;

use App\Services\InvoiceGenerationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class InvoiceGenerationController extends Controller
{
    protected $invoiceService;

    public function __construct(InvoiceGenerationService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    /**
     * Step 1: Calculate amounts based on input parameters
     * 
     * POST /api/invoices/calculate-amounts
     * 
     * Request body:
     * {
     *     "date_range": "11/01/2025 - 11/30/2025",
     *     "location_ids": [2, 46],
     *     "bank_taxable": 30,
     *     "cash_taxable": 0,
     *     "consultation_amount": 1500
     * }
     */
    public function calculateAmounts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_range' => 'required|string',
            'location_ids' => 'required|array',
            'location_ids.*' => 'integer',
            'bank_taxable' => 'required|numeric|min:0|max:100',
            'cash_taxable' => 'required|numeric|min:0|max:100',
            'consultation_amount' => 'required|numeric|min:0',
        ]);

        // Parse date range
        $dates = $this->parseDateRange($validated['date_range']);

        $params = [
            'date_from' => $dates['from'],
            'date_to' => $dates['to'],
            'location_ids' => $validated['location_ids'],
            'bank_taxable' => $validated['bank_taxable'],
            'cash_taxable' => $validated['cash_taxable'],
            'consultation_amount' => $validated['consultation_amount'],
        ];

        try {
            $result = $this->invoiceService->calculateAmounts($params);

            return response()->json([
                'success' => true,
                'message' => 'Amounts calculated successfully',
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
     * Parse date range string into from and to dates
     * 
     * @param string $dateRange Format: "11/01/2025 - 11/30/2025"
     * @return array
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