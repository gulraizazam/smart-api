<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Metrics;

use App\Services\Dashboard\Support\BranchResolver;
use App\Services\Dashboard\ValueObjects\DateRange;
use App\Services\Dashboard\ValueObjects\MetricScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Arrival-rate metric — clean replacement for the legacy
 * `DashboardChartService::getCentreWiseArrival`.
 *
 * Source of truth: the `appointments` table directly (not the legacy
 * `appointments_daily_stats` audit log, which only tracks consultations
 * — treatments would be invisible under that source). One row per
 * appointment, counted against its current `scheduled_date`.
 *
 * Legacy parity caveats:
 *   - Legacy divided daily-stats rows by 2 ("set of 2") which undercounts.
 *     We don't do that — each appointment is one event.
 *   - Legacy only showed tax-registered branches (NTN/STN gate). We show
 *     all active (non-soft-deleted) branches instead.
 *   - Legacy left today partially in the window on non-"thismonth" presets;
 *     we always exclude today, matching BranchFeedbackMetric semantics.
 *
 * Semantics per group bucket (branch or top-level service category):
 *   total         = COUNT(appointments)
 *   arrived       = rows where `appointment_status_id` is is_arrived=1
 *                   OR is_converted=1 (falls back to [2, 16])
 *   walkin        = arrived rows whose creator (`appointments.user_id`) is
 *                   in the FDM role — consultations only, defaults to 0
 *                   for treatments (by product).
 *   arrival_rate  = arrived / total × 100
 *
 * Return shape:
 *   ['rows' => list<{id, name, total, arrived, walkin, arrival_rate}>,
 *    'totals' => {total, arrived, walkin, arrival_rate}]
 */
final class ArrivalRateMetric
{
    public const TYPE_CONSULTATION = 1;

    public const TYPE_TREATMENT = 2;

    public const GROUP_BRANCH = 'branch';

    public const GROUP_CATEGORY = 'category';

    private const CACHE_TTL = 300;

    public function __construct(
        private readonly BranchResolver $branches,
    ) {}

    /**
     * @return array{
     *   rows: list<array{id: int, name: string, total: int, arrived: int, walkin: int, arrival_rate: float|null}>,
     *   totals: array{total: int, arrived: int, walkin: int, arrival_rate: float|null}
     * }
     */
    public function compute(MetricScope $scope, DateRange $range, int $type, string $groupBy): array
    {
        if ($scope->isDenyAll()) {
            return $this->emptyResponse();
        }

        if (! in_array($type, [self::TYPE_CONSULTATION, self::TYPE_TREATMENT], true)) {
            return $this->emptyResponse();
        }

        if (! in_array($groupBy, [self::GROUP_BRANCH, self::GROUP_CATEGORY], true)) {
            return $this->emptyResponse();
        }

        $cacheKey = 'mgmt_dash:arrival_rate:'
            .$scope->cacheKey()
            .'|'.$range->startString().'..'.$range->endString()
            .'|t='.$type
            .'|g='.$groupBy;

        return Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn () => $this->build($scope, $range, $type, $groupBy),
        );
    }

    /**
     * @return array{
     *   rows: list<array{id: int, name: string, total: int, arrived: int, walkin: int, arrival_rate: float|null}>,
     *   totals: array{total: int, arrived: int, walkin: int, arrival_rate: float|null}
     * }
     */
    private function build(MetricScope $scope, DateRange $range, int $type, string $groupBy): array
    {
        $branchIds = $this->branches->idsInScope($scope);
        if ($branchIds === []) {
            return $this->emptyResponse();
        }

        [$startDate, $endDate] = [$range->startString(), $this->excludeToday($range->endString())];
        if ($endDate < $startDate) {
            return $this->emptyResponse();
        }

        $arrivedStatusIds = $this->arrivedStatusIds($scope->accountId);
        $fdmUserIds = $type === self::TYPE_CONSULTATION ? $this->fdmUserIds() : [];

        if ($groupBy === self::GROUP_BRANCH) {
            return $this->byBranch($scope, $branchIds, $type, $startDate, $endDate, $arrivedStatusIds, $fdmUserIds);
        }

        return $this->byCategory($scope, $branchIds, $type, $startDate, $endDate, $arrivedStatusIds, $fdmUserIds);
    }

    /**
     * @param  list<int>  $branchIds
     * @param  list<int>  $arrivedStatusIds
     * @param  list<int>  $fdmUserIds
     * @return array<string, mixed>
     */
    private function byBranch(
        MetricScope $scope,
        array $branchIds,
        int $type,
        string $startDate,
        string $endDate,
        array $arrivedStatusIds,
        array $fdmUserIds,
    ): array {
        $rows = $this->baseQuery($scope, $branchIds, $type, $startDate, $endDate)
            ->select('a.location_id')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw($this->arrivedSelect($arrivedStatusIds))
            ->selectRaw($this->walkinSelect($arrivedStatusIds, $fdmUserIds))
            ->groupBy('a.location_id')
            ->get();

        $names = $this->branches->names($branchIds);
        $items = [];
        foreach ($rows as $r) {
            $id = (int) $r->location_id;
            $items[] = [
                'id' => $id,
                'name' => $names[$id] ?? "Branch #{$id}",
                'total' => (int) $r->total,
                'arrived' => (int) $r->arrived,
                'walkin' => (int) $r->walkin,
                'arrival_rate' => $this->rateOrNull((int) $r->arrived, (int) $r->total),
            ];
        }

        usort($items, fn (array $a, array $b): int => $b['arrival_rate'] <=> $a['arrival_rate']);

        return [
            'rows' => $items,
            'totals' => $this->aggregate($items),
        ];
    }

    /**
     * @param  list<int>  $branchIds
     * @param  list<int>  $arrivedStatusIds
     * @param  list<int>  $fdmUserIds
     * @return array<string, mixed>
     */
    private function byCategory(
        MetricScope $scope,
        array $branchIds,
        int $type,
        string $startDate,
        string $endDate,
        array $arrivedStatusIds,
        array $fdmUserIds,
    ): array {
        $serviceToParent = $this->buildServiceParentMap($scope->accountId);

        $rows = $this->baseQuery($scope, $branchIds, $type, $startDate, $endDate)
            ->whereNotNull('a.service_id')
            ->select('a.service_id')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw($this->arrivedSelect($arrivedStatusIds))
            ->selectRaw($this->walkinSelect($arrivedStatusIds, $fdmUserIds))
            ->groupBy('a.service_id')
            ->get();

        $byCategory = [];
        foreach ($rows as $r) {
            $svcId = (int) $r->service_id;
            $parentId = $serviceToParent[$svcId]['parent_id'] ?? $svcId;
            if (! isset($byCategory[$parentId])) {
                $byCategory[$parentId] = ['total' => 0, 'arrived' => 0, 'walkin' => 0];
            }
            $byCategory[$parentId]['total'] += (int) $r->total;
            $byCategory[$parentId]['arrived'] += (int) $r->arrived;
            $byCategory[$parentId]['walkin'] += (int) $r->walkin;
        }

        $parentIds = array_keys($byCategory);
        $parentNames = $parentIds === []
            ? []
            : DB::table('services')
                ->whereIn('id', $parentIds)
                ->pluck('name', 'id')
                ->toArray();

        $items = [];
        foreach ($byCategory as $parentId => $counts) {
            $items[] = [
                'id' => (int) $parentId,
                'name' => (string) ($parentNames[$parentId] ?? "Category #{$parentId}"),
                'total' => $counts['total'],
                'arrived' => $counts['arrived'],
                'walkin' => $counts['walkin'],
                'arrival_rate' => $this->rateOrNull($counts['arrived'], $counts['total']),
            ];
        }

        usort($items, fn (array $a, array $b): int => $b['arrival_rate'] <=> $a['arrival_rate']);

        return [
            'rows' => $items,
            'totals' => $this->aggregate($items),
        ];
    }

    /**
     * @param  list<int>  $branchIds
     */
    private function baseQuery(
        MetricScope $scope,
        array $branchIds,
        int $type,
        string $startDate,
        string $endDate,
    ) {
        return DB::table('appointments as a')
            ->join('locations as l', 'l.id', '=', 'a.location_id')
            ->where('a.account_id', $scope->accountId)
            ->whereIn('a.location_id', $branchIds)
            ->where('a.appointment_type_id', $type)
            ->whereNull('a.deleted_at')
            ->whereBetween('a.scheduled_date', [$startDate, $endDate])
            ->where('l.active', 1)
            ->whereNull('l.deleted_at');
    }

    /**
     * @param  list<int>  $arrivedStatusIds
     */
    private function arrivedSelect(array $arrivedStatusIds): string
    {
        if ($arrivedStatusIds === []) {
            return 'SUM(0) AS arrived';
        }
        $ids = implode(',', array_map('intval', $arrivedStatusIds));

        return "SUM(CASE WHEN a.appointment_status_id IN ({$ids}) THEN 1 ELSE 0 END) AS arrived";
    }

    /**
     * Walk-in = arrived appointment recorded by an FDM user. Treatments
     * don't have a walk-in notion (by product); fdmUserIds is empty for
     * treatments so the column is zero. `appointments.created_by` is the
     * recorder (mirrors legacy's `daily_stats.user_id`).
     *
     * @param  list<int>  $arrivedStatusIds
     * @param  list<int>  $fdmUserIds
     */
    private function walkinSelect(array $arrivedStatusIds, array $fdmUserIds): string
    {
        if ($arrivedStatusIds === [] || $fdmUserIds === []) {
            return 'SUM(0) AS walkin';
        }
        $ids = implode(',', array_map('intval', $arrivedStatusIds));
        $users = implode(',', array_map('intval', $fdmUserIds));

        return "SUM(CASE WHEN a.appointment_status_id IN ({$ids}) AND a.created_by IN ({$users}) THEN 1 ELSE 0 END) AS walkin";
    }

    /**
     * @return list<int>
     */
    private function arrivedStatusIds(int $accountId): array
    {
        $rows = DB::table('appointment_statuses')
            ->where('account_id', $accountId)
            ->where(function ($q): void {
                $q->where('is_arrived', 1)->orWhere('is_converted', 1);
            })
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        return $rows === [] ? [2, 16] : array_values($rows);
    }

    /**
     * @return list<int>
     */
    private function fdmUserIds(): array
    {
        $roleId = DB::table('roles')->where('name', 'FDM')->value('id');
        if ($roleId === null) {
            return [];
        }

        return DB::table('role_has_users')
            ->where('role_id', $roleId)
            ->pluck('user_id')
            ->map(static fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return array<int, array{name: string, parent_id: int}>
     */
    private function buildServiceParentMap(int $accountId): array
    {
        $rows = DB::table('services')
            ->where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->select('id', 'name', 'parent_id')
            ->get();

        $byId = [];
        foreach ($rows as $r) {
            $byId[(int) $r->id] = $r;
        }

        $out = [];
        foreach ($byId as $id => $r) {
            $ancestor = $r;
            $visited = [];
            while ($ancestor->parent_id !== null && isset($byId[(int) $ancestor->parent_id])) {
                if (isset($visited[(int) $ancestor->id])) {
                    break;
                }
                $visited[(int) $ancestor->id] = true;
                $ancestor = $byId[(int) $ancestor->parent_id];
            }
            $out[$id] = [
                'name' => (string) $r->name,
                'parent_id' => (int) $ancestor->id,
            ];
        }

        return $out;
    }

    private function rateOrNull(int $arrived, int $total): ?float
    {
        return $total > 0 ? round(($arrived / $total) * 100, 1) : null;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{total: int, arrived: int, walkin: int, arrival_rate: float|null}
     */
    private function aggregate(array $items): array
    {
        $t = $a = $w = 0;
        foreach ($items as $it) {
            $t += (int) $it['total'];
            $a += (int) $it['arrived'];
            $w += (int) $it['walkin'];
        }

        return [
            'total' => $t,
            'arrived' => $a,
            'walkin' => $w,
            'arrival_rate' => $this->rateOrNull($a, $t),
        ];
    }

    private function excludeToday(string $endDate): string
    {
        $today = now()->format('Y-m-d');

        return $endDate >= $today
            ? now()->subDay()->format('Y-m-d')
            : $endDate;
    }

    /**
     * @return array{
     *   rows: list<array{id: int, name: string, total: int, arrived: int, walkin: int, arrival_rate: float|null}>,
     *   totals: array{total: int, arrived: int, walkin: int, arrival_rate: float|null}
     * }
     */
    private function emptyResponse(): array
    {
        return [
            'rows' => [],
            'totals' => ['total' => 0, 'arrived' => 0, 'walkin' => 0, 'arrival_rate' => null],
        ];
    }
}
