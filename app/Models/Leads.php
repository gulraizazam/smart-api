<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use App\Services\Phone\PhoneFormattingService;

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
        'department_id',
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

    /**
     * Medical / service department (Skin / Hair / …) the lead is interested in.
     * See {@see \App\Models\LeadDepartment} — separate from HR Department.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(LeadDepartment::class, 'department_id')->withTrashed();
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

    // =========================================================================
    // Phone lookup
    // =========================================================================

    /**
     * The latest non-junk lead matching this phone, scoped to the account.
     *
     * Mirrors Patients::getByPhone — the `phone` column isn't normalised
     * consistently across the shared DB, so we match every equivalent stored
     * form (canonical, leading-zero, 92/+92) via
     * PhoneFormattingService::matchVariants. Used by the consultation lookup to
     * attach a new consultation to an existing lead that has no patient row yet
     * (instead of minting a duplicate lead).
     *
     * "Open" = not a junk-status lead. Converted/inactive leads stay eligible:
     * the consultation re-links to them and the booking cascade
     * (BackfillLeadCategoryAction) reconciles the leads_services category.
     * Soft-deleted rows are excluded by the SoftDeletes scope; a null
     * lead_status_id counts as open. Account scope is intentional — leads are
     * strictly per-account (unlike the shared users table).
     */
    public static function latestOpenByPhone(string $phone, int|false $accountId = false): ?self
    {
        $variants = PhoneFormattingService::matchVariants($phone);

        $query = self::query();
        if (empty($variants)) {
            $query->where('phone', $phone);
        } else {
            $query->whereIn('phone', $variants);
        }

        if ($accountId !== false) {
            $query->where('account_id', $accountId);
        }

        // Exclude junk-status leads. Guard against an empty id set —
        // `whereNotIn('col', [])` compiles to a false predicate in some
        // grammars and would drop every row.
        $junkStatusIds = LeadStatuses::query()
            ->when($accountId !== false, fn (Builder $q): Builder => $q->where('account_id', $accountId))
            ->where('is_junk', 1)
            ->pluck('id');
        if ($junkStatusIds->isNotEmpty()) {
            $query->whereNotIn('lead_status_id', $junkStatusIds->all());
        }

        return $query->orderByDesc('id')->first();
    }
}
