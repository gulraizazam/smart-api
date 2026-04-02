<?php

declare(strict_types=1);

namespace App\Services\Reports\Revenue;

use App\Models\Locations;
use App\Models\PackageAdvances;
use Illuminate\Support\Facades\Auth;

class GenderWiseRevenueReport
{
    /**
     * Generate gender-wise revenue report.
     *
     * Extracted from FinanceReportController::revenueByGenderAndService()
     * Business logic preserved exactly.
     */
    public function generate(
        ?string $startDate,
        ?string $endDate,
        ?array $locationIds,
    ): array {
        $accountId = Auth::user()->account_id;

        // Remove empty values
        $locationIds = $locationIds ? array_filter($locationIds) : [];

        $query = PackageAdvances::with([
            'package.appointment' => fn ($q) => $q->where('appointment_type_id', 1),
            'package.appointment.service',
            'package.appointment.patient:id,gender',
        ])
            ->whereHas('package.appointment', fn ($q) => $q->where('appointment_type_id', 1))
            ->whereDate('created_at', '>=', $startDate)
            ->whereDate('created_at', '<=', $endDate)
            ->where('account_id', $accountId)
            ->where('cash_flow', 'in')
            ->where('is_adjustment', '0')
            ->where('is_tax', '0')
            ->where('is_cancel', '0')
            ->where('cash_amount', '>', 0);

        if (! empty($locationIds)) {
            $query->whereHas('package', fn ($q) => $q->whereIn('location_id', $locationIds));
        }

        $advances = $query->orderBy('created_at', 'asc')->get();

        $reportData = [];

        foreach ($advances as $advance) {
            if (! $advance->package || ! $advance->package->appointment) {
                continue;
            }

            $appointment = $advance->package->appointment;

            if (! $appointment->service) {
                continue;
            }

            $service = $appointment->service;
            $patient = $appointment->patient;

            $gender = 'unknown';
            if ($patient?->gender == 1) {
                $gender = 'male';
            } elseif ($patient?->gender == 2) {
                $gender = 'female';
            }

            $serviceId = $service->id;

            if (! isset($reportData[$serviceId])) {
                $reportData[$serviceId] = [
                    'id' => $serviceId,
                    'name' => $service->name,
                    'male_revenue' => 0,
                    'female_revenue' => 0,
                    'unknown_gender_revenue' => 0,
                    'total_revenue' => 0,
                    'male_count' => 0,
                    'female_count' => 0,
                    'unknown_gender_count' => 0,
                    'total_count' => 0,
                ];
            }

            $amount = $advance->cash_amount;

            if ($gender === 'male') {
                $reportData[$serviceId]['male_revenue'] += $amount;
                $reportData[$serviceId]['male_count']++;
            } elseif ($gender === 'female') {
                $reportData[$serviceId]['female_revenue'] += $amount;
                $reportData[$serviceId]['female_count']++;
            } else {
                $reportData[$serviceId]['unknown_gender_revenue'] += $amount;
                $reportData[$serviceId]['unknown_gender_count']++;
            }

            $reportData[$serviceId]['total_revenue'] += $amount;
            $reportData[$serviceId]['total_count']++;
        }

        // Sort by service name
        uasort($reportData, fn ($a, $b) => strcmp($a['name'], $b['name']));

        // Get location names for display
        $selectedLocations = [];
        if (! empty($locationIds)) {
            $selectedLocations = Locations::whereIn('id', $locationIds)->pluck('name', 'id')->toArray();
        }

        return [
            'reportData' => $reportData,
            'isLocationWise' => false,
            'selectedLocations' => $selectedLocations,
            'locationIds' => $locationIds,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }
}
