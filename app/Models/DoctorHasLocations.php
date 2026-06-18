<?php

declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorHasLocations extends Model
{
    protected $fillable = ['user_id', 'location_id', 'service_id', 'end_node','is_allocated'];

    protected $table = 'doctor_has_locations';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Locations::class, 'location_id')->withTrashed();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Services::class, 'service_id')->withTrashed();
    }

    /**
     * The distinct, currently-allocated clinic (location) ids for a doctor.
     *
     * Doctors are scoped via this table, not `user_has_locations`, so the
     * branch resolvers (ResourceScopeResolver, CashflowHelper::getUserBranches)
     * fall back here when a user has no `user_has_locations` row — otherwise an
     * assigned doctor resolves to no branches and is locked out of the
     * branch-scoped dashboard.
     *
     * @return list<int>
     */
    public static function allocatedLocationIds(int $userId): array
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('is_allocated', 1)
            ->pluck('location_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
