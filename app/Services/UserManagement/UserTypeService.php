<?php

namespace App\Services\UserManagement;

use App\Models\AuditTrails;
use App\Models\User;
use App\Models\UserTypes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class UserTypeService
{
    private const CACHE_KEY_PREFIX = 'user_types_';
    private const CACHE_TTL = 3600;

    public function getDatatableData(array $params): array
    {
        $query = UserTypes::query()
            ->forAccount(Auth::user()->account_id)
            ->excludeAdmin();

        if (!empty($params['name'])) {
            $query->searchByName($params['name']);
        }

        if (!empty($params['type'])) {
            $query->ofType($params['type']);
        }

        $total = $query->count();

        $userTypes = $query
            ->orderBy($params['order_by'] ?? 'name', $params['order'] ?? 'asc')
            ->offset($params['offset'] ?? 0)
            ->limit($params['limit'] ?? 30)
            ->get();

        return [
            'data' => $userTypes,
            'total' => $total,
        ];
    }

    public function getAllForDropdown(): array
    {
        $accountId = Auth::user()->account_id;

        return Cache::remember(
            self::CACHE_KEY_PREFIX . "dropdown_{$accountId}",
            self::CACHE_TTL,
            fn (): array => UserTypes::forAccount($accountId)
                ->active()
                ->orderBy('name')
                ->get()
                ->toArray()
        );
    }

    public function getForDoctor(): array
    {
        $accountId = Auth::user()->account_id;

        return Cache::remember(
            self::CACHE_KEY_PREFIX . "doctor_{$accountId}",
            self::CACHE_TTL,
            fn (): array => UserTypes::forAccount($accountId)
                ->ofType('consultant')
                ->pluck('name', 'id')
                ->all()
        );
    }

    public function getTypeOptions(): array
    {
        return config('constants.user_types', []);
    }

    public function find(int $id): ?UserTypes
    {
        return UserTypes::where('id', $id)
            ->forAccount(Auth::user()->account_id)
            ->first();
    }

    public function create(array $data): UserTypes
    {
        $user = Auth::user();

        $userType = UserTypes::create([
            'name' => $data['name'],
            'type' => $data['type'],
            'account_id' => $user->account_id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'active' => 1,
        ]);

        AuditTrails::addEventLogger('user_types', 'create', $userType->toArray(), ['name', 'type', 'active'], $userType);

        $this->clearCache($user->account_id);

        return $userType;
    }

    public function update(int $id, array $data): ?UserTypes
    {
        $userType = $this->find($id);

        if (!$userType) {
            return null;
        }

        $oldData = $userType->toArray();

        $userType->update([
            'name' => $data['name'],
            'type' => $data['type'],
            'updated_by' => Auth::id(),
        ]);

        AuditTrails::EditEventLogger('user_types', 'edit', $userType->toArray(), ['name', 'type', 'active'], $oldData, $id);

        $this->clearCache(Auth::user()->account_id);

        return $userType;
    }

    public function delete(int $id): array
    {
        $userType = $this->find($id);

        if (!$userType) {
            return ['success' => false, 'message' => 'Resource not found.'];
        }

        if ($this->hasChildren($id)) {
            return ['success' => false, 'message' => 'Child records exist, unable to delete resource.'];
        }

        $userType->delete();
        AuditTrails::deleteEventLogger('user_types', 'delete', ['name', 'type', 'active'], $id);
        $this->clearCache(Auth::user()->account_id);

        return ['success' => true, 'message' => 'Record has been deleted successfully.'];
    }

    public function bulkDelete(array $ids): array
    {
        $accountId = Auth::user()->account_id;
        $deleted = 0;
        $skipped = 0;

        UserTypes::forAccount($accountId)
            ->whereIn('id', $ids)
            ->each(function (UserTypes $userType) use (&$deleted, &$skipped): void {
                if (!$this->hasChildren($userType->id)) {
                    $userType->delete();
                    AuditTrails::deleteEventLogger('user_types', 'delete', ['name', 'type', 'active'], $userType->id);
                    $deleted++;
                } else {
                    $skipped++;
                }
            });

        $this->clearCache($accountId);

        return compact('deleted', 'skipped');
    }

    public function activate(int $id): array
    {
        return $this->toggleStatus($id, active: true, permission: 'active');
    }

    public function inactivate(int $id): array
    {
        $userType = $this->find($id);

        if (!$userType) {
            return ['success' => false, 'message' => 'Resource not found.'];
        }

        if ($this->hasChildren($id)) {
            return ['success' => false, 'message' => 'Child records exist, unable to inactivate resource.'];
        }

        $userType->update(['active' => 0]);
        AuditTrails::InactiveEventLogger('user_types', 'inactive', ['name', 'type', 'active'], $id);
        $this->clearCache(Auth::user()->account_id);

        return ['success' => true, 'message' => 'Inactivated successfully.'];
    }

    private function toggleStatus(int $id, bool $active, string $permission): array
    {
        $userType = $this->find($id);

        if (!$userType) {
            return ['success' => false, 'message' => 'Resource not found.'];
        }

        $userType->update(['active' => (int) $active]);

        match ($active) {
            true => AuditTrails::activeEventLogger('user_types', 'active', ['name', 'type', 'active'], $id),
            false => AuditTrails::InactiveEventLogger('user_types', 'inactive', ['name', 'type', 'active'], $id),
        };

        $this->clearCache(Auth::user()->account_id);

        $label = $active ? 'Activated' : 'Inactivated';

        return ['success' => true, 'message' => "{$label} successfully."];
    }

    public function hasChildren(int $id, ?int $accountId = null): bool
    {
        $accountId ??= Auth::user()->account_id;

        return User::where('user_type_id', $id)
            ->where('account_id', $accountId)
            ->exists();
    }

    public function getAllAsDictionary(): array
    {
        $accountId = Auth::user()->account_id;

        return Cache::remember(
            self::CACHE_KEY_PREFIX . "dictionary_{$accountId}",
            self::CACHE_TTL,
            fn () => UserTypes::forAccount($accountId)->get()->getDictionary()
        );
    }

    public function clearCache(?int $accountId = null): void
    {
        $accountId ??= Auth::user()->account_id;

        Cache::forget(self::CACHE_KEY_PREFIX . "dropdown_{$accountId}");
        Cache::forget(self::CACHE_KEY_PREFIX . "doctor_{$accountId}");
        Cache::forget(self::CACHE_KEY_PREFIX . "dictionary_{$accountId}");
    }
}
