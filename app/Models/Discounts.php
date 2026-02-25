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
                'customer_type_id' => $data['customer_type_id'] ?? null,
            ]);

            $buyMode = $data['buy_mode'] ?? 'service'; // 'service' or 'category'
            $sessionCount = (int) $data['sessions_buy'];

            if ($buyMode === 'category') {
                // Category mode: base_service contains array of category IDs
                $categoryIds = is_array($data['base_service']) ? $data['base_service'] : [$data['base_service']];
                foreach ($categoryIds as $categoryId) {
                    $category = Services::find($categoryId);
                    if (!$category) continue;
                    for ($i = 0; $i < $sessionCount; $i++) {
                        BaseDiscountService::create([
                            'discount_id' => $discount->id,
                            'service_id' => $categoryId,
                            'service_price' => 0,
                            'sessions' => $sessionCount,
                            'is_category' => 1,
                            'bundle_id' => null,
                        ]);
                    }
                }
            } else {
                // Service mode: single service (existing behavior)
                $base_service = Services::find($data['base_service']);
                for ($i = 0; $i < $sessionCount; $i++) {
                    BaseDiscountService::create([
                        'discount_id' => $discount->id,
                        'service_id' => $data['base_service'],
                        'service_price' => $base_service->price,
                        'sessions' => $sessionCount,
                        'is_category' => 0,
                        'bundle_id' => null,
                    ]);
                }
            }

            // Determine the first base_service id for GET records
            $firstBaseServiceId = is_array($data['base_service']) ? $data['base_service'][0] : $data['base_service'];

            // Process GET services
            $sessions = $data['sessions'] ?? [];
            foreach ($sessions as $key => $sessionValue) {
                if (empty($sessionValue)) continue;

                $isSameService = isset($data['same_service'][$key]) && $data['same_service'][$key] == '1';
                $discountType = $data['disc_type'][$key] ?? 'complimentory';
                $discountAmount = isset($data['configurable_amount'][$key]) ? (float) $data['configurable_amount'][$key] : 0;

                if ($isSameService) {
                    // Same service: service_id=0 (placeholder), same_service=1
                    for ($i = 0; $i < (int) $sessionValue; $i++) {
                        GetDiscountService::create([
                            'discount_id' => $discount->id,
                            'service_id' => $firstBaseServiceId,
                            'same_service' => 1,
                            'service_price' => 0,
                            'base_service_id' => $firstBaseServiceId,
                            'sessions' => 1,
                            'discount_type' => $discountType,
                            'discount_amount' => $discountAmount,
                            'bundle_id' => null,
                        ]);
                    }
                } else {
                    if (empty($data['services_name'][$key])) continue;
                    $service = Services::find($data['services_name'][$key]);
                    if (!$service) continue;

                    for ($i = 0; $i < (int) $sessionValue; $i++) {
                        GetDiscountService::create([
                            'discount_id' => $discount->id,
                            'service_id' => $data['services_name'][$key],
                            'same_service' => 0,
                            'service_price' => $service->price,
                            'base_service_id' => $firstBaseServiceId,
                            'sessions' => 1,
                            'discount_type' => $discountType,
                            'discount_amount' => $discountAmount,
                            'bundle_id' => null,
                        ]);
                    }
                }
            }

            // Sync roles if provided
            if (isset($data['roles'])) {
                $discount->roles()->sync($data['roles']);
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
                'customer_type_id' => $data['customer_type_id'] ?? null,
            ]);

            // Rebuild base service records (BUY section)
            BaseDiscountService::where('discount_id', $id)->delete();
            
            $buyMode = $data['edit_buy_mode'] ?? 'service';
            $sessionCount = (int) $data['edit_sessions_buy'];

            if ($buyMode === 'category') {
                $categoryIds = is_array($data['edit_base_service']) ? $data['edit_base_service'] : [$data['edit_base_service']];
                foreach ($categoryIds as $categoryId) {
                    $category = Services::find($categoryId);
                    if (!$category) continue;
                    for ($i = 0; $i < $sessionCount; $i++) {
                        BaseDiscountService::create([
                            'discount_id' => $id,
                            'service_id' => $categoryId,
                            'service_price' => 0,
                            'sessions' => $sessionCount,
                            'is_category' => 1,
                            'bundle_id' => null,
                        ]);
                    }
                }
            } else {
                $baseService = Services::findOrFail($data['edit_base_service']);
                for ($i = 0; $i < $sessionCount; $i++) {
                    BaseDiscountService::create([
                        'discount_id' => $id,
                        'service_id' => $data['edit_base_service'],
                        'service_price' => $baseService->price,
                        'sessions' => $sessionCount,
                        'is_category' => 0,
                        'bundle_id' => null,
                    ]);
                }
            }

            $firstBaseServiceId = is_array($data['edit_base_service']) ? $data['edit_base_service'][0] : $data['edit_base_service'];

            // Rebuild GET service records
            $sessions = $data['edit_sessions'] ?? [];
            GetDiscountService::where('discount_id', $id)->delete();

            foreach ($sessions as $key => $sessionValue) {
                if (empty($sessionValue)) continue;

                $isSameService = isset($data['edit_same_service'][$key]) && $data['edit_same_service'][$key] == '1';
                $discountType = $data['edit_disc_type'][$key] ?? 'complimentory';
                $discountAmount = isset($data['configurable_amount'][$key]) ? (float) $data['configurable_amount'][$key] : 0;

                if ($isSameService) {
                    for ($i = 0; $i < (int) $sessionValue; $i++) {
                        GetDiscountService::create([
                            'discount_id' => $id,
                            'service_id' => $firstBaseServiceId,
                            'same_service' => 1,
                            'service_price' => 0,
                            'base_service_id' => $firstBaseServiceId,
                            'sessions' => 1,
                            'discount_type' => $discountType,
                            'discount_amount' => $discountAmount,
                            'bundle_id' => null,
                        ]);
                    }
                } else {
                    if (empty($data['edit_services_name'][$key])) continue;
                    $service = Services::find($data['edit_services_name'][$key]);
                    if (!$service) continue;

                    for ($i = 0; $i < (int) $sessionValue; $i++) {
                        GetDiscountService::create([
                            'discount_id' => $id,
                            'service_id' => $data['edit_services_name'][$key],
                            'same_service' => 0,
                            'service_price' => $service->price,
                            'base_service_id' => $firstBaseServiceId,
                            'sessions' => 1,
                            'discount_type' => $discountType,
                            'discount_amount' => $discountAmount,
                            'bundle_id' => null,
                        ]);
                    }
                }
            }

            $discount = Discounts::find($id);
            
            // Sync roles if provided
            if (isset($data['roles'])) {
                $discount->roles()->sync($data['roles']);
            }
            
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
