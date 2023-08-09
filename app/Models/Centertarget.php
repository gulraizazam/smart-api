<?php

namespace App\Models;

use DateTime;
use App\Helpers\Filters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\SoftDeletes;

class Centertarget extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['account_id', 'month', 'year', 'working_days', 'created_at', 'updated_at', 'deleted_at'];

    protected static $_fillable = ['account_id', 'month', 'year', 'working_days'];

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
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getTotalRecords(Request $request, $account_id = false, $apply_filter = false)
    {
        $where = self::centertarget_filters($request, $account_id, $apply_filter);

        return Centertarget::where($where)->count();
    }

    /**
     * Get Records
     *
     * @param  (int)  $iDisplayStart Start Index
     * @param  (int)  $iDisplayLength Total Records Length
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false, $apply_filter = false, $filters = [])
    {
        $where = self::centertarget_filters($request, $account_id, $apply_filter, $filters);

        if ($request->has('sort')) {

            [$orderBy, $order] = getSortBy($request);

            Filters::put(Auth::User()->id, 'centertarget', 'order_by', $orderBy);
            Filters::put(Auth::User()->id, 'centertarget', 'order', $order);
        } else {
            if (
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
     * @param  \Illuminate\Http\Request  $request
     * @param  (int)  $account_id Current Organization's ID
     * @param  (boolean)  $apply_filter
     * @return (mixed)
     */
    public static function centertarget_filters($request, $account_id, $apply_filter, $filters = [])
    {
        $where = [];
        if (hasFilter($filters, 'created_at')) {
            $date_range = explode(' - ', $filters['created_at']);
            $start_date_time = date('Y-m-d H:i:s', strtotime($date_range[0]));
            $end_date_string = new DateTime($date_range[1]);
            $end_date_string->setTime(23, 59, 0);
            $end_date_time = $end_date_string->format('Y-m-d H:i:s');
        } else {
            $start_date_time = null;
            $end_date_time = null;
        }

        if ($account_id) {
            $where[] = [
                'account_id',
                '=',
                $account_id,
            ];
            Filters::put(Auth::User()->id, 'centertarget', 'account_id', $account_id);
        } else {

            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'centertarget', 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, 'centertarget', 'account_id')) {
                    $where[] = [
                        'account_id',
                        '=',
                        Filters::get(Auth::User()->id, 'centertarget', 'account_id'),
                    ];
                }
            }
        }
        if (hasFilter($filters, 'year')) {
            $where[] = [
                'year',
                '=',
                $filters['year'],
            ];
            Filters::put(Auth::User()->id, 'centertarget', 'year', $filters['year']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'centertarget', 'year');
            } else {
                if (Filters::get(Auth::User()->id, 'centertarget', 'year')) {
                    $where[] = [
                        'year',
                        '=',
                        Filters::get(Auth::User()->id, 'centertarget', 'year'),
                    ];
                }
            }
        }
        if (hasFilter($filters, 'month')) {
            $where[] = [
                'month',
                '=',
                $filters['month'],
            ];
            Filters::put(Auth::User()->id, 'centertarget', 'month', $filters['month']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'centertarget', 'month');
            } else {
                if (Filters::get(Auth::User()->id, 'centertarget', 'month')) {
                    $where[] = [
                        'month',
                        '=',
                        Filters::get(Auth::User()->id, 'centertarget', 'month'),
                    ];
                }
            }
        }
        if (hasFilter($filters, 'created_at')) {
            $where[] = ['created_at', '>=', $start_date_time];
            $where[] = ['created_at', '<=', $end_date_time];
            Filters::put(Auth::User()->id, 'centertarget', 'created_at', $filters['created_at']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'centertarget', 'created_at');
            } else {
                if (Filters::get(Auth::User()->id, 'centertarget', 'created_at')) {
                    $where[] = ['created_at', '>=', Filters::get(Auth::User()->id, 'centertarget', 'created_at')];
                }
            }
        }

        return $where;
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
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function updateRecord($id, $request, $account_id)
    {
        $old_data = (Centertarget::find($id))->toArray();

        $data = $request->all();

        $record = self::where([
            'id' => $id,
            'account_id' => $account_id,
        ])->first();

        if (! $record) {
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
     * @return (mixed)
     */
    public static function deleteRecord($id)
    {
        $center_target = self::find($id);

        if (! $center_target) {
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
