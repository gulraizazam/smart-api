<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CashFlow\PeriodLock;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PeriodLock>
 *
 * Locks the previous calendar month by default — that's the period new
 * entries should be blocked from. Tests asserting "current month allowed"
 * vs "previous month blocked" can rely on this default.
 */
class PeriodLockFactory extends Factory
{
    protected $model = PeriodLock::class;

    public function definition(): array
    {
        $previous = now()->subMonthNoOverflow();

        return [
            'account_id' => 1,
            'month' => $previous->month,
            'year' => $previous->year,
            'locked_by' => User::factory(),
            'balance_snapshot' => json_encode([]),
        ];
    }

    public function unlocked(): static
    {
        return $this->state(fn (array $a): array => [
            'unlocked_at' => now(),
            'unlocked_by' => User::factory(),
            'unlock_reason' => 'Audit correction',
        ]);
    }
}
