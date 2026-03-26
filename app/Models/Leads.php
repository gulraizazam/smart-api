<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Leads extends BaseModal
{
    use SoftDeletes;

    protected $fillable = [
        'patient_id', 'region_id', 'city_id', 'lead_status_id', 'lead_source_id',
        'msg_count', 'active', 'created_by', 'updated_by', 'converted_by',
        'town_id', 'created_at', 'updated_at', 'account_id', 'location_id',
        'name', 'email', 'phone', 'gender', 'referred_by', 'meta_lead_id',
    ];

    protected static array $_fillable = [
        'region_id', 'city_id', 'lead_status_id', 'lead_source_id',
        'msg_count', 'service_id', 'town_id',
    ];

    protected $table = 'leads';

    protected static string $_table = 'leads';

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
    // Search Methods (kept for backward compatibility - used by API controller)
    // =========================================================================

    /**
     * Search leads by phone
     * @deprecated Use LeadService::searchLeadsByPhone() instead
     */
    public static function getLeadPhoneAjax(string $phone, int $account_id)
    {
        return self::where('active', 1)
            ->where('account_id', $account_id)
            ->where('phone', 'LIKE', "%{$phone}%")
            ->select('name', 'id', 'phone')
            ->limit(50)
            ->get();
    }

    /**
     * Search leads by ID or name
     * @deprecated Use LeadService::searchLeadsById() instead
     */
    public static function getLeadidAjax(string $name, int $account_id)
    {
        $cleaned = str_replace([' ', '-', '+', 'C-', 'c-'], '', $name);

        if (is_numeric($cleaned)) {
            $phone = ltrim($cleaned, '0');
            if (str_starts_with($phone, '92')) {
                $phone = substr($phone, 2);
            }

            $phoneVariations = array_unique([$cleaned, $phone, '0' . $phone]);

            $exact_match = self::select('name', 'id', 'phone')
                ->where('account_id', $account_id)
                ->whereIn('phone', $phoneVariations)
                ->limit(10)
                ->get();

            if ($exact_match->isNotEmpty()) {
                return $exact_match;
            }

            return self::select('name', 'id', 'phone')
                ->where('account_id', $account_id)
                ->where(function ($query) use ($phoneVariations) {
                    foreach ($phoneVariations as $variation) {
                        $query->orWhere('phone', 'LIKE', $variation . '%');
                    }
                })
                ->groupBy('phone', 'name', 'id')
                ->orderByDesc('id')
                ->limit(10)
                ->get();
        }

        if (is_numeric($name) && strlen($name) <= 10) {
            return self::select('name', 'id', 'phone')
                ->where('account_id', $account_id)
                ->where('id', $name)
                ->limit(10)
                ->get();
        }

        return self::select('name', 'id', 'phone')
            ->where('account_id', $account_id)
            ->where('name', 'LIKE', $name . '%')
            ->groupBy('phone', 'name', 'id')
            ->orderByDesc('id')
            ->limit(10)
            ->get();
    }
}
