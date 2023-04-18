<?php

namespace App\Http\Controllers;

use App\Helpers\ACL;
use App\Models\Appointments;
use App\Models\Cities;
use App\Models\Invoices;
use App\Models\Locations;
use App\Models\Packages;
use App\Models\PackageService;
use App\Models\Services;
use Carbon\Carbon;
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
        $packagebundleIds = self::where([
            'package_id' => $package->id,
            'is_allocate' => '1'
        ])->pluck('id');
        $GetPackage = Packages::findOrFail($packagebundle[0]->package_id);
        $GetAppointment = Appointments::findOrFail($GetPackage->appointment_id);
        $GetInvoiceInfo = Invoices::where(['appointment_id' => $GetAppointment->id])->first();
        $package_services = PackageService::with('service')->whereIn('package_bundle_id',$packagebundleIds)
        ->where('created_at','>',Carbon::parse($GetInvoiceInfo->created_at))
        ->get();
        foreach ($package_services as $packagebundl) {
            if($packagebundl->service->parent_id != $GetAppointment->service_id){
                $GetAppointment->update(['service_id'=> $package_services[0]->service->parent_id]);
            }
        }
    }
}
