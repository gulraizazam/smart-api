<?php

declare(strict_types=1);

namespace Tests\Feature\Services\SMS;

use App\Enums\AppointmentType;
use App\Models\Appointments;
use App\Models\SMSLogs;
use App\Services\SMS\AppointmentSmsDispatcher;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the policy of the shared SMS dispatcher used by:
 *   - appointment:deliver-on-appointment-book (booking SMS, every minute)
 *   - appointment:2nd-message-on-appointment-day (reminder SMS, daily)
 *   - any future SPA controller that emits these messages
 *
 * Focused on the SKIP branches that signal "won't dispatch" — the
 * provider-call success path requires live SMS API credentials and
 * is exercised in production logs.
 */
class AppointmentSmsDispatcherTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private AppointmentSmsDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->dispatcher = app(AppointmentSmsDispatcher::class);

        // The schema dump ships pre-seeded sms_templates + a default
        // `sys-current-sms-operator` setting. Wipe both so each test
        // starts from a clean slate and skip-reason assertions are
        // deterministic. (The `users` table is left alone — patients
        // are needed for the phone lookup.)
        DB::table('sms_templates')->delete();
        DB::table('settings')->where('slug', 'sys-current-sms-operator')->delete();
        DB::table('user_operator_settings')->delete();
    }

    public function test_skips_when_template_missing(): void
    {
        $appointment = Appointments::factory()->create([
            'appointment_type_id' => AppointmentType::Consultancy->value,
        ]);

        $result = $this->dispatcher->dispatchBookingMessage($appointment);

        $this->assertFalse($result['sent']);
        $this->assertSame('template_missing', $result['reason']);
        $this->assertSame(
            0,
            SMSLogs::query()->where('appointment_id', $appointment->id)->count(),
            'No SMSLog row should be created when the template is missing.',
        );
    }

    public function test_skips_when_operator_setting_missing(): void
    {
        $appointment = Appointments::factory()->create([
            'appointment_type_id' => AppointmentType::Consultancy->value,
        ]);
        $this->seedTemplate('on-appointment', (int) $appointment->account_id);

        $result = $this->dispatcher->dispatchBookingMessage($appointment);

        $this->assertFalse($result['sent']);
        $this->assertSame('operator_setting_missing', $result['reason']);
    }

    public function test_skips_when_credentials_missing(): void
    {
        $appointment = Appointments::factory()->create([
            'appointment_type_id' => AppointmentType::Consultancy->value,
        ]);
        $this->seedTemplate('on-appointment', (int) $appointment->account_id);
        $this->seedOperatorSetting(AppointmentSmsDispatcher::OPERATOR_TELENOR);

        $result = $this->dispatcher->dispatchBookingMessage($appointment);

        $this->assertFalse($result['sent']);
        $this->assertSame('credentials_missing', $result['reason']);
    }

    public function test_resolves_treatment_template_for_treatment_appointment(): void
    {
        $appointment = Appointments::factory()->create([
            'appointment_type_id' => AppointmentType::Treatment->value,
        ]);
        // Only seed the consultancy slug; treatment lookup must miss.
        $this->seedTemplate('on-appointment', (int) $appointment->account_id);

        $result = $this->dispatcher->dispatchBookingMessage($appointment);

        $this->assertSame(
            'template_missing',
            $result['reason'],
            'Treatment must look up `treatment-on-appointment`, not the consultancy slug.',
        );
    }

    public function test_resolves_virtual_template_for_virtual_consultancy(): void
    {
        $appointment = Appointments::factory()->create([
            'appointment_type_id' => AppointmentType::Consultancy->value,
            'consultancy_type' => 'virtual',
        ]);
        // Only seed the in-person slug; virtual lookup must miss.
        $this->seedTemplate('on-appointment', (int) $appointment->account_id);

        $result = $this->dispatcher->dispatchBookingMessage($appointment);

        $this->assertSame(
            'template_missing',
            $result['reason'],
            'Virtual consultancy must look up `virtual-on-appointment`, not the in-person slug.',
        );
    }

    public function test_reminder_dispatch_picks_second_sms_slug(): void
    {
        $appointment = Appointments::factory()->create([
            'appointment_type_id' => AppointmentType::Consultancy->value,
            'consultancy_type' => 'in_person',
        ]);
        // Seed the booking slug only; reminder must look up `second-sms`.
        $this->seedTemplate('on-appointment', (int) $appointment->account_id);

        $result = $this->dispatcher->dispatchReminderMessage($appointment);

        $this->assertSame(
            'template_missing',
            $result['reason'],
            'Reminder must look up `second-sms`, not the booking slug.',
        );
    }

    private function seedTemplate(string $slug, int $accountId): void
    {
        DB::table('sms_templates')->insert([
            'slug' => $slug,
            'name' => 'Test SMS — '.$slug,
            'content' => 'Hello {name}, your appointment is confirmed.',
            'account_id' => $accountId,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedOperatorSetting(int $operator): void
    {
        DB::table('settings')->updateOrInsert(
            ['slug' => 'sys-current-sms-operator'],
            [
                'name' => 'Current SMS Operator',
                'data' => (string) $operator,
                'account_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
}
