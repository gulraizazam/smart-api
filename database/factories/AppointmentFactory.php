<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Appointments;
use App\Models\Leads;
use App\Models\Locations;
use App\Models\Patients;
use App\Models\Services;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointments>
 *
 * Booked-state default. Use the state methods (`completed`, `cancelled`,
 * `arrived`) to advance the row through the appointment state machine.
 *
 * Production-schema NOT NULL columns the dump enforces but earlier
 * iterations of this factory missed: `lead_id`, `region_id`, `city_id`.
 * The latter two FK to the (1, 1) pair seeded by UsesFinancialFixtures —
 * tests using this factory MUST seed those fixtures first.
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointments::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'account_id' => 1,
            'appointment_type_id' => 1,
            'patient_id' => Patients::factory(),
            'lead_id' => Leads::factory(),
            'region_id' => 1,
            'city_id' => 1,
            'service_id' => Services::factory(),
            'doctor_id' => User::factory()->doctor(),
            'location_id' => Locations::factory(),
            'appointment_status_id' => 1,
            'scheduled_date' => $this->faker->date('Y-m-d', '+1 month'),
            'scheduled_time' => $this->faker->time('H:i:s'),
        ];
    }

    public function arrived(): static
    {
        return $this->state(fn (array $a): array => ['appointment_status_id' => 2]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $a): array => ['appointment_status_id' => 4]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $a): array => ['appointment_status_id' => 5]);
    }
}
