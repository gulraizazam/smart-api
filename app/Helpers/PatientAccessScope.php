<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Models\DoctorHasLocations;
use App\Models\Locations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

/**
 * Scope patient searches by the caller's assigned centres.
 *
 * Rule: a user can only find patients who have at least one appointment
 * at a centre the user is assigned to. Users assigned "All Centres" (or
 * the super-admin id=1) see every patient in their account.
 */
class PatientAccessScope
{
    private const ALL_CENTRE_NAMES = ['All Centres', 'All South Region', 'All Central Region'];

    /**
     * @return array{location_ids: int[]}|null null = unrestricted
     */
    public static function resolve(): ?array
    {
        static $cache = [];
        $userId = Auth::id();
        if ($userId === null) {
            return ['location_ids' => []];
        }
        if (array_key_exists($userId, $cache)) {
            return $cache[$userId];
        }

        $user = Auth::user();

        // Super-Admin escape — role-based primary, id=1 retained as a
        // fallback for legacy bootstrapped accounts where the root user
        // pre-dates the role rollout. Role check mirrors the global Gate
        // bypass in AppServiceProvider::configureAuthorization() so an
        // operator can't be Super-Admin for permission gates but blocked
        // here.
        if (method_exists($user, 'hasRole') && $user->hasRole('Super-Admin')) {
            return $cache[$userId] = null;
        }
        if ((int) $user->id === 1) {
            return $cache[$userId] = null;
        }

        $userLocations = self::userAssignedLocationIds($user);

        if (self::hasAllCentres($user, $userLocations)) {
            return $cache[$userId] = null;
        }

        return $cache[$userId] = ['location_ids' => $userLocations];
    }

    /**
     * Raw SQL fragment + bindings for use with DB::select.
     * Fragment begins with a space and " AND " so it is safe to append
     * inside an existing WHERE clause.
     *
     * @return array{0: string, 1: array}
     */
    public static function rawClause(string $patientIdExpr = 'users.id'): array
    {
        $scope = self::resolve();
        if ($scope === null) {
            return ['', []];
        }

        if (empty($scope['location_ids'])) {
            return [' AND 1=0', []];
        }

        $placeholders = implode(',', array_fill(0, count($scope['location_ids']), '?'));

        return [
            " AND EXISTS (SELECT 1 FROM appointments a"
            . " WHERE a.patient_id = {$patientIdExpr} AND a.location_id IN ($placeholders))",
            array_values($scope['location_ids']),
        ];
    }

    /**
     * Apply the scope to an Eloquent or Query builder on the users table.
     * Accepts either builder flavour — Eloquent proxies these calls through.
     */
    public static function applyTo($query, string $patientIdColumn = 'users.id'): void
    {
        $scope = self::resolve();
        if ($scope === null) {
            return;
        }

        if (empty($scope['location_ids'])) {
            $query->whereRaw('1=0');
            return;
        }

        $locationIds = $scope['location_ids'];
        $query->whereExists(function ($sub) use ($locationIds, $patientIdColumn) {
            $sub->selectRaw('1')
                ->from('appointments as a')
                ->whereColumn('a.patient_id', $patientIdColumn)
                ->whereIn('a.location_id', $locationIds);
        });
    }

    /**
     * Stable cache-key suffix that changes when the effective scope changes.
     */
    public static function cacheSuffix(): string
    {
        $scope = self::resolve();
        if ($scope === null) {
            return 'all';
        }
        if (empty($scope['location_ids'])) {
            return 'none';
        }
        $ids = $scope['location_ids'];
        sort($ids);

        return 'loc_' . md5(implode(',', $ids));
    }

    private static function userAssignedLocationIds($user): array
    {
        if ((int) $user->user_type_id === (int) Config::get('constants.practitioner_id')) {
            return DoctorHasLocations::where('user_id', $user->id)
                ->where('is_allocated', 1)
                ->distinct()
                ->pluck('location_id')
                ->map(fn ($v) => (int) $v)
                ->all();
        }

        return $user->user_has_locations()
            ->pluck('location_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    private static function hasAllCentres($user, array $userLocations): bool
    {
        if (empty($userLocations)) {
            return false;
        }

        $allCentreIds = Cache::remember(
            "all_centres_ids_{$user->account_id}",
            3600,
            fn () => Locations::where('account_id', $user->account_id)
                ->whereIn('name', self::ALL_CENTRE_NAMES)
                ->pluck('id')
                ->map(fn ($v) => (int) $v)
                ->all()
        );

        return ! empty(array_intersect($allCentreIds, $userLocations));
    }
}
