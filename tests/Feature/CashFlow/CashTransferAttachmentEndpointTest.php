<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Models\CashFlow\CashTransfer;
use App\Models\CashFlow\CashTransferAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * CashTransferAttachmentsController + orphan-binding tests.
 *
 * Pins the 2026-05-15 drop-zone migration:
 *   1. Upload returns 201 with a content-addressed key.
 *   2. Disallowed mime rejected.
 *   3. Identical bytes share one R2 blob across two attachment rows.
 *   4. Movement create binds orphan attachments to the new transfer.
 *   5. Movement create rejects attachment_ids from another tenant.
 */
class CashTransferAttachmentEndpointTest extends TestCase
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
            'cashflow_transfer_create', 'cashflow_transfer_view',
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

    private function fakePdf(string $name = 'slip.pdf', ?string $bytes = null): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $bytes ?? self::PDF_MAGIC);
    }

    public function test_upload_happy_path_returns_201_with_content_addressed_key(): void
    {
        $response = $this->postJson('/api/cashflow/movements/attachments', [
            'file' => $this->fakePdf(),
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id', 'cash_transfer_id', 'file_name', 'file_size',
                'mime_type', 'sha256', 'uploaded_at', 'signed_url',
                'signed_url_expires_at',
            ],
        ]);
        $this->assertNull($response->json('data.cash_transfer_id'), 'orphan upload — not bound to a transfer yet');
    }

    public function test_disallowed_mime_rejected(): void
    {
        $response = $this->postJson('/api/cashflow/movements/attachments', [
            'file' => UploadedFile::fake()->createWithContent('notes.txt', 'plain text'),
        ]);
        $response->assertStatus(422);
    }

    public function test_identical_bytes_share_one_r2_blob(): void
    {
        $body = self::PDF_MAGIC.'duplicate-marker';

        $a = $this->postJson('/api/cashflow/movements/attachments', [
            'file' => UploadedFile::fake()->createWithContent('a.pdf', $body),
        ]);
        $b = $this->postJson('/api/cashflow/movements/attachments', [
            'file' => UploadedFile::fake()->createWithContent('b.pdf', $body),
        ]);

        $a->assertStatus(201);
        $b->assertStatus(201);
        $this->assertSame($a->json('data.sha256'), $b->json('data.sha256'));

        $keys = Storage::disk('r2_invoices')->allFiles();
        $matching = array_filter($keys, fn ($k) => str_contains($k, $a->json('data.sha256')));
        $this->assertCount(1, $matching, 'one blob per SHA across two attachment rows');
    }

    public function test_movement_create_binds_orphan_attachments_to_new_transfer(): void
    {
        // Upload two orphan attachments first.
        $a = $this->postJson('/api/cashflow/movements/attachments', [
            'file' => UploadedFile::fake()->createWithContent('one.pdf', self::PDF_MAGIC.'one'),
        ])->assertStatus(201)->json('data.id');
        $b = $this->postJson('/api/cashflow/movements/attachments', [
            'file' => UploadedFile::fake()->createWithContent('two.pdf', self::PDF_MAGIC.'two'),
        ])->assertStatus(201)->json('data.id');

        $this->assertNull(CashTransferAttachment::find($a)->cash_transfer_id);
        $this->assertNull(CashTransferAttachment::find($b)->cash_transfer_id);

        // Submit a pool→pool movement carrying those attachment IDs.
        $pools = $this->seededCashPools();
        $response = $this->postJson('/api/cashflow/movements/store', [
            'source_type' => 'pool',
            'source_id' => $pools[0]->id,
            'dest_type' => 'pool',
            'dest_id' => $pools[1]->id,
            'amount' => 5000,
            'transfer_date' => now()->toDateString(),
            'description' => 'Float top-up',
            'attachment_ids' => [$a, $b],
        ]);

        $response->assertStatus(200);
        $transferId = (int) $response->json('data.id');
        $this->assertNotEmpty($transferId);

        // Both orphan rows are now bound to the new transfer.
        $this->assertSame($transferId, CashTransferAttachment::find($a)->cash_transfer_id);
        $this->assertSame($transferId, CashTransferAttachment::find($b)->cash_transfer_id);

        // The response echoes the bound attachments as a lightweight
        // summary (used by the MovementRow attachment icon).
        $this->assertCount(2, $response->json('data.attachments'));
    }

    public function test_movement_create_rejects_cross_tenant_attachment_ids(): void
    {
        $pools = $this->seededCashPools();

        // Forge a foreign-tenant attachment row directly (skipping the
        // upload endpoint's account_id wiring). The validator's `exists`
        // rule is scoped — this id should be rejected.
        // Events suppressed: GuardsTenantBoundary would otherwise force
        // account_id to the auth account, defeating this cross-tenant
        // fixture (the hardening is intentional; the validator under
        // test is unchanged and must still reject the foreign id).
        $foreign = \Illuminate\Database\Eloquent\Model::withoutEvents(fn () => CashTransferAttachment::create([
            'account_id' => 999_999,
            'cash_transfer_id' => null,
            'file_name' => 'foreign.pdf',
            'file_path' => 'accounts/999999/invoices/aa/bb/foreign.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'sha256' => str_repeat('a', 64),
            'uploaded_by' => auth()->id() ?? 1,
        ]));

        $response = $this->postJson('/api/cashflow/movements/store', [
            'source_type' => 'pool',
            'source_id' => $pools[0]->id,
            'dest_type' => 'pool',
            'dest_id' => $pools[1]->id,
            'amount' => 5000,
            'transfer_date' => now()->toDateString(),
            'attachment_ids' => [$foreign->id],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('attachment_ids.0');

        // No transfer created — sanity check.
        $this->assertSame(0, CashTransfer::where('description', '')->where('amount', 5000)->count());
        // Foreign attachment stayed orphan (not silently bound).
        $this->assertNull($foreign->fresh()->cash_transfer_id);
    }

    /**
     * Two cash pools belonging to the acting user's account, for the
     * source/dest of the test movement.
     */
    private function seededCashPools(): array
    {
        $accountId = auth()->user()->account_id;
        return \App\Models\CashFlow\CashPool::where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->limit(2)
            ->get()
            ->all();
    }
}
