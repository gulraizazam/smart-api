<?php

namespace App\Http\Controllers\Admin;

use Auth;
use App\Helpers\ACL;
use App\Models\User;
use App\Models\Leads;
use App\Models\Towns;
use App\Models\Cities;
use App\Models\SMSLogs;
use App\Models\Services;
use App\Models\Settings;
use App\Models\LeadSources;
use App\Models\LeadStatuses;
use App\Models\SMSTemplates;
use Illuminate\Http\Request;
use App\Helpers\TelenorSMSAPI;
use App\HelperModule\ApiHelper;
use App\Helpers\GeneralFunctions;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use App\Services\Lead\LeadService;

/**
 * Admin LeadsController - View Routes Only
 * 
 * All API/AJAX operations are handled by App\Http\Controllers\Api\LeadsController
 * This controller only handles view rendering and legacy popup functionality.
 */
class LeadsController extends Controller
{
    protected LeadService $leadService;

    public function __construct(LeadService $leadService)
    {
        $this->leadService = $leadService;
    }

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
     * Send SMS to lead.
     */
    public function send_sms($id)
    {
        if (!Gate::allows('leads_manage')) {
            return abort(401);
        }

        $lead = Leads::findOrFail($id);
        $response = $this->sendSMS($lead->id, $lead->phone);

        if ($response['status']) {
            flash('SMS has been sent successfully.')->success()->important();
        } else {
            flash('SMS sending failed.')->error()->important();
        }

        return redirect()->back();
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

    /**
     * Send SMS helper method.
     */
    private function sendSMS($leadId, $phone)
    {
        // Currently disabled - returns success
        return ['status' => true];

        // Uncomment below to enable SMS sending:
        // $SMSTemplate = SMSTemplates::findOrFail(2);
        // $preparedText = $this->leadService->prepareSMSContent($leadId, $SMSTemplate->content);
        // $Settings = Settings::getAllRecordsDictionary(Auth::User()->account_id);
        // $SMSObj = [
        //     'username' => $Settings[1]->data,
        //     'password' => $Settings[2]->data,
        //     'to' => GeneralFunctions::prepareNumber(GeneralFunctions::cleanNumber($phone)),
        //     'text' => $preparedText,
        //     'mask' => $Settings[3]->data,
        //     'test_mode' => $Settings[4]->data,
        // ];
        // $response = TelenorSMSAPI::SendSMS($SMSObj);
        // SMSLogs::create(array_merge($SMSObj, $response, ['lead_id' => $leadId, 'created_by' => Auth::user()->id]));
        // return $response;
    }
}
