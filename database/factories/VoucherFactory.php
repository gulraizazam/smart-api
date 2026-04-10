<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Voucher>
 */
class VoucherFactory extends Factory
{
    protected $model = Voucher::class;

    public function definition(): array
    {
        $name = 'Voucher '.$this->faker->unique()->word();

        return [
            'slug' => Str::slug($name).'-'.$this->faker->unique()->numberBetween(1000, 999999),
            'name' => $name,
            'start' => now()->subDay(),
            'end' => now()->addMonth(),
            'active' => 1,
            'account_id' => 1,
        ];
    }
}
