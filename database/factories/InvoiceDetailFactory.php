<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InvoiceDetails;
use App\Models\Invoices;
use App\Models\Services;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceDetails>
 */
class InvoiceDetailFactory extends Factory
{
    protected $model = InvoiceDetails::class;

    public function definition(): array
    {
        $servicePrice = $this->faker->randomFloat(2, 100, 10000);

        return [
            'invoice_id' => Invoices::factory(),
            'service_id' => Services::factory(),
            'qty' => 1,
            'discount_type' => null,
            'discount_price' => 0.00,
            'service_price' => $servicePrice,
            'net_amount' => $servicePrice,
            'active' => 1,
            'tax_exclusive_serviceprice' => $servicePrice,
            'tax_percentage' => 0,
            'tax_price' => 0.00,
            'tax_including_price' => $servicePrice,
            'is_exclusive' => 0,
            'is_settlement' => 0,
        ];
    }
}
