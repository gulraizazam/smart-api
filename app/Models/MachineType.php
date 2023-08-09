<?php

namespace App\Models;

use Auth;
use DateTime;
use App\Helpers\Filters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\SoftDeletes;

class MachineType extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['name', 'active', 'account_id', 'created_at', 'updated_at', 'deleted_at'];

    protected static $_fillable = ['name', 'active', 'account_id', 'created_at', 'updated_at', 'deleted_at'];

    protected $table = 'machine_types';

    protected static $_table = 'machine_types';

    protected $casts = [
        'created_at' => 'datetime:F d,Y h:i A',
    ];

    /*
     * Get the services against location id
     */
    public function machinetype_has_services()
    {
        return $this->hasMany('App\Models\MachineTypeHasServices', 'machine_type_id')->withoutGlobalScope(SoftDeletingScope::class);
    }

    /**
     * Services of Machine Types Relation.
     *
     * @return mixed
     */
    public function services()
    {
        return $this->belongsToMany('App\Models\Services', 'machine_type_has_services', 'machine_type_id', 'service_id')->withTrashed();
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
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getTotalRecords(Request $request, $account_id = false, $apply_filter = false)
    {
        $where = self::machinetype_filters($request, $account_id, $apply_filter);

        if (count($where)) {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_machine_types')) {
                return count(DB::table('machine_types')
                    ->leftJoin('machine_type_has_services', 'machine_types.id', '=', 'machine_type_has_services.machine_type_id')
                    ->where($where)
                    ->whereNull('deleted_at')
                    ->groupBy('machine_type_has_services.machine_type_id')
                    ->get());
            } else {
                return count(DB::table('machine_types')
                    ->leftJoin('machine_type_has_services', 'machine_types.id', '=', 'machine_type_has_services.machine_type_id')
                    ->where($where)
                    ->where('active', 1)
                    ->whereNull('deleted_at')
                    ->groupBy('machine_type_has_services.machine_type_id')
                    ->get());
            }
        } else {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_machine_types')) {
                return count(DB::table('machine_types')
                    ->leftJoin('machine_type_has_services', 'machine_types.id', '=', 'machine_type_has_services.machine_type_id')
                    ->whereNull('deleted_at')
                    ->groupBy('machine_type_has_services.machine_type_id')
                    ->get());
            } else {
                return count(DB::table('machine_types')
                    ->leftJoin('machine_type_has_services', 'machine_types.id', '=', 'machine_type_has_services.machine_type_id')
                    ->whereNull('deleted_at')
                    ->where('active', 1)
                    ->groupBy('machine_type_has_services.machine_type_id')
                    ->get());
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
        // dd($request->all());
        $orderBy = 'created_at';
        $order = 'desc';
        if (\Illuminate\Support\Facades\Gate::allows('view_inactive_machine_types')) {
            return self::with('services')->Filters($request, $account_id, $apply_filter)
                ->limit($iDisplayLength)
                ->offset($iDisplayStart)
                ->orderby($orderBy, $order)
                ->get();
        } else {
            return self::with('services')->where('machine_types.active', 1)->Filters($request, $account_id, $apply_filter)
                ->limit($iDisplayLength)
                ->offset($iDisplayStart)
                ->orderby($orderBy, $order)
                ->get();
        }
    }

    public function scopeFilters($query, $request, $account_id, $apply_filter)
    {
        $filters = getFilters($request->all());
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
            $query = $query->where('account_id', $account_id);
            Filters::put(Auth::User()->id, 'machinetypes', 'account_id', $account_id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'machinetypes', 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, 'machinetypes', 'account_id')) {
                    $query = $query->where('account_id', Filters::get(Auth::User()->id, 'machinetypes', 'account_id'));
                }
            }
        }

        if (hasFilter($filters, 'name')) {
            $query = $query->where('name', 'like', '%' . $filters['name'] . '%');
            Filters::put(Auth::User()->id, 'machinetypes', 'name', $filters['name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'machinetypes', 'name');
            } else {
                if (Filters::get(Auth::User()->id, 'machinetypes', 'name')) {
                    $query = $query->where('name', 'like', '%' . Filters::get(Auth::User()->id, 'machinetypes', 'name') . '%');
                }
            }
        }
        if (hasFilter($filters, 'service')) {
            $where[] = [
                'machine_type_has_services.service_id',
                '=',
                $filters['service'],
            ];
            $query = $query->whereHas('services', fn ($q) => $q->where('id', $filters['service']));
            Filters::put(Auth::User()->id, 'machinetypes', 'service', $filters['service']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'machinetypes', 'service');
            } else {
                if (Filters::get(Auth::User()->id, 'machinetypes', 'service')) {
                    $query = $query->whereHas('services', fn ($q) => $q->where('id', Filters::get(Auth::User()->id, 'machinetypes', 'service')));
                }
            }
        }

        if (hasFilter($filters, 'created_at')) {
            $query = $query->where('created_at', '>=', $start_date_time);
            $query = $query->where('created_at', '<=', $end_date_time);
            Filters::put(Auth::User()->id, 'machinetypes', 'created_at', $filters['created_at']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'machinetypes', 'created_at');
            } else {
                if (Filters::get(Auth::User()->id, 'machinetypes', 'created_at')) {
                    $query = $query->where('created_at', '>=', Filters::get(Auth::User()->id, 'machinetypes', 'created_at'));
                }
            }
        }

        if (hasFilter($filters, 'status')) {
            $query = $query->where('active', $filters['status']);
            Filters::put(Auth::user()->id, 'machinetypes', 'status', $filters['status']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'machinetypes', 'status');
            } else {
                if (Filters::get(Auth::user()->id, 'machinetypes', 'status') == 0 || Filters::get(Auth::user()->id, 'machinetypes', 'status') == 1) {
                    if (Filters::get(Auth::user()->id, 'machinetypes', 'status') != null) {
                        $query = $query->where('active', Filters::get(Auth::user()->id, 'machinetypes', 'status'));
                    }
                }
            }
        }
    }

    /*
     *  Filters for machine type
     */
    public static function machinetype_filters($request, $account_id, $apply_filter)
    {
        $where = [];
        $filters = getFilters($request->all());
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
                'machine_types.account_id',
                '=',
                $account_id,
            ];
            Filters::put(Auth::User()->id, 'machinetypes', 'account_id', $account_id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'machinetypes', 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, 'machinetypes', 'account_id')) {
                    $where[] = [
                        'machine_types.account_id',
                        '=',
                        Filters::get(Auth::User()->id, 'machinetypes', 'account_id'),
                    ];
                }
            }
        }
        if (count($filters) && isset($filters['name'])) {
            $where[] = [
                'machine_types.name',
                'like',
                '%' . $filters['name'] . '%',
            ];
            Filters::put(Auth::User()->id, 'machinetypes', 'name', $filters['name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'machinetypes', 'name');
            } else {
                if (Filters::get(Auth::User()->id, 'machinetypes', 'name')) {
                    $where[] = [
                        'machine_types.name',
                        'like',
                        '%' . Filters::get(Auth::User()->id, 'machinetypes', 'name') . '%',
                    ];
                }
            }
        }
        if (count($filters) && isset($filters['service'])) {
            $where[] = [
                'machine_type_has_services.service_id',
                '=',
                $filters['service'],
            ];
            Filters::put(Auth::User()->id, 'machinetypes', 'service', $filters['service']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'machinetypes', 'service');
            } else {
                if (Filters::get(Auth::User()->id, 'machinetypes', 'service')) {
                    $where[] = [
                        'machine_type_has_services.service_id',
                        '=',
                        Filters::get(Auth::User()->id, 'machinetypes', 'service'),
                    ];
                }
            }
        }

        if (count($filters) && isset($filters['created_at'])) {
            $where[] = ['machine_types.created_at', '>=', $start_date_time];
            $where[] = ['machine_types.created_at', '<=', $end_date_time];
            Filters::put(Auth::User()->id, 'machinetypes', 'created_at', $filters['created_at']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'machinetypes', 'created_at');
            } else {
                if (Filters::get(Auth::User()->id, 'machinetypes', 'created_at')) {
                    $where[] = [
                        'machine_types.created_at',
                        '>=',
                        Filters::get(Auth::User()->id, 'machinetypes', 'created_at'),
                    ];
                }
            }
        }

        if (count($filters) && isset($filters['status'])) {
            $where[] = [
                'machine_types.active',
                '=',
                $filters['status'],
            ];
            Filters::put(Auth::user()->id, 'machinetypes', 'status', $filters['status']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'machinetypes', 'status');
            } else {
                if (Filters::get(Auth::user()->id, 'machinetypes', 'status') == 0 || Filters::get(Auth::user()->id, 'machinetypes', 'status') == 1) {
                    if (Filters::get(Auth::user()->id, 'machinetypes', 'status') != null) {
                        $where[] = [
                            'machine_types.active',
                            '=',
                            Filters::get(Auth::user()->id, 'machinetypes', 'status'),
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

        $data['account_id'] = $account_id;

        $record = self::create($data);

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

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
        $old_data = (MachineType::find($id))->toArray();

        $data = $request->all();

        $data['account_id'] = $account_id;

        $record = self::where([
            'id' => $id,
            'account_id' => $account_id,
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
     *
     * @param id
     * @return (mixed)
     */
    public static function inactiveRecord($id)
    {
        $machinetype = MachineType::getData($id);

        if (!$machinetype) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }
        $record = $machinetype->update(['active' => 0]);
        AuditTrails::inactiveEventLogger(self::$_table, 'inactive', self::$_fillable, $id);

        return collect(['status' => true, 'message' => 'Record has been inactivated successfully.']);
    }

    /**
     * Active Record
     *
     * @param id
     * @return (mixed)
     */
    public static function activeRecord($id)
    {
        $machinetype = MachineType::getData($id);
        if (!$machinetype) {
            return collect(['status' => true, 'message' => 'Resource not found.']);
        }
        $record = $machinetype->update(['active' => 1]);
        AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

        return collect(['status' => true, 'message' => 'Record has been activated successfully.']);
    }

    /**
     * delete Record
     *
     * @param id
     * @return (mixed)
     */
    public static function deleteRecord($id)
    {
        $machinetype = MachineType::getData($id);
        if (!$machinetype) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }
        // Check if child records exists or not, If exist then disallow to delete it.
        if (MachineType::isChildExists($id, Auth::User()->account_id)) {
            return collect(['status' => false, 'message' => 'Child records exist, unable to delete resource']);
        }
        $record = $machinetype->delete();
        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        return collect(['status' => true, 'message' => 'Record has been deleted successfully.']);
    }

    /**
     * Check if child records exist
     *
     * @param  (int)  $id
     * @return (boolean)
     */
    public static function isChildExists($id, $account_id)
    {
        if (Resources::where(['machine_type_id' => $id])->count()) {
            return true;
        }

        return false;
    }
}
