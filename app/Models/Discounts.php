<?php

namespace App\Models;

use App\HelperModule\ApiHelper;
use Auth;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;

class Discounts extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['name', 'type', 'amount', 'discount_type', 'pre_days', 'post_days', 'start', 'end', 'active', 'service_id', 'location_id', 'created_at', 'updated_at', 'account_id', 'slug'];

    protected static $_fillable = ['name', 'type', 'amount', 'discount_type', 'pre_days', 'post_days', 'start', 'end', 'active', 'slug'];

    protected $table = 'discounts';

    protected static $_table = 'discounts';

    protected $casts = [
        'created_at' => 'datetime:F d,Y h:i A',
    ];

    public function setStartAttribute($start)
    {
        $this->attributes['start'] = $this->dateFormat($start);
    }

    public function setEndAttribute($end)
    {
        $this->attributes['end'] = $this->dateFormat($end);
    }

    public function getStartAttribute($start)
    {
        return $this->dateFormat($start, 'F d,Y');
    }

    public function getEndAttribute($end)
    {
        return $this->dateFormat($end, 'F d,Y');
    }

    /**
     * Get the Users.
     */
    public function discounthaslocation()
    {

        return $this->hasMany('App\Models\DiscountHasLocations', 'discount_id');
    }

    /**
     * Create Record
     *
     * @param data
     * @return (mixed)
     */
    public static function createDiscount($data)
    {

        $record = self::create($data);

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

        return $record;
    }
    public static function createConfigurableDiscount($data)
    {
   

        $discount = Discounts::Create([
            'slug' =>$data['slug'],
            'name' =>$data['name'],
            'type' =>$data['type'],
        
            'amount' => "0",
            'discount_type' =>$data['discount_type'],
            'start' =>$data['start'],
            'end' =>$data['end'],
            'active' =>$data['active'],
            'account_id' =>1,
        ]);
        $base_service_price = Services::whereId($data['base_service'])->first();
        $base_discount_service = BaseDiscountService::Create([
            'discount_id' =>$discount->id,
            'service_id' =>$data['base_service'],
            'service_price' =>$base_service_price->price,
            'sessions'=>$data['sessions_buy'],
        ]);
        $service_check = [];
       foreach($data['service'] as $service){
       
        foreach($service as $key=>$value){
            if(!in_array($key, $service_check)){
            array_push($service_check, $key);
            }
        }
       }
       foreach($service_check as $value){
       
        $service_price = Services::find($data['service']['services_name'][$value]);
        $find_bundle = Bundles::where('name',$service_price->name)->first();
        $store = new GetDiscountService();
        $store->sessions = $data['service']['sessions_get'][$value];
        $store->service_id =$data['service']['services_name'][$value];
        $store->service_price =$service_price->price;
        $store->bundle_id =$find_bundle->id;
        $store->base_service_id =$data['base_service'];
        $store->discount_id =$discount->id;
        $store->save();
    }
        
        

        return $discount;
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
        return $this->hasMany('App\Models\AuditTrailChanges', 'field_before');
    }

    public function audit_field_after()
    {
        return $this->hasMany('App\Models\AuditTrailChanges', 'field_after');
    }
    /*end*/

    /**
     * update Record
     *
     * @param data id
     * @return (mixed)
     */
    public static function updateDiscount($data, $id)
    {

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
     * @return (mixed)
     */
    public static function inactiveRecord($id)
    {

        $discount = Discounts::getData($id);

        if ($discount == null) {

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
     * @return (mixed)
     */
    public static function activeRecord($id)
    {

        $discount = Discounts::getData($id);

        if ($discount == null) {

            return false;

        } else {

            $record = $discount->update(['active' => 1]);

            AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

            return $record;
        }
    }

    /**
     * delete Record
     *
     * @param id
     * @return (mixed)
     */
    public static function deleteRecord($id)
    {

        $discount = Discounts::getData($id);

        if (! $discount) {

            flash('Resource not found.')->error()->important();

            return redirect()->route('admin.discounts.index');
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (Discounts::isChildExists($id, Auth::User()->account_id)) {

            //flash('Child records exist, unable to delete resource')->error()->important();
            //return redirect()->route('admin.discounts.index');
            return 'Child records exist, unable to delete resource.'; //ApiHelper::apiResponse(200, 'Child records exist, unable to delete resource.');
        }

        $record = $discount->delete();

        //log request for delete for audit trail

        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        flash('Record has been deleted successfully.')->success()->important();

        return 'Record has been deleted successfully';
    }

    /**
     * IChild Exists or not
     *
     * @param id
     * @return (mixed)
     */
    public static function isChildExists($id, $account_id)
    {
        if (
            DiscountHasLocations::where(['discount_id' => $id])->count() ||
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
     * @return (mixed)
     */
    public static function getDiscount($account_id)
    {

        $date = Carbon::now();

        return self::where([
            ['start', '<=', $date],
            ['end', '>=', $date],
            ['active', '=', '1'],
            ['account_id', '=', $account_id],
        ])->get();
    }

    /**
     * Get Discount data
     *
     * @param id
     * @return (mixed)
     */
    public static function getDiscountforreport($account_id)
    {

        $date = Carbon::now();

        return self::where([
            ['active', '=', '1'],
            ['account_id', '=', $account_id],
        ])->get();
    }
}
