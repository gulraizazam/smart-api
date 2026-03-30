<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    protected function casts(): array
    {
        return [
            'total_price'  => 'decimal:2',
            'active'       => 'boolean',
            'invoice_type' => InvoiceType::class,
            'created_at'   => 'datetime',
            'updated_at'   => 'datetime',
            'deleted_at'   => 'datetime',
        ];
    }

    // ── Relationships ───────────────────────────────────

    public function patient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Locations::class, 'location_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_id');
    }

    public function paymentMode(): BelongsTo
    {
        return $this->belongsTo(PaymentModes::class, 'payment_mode_id');
    }

    public function packageAdvance(): BelongsTo
    {
        return $this->belongsTo(PackageAdvances::class, 'package_advance_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Packages::class, 'package_id');
    }

    // ── Scopes ──────────────────────────────────────────

    public function scopeTaxable(Builder $query): Builder
    {
        return $query->where('invoice_type', InvoiceType::Taxable);
    }

    public function scopeNonTaxable(Builder $query): Builder
    {
        return $query->where('invoice_type', InvoiceType::Exempt);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    // ── Invoice Number Generation ───────────────────────

    /**
     * Generate invoice number: {patient_id}-{package_id}-{sequence}
     */
    public static function generateInvoiceNumber(int $patientId, int $packageId): string
    {
        $prefix = "{$patientId}-{$packageId}-";

        $maxSequence = self::withTrashed()
            ->where('patient_id', $patientId)
            ->where('package_id', $packageId)
            ->where('invoice_number', 'like', $prefix . '%')
            ->get()
            ->map(function (self $invoice) use ($prefix): int {
                if (str_starts_with($invoice->invoice_number, $prefix)) {
                    return (int) substr($invoice->invoice_number, strlen($prefix));
                }

                return 0;
            })
            ->max() ?? 0;

        $sequence = str_pad((string) ($maxSequence + 1), 2, '0', STR_PAD_LEFT);

        return "{$prefix}{$sequence}";
    }
}
