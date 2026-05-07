<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Helpers\Filters;
use App\Models\EmployeeDetail;
use App\Models\Locations;
use App\Models\User;
use App\Models\UserHasLocations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EmployeeService
{
    protected const FILTER_KEY = 'hr_employees';

    public function getDatatableData(array $requestFilters, int $accountId): array
    {
        $filters = getFilters($requestFilters);
        $applyFilter = checkFilters($filters, self::FILTER_KEY);
        $userId = Auth::id();

        $query = User::query()
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.phone',
                'users.image_src',
                'users.active',
                'users.created_at',
                'employee_details.hire_date',
                'designations.name as designation_name',
            ])
            ->leftJoin('employee_details', 'users.id', '=', 'employee_details.user_id')
            ->leftJoin('designations', 'employee_details.designation_id', '=', 'designations.id')
            ->where('users.account_id', $accountId)
            ->whereIn('users.user_type_id', [2, 5])
            ->where('users.hr_managed', 1)
            ->whereNull('users.deleted_at');

        // Apply filters
        if (hasFilter($filters, 'name')) {
            $query->where('users.name', 'like', '%' . $filters['name'] . '%');
            Filters::put($userId, self::FILTER_KEY, 'name', $filters['name']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'name');
        } else {
            $stored = Filters::get($userId, self::FILTER_KEY, 'name');
            if ($stored) {
                $query->where('users.name', 'like', '%' . $stored . '%');
            }
        }

        if (hasFilter($filters, 'designation_id')) {
            $query->where('employee_details.designation_id', $filters['designation_id']);
            Filters::put($userId, self::FILTER_KEY, 'designation_id', $filters['designation_id']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'designation_id');
        } else {
            $stored = Filters::get($userId, self::FILTER_KEY, 'designation_id');
            if ($stored) {
                $query->where('employee_details.designation_id', $stored);
            }
        }

        if (hasFilter($filters, 'status')) {
            $query->where('users.active', $filters['status']);
            Filters::put($userId, self::FILTER_KEY, 'status', $filters['status']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'status');
            $query->where('users.active', 1);
        } else {
            $stored = Filters::get($userId, self::FILTER_KEY, 'status');
            if ($stored !== null && in_array($stored, [0, 1, '0', '1'], true)) {
                $query->where('users.active', $stored);
            } else {
                $query->where('users.active', 1);
            }
        }

        if (hasFilter($filters, 'location_id')) {
            $query->whereExists(function ($sub) use ($filters) {
                $sub->select(\DB::raw(1))
                    ->from('user_has_locations')
                    ->whereColumn('user_has_locations.user_id', 'users.id')
                    ->where('user_has_locations.location_id', $filters['location_id']);
            });
            Filters::put($userId, self::FILTER_KEY, 'location_id', $filters['location_id']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'location_id');
        } else {
            $stored = Filters::get($userId, self::FILTER_KEY, 'location_id');
            if ($stored) {
                $query->whereExists(function ($sub) use ($stored) {
                    $sub->select(\DB::raw(1))
                        ->from('user_has_locations')
                        ->whereColumn('user_has_locations.user_id', 'users.id')
                        ->where('user_has_locations.location_id', $stored);
                });
            }
        }

        $total = (clone $query)->count();

        return [
            'query' => $query,
            'total' => $total,
            'active_filters' => Filters::all($userId, self::FILTER_KEY),
        ];
    }

    public function getEmployeeProfile(int $userId): User
    {
        return User::with([
            'employeeDetail.department',
            'employeeDetail.designation',
            'employeeDetail.reportingManager',
            'user_has_locations.location',
            'employeeDocuments' => fn ($q) => $q->orderByDesc('created_at'),
            'employeeDocuments.uploader',
        ])
            ->where('account_id', Auth::user()->account_id)
            ->findOrFail($userId);
    }

    public function updateEmployeeDetails(User $user, array $data, int $accountId): EmployeeDetail
    {
        $detail = EmployeeDetail::firstOrNew([
            'user_id' => $user->id,
            'account_id' => $accountId,
        ]);

        if (!$detail->exists) {
            $detail->created_by = Auth::id();
        }

        $detail->fill($data);
        $detail->save();

        return $detail;
    }

    public function syncLocations(User $user, array $locationIds, int $accountId): void
    {
        $user->user_has_locations()->delete();

        if (empty($locationIds)) {
            return;
        }

        $locations = Locations::whereIn('id', $locationIds)
            ->where('account_id', $accountId)
            ->get(['id', 'region_id']);

        foreach ($locations as $location) {
            UserHasLocations::create([
                'user_id' => $user->id,
                'location_id' => $location->id,
                'region_id' => $location->region_id,
            ]);
        }
    }

    /**
     * Create a new employee — User + EmployeeDetail + location assignment in one
     * transaction. Returns the persisted User with relations eager-loaded so the
     * caller can hand it straight to an EmployeeResource.
     *
     * @param  array<string, mixed>  $data
     */
    public function createEmployee(array $data, int $accountId): User
    {
        return DB::transaction(function () use ($data, $accountId) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone' => $data['phone'] ?? '',
                'cnic' => $data['cnic'] ?? null,
                'dob' => $data['dob'] ?? null,
                'gender' => $data['gender'] ?? null,
                'user_type_id' => (int) ($data['user_type_id'] ?? 2),
                'account_id' => $accountId,
                'active' => 1,
                'hr_managed' => 1,
            ]);

            EmployeeDetail::create([
                'user_id' => $user->id,
                'department_id' => $data['department_id'] ?? null,
                'designation_id' => $data['designation_id'] ?? null,
                'reporting_manager_id' => $data['reporting_manager_id'] ?? null,
                'hire_date' => $data['hire_date'] ?? now()->toDateString(),
                'employment_type' => $data['employment_type'] ?? null,
                'shift_hours' => $data['shift_hours'] ?? null,
                'salary' => $data['salary'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'bank_account_number' => $data['bank_account_number'] ?? null,
                'tax_id' => $data['tax_id'] ?? null,
                'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                'emergency_contact_relation' => $data['emergency_contact_relation'] ?? null,
                'address' => $data['address'] ?? null,
                'account_id' => $accountId,
                'created_by' => Auth::id(),
            ]);

            if (!empty($data['location_ids']) && is_array($data['location_ids'])) {
                $this->syncLocations($user, $data['location_ids'], $accountId);
            }

            return $user;
        });
    }

    /**
     * Soft-delete an employee and their EmployeeDetail (if any) in one
     * transaction. User tokens are revoked so the session/token cannot linger.
     */
    public function deleteEmployee(User $user): void
    {
        DB::transaction(function () use ($user): void {
            if ($user->employeeDetail) {
                $user->employeeDetail->delete();
            }

            // Revoke Sanctum tokens so a deleted employee's app session dies.
            if (method_exists($user, 'tokens')) {
                $user->tokens()->delete();
            }

            $user->delete();
        });
    }

    /**
     * Flip `users.active` to the supplied value. Returns the fresh User.
     */
    public function toggleStatus(User $user, int $status): User
    {
        $user->update(['active' => $status === 1 ? 1 : 0]);

        return $user->fresh();
    }
}
