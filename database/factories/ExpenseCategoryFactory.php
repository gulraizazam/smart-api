<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CashFlow\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseCategory>
 */
class ExpenseCategoryFactory extends Factory
{
    protected $model = ExpenseCategory::class;

    public function definition(): array
    {
        return [
            'account_id' => 1,
            'name' => $this->faker->unique()->randomElement([
                'Rent', 'Utilities', 'Salaries', 'Equipment', 'Supplies',
                'Marketing', 'Travel', 'Office', 'Repairs', 'Insurance',
            ]),
            'description' => $this->faker->sentence(),
            'vendor_emphasis' => 0,
            'is_active' => 1,
            'sort_order' => $this->faker->numberBetween(1, 100),
        ];
    }
}
