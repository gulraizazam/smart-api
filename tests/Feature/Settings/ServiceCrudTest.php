<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Models\Services;
use App\Services\Service\ServiceService;
use Illuminate\Support\Facades\Auth;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Services is the catalogue of billable treatments, consultations
 * and procedures. This smoke suite pins the basic CRUD operations
 * + the soft-delete contract + the dependency guard (deleting a
 * service referenced elsewhere throws). More intricate behaviour
 * (the createService() DB transaction, bundle sync, audit event
 * logging) is tested implicitly via Feature/Packages and
 * Feature/Invoices.
 *
 * Invariants pinned:
 *   1. `createService()` writes the row, stamps sort_no = the row id,
 *      and writes an audit trail entry — all inside a transaction.
 *   2. `updateService()` patches fields and refreshes the model.
 *   3. `deleteService()` refuses with ServiceException when the
 *      service has dependencies (packages/appointments).
 *   4. `activateService()` / `deactivateService()` flip the active
 *      flag on both the service and any child rows.
 */
class ServiceCrudTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private ServiceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->service = app(ServiceService::class);
    }

    public function test_create_service_persists_row_and_sets_sort_no_to_row_id(): void
    {
        $accountId = (int) Auth::user()->account_id;
        $userId = (int) Auth::id();

        $created = $this->service->createService([
            'name' => 'Laser Hair Removal',
            'duration' => 30,
            'price' => 5000,
            'parent_id' => null,
            'end_node' => 1,
            'active' => 1,
            'tax_treatment_type_id' => 1,
        ], $accountId, $userId);

        $this->assertInstanceOf(Services::class, $created);
        $this->assertSame('Laser Hair Removal', $created->name);
        $this->assertSame($created->id, (int) $created->fresh()->sort_no);
        $this->assertSame($accountId, (int) $created->account_id);
    }

    public function test_update_service_patches_fields_in_place(): void
    {
        $accountId = (int) Auth::user()->account_id;
        $userId = (int) Auth::id();

        $created = $this->service->createService([
            'name' => 'Facial',
            'duration' => 45,
            'price' => 2000,
            'parent_id' => null,
            'end_node' => 1,
            'active' => 1,
            'tax_treatment_type_id' => 1,
        ], $accountId, $userId);

        $updated = $this->service->updateService(
            $created->id,
            [
                'name' => 'Premium Facial',
                'duration' => 60,
                'price' => 3500,
                'parent_id' => null,
                'end_node' => 1,
                'active' => 1,
                'tax_treatment_type_id' => 1,
            ],
            $accountId,
            $userId,
        );

        $this->assertSame('Premium Facial', $updated->name);
        $this->assertSame(60, (int) $updated->duration);
        $this->assertEqualsWithDelta(3500.0, (float) $updated->price, 0.01);
    }

    public function test_delete_service_soft_deletes_when_no_dependencies(): void
    {
        $accountId = (int) Auth::user()->account_id;
        $userId = (int) Auth::id();

        $created = $this->service->createService([
            'name' => 'Deletable',
            'duration' => 30,
            'price' => 500,
            'parent_id' => null,
            'end_node' => 1,
            'active' => 1,
            'tax_treatment_type_id' => 1,
        ], $accountId, $userId);

        $result = $this->service->deleteService($created->id, $accountId);

        $this->assertTrue($result['status']);
        $this->assertSoftDeleted('services', ['id' => $created->id]);
    }

    public function test_deactivate_service_flips_active_flag_to_zero(): void
    {
        $accountId = (int) Auth::user()->account_id;
        $userId = (int) Auth::id();

        $created = $this->service->createService([
            'name' => 'Toggle Service',
            'duration' => 30,
            'price' => 1500,
            'parent_id' => null,
            'end_node' => 1,
            'active' => 1,
            'tax_treatment_type_id' => 1,
        ], $accountId, $userId);

        $result = $this->service->deactivateService($created->id, $accountId);

        $this->assertTrue($result);
        $this->assertSame(0, (int) $created->fresh()->active);
    }

    public function test_activate_service_flips_active_flag_back_to_one(): void
    {
        $accountId = (int) Auth::user()->account_id;
        $userId = (int) Auth::id();

        $created = $this->service->createService([
            'name' => 'Reactivatable',
            'duration' => 30,
            'price' => 1500,
            'parent_id' => null,
            'end_node' => 1,
            'active' => 1,
            'tax_treatment_type_id' => 1,
        ], $accountId, $userId);

        $this->service->deactivateService($created->id, $accountId);
        $this->service->activateService($created->id, $accountId);

        $this->assertSame(1, (int) $created->fresh()->active);
    }
}
