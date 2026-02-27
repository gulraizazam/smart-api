<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PackageBundles extends Model
{
    use SoftDeletes;

    protected $fillable = ['random_id', 'qty', 'discount_name', 'discount_type', 'discount_price', 'service_price', 'net_amount', 'is_exclusive', 'tax_exclusive_net_amount', 'tax_percenatage', 'tax_price', 'tax_including_price', 'location_id', 'discount_id', 'config_group_id', 'bundle_id', 'source_type', 'membership_type_id', 'membership_code_id', 'is_allocate', 'package_id', 'active', 'created_at', 'updated_at', 'deleted_at','base_service_id'];

    protected static $_fillable = ['qty', 'discount_name', 'discount_type', 'discount_price', 'service_price', 'net_amount', 'is_exclusive', 'tax_exclusive_net_amount', 'tax_percenatage', 'tax_price', 'tax_including_price', 'location_id', 'discount_id', 'bundle_id', 'package_id', 'active'];

    protected $table = 'package_bundles';

    protected static $_table = 'package_bundles';

    /*
     *save package information
     *@param $data
     *@return mixed
     *  */
    public static function createPackagebundle($data)
    {
       \Log::info('=== createPackagebundle CALLED ===', [
           'bundle_id_passed' => $data['bundle_id'] ?? 'NOT SET',
           'discount_id' => $data['discount_id'] ?? 'NOT SET',
           'random_id' => $data['random_id'] ?? 'NOT SET',
       ]);
      
       $package_id = Packages::where('random_id',$data['random_id'])->first();
        $discount_type = Discounts::find($data['discount_id']);
        
        if($discount_type && $discount_type->type =="Configurable" && $data['tax_including_price'] > 0 && $data['discount_type']==null){
            $find_base_service = BaseDiscountService::where('discount_id',$discount_type->id)->first();
            $data['discount_type'] = 'Configurable';
            $data['package_id'] =  $package_id->id ?? null;
            $data['base_service_id'] = $find_base_service->service_id;
            
        }
        else if($discount_type && $discount_type->type =="Configurable" && $data['tax_including_price'] > 0 && $data['discount_type']!=null){
            $find_base_service = BaseDiscountService::where('discount_id',$discount_type->id)->first();
            $data['discount_type'] = $data['discount_type'];
            $data['package_id'] =  $package_id->id ?? null;
            $data['base_service_id'] = $find_base_service->service_id;
            
        }else if($discount_type && $discount_type->type =="Configurable" && $data['tax_including_price'] == 0){
            $find_base_service = BaseDiscountService::where('discount_id',$discount_type->id)->first();
            $data['discount_type'] = 'Percentage';
            $data['discount_price'] = 100;
            $data['net_amount'] = 0;
            $data['tax_including_price'] = 0;
            $data['tax_exclusive_net_amount'] = 0;
            $data['tax_price'] = 0;
            $data['package_id'] = $package_id->id ?? null;
            $data['base_service_id'] = $find_base_service->service_id;
          
        }else{
            $data['discount_type'] = $data['discount_type'];
            $data['discount_price'] = $data['discount_price'];
            
        }
        
        // Always ensure package_id is set if not already
        if (!isset($data['package_id']) || $data['package_id'] === null) {
            $package_id = Packages::where('random_id', $data['random_id'])->first();
            if ($package_id) {
                $data['package_id'] = $package_id->id;
            }
        }
       
        $record = self::create($data);

        return $record;
    }

    /*
     * Get relation for bundle
     * */
    public function bundle()
    {
        return $this->belongsTo('App\Models\Bundles', 'bundle_id')->withTrashed();
    }

    /*
     * Get relation for service (used when bundle_id contains service_id for configurable discounts)
     * */
    public function service()
    {
        return $this->belongsTo('App\Models\Services', 'bundle_id')->withTrashed();
    }

    /*
     * Get relation for discount
     * */
    public function discount()
    {
        return $this->belongsTo('App\Models\Discounts', 'discount_id')->withTrashed();
    }

    /*
     * Get the service Relation
     */
    public function packageservice()
    {
        return $this->hasMany('App\Models\PackageService', 'package_bundle_id');
    }

    /*
     * Get relation for membership type
     */
    public function membershipType()
    {
        return $this->belongsTo('App\Models\MembershipType', 'membership_type_id');
    }

    /*
     * Get the package (plan) this bundle belongs to
     */
    public function package()
    {
        return $this->belongsTo('App\Models\Packages', 'package_id');
    }

    /*
     * Create Record
     *
     * @param $package
     *
     * @return mixed
     * */
    public static function createRecord($package, $request)
    {

        $parent_id = $package->id;
        $updateDetails = [
            'package_id' => $package->id,
            'is_allocate' => 1,
        ];
       
        foreach ($request['package_bundles'] as $bundle_id) {
            self::where([
                'id' => $bundle_id,
                'random_id' => $package->random_id,
            ])->update($updateDetails);
        }
        $packagebundle = self::where([
            'package_id' => $package->id,
            'is_allocate' => '1',
        ])->get();
        $packagebundleIds = self::where([
            'package_id' => $package->id,
            'is_allocate' => '1',
        ])->pluck('id');
        $GetPackage = Packages::findOrFail($packagebundle[0]->package_id);
        $GetAppointment = Appointments::join('invoices', 'appointments.id', 'invoices.appointment_id')
            ->select('appointments.id', 'appointments.service_id')
            ->where(['appointments.patient_id' => $GetPackage->patient_id, 'appointments.appointment_type_id' => 1])
            ->latest('invoices.created_at')->first();
        $GetInvoiceInfo = Invoices::where(['appointment_id' => $GetAppointment->id])->first();
        $package_services = PackageService::with('service')->whereIn('package_bundle_id', $packagebundleIds)
            ->where('created_at', '>', Carbon::parse($GetInvoiceInfo->created_at))
            ->get();
        foreach ($package_services as $packagebundl) {
            if ($packagebundl->service->parent_id != $GetAppointment->service_id) {
                $GetAppointment->update(['service_id' => $package_services[0]->service->parent_id]);
            }
        }
        foreach ($packagebundle as $packagebundle) {
            AuditTrails::addEventLogger(self::$_table, 'create', $packagebundle, self::$_fillable, $packagebundle, $parent_id);
            $packageservice = PackageService::createRecord($packagebundle);
        }

        return true;

    }

    /*
     * Update Record
     *
     * @param $package
     *
     * @return mixed
     * */
    public static function updateRecord($package, $request)
    {
        $parent_id = $package->id;
        $updateDetails = [
            'package_id' => $package->id,
            'is_allocate' => 1,
        ];
        /*Look If package_bundle not present so means package_service also not present so that s why no need to apply condition in package service model*/
        if ($request['package_bundles']) {
            foreach ($request['package_bundles'] as $bundle_id) {
                self::where([
                    ['id', '=', $bundle_id],
                    ['random_id', '=', $package->random_id],
                ])->update($updateDetails);
            }
            $packagebundle = PackageBundles::where([
                ['package_id', '=', $package->id],
                ['is_allocate', '=', '1'],
            ])->get();
            foreach ($packagebundle as $packagebundle) {
                $old_data = '0';
                AuditTrails::editEventLogger(self::$_table, 'Edit', $packagebundle, self::$_fillable, $old_data, $packagebundle, $parent_id);
                $packageservice = PackageService::updateRecord($packagebundle);
            }
        }
        $packagebundle = PackageBundles::where([
            'package_id' => $package->id,
            'is_allocate' => '1',
        ])->get();
        $packagebundleIds = self::where([
            'package_id' => $package->id,
            'is_allocate' => '1',
        ])->pluck('id');
        $GetPackage = Packages::findOrFail($package->id);
        $GetAppointment = Appointments::join('invoices', 'appointments.id', 'invoices.appointment_id')
            ->select('appointments.id', 'appointments.service_id')
            ->where(['appointments.patient_id' => $package->patient_id, 'appointments.appointment_type_id' => 1])
            ->latest('invoices.created_at')->first();
        $GetInvoiceInfo = Invoices::where(['appointment_id' => $GetAppointment->id])->first();
        $packageservicez = PackageService::with('service')->whereIn('package_bundle_id', $packagebundleIds)
            ->where('created_at', '>', Carbon::parse($GetInvoiceInfo->created_at))
            ->get();
        foreach ($packageservicez as $packagebundl) {
            if ($packagebundl->service->parent_id != $GetAppointment->service_id) {
                $GetAppointment->update(['service_id' => $packageservicez[0]->service->parent_id]);
            }
        }

        return true;
    }
}
