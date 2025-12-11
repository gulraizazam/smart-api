# Invoice Generation Changes Summary

## Changes Required in `app/Services/InvoiceGenerationService.php`

### Change 1: Update `generateTaxableInvoices()` method (Lines 508-565)

Replace the entire `generateTaxableInvoices()` method with the version that generates random amounts between 1000-10000.

**Key Changes:**
- Skip if taxable amount < 1000
- Generate random invoice amounts (1000-10000)
- Ensure no remainder < 1000 (add to last invoice)
- Use `count($invoiceAmounts)` instead of fixed count

### Change 2: Update `getPatientInvoiceDates()` method (Lines 583-627)

Replace the entire `getPatientInvoiceDates()` method with intelligent distribution logic.

**Key Changes:**
- High count (>20 invoices): 3 per day, 1-day gap (aggressive)
- Medium count (10-20 invoices): 2 per day, 2-3 day gap (moderate)
- Low count (<10 invoices): 1 per day, spread across month (conservative)

## Complete Method Code

### generateTaxableInvoices() - COMPLETE REPLACEMENT

```php
    /**
     * Generate taxable invoices for each patient with random amounts (1000-10000)
     */
    protected function generateTaxableInvoices(array $distribution): array
    {
        $invoices = [];

        foreach ($distribution as $patient) {
            $taxableAmount = $patient['taxable_amount'];
            $patientId = $patient['patient_id'];
            
            // Skip if taxable amount is less than minimum (1000)
            if ($taxableAmount < 1000) {
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

            // Generate random invoice amounts between 1000-10000
            $remainingAmount = $taxableAmount;
            $invoiceAmounts = [];
            
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

            // Get available dates for this patient
            $patientDates = $this->getPatientInvoiceDates(count($invoiceAmounts));

            $invoiceIndex = 0;
            $increment = 1;
            foreach ($patientDates as $dateInfo) {
                $date = $dateInfo['date'];
                $invoicesOnThisDay = $dateInfo['count'];

                for ($i = 0; $i < $invoicesOnThisDay && $invoiceIndex < count($invoiceAmounts); $i++) {
                    // Format: patientID-planID-increment
                    $invoiceNumber = sprintf('%d-%d-%d', $patientId, $planId, $increment);
                    
                    $invoices[] = [
                        'invoice_number' => $invoiceNumber,
                        'patient_id' => $patientId,
                        'plan_id' => $planId,
                        'invoice_date' => $date->format('Y-m-d'),
                        'amount' => $invoiceAmounts[$invoiceIndex],
                        'type' => 'taxable',
                    ];
                    $increment++;
                    $invoiceIndex++;
                }
            }
        }

        return $invoices;
    }
```

### getPatientInvoiceDates() - COMPLETE REPLACEMENT

```php
    /**
     * Get invoice dates for a patient with intelligent distribution
     * - High count (>20): 3 invoices/day, 1-day gap (aggressive)
     * - Medium count (10-20): 2 invoices/day, 2-3 day gap (moderate)
     * - Low count (<10): 1 invoice/day, spread across month (conservative)
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
```

## Manual Application Steps

1. Open `app/Services/InvoiceGenerationService.php`
2. Find the `generateTaxableInvoices()` method (around line 508)
3. Replace the entire method with the code above
4. Find the `getPatientInvoiceDates()` method (around line 583)
5. Replace the entire method with the code above
6. Save the file
7. Run: `php -l app/Services/InvoiceGenerationService.php` to verify syntax

## What This Achieves

✅ Taxable invoices have random amounts (1000-10000)
✅ High-volume patients: Aggressive scheduling (3/day, tight gaps)
✅ Medium-volume patients: Moderate scheduling (2/day, medium gaps)
✅ Low-volume patients: Spread across month (1/day, wide gaps)
✅ Never exceeds 3 invoices per day
✅ Always maintains minimum 1-day gap
