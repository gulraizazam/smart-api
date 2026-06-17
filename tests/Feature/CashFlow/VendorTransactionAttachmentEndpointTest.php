<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Models\CashFlow\Vendor;
use App\Models\CashFlow\VendorTransaction;
use App\Models\CashFlow\VendorTransactionAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * VendorTransactionAttachmentsController + orphan-binding tests.
 *
 * Exact mirror of CashTransferAttachmentEndpointTest, pinning the
 * 2026-05-17 vendor drop-zone migration:
 *   1. Upload returns 201 with a content-addressed key (orphan).
 *   2. Disallowed mime rejected.
 *   3. Identical bytes share one R2 blob across two attachment rows.
 *   4. Recording a purchase binds orphan attachments to the new tx.
 *   5. Cross-tenant attachment ids are NOT bound (service-side scope).
 *   6. Deliver binds uploaded attachments (legacy Drive URL retired).
 */
class VendorTransactionAttachmentEndpointTest extends TestCase
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
            'cashflow.vendor.transaction.create', 'cashflow.vendor.view', 'cashflow.vendor.deliver',
        ]);
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

    private function vendor(): Vendor
    {
        return Vendor::factory()->create(['account_id' => 1]);
    }

    public function test_upload_happy_path_returns_201_with_content_addressed_key(): void
    {
        $response = $this->postJson('/api/cashflow/vendors/attachments', [
            'file' => $this->fakePdf(),
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id', 'vendor_transaction_id', 'file_name', 'file_size',
                'mime_type', 'sha256', 'uploaded_at', 'signed_url',
                'signed_url_expires_at',
            ],
        ]);
        $this->assertNull($response->json('data.vendor_transaction_id'), 'orphan upload — not bound yet');
    }

    public function test_disallowed_mime_rejected(): void
    {
        $response = $this->postJson('/api/cashflow/vendors/attachments', [
            'file' => UploadedFile::fake()->createWithContent('notes.txt', 'plain text'),
        ]);
        $response->assertStatus(422);
    }

    public function test_identical_bytes_share_one_r2_blob(): void
    {
        $body = self::PDF_MAGIC.'duplicate-marker';

        $a = $this->postJson('/api/cashflow/vendors/attachments', [
            'file' => UploadedFile::fake()->createWithContent('a.pdf', $body),
        ]);
        $b = $this->postJson('/api/cashflow/vendors/attachments', [
            'file' => UploadedFile::fake()->createWithContent('b.pdf', $body),
        ]);

        $a->assertStatus(201);
        $b->assertStatus(201);
        $this->assertSame($a->json('data.sha256'), $b->json('data.sha256'));

        $keys = Storage::disk('r2_invoices')->allFiles();
        $matching = array_filter($keys, fn ($k) => str_contains($k, $a->json('data.sha256')));
        $this->assertCount(1, $matching, 'one blob per SHA across two attachment rows');
    }

    public function test_recording_a_purchase_binds_orphan_attachments(): void
    {
        $vendor = $this->vendor();

        $a = $this->postJson('/api/cashflow/vendors/attachments', [
            'file' => UploadedFile::fake()->createWithContent('one.pdf', self::PDF_MAGIC.'one'),
        ])->assertStatus(201)->json('data.id');
        $b = $this->postJson('/api/cashflow/vendors/attachments', [
            'file' => UploadedFile::fake()->createWithContent('two.pdf', self::PDF_MAGIC.'two'),
        ])->assertStatus(201)->json('data.id');

        $this->assertNull(VendorTransactionAttachment::find($a)->vendor_transaction_id);

        $response = $this->postJson("/api/cashflow/vendors/{$vendor->id}/purchase", [
            'amount' => 1000,
            'description' => 'Medical consumables',
            'transaction_date' => now()->format('Y-m-d'),
            'is_for_general' => true,
            'status' => 'ordered',
            'attachment_ids' => [$a, $b],
        ]);

        $response->assertStatus(200);
        $txId = (int) $response->json('data.id');
        $this->assertNotEmpty($txId);

        $this->assertSame($txId, VendorTransactionAttachment::find($a)->vendor_transaction_id);
        $this->assertSame($txId, VendorTransactionAttachment::find($b)->vendor_transaction_id);
    }

    public function test_cross_tenant_attachment_ids_are_not_bound(): void
    {
        $vendor = $this->vendor();

        // Foreign-tenant orphan row forged directly (events suppressed so
        // GuardsTenantBoundary doesn't rewrite account_id). bindAttachments
        // is scoped forAccount + whereNull — it must leave this untouched.
        $foreign = \Illuminate\Database\Eloquent\Model::withoutEvents(fn () => VendorTransactionAttachment::create([
            'account_id' => 999_999,
            'vendor_transaction_id' => null,
            'file_name' => 'foreign.pdf',
            'file_path' => 'accounts/999999/invoices/aa/bb/foreign.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'sha256' => str_repeat('a', 64),
            'uploaded_by' => auth()->id() ?? 1,
        ]));

        $response = $this->postJson("/api/cashflow/vendors/{$vendor->id}/purchase", [
            'amount' => 500,
            'description' => 'Another purchase',
            'transaction_date' => now()->format('Y-m-d'),
            'is_for_general' => true,
            'status' => 'ordered',
            'attachment_ids' => [$foreign->id],
        ]);

        $response->assertStatus(200);
        $this->assertNull($foreign->fresh()->vendor_transaction_id, 'foreign-tenant attachment stays orphan');
    }

    public function test_deliver_binds_uploaded_attachment(): void
    {
        $vendor = $this->vendor();
        $tx = VendorTransaction::create([
            'account_id' => 1,
            'vendor_id' => $vendor->id,
            'type' => 'purchase',
            'status' => 'ordered',
            'amount' => 750,
            'description' => 'Ordered stock',
            'transaction_date' => now()->format('Y-m-d'),
            'is_for_general' => 1,
            'created_by' => auth()->id(),
        ]);

        $att = $this->postJson('/api/cashflow/vendors/attachments', [
            'file' => UploadedFile::fake()->createWithContent('receipt.pdf', self::PDF_MAGIC.'recv'),
        ])->assertStatus(201)->json('data.id');

        $response = $this->postJson("/api/cashflow/vendors/{$vendor->id}/transactions/{$tx->id}/deliver", [
            'attachment_ids' => [$att],
        ]);

        $response->assertStatus(200);
        $this->assertSame($tx->id, VendorTransactionAttachment::find($att)->vendor_transaction_id);
        $this->assertSame('delivered', VendorTransaction::find($tx->id)->status->value);
    }

    public function test_deliver_without_url_or_attachment_is_rejected(): void
    {
        $vendor = $this->vendor();
        $tx = VendorTransaction::create([
            'account_id' => 1,
            'vendor_id' => $vendor->id,
            'type' => 'purchase',
            'status' => 'ordered',
            'amount' => 200,
            'description' => 'Ordered stock',
            'transaction_date' => now()->format('Y-m-d'),
            'is_for_general' => 1,
            'created_by' => auth()->id(),
        ]);

        $response = $this->postJson("/api/cashflow/vendors/{$vendor->id}/transactions/{$tx->id}/deliver", []);
        $response->assertStatus(422);
    }

    /** Upload an invoice (as the admin) and bind it to a fresh purchase. */
    private function attachInvoiceToPurchase(): int
    {
        $vendor = $this->vendor();
        $attId = (int) $this->postJson('/api/cashflow/vendors/attachments', [
            'file' => $this->fakePdf(),
        ])->assertStatus(201)->json('data.id');   // uploaded_by = the setUp admin
        $this->postJson("/api/cashflow/vendors/{$vendor->id}/purchase", [
            'amount' => 1000,
            'description' => 'Medical consumables',
            'transaction_date' => now()->format('Y-m-d'),
            'is_for_general' => true,
            'status' => 'ordered',
            'attachment_ids' => [$attId],
        ])->assertStatus(200);

        return $attId;
    }

    /**
     * Build a non-admin user on the admin's account holding exactly the
     * given (dotted catalog) permission slugs, and act as them.
     */
    private function actingAsUserWith(array $slugs, string $label): User
    {
        $admin = auth()->user();
        foreach ($slugs as $slug) {
            $this->createPermission($slug);
        }
        $role = $this->createRole($label.'-'.uniqid());
        $role->givePermissionTo($slugs);

        $user = User::factory()->create(['account_id' => $admin->account_id]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $user->assignRole($role);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user);

        return $user;
    }

    public function test_destroy_soft_deletes_an_invoice_for_a_creator(): void
    {
        // Baseline detach path — the setUp admin holds the (bridged)
        // create grant. The destroy endpoint had no test before; pin it.
        $attId = $this->attachInvoiceToPurchase();

        $this->deleteJson("/api/cashflow/vendors/attachments/{$attId}")
            ->assertStatus(200)
            ->assertJson(['success' => true]);
        $this->assertSoftDeleted('vendor_transaction_attachments', ['id' => $attId]);
    }

    public function test_edit_permission_holder_can_remove_an_invoice_mid_edit(): void
    {
        // Regression (reported 2026-06-05): an invoice is removed while
        // EDITING an order, so the edit grant must permit it. A user who
        // can edit a purchase but not create one — and isn't the uploader
        // — was 403'd here, even though the SPA let them open the edit form.
        $attId = $this->attachInvoiceToPurchase();

        $this->actingAsUserWith(
            ['cashflow.vendor.view', 'cashflow.vendor.transaction.edit'],
            'vendor-editor',
        );

        $this->deleteJson("/api/cashflow/vendors/attachments/{$attId}")
            ->assertStatus(200);
        $this->assertSoftDeleted('vendor_transaction_attachments', ['id' => $attId]);
    }

    public function test_view_only_user_cannot_remove_someone_elses_invoice(): void
    {
        // The widening stops at edit — plain view permission still can't
        // detach an invoice it doesn't own.
        $attId = $this->attachInvoiceToPurchase();

        $this->actingAsUserWith(['cashflow.vendor.view'], 'vendor-viewer');

        $this->deleteJson("/api/cashflow/vendors/attachments/{$attId}")
            ->assertStatus(403);
        $this->assertDatabaseHas('vendor_transaction_attachments', [
            'id' => $attId,
            'deleted_at' => null,
        ]);
    }
}
