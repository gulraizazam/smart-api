<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the gates added for the remaining open endpoints from the 2026-06-19 QA
 * (#15) — each previously ran with NO permission check:
 *   - users/getpatient-optimized  → patients.list.view  (phone already masked)
 *   - system-targets/save         → centre_targets_edit
 *   - leads/status                → leads.update_status
 *   - leads/export/{pdf,excel}    → leads.export
 *   - hr/employees/{user}/documents (upload) → owner OR hr_documents_manage
 *
 * Teeth: drop a gate and that endpoint's "requires permission" case reddens.
 * (The four appointment write endpoints — reschedule/status/SMS — are handled
 * separately because they need account-scoping too, not just a gate.)
 */
class RemainingEndpointGateTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    private function actAs(array $perms): void
    {
        foreach ($perms as $p) {
            $this->createPermission($p);
        }
        $role = $this->createRole('rem_'.uniqid());
        if ($perms !== []) {
            $role->givePermissionTo($perms);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create();
        $this->assignRoleWithPivot($user, $role);
        $this->actingAs($user);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** http method | route URI | the slug that should gate it */
    public static function endpoints(): array
    {
        return [
            'getpatient_optimized' => ['GET', 'api/users/getpatient-optimized', 'patients.list.view'],
            'save_system_target' => ['POST', 'api/system-targets/save', 'centre_targets_edit'],
            'lead_status' => ['POST', 'api/leads/status', 'leads.update_status'],
            'lead_export_pdf' => ['GET', 'api/leads/export/pdf', 'leads.export'],
            'lead_export_excel' => ['GET', 'api/leads/export/excel', 'leads.export'],
        ];
    }

    #[DataProvider('endpoints')]
    public function test_endpoint_requires_its_permission(string $method, string $route, string $slug): void
    {
        $this->createPermission($slug); // exists, but NOT granted
        $this->actAs([]);

        $this->json($method, '/'.$route, [])->assertStatus(403);
    }

    #[DataProvider('endpoints')]
    public function test_permission_holder_is_not_blocked(string $method, string $route, string $slug): void
    {
        $this->actAs([$slug]);

        $response = $this->json($method, '/'.$route, []);

        $this->assertNotSame(403, $response->status(), "holder of {$slug} must not be 403'd on {$route}");
    }

    public function test_hr_document_upload_requires_owner_or_documents_manage(): void
    {
        $this->createPermission('hr_documents_manage');
        $target = User::factory()->create(); // upload target (the acting user is NOT this user)
        $this->actAs([]);

        $this->postJson("/api/hr/employees/{$target->id}/documents", [])->assertStatus(403);
    }

    public function test_hr_documents_manage_holder_can_reach_upload(): void
    {
        $target = User::factory()->create();
        $this->actAs(['hr_documents_manage']);

        $response = $this->postJson("/api/hr/employees/{$target->id}/documents", []);

        $this->assertNotSame(403, $response->status(), 'an hr_documents_manage holder must reach the upload');
    }
}
