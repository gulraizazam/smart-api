<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use App\Helpers\Filters;


class AppointmentStatuses extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['name', 'sort_no', 'active', 'created_at', 'updated_at','deleted_at', 'is_comment', 'is_arrived', 'parent_id', 'account_id', 'allow_message', 'is_default', 'is_cancelled', 'is_unscheduled'];

    protected static $_fillable = ['name', 'active', 'parent_id', 'is_comment', 'allow_message', 'is_default', 'is_arrived', 'is_cancelled', 'is_unscheduled','deleted_at'];

    protected $table = 'appointment_statuses';

    protected static $_table = 'appointment_statuses';

    /**
     * Get the Appointments for Appointment Status.
     */
    public function appointments()
    {
        return $this->hasMany('App\Models\Appointments', 'appointment_status_id');
    }

    /**
     * Get active and sorted data only.
     */
    static public function getActiveSorted($appointment_status_id = false, $account_id)
    {
        if($appointment_status_id) {
            return self::where(['active' => 1, 'parent_id' => $appointment_status_id, 'account_id' => $account_id])->OrderBy('sort_no', 'asc')->get()->pluck('name', 'id');
        } else {
            return self::where(['active' => 1, 'account_id' => $account_id])->OrderBy('sort_no', 'asc')->get()->pluck('name', 'id');
        }
    }

    /**
     * Get active and sorted data only.
     */
    static public function getBaseActiveSorted($account_id, $exclude_appointment_status_id = false)
    {
        if($exclude_appointment_status_id) {
            return self::where(['active' => 1, 'parent_id' => 0, 'account_id' => $account_id])->where('id', '!=', $exclude_appointment_status_id)->OrderBy('sort_no', 'asc')->get()->pluck('name', 'id');
        }

        return self::where(['active' => 1, 'parent_id' => 0, 'account_id' => $account_id])->OrderBy('sort_no', 'asc')->get()->pluck('name', 'id');
    }

    /**
     * Get active and sorted data only.
     */
    static public function getActiveOnly()
    {
        return self::where(['active' => 1])->OrderBy('sort_no', 'asc')->get();
    }

    /**
     * Get Default Status
     */
    static public function getADefaultStatusOnly($account_id)
    {
        return self::where(['is_default' => '1', 'account_id' => $account_id])->first();
    }

    /**
     * Get Cancelled Status
     */
    static public function getCancelledStatusOnly($account_id)
    {
        return self::where(['is_cancelled' => '1', 'account_id' => $account_id])->first();
    }

    /**
     * Get Un-Scheduled Status
     */
    static public function getUnScheduledStatusOnly($account_id)
    {
        return self::where(['is_unscheduled' => '1', 'account_id' => $account_id])->first();
    }

    /**
     * Get Default Status
     */
    static public function getRecordByID($id, $account_id)
    {
        return self::where(['is_default' => '1', 'account_id' => $account_id])->first();
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
        $where = Self::appointment_statuses_filters($request, $account_id, $apply_filter);
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
        $where = Self::appointment_statuses_filters($request, $account_id, $apply_filter);
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
    static public function appointment_statuses_filters($request, $account_id, $apply_filter)
    {
        $where = array();
        if ($account_id) {
            $where[] = array(
                'account_id',
                '=',
                $account_id
            );
            Filters::put(Auth::User()->id, 'appointment_statuses', 'account_id', $account_id);
        }  else {

            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'appointment_statuses', 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, 'appointment_statuses', 'account_id')) {
                    $where[] = array(
                        'account_id',
                        '=',
                        Filters::get(Auth::User()->id, 'appointment_statuses', 'account_id')
                    );
                }
            }
        }
        if ($request->get('appointment_status_name')) {
            $where[] = array(
                'name',
                'like',
                '%' . $request->get('appointment_status_name') . '%'
            );
            Filters::put(Auth::User()->id, 'appointment_statuses', 'appointment_status_name', $request->get('appointment_status_name'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'appointment_statuses', 'appointment_status_name');
            } else {
                if (Filters::get(Auth::User()->id, 'appointment_statuses', 'appointment_status_name')) {
                    $where[] = array(
                        'name',
                        'like',
                        '%' . Filters::get(Auth::User()->id, 'appointment_statuses', 'appointment_status_name') . '%'
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
            Filters::put(Auth::user()->id, 'appointment_statuses', 'status', $request->get('status'));
        } else {
            if ( $apply_filter ){
                Filters::forget( Auth::user()->id, 'appointment_statuses', 'status');
            } else {
                if ( Filters::get(Auth::user()->id, 'appointment_statuses', 'status') == 0 || Filters::get(Auth::user()->id, 'appointment_statuses', 'status') == 1){
                    if ( Filters::get(Auth::user()->id, 'appointment_statuses', 'status') != null ){
                        $where[] = array(
                            'active',
                            '=',
                            Filters::get( Auth::user()->id, 'appointment_statuses', 'status')
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
     * Get All Records
     *
     * @param (int) $account_id Current Organization's ID
     *
     * @return (mixed)
     */
    static public function getAllParentRecords($account_id)
    {
        return self::where(['account_id' => $account_id, 'parent_id' => 0])->get();
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

        if ($data['parent_id'] == '') {
            $data['parent_id'] = 0;
//            $data['is_comment'] = 0;
            if (!isset($data['allow_message'])) {
                $data['allow_message'] = 0;
            }
        } else {
            $data['allow_message'] = 0;
//            if (!isset($data['is_comment'])) {
//                $data['is_comment'] = 0;
//            }
        }

        // Set comment as empty if is_comment is not set
        if (!isset($data['is_comment'])) {
            $data['is_comment'] = 0;
        }

        // Default Status is set, set other statuses now
        if (isset($data['is_default']) && $data['is_default'] == '1') {
            self::where(['account_id' => $account_id])->update(array('is_default' => 0));
        }

        // Arrived Status is set, set other statuses now
        if (isset($data['is_arrived']) && $data['is_arrived'] == '1') {
            self::where(['account_id' => $account_id])->update(array('is_arrived' => 0));
        }

        // Cancelled Status is set, set other statuses now
        if (isset($data['is_cancelled']) && $data['is_cancelled'] == '1') {
            self::where(['account_id' => $account_id])->update(array('is_cancelled' => 0));
        }

        // Un-Scheduled Status is set, set other statuses now
        if (isset($data['is_unscheduled']) && $data['is_unscheduled'] == '1') {
            self::where(['account_id' => $account_id])->update(array('is_unscheduled' => 0));
        }

        $record = self::create($data);

        $record->update(['sort_no' => $record->id]);

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

        return $record;

    }

    /**
     * Inactive Record
     *
     * @param $id
     *
     * @return (mixed)
     */
    static public function inactiveRecord($id)
    {

        $appointment_statuse = AppointmentStatuses::getData($id);

        if (!$appointment_statuse) {

            flash('Resource not found.')->error()->important();
            return redirect()->route('admin.appointment_statusess.index');
        }

        $record = $appointment_statuse->update(['active' => 0]);

        flash('Record has been inactivated successfully.')->success()->important();

        AuditTrails::inactiveEventLogger(self::$_table, 'inactive', self::$_fillable, $id);

        return $record;
    }

    /**
     * active Record
     *
     * @param $id
     *
     * @return (mixed)
     */
    static public function activeRecord($id)
    {

        $appointment_statuse = AppointmentStatuses::getData($id);

        if (!$appointment_statuse) {
            flash('Resource not found.')->error()->important();
            return redirect()->route('admin.appointment_statusess.index');
        }

        $record = $appointment_statuse->update(['active' => 1]);

        flash('Record has been activated successfully.')->success()->important();

        AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

        return $record;

    }

    /**
     * delete Record
     *
     * @param $id
     *
     * @return (mixed)
     */
    static public function deleteRecord($id)
    {

        $appointment_statuse = AppointmentStatuses::getData($id);

        if (!$appointment_statuse) {
            flash('Resource not found.')->error()->important();
            return redirect()->route('admin.appointment_statusess.index');
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (AppointmentStatuses::isChildExists($id, Auth::User()->account_id)) {
            flash('Child records exist, unable to delete resource')->error()->important();
            return redirect()->route('admin.appointment_statusess.index');
        }

        $record = $appointment_statuse->delete();

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
        $old_data = (AppointmentStatuses::find($id))->toArray();

        $data = $request->all();

        // Default Status is set, set other statuses now
        if (isset($data['is_default']) && $data['is_default'] == '1') {
            self::where(['account_id' => $account_id])->update(array('is_default' => 0));
        }

        // Arrived Status is set, set other statuses now
        if (isset($data['is_arrived']) && $data['is_arrived'] == '1') {
            self::where(['account_id' => $account_id])->update(array('is_arrived' => 0));
        }

        // Cancelled Status is set, set other statuses now
        if (isset($data['is_cancelled']) && $data['is_cancelled'] == '1') {
            self::where(['account_id' => $account_id])->update(array('is_cancelled' => 0));
        }

        // Un-Scheduled Status is set, set other statuses now
        if (isset($data['is_unscheduled']) && $data['is_unscheduled'] == '1') {
            self::where(['account_id' => $account_id])->update(array('is_unscheduled' => 0));
        }

        // Set comment as empty if is_comment is not set
        if (!isset($data['is_comment'])) {
            $data['is_comment'] = 0;
        }

        // Set Account ID
        $data['account_id'] = $account_id;

        if ($data['parent_id'] == '') {
            $data['parent_id'] = 0;
//            $data['is_comment'] = 0;
            if (!isset($data['allow_message'])) {
                $data['allow_message'] = 0;
            }
        } else {
            $data['allow_message'] = 0;
//            if (!isset($data['is_comment'])) {
//                $data['is_comment'] = 0;
//            }
        }

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

    /**
     * Get Parent Type Records
     *
     * @param (int) $prepend_dropdown_text [Optional] Prepend Dropdown First Row
     * @param (int) $account_id Current Organization's ID
     * @param (array) $skip_ids IDs which need to skip
     * @param (int) $active_records_only Get activated records only
     *
     * @return (mixed)
     */
    static public function getParentRecords($prepend_dropdown_text = false, $account_id, $skip_ids = array(), $active_records_only = false)
    {
        // If not an array then make it an array
        if (!is_array($skip_ids)) {
            $skip_ids = array($skip_ids);
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
}
