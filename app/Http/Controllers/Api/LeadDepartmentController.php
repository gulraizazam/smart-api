<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lead\StoreLeadDepartmentRequest;
use App\Http\Requests\Lead\UpdateLeadDepartmentRequest;
use App\Models\LeadDepartment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Admin CRUD for the medical / service departments used to segment leads.
 *
 * All actions are gated on the `leads.departments.manage` slug (seeded by
 * 2026_08_04_140200_add_leads_dashboard_permissions). The public read path
 * used by the lead-create form is {@see self::lookup} — cheap, deliberately
 * light-weight, gated on a broader `leads.create` permission so anyone who
 * can add a lead can see the dropdown options.
 */
class LeadDepartmentController extends Controller
{
    /**
     * GET /api/lead-departments
     */
    public function index(Request $request): JsonResponse
    {
        try {
            if (! Gate::allows('leads.departments.manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $request->validate([
                'search' => ['nullable', 'string', 'max:100'],
                'status' => ['nullable', 'integer', 'in:0,1'],
                'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            ]);

            $accountId = (int) Auth::user()->account_id;

            $query = LeadDepartment::query()->where('account_id', $accountId);

            if ($request->filled('status')) {
                $query->where('active', (int) $request->integer('status'));
            }
            if ($request->filled('search')) {
                $query->where('name', 'like', '%'.$request->string('search')->trim().'%');
            }

            $perPage = (int) ($request->integer('per_page') ?: 25);
            $paginated = $query->orderBy('sort_order')->orderBy('name')->paginate($perPage);

            return $this->successResponse('Lead departments retrieved.', [
                'items' => $paginated->items(),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                ],
            ]);
        } catch (ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\LeadDepartmentController@index');
        }
    }

    /**
     * GET /api/lead-departments/lookup
     *
     * Active departments as `{id, name}[]` for form dropdowns. Gated on
     * `leads.create` — anyone who can create a lead should see the picker.
     */
    public function lookup(): JsonResponse
    {
        try {
            if (! Gate::allows('leads.create') && ! Gate::allows('leads.edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }
            $accountId = (int) Auth::user()->account_id;

            $items = LeadDepartment::query()
                ->select('id', 'name')
                ->where('account_id', $accountId)
                ->where('active', 1)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            return $this->successResponse('Ok.', $items);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\LeadDepartmentController@lookup');
        }
    }

    /**
     * POST /api/lead-departments
     */
    public function store(StoreLeadDepartmentRequest $request): JsonResponse
    {
        try {
            $accountId = (int) Auth::user()->account_id;

            $dept = LeadDepartment::create([
                'name' => $request->string('name')->trim(),
                'sort_order' => (int) ($request->integer('sort_order') ?: 0),
                'active' => $request->boolean('active', true),
                'account_id' => $accountId,
                'created_by' => Auth::id(),
            ]);

            return $this->successResponse('Department created.', $dept);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\LeadDepartmentController@store');
        }
    }

    /**
     * PUT/PATCH /api/lead-departments/{id}
     */
    public function update(UpdateLeadDepartmentRequest $request, int $id): JsonResponse
    {
        try {
            $accountId = (int) Auth::user()->account_id;

            $dept = LeadDepartment::query()
                ->where('account_id', $accountId)
                ->find($id);
            if ($dept === null) {
                return $this->errorResponse('Department not found.', 404);
            }

            $dept->fill(array_filter([
                'name' => $request->has('name') ? $request->string('name')->trim() : null,
                'sort_order' => $request->has('sort_order') ? (int) $request->integer('sort_order') : null,
                'active' => $request->has('active') ? $request->boolean('active') : null,
                'updated_by' => Auth::id(),
            ], static fn ($v) => $v !== null));
            $dept->save();

            return $this->successResponse('Department updated.', $dept);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\LeadDepartmentController@update');
        }
    }

    /**
     * DELETE /api/lead-departments/{id}  (soft delete)
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            if (! Gate::allows('leads.departments.manage')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }
            $accountId = (int) Auth::user()->account_id;

            $dept = LeadDepartment::query()
                ->where('account_id', $accountId)
                ->find($id);
            if ($dept === null) {
                return $this->errorResponse('Department not found.', 404);
            }

            $dept->delete();

            return $this->successResponse('Department deleted.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\LeadDepartmentController@destroy');
        }
    }
}
