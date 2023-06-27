<?php

namespace App\Models;

use App\Helpers\Filters;
use Auth;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;

class SMSTemplates extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['name', 'slug', 'account_id', 'content', 'active', 'created_at', 'updated_at', 'slug'];

    protected static $_fillable = ['name', 'slug', 'content', 'active', 'slug'];

    protected $table = 'sms_templates';

    protected static $_table = 'sms_templates';

    /**
     * Get Total Records
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getTotalRecords(Request $request, $account_id = false, $apply_filter = false)
    {
        $where = self::sms_templates_filters($request, $account_id, $apply_filter);

        if (count($where)) {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_smstemplates')) {
                return self::where($where)->count();
            } else {
                return self::where($where)->where('active', 1)->count();
            }
        } else {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_smstemplates')) {
                return self::count();
            } else {
                return self::where('active', 1)->count();
            }
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
        $where = self::sms_templates_filters($request, $account_id, $apply_filter);

        if (count($where)) {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_smstemplates')) {
                return self::where($where)->limit($iDisplayLength)->offset($iDisplayStart)->get();
            } else {
                return self::where($where)->where('active', 1)->limit($iDisplayLength)->offset($iDisplayStart)->get();
            }
        } else {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_smstemplates')) {
                return self::limit($iDisplayLength)->offset($iDisplayStart)->get();
            } else {
                return self::where('active', 1)->limit($iDisplayLength)->offset($iDisplayStart)->get();
            }
        }
    }

    /**
     * Get filters
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  (int)  $account_id Current Organization's ID
     * @param  (boolean)  $apply_filter
     * @return (mixed)
     */
    public static function sms_templates_filters($request, $account_id, $apply_filter)
    {

        $where = [];
        $filters = getFilters($request->all());
        if ($account_id) {
            $where[] = [
                'account_id',
                '=',
                $account_id,
            ];
            Filters::put(Auth::User()->id, 'sms_templates', 'account_id', $account_id);
        } else {

            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'sms_templates', 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, 'sms_templates', 'account_id')) {
                    $where[] = [
                        'account_id',
                        '=',
                        Filters::get(Auth::User()->id, 'sms_templates', 'account_id'),
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
            Filters::put(Auth::User()->id, 'sms_templates', 'name', $filters['name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'sms_templates', 'name');
            } else {
                if (Filters::get(Auth::User()->id, 'sms_templates', 'name')) {
                    $where[] = [
                        'name',
                        'like',
                        '%'.Filters::get(Auth::User()->id, 'sms_templates', 'name').'%',
                    ];
                }
            }
        }
        if (hasFilter($filters, 'slug')) {
            $where[] = [
                'slug',
                'like',
                '%'.$filters['slug'].'%',
            ];
            Filters::put(Auth::User()->id, 'sms_templates', 'slug', $filters['slug']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'sms_templates', 'slug');
            } else {
                if (Filters::get(Auth::User()->id, 'sms_templates', 'slug')) {
                    $where[] = [
                        'slug',
                        'like',
                        '%'.Filters::get(Auth::User()->id, 'sms_templates', 'slug').'%',
                    ];
                }
            }
        }

        if (hasFilter($filters, 'status')) {
            $where[] = [
                'active',
                '=',
                $filters['status'],
            ];
            Filters::put(Auth::user()->id, 'sms_templates', 'status', $filters['status']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'sms_templates', 'status');
            } else {
                if (Filters::get(Auth::user()->id, 'sms_templates', 'status') == 0 || Filters::get(Auth::user()->id, 'sms_templates', 'status') == 1) {
                    if (Filters::get(Auth::user()->id, 'sms_templates', 'status') != null) {
                        $where[] = [
                            'active',
                            '=',
                            Filters::get(Auth::user()->id, 'sms_templates', 'status'),
                        ];
                    }
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
        $old_data = (SMSTemplates::find($id))->toArray();

        $data = $request->all();

        // Set Account ID
        $data['account_id'] = $account_id;

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
        return self::where(['slug' => $slug, 'account_id' => $account_id, 'active' => 1])->first();
    }

    /**
     * Check if child records exist
     *
     * @param  (int)  $id
     * @return (boolean)
     */
    public static function isChildExists($id, $account_id)
    {
        if (
            Locations::where(['city_id' => $id, 'account_id' => $account_id])->count() ||
            Leads::where(['city_id' => $id, 'account_id' => $account_id])->count() ||
            Appointments::where(['city_id' => $id, 'account_id' => $account_id])->count()
        ) {
            return true;
        }

        return false;
    }
}
