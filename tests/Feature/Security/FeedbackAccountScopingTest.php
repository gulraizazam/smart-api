<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Appointments;
use App\Models\Feedback;
use App\Models\Patients;
use App\Models\Services;
use App\Models\User;
use App\Services\Feedback\FeedbackService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Security audit 2026-06 — Feedback appointment scoping (IDOR).
 *
 * FeedbackService::store() takes a client-supplied appointment id. It used a
 * bare Appointments::findOrFail($id), so a user with feedbacks_create could
 * attach feedback to another tenant's treatment by guessing the id. The fix
 * scopes the appointment lookup to the caller's account_id (byAccount).
 *
 * Foreign (account 2) rows are created while logged OUT so the
 * GuardsTenantBoundary create-hook doesn't restamp them with account 1.
 */
class FeedbackAccountScopingTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private FeedbackService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->seedSecondAccount();
        $this->service = app(FeedbackService::class);
    }

    public function test_store_rejects_appointment_from_other_account(): void
    {
        $foreign = $this->arrivedTreatment(accountId: 2);
        $this->actingAsAccountOne();

        $this->expectException(ModelNotFoundException::class);
        $this->service->store($foreign->id, 8, 'cross-tenant attempt');
    }

    public function test_store_records_feedback_for_own_account(): void
    {
        $mine = $this->arrivedTreatment(accountId: 1);
        $this->actingAsAccountOne();

        $feedback = $this->service->store($mine->id, 8, 'great service');

        $this->assertSame($mine->id, $feedback->appointment_id);
        $this->assertSame(8, (int) $feedback->rating);
        $this->assertDatabaseHas('feedback', [
            'appointment_id' => $mine->id,
            'rating' => 8,
        ]);
    }

    /**
     * An arrived (status 2) treatment (type 2) appointment with the patient +
     * service rows store() will findOrFail. Created with the given account_id;
     * foreign rows MUST be built logged-out so account_id is honoured.
     */
    private function arrivedTreatment(int $accountId): Appointments
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

    private function actingAsAccountOne(): void
    {
        $this->actingAs(User::factory()->create(['account_id' => 1]));
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
