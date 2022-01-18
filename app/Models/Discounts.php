<?php

namespace App\Models;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\SoftDeletes;
use DB;
use Illuminate\Support\Facades\Input;
use App\Models\DiscountHasLocations;
use App\Models\AuditTrails;
use Auth;
use Carbon\Carbon;

class Discounts extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['name', 'type', 'amount','discount_type','pre_days','post_days','start','end','active','service_id','location_id','created_at', 'updated_at','account_id','slug'];

    protected static $_fillable = ['name', 'type', 'amount','discount_type','pre_days','post_days','start', 'end','active','slug'];

    protected $table = 'discounts';

    protected static $_table = 'discounts';

    protected $casts = [
        'created_at' => 'datetime:F d,Y h:i A'
    ];

    public function setStartAttribute($start) {
        $this->attributes['start'] = $this->dateFormat($start);
    }

    public function setEndAttribute($end) {
        $this->attributes['end'] = $this->dateFormat($end);
    }

    public function getStartAttribute($start) {
        return $this->dateFormat($start, "F d,Y");
    }

    public function getEndAttribute($end) {
        return $this->dateFormat($end, "F d,Y");
    }

    /**
     * Get the Users.
     */
    public function discounthaslocation(){

        return $this->hasMany('App\Models\DiscountHasLocations', 'discount_id');
    }

    /**
     * Create Record
     *
     * @param data
     *
     * @return (mixed)
     */
    static public function createDiscount($data){

        $record = self::create($data);

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

        return $record;
    }
    /**
     * Get the Package Service.
     */
    public function packageservice()
    {
        return $this->hasMany('App\Models\PackageBundles', 'discount_id');
    }

    /*Relation for audit trail*/
    public function audit_field_before()
    {
        return $this->hasMany('App\Models\AuditTrailChanges','field_before');
    }
    public function audit_field_after()
    {
        return $this->hasMany('App\Models\AuditTrailChanges','field_after');
    }
    /*end*/

    /**
     * update Record
     *
     * @param data id
     *
     * @return (mixed)
     */
    static public function updateDiscount($data,$id){

        $old_data = (Discounts::find($id))->toArray();

        $record = Discounts::findOrFail($id);

        $record->update($data);

        AuditTrails::EditEventLogger(self::$_table, 'edit', $data, self::$_fillable, $old_data, $id);

        return $record;
    }
    /**
     * inactive Record
     *
     * @param id
     *
     * @return (mixed)
     */
    static public function inactiveRecord($id){

        $discount = Discounts::getData($id);

        if($discount==null) {

            return false;

        } else {

            $record = $discount->update(['active' => 0]);

            AuditTrails::InactiveEventLogger(self::$_table, 'inactive', self::$_fillable, $id);

            return $record;
        }

    }
    /**
     * active Record
     *
     * @param id
     *
     * @return (mixed)
     */
    static public function activeRecord($id){

        $discount = Discounts::getData($id);

        if($discount==null) {

            return false;

        } else{

            $record = $discount->update(['active' => 1]);

            AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

            return $record;
        }
    }
    /**
     * delete Record
     *
     * @param id
     *
     * @return (mixed)
     */
    static public function deleteRecord($id){

        $discount = Discounts::getData($id);

        if (!$discount) {

            flash('Resource not found.')->error()->important();
            return redirect()->route('admin.discounts.index');
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (Discounts::isChildExists($id, Auth::User()->account_id)) {

            flash('Child records exist, unable to delete resource')->error()->important();
            return redirect()->route('admin.discounts.index');
        }

        $record = $discount->delete();

        //log request for delete for audit trail

        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        flash('Record has been deleted successfully.')->success()->important();

        return $record;
    }
    /**
     * IChild Exists or not
     *
     * @param id
     *
     * @return (mixed)
     */
    static public function isChildExists($id, $account_id)
    {
        if (
            DiscountHasLocations::where(['discount_id' => $id])->count()||
            PackageBundles::where(['discount_id' => $id])->count()
        ) {
            return true;
        }

        return false;
    }
    /**
     * Get Discount data
     *
     * @param id
     *
     * @return (mixed)
     */
    static public function getDiscount($account_id)
    {

        $date = Carbon::now();
        return self::where([
            ['start','<=',$date],
            ['end','>=',$date],
            ['active','=','1'],
            ['account_id', '=', $account_id]
        ])->get();
    }
    /**
     * Get Discount data
     *
     * @param id
     *
     * @return (mixed)
     */
    static public function getDiscountforreport($account_id)
    {

        $date = Carbon::now();
        return self::where([
            ['active','=','1'],
            ['account_id', '=', $account_id]
        ])->get();
    }




}
