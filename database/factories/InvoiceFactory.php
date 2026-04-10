<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invoices;
use App\Models\Locations;
use App\Models\Patients;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoices>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoices::class;

    public function definition(): array
    {
        // doctor_id is NOT NULL with no default in the production schema even
        // though it is FK-soft (the column accepts any user_id without an FK
        // constraint in some installations). Use a UserFactory in doctor()
        // state so the FK is satisfied for tests that *do* have one.
        return [
            'total_price' => $this->faker->randomFloat(2, 500, 50000),
            'account_id' => 1,
            'patient_id' => Patients::factory(),
            'invoice_status_id' => 1,
            'active' => 1,
            'is_exclusive' => 0,
            'created_by' => User::factory(),
            'doctor_id' => User::factory()->doctor(),
            'location_id' => Locations::factory(),
            'is_settlement' => 0,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $a): array => [
            'invoice_status_id' => 2,
            'active' => 0,
        ]);
    }
}
