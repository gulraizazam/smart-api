<?php

declare(strict_types=1);
namespace App\Models;

use App\Helpers\GeneralFunctions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SMSLogs extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'to', 'text', 'mask', 'status', 'sms_data', 'error_msg', 'log_type',
        'appointment_id', 'invoice_id', 'package_id', 'is_refund', 'lead_id', 'created_by', 'created_at', 'updated_at',
    ];

    protected $table = 'sms_logs';

    /**
     * Get the Leads for Lead Source.
     */
    public function appointments(): BelongsTo
    {
        return $this->belongsTo(Appointments::class, 'appointment_id');
    }

    public function getToAttribute($value)
    {
        return GeneralFunctions::prepareNumber4Call(GeneralFunctions::cleanNumber($value));
    }
}
