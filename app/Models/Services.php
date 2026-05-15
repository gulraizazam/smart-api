<?php

declare(strict_types=1);

namespace App\Models;

use App\Helpers\NodesTree;
use App\Helpers\ServiceHelper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class Services extends BaseModel
{
    use SoftDeletes;

    protected $table = 'services';

    protected $fillable = [
        'name',
        'slug',
        'end_node',
        'complimentory',
        'account_id',
        'active',
        'tax_treatment_type_id',
        'created_at',
        'updated_at',
        'parent_id',
        'duration',
        'price',
        'description',
        'color',
        'sort_no',
        'sort_number',
    ];

    protected function casts(): array
    {
        return [
            'parent_id'             => 'integer',
            'end_node'              => 'integer',
            'complimentory'         => 'integer',
            'active'                => 'integer',
            'tax_treatment_type_id' => 'integer',
            'price'                 => 'float',
            'account_id'            => 'integer',
            'sort_no'               => 'integer',
            'sort_number'           => 'integer',
        ];
    }

    /**
     * Persistence-seam coercions for paths that bypass
     * ServiceHelper::prepareServiceData() — direct $model->save() calls
     * and legacy admin controllers. Two invariants:
     *
     *   1. `parent_id` 0 / '' must land as NULL on every write. The
     *      self-ref FK `fk_services_parent_id` rejects 0 (no
     *      `services.id = 0` row) and MariaDB rejects '' as an integer
     *      outright.
     *   2. `tax_treatment_type_id` is NOT NULL with no default in
     *      production. Default to ServiceHelper::DEFAULT_TAX_TREATMENT_TYPE
     *      on create when the caller hasn't supplied one — don't rewrite
     *      legitimate values on update (factories deliberately seed id=1).
     */
    protected static function booted(): void
    {
        static::saving(function (self $service): void {
            $parentId = $service->getAttribute('parent_id');

            if ($parentId === '' || $parentId === 0 || $parentId === '0') {
                $service->setAttribute('parent_id', null);
            }
        });

        static::creating(function (self $service): void {
            $taxId = $service->getAttribute('tax_treatment_type_id');

            if ($taxId === null || $taxId === '' || (int) $taxId === 0) {
                $service->setAttribute(
                    'tax_treatment_type_id',
                    ServiceHelper::DEFAULT_TAX_TREATMENT_TYPE,
                );
            }
        });
    }

    public function scopeRootServices(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('parent_id')->orWhere('parent_id', 0);
        });
    }

    /**
     * @deprecated Use ServiceService fillable list. Kept for legacy Admin controller.
     */
    protected static string $_table = 'services';

    /**
     * @deprecated Use ServiceService fillable list. Kept for legacy Admin controller.
     */
    protected static array $_fillable = [
        'name', 'slug', 'end_node', 'complimentory', 'active',
        'tax_treatment_type_id', 'parent_id', 'duration', 'price', 'description', 'color',
    ];

    // ──────────────────────────────────────────────
    // Scopes
    // ──────────────────────────────────────────────

    public function scopeIsActive(Builder $query, int $status = 1): Builder
    {
        return $query->where('active', $status);
    }

    public function scopeParentsOnly(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->whereNull('parent_id')->orWhere('parent_id', 0);
        })->where('slug', '!=', 'all');
    }

    public function scopeForAccount(Builder $query, int $accountId): Builder
    {
        return $query->where('account_id', $accountId);
    }

    // ──────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointments::class, 'service_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Leads::class, 'service_id');
    }

    public function childlead(): HasMany
    {
        return $this->hasMany(Leads::class, 'child_service_id');
    }

    public function measurement(): HasMany
    {
        return $this->hasMany(Measurement::class, 'service_id');
    }

    public function packageservice(): HasMany
    {
        return $this->hasMany(PackageService::class, 'service_id');
    }

    public function packagebundle(): HasMany
    {
        return $this->hasMany(PackageBundles::class, 'service_id');
    }

    public function doctorhaslocation(): HasMany
    {
        return $this->hasMany(DoctorHasLocations::class, 'service_id');
    }

    public function discounthaslocation(): HasMany
    {
        return $this->hasMany(DiscountHasLocations::class, 'service_id');
    }

    public function audit_field_before(): HasMany
    {
        return $this->hasMany(AuditTrailChanges::class, 'field_before');
    }

    public function audit_field_after(): HasMany
    {
        return $this->hasMany(AuditTrailChanges::class, 'field_after');
    }

    // ──────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────

    protected function durationInMinutes(): Attribute
    {
        return Attribute::make(
            get: function (): int {
                $parts = explode(':', (string) $this->duration);

                return ((int) ($parts[0] ?? 0) * 60) + (int) ($parts[1] ?? 0);
            },
        );
    }

    protected function isParent(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->parent_id === null || $this->parent_id === 0,
        );
    }

    // ──────────────────────────────────────────────
    // Query helpers (non-deprecated)
    // ──────────────────────────────────────────────

    public static function getActiveOnly(): Collection
    {
        return self::where('active', 1)->orderBy('sort_no', 'asc')->get();
    }

    public static function getTreeStructure(): Collection
    {
        return self::where(function ($q) {
                $q->whereNull('parent_id')->orWhere('parent_id', 0);
            })
            ->with('children')
            ->orderBy('name')
            ->get();
    }

    // ──────────────────────────────────────────────
    // Legacy static methods (kept for Admin controller & other modules)
    // ──────────────────────────────────────────────

    /**
     * @deprecated Use ServiceHelper::getParentServices() or ServiceService methods.
     */
    public static function getGroupsActiveOnly(
        string $orderBy = 'name',
        string $order = 'asc',
        int|false $id = false,
        int|false $account_id = false,
    ): Collection {
        $where = ['active' => 1, 'end_node' => 0];

        if ($id) {
            $where['id'] = $id;
        }
        if ($account_id) {
            $where['account_id'] = $account_id;
        }

        return self::where($where)->orderBy($orderBy, $order)->get();
    }

    /**
     * @deprecated Use ServiceService::getTotalRecords().
     */
    public static function getTotalRecords(Request $request, int|false $account_id = false): int
    {
        $where = [];
        $filters = getFilters($request->all());

        if ($account_id) {
            $where[] = ['account_id', '=', $account_id];
        }
        if (hasFilter($filters, 'name')) {
            $where[] = ['name', 'like', '%' . $filters['name'] . '%'];
        }
        if (hasFilter($filters, 'status')) {
            $where[] = ['active', '=', $filters['status']];
        }
        if ($request->get('lead_status_name')) {
            $where[] = ['name', 'like', '%' . $request->get('lead_status_name') . '%'];
        }

        $query = count($where) ? self::where($where) : self::query();

        if (! Gate::allows('view_inactive_records')) {
            $query->where('active', 1);
        }

        return $query->count();
    }

    /**
     * @deprecated Use ServiceService::getServicesList().
     */
    public static function getRecords(
        Request $request,
        int $iDisplayStart,
        int $iDisplayLength,
        int|false $account_id = false,
    ): Collection {
        $where = [];

        if ($account_id) {
            $where[] = ['account_id', '=', $account_id];
        }
        if ($request->get('lead_status_name')) {
            $where[] = ['name', 'like', '%' . $request->get('lead_status_name') . '%'];
        }

        $query = count($where) ? self::where($where) : self::query();

        return $query->limit($iDisplayLength)->offset($iDisplayStart)->get();
    }

    /**
     * @deprecated Use ServiceHelper::getParentServices().
     */
    public static function getParentRecords(
        string|false $prepend_dropdown_text,
        int $account_id,
        array|int $skip_ids = [],
        bool $active_records_only = false,
    ): \Illuminate\Support\Collection {
        if (! is_array($skip_ids)) {
            $skip_ids = [$skip_ids];
        }

        $where = ['account_id' => $account_id];

        if ($active_records_only) {
            $where['active'] = 1;
        }

        $query = self::where($where)->where(function ($q) {
            $q->whereNull('parent_id')->orWhere('parent_id', 0);
        });
        if (count($skip_ids)) {
            $query->whereNotIn('id', $skip_ids);
        }

        $records = $query->pluck('name', 'id');

        if ($prepend_dropdown_text) {
            $records->prepend($prepend_dropdown_text, '');
        }

        return $records;
    }

    /**
     * @deprecated Access via query builder directly.
     */
    public static function getAllRecordsDictionary(int $account_id): array
    {
        return self::where('account_id', $account_id)->get()->getDictionary();
    }

    /**
     * @deprecated Access via query builder directly.
     */
    public static function getAllRecordsDictionaryWithoutAll(int $account_id): \Illuminate\Support\Collection
    {
        return self::where([
            ['account_id', '=', $account_id],
            ['active', '=', 1],
            ['slug', '!=', 'all'],
        ])->get()->keyBy('id');
    }

    /**
     * @deprecated Use ServiceService::createService().
     */
    public static function createRecord(Request $request, int $account_id): self
    {
        $data = $request->all();
        $data['duration'] = $data['duration'] ?? '00:00';
        $data['price'] = $data['price'] ?? '0.0';
        $data['account_id'] = $account_id;
        $data['end_node'] = (! isset($data['end_node']) || ! $data['end_node']) ? 0 : $data['end_node'];

        $record = self::create($data);
        $record->update(['sort_no' => $record->id]);

        AuditTrails::addEventLogger(self::$_table, 'create', $data, self::$_fillable, $record);

        $bundle = Bundles::create([
            'name'                  => $record->name,
            'price'                 => $record->price ?? 0.0,
            'services_price'        => $record->price ?? 0.0,
            'type'                  => 'single',
            'total_services'        => 1,
            'account_id'            => 1,
            'tax_treatment_type_id' => $data['tax_treatment_type_id'],
        ]);

        BundleHasServices::create([
            'bundle_id'        => $bundle->id,
            'service_id'       => $record->id,
            'service_price'    => $record->price ?? 0.0,
            'calculated_price' => $record->price ?? 0.0,
            'end_node'         => $record->end_node,
        ]);

        BundleServicesPriceHistory::createRecord([
            'bundle_id'      => $bundle->id,
            'bundle_price'   => $record->price ?? 0.0,
            'service_id'     => $record->id,
            'service_price'  => $record->price ?? 0.0,
            'effective_from' => Carbon::now()->format('Y-m-d'),
            'created_by'     => Auth::user()->id,
            'updated_by'     => Auth::user()->id,
        ], $account_id);

        return $record;
    }

    /**
     * @deprecated Use ServiceService::updateService().
     */
    public static function updateRecord(int $id, Request $request, int $account_id): ?self
    {
        $old_data = (self::find($id))->toArray();
        $data = $request->all();

        if ($data['parent_id'] == 0) {
            self::where('parent_id', $id)->update(['color' => $data['color']]);
        } else {
            $parent = self::find($data['parent_id']);
            self::where('id', $id)->update(['color' => $parent->color]);
        }

        $data['account_id'] = $account_id;
        $data['end_node'] = (! isset($data['end_node']) || ! $data['end_node']) ? 0 : $data['end_node'];
        $data['complimentory'] = (! isset($data['complimentory']) || ! $data['complimentory']) ? 0 : $data['complimentory'];

        $record = self::where(['id' => $id, 'account_id' => $account_id])->first();

        if (! $record) {
            return null;
        }

        $record->update($data);
        AuditTrails::EditEventLogger(self::$_table, 'edit', $data, self::$_fillable, $old_data, $id);

        $bundleWithService = Bundles::join('bundle_has_services', 'bundle_has_services.bundle_id', '=', 'bundles.id')
            ->where([
                'bundles.account_id'            => $account_id,
                'bundles.type'                  => 'single',
                'bundle_has_services.service_id' => $id,
            ])->select('bundles.id')->first();

        if ($bundleWithService) {
            BundleServicesPriceHistory::where(['bundle_id' => $bundleWithService->id])
                ->whereNull('effective_to')
                ->update([
                    'effective_to' => Carbon::now()->format('Y-m-d'),
                    'active'       => 0,
                    'updated_by'   => Auth::user()->id,
                ]);

            Bundles::where('id', $bundleWithService->id)->update([
                'name'                  => $record->name,
                'price'                 => $record->price,
                'services_price'        => $record->price,
                'tax_treatment_type_id' => $data['tax_treatment_type_id'],
            ]);

            BundleHasServices::where('bundle_id', $bundleWithService->id)->update([
                'service_price'    => $record->price,
                'calculated_price' => $record->price,
                'end_node'         => $record->end_node,
            ]);

            BundleServicesPriceHistory::createRecord([
                'bundle_id'      => $bundleWithService->id,
                'bundle_price'   => $record->price,
                'service_id'     => $record->id,
                'service_price'  => $record->price,
                'effective_from' => Carbon::now()->format('Y-m-d'),
                'created_by'     => Auth::user()->id,
                'updated_by'     => Auth::user()->id,
            ], $account_id);
        }

        return $record;
    }

    /**
     * @deprecated Use ServiceService::deleteService().
     */
    public static function deleteRecord(int $id): array
    {
        $service = self::getData($id);

        if (! $service) {
            return ['status' => false, 'message' => 'Resource not found.'];
        }

        if (self::isChildExists($id, Auth::user()->account_id)) {
            return ['status' => false, 'message' => 'Child records exist, unable to delete resource.'];
        }

        $service->delete();
        AuditTrails::deleteEventLogger(self::$_table, 'delete', self::$_fillable, $id);

        $bundleWithService = Bundles::join('bundle_has_services', 'bundle_has_services.bundle_id', '=', 'bundles.id')
            ->where(['bundles.type' => 'single', 'bundle_has_services.service_id' => $id])
            ->select('bundles.id')->first();

        if ($bundleWithService) {
            Bundles::where('id', $bundleWithService->id)->delete();
            BundleHasServices::where('bundle_id', $bundleWithService->id)->delete();
        }

        return ['status' => true, 'message' => 'Record has been deleted successfully.'];
    }

    /**
     * @deprecated Use ServiceService::deactivateService().
     */
    public static function inactiveRecord(int $id): bool
    {
        $service = self::getData($id);

        if (! $service) {
            return false;
        }

        $dactivation_flag = false;

        if ($service->end_node) {
            $service->update(['active' => 0]);
            $dactivation_flag = true;
        } else {
            $parentGroups = new NodesTree();
            $parentGroups->current_id = -1;
            $parentGroups->build($id, Auth::user()->account_id);
            $parentGroups->toList($parentGroups, -1);

            $Services = $parentGroups->nodeList;

            if (count($Services)) {
                $inactivate_array = array_column($Services, 'id');
                self::whereIn('id', $inactivate_array)->update(['active' => 0]);
                $dactivation_flag = true;
            } else {
                return false;
            }
        }

        AuditTrails::inactiveEventLogger(self::$_table, 'inactive', self::$_fillable, $id);

        if ($dactivation_flag) {
            $bundleWithService = Bundles::join('bundle_has_services', 'bundle_has_services.bundle_id', '=', 'bundles.id')
                ->where(['bundles.type' => 'single', 'bundle_has_services.service_id' => $id])
                ->select('bundles.id')->first();

            if ($bundleWithService) {
                Bundles::where('id', $bundleWithService->id)->update(['active' => 0]);
            }
        }

        return true;
    }

    /**
     * @deprecated Use ServiceService::activateService().
     */
    public static function activeRecord(int $id): bool
    {
        $service = self::getData($id);

        if (! $service) {
            return false;
        }

        self::where('parent_id', $id)->update(['active' => 1]);
        $service->update(['active' => 1]);

        AuditTrails::activeEventLogger(self::$_table, 'active', self::$_fillable, $id);

        $bundleWithService = Bundles::join('bundle_has_services', 'bundle_has_services.bundle_id', '=', 'bundles.id')
            ->where(['bundles.type' => 'single', 'bundle_has_services.service_id' => $id])
            ->select('bundles.id')->first();

        if ($bundleWithService) {
            Bundles::where('id', $bundleWithService->id)->update(['active' => 1]);
        }

        return true;
    }

    /**
     * @deprecated Use ServiceService::checkDependencies().
     */
    public static function isChildExists(int $id, int $account_id): bool
    {
        $invoicestatus = InvoiceStatuses::where('slug', 'paid')->first();

        return self::where(['parent_id' => $id, 'account_id' => $account_id])->exists()
            || PackageService::where('service_id', $id)->exists()
            || DiscountHasLocations::where('service_id', $id)->exists()
            || DoctorHasLocations::where('is_allocated', 1)->where('service_id', $id)->exists()
            || ServiceHasLocations::where('service_id', $id)->exists()
            || Invoices::join('invoice_details', 'invoices.id', '=', 'invoice_details.invoice_id')
                ->where('invoice_details.service_id', $id)
                ->where('invoices.invoice_status_id', $invoicestatus->id ?? 0)
                ->exists()
            || Appointments::where('service_id', $id)->exists()
            || StaffTargetServices::where('service_id', $id)->exists();
    }

    /**
     * @deprecated Use ServiceService::getServicesForSort() or tree queries.
     */
    public static function getServices(): array
    {
        $parentGroups = new NodesTree();
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::user()->account_id, true, true);
        $parentGroups->toList($parentGroups, -1);

        $Services = [$parentGroups->nodeList];
        $result = [];

        foreach ($Services as $val) {
            foreach ($val as $key => $val2) {
                if ($key > 0) {
                    $result[] = $val2;
                }
            }
        }

        return $result;
    }
}
