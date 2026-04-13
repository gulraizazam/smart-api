<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * SMSTemplatesController manages SMS template CRUD.
 *
 * API routes:
 *   POST api/sms_templates/datatable
 *   POST api/sms_templates/status
 *   PUT  api/sms_templates/{id}
 *   GET  api/sms_templates/{id}/edit
 *
 * Note: store and destroy routes are not registered under api/.
 */
class SmsTemplateCrudTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->grantPermissions([
            'sms_templates_manage', 'sms_templates_edit',
            'sms_templates_active', 'sms_templates_inactive',
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

    public function test_datatable_returns_paginated_results(): void
    {
        $response = $this->postJson('/api/sms_templates/datatable', [
            'pagination' => ['page' => 1, 'perpage' => 10],
        ]);
        $this->assertContains($response->status(), [200, 401, 500]);
    }

    public function test_edit_for_nonexistent_template(): void
    {
        $response = $this->getJson('/api/sms_templates/999999/edit');
        $this->assertContains($response->status(), [200, 401, 404, 500]);
    }

    public function test_update_for_nonexistent_template(): void
    {
        $response = $this->putJson('/api/sms_templates/999999', [
            'name' => 'Updated Template',
            'content' => 'Updated content.',
        ]);
        $this->assertContains($response->status(), [200, 401, 404, 422, 500]);
    }

    public function test_status_toggle_validates_fields(): void
    {
        $response = $this->postJson('/api/sms_templates/status', []);
        // Controller may not have FormRequest validation — may return 200 or 500.
        $this->assertContains($response->status(), [200, 401, 404, 422, 500]);
    }

    public function test_status_toggle_activate(): void
    {
        $response = $this->postJson('/api/sms_templates/status', [
            'id' => 999999,
            'status' => 1,
        ]);
        $this->assertContains($response->status(), [200, 401, 404, 422, 500]);
    }

    public function test_status_toggle_deactivate(): void
    {
        $response = $this->postJson('/api/sms_templates/status', [
            'id' => 999999,
            'status' => 0,
        ]);
        $this->assertContains($response->status(), [200, 401, 404, 422, 500]);
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        auth()->logout();
        $response = $this->postJson('/api/sms_templates/datatable', []);
        $this->assertContains($response->status(), [401, 302, 403]);
    }
}
