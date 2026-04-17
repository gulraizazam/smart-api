<?php

declare(strict_types=1);

namespace App\Services\Dashboard\Support;

use App\Services\Dashboard\ValueObjects\MetricScope;
use App\Services\DoctorDashboard\DoctorIdentifier;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for "which doctors are in this scope?" and their
 * display names. Every aggregating metric (conversion / upsell / membership /
 * feedback / product revenue / patient return / resource leaderboard) needed
 * this lookup — consolidated here.
 *
 * Scope semantics:
 *   - Company-wide → every active doctor on the account (via DoctorIdentifier
 *     which caches the Spatie role lookup).
 *   - Branch-scoped → doctors allocated to any of the scope's branches, via
 *     doctor_has_locations.is_allocated = 1.
 */
final class DoctorResolver
{
    /** @var array<string, list<int>> */
    private array $idsCache = [];

    /** @var array<string, array<int, string>> */
    private array $namesCache = [];

    public function __construct(
        private readonly DoctorIdentifier $doctorIdentifier,
    ) {}

    /**
     * @return list<int>
     */
    public function idsInScope(MetricScope $scope): array
    {
        $key = $scope->cacheKey();
        if (isset($this->idsCache[$key])) {
            return $this->idsCache[$key];
        }

        if ($scope->isCompanyWide()) {
            return $this->idsCache[$key] = array_map(
                'intval',
                $this->doctorIdentifier->getAllActiveDoctorIds($scope->accountId),
            );
        }

        $branchIds = $scope->branchIds ?? [];

        return $this->idsCache[$key] = array_values(array_map(
            'intval',
            DB::table('doctor_has_locations')
                ->whereIn('location_id', $branchIds)
                ->where('is_allocated', 1)
                ->distinct()
                ->pluck('user_id')
                ->toArray(),
        ));
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    public function names(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        sort($ids);
        $key = implode(',', $ids);
        if (isset($this->namesCache[$key])) {
            return $this->namesCache[$key];
        }

        return $this->namesCache[$key] = DB::table('users')
            ->whereIn('id', $ids)
            ->pluck('name', 'id')
            ->toArray();
    }
}
