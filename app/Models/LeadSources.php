<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use App\Models\AuditTrails;
use Auth;
use App\Helpers\Filters;

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
    static public function getActiveSorted()
    {
        return self::where([
            ['account_id', '=', Auth::User()->account_id],
            ['active', '=', '1']
        ])->OrderBy('sort_no', 'asc')->get()->pluck('name', 'id');
    }

    /**
     * Get active and sorted data only.
     */
    static public function getActiveOnly()
    {
        return self::where(['active' => 1])->OrderBy('sort_no','asc')->get();
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
        $where = Self::lead_sources_filters($request, $account_id, $apply_filter);

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
        $where = Self::lead_sources_filters($request, $account_id, $apply_filter);
        if (count($where)) {
            return self::where($where)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('sort_no')->get();
        } else {
            return self::limit($iDisplayLength)->offset($iDisplayStart)->orderBy('sort_no')->get();
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
    static public function lead_sources_filters($request, $account_id, $apply_filter)
    {
        $where = array();
        if ($account_id) {
            $where[] = array(
                'account_id',
                '=',
                $account_id
            );
            Filters::put(Auth::User()->id, 'lead_sources', 'account_id', $account_id);
        }  else {

            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'lead_sources', 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, 'lead_sources', 'account_id')) {
                    $where[] = array(
                        'account_id',
                        '=',
                        Filters::get(Auth::User()->id, 'lead_sources', 'account_id')
                    );
                }
            }
        }
        if ($request->get('lead_status_name')) {
            $where[] = array(
                'name',
                'like',
                '%' . $request->get('lead_status_name') . '%'
            );
            Filters::put(Auth::User()->id, 'lead_sources', 'lead_status_name', $request->get('lead_status_name'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'lead_sources', 'lead_status_name');
            } else {
                if (Filters::get(Auth::User()->id, 'lead_sources', 'lead_status_name')) {
                    $where[] = array(
                        'name',
                        'like',
                        '%' . Filters::get(Auth::User()->id, 'lead_sources', 'lead_status_name') . '%'
                    );
                }
            }
        }

        if ( $request->get('status') && $request->get('status') != null || $request->get('status') == 0 && $request->get('status') != null ){
            $where[] = array(
                'active',
                '=',
                $request->get('status')
            );
            Filters::put(Auth::user()->id, 'lead_sources', 'status', $request->get('status'));
        } else {
            if ( $apply_filter ){
                Filters::forget( Auth::user()->id, 'lead_sources', 'status');
            } else {
                if ( Filters::get(Auth::user()->id, 'lead_sources', 'status') == 0 || Filters::get(Auth::user()->id, 'lead_sources', 'status') == 1 ){
                    if ( Filters::get( Auth::user()->id ,'lead_sources', 'status') != null ){
                        $where[] = array(
                            'active',
                            '=',
                            Filters::get( Auth::user()->id, 'lead_sources', 'status')
                        );
                    }
                }
            }
        }

        return $where;
    }

    /**
     * Get All Records
     *
     * @param (int) $account_id Current Organization's ID
     *
     * @return (mixed)
     */
    static public function getAllRecordsDictionary($account_id)
    {
        return self::where(['account_id' => $account_id])->get()->getDictionary();
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
     *
     * @return (mixed)
     */
    static public function DeleteRecord($id)
    {

        $lead_source = LeadSources::getData($id);

        if (!$lead_source) {
            flash('Resource not found.')->error()->important();
            return redirect()->route('admin.lead_sources.index');
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (LeadSources::isChildExists($id, Auth::User()->account_id)) {
            flash('Child records exist, unable to delete resource')->error()->important();
            return redirect()->route('admin.lead_sources.index');
        }

        $record = $lead_source->delete();

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
    static public function InactiveRecord($id)
    {

        $lead_source = LeadSources::getData($id);

        if (!$lead_source) {
            flash('Resource not found.')->error()->important();
            return redirect()->route('admin.lead_sources.index');
        }

        $record = $lead_source->update(['active' => 0]);

        flash('Record has been inactivated successfully.')->success()->important();

        AuditTrails::inactiveEventLogger(self::$_table, 'inactive', self::$_fillable, $id);

        return $record;
    }

    /**
     * active Record
     *
     * @param id
     *
     * @return (mixed)
     */
    static public function activeRecord($id)
    {

        $lead_source = LeadSources::getData($id);

        if (!$lead_source) {
            flash('Resource not found.')->error()->important();
            return redirect()->route('admin.lead_sources.index');
        }

        $record = $lead_source->update(['active' => 1]);

        flash('Record has been activated successfully.')->success()->important();

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
        $old_data = (LeadSources::find($id))->toArray();

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
}
