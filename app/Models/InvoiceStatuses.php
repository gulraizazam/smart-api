<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use App\Models\AuditTrails;
use Auth;

class InvoiceStatuses extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'account_id','created_at', 'updated_at','account_id'];

    protected $table = 'invoice_statuses';

    /**
     * Get the invoice.
     */
    public function invoice()
    {
        return $this->hasMany('App\Models\Invoices', 'invoice_status_id');
    }
    /*Relation for audit trail*/
    public function audit_field_before()
    {
        return $this->hasMany('App\Models\AuditTrailChanges','field_before');
    }
    public function audit_field_after()
    {
        return $this->hasMany('App\Models\AuditTrailChanges','field_after');
    }
    /*end*/


}
