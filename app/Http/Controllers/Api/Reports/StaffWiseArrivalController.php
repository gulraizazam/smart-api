<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Reports;

use App\Helpers\ACL;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StaffWiseArrivalRequest;
use App\Models\Locations;
use App\Models\User;
use App\Services\Reports\StaffWiseArrivalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StaffWiseArrivalController extends Controller
{
    public function __construct(
        private readonly StaffWiseArrivalService $reportService,
    ) {}

    public function __invoke(StaffWiseArrivalRequest $request): JsonResponse
    {
        try {
            $stats = $this->reportService->generate(
                startDate: $request->startDate(),
                endDate: $request->endDate(),
                centreIds: $request->centreIds(),
                createdBy: $request->createdBy(),
            );

            return $this->successResponse('Staff wise arrival report generated', [
                'stats' => $stats,
                'meta' => [
                    'start_date' => $request->startDate(),
                    'end_date' => $request->endDate(),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'StaffWiseArrivalReport');
        }
    }

    /**
     * Filters payload — centres + the subset of users whose role this
     * report attributes appointments to: FDM, CSR, CSR Supervisor.
     * Looking up by role name keeps the list correct even if role IDs
     * shift between accounts.
     */
    public function filters(): JsonResponse
    {
        try {
            $accountId = Auth::user()->account_id;

            $locations = Locations::getActiveRecordsByCity('', ACL::getUserCentres(), $accountId)
                ->map(fn ($l) => ['id' => (int) $l->id, 'name' => (string) $l->name])
                ->values();

            $userIds = DB::table('role_has_users')
                ->join('roles', 'roles.id', '=', 'role_has_users.role_id')
                ->whereIn('roles.name', ['FDM', 'CSR', 'CSR Supervisor'])
                ->pluck('role_has_users.user_id')
                ->unique()
                ->values()
                ->all();

            $users = User::query()
                ->select('id', 'name')
                ->where('account_id', $accountId)
                ->where('active', 1)
                ->whereIn('id', $userIds)
                ->orderBy('name')
                ->get()
                ->map(fn ($u) => ['id' => (int) $u->id, 'name' => (string) $u->name])
                ->values();

            return $this->successResponse('Staff wise arrival filters loaded', [
                'locations' => $locations,
                'users' => $users,
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'StaffWiseArrivalFilters');
        }
    }
}
