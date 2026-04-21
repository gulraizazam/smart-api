<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Locations;
use App\Services\Dashboard\Support\ResourceScopeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Shell controller for the Management Dashboard. Renders a single Blade shell
 * (resources/views/admin/management_dashboard/index.blade.php) and switches
 * the active section server-side based on the URL. Data is loaded by the
 * frontend via ManagementDashboardApiController endpoints.
 */
class ManagementDashboardController extends Controller
{
    public function __construct(
        private readonly ResourceScopeResolver $scopeResolver,
    ) {}

    public function redirect(): RedirectResponse
    {
        return redirect()->route('admin.management_dashboard.overview');
    }

    public function overview(Request $request): View
    {
        return $this->renderShell($request, 'overview');
    }

    public function people(Request $request): View
    {
        return $this->renderShell($request, 'people');
    }

    private function renderShell(Request $request, string $activeSection): View
    {
        $user = Auth::user();
        $allowedBranchIds = $this->scopeResolver->allowedBranchIds($user);

        $branches = Locations::query()
            ->where('account_id', $user->account_id)
            ->whereNull('deleted_at')
            // Exclude rollup/virtual locations ("All Centres", "All South
            // Region", etc.) — these aren't physical branches where revenue
            // is generated, they're aggregate entries in the locations table.
            ->where('name', 'not like', 'All %')
            ->when(
                $allowedBranchIds !== null,
                static fn ($q) => $q->whereIn('id', $allowedBranchIds),
            )
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return view('admin.management_dashboard.index', [
            'active_section' => $activeSection,
            'branches' => $branches,
            'is_company_wide' => $allowedBranchIds === null,
            'allowed_branch_count' => $allowedBranchIds === null ? $branches->count() : count($allowedBranchIds),
        ]);
    }
}
