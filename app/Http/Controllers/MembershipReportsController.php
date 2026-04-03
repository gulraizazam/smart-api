<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\ExportMemberships;
use App\Helpers\ACL;
use App\Http\Requests\MembershipReportRequest;
use App\Models\Locations;
use App\Models\MembershipType;
use App\Services\Reports\MembershipReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MembershipReportsController extends Controller
{
    public function __construct(
        private readonly MembershipReportService $reportService,
    ) {}

    public function index(): View
    {
        if (! Gate::allows('operations_reports_manage')) {
            abort(401);
        }

        $locations = Locations::getActiveSorted(ACL::getUserCentres());
        $membershipTypes = MembershipType::pluck('name', 'id');

        return view('admin.reports.memberships.index', compact('locations', 'membershipTypes'));
    }

    public function loadMembershipReport(MembershipReportRequest $request): Collection
    {
        $filters = getFilters($request->all());

        $locationId = hasFilter($filters, 'location_id') ? (int) $filters['location_id'] : null;
        $membershipTypeId = hasFilter($filters, 'membership_type_id') ? $filters['membership_type_id'] : null;

        $startDate = null;
        $endDate = null;

        if (hasFilter($filters, 'date_range')) {
            $parts = explode(' - ', $filters['date_range']);
            $startDate = date('Y-m-d', strtotime($parts[0]));
            $endDate = date('Y-m-d', strtotime($parts[1]));
        }

        return $this->reportService->generate(
            locationId: $locationId,
            membershipTypeId: $membershipTypeId,
            startDate: $startDate,
            endDate: $endDate,
        );
    }

    public function export(Request $request): BinaryFileResponse
    {
        return Excel::download(
            new ExportMemberships($this->reportService, $request),
            'memberships.xlsx',
        );
    }
}
