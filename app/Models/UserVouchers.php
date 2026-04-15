<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * `amount` is the REMAINING credit on the voucher (decremented as
 * the patient redeems against services). `total_amount` is the
 * ORIGINAL face value at issuance and is only mutated by an
 * explicit UserVoucherService::update — never by redemption.
 */
class UserVouchers extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'voucher_id',
        'amount',
        'total_amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Discounts::class, 'voucher_id')
            ->where('discount_type', 'voucher');
    }

    public function packageVouchers(): HasMany
    {
        return $this->hasMany(PackageVouchers::class, 'voucher_id', 'voucher_id')
            ->where('user_id', $this->user_id);
    }
}
