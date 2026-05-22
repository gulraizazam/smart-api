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
use App\Models\Patients;
use App\Models\User;
use App\Models\UserVouchers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class UserVoucherService
{
    private const FILTER_KEY = 'vouchers';

    // ── CRUD Operations ─────────────────────────────────

    public function store(array $data): UserVouchers
    {
        return DB::transaction(function () use ($data): UserVouchers {
            $voucher = UserVouchers::create([
                'user_id' => $data['patient_id'],
                'voucher_id' => $data['voucher_id'],
                'amount' => $data['amount'],
                'total_amount' => $data['amount'],
            ]);

            // ActivityLogger::logVoucherAssigned() is typed to
            // App\Models\Patients (which extends BaseModel, NOT User), so
            // fetching the patient via User::find() triggers a TypeError
            // at the logger call — identical to the bug fixed in
            // MembershipAssignmentService. Fetch through the Patients model
            // directly so the assigned patient passes the type check.
            $patient = Patients::find($data['patient_id']);
            $discount = Discounts::find($data['voucher_id']);

            if ($patient && $discount) {
                DB::afterCommit(static function () use ($voucher, $patient, $discount): void {
                    ActivityLogger::logVoucherAssigned($voucher, $patient, $discount);
                });
            }

            return $voucher;
        });
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

        // `total_amount` is a DECIMAL column returned as a string by
        // MariaDB; cast both values to float so strict_types doesn't
        // TypeError at the logger call (which takes float|int).
        $oldAmount = (float) $voucher->total_amount;
        $newAmount = (float) $data['total_amount'];

        $voucher->update([
            'total_amount' => $newAmount,
            'amount' => $newAmount,
        ]);

        // Same Patients vs User typing trap as ::store() — the logger
        // is typed to Patients, so fetch via the Patients model.
        $patient = Patients::find($voucher->user_id);
        $discount = Discounts::find($voucher->voucher_id);
        if ($patient && $discount) {
            ActivityLogger::logVoucherUpdated($voucher, $patient, $discount, $oldAmount, $newAmount);
        }

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

        if (! $voucher) {
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

    // ── Datatable ───────────────────────────────────────

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

    // ── Voucher Usage ───────────────────────────────────

    public function getVoucherUsageData(int $id): array
    {
        $userVoucher = $this->find($id);

        if (! $userVoucher) {
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
        $mainServiceIds = $packageVouchers->pluck('main_service_id')->unique()->all();

        $packagesLookup = Packages::whereIn('random_id', $randomIds)
            ->get()
            ->keyBy('random_id');

        $bundlesByKey = PackageBundles::whereIn('random_id', $randomIds)
            ->whereIn('bundle_id', $mainServiceIds)
            ->where('discount_name', $voucherName)
            ->with('bundle')
            ->get()
            ->groupBy(static fn ($b): string => $b->random_id.'|'.$b->bundle_id);

        if ($bundlesByKey->isEmpty()) {
            return [];
        }

        $allBundleIds = $bundlesByKey->flatten(1)->pluck('id')->all();
        $servicesByBundle = PackageService::whereIn('package_bundle_id', $allBundleIds)
            ->with('service')
            ->get()
            ->groupBy('package_bundle_id');

        $voucherUsageData = [];

        foreach ($packageVouchers as $packageVoucher) {
            $key = $packageVoucher->package_random_id.'|'.$packageVoucher->main_service_id;
            $matchingBundles = $bundlesByKey->get($key, collect());
            $package = $packagesLookup[$packageVoucher->package_random_id] ?? null;

            foreach ($matchingBundles as $bundle) {
                $services = $servicesByBundle->get($bundle->id, collect());

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

    // ── Filter & Permission Helpers ─────────────────────

    public function getFilterValues(): array
    {
        return [
            'vouchers' => Discounts::where('discount_type', 'voucher')
                ->select('id', 'name')
                ->orderBy('name')
                ->limit(200)
                ->get(),
        ];
    }

    public function getActiveFilters(): array
    {
        $filters = Filters::all(Auth::id(), self::FILTER_KEY);

        if (! empty($filters['patient_id'])) {
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
            'view'   => Gate::allows('vouchers.detail.view'),
            'create' => Gate::allows('vouchers.create'),
            'edit'   => Gate::allows('vouchers.edit'),
            'delete' => Gate::allows('vouchers.destroy'),
        ];
    }

    public function isUsedInPackages(UserVouchers $voucher): bool
    {
        return PackageVouchers::where('voucher_id', $voucher->voucher_id)
            ->where('user_id', $voucher->user_id)
            ->exists();
    }

    // ── Private Helpers ─────────────────────────────────

    private function buildDatatableQuery(array $params): Builder
    {
        $userId = (int) Auth::id();
        $applyFilter = $params['apply_filter'] ?? false;

        $where = $this->buildWhereConditions($params, $userId, $applyFilter);
        $phone = $this->getPhoneFilter($params, $userId, $applyFilter);

        $query = UserVouchers::query()
            ->when(! empty($where), fn (Builder $q): Builder => $q->where($where))
            ->when(
                $phone !== null && $phone !== '',
                fn (Builder $q): Builder => $q->whereHas(
                    'user',
                    fn ($u) => $u->where('phone', 'like', '%'.$phone.'%'),
                ),
            );

        // Unified patient search — same engine the Plans / Patients /
        // picker / invoices / treatments / consultations surfaces use.
        // SPA sends `q`; classifier routes to id / phone_normalized /
        // FT name and applies the resulting filter against `user_id`.
        $q = $this->getQueryFilter($params, $userId, $applyFilter);
        if ($q !== null && $q !== '') {
            \App\Services\PatientManagement\PatientSearchService::applyPatientFilter(
                $query,
                $q,
                'user_id',
                Auth::user()?->account_id,
            );
        }

        return $query;
    }

    private function getQueryFilter(array $params, int $userId, bool $applyFilter): ?string
    {
        if (! empty($params['q'])) {
            $value = (string) $params['q'];
            Filters::put($userId, self::FILTER_KEY, 'q', $value);

            return $value;
        }

        if ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'q');

            return null;
        }

        $stored = Filters::get($userId, self::FILTER_KEY, 'q');

        return $stored !== null && $stored !== '' ? (string) $stored : null;
    }

    private function getPhoneFilter(array $params, int $userId, bool $applyFilter): ?string
    {
        if (! empty($params['phone'])) {
            $value = (string) $params['phone'];
            Filters::put($userId, self::FILTER_KEY, 'phone', $value);

            return $value;
        }

        if ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'phone');

            return null;
        }

        $stored = Filters::get($userId, self::FILTER_KEY, 'phone');

        return $stored !== null && $stored !== '' ? (string) $stored : null;
    }

    private function buildUsedVouchersLookup(Collection $vouchers): array
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
            ->groupBy(fn ($item): string => $item->user_id.'_'.$item->voucher_id)
            ->all();
    }

    private function buildWhereConditions(array $params, int $userId, bool $applyFilter): array
    {
        $where = [];

        $this->addFilter($where, $params, 'patient_id', 'user_id', '=', $userId, $applyFilter);
        $this->addFilter($where, $params, 'voucher_type', 'voucher_id', '=', $userId, $applyFilter);

        if (! empty($params['created_from'])) {
            $where[] = ['created_at', '>=', $params['created_from'].' 00:00:00'];
            Filters::put($userId, self::FILTER_KEY, 'created_from', $params['created_from']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'created_from');
        } elseif ($stored = Filters::get($userId, self::FILTER_KEY, 'created_from')) {
            $where[] = ['created_at', '>=', $stored.' 00:00:00'];
        }

        if (! empty($params['created_to'])) {
            $where[] = ['created_at', '<=', $params['created_to'].' 23:59:59'];
            Filters::put($userId, self::FILTER_KEY, 'created_to', $params['created_to']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'created_to');
        } elseif ($stored = Filters::get($userId, self::FILTER_KEY, 'created_to')) {
            $where[] = ['created_at', '<=', $stored.' 23:59:59'];
        }

        return $where;
    }

    private function addFilter(
        array &$where,
        array $params,
        string $paramKey,
        string $column,
        string $operator,
        int $userId,
        bool $applyFilter,
    ): void {
        if (! empty($params[$paramKey])) {
            $where[] = [$column, $operator, $params[$paramKey]];
            Filters::put($userId, self::FILTER_KEY, $paramKey, $params[$paramKey]);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, $paramKey);
        } elseif ($stored = Filters::get($userId, self::FILTER_KEY, $paramKey)) {
            $where[] = [$column, $operator, $stored];
        }
    }
}
