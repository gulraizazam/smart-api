<?php

namespace App\Http\Controllers;

use App\Helpers\ACL;
use App\Models\Locations;
use App\Models\Services;
use App\Models\User;
use App\Reports\Finanaces;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConversionReportController extends Controller
{
    public function index()
    {
        $services = Services::where(['parent_id' => 0])->where('slug', '!=', 'all')->pluck('id', 'name');
        $employees = User::getAllActiveEmployeeRecords(Auth::User()->account_id, ACL::getUserCentres())->pluck('name', 'id');
        $operators = User::getAllActivePractionersRecords(Auth::User()->account_id, ACL::getUserCentres())->pluck('name', 'id');
        $select_All = ['' => 'All'];
        $users = ($select_All + $employees->toArray() + $operators->toArray());
        $operators->prepend('All', '');
        $locations = Locations::getActiveSorted(ACL::getUserCentres());
        if (Auth::user()->hasRole('FDM')) {
        } else {
            $locations->prepend('All', '');
        }
        $locations_com = Locations::getActiveSorted(ACL::getUserCentres());

        return view('admin.reports.conversion', get_defined_vars());
    }



    public function LoadConversionReport(Request $request)
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);
        if ($request->date_range) {
            $date_range = explode(' - ', $request->date_range);
            $start_date = date('Y-m-d', strtotime($date_range[0]));
            $end_date = date('Y-m-d', strtotime($date_range[1]));
        } else {
            $start_date = null;
            $end_date = null;
        }
        list($report_data, $locationData, $maxConversion, $minConversion, $CategoryConversionData, $arrival_to_conversion_ratio, $average_client_coversion, $conversionsByPatient, $total_conversion, $total_arrival, $avg_cxlient_valu) = Finanaces::LoadConversionReport($request->all(), Auth::user()->account_id);

        return view('admin.reports.conversion_report', get_defined_vars());
    }
}
