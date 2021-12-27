<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use App\Helpers\Filters;
use Auth;

class SMSTemplates extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['name', 'slug', 'account_id', 'content', 'active', 'created_at', 'updated_at','slug'];

    protected static $_fillable = ['name', 'slug', 'content', 'active', 'slug'];

    protected $table = 'sms_templates';

    protected static $_table = 'sms_templates';

    /**
     * Get Total Records
     *
     * @param \Illuminate\Http\Request $request
     * @param (int) $account_id Current Organization's ID
     *
     * @return (mixed)
     */
    static public function getTotalRecords(Request $request, $account_id = false,$apply_filter = false)
    {
        $where = Self::sms_templates_filters($request, $account_id, $apply_filter);

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
        $where = Self::sms_templates_filters($request, $account_id, $apply_filter);

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
    static public function sms_templates_filters($request, $account_id, $apply_filter)
    {

        $where = array();
        if ($account_id) {
            $where[] = array(
                'account_id',
                '=',
                $account_id
            );
            Filters::put(Auth::User()->id, 'sms_templates', 'account_id', $account_id);
        }  else {

            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'sms_templates', 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, 'sms_templates', 'account_id')) {
                    $where[] = array(
                        'account_id',
                        '=',
                        Filters::get(Auth::User()->id, 'sms_templates', 'account_id')
                    );
                }
            }
        }
        if ($request->get('lead_status_name')) {
            $where[] = array(
                'name',
                'like',
                '%' . $request->get('lead_status_name') . '%'
            );
            Filters::put(Auth::User()->id, 'sms_templates', 'lead_status_name', $request->get('lead_status_name'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'sms_templates', 'lead_status_name');
            } else {
                if (Filters::get(Auth::User()->id, 'sms_templates', 'lead_status_name')) {
                    $where[] = array(
                        'name',
                        'like',
                        '%' . Filters::get(Auth::User()->id, 'sms_templates', 'lead_status_name') . '%'
                    );
                }
            }
        }
        if ($request->get('lead_status_content')) {
            $where[] = array(
                'content',
                'like',
                '%' . $request->get('lead_status_content') . '%'
            );
            Filters::put(Auth::User()->id, 'sms_templates', 'lead_status_content', $request->get('lead_status_content'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'sms_templates', 'lead_status_content');
            } else {
                if (Filters::get(Auth::User()->id, 'sms_templates', 'lead_status_content')) {
                    $where[] = array(
                        'content',
                        'like',
                        '%' . Filters::get(Auth::User()->id, 'sms_templates', 'lead_status_content') . '%'
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
            Filters::put(Auth::user()->id, 'sms_templates', 'status', $request->get('status'));
        } else {
            if ( $apply_filter ){
                Filters::forget( Auth::user()->id, 'sms_templates', 'status');
            } else {
                if ( Filters::get(Auth::user()->id, 'sms_templates', 'status') == 0 || Filters::get(Auth::user()->id, 'sms_templates', 'status') == 1){
                    if ( Filters::get(Auth::user()->id, 'sms_templates', 'status') != null ){
                        $where[] = array(
                            'active',
                            '=',
                            Filters::get( Auth::user()->id, 'sms_templates', 'status')
                        );
                    }
                }
            }
        }
        return $where ;
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
        $old_data = (SMSTemplates::find($id))->toArray();

        $data = $request->all();

        // Set Account ID
        $data['account_id'] = $account_id;

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
        return self::where(['slug' => $slug, 'account_id' => $account_id, 'active' => 1])->first();
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
        if(
            Locations::where(['city_id' => $id, 'account_id' => $account_id])->count() ||
            Leads::where(['city_id' => $id, 'account_id' => $account_id])->count() ||
            Appointments::where(['city_id' => $id, 'account_id' => $account_id])->count()
        ) {
            return true;
        }

        return false;
    }
}
