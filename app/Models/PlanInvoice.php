<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlanInvoice extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'plan_invoices';

    protected $fillable = [
        'invoice_number',
        'total_price',
        'account_id',
        'patient_id',
        'created_by',
        'location_id',
        'payment_mode_id',
        'active',
        'package_id',
        'invoice_type',
        
    ];

    protected $casts = [
        'total_price' => 'decimal:2',
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the patient that owns the invoice.
     */
    public function patient()
    {
        return $this->belongsTo(Patient::class, 'patient_id');
    }

    /**
     * Get the location of the invoice.
     */
    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    /**
     * Get the account of the invoice.
     */
    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Get the payment mode of the invoice.
     */
    public function paymentMode()
    {
        return $this->belongsTo(PaymentMode::class, 'payment_mode_id');
    }

    /**
     * Scope for taxable invoices.
     */
    public function scopeTaxable($query)
    {
        return $query->where('invoice_type', 'exempt');
    }

    /**
     * Scope for non-taxable invoices.
     */
    public function scopeNonTaxable($query)
    {
        return $query->where('invoice_type', 'taxable');
    }

    /**
     * Scope for active invoices.
     */
    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }

    /**
     * Generate invoice number format: patient_id-package_id-sequence
     */
    public static function generateInvoiceNumber(int $patientId, int $packageId): string
    {
        $existingCount = self::where('patient_id', $patientId)
            ->where('package_id', $packageId)
            ->count();

        $sequence = str_pad($existingCount + 1, 2, '0', STR_PAD_LEFT);
        
        return "{$patientId}-{$packageId}-{$sequence}";
    }
}
