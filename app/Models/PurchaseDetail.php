<?php

declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_id',
        'product_id',
        'purchase_id',
        'purchase_price',
        'total_purchase_price',
        'quantity',
    ];
}
