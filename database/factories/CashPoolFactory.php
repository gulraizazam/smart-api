<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CashFlow\CashPool;
use App\Models\Locations;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashPool>
 *
 * Default state is a branch-scoped cash pool. The audit hardened CashPool to
 * cache balances; tests that mutate balances should re-fetch the model after
 * dispatching the operation rather than asserting on a stale instance.
 */
class CashPoolFactory extends Factory
{
    protected $model = CashPool::class;

    public function definition(): array
    {
        return [
            'account_id' => 1,
            // Production schema enum is ('branch_cash','head_office_cash','bank_account')
            // — see CashPool::TYPE_BRANCH_CASH. Default to branch.
            'type' => CashPool::TYPE_BRANCH_CASH,
            'location_id' => Locations::factory(),
            'name' => $this->faker->unique()->company().' Cash',
            'opening_balance' => 0,
            'cached_balance' => 0,
            'is_active' => 1,
            'opening_balance_frozen' => 0,
        ];
    }

    public function withOpeningBalance(float $amount): static
    {
        return $this->state(fn (array $a): array => [
            'opening_balance' => $amount,
            'cached_balance' => $amount,
        ]);
    }
}
