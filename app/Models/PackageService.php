<?php

namespace App\Models;

use DB;
use Illuminate\Database\Eloquent\Model;

class PackageService extends Model
{
    protected static $_fillable = ['random_id', 'package_id', 'package_bundle_id', 'service_id', 'is_consumed', 'price', 'orignal_price', 'is_exclusive', 'tax_exclusive_price', 'tax_percenatage', 'tax_price', 'tax_including_price'];

    protected static $_table = 'package_services';

    protected $fillable = ['random_id', 'package_id', 'package_bundle_id', 'service_id', 'created_at', 'updated_at', 'is_consumed', 'price', 'orignal_price', 'is_exclusive', 'tax_exclusive_price', 'tax_percenatage', 'tax_price', 'tax_including_price'];

    protected $table = 'package_services';

    /*
     *save package service information
     *@param $data
     *@return mixed
    */

    public static function createPackageService($data)
    {
        $record = self::create($data);

        return $record;
    }

    /*
    * Get relation for service
    * */
    public function service()
    {
        return $this->belongsTo('App\Models\Services', 'service_id')->withTrashed();
    }

    /*
     * Get relation for Package
     *
     */
    public function package()
    {
        return $this->belongsTo('App\Models\Package', 'package_id')->withTrashed();
    }

    /*
     * Get relation for package bundle
     */
    public function packagebundle()
    {
        return $this->belongsTo('App\Models\PackageBundles', 'package_bundle_id')->withTrashed();
    }

    /**
     * save the package service information
     *
     *
     * @return mixed
     */
    public static function createRecord($packagebundle)
    {

        $parent_id = $packagebundle->id;

        self::where([
            ['random_id', '=', $packagebundle->random_id],
            ['package_bundle_id', '=', $packagebundle->id],
        ])->update(['package_id' => $packagebundle->package_id]);

        $packageservice = self::where('package_bundle_id', '=', $packagebundle->id)->get();

        foreach ($packageservice as $packageservice) {

            AuditTrails::addEventLogger(self::$_table, 'create', $packageservice, self::$_fillable, $packageservice, $parent_id);
        }

        return true;
    }

    /**
     * update the package service information
     *
     *
     * @return mixed
     */
    public static function updateRecord($packagebundle)
    {

        $parent_id = $packagebundle->id;

        DB::select(DB::raw("UPDATE package_services SET package_id = '$packagebundle->package_id' WHERE random_id = '$packagebundle->random_id' AND package_bundle_id = '$packagebundle->id'"));

        //  I use that code to perform update but it update updated_at col so that s why I use Raw query
        //        self::where([
        //            ['random_id', '=', $packagebundle->random_id],
        //            ['package_bundle_id','=',$packagebundle->id]
        //        ])->update(array('package_id' => $packagebundle->package_id));

        $packageservice = self::where('package_bundle_id', '=', $packagebundle->id)->get();

        foreach ($packageservice as $packageservice) {

            $old_data = '0';

            AuditTrails::editEventLogger(self::$_table, 'Edit', $packageservice, self::$_fillable, $old_data, $packageservice, $parent_id);
        }

        return true;
    }

    /**
     * update the package service information when invoice create
     *
     * @param $packagebundle
     * @return mixed
     */
    public static function updateRecordInvoice($packagesservice)
    {

        $parent_id = $packagesservice->package_bundle_id;

        $old_data = '0';

        AuditTrails::editEventLogger(self::$_table, 'Edit', $packagesservice, self::$_fillable, $old_data, $packagesservice, $parent_id);

        return true;
    }

    /**
     * update the package service information when invoice cancel
     *
     * @param $invoice_detail ,$account_id
     * @return mixed
     */
    public static function InvoiceCancel($invoice_detail, $account_id)
    {

        $package_service = self::find($invoice_detail->package_service_id);

        $old_data = $package_service->toArray();

        $parent_id = $package_service->package_bundle_id;

        $record = $package_service->update(['is_consumed' => '0']);

        $record = self::find($invoice_detail->package_service_id)->toArray();

        AuditTrails::editEventLogger(self::$_table, 'Edit', $record, self::$_fillable, $old_data, $record, $parent_id);

        return true;
    }
}
