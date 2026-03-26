<?php

namespace App\Models;

use App\Enums\Gender;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Leads extends BaseModal
{
    use SoftDeletes;

    protected $fillable = [
        'patient_id', 'region_id', 'city_id', 'lead_status_id', 'lead_source_id',
        'msg_count', 'active', 'created_by', 'updated_by', 'converted_by',
        'town_id', 'created_at', 'updated_at', 'account_id', 'location_id',
        'name', 'email', 'phone', 'gender', 'referred_by', 'meta_lead_id',
    ];

    public static array $_fillable = [
        'region_id', 'city_id', 'lead_status_id', 'lead_source_id',
        'msg_count', 'service_id', 'town_id',
    ];

    protected $table = 'leads';

    public static string $_table = 'leads';

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public static function getFillableFields(): array
    {
        return self::$_fillable;
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function lead_service(): HasMany
    {
        return $this->hasMany(LeadsServices::class, 'lead_id')
            ->with('service:id,name,parent_id', 'childservice:id,name,parent_id', 'leadStatus:id,name');
    }

    public function active_lead_service(): HasMany
    {
        return $this->hasMany(LeadsServices::class, 'lead_id')->where('status', 1);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patients::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(Cities::class)->withTrashed();
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Regions::class)->withTrashed();
    }

    public function lead_status(): BelongsTo
    {
        return $this->belongsTo(LeadStatuses::class)->withTrashed();
    }

    public function lead_source(): BelongsTo
    {
        return $this->belongsTo(LeadSources::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function lead_comments(): HasMany
    {
        return $this->hasMany(LeadComments::class, 'lead_id')->orderByDesc('created_at');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointments::class, 'lead_id');
    }

    public function towns(): BelongsTo
    {
        return $this->belongsTo(Locations::class, 'location_id')->withTrashed();
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', 1);
    }

    public function scopeForAccount(Builder $query, ?int $accountId = null): Builder
    {
        return $query->where('account_id', $accountId ?? Auth::user()->account_id);
    }

    public function scopeForCities(Builder $query, array $cityIds): Builder
    {
        return $query->where(function (Builder $q) use ($cityIds) {
            $q->whereIn('city_id', $cityIds)->orWhereNull('city_id');
        });
    }
}
