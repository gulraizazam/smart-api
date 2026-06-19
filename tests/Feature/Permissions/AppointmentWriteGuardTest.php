<?php

declare(strict_types=1);

namespace Tests\Feature\Permissions;

use App\Models\Appointments;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the 4 legacy appointment-write endpoints from the 2026-06-19 QA (#15/#19).
 * Each previously ran with NO permission check AND loaded its record by raw id
 * with no account_id filter (cross-tenant write IDOR). They are SPA-dead (the SPA
 * reschedules via /api/treatments/drag-drop-reschedule and uses the Api status/SMS
 * endpoints), so gating them on appointments_manage + scoping via byAccount() has
 * no live-SPA impact.
 *
 * Teeth: drop a gate → "requires appointments_manage" reddens; drop a byAccount()
 * scope → the cross-tenant case mutates the victim and reddens.
 */
class AppointmentWriteGuardTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    private function seedAccount(int $id): void
    {
        DB::table('accounts')->updateOrInsert(
            ['id' => $id],
            ['name' => "Acct {$id}", 'email' => "a{$id}@x.test", 'contact' => '0000000000', 'suspended' => '0', 'created_at' => now(), 'updated_at' => now()],
        );
    }

    private function actInAccount(int $account, array $perms): void
    {
        $this->seedAccount($account);
        foreach ($perms as $p) {
            $this->createPermission($p);
        }
        $role = $this->createRole('aw_'.uniqid());
        if ($perms !== []) {
            $role->givePermissionTo($perms);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create(['account_id' => $account]);
        DB::table('users')->where('id', $user->id)->update(['account_id' => $account]);
        $user->refresh();
        $this->assignRoleWithPivot($user, $role);
        $this->actingAs($user);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** http verb | route URI */
    public static function writeEndpoints(): array
    {
        return [
            'update_schedule' => ['POST', 'api/appointments/update/schedule'],
            'service_schedule' => ['POST', 'api/appointments/check-and-save-service-appointment'],
            'store_status' => ['PUT', 'api/appointments/store/appointmentstatus'],
            'resend_sms' => ['PUT', 'api/appointments/send/logged_sms'],
        ];
    }

    #[DataProvider('writeEndpoints')]
    public function test_endpoint_requires_appointments_manage(string $method, string $route): void
    {
        $this->createPermission('appointments_manage'); // exists, NOT granted
        $this->actInAccount(1, []);

        $this->json($method, '/'.$route, ['id' => 1, 'appointment_id' => 1])->assertStatus(403);
    }

    public function test_cannot_reschedule_another_accounts_appointment(): void
    {
        $victim = Appointments::factory()->create(['scheduled_date' => '2026-01-01', 'scheduled_time' => '10:00:00']);
        DB::table('appointments')->where('id', $victim->id)->update(['account_id' => 999]);

        $this->actInAccount(1, ['appointments_manage']);

        $this->postJson('/api/appointments/update/schedule', [
            'appointment_id' => $victim->id,
            'scheduled_date' => '2026-12-31',
            'scheduled_time' => '15:00:00',
        ]);

        $this->assertSame(
            '2026-01-01',
            $victim->fresh()->scheduled_date->format('Y-m-d'),
            'a user must not be able to reschedule another account\'s appointment',
        );
    }

    public function test_cannot_change_another_accounts_appointment_status(): void
    {
        $victim = Appointments::factory()->create();
        DB::table('appointments')->where('id', $victim->id)->update(['account_id' => 999]);
        $original = (int) $victim->fresh()->base_appointment_status_id;

        $this->actInAccount(1, ['appointments_manage']);

        $this->putJson('/api/appointments/store/appointmentstatus', [
            'id' => $victim->id,
            'base_appointment_status_id' => $original + 7,
        ]);

        $this->assertSame(
            $original,
            (int) $victim->fresh()->base_appointment_status_id,
            'a user must not be able to change another account\'s appointment status',
        );
    }
}
