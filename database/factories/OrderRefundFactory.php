<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Locations;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\Patients;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderRefund>
 */
class OrderRefundFactory extends Factory
{
    protected $model = OrderRefund::class;

    public function definition(): array
    {
        return [
            'account_id' => 1,
            'order_id' => Order::factory(),
            'patient_id' => Patients::factory(),
            'total_price' => $this->faker->randomFloat(2, 100, 5000),
            'status' => 'completed',
            'created_by' => User::factory(),
            'location_id' => Locations::factory(),
            'payment_mode' => 'cash',
            'quantity' => 1,
        ];
    }
}
