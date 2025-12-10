<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InvoiceGenerationService
{
    // Payment Mode IDs - adjust these according to your payment_modes table
    const PAYMENT_MODE_BANK = 2;  // Bank Transfer
    const PAYMENT_MODE_CASH = 1;  // Cash
    const PAYMENT_MODE_CARD = 3;  // Card (will be summed into bank)

    protected $dateFrom;
    protected $dateTo;
    protected $locationIds;
    protected $bankTaxablePercent;
    protected $cashTaxablePercent;
    protected $consultationAmount;

    /**
     * Step 1: Calculate all amounts
     * 
     * @param array $params
     * @return array
     */
    public function calculateAmounts(array $params): array
    {
        // Set parameters
        $this->dateFrom = Carbon::parse($params['date_from'])->startOfDay();
        $this->dateTo = Carbon::parse($params['date_to'])->endOfDay();
        $this->locationIds = $params['location_ids'];
        $this->bankTaxablePercent = $params['bank_taxable'];      // e.g., 30
        $this->cashTaxablePercent = $params['cash_taxable'];      // e.g., 0
        $this->consultationAmount = $params['consultation_amount']; // e.g., 1500

        // Step 1a: Get total amounts by payment method
        $totals = $this->getTotalsByPaymentMethod();

        // Step 1b: Calculate taxable and non-taxable splits
        $splits = $this->calculateTaxableSplits($totals);

        // Step 1c: Get patient-wise breakdown
        $patientShares = $this->calculatePatientShares($totals, $splits);

        return [
            'parameters' => [
                'date_from' => $this->dateFrom->toDateString(),
                'date_to' => $this->dateTo->toDateString(),
                'location_ids' => $this->locationIds,
                'bank_taxable_percent' => $this->bankTaxablePercent,
                'cash_taxable_percent' => $this->cashTaxablePercent,
                'consultation_amount' => $this->consultationAmount,
            ],
            'totals' => $totals,
            'splits' => $splits,
            'patient_shares' => $patientShares,
            'summary' => $this->generateSummary($totals, $splits, $patientShares),
        ];
    }

    /**
     * Get total amounts grouped by payment method
     * Note: Card payments are summed into Bank
     * 
     * @return array
     */
    protected function getTotalsByPaymentMethod(): array
    {
        $results = DB::table('package_advances')
            ->select(
                'payment_mode_id',
                DB::raw('SUM(cash_amount) as total_amount'),
                DB::raw('COUNT(*) as record_count')
            )
            ->where('cash_flow', 'in')
            ->where('cash_amount', '>', 0)
            ->whereIn('location_id', $this->locationIds)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->groupBy('payment_mode_id')
            ->get();

        $bankTotal = 0;
        $cardTotal = 0;
        $cashTotal = 0;
        $bankCount = 0;
        $cardCount = 0;
        $cashCount = 0;

        foreach ($results as $row) {
            if ($row->payment_mode_id == self::PAYMENT_MODE_BANK) {
                $bankTotal = (float) $row->total_amount;
                $bankCount = (int) $row->record_count;
            } elseif ($row->payment_mode_id == self::PAYMENT_MODE_CARD) {
                $cardTotal = (float) $row->total_amount;
                $cardCount = (int) $row->record_count;
            } elseif ($row->payment_mode_id == self::PAYMENT_MODE_CASH) {
                $cashTotal = (float) $row->total_amount;
                $cashCount = (int) $row->record_count;
            }
        }

        // Combine bank + card as "bank" for calculation purposes
        $combinedBankTotal = $bankTotal + $cardTotal;
        $combinedBankCount = $bankCount + $cardCount;

        return [
            'bank' => [
                'total' => $combinedBankTotal,
                'count' => $combinedBankCount,
                'breakdown' => [
                    'bank_transfer' => ['total' => $bankTotal, 'count' => $bankCount],
                    'card' => ['total' => $cardTotal, 'count' => $cardCount],
                ],
            ],
            'cash' => [
                'total' => $cashTotal,
                'count' => $cashCount,
            ],
            'grand_total' => $combinedBankTotal + $cashTotal,
            'total_records' => $combinedBankCount + $cashCount,
        ];
    }

    /**
     * Calculate taxable and non-taxable splits
     * 
     * @param array $totals
     * @return array
     */
    protected function calculateTaxableSplits(array $totals): array
    {
        $bankTotal = $totals['bank']['total'];
        $cashTotal = $totals['cash']['total'];

        // Bank: e.g., 30% non-taxable, 70% taxable
        $bankNonTaxablePercent = $this->bankTaxablePercent;
        $bankTaxablePercent = 100 - $this->bankTaxablePercent;

        // Cash: e.g., 0% non-taxable, 100% taxable (when cash_taxable = 0)
        $cashNonTaxablePercent = $this->cashTaxablePercent;
        $cashTaxablePercent = 100 - $this->cashTaxablePercent;

        // Calculate amounts
        $bankTaxableAmount = $bankTotal * ($bankTaxablePercent / 100);
        $bankNonTaxableAmount = $bankTotal * ($bankNonTaxablePercent / 100);

        $cashTaxableAmount = $cashTotal * ($cashTaxablePercent / 100);
        $cashNonTaxableAmount = $cashTotal * ($cashNonTaxablePercent / 100);

        return [
            'bank' => [
                'taxable_percent' => $bankTaxablePercent,
                'non_taxable_percent' => $bankNonTaxablePercent,
                'taxable_amount' => round($bankTaxableAmount, 2),
                'non_taxable_amount' => round($bankNonTaxableAmount, 2),
            ],
            'cash' => [
                'taxable_percent' => $cashTaxablePercent,
                'non_taxable_percent' => $cashNonTaxablePercent,
                'taxable_amount' => round($cashTaxableAmount, 2),
                'non_taxable_amount' => round($cashNonTaxableAmount, 2),
            ],
            'combined' => [
                'taxable_amount' => round($bankTaxableAmount + $cashTaxableAmount, 2),
                'non_taxable_amount' => round($bankNonTaxableAmount + $cashNonTaxableAmount, 2),
            ],
        ];
    }

    /**
     * Calculate each patient's share in taxable and non-taxable amounts
     * Note: Card payments are summed into Bank for each patient
     * 
     * @param array $totals
     * @param array $splits
     * @return array
     */
    protected function calculatePatientShares(array $totals, array $splits): array
    {
        // Get patient-wise totals by payment method
        $patientPayments = DB::table('package_advances')
            ->select(
                'patient_id',
                'payment_mode_id',
                DB::raw('SUM(cash_amount) as total_amount'),
                DB::raw('COUNT(*) as payment_count')
            )
            ->where('cash_flow', 'in')
            ->where('cash_amount', '>', 0)
            ->whereIn('location_id', $this->locationIds)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->groupBy('patient_id', 'payment_mode_id')
            ->get();

        // Organize by patient
        $patients = [];
        foreach ($patientPayments as $payment) {
            $patientId = $payment->patient_id;
            
            if (!isset($patients[$patientId])) {
                $patients[$patientId] = [
                    'patient_id' => $patientId,
                    'bank_paid' => 0,
                    'card_paid' => 0,
                    'cash_paid' => 0,
                    'bank_payment_count' => 0,
                    'card_payment_count' => 0,
                    'cash_payment_count' => 0,
                ];
            }

            if ($payment->payment_mode_id == self::PAYMENT_MODE_BANK) {
                $patients[$patientId]['bank_paid'] = (float) $payment->total_amount;
                $patients[$patientId]['bank_payment_count'] = (int) $payment->payment_count;
            } elseif ($payment->payment_mode_id == self::PAYMENT_MODE_CARD) {
                $patients[$patientId]['card_paid'] = (float) $payment->total_amount;
                $patients[$patientId]['card_payment_count'] = (int) $payment->payment_count;
            } elseif ($payment->payment_mode_id == self::PAYMENT_MODE_CASH) {
                $patients[$patientId]['cash_paid'] = (float) $payment->total_amount;
                $patients[$patientId]['cash_payment_count'] = (int) $payment->payment_count;
            }
        }

        // Calculate shares for each patient
        $bankTotal = $totals['bank']['total']; // This already includes card
        $cashTotal = $totals['cash']['total'];

        $patientShares = [];
        foreach ($patients as $patientId => $data) {
            // Combine bank + card for this patient
            $combinedBankPaid = $data['bank_paid'] + $data['card_paid'];
            $combinedBankPaymentCount = $data['bank_payment_count'] + $data['card_payment_count'];

            // Calculate percentages
            $bankPercent = $bankTotal > 0 ? ($combinedBankPaid / $bankTotal) : 0;
            $cashPercent = $cashTotal > 0 ? ($data['cash_paid'] / $cashTotal) : 0;

            // Calculate taxable shares
            $bankTaxableShare = $splits['bank']['taxable_amount'] * $bankPercent;
            $cashTaxableShare = $splits['cash']['taxable_amount'] * $cashPercent;

            // Calculate non-taxable shares
            $bankNonTaxableShare = $splits['bank']['non_taxable_amount'] * $bankPercent;
            $cashNonTaxableShare = $splits['cash']['non_taxable_amount'] * $cashPercent;

            $totalPaid = $combinedBankPaid + $data['cash_paid'];
            $totalTaxableShare = $bankTaxableShare + $cashTaxableShare;
            $totalNonTaxableShare = $bankNonTaxableShare + $cashNonTaxableShare;

            $patientShares[] = [
                'patient_id' => $patientId,
                'bank_paid' => round($combinedBankPaid, 2), // Bank + Card combined
                'bank_paid_breakdown' => [
                    'bank_transfer' => round($data['bank_paid'], 2),
                    'card' => round($data['card_paid'], 2),
                ],
                'cash_paid' => round($data['cash_paid'], 2),
                'total_paid' => round($totalPaid, 2),
                'bank_percent' => round($bankPercent * 100, 4),
                'cash_percent' => round($cashPercent * 100, 4),
                'taxable_share' => [
                    'bank' => round($bankTaxableShare, 2),
                    'cash' => round($cashTaxableShare, 2),
                    'total' => round($totalTaxableShare, 2),
                ],
                'non_taxable_share' => [
                    'bank' => round($bankNonTaxableShare, 2),
                    'cash' => round($cashNonTaxableShare, 2),
                    'total' => round($totalNonTaxableShare, 2),
                ],
                'payment_count' => $combinedBankPaymentCount + $data['cash_payment_count'],
                // Verification: taxable + non_taxable should equal total_paid
                'verification' => round($totalTaxableShare + $totalNonTaxableShare, 2),
            ];
        }

        // Sort by total_paid descending
        usort($patientShares, function ($a, $b) {
            return $b['total_paid'] <=> $a['total_paid'];
        });

        return $patientShares;
    }

    /**
     * Generate summary statistics
     * 
     * @param array $totals
     * @param array $splits
     * @param array $patientShares
     * @return array
     */
    protected function generateSummary(array $totals, array $splits, array $patientShares): array
    {
        $totalTaxableShare = array_sum(array_column($patientShares, 'taxable_share'));
        $totalNonTaxableShare = array_sum(array_column($patientShares, 'non_taxable_share'));

        // Fix: Calculate from nested arrays
        $taxableSum = 0;
        $nonTaxableSum = 0;
        foreach ($patientShares as $share) {
            $taxableSum += $share['taxable_share']['total'];
            $nonTaxableSum += $share['non_taxable_share']['total'];
        }

        return [
            'total_patients' => count($patientShares),
            'total_payments' => $totals['total_records'],
            'grand_total' => $totals['grand_total'],
            'taxable_total' => $splits['combined']['taxable_amount'],
            'non_taxable_total' => $splits['combined']['non_taxable_amount'],
            'patient_taxable_sum' => round($taxableSum, 2),
            'patient_non_taxable_sum' => round($nonTaxableSum, 2),
            'verification_match' => abs($totals['grand_total'] - ($taxableSum + $nonTaxableSum)) < 0.01,
        ];
    }
}