<?php

namespace App\Services\UserManagement;

use App\Models\AuditTrails;
use App\Models\User;
use App\Models\UserTypes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UserTypeService
{
    private const CACHE_KEY_PREFIX = 'user_types_';
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get paginated user types for datatable
     */
    public function getDatatableData(array $params): array
    {
        $accountId = Auth::user()->account_id;
        
        $query = UserTypes::query()
            ->where('account_id', $accountId)
            ->where('name', '!=', 'Administrator');

        // Apply filters
        $this->applyFilters($query, $params);

        // Get total count before pagination
        $total = $query->count();

        // Apply sorting
        $orderBy = $params['order_by'] ?? 'name';
        $order = $params['order'] ?? 'asc';
        $query->orderBy($orderBy, $order);

        // Apply pagination
        $offset = $params['offset'] ?? 0;
        $limit = $params['limit'] ?? 30;
        
        $userTypes = $query->offset($offset)->limit($limit)->get();

        return [
            'data' => $userTypes,
            'total' => $total,
        ];
    }

    /**
     * Apply filters to query
     */
    private function applyFilters($query, array $filters): void
    {
        if (!empty($filters['name'])) {
            $query->where('name', 'like', '%' . $filters['name'] . '%');
        }

        if (!empty($filters['type'])) {
            $query->where('type', '=', $filters['type']);
        }
    }

    /**
     * Get all user types for dropdown
     */
    public function getAllForDropdown(): array
    {
        $accountId = Auth::user()->account_id;
        $cacheKey = self::CACHE_KEY_PREFIX . 'dropdown_' . $accountId;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($accountId) {
            return UserTypes::where('account_id', $accountId)
                ->where('active', 1)
                ->orderBy('name')
                ->get()
                ->toArray();
        });
    }

    /**
     * Get user types for doctors (consultant type)
     */
    public function getForDoctor(): array
    {
        $accountId = Auth::user()->account_id;
        $cacheKey = self::CACHE_KEY_PREFIX . 'doctor_' . $accountId;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($accountId) {
            return UserTypes::where('account_id', $accountId)
                ->where('type', 'consultant')
                ->pluck('name', 'id')
                ->toArray();
        });
    }

    /**
     * Get available user type options from config
     */
    public function getTypeOptions(): array
    {
        return config('constants.user_types', []);
    }

    /**
     * Find user type by ID
     */
    public function find(int $id): ?UserTypes
    {
        $accountId = Auth::user()->account_id;
        
        return UserTypes::where('id', $id)
            ->where('account_id', $accountId)
            ->first();
    }

    /**
     * Create a new user type
     */
    public function create(array $data): UserTypes
    {
        $accountId = Auth::user()->account_id;
        $userId = Auth::user()->id;

        $userType = UserTypes::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'account_id' => $accountId,
            'created_by' => $userId,
            'updated_by' => $userId,
            'active' => 1,
        ]);

        AuditTrails::addEventLogger(
            'user_types',
            'create',
            $userType->toArray(),
            ['name', 'type', 'active'],
            $userType
        );

        $this->clearCache($accountId);

        return $userType;
    }

    /**
     * Update an existing user type
     */
    public function update(int $id, array $data): ?UserTypes
    {
        $accountId = Auth::user()->account_id;
        $userId = Auth::user()->id;

        $userType = $this->find($id);
        
        if (!$userType) {
            return null;
        }

        $oldData = $userType->toArray();

        $userType->update([
            'name' => $data['name'],
            'type' => $data['type'],
            'updated_by' => $userId,
        ]);

        AuditTrails::EditEventLogger(
            'user_types',
            'edit',
            $userType->toArray(),
            ['name', 'type', 'active'],
            $oldData,
            $id
        );

        $this->clearCache($accountId);

        return $userType;
    }

    /**
     * Delete a user type
     */
    public function delete(int $id): array
    {
        $accountId = Auth::user()->account_id;
        $userType = $this->find($id);

        if (!$userType) {
            return ['success' => false, 'message' => 'Resource not found.'];
        }

        if ($this->hasChildren($id, $accountId)) {
            return ['success' => false, 'message' => 'Child records exist, unable to delete resource.'];
        }

        $userType->delete();

        AuditTrails::deleteEventLogger(
            'user_types',
            'delete',
            ['name', 'type', 'active'],
            $id
        );

        $this->clearCache($accountId);

        return ['success' => true, 'message' => 'Record has been deleted successfully.'];
    }

    /**
     * Bulk delete user types
     */
    public function bulkDelete(array $ids): array
    {
        $accountId = Auth::user()->account_id;
        $deleted = 0;
        $skipped = 0;

        $userTypes = UserTypes::where('account_id', $accountId)
            ->whereIn('id', $ids)
            ->get();

        foreach ($userTypes as $userType) {
            if (!$this->hasChildren($userType->id, $accountId)) {
                $userType->delete();
                AuditTrails::deleteEventLogger(
                    'user_types',
                    'delete',
                    ['name', 'type', 'active'],
                    $userType->id
                );
                $deleted++;
            } else {
                $skipped++;
            }
        }

        $this->clearCache($accountId);

        return [
            'deleted' => $deleted,
            'skipped' => $skipped,
        ];
    }

    /**
     * Activate a user type
     */
    public function activate(int $id): array
    {
        $accountId = Auth::user()->account_id;
        $userType = $this->find($id);

        if (!$userType) {
            return ['success' => false, 'message' => 'Resource not found.'];
        }

        $userType->update(['active' => 1]);

        AuditTrails::activeEventLogger(
            'user_types',
            'active',
            ['name', 'type', 'active'],
            $id
        );

        $this->clearCache($accountId);

        return ['success' => true, 'message' => 'Activated successfully.'];
    }

    /**
     * Inactivate a user type
     */
    public function inactivate(int $id): array
    {
        $accountId = Auth::user()->account_id;
        $userType = $this->find($id);

        if (!$userType) {
            return ['success' => false, 'message' => 'Resource not found.'];
        }

        if ($this->hasChildren($id, $accountId)) {
            return ['success' => false, 'message' => 'Child records exist, unable to inactivate resource.'];
        }

        $userType->update(['active' => 0]);

        AuditTrails::InactiveEventLogger(
            'user_types',
            'inactive',
            ['name', 'type', 'active'],
            $id
        );

        $this->clearCache($accountId);

        return ['success' => true, 'message' => 'Inactivated successfully.'];
    }

    /**
     * Check if user type has child records (users)
     */
    public function hasChildren(int $id, ?int $accountId = null): bool
    {
        $accountId = $accountId ?? Auth::user()->account_id;

        return User::where('user_type_id', $id)
            ->where('account_id', $accountId)
            ->exists();
    }

    /**
     * Get all records as dictionary
     */
    public function getAllAsDictionary(): array
    {
        $accountId = Auth::user()->account_id;
        $cacheKey = self::CACHE_KEY_PREFIX . 'dictionary_' . $accountId;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($accountId) {
            return UserTypes::where('account_id', $accountId)
                ->get()
                ->getDictionary();
        });
    }

    /**
     * Clear user types cache for account
     */
    public function clearCache(?int $accountId = null): void
    {
        $accountId = $accountId ?? Auth::user()->account_id;

        Cache::forget(self::CACHE_KEY_PREFIX . 'dropdown_' . $accountId);
        Cache::forget(self::CACHE_KEY_PREFIX . 'doctor_' . $accountId);
        Cache::forget(self::CACHE_KEY_PREFIX . 'dictionary_' . $accountId);
    }
}
