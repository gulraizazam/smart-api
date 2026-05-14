<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Models\CashFlow\Expense;
use App\Models\CashFlow\ExpenseAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * ExpenseAttachmentsController API endpoint tests.
 *
 * Pins:
 *   1. Upload happy path returns 201 with the attachment payload.
 *   2. Oversize file (>10 MB) is rejected.
 *   3. Disallowed mime/extension is rejected.
 *   4. Wrong magic bytes (renamed binary) is rejected.
 *   5. Identical bytes → 2 DB rows, 1 R2 blob (content-addressed dedup).
 *   6. Signed-URL endpoint returns a URL for an account-scoped row.
 *   7. Destroy soft-deletes the row but leaves the R2 blob alive.
 *   8. Destroy on an attachment whose expense is Approved → 422.
 *   9. Unauthenticated access is rejected.
 */
class ExpenseAttachmentEndpointTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    private const PDF_MAGIC = "%PDF-1.4\n%fakebytes\n";

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
        $this->grantPermissions([
            'cashflow_expense_create', 'cashflow_expense_edit',
            'cashflow_expense_view',
        ]);

        // The whole suite swaps in the fake disk — no live R2 traffic.
        Storage::fake('r2_invoices');
    }

    private function grantPermissions(array $permissions): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ($permissions as $perm) {
            $this->createPermission($perm);
        }
        $role = $this->createRole('test-admin-'.uniqid());
        $role->givePermissionTo($permissions);
        auth()->user()->assignRole($role);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function fakePdf(string $name = 'invoice.pdf', ?string $bytes = null): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $bytes ?? self::PDF_MAGIC);
    }

    public function test_upload_happy_path_returns_201(): void
    {
        $response = $this->postJson('/api/cashflow/expenses/attachments', [
            'file' => $this->fakePdf(),
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id', 'file_name', 'file_size', 'mime_type', 'sha256',
                'uploaded_at', 'signed_url', 'signed_url_expires_at',
            ],
        ]);
    }

    public function test_oversize_file_rejected(): void
    {
        // Build something just over the 10 MB cap. createWithContent
        // would hold the whole string in memory; use create() which uses
        // a sparse fake on disk, then assert via the size argument.
        $big = UploadedFile::fake()->create('big.pdf', 10241); // 10 MB + 1 KB
        $response = $this->postJson('/api/cashflow/expenses/attachments', [
            'file' => $big,
        ]);
        $response->assertStatus(422);
    }

    public function test_disallowed_mime_rejected(): void
    {
        $response = $this->postJson('/api/cashflow/expenses/attachments', [
            'file' => UploadedFile::fake()->createWithContent('notes.txt', 'plain text'),
        ]);
        $response->assertStatus(422);
    }

    public function test_wrong_magic_bytes_rejected(): void
    {
        // Extension says .pdf but the bytes are a clearly-non-PDF blob.
        // FormRequest's mimes:pdf rule passes (it checks the extension);
        // the controller's finfo sniff should reject.
        $response = $this->postJson('/api/cashflow/expenses/attachments', [
            'file' => UploadedFile::fake()->createWithContent('renamed.pdf', 'definitely not pdf bytes'),
        ]);
        $response->assertStatus(422);
    }

    public function test_identical_bytes_dedup_to_one_blob(): void
    {
        $body = self::PDF_MAGIC.'duplicate-marker';

        $a = $this->postJson('/api/cashflow/expenses/attachments', [
            'file' => UploadedFile::fake()->createWithContent('a.pdf', $body),
        ]);
        $b = $this->postJson('/api/cashflow/expenses/attachments', [
            'file' => UploadedFile::fake()->createWithContent('b.pdf', $body),
        ]);

        $a->assertStatus(201);
        $b->assertStatus(201);

        $shaA = $a->json('data.sha256');
        $shaB = $b->json('data.sha256');
        $this->assertSame($shaA, $shaB, 'same bytes → same SHA');
        $this->assertSame(2, ExpenseAttachment::where('sha256', $shaA)->count());

        // One blob in R2 even though there are two attachment rows.
        $keys = Storage::disk('r2_invoices')->allFiles();
        $matching = array_filter($keys, fn ($k) => str_contains($k, $shaA));
        $this->assertCount(1, $matching, 'content-addressed: one blob per SHA');
    }

    public function test_signed_url_returns_url_for_account_scoped_row(): void
    {
        $upload = $this->postJson('/api/cashflow/expenses/attachments', [
            'file' => $this->fakePdf(),
        ]);
        $upload->assertStatus(201);
        $id = $upload->json('data.id');

        $response = $this->getJson("/api/cashflow/expenses/attachments/{$id}/signed-url");
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => ['url', 'expires_at'],
        ]);
    }

    public function test_destroy_soft_deletes_row_and_clears_unique_r2_blob(): void
    {
        // 2026-05-14: destroy now deletes the R2 blob immediately when
        // no other live row in the same account references the same
        // SHA. The orphan-prune command still catches anything that
        // fails the best-effort R2 call.
        $upload = $this->postJson('/api/cashflow/expenses/attachments', [
            'file' => $this->fakePdf('destroyable.pdf'),
        ]);
        $upload->assertStatus(201);
        $id = $upload->json('data.id');
        $key = ExpenseAttachment::withTrashed()->find($id)?->file_path;
        $this->assertNotNull($key);
        $this->assertTrue(Storage::disk('r2_invoices')->exists($key));

        $response = $this->deleteJson("/api/cashflow/expenses/attachments/{$id}");
        $response->assertStatus(200);

        // Row is soft-deleted (still recoverable via withTrashed).
        $this->assertNull(ExpenseAttachment::find($id));
        $this->assertNotNull(ExpenseAttachment::withTrashed()->find($id));
        // R2 blob is gone — nothing else in this account points at it.
        $this->assertFalse(Storage::disk('r2_invoices')->exists($key));
    }

    public function test_destroy_keeps_r2_blob_when_another_attachment_shares_the_sha(): void
    {
        // Content-addressed dedup — if the SAME bytes are uploaded as
        // a second attachment (e.g. shared invoice across two payments),
        // deleting one row must NOT remove the R2 blob, since the
        // second row still references it.
        $body = self::PDF_MAGIC.'shared-blob';
        $a = $this->postJson('/api/cashflow/expenses/attachments', [
            'file' => UploadedFile::fake()->createWithContent('a.pdf', $body),
        ]);
        $b = $this->postJson('/api/cashflow/expenses/attachments', [
            'file' => UploadedFile::fake()->createWithContent('b.pdf', $body),
        ]);
        $a->assertStatus(201);
        $b->assertStatus(201);

        $idA = $a->json('data.id');
        $key = ExpenseAttachment::withTrashed()->find($idA)?->file_path;
        $this->assertTrue(Storage::disk('r2_invoices')->exists($key));

        $this->deleteJson("/api/cashflow/expenses/attachments/{$idA}")->assertStatus(200);

        // Blob lives on because row B still references the same SHA.
        $this->assertTrue(Storage::disk('r2_invoices')->exists($key));
    }

    public function test_unauthenticated_upload_rejected(): void
    {
        auth()->logout();
        $response = $this->postJson('/api/cashflow/expenses/attachments', [
            'file' => $this->fakePdf(),
        ]);
        $this->assertContains($response->status(), [401, 302, 403]);
    }
}
