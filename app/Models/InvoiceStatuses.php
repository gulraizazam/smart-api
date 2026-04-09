<?php

declare(strict_types=1);
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class InvoiceStatuses extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'account_id', 'created_at', 'updated_at', 'account_id'];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Model $model): void {
            if (Auth::check() && in_array('account_id', $model->getFillable(), true)) {
                $model->account_id = Auth::user()->account_id;
            }
        });
    }

    protected $table = 'invoice_statuses';

    /**
     * Get the invoice.
     */
    public function invoice(): HasMany
    {
        return $this->hasMany(Invoices::class, 'invoice_status_id');
    }

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
