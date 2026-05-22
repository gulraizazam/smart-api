<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\HR;

use App\Http\Controllers\Controller;
use App\Services\HR\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class EmployeeDatatableController extends Controller
{
    public function __construct(
        protected readonly EmployeeService $service,
    ) {}

    public function datatable(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('hr_employees_view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $accountId = Auth::user()->account_id;
            $datatableData = $this->service->getDatatableData($request->all(), $accountId);

            [$orderBy, $order] = getSortBy($request);
            [$displayLength, $displayStart, $pages, $page] = getPaginationElement($request, $datatableData['total']);

            $sortColumn = match ($orderBy) {
                'name' => 'users.name',
                'email' => 'users.email',
                'designation' => 'designations.name',
                'active' => 'users.active',
                'hire_date' => 'employee_details.hire_date',
                default => 'users.name',
            };

            $records = $datatableData['query']
                ->limit($displayLength)
                ->offset($displayStart)
                ->orderBy($sortColumn, $order ?: 'asc')
                ->get();

            $data = $records->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'designation' => $user->designation_name ?? 'â€”',
                'hire_date' => $user->hire_date ? \Carbon\Carbon::parse($user->hire_date)->format('d M Y') : 'â€”',
                'active' => $user->active,
            ]);

            return response()->json([
                'data' => $data,
                'permissions' => [
                    'view' => Gate::allows('hr_employees_view'),
                    'edit' => Gate::allows('hr_employees_manage'),
                ],
                'active_filters' => $datatableData['active_filters'],
                'filter_values' => [
                    'status' => config('constants.status'),
                ],
                'meta' => [
                    'field' => $orderBy,
                    'page' => $page,
                    'pages' => $pages,
                    'perpage' => $displayLength,
                    'total' => $datatableData['total'],
                    'sort' => $order,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'EmployeeDatatableController');
        }
    }
}
