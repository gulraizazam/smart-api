<?php

namespace App\Http\Controllers;

use App\Helpers\ACL;
use App\Models\Cities;
use App\Models\Locations;
use App\Models\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConversionReportController extends Controller
{
    public function index()
    {
        $services = Services::where(['parent_id'=>0])->whereNotIn('slug',['all'])->get();
        $cities = Cities::getActiveOnly(false, Auth::User()->account_id)->pluck('full_name', 'id');
        $locations = Locations::getActiveRecordsByCity('',ACL::getUserCentres(), Auth::User()->account_id);
        return view('admin.reports.conversion',get_defined_vars());
    }
    public function LoadConversionReport(Request $request)
    {
        dd($request->all());
    }
}
