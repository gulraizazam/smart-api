<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Http\Controllers\Admin\HR\EmployeeDocumentController;
use App\Models\Appointments;
use App\Models\Leads;
use App\Models\Membership;
use App\Models\User;
use App\Services\Membership\MembershipService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * IDOR-hardening pins from the 2026-06 security audit. Each test proves a
 * record that used to be reachable across tenants by guessing its id is
 * now scoped to the caller's account (or, for HR docs, gated on the same
 * owner/manage check the sibling preview/download endpoints enforce).
 *
 * Pattern mirrors CrossTenantDataLeakTest: build the victim row in
 * account 1, then act as a user in another account and prove the API /
 * service refuses (404 / ModelNotFoundException) and leaves data intact.
 *
 * Coexistence note (crm2): every fix only NARROWS access — it adds an
 * account_id (or relationship) filter, never changes a response shape or
 * a shared column, so nothing crm2 reads from the shared DB is affected.
 */
class IdorHardeningTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
    }

    /**
     * A fully-permissioned (non-super-admin) user in the given account.
     * All permissions are granted so the ONLY thing that can block a
     * request is the tenant scope under test — not a missing permission.
     */
    private function createUserInAccount(int $accountId): User
    {
        // users.account_id FKs accounts(id) — seed the tenant row first so
        // both the factory insert and the pin-update below are valid.
        DB::table('accounts')->updateOrInsert(
            ['id' => $accountId],
            [
                'name' => "Account {$accountId}",
                'email' => "acct{$accountId}@example.test",
                'contact' => '0000000000',
                'suspended' => '0',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $user = User::factory()->create([
            'account_id' => $accountId,
            'user_type_id' => 1,
        ]);

        // GuardsTenantBoundary forces account_id to the *authenticated*
        // user's account on insert. If a victim row was just created as an
        // account-1 admin, this user would silently land in account 1 too,
        // defeating the cross-tenant test. Pin it via a raw update so the
        // attacker really lives in $accountId.
        DB::table('users')->where('id', $user->id)->update(['account_id' => $accountId]);
        $user->refresh();

        $role = $this->createRole('idor_admin_' . $accountId . '_' . uniqid());
        $role->givePermissionTo(\Spatie\Permission\Models\Permission::all());
        $user->assignRole($role);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    private function seedAccount(int $id): void
    {
        DB::table('accounts')->updateOrInsert(
            ['id' => $id],
            ['name' => "Account {$id}", 'email' => "acct{$id}@example.test", 'contact' => '0', 'suspended' => '0', 'created_at' => now(), 'updated_at' => now()],
        );
    }

    /** Move a just-created (account-1) row into another account for cross-tenant setup. */
    private function moveToAccount(string $table, int $id, int $accountId): void
    {
        $this->seedAccount($accountId);
        DB::table($table)->where('id', $id)->update(['account_id' => $accountId]);
    }

    // ── Membership (service layer — the IDOR lived in MembershipService) ──

    public function test_membership_edit_is_blocked_cross_tenant(): void
    {
        $this->actingAsAdmin();                  // account 1 owns the row
        $membership = Membership::factory()->create();

        $this->actingAs($this->createUserInAccount(999));

        $this->expectException(ModelNotFoundException::class);
        app(MembershipService::class)->getEditFormData($membership->id);
    }

    public function test_membership_update_is_blocked_cross_tenant(): void
    {
        $this->actingAsAdmin();
        $membership = Membership::factory()->create();
        $originalCode = $membership->code;

        $this->actingAs($this->createUserInAccount(999));

        try {
            app(MembershipService::class)->updateMembership($membership->id, ['code' => 'HIJACKED']);
            $this->fail('A cross-tenant update must throw.');
        } catch (\Throwable) {
            // updateMembership wraps the not-found in a MembershipException —
            // either way it must not mutate the row.
        }

        $this->assertSame(
            $originalCode,
            Membership::find($membership->id)->code,
            'A cross-tenant update attempt must leave the membership unchanged.'
        );
    }

    public function test_membership_delete_is_blocked_cross_tenant(): void
    {
        $this->actingAsAdmin();
        $membership = Membership::factory()->create();

        $this->actingAs($this->createUserInAccount(999));

        try {
            app(MembershipService::class)->deleteMembership($membership->id);
            $this->fail('A cross-tenant delete must throw.');
        } catch (ModelNotFoundException) {
            // expected
        }

        $this->assertNotNull(
            Membership::find($membership->id),
            'A cross-tenant delete attempt must leave the membership intact.'
        );
    }

    public function test_membership_toggle_is_blocked_cross_tenant(): void
    {
        $this->actingAsAdmin();
        $membership = Membership::factory()->create(['active' => 1]);

        $this->actingAs($this->createUserInAccount(999));

        $this->expectException(ModelNotFoundException::class);
        app(MembershipService::class)->toggleStatus($membership->id, 0);
    }

    public function test_student_verification_details_blocked_cross_tenant(): void
    {
        // This endpoint was completely ungated AND unscoped — the worst of
        // the cluster (leaks patient name/email/phone + student docs).
        $this->actingAsAdmin();
        $membership = Membership::factory()->create();

        $this->actingAs($this->createUserInAccount(999));

        $this->expectException(ModelNotFoundException::class);
        app(MembershipService::class)->getStudentVerificationDetails($membership->id);
    }

    public function test_membership_owner_can_still_edit_and_delete(): void
    {
        // Positive control: the fix must NOT over-restrict the owning
        // account (created_by + patient are both account 1 here).
        $this->actingAsAdmin();
        $membership = Membership::factory()->create();
        $service = app(MembershipService::class);

        $data = $service->getEditFormData($membership->id);
        $this->assertSame($membership->id, $data['membership']->id);

        $service->updateMembership($membership->id, ['code' => $membership->code]);

        $this->assertTrue($service->deleteMembership($membership->id));
        $this->assertNull(Membership::find($membership->id));
    }

    // ── Leads bulk delete (HTTP — the list endpoint `delete=` shortcut) ──

    public function test_lead_bulk_delete_is_blocked_cross_tenant(): void
    {
        $this->actingAsAdmin();              // account 1 owns the lead
        $lead = Leads::factory()->create();

        // Ensure the permission exists so the all-perms attacker PASSES the
        // gate — proving the account scope (not the gate) is what blocks.
        $this->createPermission('leads_destroy');
        $this->actingAs($this->createUserInAccount(999));

        $this->postJson('/api/leads/datatable', ['query' => ['search' => ['delete' => (string) $lead->id]]]);

        $this->assertTrue(
            DB::table('leads')->where('id', $lead->id)->whereNull('deleted_at')->exists(),
            'A cross-clinic bulk-delete must leave the lead intact.'
        );
    }

    public function test_lead_bulk_delete_requires_destroy_permission(): void
    {
        $this->actingAsAdmin();
        $lead = Leads::factory()->create();  // account 1

        // Permission must exist so the gate returns a clean deny, not an error.
        $this->createPermission('leads_destroy');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        // A same-account user with NO leads_destroy permission.
        $this->actingAs(User::factory()->create(['account_id' => 1]));

        $response = $this->postJson('/api/leads/datatable', ['query' => ['search' => ['delete' => (string) $lead->id]]]);

        $response->assertStatus(403);
        $this->assertTrue(
            DB::table('leads')->where('id', $lead->id)->whereNull('deleted_at')->exists(),
            'Bulk-delete without leads_destroy must not delete anything.'
        );
    }

    public function test_lead_bulk_delete_works_for_authorized_same_clinic(): void
    {
        $this->actingAsAdmin();              // super-admin in account 1 (passes leads_destroy)
        $lead = Leads::factory()->create();

        $this->postJson('/api/leads/datatable', ['query' => ['search' => ['delete' => (string) $lead->id]]])->assertOk();

        $this->assertFalse(
            DB::table('leads')->where('id', $lead->id)->whereNull('deleted_at')->exists(),
            'An authorized same-clinic bulk-delete should soft-delete the lead.'
        );
    }

    // ── Cluster #2: cross-tenant edit / refund / delete in other spots ──

    public function test_lead_update_is_blocked_cross_tenant(): void
    {
        $this->actingAsAdmin();
        $lead = Leads::factory()->create();
        $this->moveToAccount('leads', $lead->id, 2);
        $originalName = DB::table('leads')->where('id', $lead->id)->value('name');

        try {
            app(\App\Services\Lead\LeadService::class)->updateLead($lead->id, ['name' => 'HIJACKED', 'phone' => '03001112222']);
            $this->fail('A cross-tenant lead update must throw.');
        } catch (\Throwable) {
        }

        $this->assertSame(
            $originalName,
            DB::table('leads')->where('id', $lead->id)->value('name'),
            'A cross-tenant lead update must leave the lead unchanged.'
        );
    }

    public function test_doctor_bulk_delete_is_blocked_cross_tenant(): void
    {
        $this->actingAsAdmin();
        $doctor = User::factory()->doctor()->create();
        $this->moveToAccount('users', $doctor->id, 2);

        app(\App\Services\UserManagement\DoctorService::class)->bulkDelete([$doctor->id]);

        $this->assertTrue(
            DB::table('users')->where('id', $doctor->id)->whereNull('deleted_at')->exists(),
            'A cross-tenant doctor bulk-delete must not delete the doctor.'
        );
    }

    public function test_order_refund_is_blocked_cross_tenant(): void
    {
        $this->actingAsAdmin();
        $this->seedAccount(2);
        $location = \App\Models\Locations::query()->first();
        $orderId = (int) DB::table('orders')->insertGetId([
            'account_id' => 2,
            'order_type' => 'sale',
            'created_by' => auth()->id(),
            'location_id' => $location->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(\App\Services\Order\OrderService::class)->processRefund($orderId, []);

        $this->assertFalse($result['success']);
        $this->assertSame('Order not found.', $result['message']);
    }

    public function test_patient_update_is_blocked_cross_tenant(): void
    {
        $this->actingAsAdmin();
        $patient = \App\Models\Patients::factory()->create();
        $this->moveToAccount('users', $patient->id, 2);
        $originalName = DB::table('users')->where('id', $patient->id)->value('name');

        $result = app(\App\Services\PatientManagement\PatientService::class)
            ->update($patient->id, ['name' => 'HIJACKED', 'phone' => '03001112222']);

        $this->assertFalse($result['status'] ?? true);
        $this->assertSame(
            $originalName,
            DB::table('users')->where('id', $patient->id)->value('name'),
            'A cross-tenant patient update must leave the patient unchanged.'
        );
    }

    public function test_discount_update_is_blocked_cross_tenant(): void
    {
        $this->actingAsAdmin();
        $discount = \App\Models\Discount::factory()->create();
        $this->moveToAccount('discounts', $discount->id, 2);

        $this->expectException(ModelNotFoundException::class);
        app(\App\Services\Discount\DiscountService::class)->updateSimpleDiscount(['name' => 'X'], $discount->id);
    }

    public function test_assigned_voucher_is_blocked_cross_tenant(): void
    {
        $this->actingAsAdmin();
        $patient = User::factory()->create(['user_type_id' => 3]);
        $this->moveToAccount('users', $patient->id, 2);
        $voucherDiscount = \App\Models\Discount::factory()->create();
        $voucherId = (int) DB::table('user_vouchers')->insertGetId([
            'user_id' => $patient->id,
            'voucher_id' => $voucherDiscount->id,
            'total_amount' => 100,
            'amount' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(\App\Services\UserManagement\UserVoucherService::class);

        $this->assertNull($service->find($voucherId), 'A cross-tenant voucher must not be found.');

        try {
            $service->delete($voucherId);
            $this->fail('A cross-tenant voucher delete must throw.');
        } catch (\Throwable) {
        }

        $this->assertNotNull(
            DB::table('user_vouchers')->where('id', $voucherId)->first(),
            'A cross-tenant voucher delete must leave it intact.'
        );
    }

    public function test_feedback_is_blocked_cross_tenant(): void
    {
        $this->actingAsAdmin(); // feedback created under an account-1 location
        $location = \App\Models\Locations::query()->first();
        $this->assertNotNull($location, 'A fixture location is required.');
        $feedbackId = (int) DB::table('feedback')->insertGetId([
            'location_id' => $location->id,
            'rating' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attacker in account 2 has no access to account-1 centres.
        $this->actingAs($this->createUserInAccount(2));

        $this->getJson("/api/feedbacks/{$feedbackId}/edit")->assertStatus(404);
        $this->assertNotNull(DB::table('feedback')->where('id', $feedbackId)->first());
    }

    public function test_catalog_reorder_is_scoped_to_account(): void
    {
        // Representative of the catalog-reorder fixes (payment modes, cities,
        // regions, locations, lead sources, custom forms, service categories).
        $this->actingAsAdmin(); // account 1
        $this->seedAccount(2);
        $pmId = (int) DB::table('payment_modes')->insertGetId([
            'name' => 'Other Clinic Cash',
            'account_id' => 2,
            'active' => 1,
            'sort_number' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(\App\Services\PaymentMode\PaymentModeService::class)->saveSortOrder([$pmId]);

        $this->assertSame(
            5,
            (int) DB::table('payment_modes')->where('id', $pmId)->value('sort_number'),
            'Catalog reorder must not touch another clinic\'s row.'
        );
    }

    // ── WhatsApp prefill (HTTP — leaks patient phone) ──

    public function test_consultancy_whatsapp_data_blocked_cross_tenant(): void
    {
        $this->actingAsAdmin(); // account 1
        $appointment = Appointments::factory()->create([
            'account_id' => 1,
            'appointment_type_id' => 1, // Consultancy
        ]);

        $this->actingAs($this->createUserInAccount(999));

        $response = $this->getJson("/api/consultancy/{$appointment->id}/whatsapp-data");
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_treatment_whatsapp_data_blocked_cross_tenant(): void
    {
        $this->actingAsAdmin();
        $appointment = Appointments::factory()->create([
            'account_id' => 1,
            'appointment_type_id' => 2, // Treatment
        ]);

        $this->actingAs($this->createUserInAccount(999));

        $response = $this->getJson("/api/treatment/{$appointment->id}/whatsapp-data");
        $this->assertContains($response->status(), [403, 404]);
    }

    // ── Schedule time-off + single shift (HTTP) ──
    // Acting as the account-1 admin while the victim row lives in account
    // 999: the admin must NOT be able to read/mutate another account's row.

    private function makeTimeOffInAccount(int $accountId, string $type = 'annual_leave'): int
    {
        return (int) DB::table('resource_time_offs')->insertGetId([
            'resource_id' => 1,
            'location_id' => 1,
            'account_id' => $accountId,
            'type' => $type,
            'start_date' => '2026-05-01',
            'is_full_day' => 1,
            'is_repeat' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_get_time_off_blocked_cross_tenant(): void
    {
        $timeOffId = $this->makeTimeOffInAccount(999);

        $this->actingAsAdmin(); // account 1
        $response = $this->postJson('/api/schedule/get-time-off', ['time_off_id' => $timeOffId]);

        $this->assertSame(404, $response->status());
    }

    public function test_update_time_off_blocked_cross_tenant(): void
    {
        $timeOffId = $this->makeTimeOffInAccount(999, 'annual_leave');

        $this->actingAsAdmin();
        $response = $this->postJson('/api/schedule/update-time-off', [
            'time_off_id' => $timeOffId,
            'type' => 'time_off',
            'start_date' => '2026-06-01',
        ]);

        $this->assertSame(404, $response->status());
        $this->assertSame(
            'annual_leave',
            DB::table('resource_time_offs')->where('id', $timeOffId)->value('type'),
            'A cross-tenant update must leave the time-off unchanged.'
        );
    }

    public function test_delete_time_off_blocked_cross_tenant(): void
    {
        $timeOffId = $this->makeTimeOffInAccount(999);

        $this->actingAsAdmin();
        $response = $this->postJson('/api/schedule/delete-time-off', ['time_off_id' => $timeOffId]);

        $this->assertSame(404, $response->status());
        $this->assertNotNull(
            DB::table('resource_time_offs')->where('id', $timeOffId)->whereNull('deleted_at')->first(),
            'A cross-tenant delete must leave the time-off intact.'
        );
    }

    public function test_delete_single_shift_blocked_cross_tenant(): void
    {
        $rotaId = (int) DB::table('resource_has_rota')->insertGetId([
            'start' => '2026-05-01',
            'end' => '2026-12-31',
            'account_id' => 999,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $shiftId = (int) DB::table('resource_has_rota_days')->insertGetId([
            'resource_has_rota_id' => $rotaId,
            'date' => '2026-05-01',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsAdmin(); // account 1
        $response = $this->postJson('/api/schedule/delete-single-shift', ['shift_id' => $shiftId]);

        $this->assertSame(404, $response->status());
        $this->assertNotNull(
            DB::table('resource_has_rota_days')->where('id', $shiftId)->first(),
            'A cross-tenant single-shift delete must leave the shift intact.'
        );
    }

    // ── Employee documents (HTTP — missing owner/manage check on destroy) ──

    private function makeEmployeeDocument(int $ownerId): int
    {
        return (int) DB::table('employee_documents')->insertGetId([
            'user_id' => $ownerId,
            'file_name' => 'contract.pdf',
            'file_path' => "accounts/1/hr/documents/{$ownerId}/contract.pdf",
            'uploaded_by' => $ownerId,
            'account_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_document_destroy_requires_owner_or_manage_permission(): void
    {
        $this->actingAsAdmin();
        $owner = User::factory()->create(['account_id' => 1]);
        $documentId = $this->makeEmployeeDocument($owner->id);

        // A different account-1 user, NOT the owner and WITHOUT
        // hr_documents_manage. Route binding already passes (same account);
        // the in-method authorize check must now stop them.
        $stranger = User::factory()->create(['account_id' => 1]);
        $this->actingAs($stranger);

        $response = $this->deleteJson("/api/hr/employees/documents/{$documentId}");

        $this->assertSame(403, $response->status());
        $this->assertNotNull(
            DB::table('employee_documents')->where('id', $documentId)->first(),
            'A non-owner without hr_documents_manage must not delete the document.'
        );
    }

    public function test_document_destroy_allowed_with_manage_permission(): void
    {
        $this->actingAsAdmin();
        $owner = User::factory()->create(['account_id' => 1]);
        $documentId = $this->makeEmployeeDocument($owner->id);

        $manager = User::factory()->create(['account_id' => 1]);
        $role = $this->createRole('hr_manager_' . uniqid());
        $role->givePermissionTo($this->createPermission('hr_documents_manage'));
        $manager->assignRole($role);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($manager);

        // Fake the R2 disk so the delete() call doesn't reach the network.
        \Illuminate\Support\Facades\Storage::fake('r2');

        $response = $this->deleteJson("/api/hr/employees/documents/{$documentId}");

        $this->assertContains($response->status(), [200, 201]);
        $this->assertNull(
            DB::table('employee_documents')->where('id', $documentId)->whereNull('deleted_at')->first(),
            'A manager with hr_documents_manage must be able to delete.'
        );
    }

    public function test_uploaded_filename_is_sanitized_for_content_disposition(): void
    {
        // Pure-function pin: filenames used in the Content-Disposition
        // header must have path components and CR/LF/quote/backslash
        // stripped, so a crafted upload name can't inject a response header.
        $controller = new EmployeeDocumentController();
        $method = new \ReflectionMethod($controller, 'sanitizeFileName');
        $method->setAccessible(true);

        $this->assertSame('passwd', $method->invoke($controller, '../../etc/passwd'));
        $this->assertSame('in_voice.pdf', $method->invoke($controller, 'in"voice.pdf'));
        $this->assertSame('a_b.pdf', $method->invoke($controller, "a\r\nb.pdf"));
        $this->assertSame('ab.pdf', $method->invoke($controller, "a\x00b.pdf"));
        $this->assertSame('document', $method->invoke($controller, ''));
    }
}
