<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AppointmentStatuses;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppointmentStatuses>
 */
class AppointmentStatusFactory extends Factory
{
    protected $model = AppointmentStatuses::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->word(),
            'sort_no' => $this->faker->numberBetween(1, 100),
            'active' => 1,
            'account_id' => 1,
            'is_default' => 0,
            'is_cancelled' => 0,
            'is_arrived' => 0,
            'is_converted' => 0,
            'is_unscheduled' => 0,
        ];
    }
}
