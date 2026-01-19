<?php

namespace App\Http\Controllers\Admin;

use Auth;
use App\Helpers\ACL;
use App\Models\User;
use App\Models\Towns;
use App\Models\Cities;
use App\Models\Services;
use App\Models\LeadSources;
use App\Models\LeadStatuses;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;

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
    public function index()
    {
        if (!Gate::allows('leads_manage')) {
            return abort(401);
        }

        return view('admin.leads.index');
    }

    /**
     * Display junk leads page.
     */
    public function junk()
    {
        if (!Gate::allows('leads_junk')) {
            return abort(401);
        }

        return view('admin.leads.junk');
    }

    /**
     * Display import leads page.
     */
    public function importLeads()
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
    public function make_pop()
    {
        if (!Gate::allows('leads_create')) {
            return abort(401);
        }

        $cities = Cities::getActiveSortedFeatured(ACL::getUserCities());
        $cities->prepend('Select a City', '');

        $towns = Towns::getActiveTowns();
        $towns->prepend('Select a Town', '');

        $lead_sources = LeadSources::getActiveSorted();
        $lead_sources->prepend('Select a Lead Source', '');

        $lead_statuses = LeadStatuses::getLeadStatuses();
        $lead_statuses->prepend('Select a Lead Status', '');

        $Services = Services::where([
            ['slug', '=', 'custom'],
            ['parent_id', '=', '0'],
            ['active', '=', '1'],
        ])->get()->pluck('name', 'id');
        $Services->prepend('Select Service', '');

        $lead = new \stdClass();
        $lead->id = null;
        $lead->name = null;
        $lead->email = null;
        $lead->phone = null;
        $lead->gender = null;

        $employees = User::getAllActiveRecords(Auth::User()->account_id);
        if ($employees) {
            $employees = $employees->pluck('full_name', 'id');
            $employees->prepend('Select a Referrer', '');
        } else {
            $employees = [];
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
    public function leadupdate()
    {
        // Legacy method - kept for backward compatibility
        return redirect()->route('admin.leads.index');
    }

    /**
     * Update lead statuses (legacy route).
     */
    public function leadstatusupdate()
    {
        // Legacy method - kept for backward compatibility
        return redirect()->route('admin.leads.index');
    }
}
