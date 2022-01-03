<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends \Spatie\Permission\Models\Permission
{
    use HasFactory;

    protected $fillable=[
        'name',
        'title',
        'main_group',
        'parent_id',
        'status'
    ];

    public function parent() {
        return $this->belongsTo(static::class);
    }
}
