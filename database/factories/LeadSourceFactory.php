<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LeadSources;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadSources>
 */
class LeadSourceFactory extends Factory
{
    protected $model = LeadSources::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement([
                'Walk-in', 'Facebook', 'Instagram', 'Google', 'Referral', 'Phone',
            ]),
            'account_id' => 1,
            'sort_no' => $this->faker->numberBetween(1, 100),
            'active' => 1,
        ];
    }
}
