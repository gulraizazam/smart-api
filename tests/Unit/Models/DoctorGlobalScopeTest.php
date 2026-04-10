<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Doctors;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the global scope on the Doctors model. The scope reads
 * `user_type_id = config('constants.asthatic_operator_id')`, which is set
 * to 5 in `config/constants.php`. The audit added it to prevent doctor
 * queries from leaking patient or staff rows out of the shared `users`
 * table.
 *
 * If the constant ever changes, this test will catch the drift.
 */
class DoctorGlobalScopeTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_doctors_query_only_returns_user_type_id_five(): void
    {
        $doctor = User::factory()->doctor()->create(['name' => 'Dr. House']);
        $patient = User::factory()->patient()->create();
        $staff = User::factory()->create(['user_type_id' => 1]);

        $doctorIds = Doctors::query()->pluck('id')->all();

        $this->assertContains($doctor->id, $doctorIds);
        $this->assertNotContains($patient->id, $doctorIds);
        $this->assertNotContains($staff->id, $doctorIds);
    }

    public function test_doctors_count_uses_the_constants_config_value(): void
    {
        // The audit pinned the doctor user-type to 5 via
        // config('constants.asthatic_operator_id'). Verifying the value
        // here documents the contract for future config edits.
        $this->assertSame(5, (int) config('constants.asthatic_operator_id'));

        User::factory()->doctor()->count(2)->create();
        User::factory()->patient()->count(3)->create();

        $this->assertSame(2, Doctors::query()->count());
    }

    public function test_doctors_find_returns_null_for_a_patient_id(): void
    {
        $patient = User::factory()->patient()->create();

        $this->assertNull(Doctors::query()->find($patient->id));
    }

    public function test_global_scope_can_be_explicitly_bypassed_when_needed(): void
    {
        $patient = User::factory()->patient()->create();

        $found = Doctors::withoutGlobalScope('doctors_only')->find($patient->id);

        $this->assertNotNull($found);
        $this->assertSame(3, (int) $found->user_type_id);
    }
}
