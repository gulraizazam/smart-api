<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\HR;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Designation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DesignationController extends Controller
{
    public function index(): View
    {
        $accountId = Auth::user()->account_id;

        $designations = Designation::with('department')
            ->where('account_id', $accountId)
            ->orderBy('name')
            ->get();

        $departments = Department::active()
            ->where('account_id', $accountId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.hr.designations.index', compact('designations', 'departments'));
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $accountId = $user->account_id;

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'department_id' => ['nullable', "exists:departments,id,account_id,{$accountId}"],
            ]);

            Designation::create([
                'name' => $validated['name'],
                'department_id' => $validated['department_id'] ?? null,
                'account_id' => $user->account_id,
                'created_by' => $user->id,
            ]);

            return $this->successResponse('Designation created successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'DesignationController@store');
        }
    }

    public function update(Request $request, Designation $designation): JsonResponse
    {
        try {
            $accountId = Auth::user()->account_id;

            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'department_id' => ['nullable', "exists:departments,id,account_id,{$accountId}"],
                'active' => ['nullable', 'in:0,1'],
            ]);

            $designation->update([
                'name' => $validated['name'],
                'department_id' => $validated['department_id'] ?? null,
                'active' => $validated['active'] ?? $designation->active,
                'updated_by' => Auth::id(),
            ]);

            return $this->successResponse('Designation updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'DesignationController@update');
        }
    }

    public function destroy(Designation $designation): JsonResponse
    {
        try {
            if ($designation->employeeDetails()->exists()) {
                return $this->errorResponse('Cannot delete designation. It has employees assigned to it.');
            }

            $designation->delete();

            return $this->successResponse('Designation deleted successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'DesignationController@destroy');
        }
    }
}
