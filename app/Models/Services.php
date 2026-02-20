<?php

namespace App\Models;

use App\Helpers\NodesTree;
use Auth;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Calculation\Web\Service;

class Services extends BaseModal
{
    use SoftDeletes;

    protected $fillable = ['name', 'slug', 'end_node', 'complimentory', 'account_id', 'active', 'tax_treatment_type_id', 'created_at', 'updated_at', 'parent_id', 'duration', 'price', 'description', 'color', 'sort_no'];

    protected static $_fillable = ['name', 'slug', 'end_node', 'complimentory', 'active', 'tax_treatment_type_id', 'parent_id', 'duration', 'price', 'description', 'color'];

    protected $table = 'services';

    protected static $_table = 'services';

    public function scopeIsActive($query, $status = 1)
    {
        return $query->where('active', $status);
    }

    /**
     * Get the Service.
     */
    public function doctorhaslocation()
    {

        return $this->hasMany('App\Models\DoctorHasLocations', 'service_id');
    }

    /**
     * Get the service.
     */
    public function discounthaslocation()
    {

        return $this->hasMany('App\Models\DiscountHasLocations', 'service_id');
    }

    /**
     * Get the Appointments for Treatment.
     */
    public function appointments()
    {
        return $this->hasMany('App\Models\Appointments', 'service_id');
    }

    /**
     * Get the Leads for Treatment.
     */
    public function leads()
    {
        return $this->hasMany('App\Models\Leads', 'service_id');
    }

    public function childlead()
    {
        return $this->hasMany('App\Models\Leads', 'child_service_id');
    }

    /**
     * Get the measurement for user.
     */
    public function measurement()
    {
        return $this->hasMany('App\Models\Measurement', 'service_id');
    }

    /**
     * Get active and sorted data only.
     */
    public static function getActiveOnly()
    {
        return self::where(['active' => 1])->OrderBy('sort_no', 'asc')->get();
    }

    /**
     * Get the Service.
     */
    public function packageservice()
    {
        return $this->hasMany('App\Models\PackageService', 'service_id');
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
     * Get the Location name with City Name.
     */
    public function getDurationInMinutesAttribute($value)
    {
        $duration = explode(':', $this->duration);

        return ($duration[0] * 60) + $duration[1];
    }

    /**
     * Get active and sorted Groups only.
     *
     * @param $orderBy Order By
     * @param $order Order
     * @param  \App\Models\Services  $service_id;
     * @param  \App\Models\Accounts  $account_id;
     * @return (mixed) $response
     */
    public static function getGroupsActiveOnly($orderBy = 'name', $order = 'asc', $id = false, $account_id = false)
    {
        $where = [
            'active' => 1,
            'end_node' => 0,
        ];

        /*
         * Set Service ID
         */
        if ($id) {
            $where['id'] = $id;
        }

        /*
         * Set Account ID
         */
        if ($account_id) {
            $where['account_id'] = $account_id;
        }

        return self::where($where)->OrderBy($orderBy, $order)->get();
    }

    /**
     * Get the Package Service.
     */
    public function packagebundle()
    {
        return $this->hasMany('App\Models\PackageBundles', 'service_id');
    }

    /**
     * Get Total Records
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getTotalRecords(Request $request, $account_id = false)
    {
        $where = [];
        $filters = getFilters($request->all());
        if ($account_id) {
            $where[] = [
                'account_id',
                '=',
                $account_id,
            ];
        }
        if (hasFilter($filters, 'name')) {
            $where[] = [
                'name',
                'like',
                '%'.$filters['name'].'%',
            ];
        }
        if (hasFilter($filters, 'status')) {
            $where[] = [
                'active',
                '=',
                $filters['status'],
            ];
        }
        if ($request->get('lead_status_name')) {
            $where[] = [
                'name',
                'like',
                '%'.$request->get('lead_status_name').'%',
            ];
        }
        if (count($where)) {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_records')) {
                return self::where($where)->count();
            } else {
                return self::where($where)->where('active', 1)->count();
            }
        } else {
            if (\Illuminate\Support\Facades\Gate::allows('view_inactive_records')) {
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
    public static function getRecords(Request $request, $iDisplayStart, $iDisplayLength, $account_id = false)
    {
        $where = [];

        if ($account_id) {
            $where[] = [
                'account_id',
                '=',
                $account_id,
            ];
        }

        if ($request->get('lead_status_name')) {
            $where[] = [
                'name',
                'like',
                '%'.$request->get('lead_status_name').'%',
            ];
        }

        if (count($where)) {
            return self::where($where)->limit($iDisplayLength)->offset($iDisplayStart)->get();
        } else {
            return self::limit($iDisplayLength)->offset($iDisplayStart)->get();
        }
    }

    /**
     * Get Parent Type Records
     *
     * @param  (int)  $prepend_dropdown_text [Optional] Prepend Dropdown First Row
     * @param  (int)  $account_id Current Organization's ID
     * @param  (array)  $skip_ids IDs which need to skip
     * @param  (int)  $active_records_only Get activated records only
     * @return (mixed)
     */
    public static function getParentRecords($prepend_dropdown_text, $account_id, $skip_ids = [], $active_records_only = false)
    {
        // If not an array then make it an array
        if (! is_array($skip_ids)) {
            $skip_ids = [$skip_ids];
        }

        $where = ['account_id' => $account_id, 'parent_id' => 0];

        if ($active_records_only) {
            $where['active'] = 1;
        }

        if (count($skip_ids)) {
            $records = self::where($where)
                ->whereNotIn('id', $skip_ids)
                ->get()->pluck('name', 'id');
        } else {
            $records = self::where($where)->get()->pluck('name', 'id');
        }

        if ($prepend_dropdown_text) {
            $records->prepend($prepend_dropdown_text, '');
        }

        return $records;
    }

    /**
     * Get All Records
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getAllRecordsDictionary($account_id)
    {
        return self::where(['account_id' => $account_id], [''])->get()->getDictionary();
    }

    /**
     * Get All Records
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getAllRecordsDictionaryWithoutAll($account_id)
    {
        return self::where([
            ['account_id', '=', $account_id],
            ['active', '=', '1'],
            ['slug', '!=', 'all'],
        ])->get()->getDictionary();
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
        $data['duration'] = $data['duration'] ?? '00:00';
        $data['price'] = $data['price'] ?? '0.0';
        $data['account_id'] = $account_id;
        if (! isset($data['end_node']) || ! $data['end_node']) {
            $data['end_node'] = 0;
        }
        $record = self::create($data);
        $record->update(['sort_no' => $record->id]);
        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);
        // Create Package as well
        $bundle = Bundles::create([
            'name' => $record->name,
            'price' => $record->price ?? 0.0,
            'services_price' => $record->price ?? 0.0,
            'type' => 'single',
            'total_services' => 1,
            'account_id' => 1,
            'tax_treatment_type_id' => $data['tax_treatment_type_id'],
        ]);
        BundleHasServices::create([
            'bundle_id' => $bundle->id,
            'service_id' => $record->id,
            'service_price' => $record->price ?? 0.0,
            'calculated_price' => $record->price ?? 0.0,
            'end_node' => $record->end_node,
        ]);
        BundleServicesPriceHistory::createRecord([
            'bundle_id' => $bundle->id,
            'bundle_price' => $record->price ?? 0.0,
            'service_id' => $record->id,
            'service_price' => $record->price ?? 0.0,
            'effective_from' => Carbon::now()->format('Y-m-d'),
            'created_by' => Auth::User()->id,
            'updated_by' => Auth::User()->id,
        ], $account_id);

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

        $service = Services::getData($id);

        if (! $service) {
            return false;
        }

        $dactivation_flag = false;

        if ($service->end_node) {
            $service->update(['active' => 0]);

            $dactivation_flag = true;

        } else {

            /* Create Nodes with Parents */
            $parentGroups = new NodesTree();
            $parentGroups->current_id = -1;
            $parentGroups->build($id, Auth::User()->account_id);
            $parentGroups->toList($parentGroups, -1);

            $Services = $parentGroups->nodeList;

            if (count($Services)) {
                $inactivate_array = [];
                foreach ($Services as $_Service) {
                    $inactivate_array[] = $_Service['id'];
                }

                Services::whereIn('id', $inactivate_array)->update(['active' => 0]);

                $dactivation_flag = true;

                return true;
            }

            return false;
        }

        AuditTrails::inactiveEventLogger(self::$_table, 'inactive', self::$_fillable, $id);

        if ($dactivation_flag) {
            // De-activate Bundle Service
            $bundleWithService = Bundles::join('bundle_has_services', 'bundle_has_services.bundle_id', '=', 'bundles.id')
                ->where([
                    'bundles.type' => 'single',
                    'bundle_has_services.service_id' => $id,
                ])->first();

            if ($bundleWithService) {
                Bundles::where([
                    'id' => $bundleWithService->id,
                ])->update(['active' => 0]);
            }
        }

        return true;

    }

    /**
     * active Record
     *
     *
     * @return (mixed)
     */
    public static function activeRecord($id)
    {

        $service = Services::getData($id);

        if (! $service) {
            return false;
        }
        Services::where('parent_id', $id)->update(['active' => 1]);
        $record = $service->update(['active' => 1]);

        AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

        // Activate Bundle Service
        $bundleWithService = Bundles::join('bundle_has_services', 'bundle_has_services.bundle_id', '=', 'bundles.id')
            ->where([
                'bundles.type' => 'single',
                'bundle_has_services.service_id' => $id,
            ])->first();

        if ($bundleWithService) {
            Bundles::where([
                'id' => $bundleWithService->id,
            ])->update(['active' => 1]);
        }

        return $record;

    }

    /**
     * delete Record
     *
     *
     * @return (mixed)
     */
    public static function deleteRecord($id)
    {

        $service = Services::getData($id);

        if (! $service) {
            return [
                'status' => false,
                'message' => 'Resource not found.',
            ];
        }

        // Check if child records exists or not, If exist then disallow to delete it.
        if (Services::isChildExists($id, Auth::User()->account_id)) {
            return [
                'status' => false,
                'message' => 'Child records exist, unable to delete resource.',
            ];
        }

        $record = $service->delete();

        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        // Delete Bundle Service
        $bundleWithService = Bundles::join('bundle_has_services', 'bundle_has_services.bundle_id', '=', 'bundles.id')
            ->where([
                'bundles.type' => 'single',
                'bundle_has_services.service_id' => $id,
            ])->first();

        if ($bundleWithService) {
            Bundles::where([
                'id' => $bundleWithService->id,
            ])->delete();

            BundleHasServices::where([
                'bundle_id' => $bundleWithService->id,
            ])->delete();
        }

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
        $old_data = (Services::find($id))->toArray();
        $service = Services::find($id);
        $data = $request->all();
        if ($data['parent_id'] == 0) {
            Services::where('parent_id', $id)->update(['color' => $data['color']]);
        } else {
            $parent = Services::find($data['parent_id']);
            Services::where('id', $id)->update(['color' => $parent->color]);
        }
        // Set Account ID
        $data['account_id'] = $account_id;
        if (! isset($data['end_node']) || ! $data['end_node']) {
            $data['end_node'] = 0;
        }
        if (! isset($data['complimentory']) || ! $data['complimentory']) {
            $data['complimentory'] = 0;
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
        // Update Bundle Service
        $bundleWithService = Bundles::join('bundle_has_services', 'bundle_has_services.bundle_id', '=', 'bundles.id')
            ->where([
                'bundles.account_id' => $account_id,
                'bundles.type' => 'single',
                'bundle_has_services.service_id' => $id,
            ])->first();

        if ($bundleWithService) {
            // Deactivate Previous Price History
            BundleServicesPriceHistory::where(['bundle_id' => $bundleWithService->id])
                ->whereNull('effective_to')
                ->update([
                    'effective_to' => Carbon::now()->format('Y-m-d'),
                    'active' => 0,
                    'updated_by' => Auth::User()->id,
                ]);

            Bundles::where([
                'id' => $bundleWithService->id,
            ])->update([
                'name' => $record->name,
                'price' => $record->price,
                'services_price' => $record->price,
                'tax_treatment_type_id' => $data['tax_treatment_type_id'],
            ]);

            BundleHasServices::where([
                'bundle_id' => $bundleWithService->id,
            ])->update([
                'service_price' => $record->price,
                'calculated_price' => $record->price,
                'end_node' => $record->end_node,
            ]);

            BundleServicesPriceHistory::createRecord([
                'bundle_id' => $bundleWithService->id,
                'bundle_price' => $record->price,
                'service_id' => $record->id,
                'service_price' => $record->price,
                'effective_from' => Carbon::now()->format('Y-m-d'),
                'created_by' => Auth::User()->id,
                'updated_by' => Auth::User()->id,
            ], $account_id);
        }

        return $record;
    }

    /**
     * Check if child records exist
     *
     * @param  (int)  $id
     * @return (boolean)
     * @deprecated Use ServiceService::checkDependencies() instead
     */
    public static function isChildExists($id, $account_id)
    {
        $invoicestatus = InvoiceStatuses::where('slug', '=', 'paid')->first();
        if (
            self::where(['parent_id' => $id, 'account_id' => $account_id])->count() ||
            PackageService::where(['service_id' => $id])->count() ||
            DiscountHasLocations::where(['service_id' => $id])->count() ||
            DoctorHasLocations::where('is_allocated',1)->where(['service_id' => $id])->count() ||
            ServiceHasLocations::where(['service_id' => $id])->count() ||
            Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')->where(['invoice_details.service_id' => $id], ['invoices.invoice_status_id' => $invoicestatus->id ?? 0])->count() ||
            Appointments::where(['service_id' => $id])->count() ||
            StaffTargetServices::where(['service_id' => $id])->count()
        ) {
            return true;
        }

        return false;
    }

    /**
     * Get All services Child
     *
     * @return (mixed)
     */
    public static function getServices()
    {
        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::User()->account_id, true, true);
        $parentGroups->toList($parentGroups, -1);

        $Services[] = $parentGroups->nodeList;

        $result = [];
        if ($Services) {
            foreach ($Services as $val) {
                foreach ($val as $key => $val2) {
                    if ($key > 0) {
                        $result[] = $val2;
                    }
                }
            }
        } else {
            $result = [];
        }

        return $result;
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function parent()
    {
        return $this->hasOne(self::class, 'id', 'parent_id');
    }
    public function voucherHasLocations()
    {
        return $this->hasMany(VoucherHasLocation::class);
    }

    // Direct relationship to vouchers through voucher_has_locations
    public function vouchers()
    {
        return $this->hasManyThrough(Voucher::class, VoucherHasLocation::class, 'service_id', 'id', 'id', 'voucher_id');
    }
    public static function getTreeStructure()
    {
        return self::where('parent_id', 0)
                  ->with('children')
                  ->orderBy('name')
                  ->get();
    }
}
