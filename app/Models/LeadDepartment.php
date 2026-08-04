<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Medical / service department a lead is interested in (Skin / Hair / Aesthetics / …).
 *
 * Managed via the admin CRUD under /lead-departments and referenced by
 * {@see Leads::department_id}. Separate from HR {@see Department}, which
 * describes where employees work.
 */
class LeadDepartment extends BaseModel
{
    use SoftDeletes;

    protected $table = 'lead_departments';

    public static string $_table = 'lead_departments';

    protected $fillable = [
        'name',
        'sort_order',
        'active',
        'account_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function leads(): HasMany
    {
        return $this->hasMany(Leads::class, 'department_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Accounts::class, 'account_id');
    }
}
