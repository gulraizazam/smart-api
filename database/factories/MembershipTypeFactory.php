<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MembershipType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipType>
 */
class MembershipTypeFactory extends Factory
{
    protected $model = MembershipType::class;

    public function definition(): array
    {
        // The model's $fillable has 'status' but the production schema
        // (membership_types) has no `status` column — pinning it would
        // produce an "Unknown column 'status'" insert error. Created_by
        // is NOT NULL with an FK to users; pin to a fresh user factory.
        return [
            'name' => 'Tier '.$this->faker->unique()->numerify('####'),
            'period' => 12,
            'amount' => $this->faker->randomFloat(2, 1000, 50000),
            'active' => 1,
            'created_by' => \App\Models\User::factory(),
        ];
    }
}
