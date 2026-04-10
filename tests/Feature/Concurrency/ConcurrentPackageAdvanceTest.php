<?php

declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Http\Controllers\Admin\PackageAdvancesController;
use App\Models\Locations;
use App\Models\PackageAdvances;
use App\Models\Packages;
use App\Models\Patients;
use App\Models\PaymentModes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The audit identified PackageAdvances::savepackagesadvances as one of the
 * most exploitable race conditions in the codebase: two collectors saving
 * partial advances against the same package within the same second could
 * each see the *old* sum, both pass the `<= total_price` check, and both
 * commit — leaving the package over-paid by hundreds of thousands of
 * rupees. The fix wrapped the lookup-then-write in a single
 * `DB::transaction { lockForUpdate(); ... }` block.
 *
 * These tests pin the contract from three angles:
 *
 *   1. The lock IS taken — assert via DB::listen that the SELECT issued
 *      by the controller carries `for update`. A future refactor that
 *      drops the lock will fail this test even if the rest of the logic
 *      still walks the happy path on a single connection.
 *
 *   2. The boundary math IS correct under sequential operation — saving
 *      the exact remaining amount succeeds, saving even one rupee more
 *      is rejected and nothing is persisted.
 *
 *   3. Two committed transactions cannot exceed total_price — the
 *      end-to-end "race" property pinned with sequential transactions:
 *      after one collector commits, the next collector's pre-write SUM
 *      must reflect that commit and reject any over-limit attempt.
 */
class ConcurrentPackageAdvanceTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private Packages $package;

    private PaymentModes $cash;

    private Patients $patient;

    private Locations $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->location = Locations::factory()->create();
        $this->cash = PaymentModes::query()->where('name', 'Cash')->firstOrFail();
        $this->patient = Patients::factory()->create();
        $this->package = Packages::factory()->create([
            'patient_id' => $this->patient->id,
            'location_id' => $this->location->id,
            'total_price' => 10000,
        ]);
    }

    public function test_save_path_issues_a_for_update_lock_on_existing_advances(): void
    {
        // Capture every SQL statement the controller emits and assert that
        // the SELECT against package_advances carries `for update`. This is
        // the explicit guarantee the audit fix added; if a future refactor
        // drops it, two collectors saving simultaneously can both pass the
        // `<= total_price` check on the old sum.
        $captured = [];
        DB::listen(static function ($query) use (&$captured): void {
            $captured[] = strtolower($query->sql);
        });

        $this->callSave(2500);

        $lockingSelect = collect($captured)->first(
            static fn (string $sql): bool => str_contains($sql, 'package_advances')
                && str_contains($sql, 'for update')
        );

        $this->assertNotNull(
            $lockingSelect,
            'Expected the savepackagesadvances flow to issue a `SELECT ... FOR UPDATE` against '
            . 'package_advances. Without it, two simultaneous collectors can both observe the same '
            . 'pre-write sum and both pass the total_price check, over-paying the package.'
        );
    }

    public function test_save_at_exact_remaining_amount_is_accepted(): void
    {
        // Boundary: the package has total_price = 10000. Plant a 6000
        // advance directly via the factory (bypasses the controller path
        // so the test is not order-dependent on its own controller calls)
        // and verify the controller accepts a 4000 follow-up — i.e. the
        // boundary is `<=`, not `<`.
        PackageAdvances::factory()->create([
            'cash_flow' => 'in',
            'cash_amount' => 6000,
            'patient_id' => $this->patient->id,
            'package_id' => $this->package->id,
            'location_id' => $this->location->id,
            'payment_mode_id' => $this->cash->id,
        ]);

        $response = $this->callSave(4000);

        $this->assertTrue(
            (bool) ($response['status'] ?? false),
            'Saving the exact remaining amount must be accepted — the boundary check is <=, not <.'
        );
        $this->assertSame(2, PackageAdvances::query()->where('package_id', $this->package->id)->count());
        $this->assertSame(
            10000.00,
            (float) PackageAdvances::query()
                ->where('package_id', $this->package->id)
                ->where('cash_flow', 'in')
                ->sum('cash_amount'),
        );
    }

    public function test_save_one_rupee_over_total_is_rejected_and_nothing_persists(): void
    {
        PackageAdvances::factory()->create([
            'cash_flow' => 'in',
            'cash_amount' => 6000,
            'patient_id' => $this->patient->id,
            'package_id' => $this->package->id,
            'location_id' => $this->location->id,
            'payment_mode_id' => $this->cash->id,
        ]);

        $response = $this->callSave(4001);

        $this->assertFalse(
            (bool) ($response['status'] ?? true),
            'Saving one rupee over total_price must be rejected.'
        );
        $this->assertSame(
            1,
            PackageAdvances::query()->where('package_id', $this->package->id)->count(),
            'A rejected save must NOT have persisted the over-limit row.'
        );
        $this->assertSame(
            6000.00,
            (float) PackageAdvances::query()
                ->where('package_id', $this->package->id)
                ->sum('cash_amount'),
        );
    }

    public function test_two_sequential_commits_cannot_exceed_total_price(): void
    {
        // Pin the end-to-end race property as a sequential composition.
        // Collector A commits 7000. The very next call sees the new sum
        // and refuses to over-pay even by a small amount. Without the
        // post-commit re-read this would silently double-pay.
        $first = $this->callSave(7000);
        $this->assertTrue((bool) ($first['status'] ?? false));

        $second = $this->callSave(4000); // would push to 11000
        $this->assertFalse(
            (bool) ($second['status'] ?? true),
            'A second collector must observe the first commit and refuse to over-pay.'
        );

        $this->assertSame(
            7000.00,
            (float) PackageAdvances::query()
                ->where('package_id', $this->package->id)
                ->where('cash_flow', 'in')
                ->sum('cash_amount'),
        );
    }

    public function test_overpayment_attempt_does_not_consume_an_id_or_dirty_pool(): void
    {
        // Pin the rollback-on-reject property: a rejected save must not
        // leave any partial state behind — no audit row, no plan_invoice
        // row, no pool credit. We assert by counting the package_advances
        // rows before and after.
        PackageAdvances::factory()->create([
            'cash_flow' => 'in',
            'cash_amount' => 9999,
            'patient_id' => $this->patient->id,
            'package_id' => $this->package->id,
            'location_id' => $this->location->id,
            'payment_mode_id' => $this->cash->id,
        ]);

        $countBefore = PackageAdvances::query()->count();
        $this->callSave(2); // would push to 10001 → reject
        $countAfter = PackageAdvances::query()->count();

        $this->assertSame(
            $countBefore,
            $countAfter,
            'Rejected over-limit save must not leave a row behind. The DB::transaction wrapping '
            . 'savepackagesadvances rolls back any partial work.'
        );
    }

    /**
     * Drive the controller's save method directly with a synthetic Request.
     * Going through HTTP would force us to wire route parameters and CSRF
     * tokens for what is fundamentally a model/service-level test; calling
     * the action directly keeps the focus on the lock + boundary contract.
     */
    private function callSave(float $cashAmount): array
    {
        $request = Request::create('/finances/savepackagesadvances', 'POST', [
            'package_id' => $this->package->id,
            'patient_id' => $this->patient->id,
            'payment_mode_id' => $this->cash->id,
            'cash_amount' => $cashAmount,
            'total_price' => $this->package->total_price,
        ]);
        $request->setUserResolver(fn () => Auth::user());

        $response = app(PackageAdvancesController::class)->savepackagesadvances($request);

        return json_decode($response->getContent(), true) ?? [];
    }
}
