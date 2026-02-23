<?php

namespace App\Models;

use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\HelperModule\ApiHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class Discounts extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['name', 'type', 'amount', 'discount_type', 'pre_days', 'post_days', 'start', 'end', 'active', 'service_id', 'location_id', 'created_at', 'updated_at', 'account_id', 'slug', 'customer_type_id'];

    protected static $_fillable = ['name', 'type', 'amount', 'discount_type', 'pre_days', 'post_days', 'start', 'end', 'active', 'slug', 'customer_type_id'];

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
        if(isset($data['roles'])){
            $record->roles()->sync($data['roles']);
        }
      
        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

        return $record;
    }
    public static function createConfigurableDiscount($data)
    {
        return DB::transaction(function () use ($data) {
            // Create the discount record
            $discount = Discounts::Create([
                'slug' => $data['slug'] ?? 'default',
                'name' => $data['name'],
                'type' => $data['type'],
                'amount' => "0",
                'discount_type' => $data['discount_type'] ?? 'Treatment',
                'start' => $data['start'],
                'end' => $data['end'],
                'active' => $data['active'] ?? 0,
                'account_id' => $data['account_id'],
            ]);

            // Get base service info
            $base_service = Services::find($data['base_service']);
            $sessionCount = (int) $data['sessions_buy'];

            // Create base discount service records (BUY section)
            for ($i = 0; $i < $sessionCount; $i++) {
                BaseDiscountService::create([
                    'discount_id' => $discount->id,
                    'service_id' => $data['base_service'],
                    'service_price' => $base_service->price,
                    'sessions' => $sessionCount,
                    'bundle_id' => null, // No longer dependent on bundles
                ]);
            }

            // Process GET services
            $sessions = $data['sessions'] ?? [];
            foreach ($sessions as $key => $sessionValue) {
                if (empty($sessionValue) || empty($data['services_name'][$key])) {
                    continue;
                }

                $service = Services::find($data['services_name'][$key]);
                if (!$service) continue;

                $discountType = $data['disc_type'][$key] ?? 'complimentory';
                $discountAmount = isset($data['configurable_amount'][$key]) ? (float) $data['configurable_amount'][$key] : 0;

                // Create GET discount service records
                for ($i = 0; $i < (int) $sessionValue; $i++) {
                    GetDiscountService::create([
                        'discount_id' => $discount->id,
                        'service_id' => $data['services_name'][$key],
                        'service_price' => $service->price,
                        'base_service_id' => $data['base_service'],
                        'sessions' => 1,
                        'discount_type' => $discountType,
                        'discount_amount' => $discountAmount,
                        'bundle_id' => null, // No longer dependent on bundles
                    ]);
                }
            }

            AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $discount);

            return $discount;
        });
    }
    public static function updateConfigurableDiscount($data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            // Update discount record
            Discounts::where('id', $id)->update([
                'name' => $data['name'],
                'discount_type' => $data['discount_type'] ?? 'Treatment',
                'slug' => $data['slug'] ?? 'default',
                'type' => $data['type'],
                'amount' => $data['amount'] ?? 0,
                'start' => $data['start'],
                'end' => $data['end'],
                'active' => $data['active'] ?? 0,
            ]);

            // Update base service records (BUY section)
            $baseService = Services::findOrFail($data['edit_base_service']);
            BaseDiscountService::where('discount_id', $id)->delete();
            
            $sessionCount = (int) $data['edit_sessions_buy'];
            for ($i = 0; $i < $sessionCount; $i++) {
                BaseDiscountService::create([
                    'discount_id' => $id,
                    'service_id' => $data['edit_base_service'],
                    'service_price' => $baseService->price,
                    'sessions' => $sessionCount,
                    'bundle_id' => null, // No longer dependent on bundles
                ]);
            }

            // Update GET service records
            $sessions = $data['edit_sessions'] ?? [];
            GetDiscountService::where('discount_id', $id)->delete();

            foreach ($sessions as $key => $sessionValue) {
                if (empty($sessionValue) || empty($data['edit_services_name'][$key])) {
                    continue;
                }

                $service = Services::find($data['edit_services_name'][$key]);
                if (!$service) continue;

                $discountType = $data['edit_disc_type'][$key] ?? 'complimentory';
                $discountAmount = isset($data['configurable_amount'][$key]) ? (float) $data['configurable_amount'][$key] : 0;

                for ($i = 0; $i < (int) $sessionValue; $i++) {
                    GetDiscountService::create([
                        'discount_id' => $id,
                        'service_id' => $data['edit_services_name'][$key],
                        'service_price' => $service->price,
                        'base_service_id' => $data['edit_base_service'],
                        'sessions' => 1,
                        'discount_type' => $discountType,
                        'discount_amount' => $discountAmount,
                        'bundle_id' => null, // No longer dependent on bundles
                    ]);
                }
            }

            $discount = Discounts::find($id);
            AuditTrails::EditEventLogger(self::$_table, 'edit', $data, self::$_fillable, $discount->toArray(), $id);

            return $discount;
        });
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
        if(isset($data['roles'])){
            $record->roles()->sync($data['roles']);
        }
       
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

        if (!$discount) {

            flash('Resource not found.')->error()->important();

            return redirect()->route('admin.discounts.index');
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (Discounts::isChildExists($id, Auth::User()->account_id)) {

            flash('Child records exist, unable to delete resource.')->error()->important();
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
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'discount_role', 'discount_id', 'role_id');
    }
}
