<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use App\Models\AuditTrails;
use Auth;
use App\Helpers\Filters;


class Settings extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['name', 'data', 'account_id', 'slug', 'active', 'created_at', 'updated_at'];

    protected static $_fillable = ['name', 'data', 'slug', 'active'];

    protected $table = 'settings';

    protected static $_table = 'settings';
    /**
     * Get Total Records
     *
     * @param \Illuminate\Http\Request $request
     * @param (int) $account_id Current Organization's ID
     *
     * @return (mixed)
     */
    static public function getTotalRecords(Request $request, $account_id = false,$apply_filter)
    {
        $where = Self::settings_filters($request, $account_id, $apply_filter);
        if(count($where)) {
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
        $where = Self::settings_filters($request, $account_id, $apply_filter);

        if(count($where)) {
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
    static public function settings_filters($request, $account_id, $apply_filter)
    {
        $where = array();

        if($account_id) {
            $where[] = array(
                'account_id',
                '=',
                $account_id
            );
            Filters::put(Auth::User()->id, 'settings', 'account_id', $account_id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'settings', 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, 'settings', 'account_id')) {
                    $where[] = array(
                        'account_id',
                        '=',
                        Filters::get(Auth::User()->id, 'settings', 'account_id')
                    );
                }
            }
        }
        if ($request->get('setting_name')) {
            $where[] = array(
                'name',
                'like',
                '%' . $request->get('setting_name') . '%'
            );
            Filters::put(Auth::User()->id, 'settings', 'setting_name', $request->get('setting_name'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'settings', 'setting_name');
            } else {
                if (Filters::get(Auth::User()->id, 'settings', 'setting_name')) {
                    $where[] = array(
                        'name',
                        'like',
                        '%' . Filters::get(Auth::User()->id, 'settings', 'setting_name') . '%'
                    );
                }
            }
        }
        if ($request->get('setting_data')) {
            $where[] = array(
                'data',
                'like',
                '%' . $request->get('setting_data') . '%'
            );
            Filters::put(Auth::User()->id, 'settings', 'setting_data', $request->get('setting_data'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'settings', 'setting_data');
            } else {
                if (Filters::get(Auth::User()->id, 'settings', 'setting_data')) {
                    $where[] = array(
                        'data',
                        'like',
                        '%' . Filters::get(Auth::User()->id, 'settings', 'setting_data') . '%'
                    );
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
        $data['slug'] = 'custom';

        $record = self::create($data);
        $record->update(['sort_no' => $record->id]);

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
        $old_data = (Settings::find($id))->toArray();

        $data = $request->all();
        // Set Account ID
        $data['account_id'] = $account_id;

        if ($old_data['slug'] == 'sys-discounts') {
            $range = array($request->min, $request->max);
            $data['data'] = implode(':', $range);
        }
        if ($old_data['slug'] == 'sys-documentationcharges') {
            $data['data'] = $request->data;
        }
        if ($old_data['slug'] == 'sys-birthdaypromotion') {
            $range = array($request->pre, $request->post);
            $data['data'] = implode(':', $range);
        }

        if(!isset($data['is_featured'])) {
            $data['is_featured'] = 0;
        } else if($data['is_featured'] == '') {
            $data['is_featured'] = 0;
        }

        $record = self::where([
            'id' => $id,
            'account_id' => $account_id
        ])->first();

        if(!$record) {
            return null;
        }

        $record->update($data);

        AuditTrails::EditEventLogger(self::$_table, 'edit', $data, self::$_fillable, $old_data, $id);

        return $record;
    }

    /**
     * Get active and sorted data only.
     *
     * @param $slug
     * @param $account_id
     *
     * @return (mixed)
     */
    static public function getBySlug($slug, $account_id)
    {
        return self::where(['slug' => $slug, 'account_id' => $account_id])->first();
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
