<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use App\Models\AuditTrails;
use Auth;

class PackageBundles extends Model
{
    use SoftDeletes;

    protected $fillable = ['random_id', 'qty','discount_name', 'discount_type', 'discount_price', 'service_price', 'net_amount','is_exclusive','tax_exclusive_net_amount','tax_percenatage','tax_price','tax_including_price','location_id', 'discount_id', 'bundle_id', 'package_id', 'active', 'created_at', 'updated_at', 'deleted_at'];

    protected static $_fillable = ['qty','discount_name', 'discount_type', 'discount_price', 'service_price', 'net_amount','is_exclusive','tax_exclusive_net_amount','tax_percenatage','tax_price','tax_including_price','location_id', 'discount_id', 'bundle_id', 'package_id', 'active'];

    protected $table = 'package_bundles';

    protected static $_table = 'package_bundles';

    /*
     *save package information
     *@param $data
     *@return mixed
     *  */
    static public function createPackagebundle($data)
    {
        $record = self::create($data);

        return $record;
    }

    /*
     * Get relation for service
     * */
    public function bundle()
    {
        return $this->belongsTo('App\Models\Bundles', 'bundle_id')->withTrashed();
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
     * Create Record
     *
     * @param $package
     *
     * @return mixed
     * */
    static public function createRecord($package,$request){

        $parent_id = $package->id;

        $updateDetails = [
            'package_id' => $package->id,
            'is_allocate' => 1
        ];

        foreach ($request['package_bundles'] as $bundle_id){
            self::where([
                ['id', '=', $bundle_id],
                ['random_id','=',$package->random_id]
            ])->update($updateDetails);
        }

        $packagebundle = self::where([
            ['package_id', '=', $package->id],
            ['is_allocate','=','1']
        ])->get();

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
    static public function updateRecord($package,$request){

        $parent_id = $package->id;

        $updateDetails = [
            'package_id' => $package->id,
            'is_allocate' => 1
        ];
        /*Look If package_bundle not present so means package_service also not present so that s why no need to apply condition in package service model*/
        if($request['package_bundles']){
            foreach ($request['package_bundles'] as $bundle_id){
                self::where([
                    ['id', '=', $bundle_id],
                    ['random_id','=',$package->random_id]
                ])->update($updateDetails);
            }
            $packagebundle = PackageBundles::where([
                ['package_id', '=', $package->id],
                ['is_allocate','=','1']
            ])->get();

            foreach ($packagebundle as $packagebundle) {

                $old_data = '0';

                AuditTrails::editEventLogger(self::$_table, 'Edit', $packagebundle, self::$_fillable, $old_data, $packagebundle, $parent_id);

                $packageservice = PackageService::updateRecord($packagebundle);
            }
        }
        return true;
    }
}
