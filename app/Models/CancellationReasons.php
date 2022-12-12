<?php

namespace App\Models;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\AuditTrails;
use Auth;

class CancellationReasons extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['name', 'active', 'account_id', 'created_at', 'updated_at', 'appointment_type_id', 'sort_no'];

    protected static $_fillable = ['name', 'active', 'appointment_type_id'];

    protected $table = 'cancellation_reasons';

    protected static $_table = 'cancellation_reasons';

    /**
     * Get the Appointments for Treatment.
     */
    public function appointments()
    {
        return $this->hasMany('App\Models\Appointments', 'cancellation_reason_id');
    }

    /**
     * Get active and sorted data only.
     */
    static public function getActiveSorted()
    {
        return self::where(['active' => 1])->OrderBy('sort_no','asc')->get()->pluck('name','id');
    }

    /**
     * Get Total Records
     *
     * @param \Illuminate\Http\Request $request
     * @param (int) $account_id Current Organization's ID
     *
     * @return (mixed)
     */
    static public function getTotalRecords(Request $request, $account_id = false)
    {
        $where = array();

        if ($account_id) {
            $where[] = array(
                'account_id',
                '=',
                $account_id
            );
        }

        if ($request->get('name')) {
            $where[] = array(
                'name',
                'like',
                '%' . $request->get('name') . '%'
            );
        }

        if ($request->get('is_featured') != '') {
            $where[] = array(
                'is_featured',
                '=',
                $request->get('is_featured')
            );
        }

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
    static public function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false)
    {
        $where = array();

        if ($account_id) {
            $where[] = array(
                'account_id',
                '=',
                $account_id
            );
        }

        if ($request->get('name')) {
            $where[] = array(
                'name',
                'like',
                '%' . $request->get('name') . '%'
            );
        }

        if ($request->get('is_featured') != '') {
            $where[] = array(
                'is_featured',
                '=',
                $request->get('is_featured')
            );
        }

        if (count($where)) {
            return self::where($where)->limit($iDisplayLength)->offset($iDisplayStart)->get();
        } else {
            return self::limit($iDisplayLength)->offset($iDisplayStart)->get();
        }
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
     * Inactive Record
     *
     * @param id
     *
     * @return (mixed)
     */
    static public function inactiveRecord($id)
    {

        $cancellation_reason = CancellationReasons::getData($id);

        if (!$cancellation_reason) {

            flash('Resource not found.')->error()->important();
            return redirect()->route('admin.cancellation_reasons.index');
        }

        $record = $cancellation_reason->update(['active' => 0]);

        flash('Record has been inactivated successfully.')->success()->important();

        AuditTrails::inactiveEventLogger(self::$_table, 'inactive', self::$_fillable, $id);

        return $record;

    }

    /**
     * Active Record
     *
     * @param id
     *
     * @return (mixed)
     */
    static public function activeRecord($id)
    {

        $cancellation_reason = CancellationReasons::getData($id);

        if (!$cancellation_reason) {
            flash('Resource not found.')->error()->important();
            return redirect()->route('admin.cancellation_reasons.index');
        }

        $record = $cancellation_reason->update(['active' => 1]);

        flash('Record has been inactivated successfully.')->success()->important();

        AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

        return $record;

    }

    /**
     * Delete Record
     *
     * @param id
     *
     * @return (mixed)
     */
    static public function deleteRecord($id)
    {

        $cancellation_reason = CancellationReasons::getData($id);

        if (!$cancellation_reason) {
            flash('Resource not found.')->error()->important();
            return redirect()->route('admin.cancellation_reasons.index');
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (CancellationReasons::isChildExists($id, Auth::User()->account_id)) {
            flash('Child records exist, unable to delete resource')->error()->important();
            return redirect()->route('admin.cancellation_reasons.index');
        }

        $record = $cancellation_reason->delete();

        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        flash('Record has been deleted successfully.')->success()->important();

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
        $old_data = (CancellationReasons::find($id))->toArray();

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

        $record->update($data);

        AuditTrails::editEventLogger(self::$_table, 'edit', $data, self::$_fillable, $old_data, $id);

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
