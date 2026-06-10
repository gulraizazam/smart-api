<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Helpers\ACL;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * ACL::getUserCentres memoises per (lookup, user id) in a static that lives
 * for the whole PHP process. In a long-lived process (test suite, queue
 * worker) user ids repeat after every DB refresh, so without an explicit
 * flush one test's centre set leaks into another's same-id user — which
 * surfaced as FinanceLedgerPlanTotalTest's plan silently vanishing from a
 * location-scoped report. TestCase::setUp flushes per test; this pins the
 * memo + flush mechanics themselves.
 */
class AclMemoFlushTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    public function test_memo_serves_stale_centres_until_flushed(): void
    {
        $this->seedFinancialFixtures();
        $user = $this->actingAsAdmin();
        DB::table('user_has_locations')->insert([
            'user_id' => $user->id,
            'location_id' => $this->defaultLocation->id,
            'region_id' => $this->defaultLocation->region_id,
        ]);

        $first = ACL::getUserCentres();
        $this->assertContains((int) $this->defaultLocation->id, $first);

        // Memoised: revoking the centre is NOT visible within the same
        // request lifetime — by design (one consistent view per request).
        DB::table('user_has_locations')->where('user_id', $user->id)->delete();
        $this->assertSame($first, ACL::getUserCentres());

        // Flushed: the next lookup re-reads the DB and sees the revocation.
        ACL::flushMemo();
        $this->assertSame([], ACL::getUserCentres());
    }
}
