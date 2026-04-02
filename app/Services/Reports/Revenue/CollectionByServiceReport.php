<?php

declare(strict_types=1);

namespace App\Services\Reports\Revenue;

use App\Helpers\ACL;
use App\Models\Appointments;
use App\Models\Bundles;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\Packages;
use App\Models\PackageBundles;
use App\Models\PackageService;
use App\Models\Services;
use Carbon\Carbon;

class CollectionByServiceReport
{
    /**
     * Generate collection by service report.
     *
     * Extracted from App\Reports\Invoices::collectionbyservice()
     * Business logic preserved exactly.
     */
    public function generate(
        ?string $startDate,
        ?string $endDate,
        ?array $locationIds,
        ?string $regionId,
        int $accountId,
    ): array {
        $resolvedLocationIds = $this->resolveLocationIds($locationIds, $regionId);
        $locationInfo = Locations::whereIn('id', $resolvedLocationIds)->get();
        $reportData = [];

        foreach ($locationInfo as $location) {
            $advances = PackageAdvances::whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->where('account_id', $accountId)
                ->where('location_id', $location->id)
                ->orderBy('created_at', 'asc')
                ->get();

            if ($advances->isEmpty()) {
                continue;
            }

            $packageIds = [];
            $bundles = [];

            foreach ($advances as $advance) {
                if (! $this->isValidTransaction($advance)) {
                    continue;
                }

                if ($advance->cash_flow === 'in') {
                    $this->processInflow($advance, $reportData, $packageIds, $bundles, $startDate, $endDate);
                } else {
                    $this->processOutflow($advance, $reportData, $bundles);
                }
            }
        }

        return $this->buildResult($reportData, $startDate, $endDate);
    }

    public function generateExcel(array $result): void
    {
        $reportData = $result['reportData'];
        $startDate = $result['start_date'];
        $endDate = $result['end_date'];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);

        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Duration')->getStyle('A1')->getFont()->setBold(true);
        $sheet->setCellValue('B1', "From {$startDate} to {$endDate}");
        $sheet->setCellValue('A2', 'Date')->getStyle('A2')->getFont()->setBold(true);
        $sheet->setCellValue('B2', Carbon::now()->format('Y-m-d'));
        $sheet->setCellValue('A3', 'Service')->getStyle('A3')->getFont()->setBold(true);
        $sheet->setCellValue('B3', 'Total')->getStyle('B3')->getFont()->setBold(true);

        $counter = 4;
        $total = 0;

        foreach ($reportData as $row) {
            if ($row['amount'] > 0) {
                $total += $row['amount'];
                $sheet->setCellValue("A{$counter}", $row['name']);
                $sheet->setCellValue("B{$counter}", number_format($row['amount'], 2));
                $counter++;
            }
        }

        $sheet->setCellValue("A{$counter}", '');
        $sheet->setCellValue("B{$counter}", '');
        $counter++;
        $sheet->setCellValue("A{$counter}", 'Grand Total')->getStyle("A{$counter}")->getFont()->setBold(true);
        $sheet->setCellValue("B{$counter}", number_format($total, 2))->getStyle("B{$counter}")->getFont()->setBold(true);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="Collectionbyservice.xlsx"');
        header('Cache-Control: max-age=0');
        $writer->save('php://output');
    }

    private function resolveLocationIds(?array $locationIds, ?string $regionId): array
    {
        $where = [];

        if ($regionId) {
            $locations = ! empty($locationIds)
                ? Locations::generalrevenuegetActiveSorted($locationIds, $regionId)
                : Locations::generalrevenuegetActiveSorted(ACL::getUserCentres(), $regionId);

            foreach ($locations as $key => $location) {
                $where[] = $key;
            }
        } else {
            if (! empty($locationIds)) {
                // If location_id is an array, use it; if single value, wrap it
                if (is_array($locationIds)) {
                    $filtered = array_filter($locationIds, fn ($val) => $val !== '' && $val !== null);
                    if (! empty($filtered)) {
                        $where = array_values($filtered);
                    }
                } else {
                    $where[] = $locationIds;
                }
            }

            if (empty($where)) {
                $locations = Locations::getActiveSorted(ACL::getUserCentres());
                foreach ($locations as $key => $location) {
                    $where[] = $key;
                }
            }
        }

        return $where;
    }

    private function isValidTransaction(PackageAdvances $advance): bool
    {
        return ($advance->cash_flow === 'in'
                && $advance->cash_amount != '0'
                && $advance->is_adjustment == '0'
                && $advance->is_tax == '0'
                && $advance->is_cancel == '0')
            || ($advance->cash_flow === 'out'
                && $advance->is_refund == '1'
                && $advance->is_tax == '0');
    }

    private function processInflow(
        PackageAdvances $advance,
        array &$reportData,
        array &$packageIds,
        array &$bundles,
        ?string $startDate,
        ?string $endDate,
    ): void {
        if ($advance->appointment_id) {
            $appointment = Appointments::where('id', $advance->appointment_id)
                ->where('appointment_type_id', '2')
                ->first();

            if ($appointment) {
                $service = Services::find($appointment->service_id);

                if ($service && ! in_array($service->id, $bundles)) {
                    $reportData[$service->id] = [
                        'package_bundle_id' => 0,
                        'id' => $service->id,
                        'name' => $service->name,
                        'amount' => 0,
                    ];
                    $bundles[] = $service->id;
                }

                if ($service) {
                    $reportData[$service->id]['amount'] += $advance->cash_amount;
                }
            }
        } else {
            if (! in_array($advance->package_id, $packageIds, true)) {
                $packageIds[] = $advance->package_id;
                $package = Packages::find($advance->package_id);

                if ($package) {
                    $this->processPackageInflow($package, $advance, $reportData, $bundles, $startDate, $endDate);
                }
            }
        }
    }

    private function processPackageInflow(
        Packages $package,
        PackageAdvances $advance,
        array &$reportData,
        array &$bundles,
        ?string $startDate,
        ?string $endDate,
    ): void {
        $consumedServices = PackageService::whereDate('updated_at', '>=', $startDate)
            ->whereDate('updated_at', '<=', $endDate)
            ->where('is_consumed', '1')
            ->where('package_id', $package->id)
            ->whereNotNull('package_id')
            ->get();

        if ($consumedServices->count() > 0) {
            $consumedIds = $consumedServices->pluck('id')->toArray();
            $totalConsume = $consumedServices->sum('tax_including_price');

            // Process consumed services
            foreach ($consumedServices as $packageService) {
                $packageBundle = PackageBundles::find($packageService->package_bundle_id);
                if (! $packageBundle) {
                    continue;
                }
                $bundleInfo = Bundles::find($packageBundle->bundle_id);
                if (! $bundleInfo) {
                    continue;
                }

                if (! in_array($bundleInfo->id, $bundles)) {
                    $reportData[$bundleInfo->id] = [
                        'package_bundle_id' => $packageService->package_bundle_id,
                        'id' => $bundleInfo->id,
                        'name' => $bundleInfo->name,
                        'amount' => 0,
                    ];
                    $bundles[] = $bundleInfo->id;
                }
                $reportData[$bundleInfo->id]['amount'] += $packageService->tax_including_price;
            }

            // Calculate remaining for unconsumed services
            $cashReceive = PackageAdvances::whereDate('created_at', '>=', $startDate)
                ->whereDate('created_at', '<=', $endDate)
                ->where('package_id', $package->id)
                ->where('is_cancel', '0')
                ->where('cash_flow', 'in')
                ->sum('cash_amount');

            $unconsumedServices = PackageService::whereNotIn('id', $consumedIds)
                ->where('package_id', $package->id)
                ->whereNotNull('package_id')
                ->get();

            foreach ($unconsumedServices as $packageService) {
                $packageBundle = PackageBundles::find($packageService->package_bundle_id);
                if (! $packageBundle) {
                    continue;
                }
                $bundleInfo = Bundles::find($packageBundle->bundle_id);
                if (! $bundleInfo) {
                    continue;
                }

                $remainingAmount = $cashReceive - $totalConsume;
                $totalPrice = $package->total_price - $totalConsume;
                $divideAmount = $totalPrice > 0
                    ? ($packageService->tax_including_price / $totalPrice) * $remainingAmount
                    : 0;

                if (! in_array($bundleInfo->id, $bundles)) {
                    $reportData[$bundleInfo->id] = [
                        'id' => $bundleInfo->id,
                        'name' => $bundleInfo->name,
                        'amount' => 0,
                    ];
                    $bundles[] = $bundleInfo->id;
                }

                $reportData[$bundleInfo->id]['amount'] += $divideAmount;
            }
        }
    }

    private function processOutflow(PackageAdvances $advance, array &$reportData, array &$bundles): void
    {
        if ($advance->appointment_id) {
            $appointment = Appointments::find($advance->appointment_id);

            if ($appointment) {
                $service = Services::find($appointment->service_id);

                if ($service && ! in_array($service->id, $bundles)) {
                    $reportData[$appointment->service_id] = [
                        'id' => $appointment->service_id,
                        'name' => $service->name,
                        'amount' => 0,
                    ];
                    $bundles[] = $service->id;
                }

                if ($service) {
                    $reportData[$appointment->service_id]['amount'] -= $advance->cash_amount;
                }
            }
        } else {
            $package = Packages::find($advance->package_id);

            if ($package) {
                $packageServices = PackageService::where('package_id', $package->id)
                    ->whereNotNull('package_id')
                    ->get();

                foreach ($packageServices as $packageService) {
                    $packageBundle = PackageBundles::find($packageService->package_bundle_id);
                    if (! $packageBundle) {
                        continue;
                    }
                    $bundleInfo = Bundles::find($packageBundle->bundle_id);
                    if (! $bundleInfo) {
                        continue;
                    }

                    $totalAmount = $package->total_price;
                    $divideAmount = $totalAmount > 0
                        ? ($packageService->tax_including_price * $advance->cash_amount) / $totalAmount
                        : 0;

                    if (! in_array($bundleInfo->id, $bundles)) {
                        $reportData[$bundleInfo->id] = [
                            'id' => $bundleInfo->id,
                            'name' => $bundleInfo->name,
                            'amount' => 0,
                        ];
                        $bundles[] = $bundleInfo->id;
                    }

                    $reportData[$bundleInfo->id]['amount'] -= $divideAmount;
                }
            }
        }
    }

    private function buildResult(array $reportData, ?string $startDate, ?string $endDate): array
    {
        return [
            'reportData' => $reportData,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }
}
