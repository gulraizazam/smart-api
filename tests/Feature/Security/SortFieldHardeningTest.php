<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Http\Requests\Admin\ApplicationUserDatatableRequest;
use App\Http\Requests\Admin\DoctorDatatableRequest;
use App\Services\Lead\LeadService;
use Illuminate\Support\Facades\Validator;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * The Leads / Doctors / Users datatables feed `sort.field` straight into
 * ORDER BY. The audit (2026-06) flagged the absence of an allow-list. These
 * pins prove a crafted sort field is now rejected (falls back to a safe
 * default / fails validation) while a legitimate column still sorts.
 */
class SortFieldHardeningTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures();
        $this->actingAsAdmin();
    }

    public function test_lead_datatable_rejects_injection_sort_field(): void
    {
        $service = app(LeadService::class);

        $malicious = $service->getDatatableData([
            'sort' => ['field' => 'id);SELECT pg_sleep(5)--', 'sort' => 'asc'],
        ]);
        $this->assertSame('leads.created_at', $malicious['orderBy'],
            'A non-column sort field must fall back to the safe default.');

        $legit = $service->getDatatableData([
            'sort' => ['field' => 'name', 'sort' => 'asc'],
        ]);
        $this->assertSame('name', $legit['orderBy']);
        $this->assertSame('asc', $legit['order']);
    }

    public function test_doctor_datatable_request_rejects_injection_sort_field(): void
    {
        $rules = (new DoctorDatatableRequest())->rules();

        $this->assertTrue(
            Validator::make(['sort' => ['field' => 'name); DROP TABLE users;--']], $rules)->fails(),
            'A sort field with injection characters must fail validation.'
        );
        $this->assertFalse(
            Validator::make(['sort' => ['field' => 'users.created_at']], $rules)->fails(),
            'A legitimate prefix.column sort field must pass validation.'
        );
    }

    public function test_application_user_datatable_request_rejects_injection_sort_field(): void
    {
        $rules = (new ApplicationUserDatatableRequest())->rules();

        $this->assertTrue(
            Validator::make(['sort' => ['field' => 'name UNION SELECT password FROM users']], $rules)->fails()
        );
        $this->assertFalse(
            Validator::make(['sort' => ['field' => 'name']], $rules)->fails()
        );
    }
}
