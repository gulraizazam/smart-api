<?php

declare(strict_types=1);

namespace Tests\Feature\Patients;

use App\Models\Documents;
use App\Models\Patients;
use App\Services\PatientManagement\PatientService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The patient-document path is one of the easiest places for a clinic
 * CRM to leak: a misconfigured upload endpoint can write `.php` or
 * `.exe` into webroot and convert the file server into an RCE vector.
 *
 * The audit hardened PatientService::uploadDocument by:
 *
 *   - moving the storage location OUT of public/storage (the symlinked
 *     dir served by the webserver) and INTO storage/app/patient_image,
 *     which is only accessible via the authenticated streaming route
 *     admin.files.patient_image
 *   - enforcing an extension WHITELIST in storeUploadedFile() —
 *     anything not in ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf',
 *     'doc', 'docx', 'xls', 'xlsx'] throws InvalidArgumentException
 *   - tying every Documents row to its owning patient via user_id so
 *     the audit (and the IDOR-sweep query) can prove a document
 *     belongs to a patient in the caller's account
 *
 * These tests pin all three properties.
 */
class PatientDocumentTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private PatientService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->service = app(PatientService::class);

        // Storage::fake() forces all UploadedFile->move/store calls into
        // an in-memory disk. Without this, the test would write real
        // bytes to storage/app/patient_image — fine in dev but slow
        // and dirty in CI.
        Storage::fake('local');
    }

    public function test_upload_document_persists_a_documents_row_keyed_to_the_patient(): void
    {
        $patient = Patients::factory()->create();
        $file = UploadedFile::fake()->create('result.pdf', 50, 'application/pdf');

        $result = $this->service->uploadDocument($patient->id, $file, 'lab-result');

        $this->assertTrue($result['status']);
        $this->assertNotNull($result['document'] ?? null);

        $document = Documents::query()
            ->where('user_id', $patient->id)
            ->where('document_type', 'lab-result')
            ->first();

        $this->assertNotNull($document, 'A documents row must be persisted after a successful upload.');
        $this->assertSame('result.pdf', $document->name, 'The original filename must be preserved.');
        $this->assertStringStartsWith(
            'patient_image/',
            $document->url,
            'documents.url MUST be prefixed with patient_image/ so the streaming route can resolve it.'
        );
    }

    public function test_upload_document_rejects_an_executable_extension(): void
    {
        // Critical security pin: an .exe upload (or any extension not on
        // the whitelist) MUST be rejected. Without this guard a
        // malicious staff account can upload arbitrary native code into
        // the storage tree.
        $patient = Patients::factory()->create();
        $file = UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream');

        try {
            $this->service->uploadDocument($patient->id, $file, 'attachment');
            $this->fail('uploadDocument must reject .exe — the whitelist was bypassed.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsStringIgnoringCase('not allowed', $e->getMessage());
        }

        $this->assertSame(
            0,
            Documents::query()->where('user_id', $patient->id)->count(),
            'A rejected upload must NOT leave a Documents row behind.'
        );
    }

    public function test_upload_document_rejects_a_php_extension(): void
    {
        // The audit explicitly called out .php as the highest-risk
        // bypass: writing a .php file under storage/app and then
        // tricking the webserver into evaluating it is the classic
        // unrestricted-upload RCE.
        $patient = Patients::factory()->create();
        $file = UploadedFile::fake()->create('shell.php', 5, 'application/x-php');

        try {
            $this->service->uploadDocument($patient->id, $file, 'attachment');
            $this->fail('uploadDocument must reject .php — RCE risk.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsStringIgnoringCase('not allowed', $e->getMessage());
        }
    }

    public function test_upload_document_rejects_a_shell_script_extension(): void
    {
        $patient = Patients::factory()->create();
        $file = UploadedFile::fake()->create('payload.sh', 5, 'text/x-shellscript');

        try {
            $this->service->uploadDocument($patient->id, $file, 'attachment');
            $this->fail('uploadDocument must reject .sh.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsStringIgnoringCase('not allowed', $e->getMessage());
        }
    }

    public function test_upload_document_accepts_each_whitelisted_extension(): void
    {
        // Pin the positive cases. The whitelist is jpg, jpeg, png, gif,
        // webp, pdf, doc, docx, xls, xlsx. If any of these starts being
        // rejected, this test surfaces it before users do.
        $patient = Patients::factory()->create();

        $allowed = [
            'photo.jpg' => 'image/jpeg',
            'photo.jpeg' => 'image/jpeg',
            'photo.png' => 'image/png',
            'photo.gif' => 'image/gif',
            'photo.webp' => 'image/webp',
            'doc.pdf' => 'application/pdf',
            'doc.doc' => 'application/msword',
            'doc.docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'sheet.xls' => 'application/vnd.ms-excel',
            'sheet.xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        foreach ($allowed as $name => $mime) {
            $file = UploadedFile::fake()->create($name, 5, $mime);
            $result = $this->service->uploadDocument($patient->id, $file, 'attachment');
            $this->assertTrue(
                $result['status'],
                "Whitelisted extension {$name} must be accepted, but uploadDocument returned status=false."
            );
        }
    }

    public function test_upload_document_returns_404_envelope_for_an_unknown_patient(): void
    {
        $file = UploadedFile::fake()->create('orphan.pdf', 5, 'application/pdf');

        $result = $this->service->uploadDocument(999999, $file, 'attachment');

        $this->assertFalse($result['status']);
        $this->assertSame(404, $result['code']);
    }

    public function test_update_document_swaps_the_url_to_the_new_file(): void
    {
        $patient = Patients::factory()->create();

        $first = UploadedFile::fake()->create('v1.pdf', 5, 'application/pdf');
        $r1 = $this->service->uploadDocument($patient->id, $first, 'lab-result');
        $document = $r1['document'];

        $oldUrl = $document->url;

        $second = UploadedFile::fake()->create('v2.pdf', 5, 'application/pdf');
        $result = $this->service->updateDocument($patient->id, $document->id, 'lab-result-updated', $second);

        $this->assertTrue($result['status']);

        $fresh = Documents::query()->find($document->id);
        $this->assertSame('lab-result-updated', $fresh->document_type);
        $this->assertNotSame(
            $oldUrl,
            $fresh->url,
            'updateDocument with a new file must rotate documents.url to the new path.'
        );
    }

    public function test_update_document_rejects_a_document_that_belongs_to_another_patient(): void
    {
        // Cross-patient document access is the IDOR sweep the audit
        // pinned for documents. Pin it here too: the (patientId,
        // documentId) tuple must match in the documents table.
        $patientA = Patients::factory()->create();
        $patientB = Patients::factory()->create();

        $file = UploadedFile::fake()->create('a.pdf', 5, 'application/pdf');
        $r1 = $this->service->uploadDocument($patientA->id, $file, 'lab-result');
        $documentOfA = $r1['document'];

        // Try to update patient A's document under patient B's id —
        // must NOT find it.
        $result = $this->service->updateDocument($patientB->id, $documentOfA->id, 'cross-tenant-overwrite', null);

        $this->assertFalse($result['status']);
        $this->assertSame(404, $result['code']);
    }

    public function test_list_documents_returns_only_the_patients_own_documents(): void
    {
        // Regression: GET /api/patients/{id}/documents was a 404 (the
        // route never existed); the SPA documents tab was dead. This
        // pins the list contract and its per-patient scoping.
        $patientA = Patients::factory()->create();
        $patientB = Patients::factory()->create();

        $this->service->uploadDocument(
            $patientA->id,
            UploadedFile::fake()->create('a1.pdf', 5, 'application/pdf'),
            'lab-result',
        );
        $this->service->uploadDocument(
            $patientA->id,
            UploadedFile::fake()->create('a2.pdf', 5, 'application/pdf'),
            'consent_form',
        );
        $this->service->uploadDocument(
            $patientB->id,
            UploadedFile::fake()->create('b1.pdf', 5, 'application/pdf'),
            'lab-result',
        );

        $result = $this->service->listDocuments($patientA->id);

        $this->assertTrue($result['status']);
        $this->assertCount(
            2,
            $result['documents'],
            'listDocuments must return exactly patient A\'s documents — not patient B\'s.',
        );
        $this->assertEqualsCanonicalizing(
            ['a1.pdf', 'a2.pdf'],
            $result['documents']->pluck('name')->all(),
        );
    }

    public function test_list_documents_returns_404_envelope_for_an_unknown_patient(): void
    {
        $result = $this->service->listDocuments(999999);

        $this->assertFalse($result['status']);
        $this->assertSame(404, $result['code']);
    }

    public function test_delete_document_removes_the_row(): void
    {
        $patient = Patients::factory()->create();
        $r = $this->service->uploadDocument(
            $patient->id,
            UploadedFile::fake()->create('gone.pdf', 5, 'application/pdf'),
            'lab-result',
        );
        $documentId = $r['document']->id;

        $result = $this->service->deleteDocument($patient->id, $documentId);

        $this->assertTrue($result['status']);
        $this->assertNull(
            Documents::query()->find($documentId),
            'deleteDocument must soft-delete the row so it no longer surfaces in the tab.',
        );
    }

    public function test_delete_document_rejects_a_document_that_belongs_to_another_patient(): void
    {
        // Same IDOR sweep the update path pins — the (patientId,
        // documentId) tuple must match before anything is deleted.
        $patientA = Patients::factory()->create();
        $patientB = Patients::factory()->create();

        $r = $this->service->uploadDocument(
            $patientA->id,
            UploadedFile::fake()->create('a.pdf', 5, 'application/pdf'),
            'lab-result',
        );
        $documentOfA = $r['document'];

        $result = $this->service->deleteDocument($patientB->id, $documentOfA->id);

        $this->assertFalse($result['status']);
        $this->assertSame(404, $result['code']);
        $this->assertNotNull(
            Documents::query()->find($documentOfA->id),
            'A cross-patient delete attempt must leave the document intact.',
        );
    }
}
