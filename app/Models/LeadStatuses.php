<?php

namespace App\Models;

use App\Helpers\Filters;
use Auth;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;

class LeadStatuses extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['name', 'parent_id', 'account_id', 'is_comment', 'is_default', 'is_booked', 'is_arrived', 'is_converted', 'sort_no', 'active', 'created_at', 'updated_at', 'parent_id', 'is_comment', 'is_junk'];

    protected static $_fillable = ['name', 'active', 'parent_id', 'is_comment', 'is_default', 'is_booked', 'is_arrived', 'is_converted', 'is_junk'];

    protected $table = 'lead_statuses';

    protected static $_table = 'lead_statuses';

    /**
     * Get the Leads for Lead Status.
     */
    public function leads()
    {
        return $this->hasMany('App\Models\Leads', 'lead_status_id');
    }

    /**
     * Get active and sorted data only.
     */
    public static function getActiveSorted($skip_ids = false, $include_ids = false, $account_id = false)
    {
        if ($skip_ids && ! is_array($skip_ids)) {
            $skip_ids = [$skip_ids];
        }
        if ($include_ids && ! is_array($include_ids)) {
            $include_ids = [$include_ids];
        }

        if ($skip_ids && $include_ids) {
            return self::where(['active' => 1])->whereIn('id', $include_ids)->whereNotIn('id', $skip_ids)->OrderBy('sort_no', 'asc')->get()->pluck('name', 'id');
        } elseif ($skip_ids) {
            return self::where(['active' => 1])->whereNotIn('id', $skip_ids)->OrderBy('sort_no', 'asc')->get()->pluck('name', 'id');
        } elseif ($include_ids) {
            return self::where(['active' => 1])->whereIn('id', $include_ids)->OrderBy('sort_no', 'asc')->get()->pluck('name', 'id');
        } else {
            return self::where(['active' => 1])->OrderBy('sort_no', 'asc')->get()->pluck('name', 'id');
        }
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
        $where = self::lead_statuses_filters($request, $account_id, $apply_filter);
        if (count($where)) {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_leadstatuses')) {
                return self::where($where)->count();
            } else {
                return self::where($where)->where('active', 1)->count();
            }
        } else {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_leadstatuses')) {
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
        $where = self::lead_statuses_filters($request, $account_id, $apply_filter);
        if (count($where)) {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_leadstatuses ')) {
                return self::where($where)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('sort_no')->get();
            } else {
                return self::where($where)->where('active', 1)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('sort_no')->get();
            }
        } else {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_leadstatuses')) {
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
    public static function lead_statuses_filters($request, $account_id, $apply_filter)
    {
        $where = [];
        $filters = getFilters($request->all());
        if ($account_id) {
            $where[] = [
                'account_id',
                '=',
                $account_id,
            ];
            Filters::put(Auth::User()->id, 'lead_statuses', 'account_id', $account_id);
        } else {

            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'lead_statuses', 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, 'lead_statuses', 'account_id')) {
                    $where[] = [
                        'account_id',
                        '=',
                        Filters::get(Auth::User()->id, 'lead_statuses', 'account_id'),
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
            Filters::put(Auth::User()->id, 'lead_statuses', 'lead_status_name', $filters['name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'lead_statuses', 'lead_status_name');
            } else {
                if (Filters::get(Auth::User()->id, 'lead_statuses', 'lead_status_name')) {
                    $where[] = [
                        'name',
                        'like',
                        '%'.Filters::get(Auth::User()->id, 'lead_statuses', 'lead_status_name').'%',
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
            Filters::put(Auth::user()->id, 'lead_statuses', 'status', $filters['status']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'lead_statuses', 'status');
            } else {
                if (Filters::get(Auth::user()->id, 'lead_statuses', 'status') == 0 || Filters::get(Auth::user()->id, 'lead_statuses', 'status') == 1) {
                    if (Filters::get(Auth::user()->id, 'lead_statuses', 'status') != null) {
                        $where[] = [
                            'active',
                            '=',
                            Filters::get(Auth::user()->id, 'lead_statuses', 'status'),
                        ];
                    }
                }
            }
        }

        return $where;
    }

    /**
     * Get Parent Type Records
     *
     * @param  (int)  $prepend_dropdown_text [Optional] Prepend Dropdown First Row
     * @param  (int)  $account_id Current Organization's ID
     * @param  (array)  $skip_ids IDs which need to skip
     * @param  (int)  $active_records_only Get activated records only
     * @return (mixed)
     */
    public static function getParentRecords($prepend_dropdown_text, $account_id, $skip_ids = [], $active_records_only = false)
    {
        // If not an array then make it an array
        if (! is_array($skip_ids)) {
            $skip_ids = [$skip_ids];
        }

        $where = ['account_id' => $account_id, 'parent_id' => 0];

        if ($active_records_only) {
            $where['active'] = 1;
        }

        if (count($skip_ids)) {
            $records = self::where($where)
                ->whereNotIn('id', $skip_ids)
                ->get()->pluck('name', 'id');
        } else {
            $records = self::where($where)->get()->pluck('name', 'id');
        }

        if ($prepend_dropdown_text) {
            $records->prepend($prepend_dropdown_text, '');
        }

        return $records;
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

        if ($data['parent_id'] == '') {
            $data['parent_id'] = 0;
        }

        if (! isset($data['is_comment'])) {
            $data['is_comment'] = 0;
        }

        // Default Status is set for Junk, set other statuses now
        if (isset($data['is_junk']) && $data['is_junk'] == '1') {
            self::where(['account_id' => $account_id])->update(['is_junk' => 0]);
        }

        // Default Status is set for Open, set other statuses now
        if (isset($data['is_default']) && $data['is_default'] == '1') {
            self::where(['account_id' => $account_id])->update(['is_default' => 0]);
        }

        // Default Status is set for Arrived, set other statuses now
        if (isset($data['is_arrived']) && $data['is_arrived'] == '1') {
            self::where(['account_id' => $account_id])->update(['is_arrived' => 0]);
        }

        // Default Status is set for Converted, set other statuses now
        if (isset($data['is_converted']) && $data['is_converted'] == '1') {
            self::where(['account_id' => $account_id])->update(['is_converted' => 0]);
        }

        // Default Status is set for Booked, set other statuses now
        if (isset($data['is_booked']) && $data['is_booked'] == '1') {
            self::where(['account_id' => $account_id])->update(['is_booked' => 0]);
        }

        $record = self::create($data);
        $record->update(['sort_no' => $record->id]);

        //log request for Create for Audit Trail

        AuditTrails::addEventLogger(self::$_table, 'Create', $data, self::$_fillable, $record);

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

        $lead_statuse = LeadStatuses::getData($id);

        if (! $lead_statuse) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }
        // Check if child records exists or not, If exist then disallow to delete it.
        if (LeadStatuses::isChildExists($id, Auth::User()->account_id)) {
            return collect(['status' => false, 'message' => 'Child records exist, unable to delete resource']);
        }
        $record = $lead_statuse->delete();
        //log request for delete for audit trail
        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        return collect(['status' => true, 'message' => 'Record has been deleted successfully.']);
    }

    /**
     * Inactive Record
     *
     * @param id
     * @return (mixed)
     */
    public static function InactiveRecord($id)
    {
        $lead_statuse = LeadStatuses::getData($id);
        if (! $lead_statuse) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }
        $record = $lead_statuse->update(['active' => 0]);
        AuditTrails::InactiveEventLogger(self::$_table, 'Inactive', self::$_fillable, $id);

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
        $lead_statuse = LeadStatuses::getData($id);
        if (! $lead_statuse) {
            return collect(['status' => true, 'message' => 'Resource not found.']);
        }
        $record = $lead_statuse->update(['active' => 1]);
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
        //dd($request->is_comment);

        $old_data = (LeadStatuses::find($id))->toArray();

        $data = $request->all();

        // Default Status is set for Junk, set other statuses now
        if (isset($data['is_junk']) && $data['is_junk'] == '1') {
            self::where(['account_id' => $account_id])->update(['is_junk' => 0]);
        }

        // Default Status is set for Open, set other statuses now
        if (isset($data['is_default']) && $data['is_default'] == '1') {
            self::where(['account_id' => $account_id])->update(['is_default' => 0]);
        }

        // Default Status is set for Arrived, set other statuses now
        if (isset($data['is_arrived']) && $data['is_arrived'] == '1') {
            self::where(['account_id' => $account_id])->update(['is_arrived' => 0]);
        }

        // Default Status is set for Converted, set other statuses now
        if (isset($data['is_converted']) && $data['is_converted'] == '1') {
            self::where(['account_id' => $account_id])->update(['is_converted' => 0]);
        }

        // Default Status is set for Booked, set other statuses now
        if (isset($data['is_booked']) && $data['is_booked'] == '1') {
            self::where(['account_id' => $account_id])->update(['is_booked' => 0]);
        }

        // Set Account ID
        $data['account_id'] = $account_id;

        if (! isset($data['parent_id'])) {
            $data['parent_id'] = 0;
        }

        if (! isset($data['is_comment'])) {
            $data['is_comment'] = 0;
        }

        $record = self::where([
            'id' => $id,
            'account_id' => $account_id,
        ])->first();

        if (! $record) {
            return null;
        }

        $record->update($data);

        AuditTrails::EditEventLogger(self::$_table, 'Edit', $data, self::$_fillable, $old_data, $id);

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
        return self::where([
            'parent_id' => $id,
            'account_id' => $account_id,
        ])->count();
    }

    public static function getLeadStatuses($excludeIds = false)
    {
        $where = [
            ['account_id', '=', Auth::User()->account_id],
            ['active', '=', '1'],
            ['parent_id', '=', '0'],
        ];

        if ($excludeIds && ! is_array($excludeIds)) {
            $excludeIds = [$excludeIds];
        } else {
            $excludeIds = [];
        }

        if (count($excludeIds)) {
            return self::where($where)->whereNotIn('id', $excludeIds)->OrderBy('sort_no', 'asc')->get()->pluck('name', 'id');
        } else {
            return self::where($where)->OrderBy('sort_no', 'asc')->get()->pluck('name', 'id');
        }
    }
}
