<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Reports;

use App\Helpers\ACL;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\AppointmentsReportRequest;
use App\Models\Locations;
use App\Models\User;
use App\Services\Reports\AppointmentsReportService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class AppointmentsReportController extends Controller
{
    public function __construct(
        private readonly AppointmentsReportService $reportService,
    ) {}

    public function index(): View
    {
        $Users = User::getAllRecords(Auth::user()->account_id)
            ->whereNotIn('user_type_id', 5)
            ->where('active', 1)
            ->getDictionary();

        $locations = Locations::getActiveRecordsByCity('', ACL::getUserCentres(), Auth::user()->account_id);

        return view('admin.reports.appointments_report', compact('Users', 'locations'));
    }

    public function reportLoad(AppointmentsReportRequest $request): View
    {
        $appointments = $this->reportService->generate(
            startDate: $request->startDate(),
            endDate: $request->endDate(),
            timeInterval: $request->timeInterval(),
            centreIds: $request->centreIds(),
            createdBy: $request->createdBy(),
        );

        return view('admin.reports.appointmentsReports', compact('appointments'));
    }
}
