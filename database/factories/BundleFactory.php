<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Bundles;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bundles>
 */
class BundleFactory extends Factory
{
    protected $model = Bundles::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'price' => $this->faker->randomFloat(2, 1000, 50000),
            'services_price' => $this->faker->randomFloat(2, 1000, 50000),
            'type' => 'bundle',
            'start' => now()->subDay(),
            'end' => now()->addMonth(),
            'apply_discount' => 0,
            'total_services' => $this->faker->numberBetween(1, 10),
            'active' => 1,
            'account_id' => 1,
        ];
    }
}
