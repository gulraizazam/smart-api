<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\DecryptLegacyOnRead;
use App\Enums\EmploymentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeDetail extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'department_id',
        'designation_id',
        'reporting_manager_id',
        'hire_date',
        'employment_type',
        'shift_hours',
        'salary',
        'bank_name',
        'bank_account_number',
        'tax_id',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'address',
        'account_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'employment_type' => EmploymentType::class,
            'salary' => 'decimal:2',
            'shift_hours' => 'decimal:2',
            // Plaintext (encryption removed 2026-06-04 per product decision).
            // DecryptLegacyOnRead reads any leftover legacy ciphertext during
            // the rollout (degrading to raw instead of throwing like the
            // built-in `encrypted` cast) and writes plaintext.
            'bank_account_number' => DecryptLegacyOnRead::class,
        ];
    }

    // ── Relationships ──

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function reportingManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporting_manager_id')->withTrashed();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    // ── Scopes ──

    public function scopeForAccount(Builder $query, int $accountId): Builder
    {
        return $query->where('account_id', $accountId);
    }
}
