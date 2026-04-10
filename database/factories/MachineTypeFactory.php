<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MachineType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MachineType>
 */
class MachineTypeFactory extends Factory
{
    protected $model = MachineType::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'active' => 1,
            'account_id' => 1,
        ];
    }
}
