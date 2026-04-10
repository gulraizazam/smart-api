<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CashFlow\Vendor;
use App\Models\CashFlow\VendorTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorTransaction>
 */
class VendorTransactionFactory extends Factory
{
    protected $model = VendorTransaction::class;

    public function definition(): array
    {
        return [
            'account_id' => 1,
            'vendor_id' => Vendor::factory(),
            'type' => 'purchase',
            'status' => 'delivered',
            'amount' => $this->faker->randomFloat(2, 100, 10000),
            'description' => $this->faker->sentence(),
            'reference_no' => $this->faker->bothify('VT-####'),
            'transaction_date' => $this->faker->date('Y-m-d'),
            'is_for_general' => 0,
            'created_by' => User::factory(),
        ];
    }

    public function payment(): static
    {
        return $this->state(fn (array $a): array => ['type' => 'payment']);
    }

    public function ordered(): static
    {
        return $this->state(fn (array $a): array => ['status' => 'ordered']);
    }

    public function delivered(): static
    {
        return $this->state(fn (array $a): array => ['status' => 'delivered']);
    }
}
