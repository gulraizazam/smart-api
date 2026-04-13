<?php

declare(strict_types=1);

namespace App\Services\HR;

use App\Enums\CandidateStatus;
use App\Helpers\Filters;
use App\Models\Designation;
use App\Models\EmployeeDetail;
use App\Models\RecruitmentCandidate;
use App\Models\User;
use App\Models\UserHasLocations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RecruitmentService
{
    protected const FILTER_KEY = 'hr_recruitment';

    public function getDatatableData(array $requestFilters, int $accountId): array
    {
        $filters = getFilters($requestFilters);
        $applyFilter = checkFilters($filters, self::FILTER_KEY);
        $userId = Auth::id();

        $query = RecruitmentCandidate::query()
            ->select([
                'recruitment_candidates.*',
                'designations.name as designation_name',
                'cities.name as city_name',
                'locations.name as location_name',
            ])
            ->join('designations', 'recruitment_candidates.designation_id', '=', 'designations.id')
            ->leftJoin('cities', 'recruitment_candidates.city_id', '=', 'cities.id')
            ->leftJoin('locations', 'recruitment_candidates.location_id', '=', 'locations.id')
            ->where('recruitment_candidates.account_id', $accountId)
            ->whereNull('recruitment_candidates.deleted_at');

        if (hasFilter($filters, 'name')) {
            $query->where('recruitment_candidates.name', 'like', '%' . $filters['name'] . '%');
            Filters::put($userId, self::FILTER_KEY, 'name', $filters['name']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'name');
        }

        if (hasFilter($filters, 'status')) {
            $query->where('recruitment_candidates.status', $filters['status']);
            Filters::put($userId, self::FILTER_KEY, 'status', $filters['status']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'status');
        }

        if (hasFilter($filters, 'designation_id')) {
            $query->where('recruitment_candidates.designation_id', $filters['designation_id']);
            Filters::put($userId, self::FILTER_KEY, 'designation_id', $filters['designation_id']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'designation_id');
        }

        if (hasFilter($filters, 'location_id')) {
            $query->where('recruitment_candidates.location_id', $filters['location_id']);
            Filters::put($userId, self::FILTER_KEY, 'location_id', $filters['location_id']);
        } elseif ($applyFilter) {
            Filters::forget($userId, self::FILTER_KEY, 'location_id');
        }

        $total = (clone $query)->count();

        return [
            'query' => $query,
            'total' => $total,
            'active_filters' => Filters::all($userId, self::FILTER_KEY),
        ];
    }

    public function convertToEmployee(RecruitmentCandidate $candidate, array $data): User
    {
        return DB::transaction(function () use ($candidate, $data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? '',
                'password' => Hash::make($data['password']),
                'user_type_id' => (int) $data['user_type_id'],
                'account_id' => $candidate->account_id,
                'active' => 1,
            ]);

            EmployeeDetail::create([
                'user_id' => $user->id,
                'department_id' => $data['department_id'] ?? null,
                'designation_id' => $candidate->designation_id,
                'hire_date' => $data['hire_date'] ?? now()->toDateString(),
                'employment_type' => $data['employment_type'] ?? null,
                'salary' => $data['salary'] ?? null,
                'account_id' => $candidate->account_id,
                'created_by' => Auth::id(),
            ]);

            // Assign role if provided
            if (!empty($data['role_id'])) {
                $user->assignRole((int) $data['role_id']);
            }

            // Assign location
            if ($candidate->location_id) {
                UserHasLocations::create([
                    'user_id' => $user->id,
                    'location_id' => $candidate->location_id,
                    'region_id' => \App\Models\Locations::where('id', $candidate->location_id)
                        ->where('account_id', $candidate->account_id)
                        ->value('region_id') ?? 0,
                ]);
            }

            $candidate->update([
                'converted_user_id' => $user->id,
                'status' => CandidateStatus::Hired->value,
            ]);

            return $user;
        });
    }
}
