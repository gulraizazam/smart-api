<?php

namespace App\Models;

use App\Helpers\Filters;
use Auth;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;

class LeadSources extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['name', 'account_id', 'sort_no', 'active', 'created_at', 'updated_at'];

    protected static $_fillable = ['name', 'active'];

    protected $table = 'lead_sources';

    protected static $_table = 'lead_sources';

    /**
     * Get the Leads for Lead Source.
     */
    public function leads()
    {
        return $this->hasMany('App\Models\Leads', 'lead_source_id');
    }

    /**
     * Get active and sorted data only.
     */
    public static function getActiveSorted()
    {
        return self::where([
            ['account_id', '=', Auth::User()->account_id],
            ['active', '=', '1'],
        ])->OrderBy('sort_no', 'asc')->get()->pluck('name', 'id');
    }

    /**
     * Get active and sorted data only.
     */
    public static function getActiveOnly()
    {
        return self::where(['active' => 1])->OrderBy('sort_no', 'asc')->get();
    }

    /**
     * Get Total Records
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getTotalRecords(Request $request, $account_id = false, $apply_filter = false)
    {
        $where = self::lead_sources_filters($request, $account_id, $apply_filter);

        if (count($where)) {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_leadsources')) {
                return self::where($where)->count();
            } else {
                return self::where($where)->where('active', 1)->count();
            }
        } else {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_leadsources')) {
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
        $where = self::lead_sources_filters($request, $account_id, $apply_filter);
        if (count($where)) {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_leadsources')) {
                return self::where($where)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('sort_no')->get();
            } else {
                return self::where($where)->where('active', 1)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('sort_no')->get();
            }
        } else {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_leadsources')) {
                return self::limit($iDisplayLength)->offset($iDisplayStart)->orderBy('sort_no')->get();
            } else {
                return self::where('active', 1)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('sort_no')->get();
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
    public static function lead_sources_filters($request, $account_id, $apply_filter)
    {
        $where = [];
        $filters = getFilters($request->all());
        if ($account_id) {
            $where[] = [
                'account_id',
                '=',
                $account_id,
            ];
            Filters::put(Auth::User()->id, 'lead_sources', 'account_id', $account_id);
        } else {

            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'lead_sources', 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, 'lead_sources', 'account_id')) {
                    $where[] = [
                        'account_id',
                        '=',
                        Filters::get(Auth::User()->id, 'lead_sources', 'account_id'),
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
            Filters::put(Auth::User()->id, 'lead_sources', 'lead_status_name', $filters['name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'lead_sources', 'lead_status_name');
            } else {
                if (Filters::get(Auth::User()->id, 'lead_sources', 'lead_status_name')) {
                    $where[] = [
                        'name',
                        'like',
                        '%'.Filters::get(Auth::User()->id, 'lead_sources', 'lead_status_name').'%',
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
            Filters::put(Auth::user()->id, 'lead_sources', 'status', $filters['status']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'lead_sources', 'status');
            } else {
                if (Filters::get(Auth::user()->id, 'lead_sources', 'status') == 0 || Filters::get(Auth::user()->id, 'lead_sources', 'status') == 1) {
                    if (Filters::get(Auth::user()->id, 'lead_sources', 'status') != null) {
                        $where[] = [
                            'active',
                            '=',
                            Filters::get(Auth::user()->id, 'lead_sources', 'status'),
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
        $lead_source = LeadSources::getData($id);
        if (! $lead_source) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }
        // Check if child records exists or not, If exist then disallow to delete it.
        if (LeadSources::isChildExists($id, Auth::User()->account_id)) {
            return collect(['status' => false, 'message' => 'Child records exist, unable to delete resource']);
        }
        $record = $lead_source->delete();
        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        return collect(['status' => true, 'message' => 'Record has been deleted successfully.']);
    }

    /**
     * inactive Record
     *
     * @param id
     * @return (mixed)
     */
    public static function InactiveRecord($id)
    {
        $lead_source = LeadSources::getData($id);
        if (! $lead_source) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }
        $record = $lead_source->update(['active' => 0]);
        AuditTrails::inactiveEventLogger(self::$_table, 'inactive', self::$_fillable, $id);

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
        $lead_source = LeadSources::getData($id);
        if (! $lead_source) {
            return collect(['status' => true, 'message' => 'Resource not found.']);
        }
        $record = $lead_source->update(['active' => 1]);
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
        $old_data = (LeadSources::find($id))->toArray();

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

        AuditTrails::editEventLogger(self::$_table, 'Edit', $data, self::$_fillable, $old_data, $id);

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
        return false;
    }
}
