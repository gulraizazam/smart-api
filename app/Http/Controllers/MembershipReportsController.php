<?php

namespace App\Http\Controllers;

use App\Helpers\ACL;
use App\Http\Requests\MembershipReportRequest;
use App\Models\Locations;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\Packages;
use App\Models\Services;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PhpOffice\PhpSpreadsheet\Calculation\Web\Service;

class MembershipReportsController extends Controller
{
    public function index()
    {
        if (!Gate::allows('operations_reports_manage')) {
            return abort(401);
        }
        $locations = Locations::getActiveSorted(ACL::getUserCentres());

        $membershipTypes = MembershipType::pluck('name', 'id');
        return view('admin.reports.memberships.index', get_defined_vars());
    }
    public function loadMembershipReport(MembershipReportRequest $request)
    {
        $where = [];
        $whereMembership = [];

        if ($request->location_id) {
            $where[] = ['packages.location_id', $request->location_id];
        }

        if ($request->membership_type_id && $request->membership_type_id !== "no_membership") {
            $whereMembership[] = ['membership_type_id', '=', $request->membership_type_id];
        }

        $start_date = $request->getStartDate();
        $end_date = $request->getEndDate();
        $serviceIds = Services::where('name', 'like', '%Gold Membership Card%')
            ->orWhere('name', 'like', '%Student Membership Card%')
            ->pluck('id')->toArray();

        $packagesWithServices = Packages::with([
            'user',
            'packageservice.service',
            'location',
            'user.membership.membershipType'
        ])
            ->whereHas('packageservice', function ($query) use ($serviceIds) {
                $query->whereIn('service_id', $serviceIds);
            })
            ->where($where)
            ->when($request->membership_type_id, function ($query) use ($whereMembership, $request) {
                if ($request->membership_type_id === "no_membership") {
                    $query->whereDoesntHave('user.membership');
                } else {
                    $query->whereHas('user.membership', function ($query) use ($whereMembership) {
                        $query->where($whereMembership);
                    });
                }
            })
            ->when($start_date && $end_date, function ($query) use ($start_date, $end_date) {
                $query->whereHas('user.membership', function ($query) use ($start_date, $end_date) {
                    $query->whereBetween('assigned_at', [$start_date, $end_date]);
                });
            })
            ->get();

        $users = $packagesWithServices->map(function ($package) use ($serviceIds) {
            $user = $package->user;
            $service = $package->packageservice->whereIn('service_id', $serviceIds)->first();
            $serviceName = $service->service->name;
            $location = $package->location;
            $membership = $user->membership;
            return [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'location' => $location->name,
                'service_name' => $serviceName,
                'service_status' => $service->is_consumed ? 'Consumed' : 'Not Consumed',
                'membership_code' => $membership ? $membership->code : 'No membership',
                'membership_type' => $membership ? $membership->membershipType->name : 'No membership',
                'membership_type_id' => $membership ? $membership->membershipType->id : 0,
                'assigned_at' => $membership ? $membership->assigned_at : null,
            ];
        });

        return view('admin.reports.memberships.membership_report', ['users' => $users]);
    }
}
