<?php

namespace App\Models;

use Auth;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;

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
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getTotalRecords(Request $request, $account_id = false, $id = false)
    {
        $where = [];

        if ($id != false) {
            $where[] = [
                'appointment_id',
                '=',
                $id,
            ];
        }
        if ($request->get('type')) {
            $where[] = [
                'type',
                '=',
                $request->get('type'),
            ];
        }
        if ($request->get('created_from') && $request->get('created_from') != '') {
            $where[] = [
                'created_at',
                '>=',
                $request->get('created_from').' 00:00:00',
            ];
        }
        if ($request->get('created_to') && $request->get('created_to') != '') {
            $where[] = [
                'created_at',
                '<=',
                $request->get('created_to').' 23:59:59',
            ];
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
     * @param  (int)  $iDisplayStart Start Index
     * @param  (int)  $iDisplayLength Total Records Length
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false, $id = false)
    {
        $where = [];

        $filters = getFilters($request->all());

        if ($id != false) {
            $where[] = [
                'appointment_id',
                '=',
                $id,
            ];
        }
        if (hasFilter($filters, 'type')) {
            $where[] = [
                'type',
                '=',
                $filters['type'],
            ];
        }
        if (hasFilter($filters, 'id')) {
            $where[] = [
                'id',
                'like',
                '%'.$filters['id'].'%',
            ];
        }
        if (hasFilter($filters, 'created_from')) {
            $where[] = [
                'created_at',
                '>=',
                $filters['created_from'].' 00:00:00',
            ];
        }
        if (hasFilter($filters, 'created_to')) {
            $where[] = [
                'created_at',
                '<=',
                $filters['created_to'].' 23:59:59',
            ];
        }

        [$orderBy, $order] = getSortBy($request, 'id');

        if (count($where)) {
            return self::where($where)->limit($iDisplayLength)->offset($iDisplayStart)->orderby($orderBy, $order)->get();
        } else {
            return self::limit($iDisplayLength)->offset($iDisplayStart)->orderby($orderBy, $order)->get();
        }
    }

    /**
     * Check if child records exist
     *
     * @param  (int)  $id
     * @return (boolean)
     */
    public static function isChildExists($id, $account_id)
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
     * @return (mixed)
     */
    public static function DeleteRecord($id)
    {
        $appointmentimage = Appointmentimage::find($id);

        if (! $appointmentimage) {

            return [
                'status' => false,
                'message' => 'Resource not found.',
            ];
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (Appointmentimage::isChildExists($id, Auth::User()->account_id)) {

            return [
                'status' => false,
                'message' => 'Child records exist, unable to delete resource.',
            ];
        }

        $record = $appointmentimage->delete();

        //log request for delete for audit trail

        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        return [
            'status' => true,
            'message' => 'Record has been deleted successfully.',
        ];

    }

    /**
     * Create Record
     *
     * @param \$data
     * @return (mixed)
     */
    public static function createRecord($data, $id)
    {
        $record = self::create($data);

        //log request for Create for Audit Trail

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record, $id);

        return $record;
    }
}
