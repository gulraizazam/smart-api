<?php

declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Accounts extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'created_at', 'updated_at', 'suspended'];

    protected $table = 'accounts';

    /*Relation for audit trail*/
    public function audit_field_before(): HasMany
    {
        return $this->hasMany(AuditTrailChanges::class, 'field_before');
    }

    public function audit_field_after(): HasMany
    {
        return $this->hasMany(AuditTrailChanges::class, 'field_after');
    }
    /*end*/
}
