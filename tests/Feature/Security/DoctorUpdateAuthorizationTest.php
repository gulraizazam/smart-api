<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\UserManagement\DoctorService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Security audit 2026-06 — doctor update authorization.
 *
 * DoctorController::update passes $request->all() into DoctorService::update,
 * which used to $user->update() the whole array on a User whose $fillable
 * includes privileged columns (commission, is_advance_eligible, …). The fix
 * allowlists the editable columns and scopes the lookup by account_id.
 */
class DoctorUpdateAuthorizationTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private DoctorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->seedSecondAccount();
        $this->service = app(DoctorService::class);
    }

    public function test_update_ignores_privileged_mass_assignment(): void
    {
        $doctor = User::factory()->doctor()->create([
            'account_id' => 1,
            'commission' => 100,
            'is_advance_eligible' => 0,
        ]);

        $this->actingAs(User::factory()->create(['account_id' => 1]));

        $this->service->update($doctor->id, [
            'name' => 'Updated Name',
            'email' => $doctor->email,
            'phone' => '+923001234567',
            'gender' => 1,
            'roles' => [],
            // Privileged columns a crafted request might smuggle in:
            'commission' => 9999,
            'is_advance_eligible' => 1,
            'select_all' => 1,
            'hr_managed' => 1,
        ]);

        $fresh = $doctor->fresh();
        $this->assertSame('Updated Name', $fresh->name, 'Legit field should update.');
        $this->assertEquals(100, (float) $fresh->commission, 'commission must not be mass-assignable via doctor-edit.');
        $this->assertEquals(0, (int) $fresh->is_advance_eligible, 'is_advance_eligible must not be mass-assignable via doctor-edit.');
    }

    public function test_update_rejects_cross_account_doctor(): void
    {
        // Created logged-out so account_id=2 is honoured (not restamped to 1).
        $foreign = User::factory()->doctor()->create(['account_id' => 2]);

        $this->actingAs(User::factory()->create(['account_id' => 1]));

        $this->expectException(ModelNotFoundException::class);
        $this->service->update($foreign->id, [
            'name' => 'Hijack',
            'email' => $foreign->email,
            'phone' => '+923009999999',
            'gender' => 1,
            'roles' => [],
        ]);
    }

    private function seedSecondAccount(): void
    {
        DB::table('accounts')->updateOrInsert(
            ['id' => 2],
            ['name' => 'Second Account', 'email' => 'second@example.com', 'contact' => '0000000001', 'suspended' => '0', 'created_at' => now(), 'updated_at' => now()]
        );
    }
}
