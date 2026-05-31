<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Helpers\ACL;
use App\Models\User;
use App\Models\Towns;
use App\Models\Cities;
use App\Models\Services;
use App\Models\LeadSources;
use App\Models\LeadStatuses;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin LeadsController - View Routes Only
 *
 * All API/AJAX operations are handled by App\Http\Controllers\Api\LeadsController.
 * This controller only handles view rendering and legacy popup functionality.
 */
class LeadsController extends Controller
{
    public function index(): View
    {
        abort_unless(Gate::allows('leads.list.view'), 401);

        return view('admin.leads.index');
    }

    public function junk(): View
    {
        abort_unless(Gate::allows('leads.list.view_junk'), 401);

        return view('admin.leads.junk');
    }

    public function importLeads(): View|RedirectResponse
    {
        if (!Gate::allows('leads.import')) {
            flash('You are not authorized to access this resource.')->error()->important();
            return redirect()->route('admin.leads.index');
        }

        return view('admin.leads.import');
    }

    public function make_pop(): JsonResponse
    {
        if (! Gate::allows('leads.create')) {
            return $this->errorResponse('You are not authorized to perform this action.', 403);
        }

        $cities = Cities::getActiveSortedFeatured(ACL::getUserCities());
        $cities->prepend('Select a City', '');

        $towns = Towns::getActiveTowns();
        $towns->prepend('Select a Town', '');

        $lead_sources = LeadSources::getActiveSorted();
        $lead_sources->prepend('Select a Lead Source', '');

        $lead_statuses = LeadStatuses::getLeadStatuses();
        $lead_statuses->prepend('Select a Lead Status', '');

        $Services = Services::where(['slug' => 'custom', 'active' => 1])
            ->rootServices()
            ->pluck('name', 'id');
        $Services->prepend('Select Service', '');

        $lead = [
            'id' => null,
            'name' => null,
            'email' => null,
            'phone' => null,
            'gender' => null,
        ];

        $employees = User::getAllActiveRecords(Auth::user()->account_id)?->pluck('full_name', 'id') ?? collect();
        if ($employees->isNotEmpty()) {
            $employees->prepend('Select a Referrer', '');
        }

        return $this->successResponse('Record found.', [
            'services' => $Services,
            'cities' => $cities,
            'lead_sources' => $lead_sources,
            'lead_statuses' => $lead_statuses,
            'lead' => $lead,
            'employees' => $employees,
            'towns' => $towns,
            'leadServices' => null,
            'edit_status' => 0,
        ]);
    }

}
