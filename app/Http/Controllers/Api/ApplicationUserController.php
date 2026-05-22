<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApplicationUserDatatableRequest;
use App\Http\Requests\Admin\ApplicationUserRequest;
use App\Http\Requests\Admin\ChangePasswordRequest;
use App\Http\Requests\Admin\ChangeUserStatusRequest;
use App\Services\UserManagement\ApplicationUserService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\JsonResponse;
use App\Helpers\ACL;
use App\Models\Patients;
use App\Services\Plan\PlanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class ApplicationUserController extends Controller
{


    public function __construct(
        private readonly ApplicationUserService $userService,
        private readonly PlanService $planService,
    ) {}

    public function index(): \Illuminate\View\View
    {
        if (!Gate::allows('users_manage')) {
            return abort(401);
        }

        return view('admin.users.index');
    }

    public function datatable(ApplicationUserDatatableRequest $request): JsonResponse
    {
        try {
            $params = array_merge($request->getFilters(), [
                'orderBy' => $request->getSortField(),
                'order' => $request->getSortDirection(),
                'offset' => $request->getOffset(),
                'limit' => $request->getPerPage(),
            ]);

            $result = $this->userService->getDatatableData($params);
            $perPage = $request->getPerPage();
            $total = $result['total'];

            return response()->json([
                'success' => true,
                'message' => 'Records retrieved successfully.',
                'data' => $result['data'],
                'permissions' => $this->userService->getUserPermissions(),
                'filter_values' => $this->userService->getFilterValues(),
                'active_filters' => $this->userService->getActiveFilters(),
                'meta' => [
                    'field' => $request->getSortField(),
                    'page' => $request->getPage(),
                    'pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
                    'perpage' => $perPage,
                    'total' => $total,
                    'sort' => $request->getSortDirection(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    public function create(): JsonResponse
    {
        try {
            if (!Gate::allows('users_create')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            return $this->successResponse('Record found', $this->userService->getCreateData());
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    public function store(ApplicationUserRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('users_create')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $this->userService->create($request->validated());

            return $this->successResponse('Record has been created successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    public function edit(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('users_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $data = $this->userService->getEditData($id);

            if (!$data) {
                return $this->errorResponse('Record not found.', 404);
            }

            return $this->successResponse('Record found', $data);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    public function update(ApplicationUserRequest $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('users_edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $this->userService->update($id, $request->validated());

            return $this->successResponse('Record has been updated successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('users_destroy')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $result = $this->userService->delete($id);

            return $result
                ? $this->successResponse('Record has been deleted successfully.')
                : $this->errorResponse('Resource not found.', 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    public function status(ChangeUserStatusRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('users_active')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $result = $this->userService->changeStatus(
                (int) $request->validated('id'),
                (int) $request->validated('status'),
            );

            return $result
                ? $this->successResponse('Status has been changed successfully.')
                : $this->errorResponse('Resource not found.', 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    public function changePassword(int $id): JsonResponse|\Illuminate\Contracts\View\View
    {
        try {
            if (!Gate::allows('users_change_password')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $user = $this->userService->findByAccountId($id);

            if (!$user) {
                return $this->errorResponse('User not found.', 404);
            }

            return view('admin.users.change_password', compact('user'));
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    public function savePassword(ChangePasswordRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('users_change_password')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            try {
                $id = decrypt($request->validated('id'));
            } catch (DecryptException) {
                return $this->errorResponse('Something went wrong, please try again.', 500);
            }

            $result = $this->userService->changePassword((int) $id, $request->validated('password'));

            return $result
                ? $this->successResponse('Password has been changed successfully.')
                : $this->errorResponse('Something went wrong, please try again.', 500);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    /**
     * Path-bound JSON password update.
     *
     * Replaces the legacy GET (returns a Blade modal whose hidden
     * `<input name="id" value="{encrypt($user->id)}">` the SPA had to
     * scrape) + PATCH (decrypt the scraped id) two-step flow. The
     * encryption was never a security boundary — `findByAccountId`
     * is what actually prevents cross-tenant edits, and pinning the
     * user id in the route path is just as authorisation-safe.
     *
     * Validation rules mirror the SPA's zod schema:
     * min 8, mixed case, at least one number, at least one symbol,
     * confirmed against `password_confirmation` (Laravel's standard
     * `confirmed` rule looks for `<field>_confirmation`).
     */
    public function updatePassword(Request $request, int $id): JsonResponse
    {
        try {
            if (!Gate::allows('users_change_password')) {
                return $this->errorResponse('You are not authorized to access this resource.', 401);
            }

            // Tenant-scoped existence check. Returns null if the target
            // user belongs to a different account, which we surface as
            // 404 — indistinguishable from "no such user" so cross-tenant
            // probing can't enumerate.
            $user = $this->userService->findByAccountId($id);
            if (!$user) {
                return $this->errorResponse('User not found.', 404);
            }

            $validator = Validator::make($request->all(), [
                'password' => [
                    'required',
                    'string',
                    Password::min(8)->mixedCase()->numbers()->symbols(),
                    'confirmed',
                ],
            ]);

            if ($validator->fails()) {
                return $this->errorResponse(
                    $validator->errors()->first(),
                    422,
                    $validator->errors()->all(),
                );
            }

            $result = $this->userService->changePassword($id, (string) $request->input('password'));

            return $result
                ? $this->successResponse('Password has been changed successfully.')
                : $this->errorResponse('Something went wrong, please try again.', 500);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    public function getpatientOptimized(Request $request): JsonResponse
    {
        try {
            $patients = $this->userService->searchPatientsOptimized(
                $request->input('search', ''),
                Auth::user()->account_id,
            );

            return $this->successResponse('Record found.', [
                'patients' => $patients,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    /**
     * @deprecated Load-bearing for legacy admin Blade JS only
     *             (admin/appointments, packages, reports, etc. via
     *             `public/assets/js/custom.js` and friends). New SPA
     *             flows use getpatientOptimized() instead.
     */
    public function getpatientid(Request $request): JsonResponse
    {
        try {
            $patients = Patients::getPatientidAjax(
                $request->input('search', ''),
                Auth::user()->account_id,
            );

            return $this->successResponse('Record found.', [
                'patients' => $patients,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    /**
     * @deprecated Load-bearing for legacy admin Blade only (orders flow,
     *             returns extra membership fields not needed by the SPA).
     */
    public function getpatientidOrder(Request $request): JsonResponse
    {
        try {
            $patients = Patients::getPatientidAjaxOrder(
                $request->input('search', ''),
                Auth::user()->account_id,
            );

            return $this->successResponse('Record found.', [
                'patients' => $patients,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    /**
     * @deprecated Load-bearing for legacy admin Blade phone-prefix lookups.
     */
    public function phoneSearch(Request $request): JsonResponse
    {
        try {
            $patients = Patients::getPatientPhoneAjax(
                $request->input('search', ''),
                Auth::user()->account_id,
            );

            return $this->successResponse('Record found.', [
                'patients' => $patients,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    public function getpatientnumber(Request $request): JsonResponse
    {
        try {
            $patientId = (int) $request->input('patient_id');
            $patient = Patients::find($patientId);

            return $this->successResponse('Record found.', [
                'patient' => $patient,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    public function getUserCities(): JsonResponse
    {
        try {
            $cities = array_values(ACL::getUserCities());

            if (empty($cities)) {
                return $this->errorResponse('City not found', 404);
            }

            // `city` is the single-city auto-select hint kept for backward
            // compatibility with existing JS (locations, appointments, leads,
            // memberships views) â€” populated only when the user has exactly
            // one city. `cities` is the full list.
            return $this->successResponse('City found', [
                'city' => count($cities) === 1 ? $cities[0] : null,
                'cities' => $cities,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'ApplicationUserController');
        }
    }

    public function getUserCenters(): JsonResponse
    {
        try {
            $result = $this->planService->getUserDefaultCenter();

            if ($result['status']) {
                return $this->successResponse('Center found', ['center' => $result['center']]);
            }

            return response()->json([
                'success' => false,
                'status'  => false,
                'message' => 'No default center',
                'data'    => ['center' => null],
                'errors'  => [],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Get User Centers Error: ' . $e->getMessage());
            return $this->errorResponse('Failed to get user centers.', 500);
        }
    }

    /**
     * Active application-user dropdown for filter UIs.
     *
     * Returns {id, name}[] for the SPA's Created/Updated/Rescheduled-by
     * filters on the consultations screen. Scope:
     *   • account-bound (no cross-tenant leakage)
     *   • active = 1
     *   • user_type_id IN [administrator_id, application_user_id]
     *     — i.e. operators and admins, the only roles that actually
     *     write to appointment audit columns. Doctors and patients are
     *     excluded.
     */
    public function applicationUsersDropdown(): JsonResponse
    {
        try {
            $users = \App\Models\User::query()
                ->where('account_id', Auth::user()->account_id)
                ->where('active', 1)
                ->whereIn('user_type_id', [
                    config('constants.administrator_id'),
                    config('constants.application_user_id'),
                ])
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($u) => ['id' => (int) $u->id, 'name' => (string) $u->name])
                ->values();

            return $this->successResponse('Application users.', $users);
        } catch (\Throwable $e) {
            Log::error('applicationUsersDropdown failed: ' . $e->getMessage());
            return $this->errorResponse('Failed to load application users.', 500);
        }
    }
}
