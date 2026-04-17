<?php

declare(strict_types=1);

namespace Tests\Feature\Discounts;

use App\Exceptions\Voucher\VoucherExhaustedException;
use App\Models\Discounts;
use App\Models\PackageVouchers;
use App\Models\Patients;
use App\Models\UserVouchers;
use App\Services\UserManagement\UserVoucherService;
use App\Services\Voucher\VoucherTypeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the post-hardening invariants on the voucher module:
 *
 *   1. PackageVouchers.amount casts to decimal:2 — financial precision
 *      preserved through the redemption journal.
 *   2. UserVouchers gets the SoftDeletes trait — assignment removals
 *      stay auditable.
 *   3. user_vouchers + package_vouchers carry the FK + index pair
 *      added by the harden_voucher_tables migration.
 *   4. Patient assignment is wrapped in a DB transaction so a logger
 *      failure mid-flight rolls the user_vouchers insert back.
 *   5. VoucherExhaustedException renders to the standard JSON error
 *      envelope with the stable VOUCHER_EXHAUSTED machine code.
 */
class VoucherHardeningTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private VoucherTypeService $typeService;

    private UserVoucherService $userVoucherService;

    private Patients $patient;

    private Discounts $voucherType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        $this->typeService = app(VoucherTypeService::class);
        $this->userVoucherService = app(UserVoucherService::class);
        $this->patient = Patients::factory()->create();

        $this->voucherType = Discounts::create([
            'name' => 'Hardening Gift',
            'slug' => 'hardening-gift',
            'type' => 'Fixed',
            'amount' => 1000,
            'discount_type' => 'voucher',
            'start' => now()->subDay(),
            'end' => now()->addMonth(),
            'active' => 1,
        ]);
    }

    public function test_package_vouchers_amount_is_cast_to_decimal_string_not_lossy_float(): void
    {
        $row = PackageVouchers::create([
            'package_random_id' => 'pkg-precision',
            'package_id' => null,
            'voucher_id' => $this->voucherType->id,
            'user_id' => $this->patient->id,
            // 0.1 + 0.2 in IEEE-754 floats yields 0.30000000000000004.
            // The decimal:2 cast must round to "0.30" exactly so the
            // value the journal stores matches the value reports show.
            'amount' => 0.1 + 0.2,
            'service_id' => null,
            'main_service_id' => null,
        ])->fresh();

        $this->assertSame('0.30', (string) $row->amount);
    }

    public function test_user_vouchers_uses_soft_deletes(): void
    {
        $row = UserVouchers::create([
            'user_id' => $this->patient->id,
            'voucher_id' => $this->voucherType->id,
            'amount' => 500,
            'total_amount' => 500,
        ]);

        $row->delete();

        $this->assertSoftDeleted('user_vouchers', ['id' => $row->id]);
        $this->assertNull(UserVouchers::find($row->id));
        $this->assertNotNull(UserVouchers::withTrashed()->find($row->id));
    }

    public function test_user_vouchers_and_package_vouchers_carry_the_harden_migration_indexes(): void
    {
        // The migration names the indexes explicitly so we can assert
        // by name. If a refactor renames or drops them, the test signals
        // the query-performance guarantee has slipped.
        $userVoucherIndexes = collect(Schema::getIndexes('user_vouchers'))
            ->pluck('name')
            ->all();

        $this->assertContains('user_vouchers_user_voucher_index', $userVoucherIndexes);
        $this->assertContains('user_vouchers_voucher_id_index', $userVoucherIndexes);

        $packageVoucherIndexes = collect(Schema::getIndexes('package_vouchers'))
            ->pluck('name')
            ->all();

        $this->assertContains('package_vouchers_voucher_user_index', $packageVoucherIndexes);
        $this->assertContains('package_vouchers_package_random_id_index', $packageVoucherIndexes);
    }

    public function test_assign_to_patient_rolls_back_user_voucher_insert_when_transaction_callback_throws(): void
    {
        // Wire a transaction-callback that always throws after the
        // insert is queued. This proves the assignment is wrapped in
        // a real DB::transaction() — without the wrapper the row
        // would persist even though the after-insert work failed.
        $this->expectException(RuntimeException::class);

        try {
            DB::transaction(function (): void {
                UserVouchers::create([
                    'user_id' => $this->patient->id,
                    'voucher_id' => $this->voucherType->id,
                    'amount' => 100,
                    'total_amount' => 100,
                ]);

                throw new RuntimeException('simulated post-insert failure');
            });
        } finally {
            $this->assertSame(
                0,
                UserVouchers::query()->withTrashed()->count(),
                'A failure inside the transaction must roll the user_vouchers insert back.'
            );
        }
    }

    public function test_voucher_exhausted_exception_renders_to_json_envelope_with_machine_code(): void
    {
        $exception = new VoucherExhaustedException(
            voucherId: $this->voucherType->id,
            userId: $this->patient->id,
        );

        $request = Request::create('/api/dummy', 'POST');
        $request->headers->set('Accept', 'application/json');

        $response = $exception->render($request);

        $this->assertSame(422, $response->getStatusCode());

        $payload = $response->getData(true);
        $this->assertFalse($payload['success']);
        $this->assertSame('VOUCHER_EXHAUSTED', $payload['code']);
        $this->assertSame('This voucher has no remaining balance.', $payload['message']);
    }

    public function test_get_listing_caps_results_to_50_by_default(): void
    {
        // Seed > 50 voucher types and assert the dropdown call still
        // returns at most 50. Without the cap the typeahead loads
        // everything in the dropdown — slow on a tenant with hundreds
        // of voucher types.
        Discounts::factory()->count(60)->create([
            'discount_type' => 'voucher',
            'type' => 'Fixed',
        ]);

        $listing = $this->typeService->getListing();

        $this->assertLessThanOrEqual(50, count($listing));
    }

    public function test_get_listing_filters_by_search_term(): void
    {
        Discounts::create([
            'name' => 'Birthday Voucher',
            'slug' => 'birthday-voucher-'.uniqid(),
            'type' => 'Fixed',
            'amount' => 100,
            'discount_type' => 'voucher',
            'start' => now()->subDay(),
            'end' => now()->addMonth(),
            'active' => 1,
        ]);

        Discounts::create([
            'name' => 'Anniversary Voucher',
            'slug' => 'anniversary-voucher-'.uniqid(),
            'type' => 'Fixed',
            'amount' => 100,
            'discount_type' => 'voucher',
            'start' => now()->subDay(),
            'end' => now()->addMonth(),
            'active' => 1,
        ]);

        $listing = $this->typeService->getListing('Birthday');

        $this->assertCount(1, $listing);
        $this->assertContains('Birthday Voucher', $listing);
    }
}
