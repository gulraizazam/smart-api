<?php

declare(strict_types=1);

namespace Tests\Feature\CashFlow;

use App\Models\CashFlow\CashPool;
use App\Models\CashFlow\MovementAttachment;
use App\Models\CashFlow\StaffAdvance;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * MovementAttachmentsController + binding tests for the three staff
 * combos. Pins the 2026-05-16 expansion that gave staff advances /
 * returns / handovers the same drop-zone surface pool→pool already had.
 */
class StaffMovementAttachmentEndpointTest extends TestCase
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
            'cashflow_staff_advance_create',
            'cashflow_staff_return_create',
            'cashflow_staff_transfer_create',
            'cashflow_staff_advance_view',
        ]);
        Storage::fake('r2_invoices');
    }

    private function grantPermissions(array $permissions): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ($permissions as $perm) {
            $this->createPermission($perm);
        }
        $role = $this->createRole('test-staff-attach-'.uniqid());
        $role->givePermissionTo($permissions);
        auth()->user()->assignRole($role);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function fakePdf(string $name = 'slip.pdf', ?string $bytes = null): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $bytes ?? self::PDF_MAGIC);
    }

    public function test_upload_happy_path_returns_201_with_orphan_kind_and_id(): void
    {
        $response = $this->postJson('/api/cashflow/movements/staff-attachments', [
            'file' => $this->fakePdf(),
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id', 'movement_kind', 'movement_id', 'file_name', 'file_size',
                'mime_type', 'sha256', 'uploaded_at', 'signed_url',
                'signed_url_expires_at',
            ],
        ]);
        $this->assertNull($response->json('data.movement_kind'));
        $this->assertNull($response->json('data.movement_id'));
    }

    public function test_pool_to_staff_advance_binds_orphan_attachments_with_kind_staff_advance(): void
    {
        $a = $this->postJson('/api/cashflow/movements/staff-attachments', [
            'file' => $this->fakePdf('a.pdf', self::PDF_MAGIC.'a'),
        ])->assertStatus(201)->json('data.id');
        $b = $this->postJson('/api/cashflow/movements/staff-attachments', [
            'file' => $this->fakePdf('b.pdf', self::PDF_MAGIC.'b'),
        ])->assertStatus(201)->json('data.id');

        $accountId = auth()->user()->account_id;
        $pool = CashPool::factory()->create(['account_id' => $accountId, 'cached_balance' => 50000]);
        $staff = User::factory()->create([
            'account_id' => $accountId,
            'is_advance_eligible' => 1,
        ]);

        $response = $this->postJson('/api/cashflow/movements/store', [
            'source_type' => 'pool',
            'source_id' => $pool->id,
            'dest_type' => 'staff',
            'dest_id' => $staff->id,
            'amount' => 1500,
            'transfer_date' => now()->toDateString(),
            'description' => 'Travel advance',
            'attachment_ids' => [$a, $b],
        ]);

        $response->assertStatus(200);
        $advanceId = (int) $response->json('data.id');
        $this->assertNotEmpty($advanceId);
        $this->assertSame('staff_advance', $response->json('data.kind'));

        // Both orphans bound to the advance, kind set.
        $rowA = MovementAttachment::find($a);
        $rowB = MovementAttachment::find($b);
        $this->assertSame('staff_advance', $rowA->movement_kind);
        $this->assertSame('staff_advance', $rowB->movement_kind);
        $this->assertSame($advanceId, $rowA->movement_id);
        $this->assertSame($advanceId, $rowB->movement_id);

        // DTO echoes the attachments summary (drives the row paperclip).
        $this->assertCount(2, $response->json('data.attachments'));
    }

    public function test_staff_to_pool_return_binds_orphan_attachments_with_kind_staff_return(): void
    {
        $accountId = auth()->user()->account_id;
        $pool = CashPool::factory()->create(['account_id' => $accountId, 'cached_balance' => 50000]);
        $staff = User::factory()->create([
            'account_id' => $accountId,
            'is_advance_eligible' => 1,
        ]);
        // Seed outstanding so the return is permitted.
        StaffAdvance::factory()->create([
            'account_id' => $accountId,
            'user_id' => $staff->id,
            'pool_id' => $pool->id,
            'amount' => 5000,
        ]);

        $id = $this->postJson('/api/cashflow/movements/staff-attachments', [
            'file' => $this->fakePdf('r.pdf', self::PDF_MAGIC.'r'),
        ])->assertStatus(201)->json('data.id');

        $response = $this->postJson('/api/cashflow/movements/store', [
            'source_type' => 'staff',
            'source_id' => $staff->id,
            'dest_type' => 'pool',
            'dest_id' => $pool->id,
            'amount' => 1500,
            'transfer_date' => now()->toDateString(),
            'attachment_ids' => [$id],
        ]);

        $response->assertStatus(200);
        $row = MovementAttachment::find($id);
        $this->assertSame('staff_return', $row->movement_kind);
        $this->assertSame((int) $response->json('data.id'), $row->movement_id);
    }

    public function test_staff_to_staff_handover_binds_orphan_attachments_with_kind_staff_transfer(): void
    {
        $accountId = auth()->user()->account_id;
        $pool = CashPool::factory()->create(['account_id' => $accountId, 'cached_balance' => 50000]);
        $sender = User::factory()->create([
            'account_id' => $accountId,
            'is_advance_eligible' => 1,
        ]);
        $receiver = User::factory()->create([
            'account_id' => $accountId,
            'is_advance_eligible' => 1,
        ]);
        // Sender needs outstanding to give away.
        StaffAdvance::factory()->create([
            'account_id' => $accountId,
            'user_id' => $sender->id,
            'pool_id' => $pool->id,
            'amount' => 3000,
        ]);

        $id = $this->postJson('/api/cashflow/movements/staff-attachments', [
            'file' => $this->fakePdf('h.pdf', self::PDF_MAGIC.'h'),
        ])->assertStatus(201)->json('data.id');

        $response = $this->postJson('/api/cashflow/movements/store', [
            'source_type' => 'staff',
            'source_id' => $sender->id,
            'dest_type' => 'staff',
            'dest_id' => $receiver->id,
            'amount' => 500,
            'transfer_date' => now()->toDateString(),
            'attachment_ids' => [$id],
        ]);

        $response->assertStatus(200);
        $row = MovementAttachment::find($id);
        $this->assertSame('staff_transfer', $row->movement_kind);
        $this->assertSame((int) $response->json('data.id'), $row->movement_id);
    }

    public function test_movement_create_rejects_cross_tenant_staff_attachment_ids(): void
    {
        $accountId = auth()->user()->account_id;
        $pool = CashPool::factory()->create(['account_id' => $accountId, 'cached_balance' => 50000]);
        $staff = User::factory()->create([
            'account_id' => $accountId,
            'is_advance_eligible' => 1,
        ]);

        // Forge a foreign-tenant orphan attachment row directly. Events
        // suppressed: GuardsTenantBoundary would otherwise rewrite
        // account_id to the auth account (intentional hardening); the
        // validator under test is unchanged and must still reject it.
        $foreign = \Illuminate\Database\Eloquent\Model::withoutEvents(fn () => MovementAttachment::create([
            'account_id' => 999_999,
            'movement_kind' => null,
            'movement_id' => null,
            'file_name' => 'foreign.pdf',
            'file_path' => 'accounts/999999/invoices/aa/bb/foreign.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'sha256' => str_repeat('b', 64),
            'uploaded_by' => auth()->id() ?? 1,
        ]));

        $response = $this->postJson('/api/cashflow/movements/store', [
            'source_type' => 'pool',
            'source_id' => $pool->id,
            'dest_type' => 'staff',
            'dest_id' => $staff->id,
            'amount' => 500,
            'transfer_date' => now()->toDateString(),
            'attachment_ids' => [$foreign->id],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('attachment_ids.0');
        $this->assertNull($foreign->fresh()->movement_kind);
        $this->assertNull($foreign->fresh()->movement_id);
    }
}
