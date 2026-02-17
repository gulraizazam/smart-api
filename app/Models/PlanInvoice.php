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
        'package_advance_id',
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
     * Get the package advance that this invoice is linked to.
     */
    public function packageAdvance()
    {
        return $this->belongsTo(PackageAdvances::class, 'package_advance_id');
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
     * Finds the maximum sequence number from existing invoices and increments it
     */
    public static function generateInvoiceNumber(int $patientId, int $packageId): string
    {
        $prefix = "{$patientId}-{$packageId}-";
        
        // Get the maximum sequence number from existing invoices (including soft-deleted)
        $maxSequence = self::withTrashed()
            ->where('patient_id', $patientId)
            ->where('package_id', $packageId)
            ->where('invoice_number', 'like', $prefix . '%')
            ->get()
            ->map(function ($invoice) use ($prefix) {
                // Extract the sequence number from the invoice_number
                $invoiceNumber = $invoice->invoice_number;
                if (strpos($invoiceNumber, $prefix) === 0) {
                    $sequencePart = substr($invoiceNumber, strlen($prefix));
                    return (int) $sequencePart;
                }
                return 0;
            })
            ->max() ?? 0;

        $sequence = str_pad($maxSequence + 1, 2, '0', STR_PAD_LEFT);
        
        return "{$patientId}-{$packageId}-{$sequence}";
    }
}
