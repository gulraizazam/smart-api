<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\Cities;
use App\Models\DoctorHasLocations;
use App\Models\Locations;
use App\Models\Regions;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

class ACL
{
    /**
     * Get location IDs the current user has access to.
     */
    public static function getUserCentres(): array
    {
        return self::memoPerUser('centres', static function (): array {
            $user = Auth::user();

            $locations = match (true) {
                $user->id === 1 => Locations::where('active', 1)
                    ->where('name', '!=', 'All Centres')
                    ->pluck('id'),

                $user->user_type_id == Config::get('constants.practitioner_id') => DoctorHasLocations::where('user_id', $user->id)
                    ->where('is_allocated', 1)
                    ->distinct()
                    ->pluck('location_id'),

                default => $user->user_has_locations()->pluck('location_id'),
            };

            return self::expandAllCentres($locations?->toArray() ?? [], (int) $user->account_id);
        });
    }

    public static function getUserWarehouse(): array
    {
        $locations = Auth::user()->user_has_warehouse()->pluck('warehouse_id');

        return $locations?->toArray() ?? [];
    }

    /**
     * Get region IDs the current user has access to.
     */
    public static function getUserRegions(): array
    {
        return self::memoPerUser('regions', static function (): array {
            $user = Auth::user();
            $accountId = $user->account_id;

            $regions = $user->id === 1
                ? Regions::where('account_id', $accountId)->pluck('id')
                : Regions::whereIn('id', Cities::getActiveOnly(self::getUserCities(), $accountId)->pluck('region_id'))
                    ->where('account_id', $accountId)
                    ->pluck('id');

            return $regions?->toArray() ?? [];
        });
    }

    /**
     * Get city IDs the current user has access to.
     */
    public static function getUserCities(): array
    {
        return self::memoPerUser('cities', static function (): array {
            $user = Auth::user();
            $accountId = $user->account_id;

            $cities = match (true) {
                $user->id === 1 => Cities::where('account_id', $accountId)->pluck('id'),

                $user->user_type_id == Config::get('constants.practitioner_id') => Locations::whereIn('id',
                    DoctorHasLocations::where('user_id', $user->id)
                        ->where('is_allocated', 1)
                        ->distinct()
                        ->pluck('location_id')
                )
                    ->where('account_id', $accountId)
                    ->pluck('city_id'),

                default => Locations::whereIn(
                    'id',
                    self::expandAllCentres(
                        $user->user_has_locations()->pluck('location_id')->toArray(),
                        (int) $accountId,
                    ),
                )
                    ->where('account_id', $accountId)
                    ->pluck('city_id'),
            };

            return $cities?->toArray() ?? [];
        });
    }

    /**
     * Per-request, per-user memoization for the getUser* lookups above.
     * Preserves the original `static $cachedX` semantics (results live for
     * the lifetime of the PHP process) while removing the copy-pasted
     * skeleton from each caller. Keyed by ($key, Auth::id()) so distinct
     * lookups and distinct users don't collide.
     *
     * @var array<string, array<int|string, array>>
     */
    private static array $memo = [];

    /**
     * Long-lived processes (the test suite, queue workers) outlive a single
     * request, and in tests user ids repeat after every DB refresh — so a
     * memo from one test can poison another. Flushed per-test in setUp.
     */
    public static function flushMemo(): void
    {
        self::$memo = [];
    }

    private static function memoPerUser(string $key, Closure $loader): array
    {
        $userId = Auth::id();

        if (isset(self::$memo[$key][$userId])) {
            return self::$memo[$key][$userId];
        }

        $result = $loader();
        self::$memo[$key][$userId] = $result;

        return $result;
    }

    /**
     * If the pivot grants the virtual "All Centres" location, expand it to every
     * real active centre for the account. Real appointments are booked against
     * real centres, so queries must filter by real ids — not the virtual one.
     *
     * @param  array<int, int|string>  $locationIds
     * @return array<int, int>
     */
    private static function expandAllCentres(array $locationIds, int $accountId): array
    {
        $allCentresId = (int) Config::get('constants.all_centres_location_id');

        $normalized = array_map('intval', $locationIds);

        if ($allCentresId === 0 || ! in_array($allCentresId, $normalized, true)) {
            return $normalized;
        }

        return Locations::where('active', 1)
            ->where('account_id', $accountId)
            ->where('name', '!=', 'All Centres')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }
}
