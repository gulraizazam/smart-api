<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reports;

use App\Helpers\ACL;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\ArrivedNotConvertedRequest;
use App\Models\Cities;
use App\Models\Locations;
use App\Models\Services;
use App\Services\Reports\ArrivedNotConvertedService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class ArrivedNotConvertedController extends Controller
{
    public function __construct(
        private readonly ArrivedNotConvertedService $reportService,
    ) {}

    public function index(): View
    {
        $services = Services::where(['parent_id' => 0])->whereNotIn('slug', ['all'])->get();
        $cities = Cities::getActiveOnly(false, Auth::user()->account_id)->pluck('full_name', 'id');
        $locations = Locations::getActiveRecordsByCity('', ACL::getUserCentres(), Auth::user()->account_id);

        return view('admin.reports.arrived', compact('services', 'cities', 'locations'));
    }

    public function reportLoad(ArrivedNotConvertedRequest $request): View
    {
        $patients = $this->reportService->generate(
            startDate: $request->startDate(),
            endDate: $request->endDate(),
            locationIds: $request->locationIds(),
            doctorId: $request->doctorId(),
            serviceId: $request->serviceId(),
        );

        return view('admin.reports.arrived_not_converted', compact('patients'));
    }
}
