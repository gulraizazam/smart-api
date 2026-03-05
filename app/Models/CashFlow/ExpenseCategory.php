<?php

namespace App\Models\CashFlow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExpenseCategory extends Model
{
    use SoftDeletes;

    protected $table = 'expense_categories';

    protected $fillable = [
        'account_id', 'name', 'description', 'vendor_emphasis', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'vendor_emphasis' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Expenses under this category.
     */
    public function expenses()
    {
        return $this->hasMany(Expense::class, 'category_id');
    }

    // Scopes

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    public function scopeSorted($query)
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('name', 'asc');
    }
}
