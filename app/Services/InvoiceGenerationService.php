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
    protected $maxExemptPerPatient;
    protected $workingDays = [];
    protected $usedInvoiceNumbers = [];

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
        $this->consultationAmount = $params['consultation_amount']; // e.g., 1500
        $this->usedInvoiceNumbers = [];
        // Step 1: Calculate working days and max capacity
        $this->calculateWorkingDays();
        $maxInvoicesPerPatient = $this->calculateMaxInvoicesPerPatient();
        $this->maxExemptPerPatient = $maxInvoicesPerPatient * $this->consultationAmount;

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

        // Step 8: Generate exempt invoices
        $exemptInvoices = $this->generateInvoices($distribution, 'exempt');

        // Step 9: Generate taxable invoices
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
            ],
            'capacity' => [
                'working_days' => count($this->workingDays),
                'invoice_days_per_patient' => floor(count($this->workingDays) / 2), // with 1-day gap
                'max_invoices_per_patient' => $maxInvoicesPerPatient,
                'max_exempt_per_patient' => $this->maxExemptPerPatient,
            ],
            'totals' => $totals,
            'pool' => $pool,
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
     * Calculate maximum invoices per patient based on date range
     */
    protected function calculateMaxInvoicesPerPatient(): int
    {
        $totalWorkingDays = count($this->workingDays);
        
        // With 1-day gap, usable days = floor(working_days / 2)
        $usableInvoiceDays = floor($totalWorkingDays / 2);
        
        // Max 3 invoices per day
        return $usableInvoiceDays * 3;
    }

    /**
     * Get total payments by payment method
     */
    protected function getPaymentTotals(): array
    {
        $results = DB::table('plan_invoices')
            ->select(
                'payment_mode_id',
                DB::raw('SUM(total_price) as total_amount'),
                DB::raw('COUNT(*) as record_count')
            )
            ->where('total_price', '>', 0)
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
            'bank_plus_card' => $bankTotal + $cardTotal,
            'grand_total' => $bankTotal + $cardTotal + $cashTotal,
        ];
    }

    /**
     * Calculate the pool (Bank + Card + Cash%)
     */
    protected function calculatePool(array $totals): array
    {
        $cashToUse = $totals['cash']['total'] * ($this->cashPercent / 100);
        $poolTotal = $totals['bank']['total'] + $totals['card']['total'] + $cashToUse;

        $exemptPercent = 100 - $this->bankTaxablePercent;

        // Calculate exempt and taxable separately for Bank+Card and Cash
        $bankCardTotal = $totals['bank']['total'] + $totals['card']['total'];
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
        $patientPayments = DB::table('plan_invoices')
            ->select(
                'patient_id',
                'payment_mode_id',
                DB::raw('SUM(total_price) as total_amount'),
                DB::raw('COUNT(*) as payment_count')
            )
            ->where('total_price', '>', 0)
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

        // Calculate pool share for each patient
        foreach ($patients as $patientId => &$data) {
            $cashUsed = $data['cash_paid'] * ($this->cashPercent / 100);
            $poolShare = $data['bank_paid'] + $data['card_paid'] + $cashUsed;

            $data['cash_used'] = $cashUsed;
            $data['pool_share'] = $poolShare;
            $data['pool_percent'] = $pool['total'] > 0 ? ($poolShare / $pool['total']) * 100 : 0;
        }

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

        // Step 1: Allocate capped patients (give them max: 58,500)
        $cappedExempt = 0;
        foreach ($categorized['capped'] as $patient) {
            $exemptAmount = $this->maxExemptPerPatient;
            $exemptPercent = ($exemptAmount / $patient['pool_share']) * 100;
            
            $distribution[] = [
                'patient_id' => $patient['patient_id'],
                'pool_share' => $patient['pool_share'],
                'category' => 'capped',
                'exempt_percent' => round($exemptPercent, 2),
                'exempt_amount' => $exemptAmount,
                'taxable_amount' => $patient['pool_share'] - $exemptAmount,
            ];
            $cappedExempt += $exemptAmount;
        }

       // Step 2: Allocate small patients (give them 100% exempt intent)
        $smallExempt = 0;
        foreach ($categorized['small'] as $patient) {
            // Calculate how many invoices can be created (each = consultation_amount)
            $numInvoices = floor($patient['pool_share'] / $this->consultationAmount);
            $exemptAmount = $numInvoices * $this->consultationAmount;
            $taxableAmount = $patient['pool_share'] - $exemptAmount;
            
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
            $exemptAmount = $patient['pool_share'] * ($mediumPercent / 100);
            
            $distribution[] = [
                'patient_id' => $patient['patient_id'],
                'pool_share' => $patient['pool_share'],
                'category' => 'medium',
                'exempt_percent' => round($mediumPercent, 2),
                'exempt_amount' => round($exemptAmount, 2),
                'taxable_amount' => round($patient['pool_share'] - $exemptAmount, 2),
            ];
            $mediumExempt += $exemptAmount;
        }

        // Sort by pool_share descending
        usort($distribution, function ($a, $b) {
            return $b['pool_share'] <=> $a['pool_share'];
        });

        // Filter out patients whose exempt amount is less than consultation amount
        $distribution = array_filter($distribution, function ($patient) {
            return $patient['exempt_amount'] >= $this->consultationAmount;
        });

        // Re-index array after filtering
        return array_values($distribution);
    }

    /**
     * Generate exempt invoices for each patient
     */
    protected function generateInvoices(array $distribution, string $type = 'exempt'): array
    {
        $invoices = [];
        // Get month from date range (use start date)
        $month = $this->dateFrom->format('m');

        foreach ($distribution as $patient) {
            $exemptAmount = $patient['exempt_amount'];
            $patientId = $patient['patient_id'];
            
            // Calculate number of invoices (each invoice = consultation_amount)
            $numInvoices = floor($exemptAmount / $this->consultationAmount);
            $remainder = $exemptAmount - ($numInvoices * $this->consultationAmount);

            if ($numInvoices == 0) {
                continue;
            }

            // Get patient's plan_id from plan_invoices table
            $planId = DB::table('plan_invoices')
                ->where('patient_id', $patientId)
                ->whereIn('location_id', $this->locationIds)
                ->whereNull('deleted_at')
                ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
                ->value('package_id');

            // If no plan_id found, use 0 as default
            $planId = $planId ?? 0;

            // Get available dates for this patient (with 1-day gap)
            $patientDates = $this->getPatientInvoiceDates($numInvoices);

            $invoiceIndex = 0;
            foreach ($patientDates as $dateInfo) {
                $date = $dateInfo['date'];
                $invoicesOnThisDay = $dateInfo['count'];

                for ($i = 0; $i < $invoicesOnThisDay && $invoiceIndex < $numInvoices; $i++) {
                    // Generate unique random number (1-999) for each invoice
                    $randomNum = rand(1, 999);
                    
                    // Format: patientID-planID-month-random
                    $invoiceNumber = $this->generateUniqueInvoiceNumber($patientId, $planId, $month);
    
                    $invoices[] = [
                        
                        'invoice_number' => $invoiceNumber,
                        'patient_id' => $patientId,
                        'plan_id' => $planId,
                        'invoice_date' => $date->format('Y-m-d'),
                        'amount' => $this->consultationAmount,
                        'type' => $type,
                    ];
                    
                    $invoiceIndex++;
                }
            }
        }

        // Sort invoices by date (ascending)
        usort($invoices, function ($a, $b) {
            return strcmp($a['invoice_date'], $b['invoice_date']);
        });

        return $invoices;
    }

    /**
     * Generate taxable invoices for each patient
     */
   protected function generateTaxableInvoices(array $distribution): array
    {
        $invoices = [];
        // Get month from date range (use start date)
        $month = $this->dateFrom->format('m');

        foreach ($distribution as $patient) {
            $taxableAmount = $patient['taxable_amount'];
            $patientId = $patient['patient_id'];
            
            // Skip if taxable amount is less than minimum (100)
            if ($taxableAmount < 100) {
                continue;
            }

            // Get patient's plan_id from plan_invoices table
            $planId = DB::table('plan_invoices')
                ->where('patient_id', $patientId)
                ->whereIn('location_id', $this->locationIds)
                ->whereNull('deleted_at')
                ->whereBetween('created_at', [$this->dateFrom, $this->dateTo])
                ->value('package_id');

           // If no plan_id found, use 0 as default
            $planId = $planId ?? 0;

            // Generate invoice amounts
            $invoiceAmounts = [];
            
            // If taxable amount is less than 1000, create single invoice with full amount
            if ($taxableAmount < 1000) {
                $invoiceAmounts[] = $taxableAmount;
            } else {
                // For amounts >= 1000, generate random invoices between 1000-10000
                $remainingAmount = $taxableAmount;
                
                while ($remainingAmount >= 1000) {
                    // Random amount between 1000 and min(10000, remainingAmount)
                    $maxAmount = min(10000, $remainingAmount);
                    $amount = rand(1000, (int)$maxAmount);
                    
                    // If this would leave less than 1000, add it to this invoice
                    if ($remainingAmount - $amount < 1000) {
                        $amount = $remainingAmount;
                    }
                    
                    $invoiceAmounts[] = $amount;
                    $remainingAmount -= $amount;
                }
            }
            
            // If there's still a small remainder, add it to the last invoice
            if ($remainingAmount > 0 && count($invoiceAmounts) > 0) {
                $invoiceAmounts[count($invoiceAmounts) - 1] += $remainingAmount;
            } elseif ($remainingAmount > 0) {
                // If no invoices yet, create one with the full amount
                $invoiceAmounts[] = $taxableAmount;
            }

            // Get available dates for this patient (with 2-day gap for taxable)
            $patientDates = $this->getTaxableInvoiceDates(count($invoiceAmounts));

            $invoiceIndex = 0;
            foreach ($patientDates as $dateInfo) {
                $date = $dateInfo['date'];
                $invoicesOnThisDay = $dateInfo['count'];

                for ($i = 0; $i < $invoicesOnThisDay && $invoiceIndex < count($invoiceAmounts); $i++) {
                    // Generate unique random number (1-999) for each invoice
                    $randomNum = rand(1, 999);
                    
                    // Format: patientID-planID-month-random
                    $invoiceNumber = $this->generateUniqueInvoiceNumber($patientId, $planId, $month);
    
                    $invoices[] = [
                        'invoice_number' => $invoiceNumber,
                        'patient_id' => $patientId,
                        'plan_id' => $planId,
                        'invoice_date' => $date->format('Y-m-d'),
                        'amount' => $invoiceAmounts[$invoiceIndex],
                        'type' => 'taxable',
                    ];
                    $invoiceIndex++;
                }
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
    $maxAttempts = 100; // Prevent infinite loop
    $attempts = 0;
    
    do {
        $randomNum = rand(1, 999);
        $invoiceNumber = sprintf('%d-%d-%s-%d', $patientId, $planId, $month, $randomNum);
        $attempts++;
        
        if ($attempts >= $maxAttempts) {
            // Fallback: use timestamp-based unique number
            $randomNum = (int) (microtime(true) * 1000) % 999 + 1;
            $invoiceNumber = sprintf('%d-%d-%s-%d', $patientId, $planId, $month, $randomNum);
            break;
        }
    } while (in_array($invoiceNumber, $this->usedInvoiceNumbers));
    
    // Mark this number as used
    $this->usedInvoiceNumbers[] = $invoiceNumber;
    
    return $invoiceNumber;
}
    /**
     * Convert number to alphabetic format (1=A, 2=B... 26=Z, 27=AA, 28=AB...)
     */
    protected function numberToAlpha(int $number): string
    {
        $alpha = '';
        
        while ($number > 0) {
            $number--; // Adjust for 0-based indexing
            $alpha = chr(65 + ($number % 26)) . $alpha;
            $number = intdiv($number, 26);
        }
        
        return $alpha;
    }

    /**
     * Get invoice dates for a patient with 1-day gap rule
     */
    protected function getPatientInvoiceDates(int $numInvoices): array
    {
        $dates = [];
        $totalWorkingDays = count($this->workingDays);
        
        if ($numInvoices == 0 || $totalWorkingDays == 0) {
            return $dates;
        }

        // Determine strategy based on invoice count
        if ($numInvoices > 20) {
            // High count: Aggressive - 3 per day, 1-day gap
            $invoicesPerDay = 3;
            $dayGap = 2; // Index increment (1-day gap)
        } elseif ($numInvoices >= 10) {
            // Medium count: Moderate - 2 per day, 2-3 day gap
            $invoicesPerDay = 2;
            $dayGap = 3; // Index increment (2-day gap)
        } else {
            // Low count: Conservative - spread across month
            $invoicesPerDay = 1;
            // Calculate spacing to spread across the month
            $dayGap = max(2, floor($totalWorkingDays / $numInvoices));
        }

        $invoicesRemaining = $numInvoices;
        $workingDayIndex = 0;

        // First pass: distribute with calculated strategy
        while ($invoicesRemaining > 0 && $workingDayIndex < $totalWorkingDays) {
            $invoicesOnThisDay = min($invoicesPerDay, $invoicesRemaining);
            
            $dates[] = [
                'date' => $this->workingDays[$workingDayIndex],
                'count' => $invoicesOnThisDay,
            ];
            
            $invoicesRemaining -= $invoicesOnThisDay;
            $workingDayIndex += $dayGap;
        }

        // Second pass: if still have invoices, fill in gaps with minimum spacing
        if ($invoicesRemaining > 0) {
            $workingDayIndex = 1; // Start from first skipped day
            while ($invoicesRemaining > 0 && $workingDayIndex < $totalWorkingDays) {
                // Check if this day is already used
                $dayAlreadyUsed = false;
                foreach ($dates as $existingDate) {
                    if ($existingDate['date']->format('Y-m-d') === $this->workingDays[$workingDayIndex]->format('Y-m-d')) {
                        $dayAlreadyUsed = true;
                        break;
                    }
                }
                
                if (!$dayAlreadyUsed) {
                    $invoicesOnThisDay = min(3, $invoicesRemaining); // Don't exceed 3 per day
                    
                    $dates[] = [
                        'date' => $this->workingDays[$workingDayIndex],
                        'count' => $invoicesOnThisDay,
                    ];
                    
                    $invoicesRemaining -= $invoicesOnThisDay;
                }
                
                $workingDayIndex++;
            }
        }

        // Sort dates chronologically
        usort($dates, function ($a, $b) {
            return $a['date'] <=> $b['date'];
        });

        return $dates;
    }

    /**
     * Get invoice dates for taxable invoices with 2-day minimum gap
     */
    protected function getTaxableInvoiceDates(int $numInvoices): array
    {
        $dates = [];
        $totalWorkingDays = count($this->workingDays);
        
        if ($numInvoices == 0 || $totalWorkingDays == 0) {
            return $dates;
        }

        // Determine strategy based on invoice count - with 2-day minimum gap
        if ($numInvoices > 20) {
            // High count: Aggressive - 3 per day, 2-day gap
            $invoicesPerDay = 3;
            $dayGap = 3; // Index increment (2-day gap)
        } elseif ($numInvoices >= 10) {
            // Medium count: Moderate - 2 per day, 3-4 day gap
            $invoicesPerDay = 2;
            $dayGap = 4; // Index increment (3-day gap)
        } else {
            // Low count: Conservative - spread across month
            $invoicesPerDay = 1;
            // Calculate spacing to spread across the month (minimum 3 for 2-day gap)
            $dayGap = max(3, floor($totalWorkingDays / $numInvoices));
        }

        $invoicesRemaining = $numInvoices;
        $workingDayIndex = 0;

        // First pass: distribute with calculated strategy
        while ($invoicesRemaining > 0 && $workingDayIndex < $totalWorkingDays) {
            $invoicesOnThisDay = min($invoicesPerDay, $invoicesRemaining);
            
            $dates[] = [
                'date' => $this->workingDays[$workingDayIndex],
                'count' => $invoicesOnThisDay,
            ];
            
            $invoicesRemaining -= $invoicesOnThisDay;
            $workingDayIndex += $dayGap;
        }

        // Second pass: if still have invoices, fill in gaps with minimum 2-day spacing
        if ($invoicesRemaining > 0) {
            $workingDayIndex = 2; // Start from 2 days after first (2-day gap)
            while ($invoicesRemaining > 0 && $workingDayIndex < $totalWorkingDays) {
                // Check if this day is already used
                $dayAlreadyUsed = false;
                foreach ($dates as $existingDate) {
                    if ($existingDate['date']->format('Y-m-d') === $this->workingDays[$workingDayIndex]->format('Y-m-d')) {
                        $dayAlreadyUsed = true;
                        break;
                    }
                }
                
                if (!$dayAlreadyUsed) {
                    $invoicesOnThisDay = min(3, $invoicesRemaining); // Don't exceed 3 per day
                    
                    $dates[] = [
                        'date' => $this->workingDays[$workingDayIndex],
                        'count' => $invoicesOnThisDay,
                    ];
                    
                    $invoicesRemaining -= $invoicesOnThisDay;
                }
                
                $workingDayIndex++;
            }
        }

        // Sort dates chronologically
        usort($dates, function ($a, $b) {
            return $a['date'] <=> $b['date'];
        });

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