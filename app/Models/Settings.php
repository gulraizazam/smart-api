<?php

namespace App\Models;

use App\Helpers\Filters;
use Auth;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;

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
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getTotalRecords(Request $request, $account_id, $apply_filter)
    {
        $where = self::settings_filters($request, $account_id, $apply_filter);
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
    public static function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false, $apply_filter = false)
    {
        $where = self::settings_filters($request, $account_id, $apply_filter);
        [$orderBy, $order] = getSortBy($request);

        return self::when(count($where), fn ($q) => $q->where($where))
            ->limit($iDisplayLength)
            ->offset($iDisplayStart)
            ->orderBy($orderBy, $order)
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
    public static function settings_filters($request, $account_id, $apply_filter)
    {
        $where = [];
        $filters = getFilters($request->all());
        if ($account_id) {
            $where[] = [
                'account_id',
                '=',
                $account_id,
            ];
            Filters::put(Auth::User()->id, 'settings', 'account_id', $account_id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'settings', 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, 'settings', 'account_id')) {
                    $where[] = [
                        'account_id',
                        '=',
                        Filters::get(Auth::User()->id, 'settings', 'account_id'),
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
            Filters::put(Auth::User()->id, 'settings', 'setting_name', $filters['name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'settings', 'setting_name');
            } else {
                if (Filters::get(Auth::User()->id, 'settings', 'setting_name')) {
                    $where[] = [
                        'name',
                        'like',
                        '%'.Filters::get(Auth::User()->id, 'settings', 'setting_name').'%',
                    ];
                }
            }
        }
        if (hasFilter($filters, 'data')) {
            $where[] = [
                'data',
                'like',
                '%'.$filters['data'].'%',
            ];
            Filters::put(Auth::User()->id, 'settings', 'setting_data', $filters['data']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'settings', 'setting_data');
            } else {
                if (Filters::get(Auth::User()->id, 'settings', 'setting_data')) {
                    $where[] = [
                        'data',
                        'like',
                        '%'.Filters::get(Auth::User()->id, 'settings', 'setting_data').'%',
                    ];
                }
            }
        }

        return $where;
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
        $data['slug'] = 'custom';

        $record = self::create($data);
        $record->update(['sort_no' => $record->id]);

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
        $old_data = (Settings::find($id))->toArray();

        $data = $request->all();
        // Set Account ID
        $data['account_id'] = $account_id;

        if ($old_data['slug'] == 'sys-discounts') {
            $range = [$request->min, $request->max];
            $data['data'] = implode(':', $range);
        }
        if ($old_data['slug'] == 'sys-documentationcharges') {
            $data['data'] = $request->data;
        }
        if ($old_data['slug'] == 'sys-birthdaypromotion') {
            $range = [$request->pre, $request->post];
            $data['data'] = implode(':', $range);
        }

        if (! isset($data['is_featured'])) {
            $data['is_featured'] = 0;
        } elseif ($data['is_featured'] == '') {
            $data['is_featured'] = 0;
        }

        $record = self::where([
            'id' => $id,
            'account_id' => $account_id,
        ])->first();

        if (! $record) {
            return null;
        }
        if (isset($data['min'])) {
            $data['min'] = ltrim($data['min'], '0');
        }
        if (isset($data['max'])) {
            $data['max'] = ltrim($data['max'], '0');
        }

        $record->update($data);

        AuditTrails::EditEventLogger(self::$_table, 'edit', $data, self::$_fillable, $old_data, $id);

        return $record;
    }

    /**
     * Get active and sorted data only.
     *
     *
     * @return (mixed)
     */
    public static function getBySlug($slug, $account_id)
    {
        return self::where(['slug' => $slug, 'account_id' => $account_id])->first();
    }

    /**
     * Check if child records exist
     *
     * @param  (int)  $id
     * @return (boolean)
     */
    public static function isChildExists($id, $account_id)
    {
        return false;
    }
}
