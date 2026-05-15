<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\AppointmentStatuses;
use App\Models\AppointmentTypes;
use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\ExpenseCategory;
use App\Models\InvoiceStatuses;
use App\Models\Locations;
use App\Models\PaymentModes;
use App\Models\Services;
use App\Models\UserTypes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Trait for tests that need a deterministic financial-domain fixture
 * (locations, payment modes, services, expense categories, cash pools)
 * but do NOT want to seed the entire production seeder set.
 *
 * Each helper is idempotent — calling it twice in the same test (e.g.
 * once in setUp() and once again inline) produces the same fixture
 * without duplicating rows.
 *
 * Usage:
 *
 *     class ExpenseLifecycleTest extends TestCase
 *     {
 *         use RefreshDatabase, UsesFinancialFixtures;
 *
 *         public function setUp(): void
 *         {
 *             parent::setUp();
 *             $this->seedFinancialFixtures();
 *         }
 *     }
 */
trait UsesFinancialFixtures
{
    protected ?Locations $defaultLocation = null;

    protected ?PaymentModes $cashPaymentMode = null;

    protected ?CashPool $defaultCashPool = null;

    /**
     * Seed every fixture needed by the financial-spine feature tests.
     * Order matters — payment modes & locations are referenced by other
     * factories.
     */
    protected function seedFinancialFixtures(): void
    {
        // Flush the cache before seeding so test N+1 doesn't read a
        // stale value left behind by test N — most painful with
        // `Cache::remember`-backed search results (e.g.
        // `Patients::getPatientSearchOptimized`) keyed only by query +
        // account, which would otherwise return cached-empty across
        // tests that should each see a freshly-seeded row.
        Cache::flush();

        $this->seedDefaultAccount();
        $this->seedRegionsAndCities();
        $this->seedTaxTreatmentTypes();
        $this->seedUserTypes();
        $this->seedDefaultLocation();
        $this->seedPaymentModes();
        $this->seedAppointmentLookups();
        $this->seedInvoiceStatuses();
        $this->seedDefaultService();
        $this->seedExpenseCategories();
        $this->seedDefaultCashPool();
        $this->seedCashflowPermissions();
    }

    /**
     * Seed the Spatie permissions referenced by the CashFlow notification
     * machinery. NotificationService::getBranchManagers() (and a couple of
     * sibling methods) call `User::permission('cashflow_dashboard')` from
     * inside expense / transfer / advance create flows. If the row isn't in
     * the test DB, Spatie throws PermissionDoesNotExist and any test that
     * touches a CashFlow service indirectly fails — even when the test
     * itself has nothing to do with notifications. Pin the permissions
     * here so the trait stays the single source of truth for "what does
     * the financial spine need to be runnable in tests".
     *
     * Also forget the cached permissions once at the start of every test
     * that uses this trait. Spatie caches the registrar in the container
     * across the process, and a test that just rolled back its transaction
     * can leave the cache pointing at a now-vanished id, which then surfaces
     * in the next test as a confusing PermissionDoesNotExist.
     */
    protected function seedCashflowPermissions(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([
            'cashflow_dashboard',
            'cashflow_expense_approve',
            'cashflow_vendor_manage',
            'cashflow_category_manage',
            'cashflow_approve',
            'cashflow_void',
            'cashflow_admin',
        ] as $permission) {
            \Spatie\Permission\Models\Permission::query()->firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }
    }

    /**
     * `services.tax_treatment_type_id` FKs `tax_treatment_type(id)`. Seed
     * a single "Standard" treatment so factories that pin id=1 can insert.
     */
    protected function seedTaxTreatmentTypes(): void
    {
        // Seed id=1..3 because ServiceHelper::prepareServiceData()
        // rewrites id=1 to DEFAULT_TAX_TREATMENT_TYPE=3 before the
        // insert, so a service created via the service layer lands
        // on id=3. A test DB that only carries id=1 fails the FK.
        $rows = [
            ['id' => 1, 'name' => 'Standard'],
            ['id' => 2, 'name' => 'Exempt'],
            ['id' => 3, 'name' => 'Default'],
        ];

        foreach ($rows as $row) {
            DB::table('tax_treatment_type')->updateOrInsert(
                ['id' => $row['id']],
                [
                    'name' => $row['name'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    /**
     * Many tables (user_types, locations, services, ...) carry an FK to
     * `accounts(id)`. The schema dump preserves those FKs and the test DB
     * uses real referential integrity, so a tenant row must exist before
     * any factory referencing account_id = 1 can be created.
     */
    protected function seedDefaultAccount(): void
    {
        DB::table('accounts')->updateOrInsert(
            ['id' => 1],
            [
                'name' => 'Test Account',
                'email' => 'test@example.com',
                'contact' => '0000000000',
                'suspended' => '0',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * The locations table FKs city_id → cities and region_id → regions, and
     * cities itself FKs region_id → regions. LocationFactory pins both ids
     * to 1, so we seed a single region/city pair to satisfy the chain.
     * Both tables have a NOT NULL `slug` (default 'custom') and `name`.
     */
    protected function seedRegionsAndCities(): void
    {
        DB::table('regions')->updateOrInsert(
            ['id' => 1],
            [
                'slug' => 'test-region',
                'name' => 'Test Region',
                'active' => 1,
                'account_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('cities')->updateOrInsert(
            ['id' => 1],
            [
                'slug' => 'test-city',
                'name' => 'Test City',
                'active' => 1,
                'is_featured' => 0,
                'region_id' => 1,
                'account_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * The Invoices model resolves the cancelled status by `slug = cancelled`
     * (see Invoices::CancelRecord). Tests that exercise the cancel flow need
     * the slug-based lookup to succeed even though the lookup table is
     * normally seeded by a production seeder.
     *
     * `slug` is NOT in InvoiceStatuses::$fillable (the production seeder
     * writes it via the query builder). Going through Eloquent silently
     * drops the slug — use DB::table to bypass mass-assignment.
     */
    protected function seedInvoiceStatuses(): void
    {
        foreach ([
            ['id' => 1, 'name' => 'Pending', 'slug' => 'pending'],
            ['id' => 2, 'name' => 'Cancelled', 'slug' => 'cancelled'],
            ['id' => 3, 'name' => 'Paid', 'slug' => 'paid'],
        ] as $row) {
            DB::table('invoice_statuses')->updateOrInsert(
                ['id' => $row['id']],
                $row + [
                    'account_id' => 1,
                    'active' => 1,
                    'sort_no' => $row['id'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    protected function seedUserTypes(): void
    {
        // The tenant-isolation guards in BaseModel.php read user_type_id
        // and treat 3 = Patient, 5 = Doctor (see User::$PATIENT_GROUP /
        // $DOCTOR_GROUP). Seed both plus a generic application_user.
        // The `type` column is NOT NULL in production and stores a free-form
        // category slug — pin it here so the schema-loaded test DB accepts
        // the row.
        //
        // `id` is NOT in UserTypes::$fillable, so an Eloquent updateOrCreate
        // would silently drop the explicit id and let auto-increment assign
        // 1,2,3 instead of the expected 1,3,5. Use DB::table to bypass
        // mass-assignment and pin the canonical ids exactly.
        foreach ([
            ['id' => 1, 'name' => 'Application User', 'type' => 'staff'],
            ['id' => 3, 'name' => 'Patient', 'type' => 'patient'],
            ['id' => 5, 'name' => 'Doctor', 'type' => 'doctor'],
        ] as $type) {
            DB::table('user_types')->updateOrInsert(
                ['id' => $type['id']],
                $type + [
                    'active' => 1,
                    'account_id' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    protected function seedDefaultLocation(): Locations
    {
        if ($this->defaultLocation === null) {
            $this->defaultLocation = Locations::factory()->create([
                'name' => 'Test Centre',
            ]);
        }

        return $this->defaultLocation;
    }

    protected function seedPaymentModes(): void
    {
        // Pin ids to match the hardcoded constants in InvoiceGenerationService
        // (PAYMENT_MODE_CASH=1, PAYMENT_MODE_CARD=2, PAYMENT_MODE_BANK=4). The
        // numeric gap at id=3 mirrors production where PayPal was soft-deleted
        // but its id slot is reserved. firstOrCreate-without-id silently broke
        // these tests across runs because MySQL doesn't reset AUTO_INCREMENT
        // on rolled-back transactions — Cash drifted to id=9+ after a few
        // tests had reserved low ids.
        $modes = [
            1 => 'Cash',
            2 => 'Card',
            4 => 'Bank Transfer',
            5 => 'Cheque',
        ];

        foreach ($modes as $id => $name) {
            DB::table('payment_modes')->updateOrInsert(
                ['id' => $id],
                [
                    'name' => $name,
                    'active' => 1,
                    'account_id' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        $this->cashPaymentMode = PaymentModes::query()->find(1);
    }

    protected function seedAppointmentLookups(): void
    {
        foreach ([
            ['id' => 1, 'name' => 'Booked', 'slug' => 'booked'],
            ['id' => 2, 'name' => 'Arrived', 'slug' => 'arrived'],
            ['id' => 3, 'name' => 'In Progress', 'slug' => 'in-progress'],
            ['id' => 4, 'name' => 'Completed', 'slug' => 'completed'],
            ['id' => 5, 'name' => 'Cancelled', 'slug' => 'cancelled'],
        ] as $row) {
            AppointmentStatuses::query()->updateOrCreate(
                ['id' => $row['id']],
                $row + ['active' => 1, 'account_id' => 1]
            );
        }

        // appointment_types.slug is NOT NULL with no default in production
        // but is NOT in the model's $fillable (the production seeder writes
        // it via the query builder). Bypass Eloquent here for the same
        // reason — using DB::table avoids the mass-assignment filter while
        // still respecting the schema constraint.
        foreach ([
            ['id' => 1, 'name' => 'Treatment', 'slug' => 'treatment'],
            ['id' => 2, 'name' => 'Consultancy', 'slug' => 'consultancy'],
        ] as $row) {
            DB::table('appointment_types')->updateOrInsert(
                ['id' => $row['id']],
                $row + [
                    'active' => 1,
                    'account_id' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    protected function seedDefaultService(): Services
    {
        return Services::factory()->create([
            'name' => 'Test Service',
            'price' => 1000.00,
        ]);
    }

    protected function seedExpenseCategories(): void
    {
        ExpenseCategory::factory()->count(3)->create();
    }

    protected function seedDefaultCashPool(): CashPool
    {
        if ($this->defaultCashPool === null) {
            $this->defaultCashPool = CashPool::factory()->create([
                'name' => 'Main Cash',
                'opening_balance' => 0,
            ]);
        }

        return $this->defaultCashPool;
    }
}
