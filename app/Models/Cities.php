<?php

namespace App\Models;

use App\Helpers\Filters;
use Auth;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;

class Cities extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['account_id', 'region_id', 'name', 'slug', 'active', 'is_featured', 'created_at', 'updated_at', 'sort_number'];

    protected static $_fillable = ['name', 'slug', 'active', 'region_id', 'is_featured'];

    protected $table = 'cities';

    protected static $_table = 'cities';

    /**
     * sent the city data to resource has rota.
     */
    public function resourcehasrota()
    {
        return $this->hasMany('App\Models\ResourceHasRota', 'city_id');
    }

    /**
     * Get the Locations for City.
     */
    public function locations()
    {
        return $this->hasMany('App\Models\Locations', 'city_id');
    }

    /**
     * Get the Region for City.
     */
    public function region()
    {
        return $this->belongsTo('App\Models\Regions', 'region_id');
    }

    /**
     * Get the town of city.
     */
    public function town()
    {
        return $this->hasMany('App\Models\Towns', 'city_id', 'id');
    }

    /**
     * Get the Active Locations for City.
     */
    public function locationsActive()
    {
        return $this->hasMany('App\Models\Locations', 'city_id')->where(['active' => 1]);
    }

    /**
     * Get the doctors for City.
     */
    public function doctors()
    {
        return $this->hasMany('App\Models\Doctors', 'city_id');
    }

    /**
     * Get the appointments for City.
     */
    public function appointments()
    {
        return $this->hasMany('App\Models\Appointments', 'city_id');
    }

    /**
     * Get active and sorted data only.
     */
    public static function getActiveSorted($cityId = false)
    {
        if ($cityId && ! is_array($cityId)) {
            $cityId = [$cityId];
        }
        if ($cityId) {
            return self::whereIn('id', $cityId)->where([
                ['account_id', '=', Auth::User()->account_id],
                ['slug', '=', 'custom'],
            ])->get()->pluck('name', 'id');
        } else {
            return self::where([
                ['account_id', '=', Auth::User()->account_id],
                ['slug', '=', 'custom'],
            ])->pluck('name', 'id');
        }
    }

    /**
     * Get active and sorted data only.
     */
    public static function getActiveSortedFeatured($cityId = false, $name = 'name')
    {
        if ($cityId && ! is_array($cityId)) {
            $cityId = [$cityId];
        }

        $query = self::where(['active' => 1, 'is_featured' => 1]);
        if ($cityId) {
            $query->whereIn('id', $cityId);
        }

        return $query->get()->pluck($name, 'id');
    }

    /**
     * Get active and sorted data only.
     */
    public static function getActiveOnly($cityId = false, $account_id = false)
    {
        if ($cityId && ! is_array($cityId)) {
            $cityId = [$cityId];
        }
        $query = self::where(['active' => 1]);
        if ($cityId) {
            $query->whereIn('id', $cityId);
        }
        if ($account_id) {
            $query->where([
                ['account_id', '=', $account_id],
                ['slug', '=', 'custom'],
            ]);
        }

        return $query->OrderBy('sort_number', 'asc')->get();
    }

    /**
     * Get the Location name with City Name.
     */
    public function getFullNameAttribute($value)
    {
        return ucfirst($this->region->name).' - '.ucfirst($this->name);
    }

    /**
     * Get active and sorted data only.
     */
    public static function getActiveFeaturedOnly($cityId, $account_id)
    {
        if ($cityId && ! is_array($cityId)) {
            $cityId = [$cityId];
        }

        $query = self::where(['active' => 1, 'is_featured' => 1, 'account_id' => $account_id]);
        if ($cityId) {
            $query->whereIn('id', $cityId);
        }

        return $query->OrderBy('sort_number', 'asc');
    }

    /**
     * Get Total Records
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getTotalRecords(Request $request, $account_id = false, $apply_filter = false)
    {
        $where = self::cities_filters($request, $account_id, $apply_filter);

        if (count($where)) {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_cities')) {
                return self::where($where)->count();
            } else {
                return self::where($where)->where('active', 1)->count();
            }
        } else {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_cities')) {
                return self::count();
            } else {
                return self::where('active', 1)->count();
            }
        }
    }

    /**
     * Get Records
     *
     * @param  (int)  $iDisplayStart Start Index
     * @param  (int)  $iDisplayLength Total Records Length
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false, $apply_filter = false)
    {
        $where = self::cities_filters($request, $account_id, $apply_filter);

        if (count($where)) {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_cities')) {
                return self::where($where)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('sort_number')->get();
            } else {
                return self::where($where)->where('active', 1)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('sort_number')->get();
            }
        } else {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_cities')) {
                return self::limit($iDisplayLength)->offset($iDisplayStart)->orderBy('sort_number')->get();
            } else {
                return self::where('active', 1)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('sort_number')->get();
            }
        }
    }

    /**
     * Get filters
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  (int)  $account_id Current Organization's ID
     * @param  (boolean)  $apply_filter
     * @return (mixed)
     */
    public static function cities_filters($request, $account_id, $apply_filter)
    {

        $where = [];
        $filters = getFilters($request->all());
        if ($account_id) {
            $where[] = [
                'account_id',
                '=',
                $account_id,
            ];
            Filters::put(Auth::User()->id, 'cities', 'account_id', $account_id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'cities', 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, 'cities', 'account_id')) {
                    $where[] = [
                        'account_id',
                        '=',
                        Filters::get(Auth::User()->id, 'cities', 'account_id'),
                    ];
                }
            }
        }
        if (hasFilter($filters, 'name')) {
            $where[] = [
                'name',
                'like',
                '%'.$filters['name'].'%',
            ];
            Filters::put(Auth::User()->id, 'cities', 'name', $filters['name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'cities', 'name');
            } else {
                if (Filters::get(Auth::User()->id, 'cities', 'name')) {
                    $where[] = [
                        'name',
                        'like',
                        '%'.Filters::get(Auth::User()->id, 'cities', 'name').'%',
                    ];
                }
            }
        }
        if (hasFilter($filters, 'is_featured')) {
            $where[] = [
                'is_featured',
                '=',
                $filters['is_featured'],
            ];
            Filters::put(Auth::User()->id, 'cities', 'is_featured', $filters['is_featured']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'cities', 'is_featured');
            } else {
                if (Filters::get(Auth::User()->id, 'cities', 'is_featured')) {
                    $where[] = [
                        'is_featured',
                        '=',
                        Filters::get(Auth::User()->id, 'cities', 'is_featured'),
                    ];
                }
            }
        }
        if (hasFilter($filters, 'region_id')) {
            $where[] = [
                'region_id',
                '=',
                $filters['region_id'],
            ];
            Filters::put(Auth::User()->id, 'cities', 'region_id', $filters['region_id']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'cities', 'region_id');
            } else {
                if (Filters::get(Auth::User()->id, 'cities', 'region_id')) {
                    $where[] = [
                        'region_id',
                        '=',
                        Filters::get(Auth::User()->id, 'cities', 'region_id'),
                    ];
                }
            }
        }
        $where[] = [
            'slug',
            '=',
            'custom',
        ];

        if (hasFilter($filters, 'status')) {
            $where[] = [
                'active',
                '=',
                $filters['status'],
            ];
            Filters::put(Auth::user()->id, 'cities', 'status', $filters['status']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'cities', 'status');
            } else {
                if (Filters::get(Auth::user()->id, 'cities', 'status') == 0 || Filters::get(Auth::user()->id, 'cities', 'status') == 1) {
                    if (Filters::get(Auth::user()->id, 'cities', 'status') != null) {
                        $where[] = [
                            'active',
                            '=',
                            Filters::get(Auth::user()->id, 'cities', 'status'),
                        ];
                    }
                }
            }
        }

        return $where;
    }

    /**
     * Get All Records
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getAllRecordsDictionary($account_id, $citiesids = false)
    {
        if ($citiesids && ! is_array($citiesids)) {
            $citiesids = [$citiesids];
        }
        if ($citiesids) {
            return self::where(['account_id' => $account_id,'active'=>1,'is_featured'=>1])->whereIn('id', $citiesids)->get()->getDictionary();
        } else {
            return self::where(['account_id' => $account_id,'active'=>1,'is_featured'=>1])->get()->getDictionary();
        }
    }

    /**
     * Create Record
     *
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function createRecord($request, $account_id)
    {

        $data = $request->all();

        // Set Account ID
        $data['account_id'] = $account_id;

        if (! isset($data['is_featured'])) {
            $data['is_featured'] = 0;
        } elseif ($data['is_featured'] == '') {
            $data['is_featured'] = 0;
        }

        $record = self::create($data);

        $record->update(['sort_no' => $record->id]);

        //log request for Create for Audit Trail

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

        return $record;
    }

    /**
     * Delete Record
     *
     * @param id
     * @return (mixed)
     */
    public static function DeleteRecord($id)
    {
        $citie = Cities::getData($id);
        if (! $citie) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (Cities::isChildExists($id, Auth::User()->account_id)) {
            return collect(['status' => false, 'message' => 'Child records exist, unable to delete resource']);
        }

        $record = $citie->delete();

        //log request for delete for audit trail

        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        return collect(['status' => true, 'message' => 'Record has been deleted successfully.']);
    }

    /**
     * inactive Record
     *
     * @param id
     * @return (mixed)
     */
    public static function inactiveRecord($id)
    {
        $citie = Cities::getData($id);
        if (! $citie) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }
        $record = $citie->update(['active' => 0]);
        AuditTrails::InactiveEventLogger(self::$_table, 'inactive', self::$_fillable, $id);

        return collect(['status' => true, 'message' => 'Record has been inactivated successfully.']);
    }

    /**
     * active Record
     *
     * @param id
     * @return (mixed)
     */
    public static function activeRecord($id)
    {
        $citie = Cities::getData($id);
        if (! $citie) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }
        $record = $citie->update(['active' => 1]);
        AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

        return collect(['status' => true, 'message' => 'Record has been activated successfully.']);
    }

    /**
     * Update Record
     *
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function updateRecord($id, $request, $account_id)
    {
        $old_data = (Cities::find($id))->toArray();

        $data = $request->all();
        // Set Account ID
        $data['account_id'] = $account_id;

        if (! isset($data['is_featured'])) {
            $data['is_featured'] = 0;
        } elseif ($data['is_featured'] == '') {
            $data['is_featured'] = 0;
        }

        $record = self::where([
            'id' => $id,
            'account_id' => $account_id,
        ])->first();

        if (! $record) {
            return null;
        }

        $record->update($data);

        AuditTrails::EditEventLogger(self::$_table, 'edit', $data, self::$_fillable, $old_data, $id);

        return $record;
    }

    /**
     * Check if child records exist
     *
     * @param  (int)  $id
     * @return (boolean)
     */
    public static function isChildExists($id, $account_id)
    {
        if (
            Locations::where(['city_id' => $id, 'account_id' => $account_id])->count() ||
            Leads::where(['city_id' => $id, 'account_id' => $account_id])->count() ||
            Appointments::where(['city_id' => $id, 'account_id' => $account_id])->count()
        ) {
            return true;
        }

        return false;
    }

    public static function getCities()
    {

        return self::where([
            ['account_id', '=', Auth::User()->account_id],
            ['active', '=', '1'],
        ])->OrderBy('sort_number', 'asc')->get()->pluck('name', 'id');

    }
}
