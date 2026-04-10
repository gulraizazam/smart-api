<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LeadSources;
use App\Models\LeadStatuses;
use App\Models\Leads;
use App\Models\Locations;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Leads>
 */
class LeadFactory extends Factory
{
    protected $model = Leads::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->safeEmail(),
            'phone' => $this->faker->numerify('+92##########'),
            // The Leads model casts gender to App\Enums\Gender (int-backed:
            // Male = 1, Female = 2). Bare strings 'Male'/'Female' trip the
            // backed-enum cast on hydration.
            'gender' => $this->faker->randomElement([1, 2]),
            'lead_status_id' => LeadStatuses::factory(),
            'lead_source_id' => LeadSources::factory(),
            'msg_count' => 0,
            'active' => 1,
            'account_id' => 1,
            'location_id' => Locations::factory(),
        ];
    }
}
