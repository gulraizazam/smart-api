<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;
use Session;
use App\Models\AuditTrails;
use Auth;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use App\Helpers\Filters;
use App\Models\CentertargetMeta;


class Centertarget extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['account_id', 'month', 'year','working_days', 'created_at', 'updated_at', 'deleted_at'];

    protected static $_fillable = ['account_id', 'month', 'year','working_days'];

    protected $table = 'centertarget';

    protected static $_table = 'centertarget';

    /**
     * Get the staff_targets.
     */
    public function center_target_meta()
    {
        return $this->hasMany('App\Models\CentertargetMeta', 'centertarget_id');
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
        $where = Self::centertarget_filters($request, $account_id, $apply_filter);
        return Centertarget::where($where)->count();
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
    static public function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false,$apply_filter = false)
    {
        $where = Self::centertarget_filters($request, $account_id, $apply_filter);

        $orderBy = 'created_at';
        $order = 'desc';

        if ($request->get('order')) {
            $orderColumn = $request->get('order')[0]['column'];
            $orderBy = $request->get('columns')[$orderColumn]['data'];
            if ($orderBy == 'created_at') {
                $orderBy = 'created_at';
            }
            $order = $request->get('order')[0]['dir'];

            Filters::put(Auth::User()->id, 'centertarget', 'order_by', $orderBy);
            Filters::put(Auth::User()->id, 'centertarget', 'order', $order);
        } else {
            if(
                Filters::get(Auth::User()->id, 'centertarget', 'order_by')
                && Filters::get(Auth::User()->id, 'centertarget', 'order')
            ) {
                $orderBy = Filters::get(Auth::User()->id, 'centertarget', 'order_by');
                $order = Filters::get(Auth::User()->id, 'centertarget', 'order');

                if ($orderBy == 'created_at') {
                    $orderBy = 'created_at';
                }
            } else {
                $orderBy = 'created_at';
                $order = 'desc';
                if ($orderBy == 'created_at') {
                    $orderBy = 'created_at';
                }

                Filters::put(Auth::User()->id, 'centertarget', 'order_by', $orderBy);
                Filters::put(Auth::User()->id, 'centertarget', 'order', $order);
            }
        }
         return Centertarget::where($where)
            ->orderby($orderBy, $order)
            ->limit($iDisplayLength)->offset($iDisplayStart)
            ->get();
    }


    /**
     * Get filters
     *
     * @param \Illuminate\Http\Request $request
     * @param (int) $account_id Current Organization's ID
     * @param (boolean) $apply_filter
     * @return (mixed)
     */
    static public function centertarget_filters($request, $account_id, $apply_filter)
    {
        $where = array();
        if ($account_id) {
            $where[] = array(
                'account_id',
                '=',
                $account_id
            );
            Filters::put(Auth::User()->id, 'centertarget', 'account_id', $account_id);
        }  else {

            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'centertarget', 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, 'centertarget', 'account_id')) {
                    $where[] = array(
                        'account_id',
                        '=',
                        Filters::get(Auth::User()->id, 'centertarget', 'account_id')
                    );
                }
            }
        }
        if ($request->get('year')) {
            $where[] = array(
                'year',
                '=',
                $request->get('year')
            );
            Filters::put(Auth::User()->id, 'centertarget', 'year', $request->get('year'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'centertarget', 'year');
            } else {
                if (Filters::get(Auth::User()->id, 'centertarget', 'year')) {
                    $where[] = array(
                        'year',
                        '=',
                        Filters::get(Auth::User()->id, 'centertarget', 'year')
                    );
                }
            }
        }
        if ($request->get('month')) {
            $where[] = array(
                'month',
                '=',
                $request->get('month')
            );
            Filters::put(Auth::User()->id, 'centertarget', 'month', $request->get('month'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'centertarget', 'month');
            } else {
                if (Filters::get(Auth::User()->id, 'centertarget', 'month')) {
                    $where[] = array(
                        'month',
                        '=',
                        Filters::get(Auth::User()->id, 'centertarget', 'month')
                    );
                }
            }
        }
        if ($request->get('created_from') && $request->get('created_from') != '') {
            $where[] = array(
                'created_at',
                '>=',
                $request->get('created_from') . ' 00:00:00'
            );
            Filters::put(Auth::User()->id, 'centertarget', 'created_from', $request->get('created_from'));
        } else {
            if($apply_filter) {
                Filters::forget(Auth::User()->id, 'centertarget', 'created_from');
            } else {
                if(Filters::get(Auth::User()->id, 'centertarget', 'created_from')) {
                    $where[] = array(
                        'created_at',
                        '>=',
                        Filters::get(Auth::User()->id, 'centertarget', 'created_from') . ' 00:00:00'
                    );
                }
            }
        }
        if ($request->get('created_to') && $request->get('created_to') != '') {
            $where[] = array(
                'created_at',
                '<=',
                $request->get('created_to') . ' 23:59:59'
            );
            Filters::put(Auth::User()->id, 'centertarget', 'created_to', $request->get('created_to'));
        } else {
            if($apply_filter) {
                Filters::forget(Auth::User()->id, 'centertarget', 'created_to');
            } else {
                if(Filters::get(Auth::User()->id, 'centertarget', 'created_to')) {
                    $where[] = array(
                        'created_at',
                        '<=',
                        Filters::get(Auth::User()->id, 'centertarget', 'created_to') . ' 23:59:59'
                    );
                }
            }
        }
        return $where;
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

        //log request for Create for Audit Trail
        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

        foreach ($data['target_amount'] as $key => $amount) {
            $targetCentermeta = CentertargetMeta::createRecord($key, $amount, $account_id, $record);
        }
        return $record;
    }

    /**
     * Update Record
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return (mixed)
     */
    static function updateRecord($id, $request, $account_id)
    {
        $old_data = (Centertarget::find($id))->toArray();

        $data = $request->all();

        $record = self::where([
            'id' => $id,
            'account_id' => $account_id
        ])->first();

        if (!$record) {
            return null;
        }

        $record->update($data);

        AuditTrails::editEventLogger(self::$_table, 'Edit', $data, self::$_fillable, $old_data, $id);

        foreach ($data['target_amount'] as $key => $amount) {
            $targetCentermeta = CentertargetMeta::updateRecord($key, $amount, $account_id, $record);
        }
        return $record;
    }
    /**
     * delete Record
     *
     * @param id
     *
     * @return (mixed)
     */
    static function deleteRecord($id)
    {
        $center_target = self::find($id);

        if (!$center_target) {
            flash('Resource not found.')->error()->important();
            return redirect()->route('admin.centre_targets.index');
        }
        // Remove belonging records records
        $center_target->center_target_meta()->delete();

        $record = $center_target->delete();

        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        return $record;

    }
}
