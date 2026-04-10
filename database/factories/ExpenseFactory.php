<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\Expense;
use App\Models\CashFlow\ExpenseCategory;
use App\Models\Locations;
use App\Models\PaymentModes;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 *
 * Default state is `pending`. Use `approved()`, `rejected()`, `voided()`,
 * `flagged()` to move through the lifecycle. The audit added validation that
 * pending expenses cannot be edited after approval — tests that exercise the
 * approval pipeline should chain state methods rather than re-saving.
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'account_id' => 1,
            'expense_date' => $this->faker->date('Y-m-d'),
            'amount' => $this->faker->randomFloat(2, 100, 10000),
            'category_id' => ExpenseCategory::factory(),
            'paid_from_pool_id' => CashPool::factory(),
            'for_branch_id' => Locations::factory(),
            'payment_method_id' => PaymentModes::factory(),
            'description' => $this->faker->sentence(),
            'reference_no' => $this->faker->bothify('REF-####-???'),
            'notes' => $this->faker->optional()->sentence(),
            'status' => 'pending',
            'is_flagged' => 0,
            'created_by' => User::factory(),
            'is_for_general' => 0,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $a): array => [
            'status' => 'approved',
            'verified_by' => User::factory(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $a): array => [
            'status' => 'rejected',
            'rejection_reason' => 'Insufficient documentation',
            'verified_by' => User::factory(),
        ]);
    }

    public function voided(): static
    {
        return $this->state(fn (array $a): array => [
            'voided_at' => now(),
            'voided_by' => User::factory(),
            'void_reason' => 'Duplicate entry',
        ]);
    }

    public function flagged(): static
    {
        return $this->state(fn (array $a): array => [
            'is_flagged' => 1,
            'flag_reason' => 'Amount above threshold',
        ]);
    }
}
