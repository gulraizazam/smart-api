<?php

namespace App\Models;

use App\Helpers\Filters;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use App\Models\AuditTrails;
use Auth;


class Bundles extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['name', 'price', 'services_price', 'type','start','end', 'apply_discount', 'total_services', 'active', 'tax_treatment_type_id', 'created_at', 'updated_at', 'account_id'];

    protected static $_fillable = ['name', 'price', 'services_price', 'type','start','end', 'apply_discount', 'total_services', 'active', 'tax_treatment_type_id'];

    protected $table = 'bundles';

    protected static $_table = 'bundles';

    /**
     * sent the bundle data to resource has rota.
     */
    public function resourcehasrota()
    {
        return $this->hasMany('App\Models\ResourceHasRota', 'bundle_id');
    }

    /**
     * Get the Locations for Bundle.
     */
    public function locations()
    {
        return $this->hasMany('App\Models\Locations', 'bundle_id');
    }

    /**
     * Get the Active Locations for Bundle.
     */
    public function locationsActive()
    {
        return $this->hasMany('App\Models\Locations', 'bundle_id')->where(['active' => 1]);
    }

    /**
     * Get the doctors for Bundle.
     */
    public function doctors()
    {
        return $this->hasMany('App\Models\Doctors', 'bundle_id');
    }

    /**
     * Get the appointments for Bundle.
     */
    public function appointments()
    {
        return $this->hasMany('App\Models\Appointments', 'bundle_id');
    }

    /**
     * sent the bundle data to Package Bundle.
     */
    public function packagebundle()
    {
        return $this->hasMany('App\Models\PackageBundles', 'bundle_id');
    }

    /**
     * Get active and sorted data only.
     */
    static public function getActiveSorted($bundleId = false, $get_all = false)
    {
        if ($bundleId && !is_array($bundleId)) {
            $bundleId = array($bundleId);
        }
        if ($bundleId) {
            return self::where(['active' => 1, 'type' => 'multiple'])->whereIn('id', $bundleId)->where('account_id', '=', Auth::User()->account_id)->get()->pluck('name', 'id');
        } else {
            return self::where(['active' => 1, 'type' => 'multiple'])->where('account_id', '=', Auth::User()->account_id)->pluck('name', 'id');
        }
    }

    /**
     * Get active and sorted data only.
     */
    static public function getActiveOnly($bundleId = false)
    {
        if ($bundleId && !is_array($bundleId)) {
            $bundleId = array($bundleId);
        }
        $query = self::where(['active' => 1]);
        if ($bundleId) {
            $query->whereIn('id', $bundleId);
        }
        return $query->OrderBy('sort_number', 'asc')->get();
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

        $where = self::bundles_filters($request, $account_id, $apply_filter);
        if (count($where)) {
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
        $orderBy = 'created_at';
        $order = 'desc';

        if ($request->get('order')[0]['dir']) {
            $orderColumn = $request->get('order')[0]['column'];
            $orderBy = $request->get('columns')[$orderColumn]['data'];
            $order = $request->get('order')[0]['dir'];
        }

        $where = self::bundles_filters($request, $account_id, $apply_filter);
        if (count($where)) {
            return self::where($where)
                ->limit($iDisplayLength)
                ->offset($iDisplayStart)
                ->orderBy($orderBy, $order)
                ->get();
        } else {
            return self::limit($iDisplayLength)
                ->offset($iDisplayStart)
                ->orderBy($orderBy, $order)
                ->get();
        }
    }

    static public function bundles_filters($request, $account_id, $apply_filter)
    {
        $where = array();

        $where[] = array(
            'type',
            '!=',
            'single'
        );

        if ($account_id) {
            $where[] = array(
                'account_id',
                '=',
                $account_id
            );
            Filters::put(Auth::User()->id, 'bundles', 'account_id', $account_id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'bundles', 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, 'bundles', 'account_id')) {
                    $where[] = array(
                        'account_id',
                        '=',
                        Filters::get(Auth::User()->id, 'bundles', 'account_id')
                    );
                }
            }
        }
        if ($request->get('name') && $request->get('name') != '') {
            $where[] = array(
                'name',
                'like',
                '%' . $request->get('name') . '%'
            );
            Filters::put(Auth::User()->id, 'bundles', 'name', $request->get('name'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'bundles', 'name');
            } else {
                if (Filters::get(Auth::User()->id, 'bundles', 'name')) {
                    $where[] = array(
                        'name',
                        'like',
                        '%' . Filters::get(Auth::User()->id, 'bundles', 'name') . '%'
                    );
                }
            }
        }

        if ($request->get('price') && $request->get('price') != '') {
            $where[] = array(
                'price',
                'like',
                '%' . $request->get('price') . '%'
            );
            Filters::put(Auth::User()->id, 'bundles', 'price', $request->get('price'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'bundles', 'price');
            } else {
                if (Filters::get(Auth::User()->id, 'bundles', 'price')) {
                    $where[] = array(
                        'price',
                        'like',
                        '%' . Filters::get(Auth::User()->id, 'bundles', 'price') . '%'
                    );
                }
            }
        }

        if ($request->get('total_services') && $request->get('total_services') != '') {
            $where[] = array(
                'total_services',
                'like',
                '%' . $request->get('total_services') . '%'
            );
            Filters::put(Auth::User()->id, 'bundles', 'total_services', $request->get('total_services'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'bundles', 'total_services');
            } else {
                if (Filters::get(Auth::User()->id, 'bundles', 'total_services')) {
                    $where[] = array(
                        'total_services',
                        'like',
                        '%' . Filters::get(Auth::User()->id, 'bundles', 'total_services') . '%'
                    );
                }
            }
        }

        if ($request->get('apply_discount') != '') {
            $where[] = array(
                'apply_discount',
                'like',
                '%' . $request->get('apply_discount') . '%'
            );
            Filters::put(Auth::User()->id, 'bundles', 'apply_discount', $request->get('apply_discount'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'bundles', 'apply_discount');
            } else {
                if (Filters::get(Auth::User()->id, 'bundles', 'apply_discount')) {
                    $where[] = array(
                        'apply_discount',
                        'like',
                        '%' . Filters::get(Auth::User()->id, 'bundles', 'apply_discount') . '%'
                    );
                }
            }
        }

        if ($request->get('created_from') && $request->get('created_from') != '') {
            $where[] = array(
                'created_at',
                '>=',
                $request->get('created_from') . ' 00:00:00'
            );
            Filters::put(Auth::User()->id, 'bundles', 'created_from', $request->get('created_from') . ' 00:00:00');
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'bundles', 'created_from');
            } else {
                if (Filters::get(Auth::User()->id, 'bundles', 'created_from')) {
                    $where[] = array(
                        'created_at',
                        '>=',
                        Filters::get(Auth::User()->id, 'bundles', 'created_from')
                    );
                }
            }
        }

        if ($request->get('created_to') && $request->get('created_to') != '') {
            $where[] = array(
                'created_at',
                '<=',
                $request->get('created_to') . ' 23:59:59'
            );
            Filters::put(Auth::User()->id, 'bundles', 'created_to', $request->get('created_to') . ' 23:59:59');
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'bundles', 'created_to');
            } else {
                if (Filters::get(Auth::User()->id, 'bundles', 'created_to')) {
                    $where[] = array(
                        'created_at',
                        '<=',
                        Filters::get(Auth::User()->id, 'bundles', 'created_to')
                    );
                }
            }
        }
        if ($request->get('startdate') && $request->get('startdate') != '') {
            $where[] = array(
                'start',
                '>=',
                $request->get('startdate')
            );
            
            Filters::put(Auth::user()->id, 'bundles', 'start', $request->get('startdate'));
        }else{
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'bundles', 'start');
            } else {
                if (Filters::get(Auth::user()->id, 'bundles', 'start') == 0 || Filters::get(Auth::user()->id, 'bundles', 'start') == 1) {
                    if (Filters::get(Auth::user()->id, 'bundles', 'start') != null) {
                        $where[] = array(
                            'start',
                            '>=',
                            Filters::get(Auth::user()->id, 'bundles', 'start')
                        );
                    }
                }
            }
        }
        if ($request->get('enddate') && $request->get('enddate') != '') {
            $where[] = array(
                'end',
                '<=',
                $request->get('enddate')
            );
            Filters::put(Auth::user()->id, 'bundles', 'end', $request->get('enddate'));
        }else{
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'bundles', 'end');
            } else {
                if (Filters::get(Auth::user()->id, 'bundles', 'end') == 0 || Filters::get(Auth::user()->id, 'bundles', 'end') == 1) {
                    if (Filters::get(Auth::user()->id, 'bundles', 'end') != null) {
                        $where[] = array(
                            'end',
                            '<=',
                            Filters::get(Auth::user()->id, 'bundles', 'end')
                        );
                    }
                }
            }
        }

        if ($request->get('status') && $request->get('status') != null || $request->get('status') == 0 && $request->get('status') != null) {
            $where[] = array(
                'active',
                '=',
                $request->get('status')
            );
            Filters::put(Auth::user()->id, 'bundles', 'status', $request->get('status'));
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'bundles', 'status');
            } else {
                if (Filters::get(Auth::user()->id, 'bundles', 'status') == 0 || Filters::get(Auth::user()->id, 'bundles', 'status') == 1) {
                    if (Filters::get(Auth::user()->id, 'bundles', 'status') != null) {
                        $where[] = array(
                            'active',
                            '=',
                            Filters::get(Auth::user()->id, 'bundles', 'status')
                        );
                    }
                }
            }
        }

        return $where;
    }

    /**
     * Calculate Price based on package price
     *
     * @param (array) $services
     * @param (double) $services_price
     * @param (double) $price
     *
     * @return (array) $services
     */
    static function calculatePrices($services, $services_price, $price)
    {

        $calculated_services = array();

        /*
         * Case 1: $services_price is greater than $price
         */
        if ($services_price == $price) {
            foreach ($services as $key => $service) {
                $services[$key]['calculated_price'] = $services[$key]['service_price'];
            }
        } else if ($services_price > $price) {
            $ratio = (1 - round(($price / $services_price), 8));
            foreach ($services as $key => $service) {
                $services[$key]['calculated_price'] = round($services[$key]['service_price'] - ($services[$key]['service_price'] * $ratio), 2);
            }
        } else {
            $ratio = -1 * (1 - round(($price / $services_price), 8));
            foreach ($services as $key => $service) {
                $services[$key]['calculated_price'] = round($services[$key]['service_price'] + ($services[$key]['service_price'] * $ratio), 2);
            }
        }

        return $services;
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
        $data['type'] = 'multiple';

        if (!isset($data['apply_discount'])) {
            $data['apply_discount'] = 0;
        } else if ($data['apply_discount'] == '') {
            $data['apply_discount'] = 0;
        }

        if (is_array($data['service_id']) && count($data['service_id'])) {
            $data['total_services'] = count($data['service_id']);

            $data['services_price'] = 0.00;
            foreach ($data['service_price'] as $service_price) {
                $data['services_price'] = $data['services_price'] + $service_price;
            }
        }

        $record = self::create($data);

        //log request for Create for Audit Trail
        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

        if (is_array($data['service_id']) && count($data['service_id'])) {
            $services = Services::whereIn('id', $data['service_id'])->where(['account_id' => $account_id])->get()->getDictionary();

            // Calculate New Service Prices
            $services_calculation = array();
            foreach ($data['service_id'] as $key => $service_id) {
                if (array_key_exists($service_id, $services)) {
                    $services_calculation[$key] = array(
                        'service_id' => $service_id,
                        'service_price' => $data['service_price'][$key],
                        'calculated_price' => 0.00,
                    );
                }
            }
            $calculated_services = self::calculatePrices($services_calculation, $data['services_price'], $data['price']);

            foreach ($data['service_id'] as $key => $service_id) {
                if (array_key_exists($service_id, $services)) {
                    BundleHasServices::createRecord(array(
                        'bundle_id' => $record->id,
                        'service_id' => $service_id,
                        'service_price' => $calculated_services[$key]['service_price'],
                        'calculated_price' => $calculated_services[$key]['calculated_price'],
                        'end_node' => $services[$service_id]->end_node,
                    ), $record->id);

                    BundleServicesPriceHistory::createRecord(array(
                        'bundle_id' => $record->id,
                        'bundle_price' => $record->price,
                        'service_id' => $service_id,
                        //'service_price' => $data['service_price'][$key],
                        'service_price' => $calculated_services[$key]['calculated_price'],
                        'effective_from' => \Carbon\Carbon::now()->format('Y-m-d'),
                        'created_by' => Auth::User()->id,
                        'updated_by' => Auth::User()->id,
                    ), $account_id);
                }
            }
        }

        return $record;
    }

    /**
     * Delete Record
     *
     * @param id
     *
     * @return (mixed)
     */
    static public function DeleteRecord($id)
    {
        $bundle = Bundles::getData($id);

        if (!$bundle) {

            flash('Resource not found.')->error()->important();
            return redirect()->route('admin.bundles.index');
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (Bundles::isChildExists($id, Auth::User()->account_id)) {

            flash('Child records exist, unable to delete resource')->error()->important();
            return redirect()->route('admin.bundles.index');
        }

        $record = $bundle->delete();

        // Delete Old Bundle relationships
        BundleHasServices::where(['bundle_id' => $id])->delete();

        //log request for delete for audit trail

        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        flash('Record has been deleted successfully.')->success()->important();

        return $record;

    }

    /**
     * inactive Record
     *
     * @param id
     *
     * @return (mixed)
     */
    static public function inactiveRecord($id)
    {

        $bundle = Bundles::getData($id);

        if (!$bundle) {
            flash('Resource not found.')->error()->important();
            return redirect()->route('admin.bundles.index');
        }

        $record = $bundle->update(['active' => 0]);

        flash('Record has been inactivated successfully.')->success()->important();

        AuditTrails::InactiveEventLogger(self::$_table, 'inactive', self::$_fillable, $id);

        return $record;
    }

    /**
     * active Record
     *
     * @param id
     *
     * @return (mixed)
     */
    static function activeRecord($id)
    {

        $bundle = Bundles::getData($id);

        if (!$bundle) {

            flash('Resource not found.')->error()->important();
            return redirect()->route('admin.bundles.index');
        }

        $record = $bundle->update(['active' => 1]);

        flash('Record has been activated successfully.')->success()->important();

        AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

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
        $old_data = (Bundles::find($id))->toArray();

        $data = $request->all();
        // Set Account ID
        $data['account_id'] = $account_id;
        $data['type'] = 'multiple';

        if (!isset($data['apply_discount'])) {
            $data['apply_discount'] = 0;
        } else if ($data['apply_discount'] == '') {
            $data['apply_discount'] = 0;
        }

        if (is_array($data['service_id']) && count($data['service_id'])) {
            $data['total_services'] = count($data['service_id']);

            $data['services_price'] = 0.00;
            foreach ($data['service_price'] as $service_price) {
                $data['services_price'] = $data['services_price'] + $service_price;
            }
        }

        $record = self::where([
            'id' => $id,
            'account_id' => $account_id
        ])->first();

        if (!$record) {
            return null;
        }

        $record->update($data);

        AuditTrails::EditEventLogger(self::$_table, 'edit', $data, self::$_fillable, $old_data, $id);

        // Delete Old Bundle relationships
        BundleHasServices::where(['bundle_id' => $record->id])->delete();

        // Deactivate Previous Price History
        BundleServicesPriceHistory::where(['bundle_id' => $record->id])
            ->whereNull('effective_to')
            ->update(array(
                'effective_to' => Carbon::now()->format('Y-m-d'),
                'active' => 0,
                'updated_by' => Auth::User()->id,
            ));

        // Create New Bundle Services
        if (is_array($data['service_id']) && count($data['service_id'])) {
            $services = Services::whereIn('id', $data['service_id'])->where(['account_id' => $account_id])->get()->getDictionary();

            // Calculate New Service Prices
            $services_calculation = array();
            foreach ($data['service_id'] as $key => $service_id) {
                if (array_key_exists($service_id, $services)) {
                    $services_calculation[$key] = array(
                        'service_id' => $service_id,
                        'service_price' => $data['service_price'][$key],
                        'calculated_price' => 0.00,
                    );
                }
            }
            $calculated_services = self::calculatePrices($services_calculation, $data['services_price'], $data['price']);

            foreach ($data['service_id'] as $key => $service_id) {
                if (array_key_exists($service_id, $services)) {
                    BundleHasServices::createRecord(array(
                        'bundle_id' => $record->id,
                        'service_id' => $service_id,
                        'service_price' => $calculated_services[$key]['service_price'],
                        'calculated_price' => $calculated_services[$key]['calculated_price'],
                        'end_node' => $services[$service_id]->end_node,
                    ), $record->id);

                    BundleServicesPriceHistory::createRecord(array(
                        'bundle_id' => $record->id,
                        'bundle_price' => $record->price,
                        'service_id' => $service_id,
//                        'service_price' => $data['service_price'][$key],
                        'service_price' => $calculated_services[$key]['calculated_price'],
                        'effective_from' => \Carbon\Carbon::now()->format('Y-m-d'),
                        'created_by' => Auth::User()->id,
                        'updated_by' => Auth::User()->id,
                    ), $account_id);
                }
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
//        if (
//            Cities::where(['bundle_id' => $id, 'account_id' => $account_id])->count() ||
//            Locations::where(['bundle_id' => $id, 'account_id' => $account_id])->count() ||
//            Leads::where(['bundle_id' => $id, 'account_id' => $account_id])->count() ||
//            Appointments::where(['bundle_id' => $id, 'account_id' => $account_id])->count()
//        ) {
//            return true;
//        }

        return false;
    }

    static public function getBundles()
    {

        $date = Carbon::now();
        return self::where([
            ['start','<=',$date],
            ['end','>=',$date],
            ['account_id', '=', Auth::User()->account_id],
            ['active', '=', '1'],
        ])->OrderBy('sort_number', 'asc')->get()->pluck('name', 'id');

    }
}
