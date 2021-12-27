<?php

namespace App\Models;

use App\Helpers\ACL;
use App\Helpers\Filters;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Auth;

class MachineType extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['name', 'active', 'account_id', 'created_at', 'updated_at', 'deleted_at'];

    protected static $_fillable = ['name', 'active', 'account_id', 'created_at', 'updated_at', 'deleted_at'];

    protected $table = 'machine_types';

    protected static $_table = 'machine_types';

    /*
     * Get the services against location id
     */
    public function machinetype_has_services()
    {
        return $this->hasMany('App\Models\MachineTypeHasServices', 'machine_type_id')->withoutGlobalScope(SoftDeletingScope::class);
    }

    /**
     * Get the machine type for Resource.
     */
    public function Resource()
    {
        return $this->hasMany('App\Models\Resources', 'machine_type_id');
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
        $where = Self::machinetype_filters($request, $account_id, $apply_filter);

        if (count($where)) {
            return count(DB::table('machine_types')
                ->leftJoin('machine_type_has_services', 'machine_types.id', '=', 'machine_type_has_services.machine_type_id')
                ->where($where)
                ->whereNull('deleted_at')
                ->groupBy('machine_type_has_services.machine_type_id')
                ->get());
        } else {
            return count(DB::table('machine_types')
                ->leftJoin('machine_type_has_services', 'machine_types.id', '=', 'machine_type_has_services.machine_type_id')
                ->whereNull('deleted_at')
                ->groupBy('machine_type_has_services.machine_type_id')
                ->get());
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
        $where = Self::machinetype_filters($request, $account_id, $apply_filter);

        $orderBy = 'created_at';
        $order = 'desc';
        if ($request->get('order')) {
            $orderColumn = $request->get('order')[0]['column'];
            $orderBy = $request->get('columns')[$orderColumn]['data'];
            if ($orderBy == 'created_at') {
                $orderBy = 'machine_types.created_at';
            }
            $order = $request->get('order')[0]['dir'];

            Filters::put(Auth::User()->id, 'machinetypes', 'order_by', $orderBy);
            Filters::put(Auth::User()->id, 'machinetypes', 'order', $order);
        } else {
            if (
                Filters::get(Auth::User()->id, 'machinetypes', 'order_by')
                && Filters::get(Auth::User()->id, 'machinetypes', 'order')
            ) {
                $orderBy = Filters::get(Auth::User()->id, 'machinetypes', 'order_by');
                $order = Filters::get(Auth::User()->id, 'machinetypes', 'order');

                if ($orderBy == 'created_at') {
                    $orderBy = 'machine_types.created_at';
                }
            } else {
                $orderBy = 'created_at';
                $order = 'desc';
                if ($orderBy == 'created_at') {
                    $orderBy = 'machine_types.created_at';
                }

                Filters::put(Auth::User()->id, 'machinetypes', 'order_by', $orderBy);
                Filters::put(Auth::User()->id, 'machinetypes', 'order', $order);
            }
        }
        if (count($where)) {
            return DB::table('machine_types')
                ->leftJoin('machine_type_has_services', 'machine_types.id', '=', 'machine_type_has_services.machine_type_id')
                ->where($where)
                ->whereNull('deleted_at')
                ->groupBy('machine_type_has_services.machine_type_id')
                ->limit($iDisplayLength)
                ->offset($iDisplayStart)
                ->orderby($orderBy, $order)
                ->get();
        } else {
            return DB::table('machine_types')
                ->leftJoin('machine_type_has_services', 'machine_types.id', '=', 'machine_type_has_services.machine_type_id')
                ->whereNull('deleted_at')
                ->groupBy('machine_type_has_services.machine_type_id')
                ->limit($iDisplayLength)
                ->offset($iDisplayStart)
                ->orderby($orderBy, $order)
                ->get();
        }
    }

    /*
     *  Filters for machine type
     */
    static public function machinetype_filters($request, $account_id, $apply_filter)
    {
        $where = array();
        if ($account_id) {
            $where[] = array(
                'machine_types.account_id',
                '=',
                $account_id
            );
            Filters::put(Auth::User()->id, 'machinetypes', 'account_id', $account_id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'machinetypes', 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, 'machinetypes', 'account_id')) {
                    $where[] = array(
                        'machine_types.account_id',
                        '=',
                        Filters::get(Auth::User()->id, 'machinetypes', 'account_id')
                    );
                }
            }
        }
        if ($request->get('name')) {
            $where[] = array(
                'machine_types.name',
                'like',
                '%' . $request->get('name') . '%'
            );
            Filters::put(Auth::User()->id, 'machinetypes', 'name', $request->get('name'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'machinetypes', 'name');
            } else {
                if (Filters::get(Auth::User()->id, 'machinetypes', 'name')) {
                    $where[] = array(
                        'machine_types.name',
                        'like',
                        '%' . Filters::get(Auth::User()->id, 'machinetypes', 'name') . '%'
                    );
                }
            }
        }
        if ($request->get('service') != '') {
            $where[] = array(
                'machine_type_has_services.service_id',
                '=',
                $request->get('service')
            );
            Filters::put(Auth::User()->id, 'machinetypes', 'service', $request->get('service'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'machinetypes', 'service');
            } else {
                if (Filters::get(Auth::User()->id, 'machinetypes', 'service')) {
                    $where[] = array(
                        'machine_type_has_services.service_id',
                        '=',
                        Filters::get(Auth::User()->id, 'machinetypes', 'service')
                    );
                }
            }
        }

        if ($request->get('created_from') != '') {
            $where[] = array(
                'machine_types.created_at',
                '>=',
                $request->get('created_from') . ' 00:00:00'
            );
            Filters::put(Auth::User()->id, 'machinetypes', 'created_from', $request->get('created_from') . ' 00:00:00');
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'machinetypes', 'created_from');
            } else {
                if (Filters::get(Auth::User()->id, 'machinetypes', 'created_from')) {
                    $where[] = array(
                        'machine_types.created_at',
                        '>=',
                        Filters::get(Auth::User()->id, 'machinetypes', 'created_from') . ' 00:00:00'
                    );
                }
            }
        }

        if ($request->get('created_to') != '') {
            $where[] = array(
                'machine_types.created_at',
                '<=',
                $request->get('created_to') . ' 23:59:59'
            );
            Filters::put(Auth::User()->id, 'machinetypes', 'created_to', $request->get('created_to') . ' 23:59:59');
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'machinetypes', 'created_to');
            } else {
                if (Filters::get(Auth::User()->id, 'machinetypes', 'created_to')) {
                    $where[] = array(
                        'machine_types.created_at',
                        '<=',
                        Filters::get(Auth::User()->id, 'machinetypes', 'created_to') . ' 23:59:59'
                    );
                }
            }
        }

        if ($request->get('status') && $request->get('status') != null || $request->get('status') == 0 && $request->get('status') != null) {
            $where[] = array(
                'machine_types.active',
                '=',
                $request->get('status')
            );
            Filters::put(Auth::user()->id, 'machinetypes', 'status', $request->get('status'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'machinetypes', 'status');
            } else {
                if (Filters::get(Auth::user()->id, 'machinetypes', 'status') == 0 || Filters::get(Auth::user()->id, 'machinetypes', 'status') == 1) {
                    if (Filters::get(Auth::user()->id, 'machinetypes', 'status') != null) {
                        $where[] = array(
                            'machine_types.active',
                            '=',
                            Filters::get(Auth::user()->id, 'machinetypes', 'status')
                        );
                    }
                }
            }
        }
        return $where;
    }

    /**
     * Create Record
     * @param \Illuminate\Http\Request $request
     * @return (mixed)
     */
    static public function createRecord($request, $account_id)
    {
        $data = $request->all();

        $data['account_id'] = $account_id;

        $record = self::create($data);

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

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
        $old_data = (MachineType::find($id))->toArray();

        $data = $request->all();

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
     * Inactive Record
     * @param id
     * @return (mixed)
     */
    static public function inactiveRecord($id)
    {
        $machinetype = MachineType::getData($id);

        if (!$machinetype) {
            flash('Resource not found.')->error()->important();
            return redirect()->route('admin.machinetypes.index');
        }

        $record = $machinetype->update(['active' => 0]);

        flash('Record has been inactivated successfully.')->success()->important();

        AuditTrails::inactiveEventLogger(self::$_table, 'inactive', self::$_fillable, $id);

        return $record;
    }

    /**
     * Active Record
     * @param id
     * @return (mixed)
     */
    static public function activeRecord($id)
    {
        $machinetype = MachineType::getData($id);

        if (!$machinetype) {
            flash('Resource not found.')->error()->important();
            return redirect()->route('admin.machinetypes.index');
        }

        $record = $machinetype->update(['active' => 1]);

        flash('Record has been activated successfully.')->success()->important();

        AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

        return $record;
    }

    /**
     * delete Record
     *
     * @param id
     *
     * @return (mixed)
     */
    static public function deleteRecord($id)
    {
        $machinetype = MachineType::getData($id);

        if (!$machinetype) {
            flash('Resource not found.')->error()->important();
            return redirect()->route('admin.machinetypes.index');
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (MachineType::isChildExists($id, Auth::User()->account_id)) {
            flash('Child records exist, unable to delete resource')->error()->important();
            return redirect()->route('admin.machinetypes.index');
        }

        $record = $machinetype->delete();

        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        flash('Record has been deleted successfully.')->success()->important();

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
        if (Resources::where(['machine_type_id' => $id])->count()) {
            return true;
        }
        return false;
    }
}
