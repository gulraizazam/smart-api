<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CashFlow\Vendor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vendor>
 */
class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    public function definition(): array
    {
        return [
            'account_id' => 1,
            'name' => $this->faker->unique()->company(),
            'contact_person' => $this->faker->name(),
            'phone' => $this->faker->numerify('+92##########'),
            'email' => $this->faker->unique()->safeEmail(),
            'address' => $this->faker->address(),
            // payment_terms is enum('upfront','net_7','net_15','net_30','custom')
            // in production. Cycle through the legal values rather than the
            // human-readable labels.
            'payment_terms' => $this->faker->randomElement(['upfront', 'net_7', 'net_15', 'net_30', 'custom']),
            'opening_balance' => 0,
            'cached_balance' => 0,
            'is_active' => 1,
            'created_by' => User::factory(),
        ];
    }
}
