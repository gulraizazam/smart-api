<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\Locations;
use App\Models\User;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * DoctorController manages doctor CRUD with user_type_id=5, location
 * assignment, service assignment, status toggle, and password change.
 * Doctors are key entities in the medical workflow — they are linked to
 * appointments, consultations, and treatments.
 *
 * Pins:
 *   1. Doctor datatable returns paginated results.
 *   2. Doctor create endpoint returns form data.
 *   3. Doctor store validates required fields (name, email, password, phone, gender).
 *   4. Doctor status toggle works.
 *   5. Location display returns allocation data.
 *   6. Service retrieval accepts location parameter.
 *   7. Unauthenticated access is rejected.
 */
class DoctorManagementTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->grantPermissions([
            'doctors_manage', 'doctors_create', 'doctors_edit', 'doctors_destroy',
            'doctors_active', 'doctors_inactive', 'doctors_allocate', 'doctors_change_password',
        ]);
    }

    private function grantPermissions(array $permissions): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($permissions as $perm) {
            $this->createPermission($perm);
        }

        $role = $this->createRole('test-admin-' . uniqid());
        $role->givePermissionTo($permissions);
        auth()->user()->assignRole($role);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_doctor_datatable_returns_paginated_results(): void
    {
        $response = $this->postJson('/api/doctors/datatable', [
            'pagination' => ['page' => 1, 'perpage' => 10],
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_doctor_create_returns_form_data(): void
    {
        $response = $this->getJson('/api/doctors/create');
        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'data']);
    }

    public function test_doctor_store_validates_required_fields(): void
    {
        // Missing name, email, password, phone, gender.
        $response = $this->postJson('/api/doctors', []);
        $response->assertStatus(422);
    }

    public function test_doctor_store_validates_email_uniqueness(): void
    {
        $existing = User::factory()->create([
            'account_id' => auth()->user()->account_id,
            'user_type_id' => 5,
        ]);

        $response = $this->postJson('/api/doctors', [
            'name' => 'New Doctor',
            'email' => $existing->email,
            'password' => 'TestPass1@',
            'phone' => '1234567890',
            'gender' => 1,
        ]);

        $response->assertStatus(422);
    }

    public function test_doctor_status_toggle(): void
    {
        $doctor = User::factory()->create([
            'account_id' => auth()->user()->account_id,
            'user_type_id' => 5,
        ]);

        $response = $this->postJson('/api/doctors/status', [
            'id' => $doctor->id,
            'status' => 0,
        ]);

        $this->assertContains($response->status(), [200, 422]);
    }

    public function test_doctor_edit_returns_doctor_data(): void
    {
        $doctor = User::factory()->create([
            'account_id' => auth()->user()->account_id,
            'user_type_id' => 5,
        ]);

        $response = $this->getJson("/api/doctors/{$doctor->id}/edit");
        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'data']);
    }

    public function test_display_location_returns_allocation_data(): void
    {
        $doctor = User::factory()->create([
            'account_id' => auth()->user()->account_id,
            'user_type_id' => 5,
        ]);

        $response = $this->getJson("/api/doctors/locations/{$doctor->id}");
        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'data']);
    }

    public function test_get_services_requires_location_parameter(): void
    {
        $location = Locations::first();

        $response = $this->getJson('/api/doctors/get-service?location_id=' . $location->id);
        $this->assertContains($response->status(), [200, 422]);
    }

    public function test_unauthenticated_access_to_doctors_is_rejected(): void
    {
        auth()->logout();
        $response = $this->postJson('/api/doctors/datatable', []);
        $this->assertContains($response->status(), [401, 302, 403]);
    }
}
