<?php

namespace App\Models;

use App\Helpers\Filters;
use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use App\Models\AuditTrails;
use Auth;
use App\Helpers\ACL;
use PHPUnit\Util\Filter;
use App\Helpers\GeneralFunctions;
class Packages extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['random_id', 'name', 'sessioncount', 'total_price','is_exclusive','account_id', 'patient_id', 'active', 'created_at', 'updated_at', 'deleted_at', 'location_id','appointment_id', 'is_refund'];

    protected static $_fillable = ['name', 'sessioncount', 'total_price','is_exclusive', 'patient_id', 'active', 'location_id','appointment_id', 'is_refund', 'created_at', 'updated_at', 'deleted_at'];

    protected $table = 'packages';

    protected static $_table = 'packages';

    /*
     * get the data of patients from users table
     *
     * */
    public function user()
    {
        return $this->belongsTo('App\User', 'patient_id')->withTrashed();
    }
    /**
     * Get the packages.
     */
    public function packagesadvances(){

        return $this->hasMany('App\Models\PackageAdvances', 'package_id');
    }

    /*
    * get the data of location from location table
    *
    * */
    public function location()
    {
        return $this->belongsTo('App\Models\Locations', 'location_id')->withTrashed();
    }

    /*
     * get the data of appointment from package
     * */

    public function appointment()
    {
        $this->belongsTo(Appointments::class);
    }

    /*
     * get the data of appointment from package
     *
     */
    public function packageservice()
    {
        return $this->hasMany('App\Models\PackageService','package_id');
    }
    /*
     * Create Record
     *  @param: data
     * @return: mixed
     * */
    static public function createRecord($data,$request)
    {

        $record = self::create($data);

        $data['name'] = sprintf('%05d', $record->id);

        $record->update(['name' => sprintf('%05d', $record->id)]);

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

        $packagebundle = PackageBundles::createRecord($record,$request);

        return $record;
    }

    /*
     * Update Record
     * @param: data
     * @return: mixed
     * */
    static public function updateRecord($data,$random_id,$request){

        $record = self::where('random_id','=',$random_id)->first();

        $id = $record->id;

        $old_data = (self::find($record->id))->toArray();

        $record->update($data);

        AuditTrails::editEventLogger(self::$_table, 'Edit', $data, self::$_fillable,$old_data,$id);

        $packagebundle = PackageBundles::updateRecord($record,$request);

        return $record;
    }

    /*
    * Update Record when refu
    * @param: data
    * @return: mixed
    * */
    static public function updateRecordRefunds($package_id)
    {


        $record = self::where('id', '=', $package_id)->first();

        $id = $record->id;

        $old_data = (self::find($package_id))->toArray();

        $record->update(['is_refund' => '1']);

        AuditTrails::editEventLogger(self::$_table, 'Edit', $record, self::$_fillable, $old_data, $id);

        return $record;
    }

    /**
     * inactive Record
     *
     * @param id
     *
     * @return (mixed)
     */
    static public function inactiveRecord($id)
    {

        $package = Packages::getData($id);

        if (!$package) {
            flash('Resource not found.')->error()->important();
            return redirect()->route('admin.packages.index');
        }

        $record = $package->update(['active' => 0]);

        flash('Record has been inactivated successfully.')->success()->important();

        AuditTrails::InactiveEventLogger(self::$_table, 'inactive', self::$_fillable, $id);

        return $record;
    }

    /**
     * active Record
     *
     * @param id
     *
     * @return (mixed)
     */
    static function activeRecord($id)
    {

        $package = Packages::getData($id);

        if (!$package) {

            flash('Resource not found.')->error()->important();
            return redirect()->route('admin.packages.index');
        }

        $record = $package->update(['active' => 1]);

        flash('Record has been activated successfully.')->success()->important();

        AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

        return $record;
    }

    /**
     * Delete Record
     *
     * @param id
     *
     * @return (mixed)
     */
    static public function DeleteRecord($id)
    {
        $package = Packages::getData($id);

        if (!$package) {

            flash('Resource not found.')->error()->important();
            return redirect()->route('admin.packages.index');
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (Packages::isChildExists($id, Auth::User()->account_id)) {

            flash('Child records exist, unable to delete resource')->error()->important();
            return redirect()->route('admin.packages.index');
        }

        $record = $package->delete();

        //log request for delete for audit trail

        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        flash('Record has been deleted successfully.')->success()->important();

        return $record;
    }

    /**
     * Check if child records exist
     *
     * @param (int) $id
     * @param
     *
     * @return (boolean)
     */
    static public function isChildExists($id, $account_id)
    {
        if (
            InvoiceDetails::where(['package_id' => $id])->count() ||
            PackageAdvances::where(['package_id' => $id])->count()

        ) {
            return true;
        }

        return false;
    }


    /**
     * Get Total Records
     *
     * @param \Illuminate\Http\Request $request
     * @param (int) $account_id Current Organization's ID
     *
     * @return (mixed)
     */
    static public function getTotalRecords(Request $request, $account_id = false,$id = false , $apply_filter = false , $filename )
    {
        $where = self::filters( $request , $account_id , $id , $apply_filter , $filename );

        if (count($where)) {
            return self::where($where)->whereIn('location_id', ACL::getUserCentres())->count();
        } else {
            return self::whereIn('location_id', ACL::getUserCentres())->count();
        }
    }

    /**
     * Get Records
     *
     * @param \Illuminate\Http\Request $request
     * @param (int) $iDisplayStart Start Index
     * @param (int) $iDisplayLength Total Records Length
     * @param (int) $account_id Current Organization's ID
     *
     * @return (mixed)
     */
    static public function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false, $id = false , $apply_filter = false , $filename )
    {

        $where = self::filters( $request , $account_id , $id , $apply_filter , $filename );

        $orderBy = 'created_at';
        $order = 'desc';

        if ($request->get('order')[0]['dir']) {
            $orderColumn = $request->get('order')[0]['column'];
            $orderBy = $request->get('columns')[$orderColumn]['data'];
            $order = $request->get('order')[0]['dir'];
        }
        if (count($where)) {
            return self::where($where)->whereIn('location_id', ACL::getUserCentres())->limit($iDisplayLength)->offset($iDisplayStart)->orderby($orderBy,$order)->get();
        } else {
            return self::whereIn('location_id', ACL::getUserCentres())->limit($iDisplayLength)->offset($iDisplayStart)->orderby($orderBy,$order)->get();
        }
    }

    static public function filters( $request , $account_id , $id = false , $apply_filter , $filename ){
        $where = array();

        if($id != false){
            $where[] = array(
                'patient_id',
                '=',
                $id
            );
            Filters::put(Auth::user()->id,$filename,'patient_id', $id);
        } else {
            if ($apply_filter){
                Filters::forget(Auth::user()->id,$filename,'patient_id');
            } else {
                if (Filters::get(Auth::user()->id,$filename,'patient_id')){
                    $where[] = array(
                        'patient_id',
                        '=',
                        Filters::get(Auth::user()->id,$filename,'patient_id')
                    );
                }
            }
        }

        if ($account_id) {
            $where[] = array(
                'account_id',
                '=',
                $account_id
            );
            Filters::put(Auth::User()->id, $filename, 'account_id', $account_id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'account_id')) {
                    $where[] = array(
                        'account_id',
                        '=',
                        Filters::get(Auth::User()->id, $filename, 'account_id')
                    );
                }
            }
        }

        if ($request->get('patient_id') && $request->get('patient_id') != '' ) {
            $where[] = array(
                'patient_id',
                '=',
                $request->get('patient_id')
            );
            Filters::put(Auth::User()->id, $filename, 'patient_id', $request->get('patient_id'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'patient_id');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'patient_id')) {
                    $where[] = array(
                        'patient_id',
                        '=',
                        Filters::get(Auth::User()->id, $filename, 'patient_id')
                    );
                }
            }
        }
        if ($request->get('id') && $request->get('id') != '' ) {
            $where[] = array(
                'patient_id',
                '=',
                GeneralFunctions::patientSearch($request->get('id'))
            );
            Filters::put(Auth::User()->id, $filename, 'patient_id', GeneralFunctions::patientSearch($request->get('id')));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'id');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'id')) {
                    $where[] = array(
                        'patient_id',
                        '=',
                        Filters::get(Auth::User()->id, $filename, 'id')
                    );
                }
            }
        }

        if ($request->get('package_id') && $request->get('package_id') != '' ) {
            $where[] = array(
                'id',
                '=',
                $request->get('package_id')
            );
            Filters::put(Auth::User()->id, $filename, 'package_id', $request->get('package_id'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'package_id');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'package_id')) {
                    $where[] = array(
                        'id',
                        '=',
                        Filters::get(Auth::User()->id, $filename, 'package_id')
                    );
                }
            }
        }
        if ($request->get('created_from') && $request->get('created_from') != '') {
            $where[] = array(
                'created_at',
                '>=',
                $request->get('created_from') . ' 00:00:00'
            );
            Filters::put(Auth::User()->id, $filename, 'created_from', $request->get('created_from') . ' 00:00:00');
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'created_from');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'created_from')) {
                    $where[] = array(
                        'created_at',
                        '>=',
                        Filters::get(Auth::User()->id, $filename, 'created_from') . ' 00:00:00'
                    );
                }
            }
        }

        if ( $request->get('created_to') && $request->get('created_to') != '') {
            $where[] = array(
                'created_at',
                '<=',
                $request->get('created_to') . ' 23:59:59'
            );
            Filters::put(Auth::User()->id, $filename, 'created_to', $request->get('created_to') . ' 23:59:59');
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'created_to');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'created_to')) {
                    $where[] = array(
                        'created_at',
                        '<=',
                        Filters::get(Auth::User()->id, $filename, 'created_to') . ' 23:59:59'
                    );
                }
            }
        }

        if ($request->get('location_id') && $request->get('location_id')) {
            $where[] = array(
                'location_id',
                '=',
                $request->get('location_id')
            );
            Filters::put(Auth::User()->id, $filename, 'location_id', $request->get('location_id'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, $filename, 'location_id');
            } else {
                if (Filters::get(Auth::User()->id, $filename, 'location_id')) {
                    $where[] = array(
                        'location_id',
                        '=',
                        Filters::get(Auth::User()->id, $filename, 'location_id')
                    );
                }
            }
        }


        if ( $request->get('status') && $request->get('status') != null || $request->get('status') == 0 && $request->get('status') != null ){
            $where[] = array(
                'active',
                '=',
                $request->get('status')
            );
            Filters::put(Auth::user()->id, $filename, 'status', $request->get('status'));
        } else {
            if ( $apply_filter ){
                Filters::forget( Auth::user()->id, $filename, 'status');
            } else {
                if ( Filters::get(Auth::user()->id, $filename, 'status') == 0 || Filters::get(Auth::user()->id, $filename, 'status') == 1){
                    if ( Filters::get(Auth::user()->id, $filename, 'status') != null ){
                        $where[] = array(
                            'active',
                            '=',
                            Filters::get( Auth::user()->id, $filename, 'status')
                        );
                    }
                }
            }
        }

        return $where ;
    }
}
