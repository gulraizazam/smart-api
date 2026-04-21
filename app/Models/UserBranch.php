<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot record linking a user to a branch (location) for Management Dashboard
 * scoping. Empty assignment = company-wide view (per ResourceScopeResolver).
 */
class UserBranch extends BaseModel
{
    protected $table = 'user_branches';

    protected $fillable = [
        'account_id',
        'user_id',
        'location_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Locations::class, 'location_id');
    }
}
