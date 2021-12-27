<?php

namespace App\Models;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use DB;
use Session;
use App\Models\AuditTrails;
use Auth;

class StaffTargets extends BaseModal
{
    use SoftDeletes;

    protected $fillable = [
        'account_id', 'staff_id', 'location_id', 'total_amount', 'total_services',
        'month', 'year', 'created_at', 'updated_at', 'deleted_at'
    ];

    protected static $_fillable = [
        'account_id', 'staff_id', 'location_id', 'total_amount',
        'month', 'year',
    ];

    protected $table = 'staff_targets';

    protected static $_table = 'staff_targets';

    /**
     * Get the staff_targets.
     */
    public function staff_target_services()
    {

        return $this->hasMany('App\Models\StaffTargetServices', 'staff_target_id');
    }

    /**
     * Get the doctors for staff_target.
     */
    public function staff()
    {
        return $this->belongsTo('App\User', 'staff_id');
    }

    /**
     * Get the doctors for staff_target.
     */
    public function staff_target()
    {
        return $this->belongsTo('App\Models\StaffTargets', 'staff_target_id');
    }

    /**
     * Get the doctors for staff_target.
     */
    public function location()
    {
        return $this->belongsTo('App\Models\Locations', 'location_id');
    }

    /**
     * Get Total Records
     *
     * @param \Illuminate\Http\Request $request
     * @param (int) $account_id Current Organization's ID
     *
     * @return (mixed)
     */
    static public function getTotalRecords(Request $request, $account_id = false)
    {
        $where = array();

        if ($account_id) {
            $where[] = array(
                'account_id',
                '=',
                $account_id
            );
        }

        if ($request->header('X-LOCATION-ID')) {
            $where[] = array(
                'location_id',
                '=',
                $request->header('X-LOCATION-ID')
            );
        }

        if ($request->get('staff_id')) {
            $where[] = array(
                'staff_id',
                '=',
                $request->get('staff_id')
            );
        }

        if ($request->get('month')) {
            $where[] = array(
                'month',
                '=',
                $request->get('month')
            );
        }

        if ($request->get('year')) {
            $where[] = array(
                'year',
                '=',
                $request->get('year')
            );
        }

        if ($request->get('region')) {
            $where[] = array(
                'staff_targets.region_id',
                '=',
                $request->get('region')
            );
        }

        if ($request->get('total_amount')) {
            $where[] = array(
                'total_amount',
                '=',
                $request->get('total_amount')
            );
        }

        if (count($where)) {
            return self::where($where)
                ->count();
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
    static public function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false)
    {
        $where = array();

        if ($account_id) {
            $where[] = array(
                'account_id',
                '=',
                $account_id
            );
        }

        if ($request->header('X-LOCATION-ID')) {
            $where[] = array(
                'location_id',
                '=',
                $request->header('X-LOCATION-ID')
            );
        }

        if ($request->get('staff_id')) {
            $where[] = array(
                'staff_id',
                '=',
                $request->get('staff_id')
            );
        }

        if ($request->get('month')) {
            $where[] = array(
                'month',
                '=',
                $request->get('month')
            );
        }

        if ($request->get('year')) {
            $where[] = array(
                'year',
                '=',
                $request->get('year')
            );
        }

        if ($request->get('region')) {
            $where[] = array(
                'staff_targets.region_id',
                '=',
                $request->get('region')
            );
        }

        if ($request->get('total_amount')) {
            $where[] = array(
                'total_amount',
                '=',
                $request->get('total_amount')
            );
        }

        $orderBy = 'created_at';
        $order = 'desc';

        if ($request->get('order')[0]['dir']) {
            $orderColumn = $request->get('order')[0]['column'];
            $orderBy = $request->get('columns')[$orderColumn]['data'];
            $order = $request->get('order')[0]['dir'];
        }
        if (count($where)) {
            return self::where($where)
                ->orderby($orderBy, $order)
                ->limit($iDisplayLength)->offset($iDisplayStart)->get();
        } else {
            return self::orderby($orderBy, $order)
                ->limit($iDisplayLength)->offset($iDisplayStart)->get();
        }
    }

    /**
     * Get All Records with Dictionary
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
     * Get All Records by City
     *
     * @param (int) $cityId City's ID
     * @param (int) $account_id Current Organization's ID
     *
     * @return (mixed)
     */
    static public function getActiveRecordsByLocation($locationId = false, $staff_targetId = false, $account_id)
    {
        $where = array();

        $where[] = ['account_id', '=', $account_id];
        $where[] = ['active', '=', '1'];

        if($locationId) {
            $where[] = ['location_id', '=', $locationId];
        }

        if (is_array($staff_targetId)) {
            return self::where($where)->whereIn('id', $staff_targetId)->orderBy('name', 'asc')->get();
        } else {
            if ($staff_targetId) {
                return self::where($where)->whereIn('id', array($staff_targetId))->orderBy('name', 'asc')->get();
            } else {
                return self::where($where)->orderBy('name', 'asc')->get();
            }
        }
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

        //log request for Create for Audit Trail
        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);


        // Remove previous records
        $record->staff_target_services()->delete();

        if(is_array($data['target_amount']) && count($data['target_amount'])) {

            foreach ($data['target_amount'] as $service_id => $target_amount) {
                StaffTargetServices::createRecord(array(
                    'month' => $record->month,
                    'month' => $record->month,
                    'year' => $record->year,
                    'staff_id' => $record->staff_id,
                    'staff_target_id' => $record->id,
                    'location_id' => $record->location_id,
                    'service_id' => $service_id,
                    'target_amount' => $target_amount,
                    'target_services' => $data['target_services'][$service_id],
                    'account_id' => $data['account_id'],
                ), $record->id);
            }
        }

        return $record;
    }

    /**
     * delete Record
     *
     * @param id
     *
     * @return (mixed)
     */
    static function deleteRecord($id)
    {
        $staff_target = StaffTargets::getData($id);

        if (!$staff_target) {
            flash('Resource not found.')->error()->important();
            return redirect()->route('admin.staff_targets.index');
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (StaffTargets::isChildExists($id, Auth::User()->account_id)) {
            flash('Child records exist, unable to delete resource')->error()->important();
            return redirect()->route('admin.staff_targets.index');
        }

        // Remove belonging records records
        $staff_target->staff_target_services()->delete();

        $record = $staff_target->delete();

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
        $old_data = (StaffTargets::find($id))->toArray();

        $data = $request->all();

        $record = self::where([
            'id' => $id,
            'account_id' => $account_id
        ])->first();

        if (!$record) {
            return null;
        }

        $record->update($data);

        AuditTrails::editEventLogger(self::$_table, 'Edit', $data, self::$_fillable, $old_data, $id);

        // Remove previous records
        $record->staff_target_services()->delete();

        if(is_array($data['target_amount']) && count($data['target_amount'])) {
            foreach ($data['target_amount'] as $service_id => $target_amount) {
                StaffTargetServices::createRecord(array(
                    'month' => $record->month,
                    'year' => $record->year,
                    'staff_id' => $record->staff_id,
                    'staff_target_id' => $record->id,
                    'location_id' => $record->location_id,
                    'service_id' => $service_id,
                    'target_amount' => $target_amount,
                    'target_services' => $data['target_services'][$service_id],
                    'account_id' => $account_id,
                ), $id);
            }
        }

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

    public function service_has_staff_targets()
    {
        return $this->hasMany('App\Models\ServiceHasStaffTargets', 'staff_target_id')->withoutGlobalScope(SoftDeletingScope::class);
    }

    /**
     * Get Staff Target Services belongs to Staff Target
     *
     * @param \Illuminate\Http\Request $request
     * @param (int) $account_id Current Organization's ID
     *
     * @return (mixed)
     */
    static public function getStaffTargetServices(Request $request, $serviceIds, $account_id)
    {
        $totalServices = Services::where(array('account_id' => $account_id))->get()->getDictionary();
        $Services = Services::whereIn('id', $serviceIds)->get()->getDictionary();

        $where = array(
            'location_id' => $request->get('location_id'),
            'month' => $request->get('month'),
            'year' => $request->get('year'),
            'staff_id' => $request->get('staff_id'),
            'account_id' => $account_id
        );

        $targetServices = StaffTargetServices::where($where)->get();

        $staffTargetServices = array(
            'total_amount' => 0,
            'total_services' => 0,
            'target_services' => array(),
        );

        foreach($Services as $service) {
            $staffTargetServices['target_services'][$service->id] = array(
                'id' => $service->id,
                'name' => $service->name,
                'target_amount' => null,
                'target_services' => null,
            );
        }

        if($targetServices->count()) {
            foreach($targetServices as $targetService) {
                $staffTargetServices['total_amount'] = $staffTargetServices['total_amount'] + $targetService->target_amount;
                $staffTargetServices['total_services'] = $staffTargetServices['total_services'] + $targetService->target_services;

                if(array_key_exists($targetService->service_id, $staffTargetServices['target_services'])) {
                    $staffTargetServices['target_services'][$targetService->service_id]['target_amount'] = $targetService->target_amount;
                    $staffTargetServices['target_services'][$targetService->service_id]['target_services'] = $targetService->target_services;
                } else {
                    $staffTargetServices['target_services'][$targetService->service_id] = array(
                        'id' => $targetService->id,
                        'name' => $totalServices[$targetService->service_id]->name,
                        'target_amount' => $targetService->target_amount,
                        'target_services' => $targetService->target_services,
                    );
                }
            }
        }

        return $staffTargetServices;
    }
}
