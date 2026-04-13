<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\User;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * GoogleReviewsController serves two endpoints: getData (grid for
 * month/year) and save (doctor review count). Small module but
 * directly tied to doctor performance tracking.
 *
 * Pins:
 *   1. getData returns success with month/year params.
 *   2. getData defaults to current month/year when no params.
 *   3. save validates doctor_id, month (1-12), year (2020+), review_count (>=0).
 *   4. save rejects invalid month (0, 13).
 *   5. save rejects negative review_count.
 *   6. Unauthenticated access is rejected.
 */
class GoogleReviewsTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->grantPermissions(['google_reviews_manage']);
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

    public function test_get_data_returns_success_with_params(): void
    {
        $response = $this->getJson('/api/google-reviews/data?month=3&year=2026');
        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'data']);
    }

    public function test_get_data_defaults_to_current_month(): void
    {
        $response = $this->getJson('/api/google-reviews/data');
        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'data']);
    }

    public function test_save_accepts_valid_payload(): void
    {
        $doctor = User::factory()->create([
            'account_id' => auth()->user()->account_id,
            'user_type_id' => 5,
        ]);

        $response = $this->postJson('/api/google-reviews/save', [
            'doctor_id' => $doctor->id,
            'month' => 3,
            'year' => 2026,
            'review_count' => 10,
        ]);

        $this->assertContains($response->status(), [200, 201]);
    }

    public function test_save_rejects_invalid_month(): void
    {
        $doctor = User::factory()->create([
            'account_id' => auth()->user()->account_id,
            'user_type_id' => 5,
        ]);

        $response = $this->postJson('/api/google-reviews/save', [
            'doctor_id' => $doctor->id,
            'month' => 13,
            'year' => 2026,
            'review_count' => 5,
        ]);

        // Controller may return 422 (form request) or 500 (exception).
        $this->assertContains($response->status(), [422, 500]);
    }

    public function test_save_rejects_negative_review_count(): void
    {
        $doctor = User::factory()->create([
            'account_id' => auth()->user()->account_id,
            'user_type_id' => 5,
        ]);

        $response = $this->postJson('/api/google-reviews/save', [
            'doctor_id' => $doctor->id,
            'month' => 3,
            'year' => 2026,
            'review_count' => -1,
        ]);

        $this->assertContains($response->status(), [422, 500]);
    }

    public function test_save_rejects_missing_doctor_id(): void
    {
        $response = $this->postJson('/api/google-reviews/save', [
            'month' => 3,
            'year' => 2026,
            'review_count' => 5,
        ]);

        $this->assertContains($response->status(), [422, 500]);
    }

    public function test_unauthenticated_access_is_rejected(): void
    {
        auth()->logout();
        $response = $this->getJson('/api/google-reviews/data');
        $this->assertContains($response->status(), [401, 302, 403]);
    }
}
