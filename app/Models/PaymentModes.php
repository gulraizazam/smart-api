<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use App\Models\AuditTrails;
use Auth;
use App\Helpers\Filters;

class PaymentModes extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['account_id', 'name','type','active', 'payment_type', 'created_at', 'updated_at','sort_number'];

    protected static $_fillable = ['name','type', 'active', 'payment_type'];

    protected $table = 'payment_modes';

    protected static $_table = 'payment_modes';

    /**
     * Get the package advaances.
     */
    public function packageadvance(){

        return $this->hasMany('App\Models\PackageAdvances', 'payment_mode_id');
    }

    /*Relation for audit trail*/
    public function audit_field_before()
    {
        return $this->hasMany('App\Models\AuditTrailChanges','field_before');
    }
    public function audit_field_after()
    {
        return $this->hasMany('App\Models\AuditTrailChanges','field_after');
    }
    /*end*/

    /**
     * Get active and sorted data only.
     */
    static public function getActiveSorted($cityId = false)
    {
        if($cityId && !is_array($cityId)) {
            $cityId = array($cityId);
        }
        if($cityId) {
            return self::whereIn('id',$cityId)->get()->pluck('name','id');
        } else {
            return self::get()->pluck('name','id');
        }
    }

    /**
     * Get active and sorted data only.
     */
    static public function getActiveOnly($cityId = false)
    {
        if($cityId && !is_array($cityId)) {
            $cityId = array($cityId);
        }
        $query = self::where(['active' => 1]);
        if($cityId) {
            $query->whereIn('id',$cityId);
        }
        return $query->OrderBy('sort_number','asc')->get();
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
        $where = Self::payment_modes_filters($request, $account_id, $apply_filter);
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
        $where = Self::payment_modes_filters($request, $account_id, $apply_filter);

        if(count($where)) {
            return self::where($where)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('sort_number')->get();
        } else {
            return self::limit($iDisplayLength)->offset($iDisplayStart)->orderBy('sort_number')->get();
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
    static public function payment_modes_filters($request, $account_id, $apply_filter)
    {
        $where = array();

        if ($account_id) {
            $where[] = array(
                'account_id',
                '=',
                $account_id
            );
            Filters::put(Auth::User()->id, 'payment_modes', 'account_id', $account_id);
        }  else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'payment_modes', 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, 'payment_modes', 'account_id')) {
                    $where[] = array(
                        'account_id',
                        '=',
                        Filters::get(Auth::User()->id, 'payment_modes', 'account_id')
                    );
                }
            }
        }
        if ($request->get('name')) {
            $where[] = array(
                'name',
                'like',
                '%' . $request->get('name') . '%'
            );
            Filters::put(Auth::User()->id, 'payment_modes', 'name', $request->get('name'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'payment_modes', 'name');
            } else {
                if (Filters::get(Auth::User()->id, 'payment_modes', 'name')) {
                    $where[] = array(
                        'name',
                        'like',
                        '%' . Filters::get(Auth::User()->id, 'payment_modes', 'name') . '%'
                    );
                }
            }
        }
        if ($request->get('payment_type') != '') {
            $where[] = array(
                'payment_type',
                '=',
                $request->get('payment_type')
            );
            Filters::put(Auth::User()->id, 'payment_modes', 'payment_type', $request->get('payment_type'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'payment_modes', 'payment_type');
            } else {
                if (Filters::get(Auth::User()->id, 'payment_modes', 'payment_type')) {
                    $where[] = array(
                        'payment_type',
                        '=',
                        Filters::get(Auth::User()->id, 'payment_modes', 'payment_type')
                    );
                }
            }
        }
        if ($request->get('type') != '') {
            $where[] = array(
                'type',
                '=',
                $request->get('type')
            );
            Filters::put(Auth::User()->id, 'payment_modes', 'type', $request->get('type'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'payment_modes', 'type');
            } else {
                if (Filters::get(Auth::User()->id, 'payment_modes', 'type')) {
                    $where[] = array(
                        'type',
                        '=',
                        Filters::get(Auth::User()->id, 'payment_modes', 'type')
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
            Filters::put(Auth::user()->id, 'payment_modes', 'status', $request->get('status'));
        } else {
            if ( $apply_filter ){
                Filters::forget( Auth::user()->id, 'payment_modes', 'status');
            } else {
                if ( Filters::get(Auth::user()->id, 'payment_modes', 'status') == 0 || Filters::get(Auth::user()->id, 'payment_modes', 'status') == 1){
                    if ( Filters::get(Auth::user()->id, 'payment_modes', 'status') != null ){
                        $where[] = array(
                            'active',
                            '=',
                            Filters::get( Auth::user()->id, 'payment_modes', 'status')
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

        $payment_mode = PaymentModes::getData($id);

        if (!$payment_mode) {
            flash('Resource not found.')->error()->important();
            return redirect()->route('admin.payment_modes.index');
        }

        $record = $payment_mode->update(['active' => 0]);

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

        $payment_mode = PaymentModes::getData($id);

        if (!$payment_mode) {
            flash('Resource not found.')->error()->important();
            return redirect()->route('admin.payment_modes.index');
        }

        $record = $payment_mode->update(['active' => 1]);

        flash('Record has been inactivated successfully.')->success()->important();

        AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

        return $record;
    }

    /**
     * Delete Record
     *
     * @param $id
     *
     * @return (mixed)
     */
    static public function deleteRecord($id)
    {

        $payment_mode = PaymentModes::getData($id);

        if (!$payment_mode) {
            flash('Resource not found.')->error()->important();
            return redirect()->route('admin.payment_modes.index');
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (PaymentModes::isChildExists($id, Auth::User()->account_id)) {
            flash('Child records exist, unable to delete resource')->error()->important();
            return redirect()->route('admin.payment_modes.index');
        }

        $record = $payment_mode->delete();

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
        $old_data = (PaymentModes::find($id))->toArray();

        $data = $request->all();

        // Set Account ID
        $data['account_id'] = $account_id;

        if(!isset($data['payment_type'])) {
            $data['payment_type'] = 0;
        } else if($data['payment_type'] == '') {
            $data['payment_type'] = 0;
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
