<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\ACL;
use App\Helpers\Filters;
use App\Services\Reports\Concerns\ParsesDateRange;
use App\Support\SafeFilename;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class Locations extends BaseModel
{
    use ParsesDateRange;
    use SoftDeletes;

    /**
     * Resolve the factory class explicitly. The model is named `Locations`
     * (plural) but the factory is `LocationFactory` (singular) — Laravel's
     * auto-resolver only matches the exact `{ModelName}Factory` convention,
     * so without this hint the framework looks for `LocationsFactory` and
     * fails. Used by tests via Locations::factory().
     */
    protected static function newFactory(): LocationFactory
    {
        return LocationFactory::new();
    }

    protected static function booted(): void
    {
        $clearCache = function (self $model): void {
            $pattern = 'locations_active_sorted_'.$model->account_id;
            Cache::forget($pattern.'_name_all');
            Cache::forget('locations_dict_'.$model->account_id.'_0_0_0_all');
        };
        static::saved($clearCache);
        static::deleted($clearCache);
    }

    protected $fillable = [

        'name', 'fdo_name', 'fdo_phone', 'account_id', 'slug',
        'address', 'google_map', 'region_id', 'city_id', 'active', 'sort_no', 'created_at', 'updated_at', 'parent_id', 'image_src', 'tax_percentage', 'ntn', 'stn',
    ];

    protected static array $_fillable = ['name', 'fdo_name', 'fdo_phone', 'slug', 'address', 'google_map', 'region_id', 'city_id', 'active', 'parent_id', 'image_src', 'tax_percentage', 'ntn', 'stn'];

    protected $table = 'locations';

    protected static string $_table = 'locations';

    /**
     * sent the city data to resource has rota.
     */
    public function resourcehasrota(): HasMany
    {
        return $this->hasMany(ResourceHasRota::class, 'location_id');
    }

    /**
     * sent the location name to resource with location_id.
     */
    public function resource(): HasMany
    {

        return $this->hasMany(Resources::class, 'location_id');
    }

    /**
     * Get the locations.
     */
    public function doctorhaslocation(): HasMany
    {

        return $this->hasMany(DoctorHasLocations::class, 'location_id');
    }

    /**
     * Get the locations.
     */
    public function discounthaslocation(): HasMany
    {

        return $this->hasMany(DiscountHasLocations::class, 'location_id');
    }

    /**
     * Get the Locations that owns the City.
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(Cities::class)->withTrashed();
    }

    /**
     * Get the Locations that owns the City.
     */
    public function region(): BelongsTo
    {
        return $this->belongsTo(Regions::class)->withTrashed();
    }

    /**
     * Get the doctors for location.
     */
    public function doctors(): HasMany
    {
        return $this->hasMany(Doctors::class, 'location_id');
    }

    /**
     * Get the doctors for location.
     */
    public function doctorsActive(): HasMany
    {
        return $this->hasMany(Doctors::class, 'location_id')->where(['active' => 1]);
    }

    /**
     * Get the appointments for location.
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointments::class, 'location_id');
    }

    /**
     * Get location.
     */
    public function packageadvances(): HasMany
    {
        return $this->hasMany(PackageAdvances::class, 'location_id');
    }

    public function audit_field_before(): HasMany
    {
        return $this->hasMany(AuditTrailChanges::class, 'field_before');
    }

    public function audit_field_after(): HasMany
    {
        return $this->hasMany(AuditTrailChanges::class, 'field_after');
    }

    /**
     * Get the Location name with City Name.
     */
    protected function fullAddress(): Attribute
    {
        return Attribute::make(
            get: fn (): string => ucfirst($this->city->name ?? '').' - '.ucfirst($this->name ?? ''),
        );
    }

    /**
     * Get the locations.
     */
    public function package(): HasMany
    {

        return $this->hasMany(Packages::class, 'location_id');
    }

    /**
     * Get active and sorted data only.
     */
    public static function getActiveSorted($locationId = false, $name = 'name')
    {
        if ($locationId && ! is_array($locationId)) {
            $locationId = [$locationId];
        }
        $accountId = Auth::user()->account_id;
        $cacheKey = 'locations_active_sorted_'.$accountId.'_'.$name.'_'.($locationId ? md5(serialize($locationId)) : 'all');

        return Cache::remember($cacheKey, 3600, function () use ($locationId, $name, $accountId) {
            $query = self::where([
                ['account_id', '=', $accountId],
                ['active', '=', '1'],
                ['slug', '=', 'custom'],
            ]);
            if ($locationId) {
                $query->whereIn('id', $locationId);
            }

            return $query->pluck($name, 'id');
        });
    }

    public static function getActiveSortedLocations($locationId = false)
    {
        if ($locationId && ! is_array($locationId)) {
            $locationId = [$locationId];
        }
        if ($locationId) {
            return self::whereIn('id', $locationId)->where([
                ['account_id', '=', Auth::user()->account_id],
                ['active', '=', '1'],
                ['slug', '=', 'custom'],
            ])->get();
        } else {
            return self::where([
                ['account_id', '=', Auth::user()->account_id],
                ['active', '=', '1'],
                ['slug', '=', 'custom'],
            ])->get();
        }
    }

    /**
     * Get active and sorted data only for staff wise report.
     */
    public static function getActiveSortedStaffwisereport($locationId = false, $name = 'name')
    {
        if ($locationId && ! is_array($locationId)) {
            $locationId = [$locationId];
        }
        if ($locationId) {
            return self::whereIn('id', $locationId)->where([
                ['account_id', '=', Auth::user()->account_id],
                ['active', '=', '1'],
                ['slug', '=', 'custom'],
            ])->select('id')->get();
        } else {
            return self::where([
                ['account_id', '=', Auth::user()->account_id],
                ['active', '=', '1'],
                ['slug', '=', 'custom'],
            ])->select('id')->get();
        }
    }

    /**
     * Get active and sorted data only.
     */
    public static function getLocationActiveSorted($locationId = false)
    {
        if ($locationId && ! is_array($locationId)) {
            $locationId = [$locationId];
        }
        if ($locationId) {
            return self::whereIn('id', $locationId)->where([
                ['account_id', '=', Auth::user()->account_id],
                ['active', '=', '1'],
                ['slug', '=', 'custom'],
            ])->get();
        } else {
            return self::where([
                ['account_id', '=', Auth::user()->account_id],
                ['active', '=', '1'],
                ['slug', '=', 'custom'],
            ])->get();
        }
    }

    /**
     * Get active and sorted data only.
     */
    public static function getlocation($locationId = false)
    {
        if ($locationId && ! is_array($locationId)) {
            $locationId = [$locationId];
        }
        if ($locationId) {
            return self::whereIn('id', $locationId)->where('account_id', '=', Auth::user()->account_id)->pluck('name', 'id');
        } else {
            return self::where('account_id', '=', Auth::user()->account_id)->pluck('name', 'id');
        }
    }

    /**
     * Get active and sorted data only for general revenue summary report.
     */
    public static function generalrevenuegetActiveSorted($locationId, $region_id)
    {
        if ($locationId && ! is_array($locationId)) {
            $locationId = [$locationId];
        }
        if ($locationId) {
            return self::whereIn('id', $locationId)->where([
                ['account_id', '=', Auth::user()->account_id],
                ['active', '=', '1'],
                ['slug', '=', 'custom'],
                ['region_id', '=', $region_id],
            ])->pluck('name', 'id');
        } else {
            return self::where([
                ['account_id', '=', Auth::user()->account_id],
                ['active', '=', '1'],
                ['slug', '=', 'custom'],
                ['region_id', '=', $region_id],
            ])->pluck('name', 'id');
        }
    }

    /**
     * Get Total Records
     *
     * @param  (int)  $account_id  Current Organization's ID
     * @return (mixed)
     */
    public static function getTotalRecords(Request $request, $account_id = false, $apply_filter = false)
    {
        $where = self::locations_filters($request, $account_id, $apply_filter);
        if (count($where)) {
            if (Gate::allows('view_inactive_centres')) {
                return count(DB::table('locations')
                    ->leftJoin('service_has_locations', 'locations.id', '=', 'service_has_locations.location_id')
                    ->where($where)
                    ->whereIn('id', ACL::getUserCentres())
                    ->select('service_has_locations.location_id')
                    ->groupBy('service_has_locations.location_id')
                    ->get());
            } else {
                return count(DB::table('locations')
                    ->leftJoin('service_has_locations', 'locations.id', '=', 'service_has_locations.location_id')
                    ->where($where)
                    ->where('locations.active', 1)
                    ->whereIn('id', ACL::getUserCentres())
                    ->select('service_has_locations.location_id')
                    ->groupBy('service_has_locations.location_id')
                    ->get());
            }
        }
    }

    /**
     * Get Total Records for target
     *
     * @param  (int)  $account_id  Current Organization's ID
     * @return (mixed)
     */
    public static function getTotalRecords_target(Request $request, $account_id = false, $apply_filter = false)
    {
        $where = self::staff_target_location_filters($request, $account_id, $apply_filter);
        if (count($where)) {
            return count(DB::table('locations')
                ->leftJoin('service_has_locations', 'locations.id', '=', 'service_has_locations.location_id')
                ->where($where)
                ->whereIn('id', ACL::getUserCentres())
                ->select('service_has_locations.location_id')
                ->groupBy('service_has_locations.location_id')
                ->get());
        }
    }

    /**
     * Get Records
     *
     * @param  (int)  $iDisplayStart  Start Index
     * @param  (int)  $iDisplayLength  Total Records Length
     * @param  (int)  $account_id  Current Organization's ID
     * @return (mixed)
     */
    public static function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false, $apply_filter = false)
    {
        $where = self::locations_filters($request, $account_id, $apply_filter);

        if ($request->has('sort')) {
            [$orderBy, $order] = getSortBy($request);
            $orderColumn = $orderBy;

            if ($orderBy == 'created_at') {
                $orderBy = 'locations.created_at';
            }

            Filters::put(Auth::user()->id, 'locations', 'order_by', $orderBy);
            Filters::put(Auth::user()->id, 'locations', 'order', $order);
        } else {
            if (
                Filters::get(Auth::user()->id, 'locations', 'order_by')
                && Filters::get(Auth::user()->id, 'locations', 'order')
            ) {
                $orderBy = Filters::get(Auth::user()->id, 'locations', 'order_by');
                $order = Filters::get(Auth::user()->id, 'locations', 'order');

                if ($orderBy == 'created_at') {
                    $orderBy = 'locations.created_at';
                }
            } else {
                $orderBy = 'created_at';
                $order = 'desc';
                if ($orderBy == 'created_at') {
                    $orderBy = 'locations.created_at';
                }

                Filters::put(Auth::user()->id, 'locations', 'order_by', $orderBy);
                Filters::put(Auth::user()->id, 'locations', 'order', $order);
            }
        }
        if (count($where)) {
            if (Gate::allows('view_inactive_centres')) {
                return DB::table('locations')
                    ->leftJoin('service_has_locations', 'locations.id', '=', 'service_has_locations.location_id')
                    ->where($where)
                    ->whereNull('deleted_at')
                    ->select('locations.*')
                    ->distinct()
                    ->orderby('locations.sort_no', 'asc')
                    ->limit($iDisplayLength)->offset($iDisplayStart)->get();
            } else {
                return DB::table('locations')
                    ->leftJoin('service_has_locations', 'locations.id', '=', 'service_has_locations.location_id')
                    ->where($where)
                    ->where('active', 1)
                    ->whereIn('id', ACL::getUserCentres())
                    ->whereNull('deleted_at')
                    ->select('locations.*')
                    ->distinct()
                    ->orderby('locations.sort_no', 'asc')
                    ->limit($iDisplayLength)->offset($iDisplayStart)->get();
            }
        }
    }

    /**
     * Get Records target
     *
     * @param  (int)  $iDisplayStart  Start Index
     * @param  (int)  $iDisplayLength  Total Records Length
     * @param  (int)  $account_id  Current Organization's ID
     * @return (mixed)
     */
    public static function getRecords_target(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false, $apply_filter = false)
    {
        $where = self::staff_target_location_filters($request, $account_id, $apply_filter);

        if (count($where)) {
            return DB::table('locations')
                ->leftJoin('service_has_locations', 'locations.id', '=', 'service_has_locations.location_id')
                ->where($where)
                ->whereIn('id', ACL::getUserCentres())
                ->whereNull('deleted_at')
                ->select('locations.*')
                ->distinct()
                ->limit($iDisplayLength)->offset($iDisplayStart)->get();
        }
    }

    /**
     * Get filters
     *
     * @param  Request  $request
     * @param  (int)  $account_id  Current Organization's ID
     * @param  (bool)  $apply_filter
     * @return (mixed)
     */
    public static function locations_filters($request, $account_id, $apply_filter)
    {
        $filters = getFilters($request->all());

        [$start_date_time, $end_date_time] = self::parseDateRangeForFilter(
            hasFilter($filters, 'created_at') ? $filters['created_at'] : null
        );

        $where = [];
        if ($account_id) {
            $where[] = [
                'locations.account_id',
                '=',
                $account_id,
            ];
            Filters::put(Auth::user()->id, 'locations', 'account_id', $account_id);
        } else {

            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'locations', 'account_id');
            } else {
                if (Filters::get(Auth::user()->id, 'locations', 'account_id')) {
                    $where[] = [
                        'locations.account_id',
                        '=',
                        Filters::get(Auth::user()->id, 'locations', 'account_id'),
                    ];
                }
            }
        }
        if (hasFilter($filters, 'name')) {
            $where[] = [
                'locations.name',
                'like',
                '%'.$filters['name'].'%',
            ];
            Filters::put(Auth::user()->id, 'locations', 'name', $filters['name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'locations', 'name');
            } else {
                if (Filters::get(Auth::user()->id, 'locations', 'name')) {
                    $where[] = [
                        'locations.name',
                        'like',
                        '%'.Filters::get(Auth::user()->id, 'locations', 'name').'%',
                    ];
                }
            }
        }
        if (hasFilter($filters, 'fdo_name')) {
            $where[] = [
                'fdo_name',
                'like',
                '%'.$filters['fdo_name'].'%',
            ];
            Filters::put(Auth::user()->id, 'locations', 'fdo_name', $filters['fdo_name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'locations', 'fdo_name');
            } else {
                if (Filters::get(Auth::user()->id, 'locations', 'fdo_name')) {
                    $where[] = [
                        'fdo_name',
                        'like',
                        '%'.Filters::get(Auth::user()->id, 'locations', 'fdo_name').'%',
                    ];
                }
            }
        }
        if (hasFilter($filters, 'fdo_phone')) {
            $where[] = [
                'fdo_phone',
                'like',
                '%'.$filters['fdo_phone'].'%',
            ];
            Filters::put(Auth::user()->id, 'locations', 'fdo_phone', $filters['fdo_phone']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'locations', 'fdo_phone');
            } else {
                if (Filters::get(Auth::user()->id, 'locations', 'fdo_phone')) {
                    $where[] = [
                        'fdo_phone',
                        'like',
                        '%'.Filters::get(Auth::user()->id, 'locations', 'fdo_phone').'%',
                    ];
                }
            }
        }
        if (hasFilter($filters, 'address')) {
            $where[] = [
                'locations.address',
                'like',
                '%'.$filters['address'].'%',
            ];
            Filters::put(Auth::user()->id, 'locations', 'address', $filters['address']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'locations', 'address');
            } else {
                if (Filters::get(Auth::user()->id, 'locations', 'address')) {
                    $where[] = [
                        'locations.address',
                        'like',
                        '%'.Filters::get(Auth::user()->id, 'locations', 'address').'%',
                    ];
                }
            }
        }
        if (hasFilter($filters, 'city_id')) {
            $where[] = [
                'locations.city_id',
                '=',
                $filters['city_id'],
            ];
            Filters::put(Auth::user()->id, 'locations', 'city_id', $filters['city_id']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'locations', 'city_id');
            } else {
                if (Filters::get(Auth::user()->id, 'locations', 'city_id')) {
                    $where[] = [
                        'locations.city_id',
                        '=',
                        Filters::get(Auth::user()->id, 'locations', 'city_id'),
                    ];
                }
            }
        }
        if (hasFilter($filters, 'region_id')) {
            $where[] = [
                'locations.region_id',
                '=',
                $filters['region_id'],
            ];
            Filters::put(Auth::user()->id, 'locations', 'region_id', $filters['region_id']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'locations', 'region_id');
            } else {
                if (Filters::get(Auth::user()->id, 'locations', 'region_id')) {
                    $where[] = [
                        'locations.region_id',
                        '=',
                        Filters::get(Auth::user()->id, 'locations', 'region_id'),
                    ];
                }
            }
        }
        if (hasFilter($filters, 'service_id')) {
            $where[] = [
                'service_has_locations.service_id',
                '=',
                $filters['service_id'],
            ];
            Filters::put(Auth::user()->id, 'locations', 'service_id', $filters['service']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'locations', 'service_id');
            } else {
                if (Filters::get(Auth::user()->id, 'locations', 'service_id')) {
                    $where[] = [
                        'service_has_locations.service_id',
                        '=',
                        Filters::get(Auth::user()->id, 'locations', 'service_id'),
                    ];
                }
            }
        }
        if (hasFilter($filters, 'created_at')) {
            $where[] = ['locations.created_at', '>=', $start_date_time];
            $where[] = ['locations.created_at', '<=', $end_date_time];
            Filters::put(Auth::user()->id, 'locations', 'created_at', $filters['created_at']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'locations', 'created_at');
            } else {
                if (Filters::get(Auth::user()->id, 'locations', 'created_at')) {
                    $where[] = ['locations.created_at', '>=', Filters::get(Auth::user()->id, 'locations', 'created_at')];
                }
            }
        }

        if (hasFilter($filters, 'status')) {
            $where[] = [
                'locations.active',
                '=',
                $filters['status'],
            ];
            Filters::put(Auth::user()->id, 'locations', 'status', $filters['status']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'locations', 'status');
            } else {
                if (Filters::get(Auth::user()->id, 'locations', 'status') == 0 || Filters::get(Auth::user()->id, 'locations', 'status') == 1) {
                    if (Filters::get(Auth::user()->id, 'locations', 'status') != null) {
                        $where[] = [
                            'locations.active',
                            '=',
                            Filters::get(Auth::user()->id, 'locations', 'status'),
                        ];
                    }
                }
            }
        }

        $where[] = [
            'slug',
            '=',
            'custom',
        ];

        return $where;
    }

    /**
     * Get filters
     *
     * @param  Request  $request
     * @param  (int)  $account_id  Current Organization's ID
     * @param  (bool)  $apply_filter
     * @return (mixed)
     */
    public static function staff_target_location_filters($request, $account_id, $apply_filter)
    {
        $where = [];
        if ($account_id) {
            $where[] = [
                'locations.account_id',
                '=',
                $account_id,
            ];
            Filters::put(Auth::user()->id, 'staff_target_location', 'account_id', $account_id);
        } else {

            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'staff_target_location', 'account_id');
            } else {
                if (Filters::get(Auth::user()->id, 'staff_target_location', 'account_id')) {
                    $where[] = [
                        'locations.account_id',
                        '=',
                        Filters::get(Auth::user()->id, 'staff_target_location', 'account_id'),
                    ];
                }
            }
        }
        if ($request->get('lead_status_name')) {
            $where[] = [
                'locations.name',
                'like',
                '%'.$request->get('lead_status_name').'%',
            ];
            Filters::put(Auth::user()->id, 'staff_target_location', 'lead_status_name', $request->get('lead_status_name'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'staff_target_location', 'lead_status_name');
            } else {
                if (Filters::get(Auth::user()->id, 'staff_target_location', 'lead_status_name')) {
                    $where[] = [
                        'locations.name',
                        'like',
                        '%'.Filters::get(Auth::user()->id, 'staff_target_location', 'lead_status_name').'%',
                    ];
                }
            }
        }
        if ($request->get('lead_status_city')) {
            $where[] = [
                'locations.city_id',
                '=',
                $request->get('lead_status_city'),
            ];
            Filters::put(Auth::user()->id, 'staff_target_location', 'lead_status_city', $request->get('lead_status_city'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'staff_target_location', 'lead_status_city');
            } else {
                if (Filters::get(Auth::user()->id, 'staff_target_location', 'lead_status_city')) {
                    $where[] = [
                        'locations.city_id',
                        '=',
                        Filters::get(Auth::user()->id, 'staff_target_location', 'lead_status_city'),
                    ];
                }
            }
        }
        if ($request->get('region')) {
            $where[] = [
                'locations.region_id',
                '=',
                $request->get('region'),
            ];
            Filters::put(Auth::user()->id, 'staff_target_location', 'region', $request->get('region'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'staff_target_location', 'region');
            } else {
                if (Filters::get(Auth::user()->id, 'staff_target_location', 'region')) {
                    $where[] = [
                        'locations.region_id',
                        '=',
                        Filters::get(Auth::user()->id, 'staff_target_location', 'region'),
                    ];
                }
            }
        }
        $where[] = [
            'slug',
            '=',
            'custom',
        ];

        return $where;
    }

    /**
     * Get All Records with Dictionary
     *
     * @param  (int)  $account_id  Current Organization's ID
     * @return (mixed)
     */
    public static function getAllRecordsDictionary($account_id, $get_slug = false, $order_by = false, $order = false, $locationids = false)
    {
        if ($locationids && ! is_array($locationids)) {
            $locationids = [$locationids];
        }
        $cacheKey = 'locations_dict_'.$account_id.'_'.($get_slug ?: '0').'_'.($order_by ?: '0').'_'.($order ?: '0').'_'.($locationids ? md5(serialize($locationids)) : 'all');

        return Cache::remember($cacheKey, 3600, function () use ($account_id, $get_slug, $order_by, $order, $locationids) {
            $query = self::where('account_id', '=', $account_id);
            if ($get_slug) {
                $query->where('slug', '=', $get_slug);
            }
            if ($locationids) {
                $query->whereIn('id', $locationids);
            }
            if ($order_by && $order) {
                $query->orderBy($order_by, $order);
            }

            return $query->get()->getDictionary();
        });
    }

    /**
     * Get All Records by City
     *
     * @param  (int)  $cityId  City's ID
     * @param  (int)  $account_id  Current Organization's ID
     * @return (mixed)
     */
    public static function getActiveRecordsByCity($cityId, $locationId, $account_id)
    {
        $where = [];

        $where[] = ['account_id', '=', $account_id];
        $where[] = ['active', '=', '1'];

        if ($cityId) {
            $where[] = ['city_id', '=', $cityId];
        }

        if (is_array($locationId)) {
            $names = ['All Centres', 'All South Region', 'All Central Region'];

            return self::where($where)->whereIn('id', $locationId)->whereNotIn('name', $names)->orderBy('name', 'asc')->get();
        } else {

            if ($locationId) {
                return self::where($where)->whereIn('id', [$locationId])->orderBy('name', 'asc')->get();
            } else {
                return self::where($where)->orderBy('name', 'asc')->get();
            }
        }
    }

    /**
     * Create Record
     *
     * @param  Request  $request
     * @return (mixed)
     */
    public static function createRecord($request, $account_id)
    {
        $data = $request->all();
        // Set Account ID
        $data['account_id'] = $account_id;
        // Set Region ID
        $data['region_id'] = Cities::findOrFail($data['city_id'])->region_id;
        // Set Image — stored on the local `r2` disk under `centre_logo/`;
        // the row stores only the object key (image_src).
        if ($request->file('file')) {
            $file = $request->file('file');
            $ext = strtolower($file->getClientOriginalExtension());
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                // Filename hardening — sanitise the browser-supplied
                // basename before joining it to the R2 object key.
                $fileName = time().'-'.SafeFilename::sanitize(
                    $file->getClientOriginalName(),
                    ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                );
                $file->storeAs('centre_logo', $fileName, 'r2');
                $data['image_src'] = $fileName;
            }
        }

        $record = self::create($data);
        $record->update(['sort_no' => $record->id]);
        // log request for Create for Audit Trail
        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

        return $record;
    }

    /**
     * delete Record
     *
     * @param id
     * @return (mixed)
     */
    public static function deleteRecord($id)
    {
        $location = Locations::getData($id);

        if (! $location) {
            return [
                'status' => false,
                'message' => 'Resource not found.',
            ];
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (Locations::isChildExists($id, Auth::user()->account_id)) {
            return [
                'status' => false,
                'message' => 'Child records exist, unable to delete resource.',
            ];
        }

        $location->delete();

        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        return [
            'status' => true,
            'message' => 'Record has been deleted successfully.',
        ];

    }

    /**
     * Inactive Record
     *
     * @param id
     * @return (mixed)
     */
    public static function inactiveRecord($id)
    {

        $location = Locations::getData($id);

        if (! $location) {
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
     * @return (mixed)
     */
    public static function activeRecord($id, $status)
    {

        $location = Locations::getData($id);

        if (! $location) {
            return false;
        }

        $record = $location->update(['active' => $status]);

        AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

        return $record;

    }

    /**
     * Update Record
     *
     * @param  Request  $request
     * @return (mixed)
     */
    public static function updateRecord($id, $request, $account_id)
    {
        $old_data = (Locations::find($id))->toArray();

        $data = $request->all();

        // Set Account ID
        $data['account_id'] = $account_id;

        // Set Region ID
        $data['region_id'] = Cities::findOrFail($data['city_id'])->region_id;

        if (! isset($data['is_featured'])) {
            $data['is_featured'] = 0;
        } elseif ($data['is_featured'] == '') {
            $data['is_featured'] = 0;
        }
        // Set Image — stored on the local `r2` disk under `centre_logo/`;
        // the previous object (if any) is removed so orphans don't accumulate.
        if ($request->file('file')) {
            $file = $request->file('file');
            $ext = strtolower($file->getClientOriginalExtension());
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                // Filename hardening — sanitise the browser-supplied
                // basename before joining it to the R2 object key.
                $fileName = time().'-'.SafeFilename::sanitize(
                    $file->getClientOriginalName(),
                    ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                );
                $file->storeAs('centre_logo', $fileName, 'r2');
                $data['image_src'] = $fileName;

                if (! empty($old_data['image_src']) && $old_data['image_src'] !== $fileName) {
                    \Illuminate\Support\Facades\Storage::disk('r2')
                        ->delete('centre_logo/'.$old_data['image_src']);
                }
            }
        }
        $record = self::where([
            'id' => $id,
            'account_id' => $account_id,
        ])->first();

        if (! $record) {
            return null;
        }

        $record->update($data);

        AuditTrails::editEventLogger(self::$_table, 'Edit', $data, self::$_fillable, $old_data, $id);

        return $record;
    }

    /**
     * Check if child records exist
     *
     * @param  (int)  $id
     * @return (bool)
     */
    public static function isChildExists($id, $account_id)
    {
        return false;
    }

    public function service_has_locations(): HasMany
    {
        return $this->hasMany(ServiceHasLocations::class, 'location_id')->withoutGlobalScope(SoftDeletingScope::class);
    }

    /*
     * Function for target location data
     *
     */
    public static function LoadtargetLocationdata($request)
    {

        $center_target_status = 0;
        $center_target_working_days = 0;

        $lcoations = Locations::where([
            ['active', '=', '1'],
            ['slug', '=', 'custom'],
        ])->get();

        $targetlocationdata_existing = CentertargetMeta::where([
            ['year', '=', $request->get('year')],
            ['month', '=', $request->get('month')],
        ])->get();

        $CenterTargetArray = [];

        foreach ($lcoations as $location) {
            $CenterTargetArray[$location->id] = [
                'location_id' => $location->id,
                'location_name' => $location->city->name.'  '.$location->name,
                'target_amount' => 0,
            ];
        }

        if ($targetlocationdata_existing->count()) {

            $center_target_status = 1;

            $center_target = Centertarget::where([
                ['year', '=', $request->get('year')],
                ['month', '=', $request->get('month')],
            ])->first();

            $center_target_working_days = $center_target->working_days;

            foreach ($targetlocationdata_existing as $locationdata) {
                $location_info = Locations::find($locationdata->location_id);
                $CenterTargetArray[$locationdata->location_id] = [
                    'location_id' => $locationdata->location_id,
                    'location_name' => $location_info->city->name.'  '.$location_info->name,
                    'target_amount' => $locationdata->target_amount,
                ];
            }
        }

        return ['CenterTargetArray' => $CenterTargetArray, 'center_target_status' => $center_target_status, 'center_target_working_days' => $center_target_working_days];

    }
}
