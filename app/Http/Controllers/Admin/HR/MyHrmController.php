<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\HR;

use App\Http\Controllers\Controller;
use App\Models\EmployeeDocument;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Services\HR\HrNotificationService;
use App\Services\HR\LeaveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MyHrmController extends Controller
{
    public function profile(): View
    {
        $user = Auth::user();

        $user->load([
            'employeeDetail.department',
            'employeeDetail.designation',
            'employeeDetail.reportingManager',
            'user_has_locations.location',
        ]);

        $documents = EmployeeDocument::with('uploader')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        return view('admin.hr.my.profile', compact('user', 'documents'));
    }

    public function leaves(): View
    {
        $user = Auth::user();
        $accountId = $user->account_id;
        $fiscalYear = LeaveService::currentFiscalYear();

        $leaveTypes = LeaveType::active()
            ->where('account_id', $accountId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $balances = LeaveBalance::where('user_id', $user->id)
            ->where('fiscal_year', $fiscalYear)
            ->get()
            ->keyBy('leave_type_id');

        $applications = LeaveApplication::with('leaveType')
            ->forUser($user->id)
            ->forFiscalYear($fiscalYear)
            ->orderByDesc('created_at')
            ->get();

        $shiftHours = app(LeaveService::class)->resolveShiftHours($user->id);

        return view('admin.hr.my.leaves', compact(
            'leaveTypes',
            'balances',
            'applications',
            'fiscalYear',
            'shiftHours',
        ));
    }

    public function applyLeave(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $accountId = $user->account_id;

            $validated = $request->validate([
                'leave_type_id' => ['required', "exists:leave_types,id,account_id,{$accountId}"],
                'start_date' => ['required', 'date'],
                'end_date' => ['required', 'date', 'after_or_equal:start_date'],
                'duration_type' => ['required', 'in:full,half,short,custom'],
                'custom_hours' => ['nullable', 'required_if:duration_type,custom', 'numeric', 'gt:0'],
                'reason' => ['required', 'string', 'max:1000'],
            ]);

            $leaveService = app(LeaveService::class);

            if (in_array($validated['duration_type'], ['half', 'short', 'custom'], true)) {
                $validated['end_date'] = $validated['start_date'];
            }

            if ($validated['duration_type'] === 'custom') {
                $shiftHours = $leaveService->resolveShiftHours($user->id);
                if ($shiftHours === null) {
                    return $this->errorResponse('By-hours leave requires a shift duration on your profile. Please contact HR.');
                }
                if ((float) $validated['custom_hours'] >= $shiftHours) {
                    return $this->errorResponse('Hours must be less than your shift hours (' . $shiftHours . '). Use Full Day instead.');
                }
            }

            if ($leaveService->hasOverlap($user->id, $validated['start_date'], $validated['end_date'])) {
                return $this->errorResponse('You already have a leave application for these dates.');
            }

            $application = $leaveService->apply($validated, $user->id, $user->account_id);

            try {
                app(HrNotificationService::class)->onLeaveSubmitted($application);
            } catch (\Exception) {
                // Notification failure should not block the operation
            }

            $warning = $leaveService->getBalanceWarning(
                $user->id,
                (int) $validated['leave_type_id'],
                (float) $application->total_days
            );

            $message = 'Leave application submitted successfully.';
            if ($warning) {
                $message .= ' Warning: ' . $warning;
            }

            return $this->successResponse($message);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'MyHrmController@applyLeave');
        }
    }

    public function updateLeave(Request $request, LeaveApplication $leaveApplication): JsonResponse
    {
        try {
            if ((int) $leaveApplication->user_id !== Auth::id()) {
                return $this->errorResponse('You can only edit your own leave applications.', 403);
            }

            if ($leaveApplication->status !== \App\Enums\LeaveStatus::Pending) {
                return $this->errorResponse('Only pending applications can be edited.');
            }

            $user = Auth::user();
            $accountId = $user->account_id;

            $validated = $request->validate([
                'leave_type_id' => ['required', "exists:leave_types,id,account_id,{$accountId}"],
                'start_date' => ['required', 'date'],
                'end_date' => ['required', 'date', 'after_or_equal:start_date'],
                'duration_type' => ['required', 'in:full,half,short,custom'],
                'custom_hours' => ['nullable', 'required_if:duration_type,custom', 'numeric', 'gt:0'],
                'reason' => ['required', 'string', 'max:1000'],
            ]);

            $leaveService = app(LeaveService::class);

            if (in_array($validated['duration_type'], ['half', 'short', 'custom'], true)) {
                $validated['end_date'] = $validated['start_date'];
            }

            if ($validated['duration_type'] === 'custom') {
                $shiftHours = $leaveService->resolveShiftHours($user->id);
                if ($shiftHours === null) {
                    return $this->errorResponse('By-hours leave requires a shift duration on your profile. Please contact HR.');
                }
                if ((float) $validated['custom_hours'] >= $shiftHours) {
                    return $this->errorResponse('Hours must be less than your shift hours (' . $shiftHours . '). Use Full Day instead.');
                }
            }

            if ($leaveService->hasOverlap($user->id, $validated['start_date'], $validated['end_date'], $leaveApplication->id)) {
                return $this->errorResponse('You already have a leave application for these dates.');
            }

            $leaveService->update($leaveApplication, $validated);

            return $this->successResponse('Leave application updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'MyHrmController@updateLeave');
        }
    }

    public function cancelLeave(LeaveApplication $leaveApplication): JsonResponse
    {
        try {
            if ((int) $leaveApplication->user_id !== Auth::id()) {
                return $this->errorResponse('You can only cancel your own leave applications.', 403);
            }

            if ($leaveApplication->status->value !== 'pending') {
                return $this->errorResponse('Only pending applications can be cancelled.');
            }

            $leaveService = app(LeaveService::class);
            $leaveService->cancel($leaveApplication);

            try {
                app(HrNotificationService::class)->onLeaveCancelled($leaveApplication);
            } catch (\Exception) {
                // Notification failure should not block the operation
            }

            return $this->successResponse('Leave application cancelled.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'MyHrmController@cancelLeave');
        }
    }

}
