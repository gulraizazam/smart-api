<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\UserTypes;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserTypes>
 *
 * UserType IDs are load-bearing — see User::$PATIENT_GROUP (3) and
 * $DOCTOR_GROUP (5). Tests that need a specific id should `updateOrCreate`
 * via UsesFinancialFixtures::seedUserTypes() rather than calling this
 * factory, which only generates anonymous staff types.
 */
class UserTypeFactory extends Factory
{
    protected $model = UserTypes::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->jobTitle(),
            // `type` is NOT NULL in the production schema and stores the
            // category slug (e.g. 'staff', 'patient', 'doctor'). The factory
            // generates a generic staff type by default — tests that need
            // a specific category should override this field directly.
            'type' => 'staff',
            'account_id' => 1,
            'active' => 1,
        ];
    }
}
