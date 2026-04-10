<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Locations;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The GuardsTenantBoundary trait (used by every BaseModel descendant) is the
 * audit's primary defense against multi-tenant escape via mass-assignment.
 *
 * Two enforcement points:
 *   1. Creating: account_id is force-rewritten to the authed user's account.
 *      A request that posts {account_id: 999} for a different tenant has its
 *      account_id silently overwritten to the actor's tenant.
 *
 *   2. Updating: any dirty change to a guarded column (account_id by default,
 *      plus user_type_id and is_admin on the User model) is reverted to the
 *      original value before the UPDATE statement runs. The attempt is also
 *      logged via Log::warning() as a security event.
 *
 * These two pins guarantee that even a fully-fillable model with `account_id`
 * in $fillable cannot be used as a tenant-escape vector.
 */
class BaseModelAccountIdTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();

        // A second tenant has to exist for "cross-tenant" assertions to be
        // meaningful — otherwise the FK on locations.account_id would
        // explode and mask the guard's actual behaviour.
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

    public function test_creating_a_model_force_assigns_account_id_from_authed_user(): void
    {
        $admin = $this->actingAsAdmin(); // account_id = 1 from UserFactory

        $location = Locations::factory()->create([
            // Try to claim a different tenant — guard must overwrite this.
            'account_id' => 999,
            'name' => 'Hijack Attempt',
        ]);

        $this->assertSame(
            1,
            (int) $location->fresh()->account_id,
            'GuardsTenantBoundary::creating must force account_id to the authenticated user, '
            .'overwriting any explicit value passed in by mass-assignment.'
        );
    }

    public function test_updating_account_id_on_an_existing_row_is_reverted(): void
    {
        $admin = $this->actingAsAdmin();
        $location = Locations::factory()->create();
        $this->assertSame(1, (int) $location->fresh()->account_id);

        // Try to mass-assign a different tenant on update.
        $location->update(['account_id' => 999, 'name' => 'Tampered Name']);

        $reloaded = $location->fresh();
        $this->assertSame(
            1,
            (int) $reloaded->account_id,
            'GuardsTenantBoundary::updating must revert any dirty change to account_id.'
        );
        // Non-guarded columns must still update normally — the guard is
        // surgical, not a blanket update block.
        $this->assertSame('Tampered Name', $reloaded->name);
    }

    public function test_user_model_protects_user_type_id_against_privilege_escalation(): void
    {
        // The User model extends the guarded set to include user_type_id and
        // is_admin so that mass-assignment of those columns cannot turn a
        // regular user into a privileged one.
        $this->actingAsAdmin();
        $patient = User::factory()->patient()->create();
        $this->assertSame(3, (int) $patient->user_type_id);

        $patient->update(['user_type_id' => 1, 'name' => 'Promoted']);

        $reloaded = $patient->fresh();
        $this->assertSame(
            3,
            (int) $reloaded->user_type_id,
            'User::user_type_id is in the guarded list and must not be mutable post-create.'
        );
        $this->assertSame('Promoted', $reloaded->name);
    }

    public function test_creating_without_an_authenticated_user_does_not_force_account_id(): void
    {
        // Background jobs and console commands often run with no Auth::user().
        // The guard's creating-event hook is conditional on Auth::check(), so
        // an unauthenticated insert keeps whatever account_id the caller
        // explicitly set. This test pins that behaviour as the documented
        // contract for service code that runs outside an HTTP request.
        $this->assertFalse(\Illuminate\Support\Facades\Auth::check());

        $location = Locations::factory()->create(['account_id' => 999]);

        $this->assertSame(999, (int) $location->fresh()->account_id);
    }
}
