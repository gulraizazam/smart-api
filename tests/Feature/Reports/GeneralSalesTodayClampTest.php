<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Http\Requests\Reports\GeneralSalesReportRequest;
use App\Models\User;
use Tests\Concerns\RefreshTestDatabase as RefreshDatabase;
use Tests\Concerns\UsesFinancialFixtures;
use Tests\TestCase;

/**
 * Pins the FDM "today only" clamp on the General Sales Report.
 *
 * The FDM role is limited to same-day figures. GeneralSalesReportRequest
 * forces start/end date to today for that role — server side, so a crafted
 * API request can't widen the window — while the SPA locks the date picker.
 * Every other role keeps the full requested range.
 */
class GeneralSalesTodayClampTest extends TestCase
{
    use RefreshDatabase;
    use UsesFinancialFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedFinancialFixtures(); // seeds user_types et al. for User::factory()
    }

    private function requestAs(User $user): GeneralSalesReportRequest
    {
        $req = GeneralSalesReportRequest::create('/api/reports/general-sales', 'POST', [
            'report_type' => 'general_revenue_report_summary',
            'date_range' => '2020-01-01 - 2020-01-31',
        ]);
        $req->setUserResolver(fn () => $user);

        return $req;
    }

    public function test_fdm_role_is_clamped_to_today(): void
    {
        $fdm = User::factory()->create();
        $fdm->assignRole($this->createRole('FDM'));

        $req = $this->requestAs($fdm);
        $today = now()->toDateString();

        $this->assertTrue($req->isRestrictedToToday());
        $this->assertSame($today, $req->startDate());
        $this->assertSame($today, $req->endDate());
    }

    public function test_non_fdm_role_keeps_the_requested_range(): void
    {
        $csr = User::factory()->create();
        $csr->assignRole($this->createRole('CSR'));

        $req = $this->requestAs($csr);

        $this->assertFalse($req->isRestrictedToToday());
        $this->assertSame('2020-01-01', $req->startDate());
        $this->assertSame('2020-01-31', $req->endDate());
    }
}
