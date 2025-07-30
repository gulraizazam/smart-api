<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Voucher;
use App\Models\Package;

class PackageVouchers extends Model
{
    use HasFactory;
    protected $fillable = [
        'package_id',
        'voucher_id',
        'user_id',
        'amount',
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }
    public function package()
    {
        return $this->belongsTo(Package::class);
    }
}
