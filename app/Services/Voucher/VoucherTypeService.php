<?php

declare(strict_types=1);

namespace App\Services\Voucher;

use App\Helpers\Filters;
use App\Helpers\NodesTree;
use App\Helpers\Widgets\LocationsWidget;
use App\Helpers\Widgets\ServiceWidget;
use App\Models\AuditTrails;
use App\Models\DiscountHasLocations;
use App\Models\Discounts;
use App\Models\Locations;
use App\Models\UserVouchers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class VoucherTypeService
{
    private const FILTER_KEY = 'vouchers';

    private const DISCOUNT_TYPE = 'voucher';

    private const AUDIT_TABLE = 'discounts';

    private const AUDIT_FILLABLE = [
        'name', 'type', 'amount', 'discount_type',
        'pre_days', 'post_days', 'start', 'end',
        'active', 'slug', 'customer_type_id',
    ];

    // ── CRUD Operations ─────────────────────────────────

    public function getCreateData(): array
    {
        return [
            'discount_types' => config('constants.discount_types'),
            'discount_groups' => config('constants.discount_groups'),
            'amount_types' => config('constants.amount_types'),
        ];
    }

    public function store(array $data): Discounts
    {
        $data['account_id'] = Auth::user()->account_id;
        $data['active'] ??= '0';
        $data['type'] = 'Fixed';
        $data['discount_type'] = self::DISCOUNT_TYPE;
        // Vouchers carry their amount on the patient assignment row
        // (`user_vouchers.amount`), not on the type. The shared
        // `discounts.amount` column is NOT NULL with no default, so
        // stamp 0 when the voucher form omits it.
        $data['amount'] ??= 0;

        $record = Discounts::create($data);

        if (isset($data['roles'])) {
            $record->roles()->sync($data['roles']);
        }

        AuditTrails::addEventLogger(
            self::AUDIT_TABLE,
            'create',
            $data,
            self::AUDIT_FILLABLE,
            $record,
        );

        return $record;
    }

    public function find(int $id): ?Discounts
    {
        return Discounts::where('account_id', Auth::user()->account_id)->find($id);
    }

    public function getEditData(int $id): ?array
    {
        $voucher = $this->find($id);

        if (! $voucher) {
            return null;
        }

        $voucherArray = $voucher->toArray();

        if ($voucherArray['start']) {
            $voucherArray['start'] = $voucher->dateFormat($voucher->getRawOriginal('start'), 'Y-m-d');
        }
        if ($voucherArray['end']) {
            $voucherArray['end'] = $voucher->dateFormat($voucher->getRawOriginal('end'), 'Y-m-d');
        }

        $services = ServiceWidget::generateServiceArrayDiscount($id, Auth::user()->account_id);
        $locations = Locations::getActiveSorted();
        $isAssigned = UserVouchers::where('voucher_id', $id)->exists();

        return [
            'voucher' => $voucherArray,
            'locations' => $locations,
            'services' => $services,
            'is_assigned' => $isAssigned,
        ];
    }

    public function update(array $data, int $id): array
    {
        $isAssigned = UserVouchers::where('voucher_id', $id)->exists();

        if ($isAssigned) {
            $currentVoucher = Discounts::find($id);

            if ($currentVoucher && ($data['name'] ?? null) !== $currentVoucher->name) {
                return [
                    'success' => false,
                    'message' => 'Cannot change voucher name as it is already assigned to patients.',
                ];
            }

            unset($data['name']);
        }

        $data['active'] ??= '0';

        $record = Discounts::findOrFail($id);
        $oldData = $record->toArray();
        $record->update($data);

        if (isset($data['roles'])) {
            $record->roles()->sync($data['roles']);
        }

        AuditTrails::EditEventLogger(
            self::AUDIT_TABLE,
            'edit',
            $data,
            self::AUDIT_FILLABLE,
            $oldData,
            $id,
        );

        return [
            'success' => true,
            'message' => 'Record has been updated successfully.',
        ];
    }

    public function delete(int $id): array
    {
        $isAssigned = UserVouchers::where('voucher_id', $id)->exists();

        if ($isAssigned) {
            return [
                'success' => false,
                'message' => 'Cannot delete voucher type as it is already assigned to patients.',
            ];
        }

        $voucher = $this->find($id);

        if (! $voucher) {
            return [
                'success' => false,
                'message' => 'Resource not found.',
            ];
        }

        $voucher->delete();

        AuditTrails::deleteEventLogger(
            self::AUDIT_TABLE,
            'delete',
            self::AUDIT_FILLABLE,
            $id,
        );

        return [
            'success' => true,
            'message' => 'Record has been deleted successfully.',
        ];
    }

    // ── Status Toggle ───────────────────────────────────

    public function toggleStatus(int $id, int $status): array
    {
        $voucher = $this->find($id);

        if (! $voucher) {
            return [
                'success' => false,
                'message' => 'Resource not found.',
            ];
        }

        $voucher->update(['active' => $status]);

        $action = $status === 1 ? 'active' : 'inactive';
        $logMethod = $status === 1 ? 'activeEventLogger' : 'InactiveEventLogger';
        AuditTrails::$logMethod(self::AUDIT_TABLE, $action, self::AUDIT_FILLABLE, $id);

        return [
            'success' => true,
            'message' => 'Status has been changed successfully.',
        ];
    }

    // ── Datatable ───────────────────────────────────────

    public function getDatatable(array $params): array
    {
        $filters = $params['filters'] ?? [];
        $applyFilter = $params['apply_filter'] ?? false;
        $request = $params['request'];

        $where = $this->buildWhereConditions($filters, $applyFilter);

        $baseQuery = Discounts::where('discount_type', self::DISCOUNT_TYPE);

        if (! empty($where)) {
            $baseQuery->where($where);
        }

        $totalRecords = $baseQuery->count();

        [$iDisplayLength, $iDisplayStart, $pages, $page] = getPaginationElement($request, $totalRecords);

        $vouchers = (clone $baseQuery)
            ->orderByDesc('created_at')
            ->offset($iDisplayStart)
            ->limit($iDisplayLength)
            ->get();

        return [
            'data' => $vouchers,
            'total' => $totalRecords,
            'page' => $page,
            'pages' => $pages,
            'perpage' => $iDisplayLength,
        ];
    }

    // ── Location/Service Allocation ─────────────────────

    public function getLocationAllocations(int $id): array
    {
        $discount = Discounts::findOrFail($id);

        $locations = LocationsWidget::generateDropDownArray(Auth::user()->account_id);

        $allocations = DiscountHasLocations::with(['service', 'location.city'])
            ->where('discount_id', $discount->id)
            ->get();

        return [
            'discount' => $discount,
            'location' => $locations,
            'discount_has_location' => $allocations,
        ];
    }

    public function getServicesForLocation(mixed $request): array
    {
        $services = ServiceWidget::generateServiceArrayConsultancy($request, Auth::user()->account_id);

        return [
            'services' => $services,
            'locaiton_id_1' => $request->id,
        ];
    }

    public function getDiscountServices(mixed $request): array
    {
        $services = ServiceWidget::generateServiceArrayDiscount($request, Auth::user()->account_id);

        return [
            'services' => $services,
            'locaiton_id_1' => $request->id,
        ];
    }

    public function saveServiceAllocation(int $discountId, int $locationId, int $serviceId): array
    {
        $exists = DiscountHasLocations::where([
            ['location_id', '=', $locationId],
            ['service_id', '=', $serviceId],
            ['discount_id', '=', $discountId],
        ])->exists();

        if ($exists) {
            return [
                'success' => false,
                'message' => 'Duplicate record found.',
            ];
        }

        $record = DiscountHasLocations::create([
            'discount_id' => $discountId,
            'location_id' => $locationId,
            'service_id' => $serviceId,
        ]);

        $locationName = $record->location?->city?->name.'-'.$record->location?->name;
        $serviceName = $record->service?->name;

        return [
            'success' => true,
            'message' => 'Record Saved successfully.',
            'data' => [
                'record' => $record,
                'record_locaiton_name' => $locationName,
                'record_service_name' => $serviceName,
            ],
        ];
    }

    public function deleteServiceAllocation(int $id): void
    {
        DiscountHasLocations::findOrFail($id)->delete();
    }

    // ── Assign to Patient ───────────────────────────────

    public function assignToPatient(int $voucherId, int $patientId, float $amount): array
    {
        $voucherType = Discounts::find($voucherId);

        if (! $voucherType) {
            return [
                'success' => false,
                'message' => 'Voucher type not found.',
            ];
        }

        if (! $voucherType->active) {
            return [
                'success' => false,
                'message' => 'Cannot assign inactive voucher type to patient.',
            ];
        }

        DB::transaction(function () use ($patientId, $voucherId, $amount): void {
            UserVouchers::create([
                'user_id' => $patientId,
                'voucher_id' => $voucherId,
                'amount' => $amount,
                'total_amount' => $amount,
            ]);
        });

        return [
            'success' => true,
            'message' => 'Voucher assigned to patient successfully.',
        ];
    }

    // ── Listing ─────────────────────────────────────────

    public function getListing(?string $search = null, int $limit = 50): array
    {
        return Discounts::where('discount_type', self::DISCOUNT_TYPE)
            ->when($search, fn ($q, $s) => $q->where('name', 'like', '%'.$s.'%'))
            ->orderBy('name')
            ->limit($limit)
            ->pluck('name', 'id')
            ->toArray();
    }

    // ── Filter Helpers ──────────────────────────────────

    public function getFilterValues(): array
    {
        $parentGroups = new NodesTree;
        $parentGroups->current_id = -1;
        $parentGroups->build(0, Auth::user()->account_id);
        $parentGroups->toList($parentGroups, -1);

        return [
            'services' => $parentGroups->nodeList,
            'locations' => Locations::getlocation(),
            'status' => config('constants.status'),
        ];
    }

    public function getActiveFilters(): array
    {
        return Filters::all(Auth::id(), self::FILTER_KEY);
    }

    public function getPermissions(): array
    {
        return [
            'edit'     => Gate::allows('voucher_types.edit'),
            'delete'   => Gate::allows('voucher_types.destroy'),
            'active'   => Gate::allows('voucher_types.activate'),
            'inactive' => Gate::allows('voucher_types.deactivate'),
            'create'   => Gate::allows('voucher_types.create'),
            'allocate' => Gate::allows('voucher_types.allocate'),
            'assign'   => Gate::allows('voucher_types.assign'),
        ];
    }

    // ── Private Helpers ─────────────────────────────────

    private function buildWhereConditions(array $filters, bool $applyFilter): array
    {
        $where = [];
        $userId = (int) Auth::id();

        $this->addAccountFilter($where, $filters, $applyFilter);
        $this->addFilter($where, $filters, 'name', 'name', 'like', $userId, $applyFilter, wrapLike: true);
        $this->addDateFilter($where, $filters, 'created_from', 'created_at', '>=', $userId, $applyFilter, suffix: ' 00:00:00');
        $this->addDateFilter($where, $filters, 'created_to', 'created_at', '<=', $userId, $applyFilter, suffix: ' 23:59:59');
        $this->addFilter($where, $filters, 'startdate', 'start', '>=', $userId, $applyFilter);
        $this->addFilter($where, $filters, 'enddate', 'end', '<=', $userId, $applyFilter);
        $this->addStatusFilter($where, $filters, $userId, $applyFilter);

        return $where;
    }

    private function addAccountFilter(array &$where, array $filters, bool $applyFilter): void
    {
        $accountId = Auth::user()->account_id;

        if ($accountId && $accountId !== '') {
            $where[] = ['account_id', '=', $accountId];
            Filters::put(Auth::id(), self::FILTER_KEY, 'account_id', $accountId);

            return;
        }

        if ($applyFilter) {
            Filters::forget(Auth::id(), self::FILTER_KEY, 'account_id');
        } elseif ($stored = Filters::get(Auth::id(), self::FILTER_KEY, 'account_id')) {
            $where[] = ['account_id', '=', $stored];
        }
    }

    private function addFilter(
        array &$where,
        array $filters,
        string $paramKey,
        string $column,
        string $operator,
        int $userId,
        bool $applyFilter,
        bool $wrapLike = false,
    ): void {
        if (hasFilter($filters, $paramKey)) {
            $value = $wrapLike ? '%'.$filters[$paramKey].'%' : $filters[$paramKey];
            $where[] = [$column, $operator, $value];
            Filters::put($userId, self::FILTER_KEY, $paramKey, $filters[$paramKey]);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, $paramKey);
        } elseif ($stored = Filters::get($userId, self::FILTER_KEY, $paramKey)) {
            $value = $wrapLike ? '%'.$stored.'%' : $stored;
            $where[] = [$column, $operator, $value];
        }
    }

    private function addDateFilter(
        array &$where,
        array $filters,
        string $paramKey,
        string $column,
        string $operator,
        int $userId,
        bool $applyFilter,
        string $suffix = '',
    ): void {
        if (hasFilter($filters, $paramKey)) {
            $where[] = [$column, $operator, $filters[$paramKey].$suffix];
            Filters::put($userId, self::FILTER_KEY, $paramKey, $filters[$paramKey].$suffix);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, $paramKey);
        } elseif ($stored = Filters::get($userId, self::FILTER_KEY, $paramKey)) {
            $where[] = [$column, $operator, $stored];
        }
    }

    private function addStatusFilter(array &$where, array $filters, int $userId, bool $applyFilter): void
    {
        if (hasFilter($filters, 'status')) {
            $where[] = ['active', '=', $filters['status']];
            Filters::put($userId, self::FILTER_KEY, 'status', $filters['status']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'status');
        } else {
            $stored = Filters::get($userId, self::FILTER_KEY, 'status');

            if ($stored !== null && ($stored == 0 || $stored == 1)) {
                $where[] = ['active', '=', $stored];
            }
        }
    }
}
