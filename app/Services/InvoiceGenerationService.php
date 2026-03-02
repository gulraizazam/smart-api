<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InvoiceGenerationService
{
    // Payment Mode IDs - Only active payment modes in payment_modes table
    const PAYMENT_MODE_CASH = 1;  // Cash
    const PAYMENT_MODE_CARD = 2;  // Card
    const PAYMENT_MODE_BANK = 4;  // Bank/Wire Transfer
    // Note: PayPal (ID 3) is deleted, Settle Amount (ID 5) is excluded from calculations

    protected $dateFrom;
    protected $dateTo;
    protected $locationIds;
    protected $bankTaxablePercent;
    protected $cashPercent;
    protected $consultationAmount;
    protected $consultationAmounts = [];
    protected $isMixedMode = false;
    protected $maxExemptPerPatient;
    protected $unplacedExemptPerPatient = [];
    protected $workingDays = [];
    protected $usedInvoiceNumbers = [];
    protected $dailyRevenue = [];
    protected $dailyBudgetUsed = [];
    protected $patientDailyInvoiceCount = [];
    protected $taxPercent;
    protected $maxInvoicesPerDay;

    /**
     * Main function to calculate and generate exempt invoices
     */
    public function generateExemptInvoices(array $params): array
    {
        // Set parameters
        $this->dateFrom = Carbon::parse($params['date_from'])->startOfDay();
        $this->dateTo = Carbon::parse($params['date_to'])->endOfDay();
        $this->locationIds = $params['location_ids'];
        $this->bankTaxablePercent = $params['bank_taxable'];      // e.g., 30 means 30% taxable, 70% exempt
        $this->cashPercent = $params['cash_percent'];              // e.g., 5 means only 5% of cash is used
        $this->consultationAmount = $params['consultation_amount']; // e.g., 1500 or 2000
        $this->taxPercent = $params['tax_percent'] ?? 13;
        $this->maxInvoicesPerDay = $params['max_invoices_per_day'] ?? 2;
        $this->usedInvoiceNumbers = [];

        // Set up denomination mode
        if ($this->consultationAmount == 2000) {
            $this->isMixedMode = true;
            $this->consultationAmounts = [3000, 2500, 2000]; // largest first for greedy fitting
        } else {
            $this->isMixedMode = false;
            $this->consultationAmounts = [$this->consultationAmount]; // fixed 1500
        }

        // Step 1: Calculate working days
        $this->calculateWorkingDays();

        // Step 1b: Calculate daily revenue and filter working days to revenue-active days only
        $this->calculateDailyRevenue();

        // Step 1c: Calculate max capacity based on revenue-active days
        $maxInvoicesPerPatient = $this->calculateMaxInvoicesPerPatient();
        // Use smallest denomination for max capacity calculation
        $smallestDenom = min($this->consultationAmounts);
        $this->maxExemptPerPatient = $maxInvoicesPerPatient * $smallestDenom;

        // Step 2: Get payment totals
        $totals = $this->getPaymentTotals();

        // Step 3: Calculate pool
        $pool = $this->calculatePool($totals);

        // Step 4: Get patient-wise data
        $patients = $this->getPatientPayments($totals, $pool);

        // Step 5: Categorize patients
        $categorizedPatients = $this->categorizePatients($patients);

        // Step 6: Check if target is achievable
        $feasibility = $this->checkFeasibility($categorizedPatients, $pool);

        // Step 7: Distribute exempt percentages using smart algorithm
        $distribution = $this->distributeExemptPercentages($categorizedPatients, $pool, $feasibility);

        // Initialize daily budget tracker and per-patient-per-day counter (shared across exempt + taxable)
        $this->dailyBudgetUsed = [];
        $this->patientDailyInvoiceCount = [];

        // Step 8: Generate exempt invoices (returns ['invoices' => [...], 'unplaced_exempt' => [...]])
        $exemptResult = $this->generateInvoices($distribution, 'exempt');
        $exemptInvoices = $exemptResult['invoices'];

        // Step 9: Generate taxable invoices (includes unplaced exempt amounts)
        $taxableInvoices = $this->generateTaxableInvoices($distribution);

        // Step 10: Calculate final summary
        $summary = $this->calculateSummary($distribution, $exemptInvoices, $taxableInvoices, $pool);

        return [
            'parameters' => [
                'date_from' => $this->dateFrom->toDateString(),
                'date_to' => $this->dateTo->toDateString(),
                'location_ids' => $this->locationIds,
                'bank_taxable_percent' => $this->bankTaxablePercent,
                'cash_percent' => $this->cashPercent,
                'consultation_amount' => $this->consultationAmount,
                'tax_percent' => $this->taxPercent,
                'max_invoices_per_day' => $this->maxInvoicesPerDay,
            ],
            'capacity' => [
                'working_days' => count($this->workingDays),
                'invoice_days_per_patient' => floor(count($this->workingDays) / 2), // with 1-day gap
                'max_invoices_per_patient' => $maxInvoicesPerPatient,
                'max_exempt_per_patient' => $this->maxExemptPerPatient,
            ],
            'totals' => $totals,
            'pool' => array_merge($pool, ['actual_taxable_invoiced' => $summary['total_taxable_invoiced']]),
            'feasibility' => $feasibility,
            'patient_distribution' => $distribution,
            'exempt_invoices' => $exemptInvoices,
            'taxable_invoices' => $taxableInvoices,
            'summary' => $summary,
        ];
    }

    /**
     * Calculate working days (excluding Sundays) in the date range
     */
    protected function calculateWorkingDays(): void
    {
        $this->workingDays = [];
        $current = $this->dateFrom->copy();

        while ($current <= $this->dateTo) {
            // Exclude Sundays (0 = Sunday in Carbon)
            if ($current->dayOfWeek !== Carbon::SUNDAY) {
                $this->workingDays[] = $current->copy();
            }
            $current->addDay();
        }
    }

    /**
     * Calculate daily revenue (bank + card + cash%) and filter working days to revenue-active days only.
     * Also stores revenue weight per day for proportional invoice distribution.
     */
    protected function calculateDailyRevenue(): void
    {
        // Query daily revenue by payment mode from package_advances (incoming payments only)
        $dailyPayments = DB::table('package_advances')
            ->select(
                DB::raw('DATE(created_at) as payment_date'),
                'payment_mode_id',
                DB::raw('SUM(cash_amount) as daily_total')
            )
            ->where('cash_flow', 'in')
            ->where('cash_amount', '>', 0)
            ->where('is_cancel', 0)
            ->whereIn('location_id', $this->locationIds)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->groupBy(DB::raw('DATE(created_at)'), 'payment_mode_id')
            ->get();

        // Query daily refunds by payment mode
        $dailyRefunds = DB::table('package_advances')
            ->select(
                DB::raw('DATE(created_at) as payment_date'),
                'payment_mode_id',
                DB::raw('SUM(cash_amount) as daily_total')
            )
            ->where('cash_flow', 'out')
            ->where('cash_amount', '>', 0)
            ->where('is_refund', 1)
            ->where('is_cancel', 0)
            ->whereIn('location_id', $this->locationIds)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->groupBy(DB::raw('DATE(created_at)'), 'payment_mode_id')
            ->get();

        // Build daily refund map
        $dailyRefundMap = [];
        foreach ($dailyRefunds as $row) {
            $date = $row->payment_date;
            if (!isset($dailyRefundMap[$date])) {
                $dailyRefundMap[$date] = ['bank' => 0, 'card' => 0, 'cash' => 0];
            }
            if ($row->payment_mode_id == self::PAYMENT_MODE_BANK) {
                $dailyRefundMap[$date]['bank'] = (float) $row->daily_total;
            } elseif ($row->payment_mode_id == self::PAYMENT_MODE_CARD) {
                $dailyRefundMap[$date]['card'] = (float) $row->daily_total;
            } elseif ($row->payment_mode_id == self::PAYMENT_MODE_CASH) {
                $dailyRefundMap[$date]['cash'] = (float) $row->daily_total;
            }
        }

        // Build daily revenue map: date => pool amount (bank + card + cash%)
        $dailyMap = [];
        foreach ($dailyPayments as $row) {
            $date = $row->payment_date;
            if (!isset($dailyMap[$date])) {
                $dailyMap[$date] = ['bank' => 0, 'card' => 0, 'cash' => 0];
            }
            if ($row->payment_mode_id == self::PAYMENT_MODE_BANK) {
                $dailyMap[$date]['bank'] = (float) $row->daily_total;
            } elseif ($row->payment_mode_id == self::PAYMENT_MODE_CARD) {
                $dailyMap[$date]['card'] = (float) $row->daily_total;
            } elseif ($row->payment_mode_id == self::PAYMENT_MODE_CASH) {
                $dailyMap[$date]['cash'] = (float) $row->daily_total;
            }
        }

        // Subtract daily refunds from gross daily amounts
        foreach ($dailyRefundMap as $date => $refunds) {
            if (isset($dailyMap[$date])) {
                $dailyMap[$date]['bank'] = max(0, $dailyMap[$date]['bank'] - $refunds['bank']);
                $dailyMap[$date]['card'] = max(0, $dailyMap[$date]['card'] - $refunds['card']);
                $dailyMap[$date]['cash'] = max(0, $dailyMap[$date]['cash'] - $refunds['cash']);
            }
        }

        // Calculate net pool revenue per day and total
        $this->dailyRevenue = [];
        $totalDailyPool = 0;
        foreach ($dailyMap as $date => $amounts) {
            $dayPool = $amounts['bank'] + $amounts['card'] + ($amounts['cash'] * ($this->cashPercent / 100));
            if ($dayPool > 0) {
                $this->dailyRevenue[$date] = $dayPool;
                $totalDailyPool += $dayPool;
            }
        }

        // Filter workingDays to only include days with revenue
        $revenueDates = array_keys($this->dailyRevenue);
        $this->workingDays = array_values(array_filter($this->workingDays, function ($day) use ($revenueDates) {
            return in_array($day->format('Y-m-d'), $revenueDates);
        }));

        // Calculate weight (proportion) for each revenue day
        if ($totalDailyPool > 0) {
            foreach ($this->dailyRevenue as $date => $amount) {
                $this->dailyRevenue[$date] = [
                    'amount' => $amount,
                    'weight' => $amount / $totalDailyPool,
                ];
            }
        }
    }

    /**
     * Calculate maximum invoices per patient based on date range
     */
    protected function calculateMaxInvoicesPerPatient(): int
    {
        $totalWorkingDays = count($this->workingDays);
        
        // With 1-day gap, usable days = floor(working_days / 2)
        $usableInvoiceDays = floor($totalWorkingDays / 2);
        
        $invoicesPerDay = $this->maxInvoicesPerDay;
        
        return $usableInvoiceDays * $invoicesPerDay;
    }

    /**
     * Greedy coin-fitting: find the best combination of denominations to reach target amount.
     * Returns array of individual invoice amounts.
     * If remainder >= 500, adds it as a separate remainder invoice.
     */
    protected function fitDenominations(float $targetAmount): array
    {
        $amounts = [];
        $remaining = $targetAmount;
        $denoms = $this->consultationAmounts; // already sorted largest first

        while ($remaining >= min($denoms)) {
            foreach ($denoms as $denom) {
                if ($remaining >= $denom) {
                    $amounts[] = $denom;
                    $remaining -= $denom;
                    break; // restart from largest
                }
            }
        }

        // Remainder is NOT added as an exempt invoice — it goes to taxable
        return [
            'amounts' => $amounts,
            'total' => array_sum($amounts),
            'remainder' => round($remaining, 2),
        ];
    }

    /**
     * Get remaining daily budget for a given date.
     * Daily budget = that day's pool revenue. Invoices placed on that day consume from this budget.
     */
    protected function getDailyBudgetRemaining(string $dateStr): float
    {
        $dayRevenue = 0;
        if (isset($this->dailyRevenue[$dateStr])) {
            $dayRevenue = $this->dailyRevenue[$dateStr]['amount'];
        }
        $used = $this->dailyBudgetUsed[$dateStr] ?? 0;
        return max(0, $dayRevenue - $used);
    }

    /**
     * Consume daily budget for a given date.
     */
    protected function consumeDailyBudget(string $dateStr, float $amount): void
    {
        if (!isset($this->dailyBudgetUsed[$dateStr])) {
            $this->dailyBudgetUsed[$dateStr] = 0;
        }
        $this->dailyBudgetUsed[$dateStr] += $amount;
    }

    /**
     * Find the best date to place an invoice using soft cap with spillover.
     * 1. If the assigned date has enough budget, use it.
     * 2. Otherwise, find the working day with the most remaining budget.
     * 3. If ALL days are over budget, use the day with the most remaining budget (least over).
     * Returns the date string to use.
     */
    protected function findBestDateForInvoice(string $preferredDateStr, float $amount, int $patientId): string
    {
        $maxPerDay = $this->maxInvoicesPerDay;

        // Try preferred date first — check both budget AND per-patient-per-day cap
        if ($this->getDailyBudgetRemaining($preferredDateStr) >= $amount
            && $this->getPatientDayCount($patientId, $preferredDateStr) < $maxPerDay) {
            return $preferredDateStr;
        }

        // Spillover: find the working day with most remaining budget that also respects per-patient cap
        $bestDate = $preferredDateStr;
        $bestRemaining = -1;

        foreach ($this->workingDays as $day) {
            $dateStr = $day->format('Y-m-d');
            // Skip days where this patient already has maxPerDay invoices
            if ($this->getPatientDayCount($patientId, $dateStr) >= $maxPerDay) {
                continue;
            }
            $remaining = $this->getDailyBudgetRemaining($dateStr);
            if ($remaining > $bestRemaining) {
                $bestRemaining = $remaining;
                $bestDate = $dateStr;
            }
        }

        return $bestDate;
    }

    /**
     * Get how many invoices a patient already has on a given day.
     */
    protected function getPatientDayCount(int $patientId, string $dateStr): int
    {
        return $this->patientDailyInvoiceCount[$patientId][$dateStr] ?? 0;
    }

    /**
     * Track that a patient got an invoice on a given day.
     */
    protected function trackPatientDayInvoice(int $patientId, string $dateStr): void
    {
        if (!isset($this->patientDailyInvoiceCount[$patientId])) {
            $this->patientDailyInvoiceCount[$patientId] = [];
        }
        if (!isset($this->patientDailyInvoiceCount[$patientId][$dateStr])) {
            $this->patientDailyInvoiceCount[$patientId][$dateStr] = 0;
        }
        $this->patientDailyInvoiceCount[$patientId][$dateStr]++;
    }

    /**
     * Get total payments by payment method
     */
    protected function getPaymentTotals(): array
    {
        // Get incoming payments from package_advances
        $results = DB::table('package_advances')
            ->select(
                'payment_mode_id',
                DB::raw('SUM(cash_amount) as total_amount'),
                DB::raw('COUNT(*) as record_count')
            )
            ->where('cash_flow', 'in')
            ->where('cash_amount', '>', 0)
            ->where('is_cancel', 0)
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

        // Calculate refunds from package_advances (outgoing refund payments)
        $refundResults = DB::table('package_advances')
            ->select(
                'payment_mode_id',
                DB::raw('SUM(cash_amount) as total_amount'),
                DB::raw('COUNT(*) as record_count')
            )
            ->where('cash_flow', 'out')
            ->where('cash_amount', '>', 0)
            ->where('is_refund', 1)
            ->where('is_cancel', 0)
            ->whereIn('location_id', $this->locationIds)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->groupBy('payment_mode_id')
            ->get();

        $refundBank = 0;
        $refundCard = 0;
        $refundCash = 0;
        $refundBankCount = 0;
        $refundCardCount = 0;
        $refundCashCount = 0;

        foreach ($refundResults as $row) {
            if ($row->payment_mode_id == self::PAYMENT_MODE_BANK) {
                $refundBank = (float) $row->total_amount;
                $refundBankCount = (int) $row->record_count;
            } elseif ($row->payment_mode_id == self::PAYMENT_MODE_CARD) {
                $refundCard = (float) $row->total_amount;
                $refundCardCount = (int) $row->record_count;
            } elseif ($row->payment_mode_id == self::PAYMENT_MODE_CASH) {
                $refundCash = (float) $row->total_amount;
                $refundCashCount = (int) $row->record_count;
            }
        }

        $totalRefunds = $refundBank + $refundCard + $refundCash;
        $totalRefundCount = $refundBankCount + $refundCardCount + $refundCashCount;

        // Grand total = gross payments minus total refunds
        $grossTotal = $bankTotal + $cardTotal + $cashTotal;
        $grandTotal = $grossTotal - $totalRefunds;

        return [
            'bank' => [
                'total' => $bankTotal,
                'count' => $bankCount,
            ],
            'card' => [
                'total' => $cardTotal,
                'count' => $cardCount,
            ],
            'cash' => [
                'total' => $cashTotal,
                'count' => $cashCount,
                'percent_used' => $this->cashPercent,
                'amount_used' => $cashTotal * ($this->cashPercent / 100),
            ],
            'refunds' => [
                'total' => $totalRefunds,
                'count' => $totalRefundCount,
                'bank' => $refundBank,
                'card' => $refundCard,
                'cash' => $refundCash,
            ],
            'bank_plus_card' => $bankTotal + $cardTotal,
            'grand_total' => $grandTotal,
        ];
    }

    /**
     * Calculate the pool (Bank + Card + Cash%) using net amounts (gross - refunds)
     */
    protected function calculatePool(array $totals): array
    {
        // Use net amounts (gross - refunds) for pool calculation
        $netBank = $totals['bank']['total'] - ($totals['refunds']['bank'] ?? 0);
        $netCard = $totals['card']['total'] - ($totals['refunds']['card'] ?? 0);
        $netCash = $totals['cash']['total'] - ($totals['refunds']['cash'] ?? 0);

        $cashToUse = $netCash * ($this->cashPercent / 100);
        $poolTotal = $netBank + $netCard + $cashToUse;

        $exemptPercent = 100 - $this->bankTaxablePercent;

        // Calculate exempt and taxable separately for Bank+Card and Cash
        $bankCardTotal = $netBank + $netCard;
        $bankCardExempt = $bankCardTotal * ($exemptPercent / 100);
        $bankCardTaxable = $bankCardTotal * ($this->bankTaxablePercent / 100);
        
        $cashExempt = $cashToUse * ($exemptPercent / 100);
        $cashTaxable = $cashToUse * ($this->bankTaxablePercent / 100);
        
        // Total exempt and taxable
        $targetExempt = $bankCardExempt + $cashExempt;
        $targetTaxable = $bankCardTaxable + $cashTaxable;

        // Dynamic target range based on exempt percent (±2%)
        $exemptPercentDecimal = $exemptPercent / 100;
        $targetRangeMin = $exemptPercentDecimal - 0.02;
        $targetRangeMax = $exemptPercentDecimal + 0.02;

        return [
            'total' => $poolTotal,
            'exempt_percent' => $exemptPercent,
            'taxable_percent' => $this->bankTaxablePercent,
            'target_exempt' => $targetExempt,
            'target_taxable' => $targetTaxable,
            'target_range' => [
                'min' => $poolTotal * $targetRangeMin,
                'max' => $poolTotal * $targetRangeMax,
                'min_percent' => round($targetRangeMin * 100, 0),
                'max_percent' => round($targetRangeMax * 100, 0),
            ],
            'taxable_range' => [
                'min' => $poolTotal * ($this->bankTaxablePercent / 100 - 0.02),
                'max' => $poolTotal * ($this->bankTaxablePercent / 100 + 0.02),
                'min_percent' => round($this->bankTaxablePercent - 2, 0),
                'max_percent' => round($this->bankTaxablePercent + 2, 0),
            ],
        ];
    }

    /**
     * Get patient-wise payment breakdown
     */
    protected function getPatientPayments(array $totals, array $pool): array
    {
        $patientPayments = DB::table('package_advances')
            ->select(
                'patient_id',
                'payment_mode_id',
                DB::raw('SUM(cash_amount) as total_amount'),
                DB::raw('COUNT(*) as payment_count')
            )
            ->where('cash_flow', 'in')
            ->where('cash_amount', '>', 0)
            ->where('is_cancel', 0)
            ->whereIn('location_id', $this->locationIds)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->groupBy('patient_id', 'payment_mode_id')
            ->get();

        // Query patient-level refunds
        $patientRefunds = DB::table('package_advances')
            ->select(
                'patient_id',
                'payment_mode_id',
                DB::raw('SUM(cash_amount) as total_amount')
            )
            ->where('cash_flow', 'out')
            ->where('cash_amount', '>', 0)
            ->where('is_refund', 1)
            ->where('is_cancel', 0)
            ->whereIn('location_id', $this->locationIds)
            ->whereNull('deleted_at')
            ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
            ->groupBy('patient_id', 'payment_mode_id')
            ->get();

        // Build refund map: patient_id => [bank => x, card => y, cash => z]
        $refundMap = [];
        foreach ($patientRefunds as $refund) {
            $pid = $refund->patient_id;
            if (!isset($refundMap[$pid])) {
                $refundMap[$pid] = ['bank' => 0, 'card' => 0, 'cash' => 0];
            }
            if ($refund->payment_mode_id == self::PAYMENT_MODE_BANK) {
                $refundMap[$pid]['bank'] = (float) $refund->total_amount;
            } elseif ($refund->payment_mode_id == self::PAYMENT_MODE_CARD) {
                $refundMap[$pid]['card'] = (float) $refund->total_amount;
            } elseif ($refund->payment_mode_id == self::PAYMENT_MODE_CASH) {
                $refundMap[$pid]['cash'] = (float) $refund->total_amount;
            }
        }

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
                ];
            }

            if ($payment->payment_mode_id == self::PAYMENT_MODE_BANK) {
                $patients[$patientId]['bank_paid'] = (float) $payment->total_amount;
            } elseif ($payment->payment_mode_id == self::PAYMENT_MODE_CARD) {
                $patients[$patientId]['card_paid'] = (float) $payment->total_amount;
            } elseif ($payment->payment_mode_id == self::PAYMENT_MODE_CASH) {
                $patients[$patientId]['cash_paid'] = (float) $payment->total_amount;
            }
        }

        // Subtract patient-level refunds to get net amounts
        foreach ($patients as $patientId => &$data) {
            if (isset($refundMap[$patientId])) {
                $data['bank_paid'] = max(0, $data['bank_paid'] - $refundMap[$patientId]['bank']);
                $data['card_paid'] = max(0, $data['card_paid'] - $refundMap[$patientId]['card']);
                $data['cash_paid'] = max(0, $data['cash_paid'] - $refundMap[$patientId]['cash']);
            }
        }
        unset($data);

        // Calculate pool share for each patient (now using net amounts)
        foreach ($patients as $patientId => &$data) {
            $cashUsed = $data['cash_paid'] * ($this->cashPercent / 100);
            $poolShare = $data['bank_paid'] + $data['card_paid'] + $cashUsed;

            $data['cash_used'] = $cashUsed;
            $data['pool_share'] = $poolShare;
            $data['pool_percent'] = $pool['total'] > 0 ? ($poolShare / $pool['total']) * 100 : 0;
        }
        unset($data);

        // Sort by pool_share descending
        uasort($patients, function ($a, $b) {
            return $b['pool_share'] <=> $a['pool_share'];
        });

        return array_values($patients);
    }

    /**
     * Categorize patients into Capped, Medium, Small
     */
    protected function categorizePatients(array $patients): array
    {
        $capped = [];   // Pool share > max_exempt (58,500)
        $medium = [];   // Pool share > 30,000 and <= max_exempt
        $small = [];    // Pool share <= 30,000

        foreach ($patients as $patient) {
            $poolShare = $patient['pool_share'];

            if ($poolShare > $this->maxExemptPerPatient) {
                $patient['category'] = 'capped';
                $patient['max_exempt'] = $this->maxExemptPerPatient;
                $patient['max_exempt_percent'] = ($this->maxExemptPerPatient / $poolShare) * 100;
                $capped[] = $patient;
            } elseif ($poolShare > 30000) {
                $patient['category'] = 'medium';
                $patient['max_exempt'] = $poolShare;
                $patient['max_exempt_percent'] = 100;
                $medium[] = $patient;
            } else {
                $patient['category'] = 'small';
                $patient['max_exempt'] = $poolShare;
                $patient['max_exempt_percent'] = 100;
                $small[] = $patient;
            }
        }

        return [
            'capped' => $capped,
            'medium' => $medium,
            'small' => $small,
            'summary' => [
                'capped_count' => count($capped),
                'capped_pool' => array_sum(array_column($capped, 'pool_share')),
                'capped_max_exempt' => count($capped) * $this->maxExemptPerPatient,
                'medium_count' => count($medium),
                'medium_pool' => array_sum(array_column($medium, 'pool_share')),
                'medium_max_exempt' => array_sum(array_column($medium, 'pool_share')),
                'small_count' => count($small),
                'small_pool' => array_sum(array_column($small, 'pool_share')),
                'small_max_exempt' => array_sum(array_column($small, 'pool_share')),
            ],
        ];
    }

    /**
     * Check if target range is achievable
     */
    protected function checkFeasibility(array $categorized, array $pool): array
    {
        $summary = $categorized['summary'];

        $maxPossibleExempt = $summary['capped_max_exempt'] + $summary['medium_max_exempt'] + $summary['small_max_exempt'];
        $maxPossiblePercent = $pool['total'] > 0 ? ($maxPossibleExempt / $pool['total']) * 100 : 0;

        // Use dynamic target range minimum
        $minTargetPercent = $pool['target_range']['min_percent'];
        $isAchievable = $maxPossiblePercent >= $minTargetPercent;

        return [
            'max_possible_exempt' => $maxPossibleExempt,
            'max_possible_percent' => round($maxPossiblePercent, 2),
            'target_percent' => $pool['exempt_percent'],
            'target_range' => $minTargetPercent . '-' . $pool['target_range']['max_percent'] . '%',
            'is_achievable' => $isAchievable,
            'shortfall' => $isAchievable ? 0 : ($pool['target_range']['min'] - $maxPossibleExempt),
        ];
    }

    /**
     * Smart algorithm to distribute exempt percentages
     */
    protected function distributeExemptPercentages(array $categorized, array $pool, array $feasibility): array
    {
        $distribution = [];
        // Use dynamic exempt percent from pool
        $targetExempt = $pool['target_exempt'];

        // If not achievable, use max possible
        if (!$feasibility['is_achievable']) {
            $targetExempt = $feasibility['max_possible_exempt'];
        }

        // Step 1: Allocate capped patients (give them max exempt, rounded to multiples of consultation_amount)
        $cappedExempt = 0;
        foreach ($categorized['capped'] as $patient) {
            // Use greedy fitting to maximize exempt amount up to cap
            $fit = $this->fitDenominations(min($this->maxExemptPerPatient, $patient['pool_share']));
            $exemptAmount = $fit['total'];
            $taxableAmount = $patient['pool_share'] - $exemptAmount;
            $exemptPercent = ($exemptAmount / $patient['pool_share']) * 100;

            $distribution[] = [
                'patient_id' => $patient['patient_id'],
                'pool_share' => $patient['pool_share'],
                'category' => 'capped',
                'exempt_percent' => round($exemptPercent, 2),
                'exempt_amount' => $exemptAmount,
                'taxable_amount' => $taxableAmount,
            ];
            $cappedExempt += $exemptAmount;
        }

        // Step 2: Allocate small patients (give them 100% exempt intent)
        $smallExempt = 0;
        foreach ($categorized['small'] as $patient) {
            // Use greedy fitting on full pool_share
            $fit = $this->fitDenominations($patient['pool_share']);
            $exemptAmount = $fit['total'];
            $taxableAmount = $patient['pool_share'] - $exemptAmount;

            // If taxable remainder is less than 1000 and there are exempt invoices,
            // move the smallest exempt invoice to taxable
            $smallestDenom = min($this->consultationAmounts);
            if ($taxableAmount > 0 && $taxableAmount < 1000 && $exemptAmount >= $smallestDenom) {
                $exemptAmount -= $smallestDenom;
                $taxableAmount += $smallestDenom;
            }

            $distribution[] = [
                'patient_id' => $patient['patient_id'],
                'pool_share' => $patient['pool_share'],
                'category' => 'small',
                'exempt_percent' => 100,
                'exempt_amount' => $exemptAmount,
                'taxable_amount' => $taxableAmount,
            ];
            $smallExempt += $exemptAmount;
        }

        // Step 3: Calculate remaining for medium patients
        $remainingForMedium = $targetExempt - $cappedExempt - $smallExempt;
        $mediumPoolTotal = $categorized['summary']['medium_pool'];

        // Calculate required percentage for medium patients
        $mediumPercent = $mediumPoolTotal > 0 ? ($remainingForMedium / $mediumPoolTotal) * 100 : 0;
        $mediumPercent = min(100, max(0, $mediumPercent)); // Clamp between 0-100

        $mediumExempt = 0;
        foreach ($categorized['medium'] as $patient) {
            $rawExemptAmount = $patient['pool_share'] * ($mediumPercent / 100);

            // Use greedy fitting on the raw exempt target
            $fit = $this->fitDenominations($rawExemptAmount);
            $exemptAmount = $fit['total'];
            $taxableAmount = $patient['pool_share'] - $exemptAmount;

            $distribution[] = [
                'patient_id' => $patient['patient_id'],
                'pool_share' => $patient['pool_share'],
                'category' => 'medium',
                'exempt_percent' => round($mediumPercent, 2),
                'exempt_amount' => $exemptAmount,
                'taxable_amount' => $taxableAmount,
            ];
            $mediumExempt += $exemptAmount;
        }

        // Sort by pool_share descending
        usort($distribution, function ($a, $b) {
            return $b['pool_share'] <=> $a['pool_share'];
        });

        // Patients with exempt < smallest denomination get 0 exempt, full pool_share as taxable
        $smallestDenom = min($this->consultationAmounts);
        foreach ($distribution as &$patient) {
            if ($patient['exempt_amount'] < $smallestDenom) {
                $patient['exempt_amount'] = 0;
                $patient['exempt_percent'] = 0;
                $patient['taxable_amount'] = $patient['pool_share'];
            }
        }
        unset($patient);

        // Fine-tuning step: adjust individual medium patients one invoice at a time
        // to get total exempt as close to target as possible
        $totalExempt = array_sum(array_column($distribution, 'exempt_amount'));
        $diff = $totalExempt - $targetExempt;

        $adjustDenom = min($this->consultationAmounts);
        if ($diff > 0) {
            // Overshot: remove exempt invoices one at a time from patients
            // Priority: medium (smallest first) -> small (smallest first) -> capped (smallest first)
            $adjustIndices = [];
            foreach ($distribution as $i => $p) {
                if ($p['exempt_amount'] >= $adjustDenom) {
                    $adjustIndices[] = $i;
                }
            }
            // Sort by category priority (medium first, then small, then capped), then by pool_share ascending
            $categoryOrder = ['medium' => 0, 'small' => 1, 'capped' => 2];
            usort($adjustIndices, function ($a, $b) use ($distribution, $categoryOrder) {
                $catA = $categoryOrder[$distribution[$a]['category']] ?? 3;
                $catB = $categoryOrder[$distribution[$b]['category']] ?? 3;
                if ($catA !== $catB) return $catA <=> $catB;
                return $distribution[$a]['pool_share'] <=> $distribution[$b]['pool_share'];
            });

            foreach ($adjustIndices as $i) {
                if ($diff < $adjustDenom) break;
                while ($distribution[$i]['exempt_amount'] >= $adjustDenom && $diff >= $adjustDenom) {
                    $distribution[$i]['exempt_amount'] -= $adjustDenom;
                    $distribution[$i]['taxable_amount'] += $adjustDenom;
                    $diff -= $adjustDenom;
                }
            }
        } elseif ($diff < 0) {
            // Undershot: add exempt invoices one at a time to medium patients (largest pool_share first)
            $deficit = abs($diff);
            $mediumIndices = [];
            foreach ($distribution as $i => $p) {
                if ($p['category'] === 'medium') {
                    $mediumIndices[] = $i;
                }
            }
            usort($mediumIndices, function ($a, $b) use ($distribution) {
                return $distribution[$b]['pool_share'] <=> $distribution[$a]['pool_share'];
            });

            foreach ($mediumIndices as $i) {
                if ($deficit < $adjustDenom) break;
                $maxExempt = min(
                    $this->fitDenominations($distribution[$i]['pool_share'])['total'],
                    $this->maxExemptPerPatient
                );
                while ($distribution[$i]['exempt_amount'] < $maxExempt && $deficit >= $adjustDenom) {
                    $distribution[$i]['exempt_amount'] += $adjustDenom;
                    $distribution[$i]['taxable_amount'] -= $adjustDenom;
                    $deficit -= $adjustDenom;
                }
            }
        }

        // Recalculate exempt_percent after fine-tuning
        foreach ($distribution as &$patient) {
            if ($patient['pool_share'] > 0) {
                $patient['exempt_percent'] = round(($patient['exempt_amount'] / $patient['pool_share']) * 100, 2);
            }
        }
        unset($patient);

        return $distribution;
    }

    /**
     * Generate exempt invoices for each patient
     */
    protected function generateInvoices(array $distribution, string $type = 'exempt'): array
    {
        $invoices = [];
        $this->unplacedExemptPerPatient = [];
        $month = $this->dateFrom->format('m');

        foreach ($distribution as $patient) {
            $exemptAmount = $patient['exempt_amount'];
            $patientId = $patient['patient_id'];

            if ($exemptAmount < min($this->consultationAmounts)) {
                continue;
            }

            $fit = $this->fitDenominations($exemptAmount);
            $invoiceAmounts = $fit['amounts'];
            $numInvoices = count($invoiceAmounts);

            if ($numInvoices == 0) {
                continue;
            }

            $planId = DB::table('package_advances')
                ->where('patient_id', $patientId)
                ->where('cash_flow', 'in')
                ->where('is_cancel', 0)
                ->whereIn('location_id', $this->locationIds)
                ->whereNull('deleted_at')
                ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
                ->whereNotNull('package_id')
                ->value('package_id');

            $planId = $planId ?? 0;

            $patientDates = $this->getPatientInvoiceDates($numInvoices);

            $invoiceIndex = 0;
            foreach ($patientDates as $dateInfo) {
                $date = $dateInfo['date'];
                $preferredDateStr = $date->format('Y-m-d');
                $invoicesOnThisDay = $dateInfo['count'];

                for ($i = 0; $i < $invoicesOnThisDay && $invoiceIndex < $numInvoices; $i++) {
                    $amount = $invoiceAmounts[$invoiceIndex];

                    $actualDateStr = $this->findBestDateForInvoice($preferredDateStr, $amount, $patientId);
                    $invoiceNumber = $this->generateUniqueInvoiceNumber($patientId, $planId, $month);

                    $invoices[] = [
                        'invoice_number' => $invoiceNumber,
                        'patient_id' => $patientId,
                        'plan_id' => $planId,
                        'invoice_date' => $actualDateStr,
                        'amount' => $amount,
                        'type' => $type,
                    ];

                    $this->consumeDailyBudget($actualDateStr, $amount);
                    $this->trackPatientDayInvoice($patientId, $actualDateStr);
                    $invoiceIndex++;
                }
            }

            // Track unplaced exempt invoices — their amount will be added to taxable
            if ($invoiceIndex < $numInvoices) {
                $unplacedAmount = 0;
                for ($u = $invoiceIndex; $u < $numInvoices; $u++) {
                    $unplacedAmount += $invoiceAmounts[$u];
                }
                $this->unplacedExemptPerPatient[$patientId] = $unplacedAmount;
            }
        }

        usort($invoices, function ($a, $b) {
            return strcmp($a['invoice_date'], $b['invoice_date']);
        });

        return [
            'invoices' => $invoices,
            'unplaced_exempt' => $this->unplacedExemptPerPatient,
        ];
    }

    /**
     * Generate taxable invoices for each patient
     */
    protected function generateTaxableInvoices(array $distribution): array
    {
        $invoices = [];
        $month = $this->dateFrom->format('m');

        foreach ($distribution as $patient) {
            $patientId = $patient['patient_id'];
            $taxableAmount = $patient['taxable_amount'];

            // Add unplaced exempt amounts to taxable
            if (isset($this->unplacedExemptPerPatient[$patientId])) {
                $taxableAmount += $this->unplacedExemptPerPatient[$patientId];
            }

            if ($taxableAmount < 1) {
                continue;
            }

            $planId = DB::table('package_advances')
                ->where('patient_id', $patientId)
                ->where('cash_flow', 'in')
                ->where('is_cancel', 0)
                ->whereIn('location_id', $this->locationIds)
                ->whereNull('deleted_at')
                ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
                ->whereNotNull('package_id')
                ->value('package_id');

            $planId = $planId ?? 0;

            $invoiceAmounts = [];
            $remainingAmount = $taxableAmount;

            if ($taxableAmount < 1000) {
                $invoiceAmounts[] = round($taxableAmount, 2);
            } else {
                // For amounts >= 1000, generate random invoices between 1000-10000
                while ($remainingAmount >= 1000) {
                    $maxAmount = min(10000, $remainingAmount);
                    $amount = rand(1000, (int)$maxAmount);

                    // If this would leave less than 1000, add it to this invoice
                    if ($remainingAmount - $amount < 1000) {
                        $amount = $remainingAmount;
                    }

                    $invoiceAmounts[] = round($amount, 2);
                    $remainingAmount -= $amount;
                }
            }

            // If there's still a small remainder, add it to the last invoice
            if ($remainingAmount > 0 && count($invoiceAmounts) > 0) {
                $invoiceAmounts[count($invoiceAmounts) - 1] += round($remainingAmount, 2);
            } elseif ($remainingAmount > 0) {
                $invoiceAmounts[] = round($remainingAmount, 2);
            }

            // Get available dates for this patient (with 2-day gap for taxable)
            $patientDates = $this->getTaxableInvoiceDates(count($invoiceAmounts));

            $invoiceIndex = 0;
            foreach ($patientDates as $dateInfo) {
                $date = $dateInfo['date'];
                $preferredDateStr = $date->format('Y-m-d');
                $invoicesOnThisDay = $dateInfo['count'];

                for ($i = 0; $i < $invoicesOnThisDay && $invoiceIndex < count($invoiceAmounts); $i++) {
                    $amount = $invoiceAmounts[$invoiceIndex];

                    // Soft cap with spillover + per-patient-per-day cap
                    $actualDateStr = $this->findBestDateForInvoice($preferredDateStr, $amount, $patientId);

                    $invoiceNumber = $this->generateUniqueInvoiceNumber($patientId, $planId, $month);

                    $invoices[] = [
                        'invoice_number' => $invoiceNumber,
                        'patient_id' => $patientId,
                        'plan_id' => $planId,
                        'invoice_date' => $actualDateStr,
                        'amount' => $amount,
                        'type' => 'taxable',
                    ];
                    $this->consumeDailyBudget($actualDateStr, $amount);
                    $this->trackPatientDayInvoice($patientId, $actualDateStr);
                    $invoiceIndex++;
                }
            }

            // Safety net: if some taxable invoices couldn't be placed, merge into last placed invoice
            if ($invoiceIndex < count($invoiceAmounts) && $invoiceIndex > 0) {
                $unplacedSum = 0;
                for ($u = $invoiceIndex; $u < count($invoiceAmounts); $u++) {
                    $unplacedSum += $invoiceAmounts[$u];
                }
                $lastIdx = count($invoices) - 1;
                $invoices[$lastIdx]['amount'] += $unplacedSum;
            }
        }

        // Sort invoices by date (ascending)
        usort($invoices, function ($a, $b) {
            return strcmp($a['invoice_date'], $b['invoice_date']);
        });

        return $invoices;
    }

    protected function generateUniqueInvoiceNumber(int $patientId, int $planId, string $month): string
    {
        $prefix = sprintf('%d-%d-%s', $patientId, $planId, $month);

        if (!isset($this->usedInvoiceNumbers[$prefix])) {
            $this->usedInvoiceNumbers[$prefix] = 0;
        }
        $this->usedInvoiceNumbers[$prefix]++;

        return sprintf('%s-%d', $prefix, $this->usedInvoiceNumbers[$prefix]);
    }

    /**
     * Convert number to alphabetic format (1=A, 2=B... 26=Z, 27=AA, 28=AB...)
     */
    protected function numberToAlpha(int $number): string
    {
        $alpha = '';

        while ($number > 0) {
            $number--;
            $alpha = chr(65 + ($number % 26)) . $alpha;
            $number = intdiv($number, 26);
        }

        return $alpha;
    }

    /**
     * Get invoice dates for a patient using revenue-weighted distribution.
     * Days with higher revenue get proportionally more invoices.
     * Maintains 1-day minimum gap between invoice days for the same patient.
     */
    protected function getPatientInvoiceDates(int $numInvoices): array
    {
        return $this->getRevenueWeightedDates($numInvoices, 1);
    }

    /**
     * Get invoice dates for taxable invoices using revenue-weighted distribution.
     * Maintains 2-day minimum gap between invoice days for the same patient.
     */
    protected function getTaxableInvoiceDates(int $numInvoices): array
    {
        return $this->getRevenueWeightedDates($numInvoices, 2);
    }

    /**
     * Core revenue-weighted date distribution algorithm.
     * Distributes $numInvoices across working days proportionally to daily revenue,
     * while maintaining a minimum gap of $minGapDays between invoice days per patient.
     *
     * @param int $numInvoices Total invoices to distribute
     * @param int $minGapDays Minimum gap in working days between invoice days (1 for exempt, 2 for taxable)
     * @return array Array of ['date' => Carbon, 'count' => int]
     */
    protected function getRevenueWeightedDates(int $numInvoices, int $minGapDays): array
    {
        $dates = [];
        $totalWorkingDays = count($this->workingDays);

        if ($numInvoices == 0 || $totalWorkingDays == 0) {
            return $dates;
        }

        $maxPerDay = $this->maxInvoicesPerDay;

        // Build weighted capacity per working day index
        $dayCapacity = [];
        $totalWeight = 0;
        foreach ($this->workingDays as $i => $day) {
            $dateStr = $day->format('Y-m-d');
            $weight = 0;
            if (isset($this->dailyRevenue[$dateStr])) {
                $weight = $this->dailyRevenue[$dateStr]['weight'];
            }
            $dayCapacity[$i] = [
                'date' => $day,
                'weight' => $weight,
                'allocated' => 0,
            ];
            $totalWeight += $weight;
        }

        // If no revenue weights available, fall back to equal distribution
        if ($totalWeight == 0) {
            foreach ($dayCapacity as $i => &$dc) {
                $dc['weight'] = 1.0 / $totalWorkingDays;
            }
            unset($dc);
        }

        // Step 1: Calculate proportional allocation per day (floored)
        $invoicesRemaining = $numInvoices;
        foreach ($dayCapacity as $i => &$dc) {
            $raw = $numInvoices * $dc['weight'];
            // Round down to nearest multiple that respects maxPerDay
            $dc['allocated'] = min($maxPerDay, (int) floor($raw));
            $invoicesRemaining -= $dc['allocated'];
        }
        unset($dc);

        // Step 2: Distribute remaining invoices to days with highest fractional remainder
        if ($invoicesRemaining > 0) {
            // Calculate fractional remainders
            $remainders = [];
            foreach ($dayCapacity as $i => $dc) {
                $raw = $numInvoices * $dc['weight'];
                $fractional = $raw - $dc['allocated'];
                if ($dc['allocated'] < $maxPerDay) {
                    $remainders[$i] = $fractional;
                }
            }
            arsort($remainders);

            foreach ($remainders as $i => $frac) {
                if ($invoicesRemaining <= 0) break;
                $canAdd = min($invoicesRemaining, $maxPerDay - $dayCapacity[$i]['allocated']);
                $dayCapacity[$i]['allocated'] += $canAdd;
                $invoicesRemaining -= $canAdd;
            }
        }

        // Step 3: If still remaining (very high invoice count), fill any day up to maxPerDay
        if ($invoicesRemaining > 0) {
            foreach ($dayCapacity as $i => &$dc) {
                if ($invoicesRemaining <= 0) break;
                $canAdd = $maxPerDay - $dc['allocated'];
                if ($canAdd > 0) {
                    $add = min($invoicesRemaining, $canAdd);
                    $dc['allocated'] += $add;
                    $invoicesRemaining -= $add;
                }
            }
            unset($dc);
        }

        // Step 4: Enforce minimum gap rule per patient
        // Pick days with allocations, then thin out days that are too close
        $selectedDays = [];
        foreach ($dayCapacity as $i => $dc) {
            if ($dc['allocated'] > 0) {
                $selectedDays[$i] = $dc;
            }
        }

        // Sort by index (chronological)
        ksort($selectedDays);

        // Enforce gap: if two selected days are within minGapDays, remove the one with lower weight
        $finalDays = [];
        $lastSelectedIndex = -999;
        foreach ($selectedDays as $i => $dc) {
            if (($i - $lastSelectedIndex) > $minGapDays) {
                // Gap is satisfied
                $finalDays[$i] = $dc;
                $lastSelectedIndex = $i;
            } else {
                // Too close — redistribute these invoices to nearby valid days later
                $invoicesRemaining += $dc['allocated'];
            }
        }

        // Redistribute gap-displaced invoices to valid days
        if ($invoicesRemaining > 0) {
            foreach ($dayCapacity as $i => $dc) {
                if ($invoicesRemaining <= 0) break;
                if (isset($finalDays[$i])) continue; // Already selected

                // Check gap against all selected days
                $gapOk = true;
                foreach (array_keys($finalDays) as $si) {
                    if (abs($i - $si) <= $minGapDays) {
                        $gapOk = false;
                        break;
                    }
                }

                if ($gapOk && $dc['weight'] > 0) {
                    $add = min($invoicesRemaining, $maxPerDay);
                    $finalDays[$i] = [
                        'date' => $dc['date'],
                        'weight' => $dc['weight'],
                        'allocated' => $add,
                    ];
                    $invoicesRemaining -= $add;
                    // Re-sort to maintain order for gap checks
                    ksort($finalDays);
                }
            }
        }

        // Step 5: Build output array
        ksort($finalDays);
        foreach ($finalDays as $i => $dc) {
            if ($dc['allocated'] > 0) {
                $dates[] = [
                    'date' => $dc['date'],
                    'count' => $dc['allocated'],
                ];
            }
        }

        return $dates;
    }

    /**
     * Calculate final summary
     */
    protected function calculateSummary(array $distribution, array $exemptInvoices, array $taxableInvoices, array $pool): array
    {
        $totalExemptAmount = array_sum(array_column($distribution, 'exempt_amount'));
        $totalTaxableAmount = array_sum(array_column($distribution, 'taxable_amount'));
        
        $totalExemptInvoiced = array_sum(array_column($exemptInvoices, 'amount'));
        $totalTaxableInvoiced = array_sum(array_column($taxableInvoices, 'amount'));

        // Calculate remainders (amounts that couldn't become invoices)
        $exemptRemainder = $totalExemptAmount - $totalExemptInvoiced;
        $taxableRemainder = $totalTaxableAmount - $totalTaxableInvoiced;

        return [
            'total_patients' => count($distribution),
            'total_pool' => $pool['total'],
            'total_exempt_calculated' => round($totalExemptAmount, 2),
            'total_exempt_invoiced' => $totalExemptInvoiced,
            'exempt_remainder' => round($exemptRemainder, 2),
            'total_taxable_calculated' => round($totalTaxableAmount, 2),
            'total_taxable_invoiced' => $totalTaxableInvoiced,
            'taxable_remainder' => round($taxableRemainder, 2),
            'exempt_percent' => $pool['total'] > 0 ? round(($totalExemptInvoiced / $pool['total']) * 100, 2) : 0,
            'taxable_percent' => $pool['total'] > 0 ? round(($totalTaxableInvoiced / $pool['total']) * 100, 2) : 0,
            'total_exempt_invoices' => count($exemptInvoices),
            'total_taxable_invoices' => count($taxableInvoices),
            'total_invoices' => count($exemptInvoices) + count($taxableInvoices),
            'verification' => [
                'pool_total' => $pool['total'],
                'total_invoiced' => $totalExemptInvoiced + $totalTaxableInvoiced,
                'total_remainders' => $exemptRemainder + $taxableRemainder,
                'sum' => $totalExemptInvoiced + $totalTaxableInvoiced + $exemptRemainder + $taxableRemainder,
                'match' => abs($pool['total'] - ($totalExemptInvoiced + $totalTaxableInvoiced + $exemptRemainder + $taxableRemainder)) < 1,
            ],
        ];
    }
}