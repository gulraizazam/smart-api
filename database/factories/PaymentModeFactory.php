<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PaymentModes;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentModes>
 */
class PaymentModeFactory extends Factory
{
    protected $model = PaymentModes::class;

    public function definition(): array
    {
        return [
            'account_id' => 1,
            'name' => $this->faker->unique()->randomElement([
                'Cash', 'Card', 'Cheque', 'Bank Transfer', 'Online', 'EasyPaisa',
            ]),
            'active' => 1,
            'sort_number' => $this->faker->numberBetween(1, 100),
        ];
    }

    public function cash(): static
    {
        return $this->state(fn (array $attributes): array => [
            'name' => 'Cash',
        ]);
    }
}
