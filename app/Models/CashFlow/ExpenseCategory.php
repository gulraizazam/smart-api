<?php

declare(strict_types=1);
namespace App\Models\CashFlow;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExpenseCategory extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'expense_categories';

    protected $fillable = [
        'account_id', 'name', 'description', 'vendor_emphasis', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'vendor_emphasis' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Expenses under this category.
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'category_id');
    }

    // Scopes

    public function scopeActive($query): Builder
    {
        return $query->where('is_active', 1);
    }

    public function scopeForAccount($query, int $accountId): Builder
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeSorted($query): Builder
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }
}
