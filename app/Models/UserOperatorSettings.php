<?php

namespace App\Models;

use App\Helpers\Filters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Auth;

class UserOperatorSettings extends BaseModal
{
    use SoftDeletes;

    protected $fillable = [
        'operator_id', 'operator_name', 'url', 'username', 'password', 'mask', 'test_mode', 'string_1', 'string_2',
        'account_id', 'created_at', 'updated_at'
    ];
    protected static $_fillable = ['operator_id', 'operator_name', 'url', 'username', 'password', 'mask', 'test_mode', 'string_1', 'string_2'];

    protected $table = 'user_operator_settings';

    protected static $_table = 'user_operator_settings';

    /**
     * Get Record
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return (mixed)
     */
    static public function getRecord($account_id,$data)
    {
        return self::where([
            ['account_id','=', $account_id],
            ['id','=',$data]
        ])->first();
    }

    /**
     * Get Operators
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return (mixed)
     */
    static public function getGlobalOperators()
    {
        return GlobalOperatorSettings::get()->pluck('operator_name', 'id');
    }

    /**
     * Get Operators by ID
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return (mixed)
     */
    static public function getGlobalOperator($operator_id)
    {
        return GlobalOperatorSettings::where(array('id' => $operator_id))->first();
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
        $old_data = (UserOperatorSettings::find($id))->toArray();
//        $map_array = array(
//            'operator_id', 'operator_name', 'url', 'username', 'password', 'mask', 'test_mode', 'string_1', 'string_2'
//        );
//
//        $GlobalOperatorSetting = UserOperatorSettings::getGlobalOperator($request->get('operator_id'));
//        if ($GlobalOperatorSetting) {
//            $GlobalOperatorSetting = $GlobalOperatorSetting->toArray();
//        }
//
        $data = $request->all();
//
//        foreach ($map_array as $map_id) {
//            if (array_key_exists($map_id, $GlobalOperatorSetting) && $GlobalOperatorSetting[$map_id]) {
//                $data[$map_id] = $GlobalOperatorSetting[$map_id];
//            }
//        }
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
     * Get Total Records
     *
     * @param \Illuminate\Http\Request $request
     * @param (int) $account_id Current Organization's ID
     *
     * @return (mixed)
     */
    static public function getTotalRecords(Request $request, $account_id = false, $apply_filter = false)
    {
        $where = Self::operators_filters($request, $account_id, $apply_filter);

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
        $where = Self::operators_filters($request, $account_id, $apply_filter);

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
    static public function operators_filters($request, $account_id, $apply_filter)
    {

        $where = array();

        if ($account_id) {
            $where[] = array(
                'account_id',
                '=',
                $account_id
            );
            Filters::put(Auth::User()->id, 'operators', 'account_id', $account_id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'operators', 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, 'operators', 'account_id')) {
                    $where[] = array(
                        'account_id',
                        '=',
                        Filters::get(Auth::User()->id, 'operators', 'account_id')
                    );
                }
            }
        }
        if ($request->get('operator_name')) {
            $where[] = array(
                'operator_name',
                'like',
                '%' . $request->get('operator_name') . '%'
            );
            Filters::put(Auth::User()->id, 'operators', 'operator_name', $request->get('operator_name'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'operators', 'operator_name');
            } else {
                if (Filters::get(Auth::User()->id, 'operators', 'operator_name')) {
                    $where[] = array(
                        'operator_name',
                        'like',
                        '%' . Filters::get(Auth::User()->id, 'operators', 'operator_name') . '%'
                    );
                }
            }
        }

        return $where;
    }
}
