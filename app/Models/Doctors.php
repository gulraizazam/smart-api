<?php

declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Doctors extends BaseModel
{
    protected $fillable = [
        'name', 'email', 'password', 'remember_token',
        'mobile', 'main_account', 'gender', 'user_type_id',
        'resource_type_id', 'account_id',
    ];

    protected static int $USER_TYPE_STATIC = 5;

    protected $table = 'users';

    protected function fullName(): Attribute
    {
        return Attribute::get(
            fn (): string => ucfirst($this->name) . ' - ' . strtolower($this->email)
        );
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(Cities::class)->withTrashed();
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Regions::class)->withTrashed();
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Locations::class)->withTrashed();
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointments::class, 'doctor_id');
    }

    public function audit_field_before(): HasMany
    {
        return $this->hasMany(AuditTrailChanges::class, 'field_before');
    }

    public function audit_field_after(): HasMany
    {
        return $this->hasMany(AuditTrailChanges::class, 'field_after');
    }

    public static function getAll(int $accountId)
    {
        return static::where([
            'user_type_id' => static::$USER_TYPE_STATIC,
            'account_id' => $accountId,
        ])->get();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', 1);
    }

    public function scopeForAccount(Builder $query, int $accountId): Builder
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeAllocatedToLocation(Builder $query, array $locationIds): Builder
    {
        return $query->join('doctor_has_locations', function ($join) {
            $join->on('users.id', '=', 'doctor_has_locations.user_id')
                ->where('users.user_type_id', '=', config('constants.asthatic_operator_id'))
                ->where('users.active', '=', 1)
                ->where('doctor_has_locations.is_allocated', '=', 1);
        })->whereIn('doctor_has_locations.location_id', $locationIds);
    }

    /**
     * Get active doctors, optionally filtered by location, account, and doctor IDs.
     */
    public static function getActiveOnly(
        array|int|false $locationId = false,
        int|false $accountId = false,
        array|int|false $doctorId = false,
        bool $pluckColumns = true,
    ) {
        $locationIds = match (true) {
            $locationId === false => null,
            is_array($locationId) => $locationId,
            default => [$locationId],
        };

        $doctorIds = match (true) {
            $doctorId === false => null,
            is_array($doctorId) => $doctorId,
            default => [$doctorId],
        };

        $query = $locationIds
            ? static::buildLocationQuery($locationIds, $accountId)
            : static::buildBaseQuery($accountId);

        if ($doctorIds) {
            $column = $locationIds ? 'users.id' : 'users.id';
            $query->whereIn($column, $doctorIds);
        }

        $results = $query->get();

        if ($pluckColumns) {
            $pluckKey = $locationIds ? 'user_id' : 'id';
            return $results->pluck('name', $pluckKey);
        }

        return $results;
    }

    private static function buildLocationQuery(array $locationIds, int|false $accountId): Builder
    {
        $query = static::join('doctor_has_locations', function ($join) use ($accountId) {
            $join->on('users.id', '=', 'doctor_has_locations.user_id')
                ->where('users.user_type_id', '=', config('constants.asthatic_operator_id'))
                ->where('users.active', '=', 1)
                ->where('doctor_has_locations.is_allocated', '=', 1);

            if ($accountId) {
                $join->where('users.account_id', '=', $accountId);
            }
        })->whereIn('doctor_has_locations.location_id', $locationIds);

        return $query;
    }

    private static function buildBaseQuery(int|false $accountId): Builder
    {
        $query = static::where('users.user_type_id', '=', config('constants.asthatic_operator_id'))
            ->where('users.active', '=', 1);

        if ($accountId) {
            $query->where('users.account_id', '=', $accountId);
        }

        return $query;
    }

    /**
     * Get Location based Doctors grouped by location_id.
     */
    public static function getLocationDoctors(): array
    {
        $doctors = static::join('doctor_has_locations', function ($join) {
            $join->on('users.id', '=', 'doctor_has_locations.user_id')
                ->where('users.user_type_id', '=', config('constants.asthatic_operator_id'))
                ->where('doctor_has_locations.is_allocated', '=', 1)
                ->where('users.active', '=', 1);
        })->get();

        $data = [];
        foreach ($doctors as $doctor) {
            $data[$doctor['location_id']][$doctor['user_id']] = $doctor;
        }

        return $data;
    }
}
