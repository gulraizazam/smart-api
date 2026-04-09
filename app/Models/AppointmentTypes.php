<?php

declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class AppointmentTypes extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'active', 'created_at', 'updated_at', 'account_id'];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Model $model): void {
            if (Auth::check() && in_array('account_id', $model->getFillable(), true)) {
                $model->account_id = Auth::user()->account_id;
            }
        });
    }

    protected $table = 'appointment_types';

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

    /**
     * Get All Records
     *
     * @param  (int)  $account_id Current Organization's ID
     * @return (mixed)
     */
    public static function getAllRecords($account_id)
    {
        return self::where(['account_id' => $account_id])->get();
    }
}
