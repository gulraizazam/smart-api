<?php

namespace App\Models;

use App\Helpers\Filters;
use Auth;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;

class PaymentModes extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['account_id', 'name', 'type', 'active', 'payment_type', 'created_at', 'updated_at', 'sort_number'];

    protected static $_fillable = ['name', 'type', 'active', 'payment_type'];

    protected $table = 'payment_modes';

    protected static $_table = 'payment_modes';

    public function scopeActive($query, $active = 1)
    {
        $query->where('active', $active);
    }

    /**
     * Get the package advaances.
     */
    public function packageadvance()
    {

        return $this->hasMany('App\Models\PackageAdvances', 'payment_mode_id');
    }

    /*Relation for audit trail*/
    public function audit_field_before()
    {
        return $this->hasMany('App\Models\AuditTrailChanges', 'field_before');
    }

    public function audit_field_after()
    {
        return $this->hasMany('App\Models\AuditTrailChanges', 'field_after');
    }
    /*end*/

    /**
     * Get active and sorted data only.
     */
    public static function getActiveSorted($cityId = false)
    {
        if ($cityId && ! is_array($cityId)) {
            $cityId = [$cityId];
        }
        if ($cityId) {
            return self::whereIn('id', $cityId)->get()->pluck('name', 'id');
        } else {
            return self::get()->pluck('name', 'id');
        }
    }

    /**
     * Get active and sorted data only.
     */
    public static function getActiveOnly($cityId = false)
    {
        if ($cityId && ! is_array($cityId)) {
            $cityId = [$cityId];
        }
        $query = self::where(['active' => 1]);
        if ($cityId) {
            $query->whereIn('id', $cityId);
        }

        return $query->OrderBy('sort_number', 'asc')->get();
    }

    /**
     * Get Total Records
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getTotalRecords(Request $request, $account_id = false, $apply_filter = false)
    {
        $where = self::payment_modes_filters($request, $account_id, $apply_filter);
        if (count($where)) {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_paymentmodes')) {
                return self::where($where)->count();
            } else {
                return self::where($where)->where('active', 1)->count();
            }
        } else {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_paymentmodes')) {
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
        $where = self::payment_modes_filters($request, $account_id, $apply_filter);

        if (count($where)) {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_paymentmodes')) {
                return self::where($where)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('sort_number')->get();
            } else {
                return self::where($where)->where('active', 1)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('sort_number')->get();
            }
        } else {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_paymentmodes')) {
                return self::limit($iDisplayLength)->offset($iDisplayStart)->orderBy('sort_number')->get();
            } else {
                return self::where('active', 1)->limit($iDisplayLength)->offset($iDisplayStart)->orderBy('sort_number')->get();
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
    public static function payment_modes_filters($request, $account_id, $apply_filter)
    {
        $where = [];
        $filters = getFilters($request->all());
        if ($account_id) {
            $where[] = [
                'account_id',
                '=',
                $account_id,
            ];
            Filters::put(Auth::User()->id, 'payment_modes', 'account_id', $account_id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'payment_modes', 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, 'payment_modes', 'account_id')) {
                    $where[] = [
                        'account_id',
                        '=',
                        Filters::get(Auth::User()->id, 'payment_modes', 'account_id'),
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
            Filters::put(Auth::User()->id, 'payment_modes', 'name', $filters['name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'payment_modes', 'name');
            } else {
                if (Filters::get(Auth::User()->id, 'payment_modes', 'name')) {
                    $where[] = [
                        'name',
                        'like',
                        '%'.Filters::get(Auth::User()->id, 'payment_modes', 'name').'%',
                    ];
                }
            }
        }
        if (hasFilter($filters, 'payment_type')) {
            $where[] = [
                'payment_type',
                '=',
                $filters['payment_type'],
            ];
            Filters::put(Auth::User()->id, 'payment_modes', 'payment_type', $filters['payment_type']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'payment_modes', 'payment_type');
            } else {
                if (Filters::get(Auth::User()->id, 'payment_modes', 'payment_type')) {
                    $where[] = [
                        'payment_type',
                        '=',
                        Filters::get(Auth::User()->id, 'payment_modes', 'payment_type'),
                    ];
                }
            }
        }
        if (hasFilter($filters, 'type')) {
            $where[] = [
                'type',
                '=',
                $filters['type'],
            ];
            Filters::put(Auth::User()->id, 'payment_modes', 'type', $filters['type']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'payment_modes', 'type');
            } else {
                if (Filters::get(Auth::User()->id, 'payment_modes', 'type')) {
                    $where[] = [
                        'type',
                        '=',
                        Filters::get(Auth::User()->id, 'payment_modes', 'type'),
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
            Filters::put(Auth::user()->id, 'payment_modes', 'status', $filters['status']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'payment_modes', 'status');
            } else {
                if (Filters::get(Auth::user()->id, 'payment_modes', 'status') == 0 || Filters::get(Auth::user()->id, 'payment_modes', 'status') == 1) {
                    if (Filters::get(Auth::user()->id, 'payment_modes', 'status') != null) {
                        $where[] = [
                            'active',
                            '=',
                            Filters::get(Auth::user()->id, 'payment_modes', 'status'),
                        ];
                    }
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

        $record = self::create($data);

        $record->update(['sort_no' => $record->id]);

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

        return $record;
    }

    /**
     * Inactive Record
     *
     *
     * @return (mixed)
     */
    public static function inactiveRecord($id)
    {
        $payment_mode = PaymentModes::getData($id);
        if (! $payment_mode) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }
        $record = $payment_mode->update(['active' => 0]);
        AuditTrails::inactiveEventLogger(self::$_table, 'inactive', self::$_fillable, $id);

        return collect(['status' => true, 'message' => 'Record has been inactivated successfully.']);
    }

    /**
     * active Record
     *
     *
     * @return (mixed)
     */
    public static function activeRecord($id)
    {
        $payment_mode = PaymentModes::getData($id);
        if (! $payment_mode) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }
        $record = $payment_mode->update(['active' => 1]);
        AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

        return collect(['status' => true, 'message' => 'Record has been inactivated successfully.']);
    }

    /**
     * Delete Record
     *
     *
     * @return (mixed)
     */
    public static function deleteRecord($id)
    {
        $payment_mode = PaymentModes::getData($id);
        if (! $payment_mode) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }
        // Check if child records exists or not, If exist then disallow to delete it.
        if (PaymentModes::isChildExists($id, Auth::User()->account_id)) {
            return collect(['status' => false, 'message' => 'Child records exist, unable to delete resource']);
        }
        $record = $payment_mode->delete();
        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        return collect(['status' => true, 'message' => 'Record has been deleted successfully.']);
    }

    /**
     * Update Record
     *
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function updateRecord($id, $request, $account_id)
    {
        $old_data = (PaymentModes::find($id))->toArray();

        $data = $request->all();

        // Set Account ID
        $data['account_id'] = $account_id;

        if (! isset($data['payment_type'])) {
            $data['payment_type'] = 0;
        } elseif ($data['payment_type'] == '') {
            $data['payment_type'] = 0;
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
