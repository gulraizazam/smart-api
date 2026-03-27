<?php

declare(strict_types=1);

namespace App\Services\UserManagement;

use App\Helpers\ActivityLogger;
use App\Helpers\Filters;
use App\Http\Resources\User\UserVoucherResource;
use App\Models\Discounts;
use App\Models\PackageBundles;
use App\Models\Packages;
use App\Models\PackageService;
use App\Models\PackageVouchers;
use App\Models\User;
use App\Models\UserVouchers;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class UserVoucherService
{
    private const FILTER_KEY = 'vouchers';

    public function getDatatableCount(array $params): int
    {
        return $this->buildDatatableQuery($params)->count();
    }

    public function getDatatableData(array $params): array
    {
        $vouchers = $this->buildDatatableQuery($params)
            ->with(['user', 'voucher'])
            ->orderByDesc('created_at')
            ->offset($params['offset'] ?? 0)
            ->limit($params['limit'] ?? 30)
            ->get();

        $usedVouchersLookup = $this->buildUsedVouchersLookup($vouchers);
        UserVoucherResource::$usedVouchersLookup = $usedVouchersLookup;

        return [
            'data' => UserVoucherResource::collection($vouchers),
            'total' => $vouchers->count(),
        ];
    }

    private function buildDatatableQuery(array $params): \Illuminate\Database\Eloquent\Builder
    {
        $userId = (int) Auth::id();
        $applyFilter = $params['apply_filter'] ?? false;

        $where = $this->buildWhereConditions($params, $userId, $applyFilter);

        return UserVouchers::query()
            ->when(count($where) > 0, fn ($q) => $q->where($where));
    }

    public function store(array $data): UserVouchers
    {
        $voucher = UserVouchers::create([
            'user_id' => $data['patient_id'],
            'voucher_id' => $data['voucher_id'],
            'amount' => $data['amount'],
            'total_amount' => $data['amount'],
        ]);

        $patient = User::find($data['patient_id']);
        $discount = Discounts::find($data['voucher_id']);
        ActivityLogger::logVoucherAssigned($voucher, $patient, $discount);

        return $voucher;
    }

    public function find(int $id): ?UserVouchers
    {
        return UserVouchers::with(['user', 'voucher'])->find($id);
    }

    public function update(int $id, array $data): array
    {
        $voucher = UserVouchers::findOrFail($id);

        if ($this->isUsedInPackages($voucher)) {
            return [
                'success' => false,
                'message' => 'This voucher cannot be updated as it is already applied to services.',
            ];
        }

        $oldAmount = $voucher->total_amount;

        $voucher->update([
            'total_amount' => $data['total_amount'],
            'amount' => $data['total_amount'],
        ]);

        $patient = User::find($voucher->user_id);
        $discount = Discounts::find($voucher->voucher_id);
        ActivityLogger::logVoucherUpdated($voucher, $patient, $discount, $oldAmount, $data['total_amount']);

        return [
            'success' => true,
            'message' => 'Voucher updated successfully.',
            'voucher' => $voucher,
        ];
    }

    public function delete(int $id): array
    {
        $voucher = UserVouchers::findOrFail($id);

        if ($this->isUsedInPackages($voucher)) {
            return [
                'success' => false,
                'message' => 'This voucher cannot be deleted as it is already applied to services.',
            ];
        }

        $voucher->delete();

        return [
            'success' => true,
            'message' => 'Voucher deleted successfully.',
        ];
    }

    public function getEditData(int $id): ?array
    {
        $voucher = $this->find($id);

        if (!$voucher) {
            return null;
        }

        if ($this->isUsedInPackages($voucher)) {
            return ['restricted' => true];
        }

        return [
            'voucher' => $voucher,
            'patient_name' => $voucher->user?->name ?? 'N/A',
            'voucher_type_name' => $voucher->voucher?->name ?? 'N/A',
        ];
    }

    public function getVoucherUsageData(int $id): array
    {
        $userVoucher = $this->find($id);

        if (!$userVoucher) {
            return [];
        }

        $packageVouchers = PackageVouchers::where('user_id', $userVoucher->user_id)
            ->where('voucher_id', $userVoucher->voucher_id)
            ->select('package_random_id', 'main_service_id')
            ->distinct()
            ->get();

        if ($packageVouchers->isEmpty()) {
            return [];
        }

        $voucherName = $userVoucher->voucher?->name;
        $randomIds = $packageVouchers->pluck('package_random_id')->unique()->all();

        // Batch load all packages by random_id to avoid N+1
        $packagesLookup = Packages::whereIn('random_id', $randomIds)
            ->get()
            ->keyBy('random_id');

        // Batch load all matching bundles with their relationships
        $allBundles = PackageBundles::whereIn('random_id', $randomIds)
            ->whereIn('bundle_id', $packageVouchers->pluck('main_service_id')->unique()->all())
            ->where('discount_name', $voucherName)
            ->with('bundle')
            ->get();

        if ($allBundles->isEmpty()) {
            return [];
        }

        // Batch load all package services for the found bundles
        $bundleIds = $allBundles->pluck('id')->all();
        $allPackageServices = PackageService::whereIn('package_bundle_id', $bundleIds)
            ->with('service')
            ->get()
            ->groupBy('package_bundle_id');

        $voucherUsageData = [];

        foreach ($packageVouchers as $packageVoucher) {
            $matchingBundles = $allBundles->filter(
                fn ($b) => $b->random_id === $packageVoucher->package_random_id
                    && $b->bundle_id === $packageVoucher->main_service_id
            );

            $package = $packagesLookup[$packageVoucher->package_random_id] ?? null;

            foreach ($matchingBundles as $bundle) {
                $services = $allPackageServices[$bundle->id] ?? collect();

                foreach ($services as $service) {
                    $voucherUsageData[] = [
                        'package_id' => $package?->id,
                        'package_random_id' => $packageVoucher->package_random_id,
                        'bundle_name' => $bundle->bundle?->name ?? 'N/A',
                        'service_name' => $service->service?->name ?? 'N/A',
                        'discount_type' => $bundle->discount_type,
                        'discount_price' => $bundle->discount_price,
                        'user_id' => $userVoucher->user_id,
                    ];
                }
            }
        }

        return $voucherUsageData;
    }

    public function getFilterValues(): array
    {
        return [
            'vouchers' => Discounts::where('discount_type', 'voucher')
                ->select('id', 'name')
                ->get(),
        ];
    }

    public function getActiveFilters(): array
    {
        $filters = Filters::all(Auth::id(), self::FILTER_KEY);

        if (!empty($filters['patient_id'])) {
            $patient = User::find($filters['patient_id']);
            if ($patient) {
                $filters['patient_name'] = $patient->name;
            }
        }

        return $filters;
    }

    public function getPermissions(): array
    {
        return [
            'view' => Gate::allows('vouchers_view'),
            'create' => Gate::allows('vouchers_create'),
            'edit' => Gate::allows('vouchers_edit'),
            'delete' => Gate::allows('vouchers_destroy'),
        ];
    }

    public function isUsedInPackages(UserVouchers $voucher): bool
    {
        return PackageVouchers::where('voucher_id', $voucher->voucher_id)
            ->where('user_id', $voucher->user_id)
            ->exists();
    }

    private function buildUsedVouchersLookup($vouchers): array
    {
        $userIds = $vouchers->pluck('user_id')->unique()->all();
        $voucherIds = $vouchers->pluck('voucher_id')->unique()->all();

        if (empty($userIds) || empty($voucherIds)) {
            return [];
        }

        return PackageVouchers::whereIn('user_id', $userIds)
            ->whereIn('voucher_id', $voucherIds)
            ->select('user_id', 'voucher_id')
            ->get()
            ->groupBy(fn ($item) => $item->user_id . '_' . $item->voucher_id)
            ->all();
    }

    private function buildWhereConditions(array $params, int $userId, bool $applyFilter): array
    {
        $where = [];

        $this->addFilter($where, $params, 'patient_id', 'user_id', '=', $userId, $applyFilter);
        $this->addFilter($where, $params, 'voucher_type', 'voucher_id', '=', $userId, $applyFilter);

        if (!empty($params['created_from'])) {
            $where[] = ['created_at', '>=', $params['created_from'] . ' 00:00:00'];
            Filters::put($userId, self::FILTER_KEY, 'created_from', $params['created_from']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'created_from');
        } elseif ($stored = Filters::get($userId, self::FILTER_KEY, 'created_from')) {
            $where[] = ['created_at', '>=', $stored . ' 00:00:00'];
        }

        if (!empty($params['created_to'])) {
            $where[] = ['created_at', '<=', $params['created_to'] . ' 23:59:59'];
            Filters::put($userId, self::FILTER_KEY, 'created_to', $params['created_to']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'created_to');
        } elseif ($stored = Filters::get($userId, self::FILTER_KEY, 'created_to')) {
            $where[] = ['created_at', '<=', $stored . ' 23:59:59'];
        }

        return $where;
    }

    private function addFilter(array &$where, array $params, string $paramKey, string $column, string $operator, int $userId, bool $applyFilter): void
    {
        if (!empty($params[$paramKey])) {
            $where[] = [$column, $operator, $params[$paramKey]];
            Filters::put($userId, self::FILTER_KEY, $paramKey, $params[$paramKey]);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, $paramKey);
        } elseif ($stored = Filters::get($userId, self::FILTER_KEY, $paramKey)) {
            $where[] = [$column, $operator, $stored];
        }
    }
}
