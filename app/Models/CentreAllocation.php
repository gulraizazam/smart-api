<?php

declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CentreAllocation extends Model
{
    use HasFactory;
    protected $fillable = ['product_id', 'centre_id', 'quantity'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function centre(): BelongsTo
    {
        return $this->belongsTo(Locations::class,'centre_id');
    }
}
