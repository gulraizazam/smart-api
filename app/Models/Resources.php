<?php

namespace App\Models;

use Auth;
use DateTime;
use Carbon\Carbon;
use App\Helpers\ACL;
use App\Helpers\Filters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resources extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['name', 'active', 'account_id', 'resource_type_id', 'external_id', 'machine_type_id', 'created_at', 'updated_at', 'location_id'];

    protected static $_fillable = ['name', 'account_id', 'resource_type_id', 'external_id', 'machine_type_id', 'created_at', 'updated_at', 'location_id'];

    protected $table = 'resources';

    protected static $_table = 'resources';

    protected $casts = [
        'created_at' => 'datetime:F d,Y h:i A',
    ];

    /**
     * Get minTime of resource rota days with respect to doctor and machine
     *
     * @return mixed
     */
    public static function getMinTimeWithDrAndMachine($location_id, $doctor_id, $machine_id, $start, $end)
    {
        return self::join('resource_has_rota', 'resources.id', '=', 'resource_has_rota.resource_id')
            ->join('resource_has_rota_days', 'resource_has_rota.id', '=', 'resource_has_rota_days.resource_has_rota_id')
            ->where('resource_has_rota.location_id', '=', $location_id)
            ->where('resources.external_id', '=', $doctor_id)
            ->orWhere('resource_has_rota.resource_id', '=', $machine_id)
            ->where('resource_has_rota.resource_type_id', '=', config('constants.resource_room_type_id'))
            ->where('resource_has_rota_days.date', '>', $start)
            ->where('resource_has_rota_days.date', '<', $end)
            ->min(DB::raw('time(resource_has_rota_days.start_timestamp)'));
    }

    /**
     * get MinRota time for consulting appointment
     *
     * @return mixed
     */
    public static function getMinTimeWithDr($location_id, $doctor_id, $start, $end)
    {
        return self::join('resource_has_rota', 'resources.id', '=', 'resource_has_rota.resource_id')
            ->join('resource_has_rota_days', 'resource_has_rota.id', '=', 'resource_has_rota_days.resource_has_rota_id')
            ->where('resource_has_rota.location_id', '=', $location_id)
            ->where('resources.external_id', '=', $doctor_id)
            ->where('resource_has_rota_days.date', '>', $start)
            ->where('resource_has_rota_days.date', '<', $end)
            ->min(DB::raw('time(resource_has_rota_days.start_timestamp)'));
    }

    /*
     * Get the Location against service:location_id.
     */
    public function location()
    {
        return $this->belongsTo('App\Models\Locations', 'location_id')->withTrashed();
    }

    /*
    * Get the Location against service:location_id.
    */
    public function resourcetype()
    {
        return $this->belongsTo('App\Models\ResourceTypes', 'resource_type_id')->withTrashed();
    }

    /**
     * Get the Machine Type.
     */
    public function MachineType()
    {
        return $this->belongsTo('App\Models\MachineType')->withTrashed();
    }

    /*Get the services against location id
     *
     */
    public function resource_has_services()
    {
        return $this->hasMany('App\Models\ResourceHasServices', 'resource_id')->withoutGlobalScope(SoftDeletingScope::class);
    }

    public static function getResourceType($slug)
    {
        return ResourceTypes::where('slug', 'like', $slug)->value('id');
    }

    public static function getResourceWithRotas($resource_id)
    {
        $where = [];
        $where[] = ['id', '=', $resource_id];
        $where[] = ['account_id', '=', Auth::User()->account_id];

        return self::where($where)->with('doctor_rotas')->get();
    }

    /**
     * @param  Request  $request (doctor_id, started_time, end_time)
     * @return bool
     */
    public static function checkDoctorAvailbility(Request $request)
    {
        if (
            $request->get('doctor_id')
            && $request->get('start')
            && $request->get('end')
        ) {

            $data['started_time'] = \Carbon\Carbon::parse($request->get('start'))->format('Y-m-d H:i:s');
            $data['ended_time'] = \Carbon\Carbon::parse($request->get('end'))->format('Y-m-d H:i:s');
        } else {
            return false;
        }
        $start_for_break_check = \Carbon\Carbon::parse($request->get('start'))->format('H:i');
        $end_for_break_check = \Carbon\Carbon::parse($request->get('end'))->format('H:i');

        $record = self::join('resource_has_rota', 'resources.id', '=', 'resource_has_rota.resource_id')
            ->join('resource_has_rota_days', 'resource_has_rota.id', '=', 'resource_has_rota_id')
            ->where('resources.external_id', '=', $request->get('doctor_id'))
            ->where('resource_has_rota_days.start_timestamp', '<=', $data['started_time'])
            ->where('resource_has_rota_days.end_timestamp', '>=', $data['ended_time'])
            ->get()->toArray();
        if ($record) {
            if ($record[0]['start_time']) {
                if ($record[0]['start_off']) {
                    $start_break = Carbon::parse($record[0]['start_off'])->format('H:i');
                    $end_break = Carbon::parse($record[0]['end_off'])->format('H:i');
                    if (
                        ($start_for_break_check > $start_break &&
                            $start_for_break_check < $end_break)
                        ||
                        ($end_for_break_check > $start_break &&
                            $end_for_break_check < $end_break)

                    ) {
                        return false;
                    } else {
                        return $record;
                    }
                } else {
                    return $record;
                }
            } else {
                return $record;
            }
        } else {
            return false;
        }
    }

    public static function checkingDoctorAvailbility($doctor_id, $start, $end)
    {

        $data['started_time'] = $start;
        $data['ended_time'] = $end;

        $start_for_break_check = \Carbon\Carbon::parse($start)->format('H:i');
        $end_for_break_check = \Carbon\Carbon::parse($end)->format('H:i');

        $record = self::join("resource_has_rota", "resources.id", "=", "resource_has_rota.resource_id")
            ->join("resource_has_rota_days", "resource_has_rota.id", "=", "resource_has_rota_id")
            ->where(["resources.external_id" => $doctor_id])
            ->where("resource_has_rota_days.start_timestamp", "<=", $data["started_time"])
            ->first();

        if ($record) {
            if ($record->start_time) {
                if ($record->start_off) {
                    $start_break = Carbon::parse($record->start_off)->format('H:i');
                    $end_break = Carbon::parse($record->end_off)->format('H:i');
                    if (
                        ($start_for_break_check > $start_break &&
                            $start_for_break_check < $end_break)
                        ||
                        ($end_for_break_check > $start_break &&
                            $end_for_break_check < $end_break)

                    ) {
                        return false;
                    } else {
                        return $record;
                    }
                } else {
                    return $record;
                }
            } else {
                return $record;
            }
        } else {
            return false;
        }
    }

    /**
     * is Room has rota in this time slot
     *
     * @param  Request  $request (resource_id, started_time, end_time
     * @return bool
     */
    public static function checkRoomAvailbility(Request $request)
    {
        if (
            $request->get('resourceId')
            && $request->get('start')
            && $request->get('end')
        ) {
            $data["started_time"] = \Carbon\Carbon::parse($request->get("start"))->format("Y-m-d H:i:s");
            $data["ended_time"] = \Carbon\Carbon::parse($request->get("end"))->format("Y-m-d H:i:s");
        } else {
            return false;
        }
        $record = self::join('resource_has_rota', 'resource_id', '=', 'resource_has_rota.resource_id')
            ->join('resource_has_rota_days', 'resource_has_rota.id', '=', 'resource_has_rota_id')
            ->where(['resources.id' => $request->get('resourceId')])
            ->where(['resource_has_rota.resource_id' => $request->get('resourceId')])
            ->where('resource_has_rota_days.start_timestamp', '<=', $data['started_time'])
            ->where('resource_has_rota_days.end_timestamp', '>=', $data['ended_time'])
            ->get()->toArray();
        if ($record) {
            return $record;
        } else {
            return false;
        }
    }

    /**
     * is Room has rota in this time slot
     *
     * @param  Request  $request (resource_id, started_time, end_time
     * @return bool
     */
    public static function checkingRoomAvailbility($resource_id, $start, $end)
    {
        if (
            $resource_id
            && $start
            && $end
        ) {
            $data["started_time"] = \Carbon\Carbon::parse($start)->format("Y-m-d H:i:s");
            $data["ended_time"] = \Carbon\Carbon::parse($end)->format("Y-m-d H:i:s");
        } else {
            return false;
        }

        return self::join('resource_has_rota', 'resource_id', '=', 'resource_has_rota.resource_id')
            ->join('resource_has_rota_days', 'resource_has_rota.id', '=', 'resource_has_rota_id')
            ->where(['resources.id' => $resource_id])
            ->where(['resource_has_rota.resource_id' => $resource_id])
            ->where('resource_has_rota_days.start_timestamp', '<=', $data['started_time'])
            ->where('resource_has_rota_days.end_timestamp', '>=', $data['ended_time'])
            ->exists();
    }

    /**
     * get doctor rotoas
     *
     * @return mixed
     */
    public static function getDoctorWithRotas($location_id, $doctor_id, $start_date, $end_date)
    {
        $where = [];
        $where[] = array("external_id", "=", $doctor_id);
        $where[] = array("resource_type_id", "=", self::getResourceType("doctor"));
        $where[] = array("account_id", "=", Auth::User()->account_id);
        return self::where($where)->with(["resource_rota", "doctor_rotas" => function ($query) use ($location_id, $start_date, $end_date) {
            $query->whereBetween("resource_has_rota_days.date", [$start_date, $end_date]);
            $query->where(["resource_has_rota.location_id" => $location_id]);
            $query->where(["resource_has_rota.active" => '1']);
        }])->get();
    }

    public static function getRoomsWithRotas()
    {
        $where = [];
        $where[] = ['resource_type_id', '=', self::getResourceType('room')];
        $where[] = ['account_id', '=', Auth::User()->account_id];

        return self::where($where)->with('rotas')->get();
    }

    public static function getRoomsResourceRotaWithoutDays($location_id)
    {
        $account_id = Auth::User()->account_id;
        $resource_type_id = self::getResourceType('Machine');
        $location_id = $location_id;
        $resources = DB::select(DB::raw("SELECT resources.id FROM resources INNER JOIN locations ON resources.location_id=locations.id WHERE resources.account_id = '$account_id' AND resources.resource_type_id ='$resource_type_id'  AND resources.location_id ='$location_id' "));
        $resources_array = [];
        foreach ($resources as $r) {
            $r = $r->id;
            $resources_array[] = Resources::where(['id' => $r])->with("resource_rota")->first();
        }

        return $resources_array;
        //return self::join('resources','locations.id  resources.location_id','=','resources.location_id')->where($where)->select('resources.*')->with("resource_rota")->get();
    }

    /**
     * get machines resources without rota days
     *
     * @return array
     */
    public static function getMachinesResourcesRotaWithoutDays($location_id, $machine_id)
    {
        $account_id = Auth::User()->account_id;
        $resource_type_id = self::getResourceType('Machine');
        $resources = DB::select(DB::raw("SELECT resources.id FROM resources INNER JOIN locations ON resources.location_id=locations.id WHERE resources.account_id = '$account_id' AND resources.id = '$machine_id' AND resources.resource_type_id ='$resource_type_id'  AND resources.location_id ='$location_id' "));
        $resources_array = [];
        foreach ($resources as $r) {
            $r = $r->id;
            $resources_array[] = Resources::where(['id' => $r])->with("resource_rota")->first();
        }

        return $resources_array;
    }

    public static function getRoomsWithRotasWithSpecificDate($start_date, $end_date, $range = false)
    {
        $where = [];
        $where[] = ['resource_type_id', '=', self::getResourceType('room')];
        $where[] = ['account_id', '=', Auth::User()->account_id];

        return self::where($where)->with(['rotas' => function ($query) use ($start_date, $end_date, $range) {
            if ($range) {
                $query->whereBetween('resource_has_rota_days.date', [$start_date, $end_date]);
            } else {
                $query->where(['resource_has_rota_days.date' => $start_date]);
            }
        }])->get();
    }

    public static function getDoctorWithRotasWithSpecificDate($location_id, $doctor_id, $start_date, $end_date)
    {
        $where = [];
        $where[] = ['external_id', '=', $doctor_id];
        $where[] = ['resource_type_id', '=', self::getResourceType('doctor')];
        $where[] = ['account_id', '=', Auth::User()->account_id];

        return self::where($where)->with(['doctor_rotas' => function ($query) use ($location_id, $start_date, $end_date) {
            $query->whereBetween('resource_has_rota_days.date', [$start_date, $end_date]);
            $query->where(['resource_has_rota.location_id' => $location_id]);
            $query->where(['resource_has_rota.is_treatment' => '1']);
            $query->where(['resource_has_rota.active' => '1']);
        }])->get();
    }

    public static function getDoctorRotaHasDay($start_date, $doctor_id)
    {
        $resouce = self::where(['external_id' => $doctor_id])->first();

        if ($resouce) {
            $record = ResourceHasRota::join('resource_has_rota_days', 'resource_has_rota_days.resource_has_rota_id', '=', 'resource_has_rota.id')
                ->whereDate('resource_has_rota_days.date', Carbon::parse($start_date)->format('Y-m-d'))
                ->where(['resource_has_rota.resource_id' => $resouce->id])
                ->select('resource_has_rota_days.*')
                ->first();

            if ($record) {
                return [
                    'resource_id' => $resouce->id,
                    'resource_has_rota_day_id' => $record->id,
                    'resource' => $record,
                    'resource_has_rota_day' => $record,
                ];
            } else {
                return [
                    'resource_id' => $resouce->id,
                    'resource_has_rota_day_id' => null,
                    'resource' => $record,
                    'resource_has_rota_day' => null,
                ];
            }
        }

        return [
            'resource_id' => null,
            'resource_has_rota_day_id' => null,
            'resource' => null,
            'resource_has_rota_day' => null,
        ];
    }

    public static function getResourceRotaHasDay($start_date, $resource_id)
    {
        $record = ResourceHasRota::join('resource_has_rota_days', 'resource_has_rota_days.resource_has_rota_id', '=', 'resource_has_rota.id')
            ->whereDate('resource_has_rota_days.date', Carbon::parse($start_date)->format('Y-m-d'))
            ->where(['resource_has_rota.resource_id' => $resource_id])
            ->select('resource_has_rota_days.*')
            ->first();

        if ($record) {
            return [
                'resource_has_rota_day_id' => $record->id,
                'resource_has_rota_day' => $record,
            ];
        } else {
            return [
                'resource_has_rota_day_id' => null,
                'resource_has_rota_day' => null,
            ];
        }
    }

    public function resource_rota()
    {
        return $this->hasOne("\App\Models\ResourceHasRota", 'resource_id');
    }

    public function resourceRota()
    {
        return $this->hasMany("\App\Models\ResourceHasRota", 'resource_id');
    }

    public function rotas()
    {
        return $this->hasManyThrough('\App\Models\ResourceHasRotaDays', '\App\Models\ResourceHasRota', 'resource_id', 'resource_has_rota_id', 'id', 'id');
    }

    public function doctor_rotas()
    {
        return $this->hasManyThrough('\App\Models\ResourceHasRotaDays', '\App\Models\ResourceHasRota', 'resource_id', 'resource_has_rota_id', 'id', 'id');
    }

    public function resource_types()
    {

        return $this->belongsTo('App\Models\ResourceTypes', 'resource_type_id');
    }

    /**
     * Get active and sorted data only.
     */
    public static function getActiveSorted($skip_ids = false, $include_ids = false)
    {
        if ($skip_ids && !is_array($skip_ids)) {
            $skip_ids = [$skip_ids];
        }
        if ($include_ids && !is_array($include_ids)) {
            $include_ids = [$include_ids];
        }

        if ($skip_ids && $include_ids) {
            return self::where(['active' => 1])->whereIn('id', $include_ids)->whereNotIn('id', $skip_ids)->OrderBy('name', 'asc')->get()->pluck('name', 'id');
        } elseif ($skip_ids) {
            return self::where(['active' => 1])->whereNotIn('id', $skip_ids)->OrderBy('name', 'asc')->get()->pluck('name', 'id');
        } elseif ($include_ids) {
            return self::where(['active' => 1])->whereIn('id', $include_ids)->OrderBy('name', 'asc')->get()->pluck('name', 'id');
        } else {
            return self::where(['active' => 1])->OrderBy('name', 'asc')->get()->pluck('name', 'id');
        }
    }

    /**
     * Get Total Records
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getTotalRecords(Request $request, $account_id = false, $apply_filter = false)
    {
        $where = self::resources_filters($request, $account_id, $apply_filter);

        if (count($where)) {
            $count = Resources::where($where)
                ->whereIn('location_id', ACL::getUserCentres())
                ->count();
        } else {
            $count = Resources::where($where)
                ->whereIn('location_id', ACL::getUserCentres())
                ->count();
        }

        return $count;
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
        $where = self::resources_filters($request, $account_id, $apply_filter);

        [$orderBy, $order] = getSortBy($request);

        if ($request->has('sort')) {
            Filters::put(Auth::User()->id, 'resources', 'order_by', $orderBy);
            Filters::put(Auth::User()->id, 'resources', 'order', $order);
        } else {
            if (
                Filters::get(Auth::User()->id, 'resources', 'order_by')
                && Filters::get(Auth::User()->id, 'resources', 'order')
            ) {
                $orderBy = Filters::get(Auth::User()->id, 'resources', 'order_by');
                $order = Filters::get(Auth::User()->id, 'resources', 'order');
            } else {

                Filters::put(Auth::User()->id, 'resources', 'order_by', $orderBy);
                Filters::put(Auth::User()->id, 'resources', 'order', $order);
            }
        }
        if (count($where)) {
            if (\Illuminate\Support\Facades\Gate::allows("view_inactive_resources")) {
                return Resources::with(['location.city', 'resource_types', 'MachineType'])->where($where)
                    ->whereIn('location_id', ACL::getUserCentres())
                    ->limit($iDisplayLength)
                    ->offset($iDisplayStart)
                    ->orderby($orderBy, $order)
                    ->get();
            } else {
                return Resources::with(['location.city', 'resource_types', 'MachineType'])->where($where)
                    ->whereIn('location_id', ACL::getUserCentres())
                    ->where(['resources.active' => 1])
                    ->where(['resources.active' => 1])
                    ->limit($iDisplayLength)
                    ->offset($iDisplayStart)
                    ->orderby($orderBy, $order)
                    ->get();
            }
        } else {
            if (\Illuminate\Support\Facades\Gate::allows("view_inactive_resources")) {
                return Resources::with(['location.city', 'resource_types', 'MachineType'])->whereIn('location_id', ACL::getUserCentres())
                    ->limit($iDisplayLength)
                    ->offset($iDisplayStart)
                    ->orderby($orderBy, $order)
                    ->get();
            } else {
                return Resources::with(['location.city', 'resource_types', 'MachineType'])->whereIn('location_id', ACL::getUserCentres())
                    ->where(['resources.active' => 1])
                    ->limit($iDisplayLength)
                    ->offset($iDisplayStart)
                    ->orderby($orderBy, $order)
                    ->get();
            }
        }
    }

    public static function resources_filters($request, $account_id, $apply_filter)
    {
        $where = [];

        $filename = 'resources';
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
            $where[] = array(['account_id' => $account_id]);
            Filters::put(Auth::User()->id, 'resources', 'account_id', $account_id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'resources', 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, 'resources', 'account_id')) {
                    $where[] = array(['account_id' => Filters::get(Auth::User()->id, 'resources', 'account_id')]);
                }
            }
        }
        if (hasFilter($filters, 'name')) {
            $where[] = ['name', 'like', '%' . $filters['name'] . '%',];
            Filters::put(Auth::User()->id, 'resources', 'name', $filters['name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'resources', 'name');
            } else {
                if (Filters::get(Auth::User()->id, 'resources', 'name')) {
                    $where[] = ['name', 'like', '%' . Filters::get(Auth::User()->id, 'resources', 'name') . '%',];
                }
            }
        }
        if (hasFilter($filters, 'resource_type_id')) {
            $where[] = array(['resource_type_id' => $filters['resource_type_id']]);
            Filters::put(Auth::User()->id, 'resources', 'resource_type_id', $filters['resource_type_id']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'resources', 'resource_type_id');
            } else {
                if (Filters::get(Auth::User()->id, 'resources', 'resource_type_id')) {
                    $where[] = array(['resource_type_id' => Filters::get(Auth::User()->id, 'resources', 'resource_type_id')]);
                }
            }
        }
        if (hasFilter($filters, 'location_id')) {
            $where[] = array(['location_id' => $filters['location_id']]);
            Filters::put(Auth::User()->id, 'resources', 'location_id', $filters['location_id']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'resources', 'location_id');
            } else {
                if (Filters::get(Auth::User()->id, 'resources', 'location_id')) {
                    $where[] = array(['location_id' => Filters::get(Auth::User()->id, 'resources', 'location_id')]);
                }
            }
        }

        if (hasFilter($filters, 'machine_type_id')) {
            $where[] = array(['machine_type_id' => $filters['machine_type_id']]);
            Filters::put(Auth::User()->id, 'resources', 'machine_type_id', $filters['machine_type_id']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'resources', 'machine_type_id');
            } else {
                if (Filters::get(Auth::User()->id, 'resources', 'machine_type_id')) {
                    $where[] = array(['machine_type_id' => Filters::get(Auth::User()->id, 'resources', 'machine_type_id')]);
                }
            }
        }

        if (hasFilter($filters, 'created_at')) {
            $where[] = ['resources.created_at', '>=', $start_date_time];
            $where[] = ['resources.created_at', '<=', $end_date_time];
            Filters::put(Auth::User()->id, 'resources', 'created_at', $filters['created_at']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'resources', 'created_at');
            } else {
                if (Filters::get(Auth::User()->id, 'resources', 'created_at')) {
                    $where[] = ['resources.created_at', '>=', Filters::get(Auth::User()->id, 'resources', 'created_at')];
                }
            }
        }

        if (hasFilter($filters, 'status')) {
            $where[] = array(['resources.active' => $filters['status']]);
            Filters::put(Auth::user()->id, 'resources', 'status', $filters['status']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'resources', 'status');
            } else {
                if (Filters::get(Auth::user()->id, 'resources', 'status') == 0 || Filters::get(Auth::user()->id, 'resources', 'status') == 1) {
                    if (Filters::get(Auth::user()->id, 'resources', 'status') != null) {
                        $where[] = array(['resources.active' => Filters::get(Auth::user()->id, 'resources', 'status')]);
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

        $data['account_id'] = $account_id;

        $data['external_id'] = '0';

        $record = self::create($data);

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

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

        $resource = Resources::getData($id);

        if (!$resource) {
            flash('Resource not found.')->error()->important();

            return redirect()->route('admin.resources.index');
        }

        $record = $resource->update(['active' => 0]);

        flash('Record has been inactivated successfully.')->success()->important();

        AuditTrails::inactiveEventLogger(self::$_table, 'inactive', self::$_fillable, $id);

        return $record;
    }

    /**
     * Create Record
     *
     * @param id
     * @return (mixed)
     */
    public static function activeRecord($id)
    {

        $resource = Resources::getData($id);

        if (!$resource) {
            return false;
        }

        $record = $resource->update(['active' => 1]);

        AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

        return true;
    }

    /**
     * delete Record
     *
     * @param id
     * @return (mixed)
     */
    public static function deleteRecord($id)
    {

        $resource = Resources::getData($id);

        if (!$resource) {
            return [
                'status' => false,
                'message' => 'Resource not found.',
            ];
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (Resources::isChildExists($id, Auth::User()->account_id)) {

            return [
                'status' => false,
                'message' => 'Child records exist, unable to delete resource',
            ];
        }

        $record = $resource->delete();

        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        return [
            'status' => true,
            'message' => 'Record has been deleted successfully.',
        ];
    }

    /**
     * Update Record
     *
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function updateRecord($id, $request, $account_id)
    {
        $old_data = (Resources::find($id))->toArray();

        $data = $request->all();

        $data['account_id'] = $account_id;

        $data['external_id'] = '0';

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
     * Check if child records exist
     *
     * @param  (int)  $id
     * @return (boolean)
     */
    public static function isChildExists($id, $account_id)
    {
        if (
            ResourceHasRota::where(['resource_id' => $id])->count()
        ) {
            return true;
        }

        return false;
    }

    /**
     * get resource
     *
     * @return (mixed)
     */
    public static function getresource()
    {
        return self::get()->pluck('name', 'id');
    }

    /**
     * get machine against location id in rota management
     *
     * @param location id and account id
     * @return (mixed)
     */
    public static function getActiveOnly($locationId = false, $account_id = false)
    {
        return self::where([
            ['location_id', '=', $locationId],
            ['active', '=', 1],
            ['external_id', '=', 0],
        ])->get();
    }
}
