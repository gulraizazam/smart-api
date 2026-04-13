<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\HR;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View
    {
        $accountId = Auth::user()->account_id;
        $departments = Department::where('account_id', $accountId)
            ->orderBy('name')
            ->get();

        return view('admin.hr.departments.index', compact('departments'));
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
            ]);

            $user = Auth::user();

            Department::create([
                'name' => $validated['name'],
                'account_id' => $user->account_id,
                'created_by' => $user->id,
            ]);

            return $this->successResponse('Department created successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'DepartmentController@store');
        }
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'active' => ['nullable', 'in:0,1'],
            ]);

            $department->update([
                'name' => $validated['name'],
                'active' => $validated['active'] ?? $department->active,
                'updated_by' => Auth::id(),
            ]);

            return $this->successResponse('Department updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'DepartmentController@update');
        }
    }

    public function destroy(Department $department): JsonResponse
    {
        try {
            if ($department->employeeDetails()->exists()) {
                return $this->errorResponse('Cannot delete department. It has employees assigned to it.');
            }

            $department->delete();

            return $this->successResponse('Department deleted successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'DepartmentController@destroy');
        }
    }
}
