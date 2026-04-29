<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reports;

use App\Helpers\ACL;
use App\Http\Controllers\Controller;
use App\Models\Locations;
use App\Models\MembershipType;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Filter dropdowns for the Membership report — centres list (ACL-scoped)
 * plus the membership-type catalogue. Permits the same gate as the data
 * endpoint so the dropdown isn't queried by users who can't load the report.
 */
class MembershipReportFiltersApiController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            if (! Gate::allows('operations_reports_manage')) {
                return $this->errorResponse('Unauthorized.', 403);
            }

            $accountId = Auth::user()->account_id;

            $locations = collect(Locations::getActiveRecordsByCity('', ACL::getUserCentres(), $accountId))
                ->map(fn ($l) => ['id' => (int) $l->id, 'name' => $l->name])
                ->values()
                ->all();

            $membershipTypes = MembershipType::orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($m) => ['id' => (int) $m->id, 'name' => $m->name])
                ->all();

            return $this->successResponse('OK', [
                'locations' => $locations,
                'membership_types' => $membershipTypes,
            ]);
        } catch (\Throwable $e) {
            Log::error('MembershipReportFiltersApi failed: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->errorResponse('An error occurred. Please try again.', 500);
        }
    }
}
