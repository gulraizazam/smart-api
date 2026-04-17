<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageVouchers extends Model
{
    use HasFactory;

    protected $fillable = [
        'package_random_id',
        'package_id',
        'voucher_id',
        'user_id',
        'amount',
        'service_id',
        'main_service_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Discounts::class, 'voucher_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Packages::class, 'package_id');
    }
}
