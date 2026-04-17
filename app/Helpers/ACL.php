<?php

declare(strict_types=1);
namespace App\Helpers;

use App\Models\Cities;
use App\Models\DoctorHasLocations;
use App\Models\Locations;
use App\Models\Regions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

class ACL
{
    /**
     * Get location IDs the current user has access to.
     */
    public static function getUserCentres(): array
    {
        static $cachedLocations = [];
        $userId = Auth::id();

        if (isset($cachedLocations[$userId])) {
            return $cachedLocations[$userId];
        }

        $user = Auth::user();

        $locations = match (true) {
            $user->id === 1 => Locations::where('active', 1)
                ->where('name', '!=', 'All Centres')
                ->pluck('id'),

            $user->user_type_id == Config::get('constants.practitioner_id') =>
                DoctorHasLocations::where('user_id', $user->id)
                    ->where('is_allocated', 1)
                    ->distinct()
                    ->pluck('location_id'),

            default => $user->user_has_locations()->pluck('location_id'),
        };

        $result = self::expandAllCentres($locations?->toArray() ?? [], (int) $user->account_id);
        $cachedLocations[$userId] = $result;

        return $result;
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
        static $cachedRegions = [];
        $userId = Auth::id();

        if (isset($cachedRegions[$userId])) {
            return $cachedRegions[$userId];
        }

        $user = Auth::user();
        $accountId = $user->account_id;

        $regions = $user->id === 1
            ? Regions::where('account_id', $accountId)->pluck('id')
            : Regions::whereIn('id', Cities::getActiveOnly(self::getUserCities(), $accountId)->pluck('region_id'))
                ->where('account_id', $accountId)
                ->pluck('id');

        $result = $regions?->toArray() ?? [];
        $cachedRegions[$userId] = $result;

        return $result;
    }

    /**
     * Get city IDs the current user has access to.
     */
    public static function getUserCities(): array
    {
        static $cachedCities = [];
        $userId = Auth::id();

        if (isset($cachedCities[$userId])) {
            return $cachedCities[$userId];
        }

        $user = Auth::user();
        $accountId = $user->account_id;

        $cities = match (true) {
            $user->id === 1 => Cities::where('account_id', $accountId)->pluck('id'),

            $user->user_type_id == Config::get('constants.practitioner_id') =>
                Locations::whereIn('id',
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

        $result = $cities?->toArray() ?? [];
        $cachedCities[$userId] = $result;

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

        if ($allCentresId === 0 || !in_array($allCentresId, $normalized, true)) {
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
