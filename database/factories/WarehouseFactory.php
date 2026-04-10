<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company().' Warehouse',
            'manager_name' => $this->faker->name(),
            'manager_phone' => $this->faker->numerify('+92##########'),
            'account_id' => 1,
            'address' => $this->faker->address(),
            'active' => 1,
        ];
    }
}
