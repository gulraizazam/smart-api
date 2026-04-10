<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\StaffAdvance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffAdvance>
 */
class StaffAdvanceFactory extends Factory
{
    protected $model = StaffAdvance::class;

    public function definition(): array
    {
        return [
            'account_id' => 1,
            'user_id' => User::factory(),
            'pool_id' => CashPool::factory(),
            'amount' => $this->faker->randomFloat(2, 100, 5000),
            'description' => $this->faker->sentence(),
            'created_by' => User::factory(),
        ];
    }

    public function voided(): static
    {
        return $this->state(fn (array $a): array => [
            'voided_at' => now(),
            'voided_by' => User::factory(),
            'void_reason' => 'Returned in full',
        ]);
    }
}
