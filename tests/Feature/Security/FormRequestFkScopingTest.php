<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Appointments;
use App\Models\Leads;
use App\Models\Patients;
use App\Models\Services;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Security audit 2026-06 — FormRequest FK rules are account-scoped.
 *
 * Two FormRequests validated a tenant-owned FK with a bare `exists:`, which
 * lets a client point the FK at another tenant's row (cross-tenant FK
 * injection):
 *   - StoreFeedbackRequest        `treatment` → appointments.id
 *   - StoreUpdateLeadCommentsRequest `lead_id` → leads.id
 * The fix uses Rule::exists()->where('account_id', caller)->whereNull(
 * 'deleted_at'), so a foreign-account id fails validation (422) while a
 * same-account id passes.
 *
 * The caller is an account-1 Super-Admin (gate bypass) so the *gate* never
 * masks the validation behaviour under test. Foreign (account 2) rows are
 * created logged-out so GuardsTenantBoundary doesn't restamp them to 1.
 */
class FormRequestFkScopingTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->seedSecondAccount();
    }

    public function test_store_feedback_rejects_other_account_appointment(): void
    {
        $foreign = $this->treatmentAppointment(accountId: 2);
        $this->actingAsAdmin();

        $this->postJson('/api/feedbacks', [
            'treatment' => $foreign->id,
            'rating' => 8,
        ])->assertStatus(422)->assertJsonValidationErrors('treatment');
    }

    public function test_store_feedback_accepts_own_account_appointment(): void
    {
        $mine = $this->treatmentAppointment(accountId: 1);
        $this->actingAsAdmin();

        // Same-tenant id passes the FK rule — no `treatment` validation error.
        $this->postJson('/api/feedbacks', [
            'treatment' => $mine->id,
            'rating' => 8,
        ])->assertJsonMissingValidationErrors('treatment');
    }

    public function test_store_lead_comment_rejects_other_account_lead(): void
    {
        $foreign = Leads::factory()->create(['account_id' => 2]);
        $this->actingAsAdmin();

        $this->postJson('/api/leads/comment', [
            'lead_id' => $foreign->id,
            'comment' => 'cross-tenant comment',
        ])->assertStatus(422)->assertJsonValidationErrors('lead_id');
    }

    public function test_store_lead_comment_accepts_own_account_lead(): void
    {
        $mine = Leads::factory()->create(['account_id' => 1]);
        $this->actingAsAdmin();

        $this->postJson('/api/leads/comment', [
            'lead_id' => $mine->id,
            'comment' => 'legit comment',
        ])->assertJsonMissingValidationErrors('lead_id');
    }

    /** Arrived treatment appointment with the patient + service store() reads. */
    private function treatmentAppointment(int $accountId): Appointments
    {
        $patient = Patients::factory()->create(['account_id' => $accountId]);
        $service = Services::factory()->create(['account_id' => $accountId]);

        return Appointments::factory()->create([
            'account_id' => $accountId,
            'appointment_type_id' => 2,
            'appointment_status_id' => 2,
            'patient_id' => $patient->id,
            'service_id' => $service->id,
        ]);
    }

    private function seedSecondAccount(): void
    {
        DB::table('accounts')->updateOrInsert(
            ['id' => 2],
            [
                'name' => 'Second Account',
                'email' => 'second@example.com',
                'contact' => '0000000001',
                'suspended' => '0',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }
}
