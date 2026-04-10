<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\Patients;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    protected $model = Membership::class;

    public function definition(): array
    {
        // memberships.created_by is NOT NULL with an FK to users —
        // pin a fresh user factory so the row inserts.
        $start = now()->startOfDay();

        return [
            'code' => 'MEM-'.Str::upper(Str::random(8)),
            'membership_type_id' => MembershipType::factory(),
            'start_date' => $start,
            'end_date' => $start->copy()->addYear(),
            'patient_id' => Patients::factory(),
            'active' => 1,
            'assigned_at' => now(),
            'is_referral' => 0,
            'created_by' => \App\Models\User::factory(),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $a): array => [
            'start_date' => now()->subYears(2),
            'end_date' => now()->subYear(),
        ]);
    }
}
