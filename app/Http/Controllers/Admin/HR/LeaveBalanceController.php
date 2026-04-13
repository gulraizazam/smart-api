<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\HR;

use App\Http\Controllers\Controller;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\HR\LeaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LeaveBalanceController extends Controller
{
    public function __construct(
        protected readonly LeaveService $leaveService,
    ) {}

    public function index(): View
    {
        $accountId = Auth::user()->account_id;
        $fiscalYear = LeaveService::currentFiscalYear();

        $leaveTypes = LeaveType::active()
            ->where('account_id', $accountId)
            ->orderBy('name')
            ->get();

        $employees = User::where('account_id', $accountId)
            ->whereIn('user_type_id', [2, 5])
            ->where('active', 1)
            ->where('hr_managed', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.hr.leave.balances.index', compact('leaveTypes', 'employees', 'fiscalYear'));
    }

    public function allocate(Request $request): JsonResponse
    {
        try {
            $accountId = Auth::user()->account_id;

            $validated = $request->validate([
                'user_id' => ['required', "exists:users,id,account_id,{$accountId}"],
                'leave_type_id' => ['required', "exists:leave_types,id,account_id,{$accountId}"],
                'allocated' => ['required', 'numeric', 'min:0', 'max:365'],
                'fiscal_year' => ['required', 'string', 'max:7'],
            ]);

            $this->leaveService->allocateBalance(
                (int) $validated['user_id'],
                (int) $validated['leave_type_id'],
                (float) $validated['allocated'],
                $validated['fiscal_year'],
                Auth::user()->account_id
            );

            return $this->successResponse('Leave balance allocated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeaveBalanceController@allocate');
        }
    }

    public function bulkAllocateForm(): View
    {
        $accountId = Auth::user()->account_id;
        $fiscalYear = LeaveService::currentFiscalYear();

        $leaveTypes = LeaveType::active()
            ->where('account_id', $accountId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $employees = User::where('account_id', $accountId)
            ->whereIn('user_type_id', [2, 5])
            ->where('active', 1)
            ->where('hr_managed', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.hr.leave.balances.bulk-allocate', compact('leaveTypes', 'employees', 'fiscalYear'));
    }

    public function bulkAllocate(Request $request): JsonResponse
    {
        try {
            $accountId = Auth::user()->account_id;

            $validated = $request->validate([
                'user_ids' => ['required', 'array', 'min:1'],
                'user_ids.*' => ["exists:users,id,account_id,{$accountId}"],
                'leave_type_id' => ['required', "exists:leave_types,id,account_id,{$accountId}"],
                'allocated' => ['required', 'numeric', 'min:0', 'max:365'],
                'fiscal_year' => ['required', 'string', 'max:7'],
            ]);

            $count = $this->leaveService->bulkAllocate(
                $validated['user_ids'],
                (int) $validated['leave_type_id'],
                (float) $validated['allocated'],
                $validated['fiscal_year'],
                Auth::user()->account_id
            );

            return $this->successResponse("Leave balance allocated to {$count} employees.");
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeaveBalanceController@bulkAllocate');
        }
    }

    public function export(Request $request)
    {
        $accountId = Auth::user()->account_id;
        $fiscalYear = $request->get('fiscal_year', LeaveService::currentFiscalYear());

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
            $headers[] = $lt->name . ' (Allocated)';
            $headers[] = $lt->name . ' (Used)';
            $headers[] = $lt->name . ' (Remaining)';
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
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
