<?php

namespace App\Models;

use App\Helpers\Filters;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class Bundles extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['name', 'price', 'services_price', 'type', 'start', 'end', 'apply_discount', 'total_services', 'active', 'tax_treatment_type_id', 'created_at', 'updated_at', 'account_id'];

    protected static $_fillable = ['name', 'price', 'services_price', 'type', 'start', 'end', 'apply_discount', 'total_services', 'active', 'tax_treatment_type_id'];

    protected $table = 'bundles';

    protected static $_table = 'bundles';

    protected $casts = [
        'created_at' => 'datetime:F d,Y h:i A',
    ];

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
    public static function getActiveSorted($bundleId = false, $get_all = false)
    {
        if ($bundleId && ! is_array($bundleId)) {
            $bundleId = [$bundleId];
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
    public static function getActiveOnly($bundleId = false)
    {
        if ($bundleId && ! is_array($bundleId)) {
            $bundleId = [$bundleId];
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
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getTotalRecords(Request $request, $account_id = false, $apply_filter = false)
    {

        $where = self::bundles_filters($request, $account_id, $apply_filter);
        if (count($where)) {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_packages')) {
                return self::where($where)->count();
            } else {
                return self::where($where)->where('active', 1)->count();
            }
        } else {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_packages')) {
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

        [$orderBy, $order] = getSortBy($request);

        $where = self::bundles_filters($request, $account_id, $apply_filter);
        if (count($where)) {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_packages')) {
                return self::where($where)
                    ->limit($iDisplayLength)
                    ->offset($iDisplayStart)
                    ->orderBy($orderBy, $order)
                    ->get();
            } else {
                return self::where($where)
                    ->where('active', 1)
                    ->limit($iDisplayLength)
                    ->offset($iDisplayStart)
                    ->orderBy($orderBy, $order)
                    ->get();
            }
        } else {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_packages')) {
                return self::limit($iDisplayLength)
                    ->offset($iDisplayStart)
                    ->orderBy($orderBy, $order)
                    ->get();
            } else {
                return self::where('active', 1)->limit($iDisplayLength)
                    ->offset($iDisplayStart)
                    ->orderBy($orderBy, $order)
                    ->get();
            }
        }
    }

    public static function bundles_filters($request, $account_id, $apply_filter)
    {
        $where = [];
        $filters = getFilters($request->all());
        $where[] = [
            'type',
            '!=',
            'single',
        ];

        if ($account_id) {
            $where[] = [
                'account_id',
                '=',
                $account_id,
            ];
            Filters::put(Auth::User()->id, 'bundles', 'account_id', $account_id);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'bundles', 'account_id');
            } else {
                if (Filters::get(Auth::User()->id, 'bundles', 'account_id')) {
                    $where[] = [
                        'account_id',
                        '=',
                        Filters::get(Auth::User()->id, 'bundles', 'account_id'),
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
            Filters::put(Auth::User()->id, 'bundles', 'name', $filters['name']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'bundles', 'name');
            } else {
                if (Filters::get(Auth::User()->id, 'bundles', 'name')) {
                    $where[] = [
                        'name',
                        'like',
                        '%'.Filters::get(Auth::User()->id, 'bundles', 'name').'%',
                    ];
                }
            }
        }

        if (hasFilter($filters, 'price')) {
            $where[] = [
                'price',
                '=',
                $filters['price'],
            ];
            Filters::put(Auth::User()->id, 'bundles', 'price', $filters['price']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'bundles', 'price');
            } else {
                if (Filters::get(Auth::User()->id, 'bundles', 'price')) {
                    $where[] = [
                        'price',
                        '=',
                        Filters::get(Auth::User()->id, 'bundles', 'price'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'total_services')) {
            $where[] = [
                'total_services',
                '=',
                $filters['total_services'],
            ];
            Filters::put(Auth::User()->id, 'bundles', 'total_services', $filters['total_services']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'bundles', 'total_services');
            } else {
                if (Filters::get(Auth::User()->id, 'bundles', 'total_services')) {
                    $where[] = [
                        'total_services',
                        '=',
                        Filters::get(Auth::User()->id, 'bundles', 'total_services'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'apply_discount') || (isset($filters['apply_discount']) && ($filters['apply_discount'] == 0 || $filters['apply_discount'] == 1))) {
            $where[] = [
                'apply_discount',
                '=',
                $filters['apply_discount'],
            ];
            Filters::put(Auth::User()->id, 'bundles', 'apply_discount', $filters['apply_discount']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'bundles', 'apply_discount');
            } else {
                if (Filters::get(Auth::User()->id, 'bundles', 'apply_discount')) {
                    $where[] = [
                        'apply_discount',
                        '=',
                        Filters::get(Auth::User()->id, 'bundles', 'apply_discount'),
                    ];
                }
            }
        }

        if (hasFilter($filters, 'created_from')) {
            $created_from = Carbon::createFromFormat('m/d/Y', $filters['created_from'])->startOfDay()->toDateTimeString();
            $where[] = [
                'created_at',
                '>=',
                $created_from,
            ];
            Filters::put(Auth::User()->id, 'bundles', 'created_from', $filters['created_from']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'bundles', 'created_from');
            } else {
                if (Filters::get(Auth::User()->id, 'bundles', 'created_from')) {
                    $created_from = Carbon::createFromFormat('m/d/Y', Filters::get(Auth::User()->id, 'bundles', 'created_from'))->startOfDay()->toDateTimeString();
                    $where[] = [
                        'created_at',
                        '>=',
                        $created_from,
                    ];
                }
            }
        }

        if (hasFilter($filters, 'created_to')) {
            $created_to = Carbon::createFromFormat('m/d/Y', $filters['created_to'])->endOfDay()->toDateTimeString();
            $where[] = [
                'created_at',
                '<=',
                $created_to,
            ];
            Filters::put(Auth::User()->id, 'bundles', 'created_to', $filters['created_to']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::User()->id, 'bundles', 'created_to');
            } else {
                if (Filters::get(Auth::User()->id, 'bundles', 'created_to')) {
                    $created_to = Carbon::createFromFormat('m/d/Y', Filters::get(Auth::User()->id, 'bundles', 'created_to'))->endOfDay()->toDateTimeString();
                    $where[] = [
                        'created_at',
                        '<=',
                        $created_to,
                    ];
                }
            }
        }
        if (hasFilter($filters, 'startdate')) {
            $where[] = [
                'start',
                '>=',
                $filters['startdate'],
            ];
            Filters::put(Auth::user()->id, 'bundles', 'start', $filters['startdate']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'bundles', 'start');
            } else {
                if (Filters::get(Auth::user()->id, 'bundles', 'start')) {
                    $where[] = [
                        'start',
                        '>=',
                        Filters::get(Auth::user()->id, 'bundles', 'start'),
                    ];
                }
            }
        }
        if (hasFilter($filters, 'enddate')) {
            $where[] = [
                'end',
                '<=',
                $filters['enddate'],
            ];
            Filters::put(Auth::user()->id, 'bundles', 'end', $filters['enddate']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'bundles', 'end');
            } else {
                if (Filters::get(Auth::user()->id, 'bundles', 'end') != null) {
                    $where[] = [
                        'end',
                        '<=',
                        Filters::get(Auth::user()->id, 'bundles', 'end'),
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
            Filters::put(Auth::user()->id, 'bundles', 'status', $filters['status']);
        } else {
            if ($apply_filter) {
                Filters::forget(Auth::user()->id, 'bundles', 'status');
            } else {
                if (Filters::get(Auth::user()->id, 'bundles', 'status') == 0 || Filters::get(Auth::user()->id, 'bundles', 'status') == 1) {
                    if (Filters::get(Auth::user()->id, 'bundles', 'status') != null) {
                        $where[] = [
                            'active',
                            '=',
                            Filters::get(Auth::user()->id, 'bundles', 'status'),
                        ];
                    }
                }
            }
        }

        return $where;
    }

    /**
     * Calculate Price based on package price
     *
     * @param  (array)  $services
     * @param  (double)  $services_price
     * @param  (double)  $price
     * @return (array) $services
     */
    public static function calculatePrices($services, $services_price, $price)
    {

        $calculated_services = [];

        /*
         * Case 1: $services_price is greater than $price
         */
        if ($services_price == $price) {
            foreach ($services as $key => $service) {
                $services[$key]['calculated_price'] = $services[$key]['service_price'];
            }
        } elseif ($services_price > $price) {
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
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function createRecord($request, $account_id)
    {
        $data = $request->all();

        // Set Account ID
        $data['account_id'] = $account_id;
        $data['type'] = 'multiple';

        if (! isset($data['apply_discount'])) {
            $data['apply_discount'] = 0;
        } elseif ($data['apply_discount'] == '') {
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
            $services_calculation = [];
            foreach ($data['service_id'] as $key => $service_id) {
                if (array_key_exists($service_id, $services)) {
                    $services_calculation[$key] = [
                        'service_id' => $service_id,
                        'service_price' => $data['service_price'][$key],
                        'calculated_price' => 0.00,
                    ];
                }
            }
            $calculated_services = self::calculatePrices($services_calculation, $data['services_price'], $data['price']);

            foreach ($data['service_id'] as $key => $service_id) {
                if (array_key_exists($service_id, $services)) {
                    BundleHasServices::createRecord([
                        'bundle_id' => $record->id,
                        'service_id' => $service_id,
                        'service_price' => $calculated_services[$key]['service_price'],
                        'calculated_price' => $calculated_services[$key]['calculated_price'],
                        'end_node' => $services[$service_id]->end_node,
                    ], $record->id);

                    BundleServicesPriceHistory::createRecord([
                        'bundle_id' => $record->id,
                        'bundle_price' => $record->price,
                        'service_id' => $service_id,
                        //'service_price' => $data['service_price'][$key],
                        'service_price' => $calculated_services[$key]['calculated_price'],
                        'effective_from' => \Carbon\Carbon::now()->format('Y-m-d'),
                        'created_by' => Auth::User()->id,
                        'updated_by' => Auth::User()->id,
                    ], $account_id);
                }
            }
        }

        return $record;
    }

    /**
     * Delete Record
     *
     * @param id
     * @return (mixed)
     */
    public static function DeleteRecord($id)
    {
        $bundle = Bundles::getData($id);

        if (! $bundle) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (Bundles::isChildExists($id, Auth::User()->account_id)) {
            return collect(['status' => false, 'message' => 'Child records exist, unable to delete resource']);
        }

        $record = $bundle->delete();

        // Delete Old Bundle relationships
        BundleHasServices::where(['bundle_id' => $id])->delete();

        //log request for delete for audit trail

        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        return collect(['status' => true, 'message' => 'Record has been deleted successfully.']);

    }

    /**
     * inactive Record
     *
     * @param id
     * @return (mixed)
     */
    public static function inactiveRecord($id)
    {
        $bundle = Bundles::getData($id);
        if (! $bundle) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }
        $record = $bundle->update(['active' => 0]);
        AuditTrails::InactiveEventLogger(self::$_table, 'inactive', self::$_fillable, $id);

        return collect(['status' => true, 'message' => 'Record has been inactivated successfully.']);
    }

    /**
     * active Record
     *
     * @param id
     * @return (mixed)
     */
    public static function activeRecord($id)
    {
        $bundle = Bundles::getData($id);
        if (! $bundle) {
            return collect(['status' => false, 'message' => 'Resource not found.']);
        }
        $record = $bundle->update(['active' => 1]);
        AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

        return collect(['status' => true, 'message' => 'Record has been activated successfully.']);
    }

    /**
     * Update Record
     *
     * @param  \Illuminate\Http\Request  $request
     * @return (mixed)
     */
    public static function updateRecord($id, $request, $account_id)
    {
        $old_data = (Bundles::find($id))->toArray();

        $data = $request->all();
        // Set Account ID
        $data['account_id'] = $account_id;
        $data['type'] = 'multiple';

        if (! isset($data['apply_discount'])) {
            $data['apply_discount'] = 0;
        } elseif ($data['apply_discount'] == '') {
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
            'account_id' => $account_id,
        ])->first();

        if (! $record) {
            return null;
        }

        $record->update($data);

        AuditTrails::EditEventLogger(self::$_table, 'edit', $data, self::$_fillable, $old_data, $id);

        // Delete Old Bundle relationships
        BundleHasServices::where(['bundle_id' => $record->id])->delete();

        // Deactivate Previous Price History
        BundleServicesPriceHistory::where(['bundle_id' => $record->id])
            ->whereNull('effective_to')
            ->update([
                'effective_to' => Carbon::now()->format('Y-m-d'),
                'active' => 0,
                'updated_by' => Auth::User()->id,
            ]);

        // Create New Bundle Services
        if (is_array($data['service_id']) && count($data['service_id'])) {
            $services = Services::whereIn('id', $data['service_id'])->where(['account_id' => $account_id])->get()->getDictionary();

            // Calculate New Service Prices
            $services_calculation = [];
            foreach ($data['service_id'] as $key => $service_id) {
                if (array_key_exists($service_id, $services)) {
                    $services_calculation[$key] = [
                        'service_id' => $service_id,
                        'service_price' => $data['service_price'][$key],
                        'calculated_price' => 0.00,
                    ];
                }
            }
            $calculated_services = self::calculatePrices($services_calculation, $data['services_price'], $data['price']);

            foreach ($data['service_id'] as $key => $service_id) {
                if (array_key_exists($service_id, $services)) {
                    BundleHasServices::createRecord([
                        'bundle_id' => $record->id,
                        'service_id' => $service_id,
                        'service_price' => $calculated_services[$key]['service_price'],
                        'calculated_price' => $calculated_services[$key]['calculated_price'],
                        'end_node' => $services[$service_id]->end_node,
                    ], $record->id);

                    BundleServicesPriceHistory::createRecord([
                        'bundle_id' => $record->id,
                        'bundle_price' => $record->price,
                        'service_id' => $service_id,
                        //                        'service_price' => $data['service_price'][$key],
                        'service_price' => $calculated_services[$key]['calculated_price'],
                        'effective_from' => \Carbon\Carbon::now()->format('Y-m-d'),
                        'created_by' => Auth::User()->id,
                        'updated_by' => Auth::User()->id,
                    ], $account_id);
                }
            }
        }

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

    public static function getBundles()
    {

        $date = Carbon::now();

        return self::where([
            ['start', '<=', $date],
            ['end', '>=', $date],
            ['account_id', '=', Auth::User()->account_id],
            ['active', '=', '1'],
        ])->OrderBy('sort_number', 'asc')->get()->pluck('name', 'id');

    }
}
