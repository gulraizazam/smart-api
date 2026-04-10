<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\Locations;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * End-to-end tenant-isolation pin. Even when a row carries an explicit
 * cross-tenant `account_id` (e.g. created by a privileged service script
 * before the actor logs in), the per-tenant fetch helpers must NOT return
 * it to a user whose `Auth::user()->account_id` does not match.
 *
 * BaseModel exposes two such helpers — `getData(int $id)` and
 * `getBulkData(int|array $id)` — and the audit removed a third
 * (`getBulkData_forimage`) for the same reason.
 *
 * These tests assert the contract:
 *   1. A user from account A cannot read a row owned by account B
 *      via the canonical helpers.
 *   2. The deprecated helper still throws (regression guard).
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();

        // Two real tenants are needed to make any cross-tenant assertion
        // meaningful.
        DB::table('accounts')->updateOrInsert(
            ['id' => 999],
            [
                'name' => 'Other Tenant',
                'email' => 'other@example.com',
                'contact' => '0000000000',
                'suspended' => '0',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function test_get_data_returns_null_for_a_row_in_a_different_account(): void
    {
        // Plant a row in account 999 with no actor logged in (so the
        // GuardsTenantBoundary creating-event hook does not rewrite the
        // account_id).
        $foreignLocation = Locations::factory()->create(['account_id' => 999]);
        $this->assertSame(999, (int) $foreignLocation->fresh()->account_id);

        // Now log in as an account-1 admin and try to read it.
        $this->actingAsAdmin();

        $found = Locations::getData($foreignLocation->id);

        $this->assertNull(
            $found,
            'BaseModel::getData must filter by Auth::user()->account_id and return null '
            .'when the requested row belongs to a different tenant.'
        );
    }

    public function test_get_bulk_data_skips_rows_belonging_to_a_different_account(): void
    {
        // Two foreign rows + one in-tenant row (created after login below).
        $foreign1 = Locations::factory()->create(['account_id' => 999]);
        $foreign2 = Locations::factory()->create(['account_id' => 999]);

        $this->actingAsAdmin();
        $own = Locations::factory()->create();

        $found = Locations::getBulkData([$foreign1->id, $foreign2->id, $own->id]);

        $foundIds = $found->pluck('id')->all();
        $this->assertContains($own->id, $foundIds);
        $this->assertNotContains($foreign1->id, $foundIds);
        $this->assertNotContains($foreign2->id, $foundIds);
    }

    public function test_deprecated_get_bulk_data_for_image_helper_throws(): void
    {
        // The audit removed this helper because it ran whereIn('id', $id)
        // with no tenant scoping — a confirmed cross-tenant IDOR vector.
        // The hard throw is the regression guard against accidental
        // reintroduction; pin it.
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('BaseModel::getBulkData_forimage was removed for tenant-isolation reasons.');

        Locations::getBulkData_forimage([1, 2, 3]);
    }
}
