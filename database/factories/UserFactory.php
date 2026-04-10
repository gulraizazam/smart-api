<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 *
 * The default state produces an Application User (user_type_id = 1) so it
 * does NOT get filtered out by the Patient/Doctor global scopes that the
 * audit added in BaseModel.php. Use the state methods (`admin`, `doctor`,
 * `patient`, `frontDesk`) to opt into a specific identity type.
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        // The production users table does NOT have `email_verified_at` or
        // `is_admin` columns — admin status is determined by Spatie roles,
        // not by a column. `gender` is `tinyint(4)` in production: 0 = Male,
        // 1 = Female. Keep this in sync with `app/Models/User.php::$fillable`.
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
            'phone' => $this->faker->numerify('+92##########'),
            'gender' => $this->faker->randomElement([0, 1]),
            'dob' => $this->faker->date('Y-m-d', '-20 years'),
            'address' => $this->faker->address(),
            'user_type_id' => 1,
            'account_id' => 1,
            'active' => 1,
        ];
    }

    /**
     * Super-Admin user — production has no `is_admin` column; admin powers
     * are conferred by Spatie roles. Tests that need a super-admin should
     * call `->assignRole('Super Admin')` on the returned model after
     * seeding the role itself.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_type_id' => 1,
        ]);
    }

    /**
     * Doctor — `user_type_id = 5` matches User::$DOCTOR_GROUP and is what
     * the Doctors global scope filters on.
     */
    public function doctor(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_type_id' => 5,
        ]);
    }

    /**
     * Patient — `user_type_id = 3` matches User::$PATIENT_GROUP and is what
     * the Patients global scope filters on.
     */
    public function patient(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_type_id' => 3,
        ]);
    }

    /**
     * Front-desk operator — generic application user without admin powers.
     * Tests that exercise per-permission gating should also assignRole()
     * the appropriate Spatie role on the returned model.
     */
    public function frontDesk(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_type_id' => 1,
        ]);
    }
}
