<?php

declare(strict_types=1);

namespace Tests\Feature\Memberships;

use App\Models\Membership;
use App\Models\MembershipType;
use App\Models\Patients;
use App\Models\StudentVerification;
use App\Services\Membership\StudentVerificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Student memberships are a discounted tier the clinic grants only
 * to patients who upload proof of enrollment (student ID, acceptance
 * letter, etc.). The StudentVerificationService is the gate:
 *
 *   1. Upload validation — only jpg/png/gif/webp/pdf, ≤ 10 MB.
 *      A malicious `.php` or an over-size PDF must be silently
 *      dropped (the service logs a warning and refuses the row).
 *      This is the audit's #1 concern here — the tier is
 *      cash-money-adjacent and a successful upload unlocks the
 *      discount, so an attacker who smuggles an executable into the
 *      uploads directory is a pathway to RCE.
 *
 *   2. Status transitions — pending → approved / rejected, plus a
 *      reviewed_at stamp and reviewed_by trail. A verification that
 *      silently skips the status transition would hand the discount
 *      to anyone who managed to upload a file.
 *
 *   3. isStudentMembership() — the call the controller uses to
 *      decide whether to even show the upload UI. It's a substring
 *      match on the tier name; pin it so a tier rename doesn't
 *      quietly turn the feature off.
 */
class StudentVerificationTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private StudentVerificationService $service;

    private Patients $patient;

    private MembershipType $studentTier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();

        // Force a fake local disk so the production uploads directory
        // is never touched even if a test leaks outside the assertion.
        Storage::fake('local');

        $this->service = app(StudentVerificationService::class);
        $this->patient = Patients::factory()->create();
        $this->studentTier = MembershipType::factory()->create([
            'name' => 'Student Tier',
            'active' => 1,
        ]);
    }

    public function test_is_student_membership_matches_by_name_substring(): void
    {
        $this->assertTrue($this->service->isStudentMembership($this->studentTier->id));

        $gold = MembershipType::factory()->create(['name' => 'Gold Tier']);
        $this->assertFalse($this->service->isStudentMembership($gold->id));
    }

    public function test_store_verification_persists_the_row_with_pending_status(): void
    {
        $verification = $this->service->storeVerification([
            'patient_id' => $this->patient->id,
            'membership_type_id' => $this->studentTier->id,
            'documents' => [
                UploadedFile::fake()->image('student_id.jpg', 800, 600)->size(500),
            ],
        ]);

        $this->assertNotNull($verification);
        $this->assertSame($this->patient->id, $verification->patient_id);
        $this->assertSame('pending', $verification->status);
        $this->assertIsArray($verification->document_paths);
        $this->assertCount(1, $verification->document_paths);
        $this->assertNotNull($verification->submitted_at);
    }

    public function test_store_verification_rejects_disallowed_file_types(): void
    {
        // A `.exe` (or `.php`) upload must be silently dropped: the
        // service logs a warning and, with no valid documents left,
        // returns null so the controller refuses the submission.
        $result = $this->service->storeVerification([
            'patient_id' => $this->patient->id,
            'membership_type_id' => $this->studentTier->id,
            'documents' => [
                UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload'),
            ],
        ]);

        $this->assertNull(
            $result,
            'An upload containing only disallowed file types must not produce a verification row.',
        );
        $this->assertSame(0, StudentVerification::query()->count());
    }

    public function test_store_verification_rejects_files_over_the_size_limit(): void
    {
        // The service caps individual files at 10 MB. An 11 MB PDF
        // is rejected; no row created.
        $result = $this->service->storeVerification([
            'patient_id' => $this->patient->id,
            'membership_type_id' => $this->studentTier->id,
            'documents' => [
                UploadedFile::fake()->create('huge.pdf', 11 * 1024, 'application/pdf'),
            ],
        ]);

        $this->assertNull($result);
        $this->assertSame(0, StudentVerification::query()->count());
    }

    public function test_store_verification_accepts_multiple_valid_documents(): void
    {
        $verification = $this->service->storeVerification([
            'patient_id' => $this->patient->id,
            'membership_type_id' => $this->studentTier->id,
            'documents' => [
                UploadedFile::fake()->image('front.jpg')->size(300),
                UploadedFile::fake()->create('letter.pdf', 500, 'application/pdf'),
            ],
        ]);

        $this->assertNotNull($verification);
        $this->assertCount(2, $verification->document_paths);
    }

    public function test_approve_verification_stamps_reviewer_and_flips_status(): void
    {
        $verification = StudentVerification::create([
            'patient_id' => $this->patient->id,
            'membership_type_id' => $this->studentTier->id,
            'document_paths' => ['student_verifications/fake.pdf'],
            'status' => 'pending',
            'submitted_by' => 1,
            'submitted_at' => now(),
        ]);

        $approved = $this->service->approveVerification($verification->id);

        $this->assertSame('approved', $approved->status);
        $this->assertNotNull($approved->reviewed_at);
        $this->assertNotNull($approved->reviewed_by);
    }

    public function test_reject_verification_records_the_reason(): void
    {
        $verification = StudentVerification::create([
            'patient_id' => $this->patient->id,
            'membership_type_id' => $this->studentTier->id,
            'document_paths' => ['student_verifications/fake.pdf'],
            'status' => 'pending',
            'submitted_by' => 1,
            'submitted_at' => now(),
        ]);

        $rejected = $this->service->rejectVerification(
            $verification->id,
            'Document not readable — please re-upload a clearer scan.',
        );

        $this->assertSame('rejected', $rejected->status);
        $this->assertStringContainsString('Document not readable', (string) $rejected->rejection_reason);
        $this->assertNotNull($rejected->reviewed_at);
    }

    public function test_has_documents_returns_true_only_when_a_valid_uploaded_file_is_present(): void
    {
        $valid = UploadedFile::fake()->image('ok.jpg')->size(100);
        $this->assertTrue($this->service->hasDocuments([$valid]));

        $this->assertFalse($this->service->hasDocuments([]));
        $this->assertFalse($this->service->hasDocuments(['not-an-upload']));
    }
}
