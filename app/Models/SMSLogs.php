<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SMSLogs extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'to', 'text', 'mask', 'status', 'sms_data', 'error_msg', 'log_type',
        'appointment_id','invoice_id','package_id','is_refund', 'lead_id', 'created_by', 'created_at', 'updated_at'
    ];

    protected $table = 'sms_logs';

    /**
     * Get the Leads for Lead Source.
     */
    public function appointments()
    {
        return $this->belongsTo('App\Models\Appointments', 'appointment_id');
    }
}
