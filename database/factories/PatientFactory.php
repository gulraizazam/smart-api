<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Patients;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Patients>
 *
 * Patients are stored in the `users` table behind a global scope that filters
 * `user_type_id = 3` (User::$PATIENT_GROUP). Setting that id here is what makes
 * the row visible through the `Patients` model.
 */
class PatientFactory extends Factory
{
    protected $model = Patients::class;

    public function definition(): array
    {
        // The `users` table (where patients live) does NOT have an
        // `address` column — patient addresses live on the patient
        // detail / profile relations, not directly on the user row.
        // Writing it here used to silently swallow into a since-dropped
        // column; now MariaDB surfaces "Unknown column 'address'" and
        // breaks the whole feature suite.
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'phone' => $this->faker->numerify('+92##########'),
            // gender is tinyint(4) in production: 0 = Male, 1 = Female.
            'gender' => $this->faker->randomElement([0, 1]),
            'cnic' => $this->faker->numerify('#####-#######-#'),
            'dob' => $this->faker->date('Y-m-d', '-25 years'),
            'active' => 1,
            'user_type_id' => 3,
            'account_id' => 1,
            'main_account' => 1,
        ];
    }
}
