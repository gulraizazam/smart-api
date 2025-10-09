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
        'amount'
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }
}
