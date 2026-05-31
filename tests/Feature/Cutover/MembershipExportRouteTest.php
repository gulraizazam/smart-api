<?php

declare(strict_types=1);

namespace Tests\Feature\Cutover;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * S2-2 regression guard.
 *
 * The SPA (src/routes/memberships.tsx) renders Export PDF / Excel buttons that
 * hit /api/memberships/export/{pdf,excel}. Those endpoints existed ONLY under the
 * legacy /admin group (admin/memberships/export/*), which is deleted at the Blade
 * cutover — so the SPA would 404. These /api twins (same Api\MembershipsController
 * methods) must stay registered. Fails if either route is dropped.
 */
class MembershipExportRouteTest extends TestCase
{
    public function test_membership_export_pdf_route_is_registered_on_api(): void
    {
        $route = Route::getRoutes()->getByName('admin.memberships.export_pdf');

        $this->assertNotNull($route, '/api/memberships/export/pdf must be registered.');
        $this->assertSame('api/memberships/export/pdf', $route->uri());
        $this->assertStringContainsString('MembershipsController@exportPdf', $route->getActionName());
    }

    public function test_membership_export_excel_route_is_registered_on_api(): void
    {
        $route = Route::getRoutes()->getByName('admin.memberships.export_excel');

        $this->assertNotNull($route, '/api/memberships/export/excel must be registered.');
        $this->assertSame('api/memberships/export/excel', $route->uri());
        $this->assertStringContainsString('MembershipsController@exportDocs', $route->getActionName());
    }
}
