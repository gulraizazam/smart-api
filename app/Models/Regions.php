<?php

namespace App\Models;

use App\Helpers\Filters;
use App\Helpers\Widgets\RegionsWidget;
use Auth;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;

class Regions extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['account_id', 'name', 'slug', 'active', 'created_at', 'updated_at', 'sort_number'];

    protected static $_fillable = ['name', 'slug', 'active'];

    protected $table = 'regions';

    protected static $_table = 'regions';

    /**
     * sent the region data to resource has rota.
     */
    public function resourcehasrota()
    {
        return $this->hasMany('App\Models\ResourceHasRota', 'region_id');
    }

    /**
     * Get the Locations for Region.
     */
    public function locations()
    {
        return $this->hasMany('App\Models\Locations', 'region_id');
    }

    /**
     * Get the Active Locations for Region.
     */
    public function locationsActive()
    {
        return $this->hasMany('App\Models\Locations', 'region_id')->where(['active' => 1]);
    }

    /**
     * Get the doctors for Region.
     */
    public function doctors()
    {
        return $this->hasMany('App\Models\Doctors', 'region_id');
    }

    /**
     * Get the appointments for Region.
     */
    public function appointments()
    {
        return $this->hasMany('App\Models\Appointments', 'region_id');
    }

    /**
     * Get the Regions.
     */
    public static function getAll($account_id, $get_slug = false, $order_by = false, $order = false, $regionids = false)
    {
        if ($regionids && ! is_array($regionids)) {
            $regionids = [$regionids];
        }

        if ($regionids) {
            if ($get_slug) {
                if ($order_by && $order) {
                    return self::where('account_id', '=', $account_id)->where('slug', '=', $get_slug)->whereIn('id', $regionids)->orderBy($order_by, $order)->get();
                }

                return self::where('account_id', '=', $account_id)->where('slug', '=', $get_slug)->whereIn('id', $regionids)->get();
            } else {
                if ($order_by && $order) {
                    return self::where('account_id', '=', $account_id)->orderBy($order_by, $order)->whereIn('id', $regionids)->get();
                }

                return self::where('account_id', '=', $account_id)->whereIn('id', $regionids)->get();
            }
        } else {
            if ($get_slug) {
                if ($order_by && $order) {
                    return self::where('account_id', '=', $account_id)->where('slug', '=', $get_slug)->orderBy($order_by, $order)->get();
                }

                return self::where('account_id', '=', $account_id)->where('slug', '=', $get_slug)->get();
            } else {
                if ($order_by && $order) {
                    return self::where('account_id', '=', $account_id)->orderBy($order_by, $order)->get();
                }

                return self::where('account_id', '=', $account_id)->get();
            }
        }
    }

    /**
     * Get active and sorted data only.
     */
    public static function getActiveSorted($regionId = false, $get_all = false)
    {
        if ($regionId && ! is_array($regionId)) {
            $regionId = [$regionId];
        }
        if ($regionId) {
            return self::where(['active' => 1, 'slug' => 'custom'])->whereIn('id', $regionId)->where('account_id', '=', Auth::User()->account_id)->get()->pluck('name', 'id');
        } else {
            return self::where(['active' => 1, 'slug' => 'custom'])->where('account_id', '=', Auth::User()->account_id)->pluck('name', 'id');
        }
    }

    /**
     * Get active and sorted data only.
     */
    public static function getActiveOnly($regionId = false, $account_id = false, $get_custom_only = false)
    {
        if ($regionId && ! is_array($regionId)) {
            $regionId = [$regionId];
        }
        $query = self::where(['active' => 1, 'slug' => 'custom']);
        if ($regionId) {
            $query->where('slug', '=', 'custom')->whereIn('id', $regionId);
        }
        if ($account_id) {
            $query->where(['account_id' => $account_id]);
        }
        if ($get_custom_only) {
            $query->where(['slug' => 'custom']);
        }

        return $query->OrderBy('sort_number', 'asc')->get();
    }

    /**
     * Get Total Records
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getTotalRecords(Request $request, $account_id = false, $apply_filter = false)
    {
        $where = self::regions_filters($request, $account_id, $apply_filter);
        if (count($where)) {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_regions')) {
                return self::where([
                    [$where],
                    ['slug', '=', 'custom'],
                ])->count();
            } else {
                return self::where([
                    [$where],
                    ['slug', '=', 'custom'],
                ])->where('active', 1)->count();
            }
        } else {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_regions')) {
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
        $where = self::regions_filters($request, $account_id, $apply_filter);
        if (count($where)) {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_regions')) {
                return self::where([
                    [$where],
                    ['slug', '=', 'custom'],
                ])->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('sort_number')->get();
            } else {
                return self::where([
                    [$where],
                    ['slug', '=', 'custom'],
                ])->where('active', 1)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('sort_number')->get();
            }
        } else {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_regions')) {
                return self::where('slug', '=', 'custom')->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('sort_number')->get();
            } else {
                return self::where('active', 1)->where('slug', '=', 'custom')->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('sort_number')->get();
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
    public static function regions_filters($request, $account_id, $apply_filter)
    {
        $filters = getFilters($request->all());
        $where = [];
        if ($account_id) {
            $where[] = [
                'account_id',
                '=',
                $account_id,
            ];
            Filters::put(Auth::User()->id, 'regions', 'account_id', $account_id);
        } else {

            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'regions', 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, 'regions', 'account_id')) {
                    $where[] = [
                        'account_id',
                        '=',
                        Filters::get(Auth::User()->id, 'regions', 'account_id'),
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
            Filters::put(Auth::User()->id, 'regions', 'name', $filters['name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'regions', 'name');
            } else {
                if (Filters::get(Auth::User()->id, 'regions', 'name')) {
                    $where[] = [
                        'name',
                        'like',
                        '%'.Filters::get(Auth::User()->id, 'regions', 'name').'%',
                    ];
                }
            }
        }

        if (hasFilter($filters, 'status')) {
            $where[] = [
                'active',
                '=',
                $filters['status'],
            ];
            Filters::put(Auth::user()->id, 'regions', 'status', $filters['status']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'regions', 'status');
            } else {
                if (Filters::get(Auth::user()->id, 'regions', 'status') == 0 || Filters::get(Auth::user()->id, 'regions', 'status') == 1) {
                    if (Filters::get(Auth::user()->id, 'regions', 'status') != null) {
                        $where[] = [
                            'active',
                            '=',
                            Filters::get(Auth::user()->id, 'regions', 'status'),
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
    public static function getAllRecordsDictionary($account_id)
    {
        return self::where(['account_id' => $account_id])->get()->getDictionary();
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
        $data['slug'] = 'custom';

        $record = self::create($data);

        $record->update(['sort_no' => $record->id]);

        // Call Create Regions Widget
        RegionsWidget::advancedCreateRegion($record, $account_id);

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
        $region = Regions::getData($id);

        if (! $region) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }

        if ($region->slug == 'all') {
            return collect(['status' => false, 'message' => 'Root region can not be deleted.']);
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (Regions::isChildExists($id, Auth::User()->account_id)) {
            return collect(['status' => false, 'message' => 'Child records exist, unable to delete resource']);
        }

        /*
         * Delete Sub locations
         */
        Locations::where(['region_id' => $region->id])->delete();

        /*
         * Delete Sub Cities
         */
        Cities::where(['region_id' => $region->id])->delete();

        $record = $region->delete();

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
        $region = Regions::getData($id);
        if (! $region) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }
        $record = $region->update(['active' => 0]);

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
        $region = Regions::getData($id);
        if (! $region) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }
        $region->update(['active' => 1]);
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
        $old_data = (Regions::find($id))->toArray();

        $data = $request->all();
        // Set Account ID
        $data['account_id'] = $account_id;

        $record = self::where([
            'id' => $id,
            'account_id' => $account_id,
        ])->first();

        if (! $record) {
            return null;
        }

        $record->update($data);

        // Call Update Regions Widget
        RegionsWidget::advancedUpdateRegion($record, $account_id);

        AuditTrails::EditEventLogger(self::$_table, 'edit', $data, self::$_fillable, $old_data, $id);

        return $record;
    }

    /**
     * Check if child records exist
     *
     * @param  (int)  $id
     * @return (boolean)
     */
    protected static function isChildExists($id, $account_id)
    {
        if (
            //            Cities::where(['region_id' => $id, 'account_id' => $account_id])->count() ||
            //            Locations::where(['region_id' => $id, 'account_id' => $account_id])->count() ||
            Leads::where(['region_id' => $id, 'account_id' => $account_id])->count() ||
            Appointments::where(['region_id' => $id, 'account_id' => $account_id])->count()
        ) {
            return true;
        }

        return false;
    }

    public static function getRegions()
    {

        return self::where([
            ['account_id', '=', Auth::User()->account_id],
            ['active', '=', '1'],
        ])->OrderBy('sort_number', 'asc')->get()->pluck('name', 'id');

    }
}
