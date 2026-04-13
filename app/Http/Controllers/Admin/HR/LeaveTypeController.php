<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\HR;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LeaveTypeController extends Controller
{
    public function index(): View
    {
        $leaveTypes = LeaveType::where('account_id', Auth::user()->account_id)
            ->orderBy('name')
            ->get();

        return view('admin.hr.leave.types.index', compact('leaveTypes'));
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'is_paid' => ['nullable', 'in:0,1'],
            ]);

            LeaveType::create([
                'name' => $validated['name'],
                'is_paid' => $validated['is_paid'] ?? 1,
                'account_id' => Auth::user()->account_id,
                'created_by' => Auth::id(),
            ]);

            return $this->successResponse('Leave type created successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeaveTypeController@store');
        }
    }

    public function update(Request $request, LeaveType $leaveType): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'is_paid' => ['nullable', 'in:0,1'],
                'active' => ['nullable', 'in:0,1'],
            ]);

            $leaveType->update([
                'name' => $validated['name'],
                'is_paid' => $validated['is_paid'] ?? $leaveType->is_paid,
                'active' => $validated['active'] ?? $leaveType->active,
            ]);

            return $this->successResponse('Leave type updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeaveTypeController@update');
        }
    }

    public function destroy(LeaveType $leaveType): JsonResponse
    {
        try {
            if ($leaveType->applications()->exists()) {
                return $this->errorResponse('Cannot delete leave type. It has applications linked to it.');
            }

            $leaveType->delete();

            return $this->successResponse('Leave type deleted successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'LeaveTypeController@destroy');
        }
    }
}
