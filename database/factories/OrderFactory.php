<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Locations;
use App\Models\Order;
use App\Models\Patients;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patients::factory(),
            'location_id' => Locations::factory(),
            'warehouse_id' => Warehouse::factory(),
            'total_price' => $this->faker->randomFloat(2, 500, 20000),
            'order_type' => 'sale',
            'payment_mode' => 'cash',
            'created_by' => User::factory(),
            'account_id' => 1,
            'status' => 1,
            'quantity' => $this->faker->numberBetween(1, 10),
        ];
    }
}
