<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Locations;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true),
            'account_id' => 1,
            'brand_id' => Brand::factory(),
            'location_id' => Locations::factory(),
            'warehouse_id' => Warehouse::factory(),
            'sale_price' => $this->faker->randomFloat(2, 100, 5000),
            'product_type' => 'sale',
            'status' => 1,
            'created_by' => User::factory(),
            'sku' => $this->faker->unique()->bothify('SKU-####-???'),
        ];
    }
}
