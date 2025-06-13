<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CentreAllocation extends Model
{
    use HasFactory;
    protected $fillable = ['product_id', 'centre_id', 'quantity'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function centre()
    {
        return $this->belongsTo(Locations::class,'centre_id');
    }
}
