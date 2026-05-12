# Cutera test suite

A layered PHPUnit test suite for the Cutera clinic management CRM. Built during the principal-QA hardening pass that followed the Laravel 12 / PHP 8.4 upgrade — see `memory/project_audit_progress.md` and `memory/project_database_audit.md` for the audit findings the suite was written to defend against.

## Current metrics

| Metric | Count |
|---|---|
| Test files | 103 |
| Test methods | 634 |
| Assertions | 1,335 |
| Factories | 35 |
| Test traits | 4 |

### Breakdown by layer

| Layer | Files | Methods |
|---|---|---|
| Unit / Security | 7 | 77 |
| Unit / Helpers | 3 | 37 |
| Unit / Enums | 5 | 26 |
| Unit / Models | 3 | 13 |
| Unit / Observers | 7 | 28 |
| Unit / Requests | 10 | 83 |
| Unit / Services | 1 | — |
| Feature / Auth | 3 | 16 |
| Feature / Authorization | 4 | 13 |
| Feature / Patients | 5 | 35 |
| Feature / Appointments | 5 | 38 |
| Feature / Invoices | 1 | 3 |
| Feature / Packages | 1 | 4 |
| Feature / CashFlow | 5 | 25 |
| Feature / Memberships | 5 | 39 |
| Feature / Discounts | 6 | 42 |
| Feature / Reports | 6 | 19 |
| Feature / Leads | 3 | 24 |
| Feature / Settings | 4 | 21 |
| Feature / Api | 4 | 13 |
| Feature / Commands | 7 | 21 |
| Feature / Concurrency | 4 | 23 |
| Integration | 4 | 16 |

## What is here

```
tests/
├── CreatesApplication.php       — Laravel bootstrap trait (untouched)
├── TestCase.php                 — base test class with auth & assertion helpers
├── Concerns/                    — reusable test traits
│   ├── UsesFinancialFixtures.php
│   ├── AssertsAuditTrail.php
│   ├── AssertsTransactional.php
│   └── UsesSecondConnection.php
├── Unit/
│   ├── Helpers/                 — pure-logic helpers (sanitize_money, etc.)
│   ├── Enums/                   — App\Enums\* assertions
│   ├── Models/                  — scopes, casts, observers, global scopes
│   ├── Services/                — pricing, refund, dashboard calculators
│   ├── Requests/                — FormRequest validation rules
│   ├── Observers/               — CashFlow audit-log observers
│   └── Security/                — pre-existing security regression tests
├── Feature/
│   ├── Auth/                    — login, 2FA, lockout, password reset
│   ├── Authorization/           — gates, IP restriction, tenant isolation
│   ├── Patients/                — patient CRUD + documents
│   ├── Appointments/            — CRUD, scheduling, status machine
│   ├── Invoices/                — creation, cancellation, tax, PDF
│   ├── Packages/                — packages + advances + refunds
│   ├── CashFlow/                — expenses, transfers, vendors, period locks
│   ├── Memberships/             — assignment, renewal, expiry
│   ├── Discounts/               — discounts and vouchers
│   ├── Reports/                 — finance, revenue, conversion, ops
│   ├── Leads/                   — lead CRUD + conversion
│   ├── Settings/                — locations, services, resources, payment modes
│   ├── Api/                     — JSON shape, pagination, Sanctum
│   ├── Commands/                — console commands + jobs
│   └── Concurrency/             — race conditions on the financial spine
└── Integration/
    ├── LeadToInvoiceFlowTest
    ├── RefundEndToEndTest
    ├── MembershipPurchaseFlowTest
    └── CashTransferAcrossPoolsTest
```

## One-time setup (local dev)

The suite **must** run against a dedicated MariaDB database — never the production schema. The connection is wired through the `mariadb_testing` block in `config/database.php`.

```bash
# 1. Create the test database (MariaDB 11.x; prod is MariaDB 11.8)
mariadb -u root -p -e "CREATE DATABASE cutera_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Configure your local .env (or copy from .env.example)
#    Add the following lines to your .env if they aren't already there:
#
#       DB_TEST_HOST=127.0.0.1
#       DB_TEST_PORT=3307
#       DB_TEST_DATABASE=cutera_test
#       DB_TEST_USERNAME=root
#       DB_TEST_PASSWORD=

# 3. Apply migrations to the test DB
php artisan migrate --database=mariadb_testing --force

# 4. Run the suite
php artisan test
```

Alternatively, if you maintain a `.env.testing` file, Laravel will pick it up automatically when `APP_ENV=testing` (which `phpunit.xml` sets).

## Running tests

```bash
# Whole suite
php artisan test

# Just one suite
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature

# By directory
php artisan test --filter=CashFlow
php artisan test tests/Feature/Invoices

# By single test method
php artisan test --filter=test_overpayment_is_rejected

# With coverage (requires Xdebug or pcov)
php artisan test --coverage --min=40

# Concurrency tests are slower and intentionally exercise lockForUpdate.
# Mark them as a separate group so CI can retry only this group on flake.
php artisan test --group=concurrency
```

## Conventions

### Test base classes
- **Pure logic** (no Laravel container, no DB) — extend `PHPUnit\Framework\TestCase`. Fastest. The pre-existing `tests/Unit/Security/*` tests follow this pattern.
- **Laravel container needed but no DB** (e.g., testing a `Request` parser) — extend `Tests\TestCase`. The pre-existing `GetSortByTest` follows this pattern.
- **Database access needed** — extend `Tests\TestCase` and `use Illuminate\Foundation\Testing\RefreshDatabase`. Each test runs inside a transaction that rolls back at teardown — no manual cleanup required.

### Acting as a user
`Tests\TestCase` exposes opt-in helpers for the most common roles. Each helper creates a fresh user via the relevant factory state and calls `actingAs()`.

```php
$this->actingAsAdmin();              // Super-Admin
$this->actingAsRole('Finance');      // any named role
$this->actingAsDoctor($locationId);  // doctor at a specific location
$this->actingAsPatient();            // patient (user_type_id = 3)
```

### Asserting an audit-trail row
The CashFlow domain writes to `cashflow_audit_logs` via observers — the audit trail is part of the contract. Use the `AssertsAuditTrail` trait to assert that the right row was written.

```php
use Tests\Concerns\AssertsAuditTrail;

class ExpenseLifecycleTest extends TestCase
{
    use RefreshDatabase, AssertsAuditTrail;

    public function test_approval_appends_audit_row(): void
    {
        // ...
        $this->assertCashflowAuditLogged(
            entityType: Expense::class,
            entityId:   $expense->id,
            action:     'approved',
        );
    }
}
```

### Asserting a transaction rollback
The `AssertsTransactional` trait offers a helper that forces an exception at a specific point in a callback and then re-runs the same setup, asserting that no row was persisted.

### Concurrency tests
Concurrency tests are gated behind `@group concurrency`. They use the `UsesSecondConnection` trait to open a second, transaction-independent PDO connection so two simulated users can race against the same row. **Do not** use `pcntl_fork` — it is unreliable on Windows and inside CI containers.

## What is NOT covered

The suite is intentionally focused. The following are out of scope and should be added separately if needed:

- **Browser / Dusk tests** — no Selenium / Chromedriver setup.
- **Performance / load tests** — separate discipline.
- **Mutation tests** (Infection PHP) — useful once baseline coverage exists.
- **External API tests** (Meta Conversions, Google Reviews) — those services are mocked at the boundary; consult the unit tests in `tests/Unit/Services/`.

## CI

A GitHub Actions workflow at `.github/workflows/tests.yml` runs the full suite on every push to `live_master`, `test`, or `staging`, and on every pull-request targeting `live_master`. The workflow:

1. Spins up a MariaDB 11 service container
2. Sets up PHP 8.4 with required extensions
3. Loads the schema dump (`database/schema/mariadb_testing-schema.sql`)
4. Runs pending migrations
5. Executes `php artisan test`

Failing tests block merge.

## Production bugs discovered by this suite

The test suite surfaced three real production bugs during development:

1. **`services.sort_no` integer overflow** — The column was `tinyint(3) unsigned` (max 255), but `Services::createRecord()` sets `sort_no = $record->id`. Any DB that ever created >255 services (including rolled-back inserts that advance `auto_increment`) would crash. Fixed via migration `2026_04_09_140000_widen_services_sort_no_to_int.php`.

2. **`PaymentModeService` contract mismatch** — The service cast request data to `stdClass` but the model expected an array with `->all()`. Fixed by passing arrays directly.

3. **`InactivePackages` command return type** — `handle()` returned `true` instead of `Command::SUCCESS` (int). PHP 8.4 strict return type enforcement would crash the command. Fixed to return proper int constants.

## When a test fails — triage protocol

1. **Read the assertion message.** The test bodies in this suite are dense with `// Why:` comments — start there.
2. **Decide which broke**: the test, the spec, or the code.
   - Test broke (e.g. factory drift after a migration) → fix the test.
   - Spec changed (e.g. business now allows partial refunds without admin sign-off) → update the test and add a comment referencing the JIRA / decision.
   - Code broke (e.g. a refactor removed `lockForUpdate()` from `savepackagesadvances`) → **fix the code**, not the test.
3. **Never weaken a test to make it pass.** The whole point of the suite is that it catches regressions; weakening it is throwing away the warning siren.
