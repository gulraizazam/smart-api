<?php

namespace App\Http\Controllers;

use App\Helpers\DashboardHelper;
use App\Models\Locations;
use App\Models\RoleHasUsers;
use App\Models\User;
use App\HelperModule\ApiHelper;
use App\Services\Dashboard\DashboardRevenueService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    private const ADMIN_ROLES = ['Administrator', 'Super-Admin', 'Head of Operations', 'Finance', 'HRM'];
    private const CSR_ROLES = ['CSR Supervisor', 'Social Lead', 'CSR'];
    private const DOCTOR_ROLES = ['Aesthetic Doctor', 'Consultant', 'Lifestyle Consultant'];
    private const EXCLUDED_CENTRES = ['All South Region', 'All Central Region', 'All Centres'];

    public function __construct(
        private readonly DashboardRevenueService $revenueService,
    ) {}

    public function index(Request $request): View|\Illuminate\Http\RedirectResponse
    {
        $user = Auth::user();

        if ($user->can('doctor_dashboard') && $user->hasAnyRole(self::DOCTOR_ROLES)) {
            return redirect()->route('admin.doctor_dashboard');
        }

        $userCentres = DashboardHelper::getUserCentres();
        $dateTimeInfo = DashboardHelper::getDateTimeInfo();

        $centres = Locations::whereIn('id', $userCentres)
            ->whereNotIn('name', self::EXCLUDED_CENTRES)
            ->where('active', 1)
            ->select('id', 'name')
            ->get();

        $isAdmin = $user->hasAnyRole(self::ADMIN_ROLES);
        $isCSRRole = $user->hasAnyRole(self::CSR_ROLES);

        $csrUsers = $isCSRRole
            ? User::whereIn('id', RoleHasUsers::whereIn('role_id', [2, 3, 24])->pluck('user_id'))
                ->where('active', 1)
                ->select('id', 'name')
                ->get()
            : collect();

        return view('admin.home', [
            'today'              => $dateTimeInfo['today'],
            'startWeek'          => $dateTimeInfo['startWeek'],
            'month'              => $dateTimeInfo['month'],
            'currentTime'        => $dateTimeInfo['currentTime'],
            'location_id'        => $userCentres,
            'requestType'        => $request->type ?? 'today',
            'centres'            => $centres,
            'firstCentre'        => $centres->first(),
            'isAdmin'            => $isAdmin,
            'isCSRRole'          => $isCSRRole,
            'hasMultipleCentres' => $isAdmin || $centres->count() > 1,
            'csrUsers'           => $csrUsers,
        ]);
    }

    public function myCollectionByCentre(Request $request): JsonResponse
    {
        $result = $this->revenueService->getMyCollectionByCentre($request->type ?? '', $request);

        return ApiHelper::apiResponse(200, 'pie chart data', true, [
            'pie'   => $result['data'],
            'total' => number_format($result['total'] ?? 0, 2),
        ]);
    }

    public function myRevenueByCentre(Request $request): JsonResponse
    {
        $result = $this->revenueService->getMyRevenueByCentre($request->type ?? '', $request);

        return ApiHelper::apiResponse(200, 'Bar chart data', true, [
            'pie'   => $result['data'],
            'total' => number_format($result['total'] ?? 0, 2),
        ]);
    }

    public function myRevenueByService(Request $request): JsonResponse
    {
        $result = $this->revenueService->getRevenueByService(
            $request->period ?? '',
            $request,
            'dashboard_my_revenue_by_service',
        );

        return ApiHelper::apiResponse(200, 'service data', true, [
            'pie'    => $result['data'],
            'colors' => $result['colors'],
            'total'  => number_format($result['total'] ?? 0, 2),
        ]);
    }
}
