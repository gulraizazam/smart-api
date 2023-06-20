<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class LeadStatusesFactory extends Factory
{
    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'sort_no' => $this->faker->numberBetween(1, 100),
            'created_at' => \Carbon\Carbon::now(),
            'updated_at' => \Carbon\Carbon::now(),
        ];
    }
}
