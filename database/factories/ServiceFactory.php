<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Services;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Services>
 */
class ServiceFactory extends Factory
{
    protected $model = Services::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->words(3, true);
        // slug is varchar(40) in production — keep a short unique suffix
        // and truncate the slugged name so generated slugs never exceed
        // the column width.
        $suffix = '-'.$this->faker->unique()->numberBetween(1000, 999999);
        $slug = substr(Str::slug($name), 0, 40 - strlen($suffix)).$suffix;

        return [
            'name' => $name,
            'slug' => $slug,
            'end_node' => 1,
            'complimentory' => 0,
            'account_id' => 1,
            'active' => 1,
            'duration' => $this->faker->randomElement([15, 30, 45, 60, 90]),
            'price' => $this->faker->randomFloat(2, 100, 10000),
            'description' => $this->faker->sentence(),
            'color' => $this->faker->hexColor(),
            // sort_no is tinyint(3) unsigned in production — must fit in 0..255.
            'sort_no' => $this->faker->numberBetween(1, 250),
            'sort_number' => $this->faker->numberBetween(1, 999),
            // tax_treatment_type_id is NOT NULL with no default in production.
            // 1 = standard taxable; tests that care about tax treatment
            // should override this field.
            'tax_treatment_type_id' => 1,
        ];
    }

    public function complimentary(): static
    {
        return $this->state(fn (array $attributes): array => [
            'complimentory' => 1,
            'price' => 0.00,
        ]);
    }
}
