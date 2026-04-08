<?php

declare(strict_types=1);
namespace App\Models\CashFlow;

use App\Enums\RequestStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryRequest extends Model
{
    protected $table = 'category_requests';

    protected $fillable = [
        'account_id', 'name', 'description',
        'requested_by', 'status', 'admin_notes', 'category_id',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'status' => RequestStatus::class,
        ];
    }

    // Status enum: App\Enums\RequestStatus

    /**
     * User who requested the category.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by')->withTrashed();
    }

    /**
     * Linked category if approved.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id')->withTrashed();
    }

    // Scopes

    public function scopeForAccount($query, int $accountId): Builder
    {
        return $query->where('account_id', $accountId);
    }

    public function scopePending($query): Builder
    {
        return $query->where('status', RequestStatus::Pending);
    }
}
