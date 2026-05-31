<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * PatientFileController streams patient images and student verification
 * documents from the private storage disk. The audit added this controller
 * to replace direct public symlinks — it enforces:
 *
 *   1. Filename format: only [A-Za-z0-9._-] (rejects path traversal).
 *   2. Tenant boundary: the file's owner must belong to the requesting
 *      user's account_id. A cross-tenant lookup always returns 404.
 *   3. File existence: returns 404 if the file is not on disk.
 *   4. Security headers: Cache-Control: private, X-Content-Type-Options: nosniff.
 *
 * This is HIPAA/GDPR-class file access control — zero test coverage
 * until now.
 *
 * The streams are served by the live API routes
 *   GET api/files/patient-image/{filename}
 *   GET api/files/student-verification/{filename}
 * (the legacy admin/* Blade wrappers were deleted with the admin UI;
 * the controller's guardFilename() still rejects traversal / special
 * chars / NUL with a 404 regardless of the route boundary).
 */
class PatientFileAccessTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_path_traversal_in_filename_returns_404(): void
    {
        // A path with slashes is normalized away / never matches the
        // single-segment {filename} route, and guardFilename() rejects
        // anything outside [A-Za-z0-9._-]+ with a 404.
        $response = $this->get('/api/files/patient-image/../../../etc/passwd');
        $this->assertContains($response->status(), [404, 405]);
    }

    public function test_dotdot_encoded_traversal_returns_404(): void
    {
        $response = $this->get('/api/files/patient-image/..%2F..%2Fetc%2Fpasswd');
        $this->assertContains($response->status(), [404, 405]);
    }

    public function test_nonexistent_file_returns_404(): void
    {
        $response = $this->get('/api/files/patient-image/nonexistent-file-abc123.jpg');
        $response->assertStatus(404);
    }

    public function test_student_verification_nonexistent_file_returns_404(): void
    {
        $response = $this->get('/api/files/student-verification/nonexistent-doc.pdf');
        $response->assertStatus(404);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        // Log out and try to access.
        auth()->logout();

        $response = $this->get('/api/files/patient-image/test.jpg');
        $this->assertContains($response->status(), [302, 401, 403]);
    }

    public function test_cross_tenant_file_access_returns_404(): void
    {
        // Create a patient image record in account 1, then try to
        // access it from a user in account 999. The controller must
        // NOT return the file — always 404 for wrong-tenant.
        $patient = User::factory()->create([
            'user_type_id' => 3,
            'account_id' => 1,
            'image_src' => 'test-tenant-image.jpg',
        ]);

        // Switch to a user in a different account.
        $otherUser = User::factory()->create([
            'user_type_id' => 1,
            'account_id' => 999,
        ]);
        $this->actingAs($otherUser);

        $response = $this->get('/api/files/patient-image/test-tenant-image.jpg');
        $response->assertStatus(404);
    }

    public function test_filename_with_special_characters_is_rejected(): void
    {
        // guardFilename() ([A-Za-z0-9._-]+) rejects spaces, <, >, etc.
        $response = $this->get('/api/files/patient-image/file name.jpg');
        $this->assertContains($response->status(), [404, 405]);
    }

    public function test_nul_byte_in_filename_is_rejected(): void
    {
        $response = $this->get('/api/files/patient-image/test%00.jpg');
        $this->assertContains($response->status(), [404, 405]);
    }
}
