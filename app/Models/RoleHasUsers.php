<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\Models\Role;

class RoleHasUsers extends Model
{
    use SoftDeletes;

    protected $table = 'role_has_users';

    protected $fillable = ['role_id', 'user_id'];

    public $timestamps = false;

    protected $casts = [
        'role_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
