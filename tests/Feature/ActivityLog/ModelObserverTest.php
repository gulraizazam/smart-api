<?php

declare(strict_types=1);

namespace Tests\Feature\ActivityLog;

use App\Enums\ActivityLogTier;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Contract tests for App\Observers\ActivityLogObserver + App\Traits\LogsModelActivity,
 * pinned via the User model.
 *
 *   - Create writes a SecurityAudit-tier row with action=created.
 *     (Tier was renamed from `Security` during the HIPAA reclassification —
 *     see App\Enums\ActivityLogTier docblock; old `Security` case is kept
 *     only for legacy rows.)
 *   - Password-only update writes nothing (password is in the global denylist).
 *   - Name update writes a row whose description names the changed field
 *     but NOT the before/after value (activityLogMaskValues defaults true).
 *   - Raw PII (email, phone, password, cnic) NEVER appears in any
 *     description, regardless of operation.
 *   - Soft-delete writes action=deleted.
 */
class ModelObserverTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        // The User factory writes account_id=1 + user_type_id=1; the test
        // schema dump is structure-only and the FK constraints reject the
        // insert unless those parent rows exist. Seed the canonical
        // fixture once so every test in this class gets a clean baseline
        // instead of accidentally piggy-backing on transaction-state
        // leakage from a sibling test.
        $this->seedFinancialFixtures();
    }

    public function test_user_create_writes_security_tier_row(): void
    {
        $before = Activity::count();

        $user = User::factory()->create([
            'name' => 'Zara Khan',
            'email' => 'zara@example.test',
            'password' => Hash::make('correct-horse-battery'),
        ]);

        $this->assertSame($before + 1, Activity::count());

        $row = Activity::orderByDesc('id')->first();
        $this->assertSame('created', $row->action);
        $this->assertSame('user_created', $row->activity_type);
        $this->assertSame(ActivityLogTier::SecurityAudit->value, $row->log_tier);
        $this->assertStringContainsString('User#'.$user->id, (string) $row->description);
    }

    public function test_password_only_update_is_not_logged(): void
    {
        $user = User::factory()->create();
        $before = Activity::count();

        $user->password = Hash::make('new-password-xyz');
        $user->save();

        // Password is globally denylisted, so the filtered change-set is
        // empty and the observer skips the write entirely.
        $this->assertSame($before, Activity::count());
    }

    public function test_name_update_logs_field_name_but_not_value(): void
    {
        $user = User::factory()->create(['name' => 'Original Name']);
        $before = Activity::count();

        $user->name = 'Brand New Name';
        $user->save();

        $this->assertSame($before + 1, Activity::count());

        $row = Activity::orderByDesc('id')->first();
        $this->assertSame('updated', $row->action);
        $this->assertStringContainsString('name', (string) $row->description);
        $this->assertStringNotContainsString('Brand New Name', (string) $row->description);
        $this->assertStringNotContainsString('Original Name', (string) $row->description);
    }

    public function test_pii_never_leaks_into_description(): void
    {
        $email = 'leaky-'.uniqid().'@example.test';
        $phone = '0300-1234567';
        $cnic = '42101-1234567-8';
        $password = 's3cret-'.uniqid();

        $user = User::factory()->create([
            'email' => $email,
            'phone' => $phone,
            'cnic' => $cnic,
            'password' => Hash::make($password),
        ]);

        // Some downstream update so we exercise the update path too.
        $user->name = 'Post Update';
        $user->save();

        $descriptions = Activity::pluck('description')->implode("\n");

        $this->assertStringNotContainsString($email, $descriptions);
        $this->assertStringNotContainsString($phone, $descriptions);
        $this->assertStringNotContainsString($cnic, $descriptions);
        $this->assertStringNotContainsString($password, $descriptions);
    }

    public function test_soft_delete_writes_deleted_row(): void
    {
        $user = User::factory()->create();
        $before = Activity::count();

        $user->delete();

        $this->assertSame($before + 1, Activity::count());

        $row = Activity::orderByDesc('id')->first();
        $this->assertSame('deleted', $row->action);
        $this->assertSame('user_deleted', $row->activity_type);
        $this->assertSame(ActivityLogTier::SecurityAudit->value, $row->log_tier);
    }
}
