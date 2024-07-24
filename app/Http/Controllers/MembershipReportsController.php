<?php

namespace App\Http\Controllers;

use App\Exports\ExportMemberships;
use App\Helpers\ACL;
use App\Http\Requests\MembershipReportRequest;
use App\Models\Locations;
use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\Packages;
use DateTime;
use App\Models\Services;
use App\Models\User;
use Maatwebsite\Excel\Facades\Excel;
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
        $filters = getFilters($request->all());

        if (hasFilter($filters, 'location_id')) {
            $where[] = ['packages.location_id', '=', $filters['location_id']];
        }

        if (hasFilter($filters, 'membership_type_id') && $filters['membership_type_id'] !== "no_membership") {
            $whereMembership[] = ['membership_type_id', '=', $filters['membership_type_id']];
        }
        if (hasFilter($filters, 'date_range')) {
            $date_range = explode(' - ', $filters['date_range']);

            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }

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
            ->when(isset($filters['membership_type_id']), function ($query) use ($whereMembership, $filters) {
                if ($filters['membership_type_id'] === "no_membership") {
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

        return $users;
    }
    public function Export(Request $request)
    {
        return Excel::download(new ExportMemberships($request), 'memberships.' . 'xlsx');
    }
}
