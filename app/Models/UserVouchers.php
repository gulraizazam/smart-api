<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserVouchers extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'voucher_id',
        'amount',
        'total_amount',
    ];

    #[\Override]
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
