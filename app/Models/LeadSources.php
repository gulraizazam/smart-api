<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class LeadSources extends BaseModel
{
    use SoftDeletes;

    protected $table = 'lead_sources';

    public static string $_table = 'lead_sources';

    protected $fillable = ['name', 'account_id', 'sort_no', 'active', 'created_at', 'updated_at'];

    public static array $_fillable = ['name', 'active'];

    #[\Override]
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_no' => 'integer',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function leads(): HasMany
    {
        return $this->hasMany(Leads::class, 'lead_source_id');
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

    public function scopeSorted(Builder $query): Builder
    {
        return $query->orderBy('sort_no');
    }

    // =========================================================================
    // Query Helpers (used by other modules — Appointments, Leads, etc.)
    // =========================================================================

    public static function getActiveSorted(): \Illuminate\Support\Collection
    {
        return self::forAccount()->active()->sorted()->pluck('name', 'id');
    }

    public static function getActiveOnly(): Collection
    {
        return self::active()->sorted()->get();
    }

    public static function getAllRecordsDictionary(int $accountId): array
    {
        return self::where('account_id', $accountId)->get()->getDictionary();
    }

    public static function isChildExists(int $id, int $accountId): bool
    {
        return false;
    }
}
