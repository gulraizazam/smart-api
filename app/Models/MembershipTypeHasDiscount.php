<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipTypeHasDiscount extends Model
{
    protected $table = 'membership_type_has_discounts';

    protected $fillable = [
        'membership_type_id',
        'discount_id',
        'created_by',
    ];

    // ── Relationships ────────────────────────────────────

    public function membershipType(): BelongsTo
    {
        return $this->belongsTo(MembershipType::class, 'membership_type_id');
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class, 'discount_id');
    }
}
