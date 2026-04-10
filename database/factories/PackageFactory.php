<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Locations;
use App\Models\Packages;
use App\Models\Patients;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Packages>
 */
class PackageFactory extends Factory
{
    protected $model = Packages::class;

    public function definition(): array
    {
        return [
            'random_id' => (string) Str::uuid(),
            'name' => $this->faker->words(3, true),
            'plan_name' => $this->faker->word(),
            'sessioncount' => $this->faker->numberBetween(1, 12),
            'total_price' => $this->faker->randomFloat(2, 1000, 100000),
            'is_exclusive' => 0,
            // Packages::$casts maps plan_type to App\Enums\PlanType which
            // only accepts 'plan' | 'bundle' | 'membership'. The legacy
            // value 'package' is not in the enum.
            'plan_type' => 'plan',
            'account_id' => 1,
            'patient_id' => Patients::factory(),
            'active' => 1,
            'location_id' => Locations::factory(),
        ];
    }
}
