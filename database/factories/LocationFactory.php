<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Locations;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Locations>
 */
class LocationFactory extends Factory
{
    protected $model = Locations::class;

    public function definition(): array
    {
        // locations.slug is varchar(40) in production. Faker company names
        // can be long enough to push the slug past that limit, so cap the
        // base before slugifying and use a deterministic suffix.
        $name = $this->faker->unique()->company().' Centre';
        $slugBase = substr(Str::slug($name), 0, 25);
        $slug = $slugBase.'-'.$this->faker->unique()->numberBetween(1000, 999999);

        return [
            'name' => $name,
            'slug' => $slug,
            'fdo_name' => $this->faker->name(),
            'fdo_phone' => $this->faker->numerify('+92##########'),
            'address' => $this->faker->address(),
            'account_id' => 1,
            // city_id / region_id are NOT NULL with no default in production.
            // Tests rarely care about geographic hierarchy, so the factory
            // pins them to 1 — fixtures that need real values can override.
            'city_id' => 1,
            'region_id' => 1,
            'active' => 1,
            'tax_percentage' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'active' => 0,
        ]);
    }
}
