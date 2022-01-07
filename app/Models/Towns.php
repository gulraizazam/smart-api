<?php

namespace App\Models;

use App\Helpers\Filters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Auth;

class Towns extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['name', 'city_id', 'active','account_id', 'created_at', 'updated_at', 'deleted_at'];

    protected static $_fillable = ['name', 'slug', 'active','account_id'];

    protected $table = 'towns';

    protected static $_table = 'towns';

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
        $where = Self::towns_filters($request, $account_id, $apply_filter);

        if (count($where)) {
            return self::where($where)->count();
        } else {
            return self::count();
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
        $where = Self::towns_filters($request, $account_id, $apply_filter);

        if (count($where)) {
            return self::where($where)->limit($iDisplayLength)->offset($iDisplayStart)->get();
        } else {
            return self::limit($iDisplayLength)->offset($iDisplayStart)->get();
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
    static public function towns_filters($request, $account_id, $apply_filter)
    {

        $where = array();

        $filters = getFilters($request->all());

        $apply_filter = checkFilters($filters, 'towns');

        if ($account_id) {
            $where[] = array(
                'account_id',
                '=',
                $account_id
            );
            Filters::put(Auth::User()->id, 'towns', 'account_id', $account_id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'towns', 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, 'towns', 'account_id')) {
                    $where[] = array(
                        'account_id',
                        '=',
                        Filters::get(Auth::User()->id, 'towns', 'account_id')
                    );
                }
            }
        }
        if(count($filters) > 0 && hasFilter($filters, 'name')) {
            $where[] = array(
                'name',
                'like',
                '%' . $filters['name'] . '%'
            );
            Filters::put(Auth::User()->id, 'towns', 'name', $filters['name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'towns', 'name');
            } else {
                if (Filters::get(Auth::User()->id, 'towns', 'name')) {
                    $where[] = array(
                        'name',
                        'like',
                        '%' . Filters::get(Auth::User()->id, 'towns', 'name') . '%'
                    );
                }
            }
        }
        if(count($filters) > 0 && hasFilter($filters, 'city_id')) {
            $where[] = array(
                'city_id',
                '=',
                $filters['city_id']
            );
            Filters::put(Auth::User()->id, 'towns', 'city_id', $filters['city_id']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'towns', 'city_id');
            } else {
                if (Filters::get(Auth::User()->id, 'towns', 'city_id')) {
                    $where[] = array(
                        'city_id',
                        '=',
                        Filters::get(Auth::User()->id, 'towns', 'city_id')
                    );
                }
            }
        }
        if (count($filters) > 0 && hasFilter($filters, 'status') || hasFilter($filters, 'status') && $filters['status'] == 0 && $filters['status'] != null) {
            $where[] = array(
                'active',
                '=',
                $filters['status']
            );
            Filters::put(Auth::user()->id, 'towns', 'status', $filters['status']);
        } else {
            if ( $apply_filter ){
                Filters::forget( Auth::user()->id, 'towns', 'status');
            } else {
                if ( Filters::get(Auth::user()->id, 'towns', 'status' ) == 0 || Filters::get(Auth::user()->id, 'towns', 'status' ) == 1 ){
                    if ( Filters::get(Auth::user()->id, 'towns', 'status' ) != null ){
                        $where[] = array(
                            'active',
                            '=',
                            Filters::get( Auth::user()->id, 'towns', 'status')
                        );
                    }
                }
            }
        }
        return $where;
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
            Leads::where(['town_id' => $id, 'account_id' => $account_id])->count()
        ) {
            return true;
        }
        return false;
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

        $data['account_id'] = $account_id;

        $record = self::create($data);

        $record->update(['sort_no' => $record->id]);

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

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
        $old_data = (Towns::find($id))->toArray();

        $data = $request->all();
        // Set Account ID
        $data['account_id'] = $account_id;

        $record = self::where([
            'id' => $id,
            'account_id' => $account_id
        ])->first();

        if (!$record) {
            return null;
        }

        $record->update($data);

        AuditTrails::EditEventLogger(self::$_table, 'edit', $data, self::$_fillable, $old_data, $id);

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
        $town = Towns::getData($id);

        if (!$town) {

            flash('Resource not found.')->error()->important();
            return redirect()->route('admin.towns.index');
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (Towns::isChildExists($id, Auth::User()->account_id)) {

            flash('Child records exist, unable to delete resource')->error()->important();
            return redirect()->route('admin.towns.index');
        }

        $record = $town->delete();

        //log request for delete for audit trail

        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        flash('Record has been deleted successfully.')->success()->important();

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
        $town = Towns::getData($id);

        if (!$town) {
            flash('Resource not found.')->error()->important();
            return redirect()->route('admin.towns.index');
        }

        $record = $town->update(['active' => 0]);

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
    static function activeRecord($id, $status = 1)
    {
        $town = Towns::getData($id);

        if (!$town) {

            session()->flash('error', 'Resource not found.');
           return false;
        }
       
        $record = $town->update(['active' => $status]);

        session()->flash('success', 'Status has been changed successfully.');

        AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

        return $record;
    }

    /**
     * Get the active towns.
     */
    static public function getActiveTowns()
    {
        $query = self::where(['active' => 1, 'account_id' => 1])->get()->pluck('name', 'id');
        return $query;
    }

    /**
     * Get the comments for the blog post.
     */
    public function leads()
    {
        return $this->hasMany('App\Models\Leads', 'town_id', 'id');
    }

    /**
     * Get the city of town.
     */
    public function city()
    {
        return $this->belongsTo('App\Models\Cities', 'city_id');
    }

    /**
     * Get the Get the Town Name with City.
     */
    public function getFullNameAttribute($value)
    {
        return ucfirst($this->city->name) . ' - ' . ucfirst($this->name);
    }
}
