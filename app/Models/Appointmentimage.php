<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use App\Models\AuditTrails;
use Auth;
use App\Helpers\GeneralFunctions;

class Appointmentimage extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['image_name', 'image_path', 'type', 'appointment_id', 'created_at', 'updated_at', 'deleted_at'];

    protected static $_fillable = ['image_name', 'image_path', 'type', 'appointment_id'];

    protected $table = 'appointmentimages';

    protected static $_table = 'appointmentimages';


    public function appointment()
    {
        return $this->belongsTo(Appointments::class);
    }
    /**
     * Get Total Records
     *
     * @param \Illuminate\Http\Request $request
     * @param (int) $account_id Current Organization's ID
     *
     * @return (mixed)
     */
    static public function getTotalRecords(Request $request, $account_id = false,$id = false)
    {
        $where = array();

        if($id != false){
            $where[] = array(
                'appointment_id',
                '=',
                $id
            );
        }
        if ($request->get('type')) {
            $where[] = array(
                'type',
                '=',
                $request->get('type')
            );
        }
        if ($request->get('created_from') && $request->get('created_from') != '') {
            $where[] = array(
                'created_at',
                '>=',
                $request->get('created_from') . ' 00:00:00'
            );
        }
        if ($request->get('created_to') && $request->get('created_to') != '') {
            $where[] = array(
                'created_at',
                '<=',
                $request->get('created_to') . ' 23:59:59'
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
    static public function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false, $id = false)
    {
        $where = array();

        if($id != false){
            $where[] = array(
                'appointment_id',
                '=',
                $id
            );
        }
        if ($request->get('type')) {
            $where[] = array(
                'type',
                '=',
                $request->get('type')
            );
        }
        if ($request->get('id')) {
            $where[] = array(
                'id',
                'like',
                '%' . $request->get('id') . '%'
            );
        }
        if ($request->get('created_from') && $request->get('created_from') != '') {
            $where[] = array(
                'created_at',
                '>=',
                $request->get('created_from') . ' 00:00:00'
            );
        }
        if ($request->get('created_to') && $request->get('created_to') != '') {
            $where[] = array(
                'created_at',
                '<=',
                $request->get('created_to') . ' 23:59:59'
            );
        }
        $orderBy = 'created_at';
        $order = 'desc';

        if ($request->get('order')[0]['dir']) {
            $orderColumn = $request->get('order')[0]['column'];
            $orderBy = $request->get('columns')[$orderColumn]['data'];
            $order = $request->get('order')[0]['dir'];
        }
        if (count($where)) {
            return self::where($where)->limit($iDisplayLength)->offset($iDisplayStart)->orderby($orderBy,$order)->get();
        } else {
            return self::limit($iDisplayLength)->offset($iDisplayStart)->orderby($orderBy,$order)->get();
        }
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
//        if (
//            Locations::where(['city_id' => $id, 'account_id' => $account_id])->count() ||
//            Leads::where(['city_id' => $id, 'account_id' => $account_id])->count() ||
//            Appointments::where(['city_id' => $id, 'account_id' => $account_id])->count()
//        ) {
//            return true;
//        }

        return false;
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
        $appointmentimage = Appointmentimage::find($id);

        if (!$appointmentimage) {

            flash('Resource not found.')->error()->important();
            return redirect()->route('admin.appointmentsimage.imageindex',[$appointmentimage->appointment_id]);

        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (Appointmentimage::isChildExists($id, Auth::User()->account_id)) {

            flash('Child records exist, unable to delete resource')->error()->important();
            return redirect()->route('admin.appointmentsimage.imageindex',[$appointmentimage->appointment_id]);
        }

        $record = $appointmentimage->delete();

        //log request for delete for audit trail

        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        flash('Record has been deleted successfully.')->success()->important();

        return $record;

    }

    /**
     * Create Record
     *
     * @param \$data
     *
     * @return (mixed)
     */
    static public function createRecord($data,$id)
    {
        $record = self::create($data);

        //log request for Create for Audit Trail

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable,$record,$id);

        return $record;
    }

}
