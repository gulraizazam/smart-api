<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\ACL;
use App\Models\User;
use App\Models\Towns;
use App\Models\Cities;
use App\Models\Services;
use App\Models\LeadSources;
use App\Models\LeadStatuses;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin LeadsController - View Routes Only
 * 
 * All API/AJAX operations are handled by App\Http\Controllers\Api\LeadsController
 * This controller only handles view rendering and legacy popup functionality.
 */
class LeadsController extends Controller
{

    /**
     * Display leads listing page.
     */
    public function index(): View
    {
        abort_unless(Gate::allows('leads_manage'), 401);

        return view('admin.leads.index');
    }

    /**
     * Display junk leads page.
     */
    public function junk(): View
    {
        abort_unless(Gate::allows('leads_junk'), 401);

        return view('admin.leads.junk');
    }

    /**
     * Display import leads page.
     */
    public function importLeads(): View|RedirectResponse
    {
        if (!Gate::allows('leads_import')) {
            flash('You are not authorized to access this resource.')->error()->important();
            return redirect()->route('admin.leads.index');
        }

        return view('admin.leads.import');
    }

    /**
     * Legacy popup for creating lead (used in appointments).
     */
    public function make_pop(): View
    {
        abort_unless(Gate::allows('leads_create'), 401);

        $cities = Cities::getActiveSortedFeatured(ACL::getUserCities());
        $cities->prepend('Select a City', '');

        $towns = Towns::getActiveTowns();
        $towns->prepend('Select a Town', '');

        $lead_sources = LeadSources::getActiveSorted();
        $lead_sources->prepend('Select a Lead Source', '');

        $lead_statuses = LeadStatuses::getLeadStatuses();
        $lead_statuses->prepend('Select a Lead Status', '');

        $Services = Services::where(['slug' => 'custom', 'parent_id' => 0, 'active' => 1])
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

        $edit_status = 0;
        $leadServices = null;

        return view('admin.leads.createTo', compact(
            'Services', 'cities', 'lead_sources', 'lead_statuses',
            'lead', 'leadServices', 'employees', 'edit_status', 'towns'
        ));
    }

    /**
     * Update leads (legacy route).
     */
    public function leadupdate(): RedirectResponse
    {
        return redirect()->route('admin.leads.index');
    }

    public function leadstatusupdate(): RedirectResponse
    {
        return redirect()->route('admin.leads.index');
    }
}
