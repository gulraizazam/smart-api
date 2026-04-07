<?php

declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use App\Models\Locations;
use App\Models\PackageBundles;
use Illuminate\Http\Request;

class JenpharmVoucherReportController extends Controller
{
    public function index(): mixed
    {
        $discount = Discount::where('name', 'Jenpharm Gift Voucher')->first();

        if (!$discount) {
            return view('admin.reports.jenpharm_voucher_report', [
                'discount' => null,
                'locationData' => collect(),
                'totalPlans' => 0,
            ]);
        }

        // Get all package_bundles using this discount, grouped by location
        $locationData = PackageBundles::where('discount_id', $discount->id)
            ->select('location_id', \DB::raw('COUNT(*) as total_plans'))
            ->groupBy('location_id')
            ->with('package')
            ->get()
            ->map(function ($item) {
                $location = Locations::withTrashed()->find($item->location_id);
                return (object) [
                    'location_id' => $item->location_id,
                    'location_name' => $location ? $location->name : 'N/A',
                    'total_plans' => $item->total_plans,
                ];
            })
            ->sortByDesc('total_plans')
            ->values();

        $totalPlans = $locationData->sum('total_plans');

        return view('admin.reports.jenpharm_voucher_report', [
            'discount' => $discount,
            'locationData' => $locationData,
            'totalPlans' => $totalPlans,
        ]);
    }
}
