<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderDetail>
 */
class OrderDetailFactory extends Factory
{
    protected $model = OrderDetail::class;

    public function definition(): array
    {
        $price = $this->faker->randomFloat(2, 100, 5000);

        return [
            'order_id' => Order::factory(),
            'product_id' => Product::factory(),
            'quantity' => 1,
            'sale_price' => $price,
            'discount_price' => 0,
            'sale_price_after_discount' => $price,
            'order_type' => 'sale',
            'account_id' => 1,
        ];
    }
}
