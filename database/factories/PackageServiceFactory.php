<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Packages;
use App\Models\PackageService;
use App\Models\Services;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PackageService>
 */
class PackageServiceFactory extends Factory
{
    protected $model = PackageService::class;

    public function definition(): array
    {
        $price = $this->faker->randomFloat(2, 100, 5000);

        return [
            'random_id' => (string) Str::uuid(),
            'package_id' => Packages::factory(),
            'service_id' => Services::factory(),
            'is_consumed' => 0,
            'price' => $price,
            'orignal_price' => $price,
            'actual_price' => $price,
            'is_exclusive' => 0,
            'tax_exclusive_price' => $price,
            'tax_percentage' => 0,
            'tax_price' => 0.00,
            'tax_including_price' => $price,
        ];
    }

    public function consumed(): static
    {
        return $this->state(fn (array $a): array => [
            'is_consumed' => 1,
            'consumed_at' => now(),
        ]);
    }
}
