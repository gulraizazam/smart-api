<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HR\StoreEmployeeRequest;
use App\Http\Requests\Admin\HR\UpdateEmployeeRequest;
use App\Http\Resources\HR\EmployeeResource;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Locations;
use App\Models\User;
use App\Models\UserTypes;
use App\Services\HR\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    public function __construct(
        protected readonly EmployeeService $service,
    ) {}

    /**
     * POST /api/hr/employees
     */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        try {
            $accountId = (int) Auth::user()->account_id;
            $validated = $request->validated();

            $user = $this->service->createEmployee($validated, $accountId);

            $fresh = $this->service->getEmployeeProfile($user->id);

            return $this->successResponse(
                'Employee created successfully.',
                new EmployeeResource($fresh),
                201,
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\EmployeeController@store');
        }
    }

    /**
     * GET /api/hr/employees/form-options
     *
     * One-shot lookup payload for the employee create/edit form so every
     * picker can render names instead of raw FK ids. Designations carry
     * `department_id` so the SPA can narrow the list when a department is
     * picked. All lists are scoped to the current account; managers are
     * the same allowlist the legacy show() page uses (Application User /
     * Practitioner with `hr_managed=1`).
     */
    public function formOptions(): JsonResponse
    {
        try {
            if (! Gate::allows('hr_employees_view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $accountId = (int) Auth::user()->account_id;

            $departments = Department::active()
                ->where('account_id', $accountId)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($d) => ['id' => (int) $d->id, 'name' => $d->name])
                ->all();

            $designations = Designation::active()
                ->where('account_id', $accountId)
                ->orderBy('name')
                ->get(['id', 'name', 'department_id'])
                ->map(fn ($d) => [
                    'id' => (int) $d->id,
                    'name' => $d->name,
                    'department_id' => $d->department_id !== null ? (int) $d->department_id : null,
                ])
                ->all();

            $userTypes = UserTypes::orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name])
                ->all();

            $locations = Locations::where('account_id', $accountId)
                ->where('active', 1)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($l) => ['id' => (int) $l->id, 'name' => $l->name])
                ->all();

            $managers = User::where('account_id', $accountId)
                ->whereIn('user_type_id', [2, 5])
                ->where('active', 1)
                ->where('hr_managed', 1)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($u) => ['id' => (int) $u->id, 'name' => $u->name])
                ->all();

            return $this->successResponse('OK', [
                'departments' => $departments,
                'designations' => $designations,
                'user_types' => $userTypes,
                'locations' => $locations,
                'managers' => $managers,
            ]);
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\EmployeeController@formOptions');
        }
    }

    /**
     * GET /api/hr/employees/{user}
     */
    public function show(User $user): JsonResponse
    {
        try {
            if (!Gate::allows('hr_employees_view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            if ((int) $user->account_id !== (int) Auth::user()->account_id) {
                return $this->errorResponse('Employee not found.', 404);
            }

            $profile = $this->service->getEmployeeProfile($user->id);

            return $this->successResponse(
                'Employee retrieved successfully.',
                new EmployeeResource($profile),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\EmployeeController@show');
        }
    }

    /**
     * PATCH /api/hr/employees/{user}
     */
    public function update(UpdateEmployeeRequest $request, User $user): JsonResponse
    {
        try {
            if ((int) $user->account_id !== (int) Auth::user()->account_id) {
                return $this->errorResponse('Employee not found.', 404);
            }

            $accountId = (int) Auth::user()->account_id;
            $validated = $request->validated();

            DB::transaction(function () use ($validated, $user, $accountId): void {
                // `address` lives on employee_details (see migration
                // 2026_04_23_120000_add_address_to_employee_details_table.php) —
                // it flows through to $hrFields via the except() below.
                $profileFields = ['name', 'email', 'phone', 'cnic', 'dob', 'gender'];

                $profileUpdates = collect($validated)->only($profileFields)->toArray();
                if (!empty($validated['password'])) {
                    $profileUpdates['password'] = $validated['password'];
                }

                if (!empty($profileUpdates)) {
                    $user->update($profileUpdates);
                }

                $hrFields = collect($validated)
                    ->except([...$profileFields, 'password', 'location_ids'])
                    ->toArray();

                if (!empty($hrFields)) {
                    $this->service->updateEmployeeDetails($user, $hrFields, $accountId);
                }

                if (array_key_exists('location_ids', $validated)) {
                    $this->service->syncLocations(
                        $user,
                        $validated['location_ids'] ?? [],
                        $accountId,
                    );
                }
            });

            $fresh = $this->service->getEmployeeProfile($user->id);

            return $this->successResponse(
                'Employee updated successfully.',
                new EmployeeResource($fresh),
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\EmployeeController@update');
        }
    }

    /**
     * DELETE /api/hr/employees/{user}
     */
    public function destroy(User $user): JsonResponse
    {
        try {
            if (!Gate::allows('hr_employees_manage')) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            if ((int) $user->account_id !== (int) Auth::user()->account_id) {
                return $this->errorResponse('Employee not found.', 404);
            }

            if ((int) $user->id === (int) Auth::id()) {
                return $this->errorResponse('You cannot delete your own account.', 422);
            }

            $this->service->deleteEmployee($user);

            return $this->successResponse('Employee deleted successfully.');
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\EmployeeController@destroy');
        }
    }

    /**
     * POST /api/hr/employees/{user}/avatar  (multipart, field `avatar`)
     *
     * Stores the file under `storage/app/patient_image/<filename>` so it
     * piggy-backs on the existing PatientFileController::patientImage
     * stream — that route also serves employee avatars and is already
     * gated by account_id. Filename is slash-free (the file route's
     * default {filename} segment doesn't allow slashes) and salted with
     * the user id + a random token to avoid collisions.
     */
    public function avatarStore(Request $request, User $user): JsonResponse
    {
        try {
            if (! Gate::allows('hr_employees_manage')) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            if ((int) $user->account_id !== (int) Auth::user()->account_id) {
                return $this->errorResponse('Employee not found.', 404);
            }

            $request->validate([
                'avatar' => ['required', 'image', 'mimes:jpeg,jpg,png,gif,webp', 'max:4096'],
            ], [
                'avatar.max' => 'Image must be 4 MB or smaller.',
                'avatar.mimes' => 'Image must be JPG, PNG, GIF, or WebP.',
            ]);

            $file = $request->file('avatar');
            $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
            // Slash-free filename — the streaming route's `{filename}`
            // segment doesn't allow `/` by default, so a nested directory
            // would break the public URL.
            $filename = "employee-{$user->id}-".Str::random(16).'.'.$ext;

            $disk = Storage::disk('local');
            $disk->putFileAs('patient_image', $file, $filename);

            // Drop the previous avatar if it lived under the same store.
            $previous = $user->image_src;
            if ($previous && $previous !== $filename && $disk->exists('patient_image/'.$previous)) {
                $disk->delete('patient_image/'.$previous);
            }

            // `image_src` isn't in User::$fillable — set the attribute
            // explicitly so the row actually persists. update(['image_src'=>…])
            // would silently drop the value.
            $user->image_src = $filename;
            $user->save();

            $fresh = $this->service->getEmployeeProfile($user->id);

            return $this->successResponse(
                'Photo updated successfully.',
                new EmployeeResource($fresh),
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\EmployeeController@avatarStore');
        }
    }

    /**
     * DELETE /api/hr/employees/{user}/avatar
     */
    public function avatarDestroy(User $user): JsonResponse
    {
        try {
            if (! Gate::allows('hr_employees_manage')) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            if ((int) $user->account_id !== (int) Auth::user()->account_id) {
                return $this->errorResponse('Employee not found.', 404);
            }

            $previous = $user->image_src;
            if ($previous) {
                $disk = Storage::disk('local');
                if ($disk->exists('patient_image/'.$previous)) {
                    $disk->delete('patient_image/'.$previous);
                }
            }

            // `image_src` isn't fillable — same reason as avatarStore.
            $user->image_src = null;
            $user->save();

            $fresh = $this->service->getEmployeeProfile($user->id);

            return $this->successResponse(
                'Photo removed successfully.',
                new EmployeeResource($fresh),
            );
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\EmployeeController@avatarDestroy');
        }
    }

    /**
     * PATCH /api/hr/employees/{user}/status
     * Body: { status: 0|1 } — matches the Users/Doctors toggle pattern used
     * throughout the admin controllers.
     */
    public function status(Request $request, User $user): JsonResponse
    {
        try {
            if (!Gate::allows('hr_employees_manage')) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            if ((int) $user->account_id !== (int) Auth::user()->account_id) {
                return $this->errorResponse('Employee not found.', 404);
            }

            $validated = $request->validate([
                'status' => ['required', 'integer', 'in:0,1'],
            ]);

            $updated = $this->service->toggleStatus($user, (int) $validated['status']);

            return $this->successResponse(
                'Employee status updated successfully.',
                [
                    'id' => $updated->id,
                    'active' => (int) $updated->active,
                ],
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse($e->getMessage(), 422, $e->errors());
        } catch (\Exception $e) {
            return $this->handleException($e, 'Api\\HR\\EmployeeController@status');
        }
    }
}
