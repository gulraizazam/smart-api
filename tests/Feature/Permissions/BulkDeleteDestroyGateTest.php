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
 * Pins the bulk-delete-via-datatable gate found in the 2026-06-19 QA (task #15):
 * each of these admin datatable endpoints carried a `delete=<ids>` branch that
 * deleted rows WITHOUT checking the resource's `*_destroy` permission — while the
 * single-record `destroy()` IS gated. Brands/Products had no gate on the datatable
 * at all; the rest gated the list on `*_manage` but the delete branch bypassed
 * `*_destroy`. Mirrors the already-fixed LeadsController precedent
 * (IdorHardeningTest::test_lead_bulk_delete_requires_destroy_permission).
 *
 * Teeth: remove the `abort_unless(...'_destroy')` from any branch → that row's
 * "requires destroy permission" case goes red (the delete runs, returns 200).
 */
class BulkDeleteDestroyGateTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    /** route URI | manage (view) slug | destroy slug */
    public static function bulkDeleteEndpoints(): array
    {
        return [
            'brands' => ['api/brands/datatable', 'brand_manage', 'brand_destroy'],
            'products' => ['api/products/datatable', 'product_manage', 'product_destroy'],
            'lead_sources' => ['api/lead_sources/datatable', 'lead_sources_manage', 'lead_sources_destroy'],
            'lead_statuses' => ['api/lead_statuses/datatable', 'lead_statuses_manage', 'lead_statuses_destroy'],
            'machine_types' => ['api/machine_types/datatable', 'machineType_manage', 'machineType_destroy'],
            'user_types' => ['api/user_types/datatable', 'user_types_manage', 'user_types_destroy'],
        ];
    }

    private function actAs(array $perms): void
    {
        foreach ($perms as $p) {
            $this->createPermission($p);
        }
        $role = $this->createRole('blk_'.uniqid());
        $role->givePermissionTo($perms);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create();
        $this->assignRoleWithPivot($user, $role);
        $this->actingAs($user);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    #[DataProvider('bulkDeleteEndpoints')]
    public function test_bulk_delete_requires_destroy_permission(string $route, string $manage, string $destroy): void
    {
        // Holds the view/manage slug (so the list gate, where present, passes)
        // but NOT the destroy slug — the bulk-delete branch must refuse.
        $this->createPermission($destroy); // ensure it exists, just not granted
        $this->actAs([$manage]);

        $this->postJson('/'.$route, ['query' => ['search' => ['delete' => '999999']]])
            ->assertStatus(403);
    }

    #[DataProvider('bulkDeleteEndpoints')]
    public function test_destroy_holder_passes_the_bulk_delete_gate(string $route, string $manage, string $destroy): void
    {
        // Holds both — the gate must let them through (no 403). The id is
        // non-existent so the delete is a harmless no-op; we only assert the
        // gate does not over-block a legitimate destroy-holder.
        $this->actAs([$manage, $destroy]);

        $response = $this->postJson('/'.$route, ['query' => ['search' => ['delete' => '999999']]]);

        $this->assertNotSame(403, $response->status(), 'a holder of '.$destroy.' must pass the bulk-delete gate');
    }

    /**
     * Locations + invoices datatables had no top read-gate, so their delete
     * branch is the only gate — keyed by a single slug. (Orders' datatable delete
     * branch is dead code: checkFilters() returns a bool, so isset($apply_filter
     * ['delete']) is always false — it can never delete, so it's no hole and is
     * left untouched. The invoices route is the literal `invoices/datatable/&`.)
     *
     * route URI | the slug that now gates its delete branch
     */
    public static function singleSlugBulkDeleteEndpoints(): array
    {
        return [
            'invoices' => ['api/invoices/datatable/&', 'invoices.cancel'],
            'locations' => ['api/locations/datatable', 'locations_destroy'],
        ];
    }

    #[DataProvider('singleSlugBulkDeleteEndpoints')]
    public function test_bulk_delete_branch_requires_its_permission(string $route, string $slug): void
    {
        $this->createPermission($slug); // exists, NOT granted
        $this->actAs([]);

        $this->postJson('/'.$route, ['query' => ['search' => ['delete' => '999999']]])
            ->assertStatus(403);
    }

    #[DataProvider('singleSlugBulkDeleteEndpoints')]
    public function test_bulk_delete_permission_holder_is_not_blocked(string $route, string $slug): void
    {
        $this->actAs([$slug]);

        $response = $this->postJson('/'.$route, ['query' => ['search' => ['delete' => '999999']]]);

        $this->assertNotSame(403, $response->status(), "holder of {$slug} must pass the bulk-delete gate on {$route}");
    }
}
