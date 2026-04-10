<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Discount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Discount>
 */
class DiscountFactory extends Factory
{
    protected $model = Discount::class;

    public function definition(): array
    {
        $name = 'Discount '.$this->faker->unique()->word();

        // `discounts.type` is ENUM('Fixed','Percentage','Configurable')
        // — strictly title-cased. Lowercase 'fixed' worked accidentally
        // under the case-insensitive collation for enum lookup, but
        // 'percent' is not in the allowed set and triggers a
        // "Data truncated for column 'type'" warning. Use the exact
        // enum value so tests don't quietly drop rows.
        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1000, 999999),
            'type' => 'Fixed',
            'amount' => $this->faker->randomFloat(2, 50, 500),
            'discount_type' => 'general',
            'pre_days' => 0,
            'post_days' => 0,
            'start' => now()->subDay(),
            'end' => now()->addMonth(),
            'active' => 1,
            'account_id' => 1,
        ];
    }

    public function percentage(): static
    {
        return $this->state(fn (array $a): array => [
            'type' => 'Percentage',
            'amount' => $this->faker->numberBetween(5, 30),
        ]);
    }
}
