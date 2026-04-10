<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\Packages;
use App\Models\Patients;
use App\Models\PaymentModes;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PackageAdvances>
 *
 * Default state is an `in` flow (cash collected from the patient). Use the
 * `out` state for refunds. The `cash_amount` is small (100–500) so a typical
 * test can sum several advances without overflowing the package's
 * `total_price`.
 */
class PackageAdvanceFactory extends Factory
{
    protected $model = PackageAdvances::class;

    public function definition(): array
    {
        return [
            'cash_flow' => 'in',
            'cash_amount' => $this->faker->randomFloat(2, 100, 500),
            'active' => 1,
            'patient_id' => Patients::factory(),
            'payment_mode_id' => PaymentModes::factory(),
            'account_id' => 1,
            'appointment_type_id' => 1,
            'location_id' => Locations::factory(),
            'created_by' => User::factory(),
            'package_id' => Packages::factory(),
        ];
    }

    public function out(): static
    {
        return $this->state(fn (array $a): array => [
            'cash_flow' => 'out',
        ]);
    }
}
