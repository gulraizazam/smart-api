<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PackageService;
use App\Models\PackageBundles;
use App\Models\Packages;
use App\Models\PackageAdvances;
use DB;

class TestPlanConsumptionRules extends Command
{
    protected $signature = 'test:plan-consumption-rules {package_id?}';
    protected $description = 'Test plan consumption rules against a real package to verify all scenarios';

    public function handle()
    {
        $packageId = $this->argument('package_id');

        if (!$packageId) {
            $this->info("Usage: php artisan test:plan-consumption-rules {package_id}");
            $this->info("");
            $this->info("This command inspects a plan and reports:");
            $this->info("  - Config groups and their consumption state");
            $this->info("  - Whether the Add button should be locked");
            $this->info("  - Per-service delete eligibility");
            $this->info("  - Fully-paid status and payment coverage");
            $this->info("");
            $this->runAllScenarioChecks();
            return;
        }

        $package = Packages::find($packageId);
        if (!$package) {
            $this->error("Package #{$packageId} not found.");
            return;
        }

        $this->inspectPackage($package);
    }

    private function inspectPackage(Packages $package)
    {
        $this->info("=== Plan #{$package->id}: {$package->plan_name} ===");
        $this->info("Total Price: {$package->total_price}");

        // Payment info
        $totalIn = PackageAdvances::where('package_id', $package->id)->where('cash_flow', 'in')->sum('cash_amount');
        $totalOut = PackageAdvances::where('package_id', $package->id)->where('cash_flow', 'out')->sum('cash_amount');
        $balance = $totalIn - $totalOut;
        $totalServiceValue = PackageService::where('package_id', $package->id)->sum('tax_including_price');
        $isPlanFullyPaid = $totalIn >= $totalServiceValue;

        $this->info("Total Payments (in): {$totalIn}");
        $this->info("Total Consumed (out): {$totalOut}");
        $this->info("Balance: {$balance}");
        $this->info("Total Service Value: {$totalServiceValue}");
        $this->info("Fully Paid: " . ($isPlanFullyPaid ? 'YES' : 'NO'));
        $this->info("");

        // Get all bundles and services
        $bundles = PackageBundles::where('package_id', $package->id)->get();
        $services = PackageService::where('package_id', $package->id)->get();

        // Group by config_group_id
        $configGroups = [];
        foreach ($bundles as $bundle) {
            if ($bundle->config_group_id) {
                $configGroups[$bundle->config_group_id][] = $bundle;
            }
        }

        // Display services table
        $this->info("--- Services ---");
        $headers = ['Bundle ID', 'Service/Bundle', 'Price', 'Config Group', 'Consumed', 'Order', 'Deletable?'];
        $rows = [];

        foreach ($bundles as $bundle) {
            $bundleServices = $services->where('package_bundle_id', $bundle->id);
            $isConsumed = $bundleServices->where('is_consumed', '1')->count() > 0;
            $consumptionOrder = $bundleServices->first() ? $bundleServices->first()->consumption_order : '-';

            // Check delete eligibility
            $deletable = $this->checkDeleteEligibility($bundle, $services);

            $rows[] = [
                $bundle->id,
                $bundle->bundle_id . ' (' . ($bundle->service ? $bundle->service->name ?? '' : '') . ')',
                $bundle->tax_including_price,
                $bundle->config_group_id ?? '-',
                $isConsumed ? 'YES' : 'No',
                $consumptionOrder,
                $deletable,
            ];
        }

        $this->table($headers, $rows);
        $this->info("");

        // Config group analysis
        if (count($configGroups) > 0) {
            $this->info("--- Config Group Analysis ---");
            foreach ($configGroups as $groupId => $groupBundles) {
                $this->info("Group: {$groupId}");
                $hasOutOfOrder = $this->checkOutOfOrderConsumption($groupId, $package->id);
                $groupServices = $services->whereIn('package_bundle_id', collect($groupBundles)->pluck('id'));
                $anyConsumed = $groupServices->where('is_consumed', '1')->count() > 0;

                $this->info("  Any consumed: " . ($anyConsumed ? 'YES' : 'No'));
                $this->info("  Out-of-order: " . ($hasOutOfOrder ? 'YES — ADD LOCKED!' : 'No'));
                $this->info("");
            }
        }

        // Final verdict
        $hasOutOfOrderAny = $this->checkOutOfOrderConsumptionForPlan($package->id);
        $this->info("=== VERDICT ===");
        $this->info("Add Button Locked: " . ($hasOutOfOrderAny ? 'YES (out-of-order config group consumption)' : 'NO (can add services)'));
    }

    private function checkDeleteEligibility(PackageBundles $bundle, $allServices)
    {
        $bundleServices = $allServices->where('package_bundle_id', $bundle->id);

        // Check if own services are consumed
        if ($bundleServices->where('is_consumed', '1')->count() > 0) {
            return 'NO (consumed)';
        }

        // Check if belongs to config group with consumed service
        if ($bundle->config_group_id) {
            $groupBundleIds = PackageBundles::where('config_group_id', $bundle->config_group_id)->pluck('id');
            $groupConsumed = PackageService::whereIn('package_bundle_id', $groupBundleIds)
                ->where('is_consumed', '1')
                ->exists();

            if ($groupConsumed) {
                return 'NO (config group consumed)';
            }
        }

        return 'YES';
    }

    private function checkOutOfOrderConsumption($configGroupId, $packageId)
    {
        return PackageService::where('package_services.package_id', $packageId)
            ->join('package_bundles', 'package_services.package_bundle_id', '=', 'package_bundles.id')
            ->where('package_bundles.config_group_id', $configGroupId)
            ->where('package_services.is_consumed', '1')
            ->whereExists(function ($query) use ($packageId, $configGroupId) {
                $query->select(DB::raw(1))
                    ->from('package_services as ps2')
                    ->join('package_bundles as pb2', 'ps2.package_bundle_id', '=', 'pb2.id')
                    ->where('pb2.config_group_id', $configGroupId)
                    ->where('ps2.package_id', $packageId)
                    ->where('ps2.is_consumed', '0')
                    ->whereColumn('ps2.consumption_order', '<', 'package_services.consumption_order');
            })
            ->exists();
    }

    private function checkOutOfOrderConsumptionForPlan($packageId)
    {
        return PackageService::where('package_services.package_id', $packageId)
            ->join('package_bundles', 'package_services.package_bundle_id', '=', 'package_bundles.id')
            ->whereNotNull('package_bundles.config_group_id')
            ->where('package_services.is_consumed', '1')
            ->whereExists(function ($query) use ($packageId) {
                $query->select(DB::raw(1))
                    ->from('package_services as ps2')
                    ->join('package_bundles as pb2', 'ps2.package_bundle_id', '=', 'pb2.id')
                    ->whereColumn('pb2.config_group_id', 'package_bundles.config_group_id')
                    ->where('ps2.package_id', $packageId)
                    ->where('ps2.is_consumed', '0')
                    ->whereColumn('ps2.consumption_order', '<', 'package_services.consumption_order');
            })
            ->exists();
    }

    private function runAllScenarioChecks()
    {
        $this->info("=== Scenario Test Matrix ===");
        $this->info("");
        $this->info("To test each scenario, create the plan state manually, then run:");
        $this->info("  php artisan test:plan-consumption-rules {package_id}");
        $this->info("");

        $scenarios = [
            ['#1', 'No consumption, no config groups', 'Add: YES, Delete all: YES'],
            ['#2', 'No consumption, has config group', 'Add: YES, Delete all: YES'],
            ['#3', 'Simple service consumed, no config groups', 'Add: YES, Delete unconsumed: YES, Delete consumed: NO'],
            ['#4', 'Config group BUY consumed then GET consumed (correct order)', 'Add: YES, Delete config group: NO'],
            ['#5', 'Config group BUY consumed, GET unconsumed (correct partial)', 'Add: YES, Delete config group: NO'],
            ['#6', 'Config group GET consumed, BUY unconsumed (OUT OF ORDER)', 'Add: NO, Delete config group: NO'],
            ['#7', 'Config group GET consumed + simple service unconsumed', 'Add: NO, Delete simple: YES, Delete config: NO'],
            ['#8', 'Fully paid, config group all unconsumed', 'Add: YES, Delete all: YES'],
            ['#9', 'Simple consumed + unconsumed config group (no consumption in group)', 'Add: YES, Delete config group: YES, Delete consumed simple: NO'],
            ['#10', 'Multiple config groups, one out-of-order', 'Add: NO (one bad group locks all)'],
        ];

        $headers = ['Scenario', 'State', 'Expected Behavior'];
        $this->table($headers, $scenarios);
    }
}
