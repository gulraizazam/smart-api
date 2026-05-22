<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\HR;

use App\Http\Controllers\Controller;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\HR\LeaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class LeaveBalanceDatatableController extends Controller
{
    public function datatable(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('hr_leave_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $accountId = Auth::user()->account_id;
            $fiscalYear = $request->get('fiscal_year', LeaveService::currentFiscalYear());

            $employees = User::where('account_id', $accountId)
                ->whereIn('user_type_id', [2, 5])
                ->where('active', 1)
                ->where('hr_managed', 1)
                ->orderBy('name')
                ->get(['id', 'name']);

            $leaveTypes = LeaveType::active()
                ->where('account_id', $accountId)
                ->orderBy('name')
                ->get();

            $balances = LeaveBalance::where('account_id', $accountId)
                ->where('fiscal_year', $fiscalYear)
                ->get()
                ->groupBy('user_id');

            $data = $employees->map(function ($emp) use ($leaveTypes, $balances) {
                $row = [
                    'id' => $emp->id,
                    'name' => $emp->name,
                    'balances' => [],
                ];

                $userBalances = $balances->get($emp->id, collect());

                foreach ($leaveTypes as $lt) {
                    $balance = $userBalances->firstWhere('leave_type_id', $lt->id);
                    $row['balances'][] = [
                        'type' => $lt->name,
                        'type_id' => $lt->id,
                        'allocated' => $balance ? (float) $balance->allocated : 0,
                        'used' => $balance ? (float) $balance->used : 0,
                        'remaining' => $balance ? (float) $balance->allocated - (float) $balance->used : 0,
                    ];
                }

                return $row;
            });

            return $this->successResponse('OK', [
                'employees' => $data,
                'leave_types' => $leaveTypes->map(fn ($lt) => ['id' => $lt->id, 'name' => $lt->name]),
                'fiscal_year' => $fiscalYear,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeaveBalanceDatatableController');
        }
    }
}
