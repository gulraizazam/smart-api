<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\CashTransfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashTransfer>
 *
 * IMPORTANT: from_pool_id and to_pool_id default to two NEW pools. Tests that
 * need both pools to share an account should pass them explicitly via
 * ->state(['from_pool_id' => ..., 'to_pool_id' => ...]).
 */
class CashTransferFactory extends Factory
{
    protected $model = CashTransfer::class;

    public function definition(): array
    {
        return [
            'account_id' => 1,
            'transfer_date' => $this->faker->date('Y-m-d'),
            'amount' => $this->faker->randomFloat(2, 100, 5000),
            'from_pool_id' => CashPool::factory(),
            'to_pool_id' => CashPool::factory(),
            // cash_transfers.method is enum('physical_cash','bank_deposit')
            // in production — see schema dump.
            'method' => 'physical_cash',
            'reference_no' => $this->faker->bothify('TRF-####'),
            'description' => $this->faker->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
