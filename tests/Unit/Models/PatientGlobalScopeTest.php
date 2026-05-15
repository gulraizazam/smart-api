<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Patients;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the global scope on the Patients model. The audit added this scope
 * because the `users` table is shared across all identity types (patients,
 * doctors, application users) and prior code was leaking non-patient rows
 * into patient queries — a confirmed PII / IDOR risk.
 *
 * The scope must:
 *   1. Filter to user_type_id = 3 by default
 *   2. NOT be removable via plain query() — bypass requires
 *      ::withoutGlobalScope('patients_only') (the registered scope name)
 */
class PatientGlobalScopeTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    public function test_patients_query_only_returns_user_type_id_three(): void
    {
        $patient = User::factory()->patient()->create(['name' => 'Alice Patient']);
        $doctor = User::factory()->doctor()->create(['name' => 'Bob Doctor']);
        $staff = User::factory()->create(['name' => 'Carol Staff', 'user_type_id' => 1]);

        $patientIds = Patients::query()->pluck('id')->all();

        $this->assertContains($patient->id, $patientIds);
        $this->assertNotContains($doctor->id, $patientIds);
        $this->assertNotContains($staff->id, $patientIds);
    }

    public function test_patients_count_excludes_doctors_and_staff(): void
    {
        User::factory()->patient()->count(3)->create();
        User::factory()->doctor()->count(2)->create();
        User::factory()->create(['user_type_id' => 1]);

        $this->assertSame(3, Patients::query()->count());
    }

    public function test_patients_find_returns_null_for_non_patient_id(): void
    {
        // A doctor exists in the users table but is not a patient.
        $doctor = User::factory()->doctor()->create();

        $this->assertNull(Patients::query()->find($doctor->id));
    }

    public function test_global_scope_can_be_explicitly_bypassed_when_needed(): void
    {
        // Background scripts that need to read the underlying users table
        // can opt out by name. This pin documents the bypass mechanism so
        // that future maintenance keeps the scope name stable.
        $doctor = User::factory()->doctor()->create();

        $found = Patients::withoutGlobalScope('patients_only')->find($doctor->id);

        $this->assertNotNull($found);
        $this->assertSame(5, (int) $found->user_type_id);
    }

    public function test_raw_users_table_query_sees_all_user_types(): void
    {
        // Belt-and-braces sanity check — the scope is enforced at the
        // Eloquent layer only. Code that hits the table directly via
        // DB::table('users') still sees every row, which is the audit's
        // accepted trade-off for things like cross-type joins.
        User::factory()->patient()->count(2)->create();
        User::factory()->doctor()->count(1)->create();

        $rawCount = DB::table('users')->whereIn('user_type_id', [3, 5])->count();

        $this->assertSame(3, $rawCount);
    }

    public function test_saving_a_patient_populates_phone_normalized_from_phone(): void
    {
        // 2026-05-15 regression: Patients extends BaseModel, not User,
        // so the `phone_normalized` `saving` hook on User never fired
        // for patients. Production patient phone-search queries
        // `WHERE phone_normalized LIKE '302%'` and silently returned
        // nothing because the column was NULL on every Patient row.
        $patient = User::factory()->patient()->create([
            'phone' => '+92-310 555 1234',
        ]);

        // Re-query via Patients to ensure the hook fires on the
        // patient-scoped model (the model owning the new `saving` hook).
        $row = Patients::find($patient->id);
        $row->phone = '+92-310 555 1234';
        $row->save();

        $this->assertSame(
            '923105551234',
            $row->fresh()->phone_normalized,
            'phone_normalized must hold the digits-only form of phone after save.',
        );
    }

    public function test_clearing_phone_clears_phone_normalized(): void
    {
        $patient = User::factory()->patient()->create([
            'phone' => '0314-1234567',
        ]);

        $row = Patients::find($patient->id);
        // Trigger the saving hook on first save.
        $row->phone = '0314-1234567';
        $row->save();
        $this->assertSame('03141234567', $row->fresh()->phone_normalized);

        $row->phone = '';
        $row->save();

        $this->assertNull(
            $row->fresh()->phone_normalized,
            'Empty phone must null out phone_normalized so partial-match search drops the row.',
        );
    }
}
