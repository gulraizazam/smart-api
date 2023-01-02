<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Helpers\ACL;
use App\Helpers\Filters;

class Locations extends BaseModal
{
    use SoftDeletes;

    protected $fillable = [

        'name', 'fdo_name', 'fdo_phone', 'account_id', 'slug',
        'address', 'google_map', 'region_id', 'city_id', 'active', 'sort_no', 'created_at', 'updated_at', 'parent_id', 'image_src', 'tax_percentage', 'ntn', 'stn'
    ];

    protected static $_fillable = ['name', 'fdo_name', 'fdo_phone', 'slug', 'address', 'google_map', 'region_id', 'city_id', 'active', 'parent_id', 'image_src', 'tax_percentage', 'ntn', 'stn'];

    protected $table = 'locations';

    protected static $_table = 'locations';

    /**
     * sent the city data to resource has rota.
     */
    public function resourcehasrota()
    {
        return $this->hasMany('App\Models\ResourceHasRota', 'location_id');
    }

    /**
     * sent the location name to resource with location_id.
     */
    public function resource()
    {

        return $this->hasMany('App\Models\Resources', 'location_id');
    }

    /**
     * Get the locations.
     */
    public function doctorhaslocation()
    {

        return $this->hasMany('App\Models\DoctorHasLocations', 'location_id');
    }

    /**
     * Get the locations.
     */
    public function discounthaslocation()
    {

        return $this->hasMany('App\Models\DiscountHasLocations', 'location_id');
    }

    /**
     * Get the Locations that owns the City.
     */
    public function city()
    {
        return $this->belongsTo('App\Models\Cities')->withTrashed();
    }


    /**
     * Get the Locations that owns the City.
     */
    public function region()
    {
        return $this->belongsTo('App\Models\Regions')->withTrashed();
    }

    /**
     * Get the doctors for location.
     */
    public function doctors()
    {
        return $this->hasMany('App\Models\Doctors', 'location_id');
    }

    /**
     * Get the doctors for location.
     */
    public function doctorsActive()
    {
        return $this->hasMany('App\Models\Doctors', 'location_id')->where(['active' => 1]);
    }

    /**
     * Get the appointments for location.
     */
    public function appointments()
    {
        return $this->hasMany('App\Models\Appointments', 'location_id');
    }

    /**
     * Get location.
     */
    public function packageadvances()
    {
        return $this->hasMany('App\Models\PackageAdvances', 'location_id');
    }

    public function audit_field_before()
    {
        return $this->hasMany('App\Models\AuditTrailChanges', 'field_before');
    }

    public function audit_field_after()
    {
        return $this->hasMany('App\Models\AuditTrailChanges', 'field_after');
    }

    /**
     * Get the Location name with City Name.
     */
    public function getFullAddressAttribute($value)
    {
        return ucfirst($this->city->name ?? '') . ' - ' . ucfirst($this->name ?? '');
    }

    /**
     * Get the locations.
     */
    public function package()
    {

        return $this->hasMany('App\Models\Packages', 'location_id');
    }

    /**
     * Get active and sorted data only.
     */
    static public function getActiveSorted($locationId = false, $name = 'name')
    {
        if ($locationId && !is_array($locationId)) {
            $locationId = array($locationId);
        }
        if ($locationId) {
            return self::whereIn('id', $locationId)->where([
                ['account_id', '=', Auth::User()->account_id],
                ['active', '=', '1'],
                ['slug', '=', 'custom']
            ])->get()->pluck($name, 'id');
        } else {
            return self::where([
                ['account_id', '=', Auth::User()->account_id],
                ['active', '=', '1'],
                ['slug', '=', 'custom']
            ])->get()->pluck($name, 'id');
        }
    }

    static public function getActiveSortedLocations($locationId = false)
    {
        if ($locationId && !is_array($locationId)) {
            $locationId = array($locationId);
        }
        if ($locationId) {
            return self::whereIn('id', $locationId)->where([
                ['account_id', '=', Auth::User()->account_id],
                ['active', '=', '1'],
                ['slug', '=', 'custom']
            ])->get();
        } else {
            return self::where([
                ['account_id', '=', Auth::User()->account_id],
                ['active', '=', '1'],
                ['slug', '=', 'custom']
            ])->get();
        }
    }

    /**
     * Get active and sorted data only for staff wise report.
     */
    static public function getActiveSortedStaffwisereport($locationId = false, $name = 'name')
    {
        if ($locationId && !is_array($locationId)) {
            $locationId = array($locationId);
        }
        if ($locationId) {
            return self::whereIn('id', $locationId)->where([
                ['account_id', '=', Auth::User()->account_id],
                ['active', '=', '1'],
                ['slug', '=', 'custom']
            ])->select('id')->get();
        } else {
            return self::where([
                ['account_id', '=', Auth::User()->account_id],
                ['active', '=', '1'],
                ['slug', '=', 'custom']
            ])->select('id')->get();
        }
    }


    /**
     * Get active and sorted data only.
     */
    static public function getLocationActiveSorted($locationId = false)
    {
        if ($locationId && !is_array($locationId)) {
            $locationId = array($locationId);
        }
        if ($locationId) {
            return self::whereIn('id', $locationId)->where([
                ['account_id', '=', Auth::User()->account_id],
                ['active', '=', '1'],
                ['slug', '=', 'custom']
            ])->get();
        } else {
            return self::where([
                ['account_id', '=', Auth::User()->account_id],
                ['active', '=', '1'],
                ['slug', '=', 'custom']
            ])->get();
        }
    }


    /**
     * Get active and sorted data only.
     */
    static public function getlocation($locationId = false)
    {
        if ($locationId && !is_array($locationId)) {
            $locationId = array($locationId);
        }
        if ($locationId) {
            return self::whereIn('id', $locationId)->where('account_id', '=', Auth::User()->account_id)->get()->pluck('name', 'id');
        } else {
            return self::where('account_id', '=', Auth::User()->account_id)->pluck('name', 'id');
        }
    }

    /**
     * Get active and sorted data only for general revenue summary report.
     */
    static public function generalrevenuegetActiveSorted($locationId = false, $region_id)
    {
        if ($locationId && !is_array($locationId)) {
            $locationId = array($locationId);
        }
        if ($locationId) {
            return self::whereIn('id', $locationId)->where([
                ['account_id', '=', Auth::User()->account_id],
                ['active', '=', '1'],
                ['slug', '=', 'custom'],
                ['region_id', '=', $region_id]
            ])->get()->pluck('name', 'id');
        } else {
            return self::where([
                ['account_id', '=', Auth::User()->account_id],
                ['active', '=', '1'],
                ['slug', '=', 'custom'],
                ['region_id', '=', $region_id]
            ])->get()->pluck('name', 'id');
        }
    }


    /**
     * Get Total Records
     *
     * @param \Illuminate\Http\Request $request
     * @param (int) $account_id Current Organization's ID
     *
     * @return (mixed)
     */
    static public function getTotalRecords(Request $request, $account_id = false, $apply_filter = false)
    {
        $where = Self::locations_filters($request, $account_id, $apply_filter);
        if (count($where)) {
            if(\Illuminate\Support\Facades\Gate::allows("view_inactive_records")){
                return count(DB::table('locations')
                    ->leftJoin('service_has_locations', 'locations.id', '=', 'service_has_locations.location_id')
                    ->where($where)
                    ->whereIn('id', ACL::getUserCentres())
                    ->groupBy('service_has_locations.location_id')
                    ->get());
            }else{
                return count(DB::table('locations')
                    ->leftJoin('service_has_locations', 'locations.id', '=', 'service_has_locations.location_id')
                    ->where($where)
                    ->where('locations.active',1)
                    ->whereIn('id', ACL::getUserCentres())
                    ->groupBy('service_has_locations.location_id')
                    ->get());
            }
        }
    }


    /**
     * Get Total Records for target
     *
     * @param \Illuminate\Http\Request $request
     * @param (int) $account_id Current Organization's ID
     *
     * @return (mixed)
     */
    static public function getTotalRecords_target(Request $request, $account_id = false, $apply_filter = false)
    {
        $where = Self::staff_target_location_filters($request, $account_id, $apply_filter);
        if (count($where)) {
            return count(DB::table('locations')
                ->leftJoin('service_has_locations', 'locations.id', '=', 'service_has_locations.location_id')
                ->where($where)
                ->whereIn('id', ACL::getUserCentres())
                ->groupBy('service_has_locations.location_id')
                ->get());
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
    static public function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false, $apply_filter = false)
    {
        $where = self::locations_filters($request, $account_id, $apply_filter);

        if ($request->has('sort')) {
            list($orderBy, $order) = getSortBy($request);
            $orderColumn = $orderBy;

            if ($orderBy == 'created_at') {
                $orderBy = 'locations.created_at';
            }

            Filters::put(Auth::User()->id, 'locations', 'order_by', $orderBy);
            Filters::put(Auth::User()->id, 'locations', 'order', $order);
        } else {
            if (
                Filters::get(Auth::User()->id, 'locations', 'order_by')
                && Filters::get(Auth::User()->id, 'locations', 'order')
            ) {
                $orderBy = Filters::get(Auth::User()->id, 'locations', 'order_by');
                $order = Filters::get(Auth::User()->id, 'locations', 'order');

                if ($orderBy == 'created_at') {
                    $orderBy = 'locations.created_at';
                }
            } else {
                $orderBy = 'created_at';
                $order = 'desc';
                if ($orderBy == 'created_at') {
                    $orderBy = 'locations.created_at';
                }

                Filters::put(Auth::User()->id, 'locations', 'order_by', $orderBy);
                Filters::put(Auth::User()->id, 'locations', 'order', $order);
            }
        }
        if (count($where)) {
            if(\Illuminate\Support\Facades\Gate::allows("view_inactive_records")){
                return DB::table('locations')
                    ->leftJoin('service_has_locations', 'locations.id', '=', 'service_has_locations.location_id')
                    ->where($where)
                    ->whereIn('id', ACL::getUserCentres())
                    ->whereNull('deleted_at')
                    ->groupBy('service_has_locations.location_id', 'locations.id')
                    ->orderby('sort_no', 'asc')
                    ->limit($iDisplayLength)->offset($iDisplayStart)->get();
            }else{
                return DB::table('locations')
                ->leftJoin('service_has_locations', 'locations.id', '=', 'service_has_locations.location_id')
                ->where($where)
                ->where('active',1)
                ->whereIn('id', ACL::getUserCentres())
                ->whereNull('deleted_at')
                ->groupBy('service_has_locations.location_id', 'locations.id')
                ->orderby('sort_no', 'asc')
                ->limit($iDisplayLength)->offset($iDisplayStart)->get();
            }
        }
    }

    /**
     * Get Records target
     *
     * @param \Illuminate\Http\Request $request
     * @param (int) $iDisplayStart Start Index
     * @param (int) $iDisplayLength Total Records Length
     * @param (int) $account_id Current Organization's ID
     *
     * @return (mixed)
     */
    static public function getRecords_target(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false, $apply_filter = false)
    {
        $where = Self::staff_target_location_filters($request, $account_id, $apply_filter);

        if (count($where)) {
            return DB::table('locations')
                ->leftJoin('service_has_locations', 'locations.id', '=', 'service_has_locations.location_id')
                ->where($where)
                ->whereIn('id', ACL::getUserCentres())
                ->whereNull('deleted_at')
                ->groupBy('service_has_locations.location_id', 'locations.id')
                ->limit($iDisplayLength)->offset($iDisplayStart)->get();
        }
    }


    /**
     * Get filters
     *
     * @param \Illuminate\Http\Request $request
     * @param (int) $account_id Current Organization's ID
     * @param (boolean) $apply_filter
     * @return (mixed)
     */
    static public function locations_filters($request, $account_id, $apply_filter)
    {
        $filters = getFilters($request->all());

        $where = array();
        if ($account_id) {
            $where[] = array(
                'locations.account_id',
                '=',
                $account_id
            );
            Filters::put(Auth::User()->id, 'locations', 'account_id', $account_id);
        } else {

            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'locations', 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, 'locations', 'account_id')) {
                    $where[] = array(
                        'locations.account_id',
                        '=',
                        Filters::get(Auth::User()->id, 'locations', 'account_id')
                    );
                }
            }
        }
        if (hasFilter($filters, 'name')) {
            $where[] = array(
                'locations.name',
                'like',
                '%' . $filters['name'] . '%'
            );
            Filters::put(Auth::User()->id, 'locations', 'name', $filters['name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'locations', 'name');
            } else {
                if (Filters::get(Auth::User()->id, 'locations', 'name')) {
                    $where[] = array(
                        'locations.name',
                        'like',
                        '%' . Filters::get(Auth::User()->id, 'locations', 'name') . '%'
                    );
                }
            }
        }
        if (hasFilter($filters, 'fdo_name')) {
            $where[] = array(
                'fdo_name',
                'like',
                '%' . $filters['fdo_name'] . '%'
            );
            Filters::put(Auth::User()->id, 'locations', 'fdo_name', $filters['fdo_name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'locations', 'fdo_name');
            } else {
                if (Filters::get(Auth::User()->id, 'locations', 'fdo_name')) {
                    $where[] = array(
                        'fdo_name',
                        'like',
                        '%' . Filters::get(Auth::User()->id, 'locations', 'fdo_name') . '%'
                    );
                }
            }
        }
        if (hasFilter($filters, 'fdo_phone')) {
            $where[] = array(
                'fdo_phone',
                'like',
                '%' . $filters['fdo_phone'] . '%'
            );
            Filters::put(Auth::User()->id, 'locations', 'fdo_phone', $filters['fdo_phone']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'locations', 'fdo_phone');
            } else {
                if (Filters::get(Auth::User()->id, 'locations', 'fdo_phone')) {
                    $where[] = array(
                        'fdo_phone',
                        'like',
                        '%' . Filters::get(Auth::User()->id, 'locations', 'fdo_phone') . '%'
                    );
                }
            }
        }
        if (hasFilter($filters, 'address')) {
            $where[] = array(
                'locations.address',
                'like',
                '%' . $filters['address'] . '%'
            );
            Filters::put(Auth::User()->id, 'locations', 'address', $filters['address']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'locations', 'address');
            } else {
                if (Filters::get(Auth::User()->id, 'locations', 'address')) {
                    $where[] = array(
                        'locations.address',
                        'like',
                        '%' . Filters::get(Auth::User()->id, 'locations', 'address') . '%'
                    );
                }
            }
        }
        if (hasFilter($filters, 'city_id')) {
            $where[] = array(
                'locations.city_id',
                '=',
                $filters['city_id']
            );
            Filters::put(Auth::User()->id, 'locations', 'city_id', $filters['city_id']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'locations', 'city_id');
            } else {
                if (Filters::get(Auth::User()->id, 'locations', 'city_id')) {
                    $where[] = array(
                        'locations.city_id',
                        '=',
                        Filters::get(Auth::User()->id, 'locations', 'city_id')
                    );
                }
            }
        }
        if (hasFilter($filters, 'region_id')) {
            $where[] = array(
                'locations.region_id',
                '=',
                $filters['region_id']
            );
            Filters::put(Auth::User()->id, 'locations', 'region_id', $filters['region_id']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'locations', 'region_id');
            } else {
                if (Filters::get(Auth::User()->id, 'locations', 'region_id')) {
                    $where[] = array(
                        'locations.region_id',
                        '=',
                        Filters::get(Auth::User()->id, 'locations', 'region_id')
                    );
                }
            }
        }
        if (hasFilter($filters, 'service_id')) {
            $where[] = array(
                'service_has_locations.service_id',
                '=',
                $filters['service_id']
            );
            Filters::put(Auth::User()->id, 'locations', 'service_id', $filters['service']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'locations', 'service_id');
            } else {
                if (Filters::get(Auth::User()->id, 'locations', 'service_id')) {
                    $where[] = array(
                        'service_has_locations.service_id',
                        '=',
                        Filters::get(Auth::User()->id, 'locations', 'service_id')
                    );
                }
            }
        }
        if (hasFilter($filters, 'created_from')) {
            $where[] = array(
                'locations.created_at',
                '>=',
                $filters['created_from'] . ' 00:00:00'
            );
            Filters::put(Auth::User()->id, 'locations', 'created_from', $filters['created_from']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'locations', 'created_from');
            } else {
                if (Filters::get(Auth::User()->id, 'locations', 'created_from')) {
                    $where[] = array(
                        'locations.created_at',
                        '>=',
                        Filters::get(Auth::User()->id, 'locations', 'created_from') . ' 00:00:00'
                    );
                }
            }
        }
        if (hasFilter($filters, 'created_to')) {
            $where[] = array(
                'locations.created_at',
                '<=',
                $filters['created_to'] . ' 23:59:59'
            );
            Filters::put(Auth::User()->id, 'locations', 'created_to', $filters['created_to']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'locations', 'created_to');
            } else {
                if (Filters::get(Auth::User()->id, 'locations', 'created_to')) {
                    $where[] = array(
                        'locations.created_at',
                        '<=',
                        Filters::get(Auth::User()->id, 'locations', 'created_to') . ' 23:59:59'
                    );
                }
            }
        }

        if (hasFilter($filters, 'status')) {
            $where[] = array(
                'locations.active',
                '=',
                $filters['status']
            );
            Filters::put(Auth::user()->id, 'locations', 'status', $filters['status']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'locations', 'status');
            } else {
                if (Filters::get(Auth::user()->id, 'locations', 'status') == 0 || Filters::get(Auth::user()->id, 'locations', 'status') == 1) {
                    if (Filters::get(Auth::user()->id, 'locations', 'status') != null) {
                        $where[] = array(
                            'locations.active',
                            '=',
                            Filters::get(Auth::user()->id, 'locations', 'status')
                        );
                    }
                }
            }
        }

        $where[] = array(
            'slug',
            '=',
            'custom'
        );

//        dd( $where );

        return $where;
    }

    /**
     * Get filters
     *
     * @param \Illuminate\Http\Request $request
     * @param (int) $account_id Current Organization's ID
     * @param (boolean) $apply_filter
     * @return (mixed)
     */
    static public function staff_target_location_filters($request, $account_id, $apply_filter)
    {
        $where = array();
        if ($account_id) {
            $where[] = array(
                'locations.account_id',
                '=',
                $account_id
            );
            Filters::put(Auth::User()->id, 'staff_target_location', 'account_id', $account_id);
        } else {

            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'staff_target_location', 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, 'staff_target_location', 'account_id')) {
                    $where[] = array(
                        'locations.account_id',
                        '=',
                        Filters::get(Auth::User()->id, 'staff_target_location', 'account_id')
                    );
                }
            }
        }
        if ($request->get('lead_status_name')) {
            $where[] = array(
                'locations.name',
                'like',
                '%' . $request->get('lead_status_name') . '%'
            );
            Filters::put(Auth::User()->id, 'staff_target_location', 'lead_status_name', $request->get('lead_status_name'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'staff_target_location', 'lead_status_name');
            } else {
                if (Filters::get(Auth::User()->id, 'staff_target_location', 'lead_status_name')) {
                    $where[] = array(
                        'locations.name',
                        'like',
                        '%' . Filters::get(Auth::User()->id, 'staff_target_location', 'lead_status_name') . '%'
                    );
                }
            }
        }
        if ($request->get('lead_status_city')) {
            $where[] = array(
                'locations.city_id',
                '=',
                $request->get('lead_status_city')
            );
            Filters::put(Auth::User()->id, 'staff_target_location', 'lead_status_city', $request->get('lead_status_city'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'staff_target_location', 'lead_status_city');
            } else {
                if (Filters::get(Auth::User()->id, 'staff_target_location', 'lead_status_city')) {
                    $where[] = array(
                        'locations.city_id',
                        '=',
                        Filters::get(Auth::User()->id, 'staff_target_location', 'lead_status_city')
                    );
                }
            }
        }
        if ($request->get('region')) {
            $where[] = array(
                'locations.region_id',
                '=',
                $request->get('region')
            );
            Filters::put(Auth::User()->id, 'staff_target_location', 'region', $request->get('region'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'staff_target_location', 'region');
            } else {
                if (Filters::get(Auth::User()->id, 'staff_target_location', 'region')) {
                    $where[] = array(
                        'locations.region_id',
                        '=',
                        Filters::get(Auth::User()->id, 'staff_target_location', 'region')
                    );
                }
            }
        }
        $where[] = array(
            'slug',
            '=',
            'custom'
        );

        return $where;
    }


    /**
     * Get All Records with Dictionary
     *
     * @param (int) $account_id Current Organization's ID
     *
     * @return (mixed)
     */
    static public function getAllRecordsDictionary($account_id, $get_slug = false, $order_by = false, $order = false, $locationids = false)
    {
        if ($locationids && !is_array($locationids)) {
            $locationids = array($locationids);
        }
        if ($locationids) {

            if ($get_slug) {
                if ($order_by && $order) {
                    return self::where('account_id', '=', $account_id)->where('slug', '=', $get_slug)->whereIn('id', $locationids)->orderBy($order_by, $order)->get()->getDictionary();
                }

                return self::where('account_id', '=', $account_id)->where('slug', '=', $get_slug)->whereIn('id', $locationids)->get()->getDictionary();
            } else {
                if ($order_by && $order) {
                    return self::where('account_id', '=', $account_id)->orderBy($order_by, $order)->whereIn('id', $locationids)->get()->getDictionary();
                }

                return self::where('account_id', '=', $account_id)->get()->whereIn('id', $locationids)->getDictionary();
            }
        } else {
            if ($get_slug) {
                if ($order_by && $order) {
                    return self::where('account_id', '=', $account_id)->where('slug', '=', $get_slug)->orderBy($order_by, $order)->get()->getDictionary();
                }

                return self::where('account_id', '=', $account_id)->where('slug', '=', $get_slug)->get()->getDictionary();
            } else {
                if ($order_by && $order) {
                    return self::where('account_id', '=', $account_id)->orderBy($order_by, $order)->get()->getDictionary();
                }

                return self::where('account_id', '=', $account_id)->get()->getDictionary();
            }
        }
    }

    /**
     * Get All Records by City
     *
     * @param (int) $cityId City's ID
     * @param (int) $account_id Current Organization's ID
     *
     * @return (mixed)
     */
    static public function getActiveRecordsByCity($cityId = false, $locationId = false, $account_id)
    {
        $where = array();

        $where[] = ['account_id', '=', $account_id];
        $where[] = ['active', '=', '1'];

        if ($cityId) {
            $where[] = ['city_id', '=', $cityId];
        }

        if (is_array($locationId)) {
            return self::where($where)->whereIn('id', $locationId)->orderBy('name', 'asc')->get();
        } else {
            if ($locationId) {
                return self::where($where)->whereIn('id', array($locationId))->orderBy('name', 'asc')->get();
            } else {
                return self::where($where)->orderBy('name', 'asc')->get();
            }
        }
    }

    /**
     * Create Record
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return (mixed)
     */
    static public function createRecord($request, $account_id)
    {
        $data = $request->all();
        // Set Account ID
        $data['account_id'] = $account_id;
        // Set Region ID
        $data['region_id'] = Cities::findOrFail($data['city_id'])->region_id;
        //Set Image
        if ($request->file('file')) {
            $file = $request->file('file');
            $fileName = time().'-'.$file->getClientOriginalName();
            $file->storeAs('public/centre_logo', $fileName);
            $ext = $file->getClientOriginalExtension();
            $data['image_src'] = $fileName;
        }

        $record = self::create($data);
        $record->update(['sort_no' => $record->id]);
        //log request for Create for Audit Trail
        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);
        return $record;
    }

    /**
     * delete Record
     *
     * @param id
     *
     * @return (mixed)
     */
    static function deleteRecord($id)
    {
        $location = Locations::getData($id);

        if (!$location) {
            return [
                'status' => false,
                'message' => 'Resource not found.'
            ];
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (Locations::isChildExists($id, Auth::User()->account_id)) {
            return [
                'status' => false,
                'message' => 'Child records exist, unable to delete resource.'
            ];
        }

        $location->delete();

        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        return [
            'status' => true,
            'message' => 'Record has been deleted successfully.'
        ];

    }

    /**
     * Inactive Record
     *
     * @param id
     *
     * @return (mixed)
     */
    static function inactiveRecord($id)
    {

        $location = Locations::getData($id);

        if (!$location) {
            flash('Resource not found.')->error()->important();
            return redirect()->route('admin.locations.index');
        }

        $record = $location->update(['active' => 0]);

        AuditTrails::inactiveEventLogger(self::$_table, 'Inactive', self::$_fillable, $id);

        return $record;

    }

    /**
     * active Record
     *
     * @param id
     *
     * @return (mixed)
     */
    static function activeRecord($id, $status)
    {

        $location = Locations::getData($id);

        if (!$location) {
            return false;
        }

        $record = $location->update(['active' => $status]);

        AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

        return $record;

    }

    /**
     * Update Record
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return (mixed)
     */
    static public function updateRecord($id, $request, $account_id)
    {
        $old_data = (Locations::find($id))->toArray();

        $data = $request->all();

        // Set Account ID
        $data['account_id'] = $account_id;

        // Set Region ID
        $data['region_id'] = Cities::findOrFail($data['city_id'])->region_id;

        if (!isset($data['is_featured'])) {
            $data['is_featured'] = 0;
        } else if ($data['is_featured'] == '') {
            $data['is_featured'] = 0;
        }
        //Set Image
        if ($request->file('file')) {
            $file = $request->file('file');
            $fileName = time().'-'.$file->getClientOriginalName();
            $file->storeAs('public/centre_logo', $fileName);
            $ext = $file->getClientOriginalExtension();
            $data['image_src'] = $fileName;
        }
        $record = self::where([
            'id' => $id,
            'account_id' => $account_id
        ])->first();

        if (!$record) {
            return null;
        }

        $record->update($data);

        AuditTrails::editEventLogger(self::$_table, 'Edit', $data, self::$_fillable, $old_data, $id);

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
        return false;
    }

    public function service_has_locations()
    {
        return $this->hasMany('App\Models\ServiceHasLocations', 'location_id')->withoutGlobalScope(SoftDeletingScope::class);
    }

    /*
     * Function for target location data
     *
     */
    static function LoadtargetLocationdata($request)
    {

        $center_target_status = 0;
        $center_target_working_days = 0;

        $lcoations = Locations::where([
            ['active', '=', '1'],
            ['slug', '=', 'custom']
        ])->get();

        $targetlocationdata_existing = CentertargetMeta::where([
            ['year', '=', $request->get('year')],
            ['month', '=', $request->get('month')]
        ])->get();

        $CenterTargetArray = array();

        foreach ($lcoations as $location) {
            $CenterTargetArray[$location->id] = array(
                'location_id' => $location->id,
                'location_name' => $location->city->name . '  ' . $location->name,
                'target_amount' => 0,
            );
        }

        if ($targetlocationdata_existing->count()) {

            $center_target_status = 1;

            $center_target = Centertarget::where([
                ['year', '=', $request->get('year')],
                ['month', '=', $request->get('month')]
            ])->first();

            $center_target_working_days = $center_target->working_days;

            foreach ($targetlocationdata_existing as $locationdata) {
                $location_info = Locations::find($locationdata->location_id);
                $CenterTargetArray[$locationdata->location_id] = array(
                    'location_id' => $locationdata->location_id,
                    'location_name' => $location_info->city->name . '  ' . $location_info->name,
                    'target_amount' => $locationdata->target_amount,
                );
            }
        }
        return array('CenterTargetArray' => $CenterTargetArray, 'center_target_status' => $center_target_status,'center_target_working_days' => $center_target_working_days);

    }

}
