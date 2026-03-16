<?php

namespace App\Http\Controllers;

use App\HelperModule\ApiHelper;
use App\Services\DoctorDashboard\DoctorDashboardService;
use App\Services\DoctorDashboard\DoctorIdentifier;
use App\Models\DoctorGoogleReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DoctorDashboardController extends Controller
{
    private $success = 200;
    private $error = 500;

    private DoctorDashboardService $dashboardService;
    private DoctorIdentifier $doctorIdentifier;

    public function __construct(
        DoctorDashboardService $dashboardService,
        DoctorIdentifier $doctorIdentifier
    ) {
        $this->dashboardService = $dashboardService;
        $this->doctorIdentifier = $doctorIdentifier;
    }

    /**
     * Display the doctor dashboard view.
     */
    public function index()
    {
        $user = Auth::user();
        $accountId = $user->account_id;
        $doctorId = $user->id;

        // Verify user is a doctor
        if (!$this->doctorIdentifier->isDoctor($doctorId)) {
            abort(403, 'Access denied. Doctor role required.');
        }

        $doctorInfo = $this->doctorIdentifier->getDoctorInfo($doctorId);
        $targets = $this->dashboardService->getTargets($accountId);

        return view('admin.doctor_dashboard.index', compact('doctorInfo', 'targets'));
    }

    /**
     * Get all KPI data.
     */
    public function getKpis(Request $request)
    {
        try {
            $user = Auth::user();
            $doctorId = $user->id;
            $accountId = $user->account_id;
            $period = $request->get('period', 'this_month');

            $data = $this->dashboardService->getKpiData($doctorId, $accountId, $period);

            return ApiHelper::apiResponse($this->success, 'KPI data loaded', true, $data);
        } catch (\Exception $e) {
            \Log::error('Doctor Dashboard KPI Error: ' . $e->getMessage());
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false);
        }
    }

    /**
     * Get hero strip data (goal progress, streak, personal bests).
     */
    public function getHeroData()
    {
        try {
            $user = Auth::user();
            $data = $this->dashboardService->getHeroData($user->id, $user->account_id);

            return ApiHelper::apiResponse($this->success, 'Hero data loaded', true, $data);
        } catch (\Exception $e) {
            \Log::error('Doctor Dashboard Hero Error: ' . $e->getMessage());
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false);
        }
    }

    /**
     * Get today's appointments.
     */
    public function getTodaysAppointments()
    {
        try {
            $user = Auth::user();
            $data = $this->dashboardService->getTodaysAppointments($user->id, $user->account_id);

            return ApiHelper::apiResponse($this->success, 'Today\'s appointments loaded', true, $data);
        } catch (\Exception $e) {
            \Log::error('Doctor Dashboard Appointments Error: ' . $e->getMessage());
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false);
        }
    }

    /**
     * Get benchmark data.
     */
    public function getBenchmarks(Request $request)
    {
        try {
            $user = Auth::user();
            $period = $request->get('period', 'this_month');

            if ($period === 'last_month') {
                $startDate = now()->subMonth()->startOfMonth()->format('Y-m-d');
                $endDate = now()->subMonth()->endOfMonth()->format('Y-m-d');
            } else {
                $startDate = now()->startOfMonth()->format('Y-m-d');
                $endDate = now()->format('Y-m-d');
            }

            $data = $this->dashboardService->getBenchmarks($startDate, $endDate, $user->account_id);

            return ApiHelper::apiResponse($this->success, 'Benchmark data loaded', true, $data);
        } catch (\Exception $e) {
            \Log::error('Doctor Dashboard Benchmark Error: ' . $e->getMessage());
            return ApiHelper::apiResponse($this->error, $e->getMessage(), false);
        }
    }
}
