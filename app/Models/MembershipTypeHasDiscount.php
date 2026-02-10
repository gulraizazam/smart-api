<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MembershipTypeHasDiscount extends Model
{
    use HasFactory;

    protected $table = 'membership_type_has_discounts';

    protected $fillable = [
        'membership_type_id',
        'discount_id',
        'created_by',
    ];

    public function membershipType()
    {
        return $this->belongsTo(MembershipType::class, 'membership_type_id');
    }

    public function discount()
    {
        return $this->belongsTo(Discounts::class, 'discount_id');
    }
}
