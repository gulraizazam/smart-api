<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Exceptions\LeadException;
use App\Models\Leads;
use App\Models\User;
use App\Services\Lead\LeadService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Security audit 2026-06 — Lead account scoping (IDOR).
 *
 * Every by-id Lead lookup in LeadService must be scoped to the caller's
 * account_id. Previously several used a bare Leads::find()/findOrFail(),
 * letting a user with lead permissions read / mutate / SMS another tenant's
 * leads by guessing the id. The sibling methods (toggleStatus, getLeadForEdit,
 * removeLeadFromJunk) were already scoped — these tests pin the rest.
 *
 * Foreign (account 2) leads are created while logged OUT so the
 * GuardsTenantBoundary create-hook doesn't restamp them with account 1.
 */
class LeadAccountScopingTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private LeadService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->seedSecondAccount();
        $this->service = app(LeadService::class);
    }

    public function test_find_lead_returns_null_for_other_account(): void
    {
        $foreign = $this->foreignLead();
        $this->actingAsAccountOne();

        $this->assertNull($this->service->findLead($foreign->id));
    }

    public function test_find_lead_returns_record_for_own_account(): void
    {
        $mine = Leads::factory()->create(['account_id' => 1]);
        $this->actingAsAccountOne();

        $found = $this->service->findLead($mine->id);

        $this->assertNotNull($found);
        $this->assertSame($mine->id, $found->id);
    }

    public function test_get_lead_detail_returns_null_for_other_account(): void
    {
        $foreign = $this->foreignLead();
        $this->actingAsAccountOne();

        $this->assertNull($this->service->getLeadDetail($foreign->id));
    }

    public function test_update_lead_status_rejects_other_account(): void
    {
        $foreign = $this->foreignLead();
        $this->actingAsAccountOne();

        $this->expectException(ModelNotFoundException::class);
        $this->service->updateLeadStatus($foreign->id, ['lead_status_parent_id' => 1]);
    }

    public function test_send_sms_to_lead_rejects_other_account(): void
    {
        $foreign = $this->foreignLead();
        $this->actingAsAccountOne();

        $this->expectException(ModelNotFoundException::class);
        $this->service->sendSmsToLead($foreign->id);
    }

    public function test_update_lead_city_returns_null_for_other_account(): void
    {
        $foreign = $this->foreignLead();
        $this->actingAsAccountOne();

        $this->assertNull($this->service->updateLeadCity($foreign->id, 1));
    }

    public function test_lead_status_hierarchy_rejects_other_account(): void
    {
        $foreign = $this->foreignLead();
        $this->actingAsAccountOne();

        $this->expectException(LeadException::class);
        $this->service->getLeadStatusesWithChildren($foreign->id);
    }

    /** Created logged-out so account_id=2 is honoured (not restamped to 1). */
    private function foreignLead(): Leads
    {
        return Leads::factory()->create(['account_id' => 2]);
    }

    private function actingAsAccountOne(): void
    {
        $this->actingAs(User::factory()->create(['account_id' => 1]));
    }

    private function seedSecondAccount(): void
    {
        DB::table('accounts')->updateOrInsert(
            ['id' => 2],
            [
                'name' => 'Second Account',
                'email' => 'second@example.com',
                'contact' => '0000000001',
                'suspended' => '0',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
