<?php

namespace App\Http\Controllers;

use App\HelperModule\ApiHelper;
use App\Services\DoctorDashboard\DoctorDashboardService;
use App\Services\DoctorDashboard\DoctorIdentifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class DoctorDashboardController extends Controller
{
    protected string $success;
    protected string $error;

    public function __construct(
        protected readonly DoctorDashboardService $dashboardService,
        protected readonly DoctorIdentifier $doctorIdentifier,
    ) {
        $this->success = config('constants.api_status.success');
        $this->error = config('constants.api_status.error');
    }

    public function index(): View
    {
        $user = Auth::user();
        $doctorId = $user->id;

        if (!$this->doctorIdentifier->isDoctor($doctorId)) {
            abort(403, 'Access denied. Doctor role required.');
        }

        $doctorInfo = $this->doctorIdentifier->getDoctorInfo($doctorId);

        $userRoles = $user->user_roles()->pluck('name')->toArray();
        $showUpsellCards = in_array('Aesthetic Doctor', $userRoles) || in_array('Lifestyle Consultant', $userRoles);

        return view('admin.doctor_dashboard.index', compact('doctorInfo', 'showUpsellCards'));
    }

    public function getKpis(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $period = $request->get('period', 'this_month');

            $data = $this->dashboardService->getKpiData($user->id, $user->account_id, $period);

            return ApiHelper::apiResponse($this->success, 'KPI data loaded.', true, $data);
        } catch (\Exception $e) {
            Log::error('Doctor Dashboard KPI Error: ' . $e->getMessage());
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false);
        }
    }

    public function getHeroData(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $period = $request->get('period', 'this_month');
            $data = $this->dashboardService->getHeroData($user->id, $user->account_id, $period);

            return ApiHelper::apiResponse($this->success, 'Hero data loaded.', true, $data);
        } catch (\Exception $e) {
            Log::error('Doctor Dashboard Hero Error: ' . $e->getMessage());
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false);
        }
    }

    public function getTodaysAppointments(): JsonResponse
    {
        try {
            $user = Auth::user();
            $data = $this->dashboardService->getTodaysAppointments($user->id, $user->account_id);

            return ApiHelper::apiResponse($this->success, "Today's appointments loaded.", true, $data);
        } catch (\Exception $e) {
            Log::error('Doctor Dashboard Appointments Error: ' . $e->getMessage());
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false);
        }
    }

    public function getBenchmarks(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $period = $request->get('period', 'this_month');

            [$startDate, $endDate] = match ($period) {
                'last_month' => [
                    now()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d'),
                    now()->subMonthNoOverflow()->endOfMonth()->format('Y-m-d'),
                ],
                default => [
                    now()->startOfMonth()->format('Y-m-d'),
                    now()->format('Y-m-d'),
                ],
            };

            $data = $this->dashboardService->getBenchmarks($user->id, $startDate, $endDate, $user->account_id);

            return ApiHelper::apiResponse($this->success, 'Benchmark data loaded.', true, $data);
        } catch (\Exception $e) {
            Log::error('Doctor Dashboard Benchmark Error: ' . $e->getMessage());
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false);
        }
    }
}
