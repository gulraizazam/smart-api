<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HR\AllocateLeaveBalanceRequest;
use App\Http\Requests\Admin\HR\BulkAllocateLeaveBalanceRequest;
use App\Http\Resources\HR\LeaveBalanceResource;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\HR\LeaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveBalanceController extends Controller
{
    public function __construct(
        protected readonly LeaveService $leaveService,
    ) {}

    /**
     * GET /api/hr/leave-balances
     *
     * Paginated list of balances with optional filters. Non-admins
     * (without hr_leave_manage) only ever see their own rows.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('hr_leave_view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $accountId = (int) Auth::user()->account_id;

            $request->validate([
                'user_id' => ['nullable', 'integer'],
                'leave_type_id' => ['nullable', 'integer'],
                'fiscal_year' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            ]);

            $query = LeaveBalance::query()
                ->with(['user:id,name', 'leaveType:id,name,is_paid'])
                ->where('account_id', $accountId);

            if (! Gate::allows('hr_leave_manage')) {
                $query->where('user_id', (int) Auth::id());
            } elseif ($request->filled('user_id')) {
                $query->where('user_id', (int) $request->integer('user_id'));
            }

            if ($request->filled('leave_type_id')) {
                $query->where('leave_type_id', (int) $request->integer('leave_type_id'));
            }

            $query->where('fiscal_year', $request->string('fiscal_year')->toString() ?: LeaveService::currentFiscalYear());

            $perPage = (int) ($request->integer('per_page') ?: 25);

            $paginated = $query->orderByDesc('id')->paginate($perPage);

            return $this->successResponse(
                'Leave balances retrieved successfully.',
                [
                    'items' => LeaveBalanceResource::collection($paginated->items()),
                    'meta' => [
                        'current_page' => $paginated->currentPage(),
                        'last_page' => $paginated->lastPage(),
                        'per_page' => $paginated->perPage(),
                        'total' => $paginated->total(),
                    ],
                ],
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\LeaveBalanceController@index');
        }
    }

    /**
     * GET /api/hr/leave-balances/matrix
     *
     * Employee × leave-type matrix for a given fiscal year. Mirrors the admin
     * datatable view but as a clean API shape for mobile / dashboards.
     */
    public function matrix(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('hr_leave_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'fiscal_year' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            ]);

            $accountId = (int) Auth::user()->account_id;
            $fiscalYear = $request->string('fiscal_year')->toString() ?: LeaveService::currentFiscalYear();

            $employees = User::where('account_id', $accountId)
                ->whereIn('user_type_id', [2, 5])
                ->where('active', 1)
                ->where('hr_managed', 1)
                ->orderBy('name')
                ->get(['id', 'name']);

            $leaveTypes = LeaveType::active()
                ->where('account_id', $accountId)
                ->orderBy('name')
                ->get(['id', 'name', 'is_paid']);

            $balances = LeaveBalance::where('account_id', $accountId)
                ->where('fiscal_year', $fiscalYear)
                ->get()
                ->groupBy('user_id');

            $rows = $employees->map(function (User $emp) use ($leaveTypes, $balances) {
                $userBalances = $balances->get($emp->id, collect());

                return [
                    'user_id' => (int) $emp->id,
                    'user_name' => (string) $emp->name,
                    'balances' => $leaveTypes->map(function (LeaveType $lt) use ($userBalances) {
                        $balance = $userBalances->firstWhere('leave_type_id', $lt->id);
                        $allocated = $balance ? (float) $balance->allocated : 0.0;
                        $used = $balance ? (float) $balance->used : 0.0;

                        return [
                            'leave_type_id' => (int) $lt->id,
                            'leave_type_name' => (string) $lt->name,
                            'allocated' => $allocated,
                            'used' => $used,
                            'remaining' => round($allocated - $used, 2),
                        ];
                    })->values(),
                ];
            })->values();

            return $this->successResponse(
                'Leave balance matrix retrieved successfully.',
                [
                    'fiscal_year' => $fiscalYear,
                    'leave_types' => $leaveTypes->map(fn (LeaveType $lt) => [
                        'id' => (int) $lt->id,
                        'name' => (string) $lt->name,
                        'is_paid' => (bool) $lt->is_paid,
                    ])->values(),
                    'rows' => $rows,
                ],
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\LeaveBalanceController@matrix');
        }
    }

    /**
     * GET /api/hr/leave-balances/employee/{user}
     *
     * All balances for a single employee in a given fiscal year. Useful for
     * the mobile profile screen.
     */
    public function forEmployee(Request $request, User $user): JsonResponse
    {
        try {
            $authUser = Auth::user();
            $isSelf = (int) $user->id === (int) $authUser->id;

            if (! $isSelf && ! Gate::allows('hr_leave_manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            if ((int) $user->account_id !== (int) $authUser->account_id) {
                return $this->errorResponse('Employee not found.', 404);
            }

            $request->validate([
                'fiscal_year' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            ]);

            $fiscalYear = $request->string('fiscal_year')->toString() ?: LeaveService::currentFiscalYear();

            $balances = LeaveBalance::with('leaveType:id,name,is_paid')
                ->where('account_id', (int) $authUser->account_id)
                ->where('user_id', (int) $user->id)
                ->where('fiscal_year', $fiscalYear)
                ->get();

            return $this->successResponse(
                'Employee leave balances retrieved successfully.',
                [
                    'user' => ['id' => (int) $user->id, 'name' => (string) $user->name],
                    'fiscal_year' => $fiscalYear,
                    'balances' => LeaveBalanceResource::collection($balances),
                ],
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\LeaveBalanceController@forEmployee');
        }
    }

    /**
     * POST /api/hr/leave-balances/allocate
     *
     * Upsert a single employee's balance for the given leave type + fiscal year.
     */
    public function allocate(AllocateLeaveBalanceRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $accountId = (int) Auth::user()->account_id;

            $balance = $this->leaveService->allocateBalance(
                (int) $validated['user_id'],
                (int) $validated['leave_type_id'],
                (float) $validated['allocated'],
                (string) $validated['fiscal_year'],
                $accountId,
            );

            $balance->load(['user:id,name', 'leaveType:id,name,is_paid']);

            return $this->successResponse(
                'Leave balance allocated successfully.',
                new LeaveBalanceResource($balance),
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\LeaveBalanceController@allocate');
        }
    }

    /**
     * POST /api/hr/leave-balances/bulk-allocate
     */
    public function bulkAllocate(BulkAllocateLeaveBalanceRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $accountId = (int) Auth::user()->account_id;

            $count = $this->leaveService->bulkAllocate(
                $validated['user_ids'],
                (int) $validated['leave_type_id'],
                (float) $validated['allocated'],
                (string) $validated['fiscal_year'],
                $accountId,
            );

            return $this->successResponse(
                "Leave balance allocated to {$count} employees.",
                [
                    'allocated_count' => $count,
                    'leave_type_id' => (int) $validated['leave_type_id'],
                    'fiscal_year' => (string) $validated['fiscal_year'],
                    'allocated' => (float) $validated['allocated'],
                ],
            );
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\LeaveBalanceController@bulkAllocate');
        }
    }

    /**
     * DELETE /api/hr/leave-balances/{leaveBalance}
     *
     * Remove an allocation row. 409 if `used > 0` — leave has already been
     * drawn against it; HR must manually zero-out instead of deleting.
     */
    public function destroy(LeaveBalance $leaveBalance): JsonResponse
    {
        try {
            if (! Gate::allows('hr_leave_manage')) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            if ((int) $leaveBalance->account_id !== (int) Auth::user()->account_id) {
                return $this->errorResponse('Leave balance not found.', 404);
            }

            if ((float) $leaveBalance->used > 0) {
                return $this->errorResponse(
                    'Cannot delete a leave balance with recorded usage. Reduce `allocated` or adjust the underlying leave applications instead.',
                    409,
                );
            }

            $leaveBalance->delete();

            return $this->successResponse('Leave balance removed successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\LeaveBalanceController@destroy');
        }
    }

    /**
     * GET /api/hr/leave-balances/fiscal-year
     *
     * Returns the current fiscal year string (e.g. "2025-26") plus its bounds
     * — handy for mobile clients building the filter chip.
     */
    public function fiscalYear(): JsonResponse
    {
        try {
            if (! Gate::allows('hr_leave_view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $fiscalYear = LeaveService::currentFiscalYear();
            [$start, $end] = LeaveService::fiscalYearDates($fiscalYear);

            return $this->successResponse('Current fiscal year resolved.', [
                'fiscal_year' => $fiscalYear,
                'start_date' => $start,
                'end_date' => $end,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\LeaveBalanceController@fiscalYear');
        }
    }

    /**
     * GET /api/hr/leave-balances/export
     *
     * CSV download of the full balance matrix for the given fiscal year.
     */
    public function export(Request $request): StreamedResponse|JsonResponse
    {
        try {
            if (! Gate::allows('hr_leave_manage')) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            $request->validate([
                'fiscal_year' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            ]);

            $accountId = (int) Auth::user()->account_id;
            $fiscalYear = $request->string('fiscal_year')->toString() ?: LeaveService::currentFiscalYear();

            $leaveTypes = LeaveType::active()
                ->where('account_id', $accountId)
                ->orderBy('name')
                ->get();

            $employees = User::where('account_id', $accountId)
                ->whereIn('user_type_id', [2, 5])
                ->where('active', 1)
                ->where('hr_managed', 1)
                ->orderBy('name')
                ->get();

            $balances = LeaveBalance::where('account_id', $accountId)
                ->where('fiscal_year', $fiscalYear)
                ->get()
                ->groupBy('user_id');

            $rows = [];
            $headers = ['Employee'];
            foreach ($leaveTypes as $lt) {
                $headers[] = $lt->name.' (Allocated)';
                $headers[] = $lt->name.' (Used)';
                $headers[] = $lt->name.' (Remaining)';
            }
            $rows[] = $headers;

            foreach ($employees as $emp) {
                $row = [$emp->name];
                $userBalances = $balances->get($emp->id, collect());

                foreach ($leaveTypes as $lt) {
                    $balance = $userBalances->firstWhere('leave_type_id', $lt->id);
                    $allocated = $balance ? (float) $balance->allocated : 0;
                    $used = $balance ? (float) $balance->used : 0;
                    $row[] = $allocated;
                    $row[] = $used;
                    $row[] = $allocated - $used;
                }

                $rows[] = $row;
            }

            $filename = "leave_balances_{$fiscalYear}.csv";
            $callback = function () use ($rows) {
                $file = fopen('php://output', 'w');
                foreach ($rows as $row) {
                    fputcsv($file, $row);
                }
                fclose($file);
            };

            return response()->stream($callback, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\LeaveBalanceController@export');
        }
    }
}
