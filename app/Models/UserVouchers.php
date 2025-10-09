<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserVouchers extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'voucher_id',
        'amount',
        'total_amount'
    ];

    protected $casts = [
        'created_at' => 'datetime:F d,Y h:i A',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
   public function voucher()
    {
        return $this->belongsTo(Discounts::class, 'voucher_id', 'id')
            ->where('discount_type', 'voucher');
    }

    public function packageVouchers()
    {
        return $this->hasMany(PackageVouchers::class, 'voucher_id', 'voucher_id')
            ->where('user_id', $this->user_id);
    }
}
