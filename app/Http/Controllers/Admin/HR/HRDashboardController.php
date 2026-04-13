<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\HR;

use App\Http\Controllers\Controller;
use App\Models\LeaveApplication;
use App\Models\RecruitmentCandidate;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HRDashboardController extends Controller
{
    public function index(): View
    {
        $accountId = Auth::user()->account_id;
        $now = now();

        $totalEmployees = User::where('account_id', $accountId)
            ->whereIn('user_type_id', [2, 5])
            ->where('active', 1)
            ->where('hr_managed', 1)
            ->count();

        $pendingLeaves = LeaveApplication::where('account_id', $accountId)
            ->pending()
            ->count();

        $approvedThisMonth = LeaveApplication::where('account_id', $accountId)
            ->approved()
            ->whereMonth('reviewed_at', $now->month)
            ->whereYear('reviewed_at', $now->year)
            ->count();

        $openCandidates = RecruitmentCandidate::where('account_id', $accountId)
            ->open()
            ->count();

        $pendingApplications = LeaveApplication::with(['user', 'leaveType'])
            ->where('account_id', $accountId)
            ->pending()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return view('admin.hr.dashboard.index', compact(
            'totalEmployees',
            'pendingLeaves',
            'approvedThisMonth',
            'openCandidates',
            'pendingApplications',
        ));
    }
}
