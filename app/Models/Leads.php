<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class Leads extends BaseModel
{
    use SoftDeletes;

    protected $table = 'leads';

    public static string $_table = 'leads';

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

    protected function casts(): array
    {
        return [
            // `gender` is intentionally NOT cast to App\Enums\Gender. Legacy prod
            // rows carry gender=0 (tinyint NOT NULL DEFAULT 0, "unspecified"), which
            // is not an enum case — the enum cast threw ValueError the moment
            // $lead->gender was touched (AppointmentService, ExportLead, list/detail
            // resources). Kept as a raw int, consistent with Patients/User; resources
            // label it via Gender::tryFrom on the raw attribute. (go-live §4-A)
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

    public function leadServices(): HasMany
    {
        return $this->hasMany(LeadsServices::class, 'lead_id')
            ->with('service:id,name,parent_id', 'childService:id,name,parent_id', 'leadStatus:id,name');
    }

    public function activeLeadServices(): HasMany
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

    public function leadStatus(): BelongsTo
    {
        return $this->belongsTo(LeadStatuses::class)->withTrashed();
    }

    public function leadSource(): BelongsTo
    {
        return $this->belongsTo(LeadSources::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function leadComments(): HasMany
    {
        return $this->hasMany(LeadComments::class, 'lead_id')->orderByDesc('created_at');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointments::class, 'lead_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Locations::class, 'location_id')->withTrashed();
    }

    // =========================================================================
    // Legacy Relationship Aliases (backward compatibility)
    // =========================================================================

    public function lead_service(): HasMany
    {
        return $this->leadServices();
    }

    public function active_lead_service(): HasMany
    {
        return $this->activeLeadServices();
    }

    public function lead_status(): BelongsTo
    {
        return $this->leadStatus();
    }

    public function lead_source(): BelongsTo
    {
        return $this->leadSource();
    }

    public function lead_comments(): HasMany
    {
        return $this->leadComments();
    }

    public function towns(): BelongsTo
    {
        return $this->location();
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
        return $query->where(function (Builder $q) use ($cityIds): void {
            $q->whereIn('city_id', $cityIds)->orWhereNull('city_id');
        });
    }

    /**
     * Centre/branch-level ACL — the authoritative lead visibility boundary.
     *
     * Leads carry both a (required) city_id and a (nullable) location_id;
     * because one city can hold several branches, scoping by city leaks a
     * sibling branch's leads to a single-branch user. Scope by branch instead,
     * mirroring every other surface (appointments, invoices, orders…).
     *
     * Semantics match ResourceScopeResolver::allowedBranchIds():
     *   - null  → company-wide: no restriction (see all leads)
     *   - array → restrict to those branches; leads with a null location_id are
     *             the shared unassigned pool and stay visible to everyone.
     *
     * location_id is qualified (`leads.location_id`) so the scope is safe to
     * chain onto joined queries (e.g. the lead report base query).
     */
    public function scopeForBranches(Builder $query, ?array $branchIds): Builder
    {
        if ($branchIds === null) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($branchIds): void {
            $q->whereIn('leads.location_id', $branchIds)->orWhereNull('leads.location_id');
        });
    }
}
