<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AppointmentTypes;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentTypes>
 */
class AppointmentTypeFactory extends Factory
{
    protected $model = AppointmentTypes::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement(['Treatment', 'Consultancy', 'Follow-up', 'Walk-in']),
            'active' => 1,
            'account_id' => 1,
        ];
    }
}
