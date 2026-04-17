<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Locations;
use App\Models\User;
use App\Models\UserBranch;
use App\Services\Dashboard\Support\ResourceScopeResolver;
use App\Traits\ApiResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Admin CRUD for the user_branches pivot (Management Dashboard scoping).
 *
 * A user with zero rows in user_branches sees every branch (company view).
 * Populate rows to scope them to a regional subset. See ResourceScopeResolver.
 */
class UserBranchesController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ResourceScopeResolver $scopeResolver,
    ) {}

    public function index(): View
    {
        $accountId = (int) Auth::user()->account_id;

        $users = User::query()
            ->where('account_id', $accountId)
            ->where('active', 1)
            ->whereHas('roles', function ($q): void {
                $q->whereIn('name', ['Management', 'Administrator', 'Super-Admin']);
            })
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        $branches = Locations::query()
            ->where('account_id', $accountId)
            ->whereNull('deleted_at')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $assignments = UserBranch::query()
            ->where('account_id', $accountId)
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('location_id')->map(fn ($id) => (int) $id)->all());

        return view('admin.user_branches.index', [
            'users' => $users,
            'branches' => $branches,
            'assignments' => $assignments,
        ]);
    }

    public function update(Request $request, int $userId): JsonResponse
    {
        $accountId = (int) Auth::user()->account_id;

        // Branch IDs must belong to the current account — `exists:locations,id`
        // alone would accept cross-account IDs. Rule::exists scopes the lookup.
        $validated = $request->validate([
            'branch_ids' => 'array',
            'branch_ids.*' => [
                'integer',
                Rule::exists('locations', 'id')
                    ->where('account_id', $accountId)
                    ->whereNull('deleted_at'),
            ],
        ]);

        $user = User::query()
            ->where('id', $userId)
            ->where('account_id', $accountId)
            ->firstOrFail();

        $branchIds = array_values(array_unique(array_map('intval', $validated['branch_ids'] ?? [])));

        DB::transaction(function () use ($accountId, $user, $branchIds): void {
            UserBranch::query()
                ->where('account_id', $accountId)
                ->where('user_id', $user->id)
                ->delete();

            if ($branchIds !== []) {
                $now = now();
                UserBranch::insert(array_map(static fn (int $branchId): array => [
                    'account_id' => $accountId,
                    'user_id' => $user->id,
                    'location_id' => $branchId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $branchIds));
            }
        });

        // Invalidate resolver cache only after the write is committed so a
        // rollback can't leave stale cache hits pointing at deleted rows.
        DB::afterCommit(fn () => $this->scopeResolver->invalidate($user->id));

        return $this->successResponse('Branch assignments updated.', [
            'user_id' => $user->id,
            'branch_ids' => $branchIds,
            'is_company_wide' => $branchIds === [],
        ]);
    }
}
