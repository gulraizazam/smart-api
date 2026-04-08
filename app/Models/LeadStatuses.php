<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

class LeadStatuses extends BaseModel
{
    use SoftDeletes;

    protected $table = 'lead_statuses';

    public static string $_table = 'lead_statuses';

    protected $fillable = [
        'name', 'parent_id', 'account_id', 'is_comment', 'is_default',
        'is_booked', 'is_arrived', 'is_converted', 'sort_no', 'active',
        'created_at', 'updated_at', 'is_junk',
    ];

    public static array $_fillable = [
        'name', 'active', 'parent_id', 'is_comment', 'is_default',
        'is_booked', 'is_arrived', 'is_converted', 'is_junk',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'is_comment' => 'boolean',
            'is_default' => 'boolean',
            'is_booked' => 'boolean',
            'is_arrived' => 'boolean',
            'is_converted' => 'boolean',
            'is_junk' => 'boolean',
            'parent_id' => 'integer',
            'sort_no' => 'integer',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function leads(): HasMany
    {
        return $this->hasMany(Leads::class, 'lead_status_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
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

    public function scopeParentsOnly(Builder $query): Builder
    {
        return $query->where('parent_id', 0);
    }

    // =========================================================================
    // Query Helpers (used by other modules — Patients, Leads, Appointments)
    // =========================================================================

    public static function getActiveSorted(
        array|int|false $skipIds = false,
        array|int|false $includeIds = false,
        int|false $accountId = false,
    ): \Illuminate\Support\Collection {
        $query = self::active();

        if ($skipIds) {
            $query->whereNotIn('id', (array) $skipIds);
        }
        if ($includeIds) {
            $query->whereIn('id', (array) $includeIds);
        }

        return $query->sorted()->pluck('name', 'id');
    }

    public static function getActiveOnly(): Collection
    {
        return self::active()->sorted()->get();
    }

    public static function getLeadStatuses(int|false $excludeIds = false): \Illuminate\Support\Collection
    {
        $query = self::forAccount()->active()->parentsOnly();

        if ($excludeIds) {
            $query->whereNotIn('id', (array) $excludeIds);
        }

        return $query->sorted()->pluck('name', 'id');
    }

    public static function getParentRecords(
        string|false $prependText,
        int $accountId,
        array|int $skipIds = [],
        bool $activeOnly = false,
    ): \Illuminate\Support\Collection {
        $query = self::where('account_id', $accountId)->parentsOnly();

        if ($activeOnly) {
            $query->active();
        }

        $skipIds = (array) $skipIds;
        if (!empty($skipIds)) {
            $query->whereNotIn('id', $skipIds);
        }

        $records = $query->pluck('name', 'id');

        if ($prependText) {
            $records->prepend($prependText, '');
        }

        return $records;
    }

    public static function getAllRecordsDictionary(int $accountId): array
    {
        return self::where('account_id', $accountId)->get()->getDictionary();
    }

    public static function isChildExists(int $id, int $accountId): int
    {
        return self::where(['parent_id' => $id, 'account_id' => $accountId])->count();
    }
}
