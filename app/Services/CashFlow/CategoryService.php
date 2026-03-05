<?php

namespace App\Services\CashFlow;

use App\Models\CashFlow\CashflowAuditLog;
use App\Models\CashFlow\CategoryRequest;
use App\Models\CashFlow\ExpenseCategory;
use Illuminate\Support\Facades\Cache;

class CategoryService
{
    private CashflowAuditService $auditService;
    private NotificationService $notificationService;

    public function __construct(CashflowAuditService $auditService, NotificationService $notificationService)
    {
        $this->auditService = $auditService;
        $this->notificationService = $notificationService;
    }

    /**
     * Get all categories for account.
     */
    public function getAll(int $accountId)
    {
        return ExpenseCategory::forAccount($accountId)
            ->sorted()
            ->get();
    }

    /**
     * Get active categories for dropdown.
     */
    public function getActive(int $accountId)
    {
        return ExpenseCategory::forAccount($accountId)
            ->active()
            ->sorted()
            ->get(['id', 'name', 'vendor_emphasis']);
    }

    /**
     * Create a new category.
     */
    public function create(array $data, int $accountId): ExpenseCategory
    {
        $maxSort = ExpenseCategory::forAccount($accountId)->max('sort_order') ?? 0;

        $category = ExpenseCategory::create([
            'account_id' => $accountId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'vendor_emphasis' => $data['vendor_emphasis'] ?? false,
            'is_active' => 1,
            'sort_order' => $maxSort + 1,
        ]);

        $this->auditService->log(
            CashflowAuditLog::ACTION_CREATED,
            CashflowAuditLog::ENTITY_CATEGORY,
            $category->id,
            null,
            $category->toArray()
        );

        $this->clearCache($accountId);
        return $category;
    }

    /**
     * Update a category.
     */
    public function update(int $categoryId, array $data, int $accountId): ExpenseCategory
    {
        $category = ExpenseCategory::forAccount($accountId)->findOrFail($categoryId);
        $oldValues = $category->toArray();

        $category->update(array_filter([
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'vendor_emphasis' => isset($data['vendor_emphasis']) ? (bool) $data['vendor_emphasis'] : null,
        ], fn($v) => $v !== null));

        $this->auditService->log(
            CashflowAuditLog::ACTION_UPDATED,
            CashflowAuditLog::ENTITY_CATEGORY,
            $category->id,
            $oldValues,
            $category->fresh()->toArray()
        );

        $this->clearCache($accountId);
        return $category->fresh();
    }

    /**
     * Toggle category active status.
     */
    public function toggle(int $categoryId, int $accountId): ExpenseCategory
    {
        $category = ExpenseCategory::forAccount($accountId)->findOrFail($categoryId);
        $oldActive = $category->is_active;
        $category->update(['is_active' => !$oldActive]);

        $this->auditService->log(
            $oldActive ? CashflowAuditLog::ACTION_DEACTIVATED : CashflowAuditLog::ACTION_UPDATED,
            CashflowAuditLog::ENTITY_CATEGORY,
            $category->id,
            ['is_active' => $oldActive],
            ['is_active' => !$oldActive]
        );

        $this->clearCache($accountId);
        return $category->fresh();
    }

    // ===================== CATEGORY REQUESTS =====================

    /**
     * Get category requests.
     */
    public function getCategoryRequests(int $accountId, ?string $status = null, int $perPage = 25)
    {
        $query = CategoryRequest::forAccount($accountId)
            ->with('requester:id,name')
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a category request (staff can suggest new categories).
     */
    public function createCategoryRequest(array $data, int $accountId): CategoryRequest
    {
        $request = CategoryRequest::create([
            'account_id' => $accountId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'requested_by' => \Illuminate\Support\Facades\Auth::id(),
            'status' => CategoryRequest::STATUS_PENDING,
        ]);

        $this->auditService->log(
            CashflowAuditLog::ACTION_CREATED,
            CashflowAuditLog::ENTITY_CATEGORY_REQUEST,
            $request->id,
            null,
            $request->toArray()
        );

        $this->notificationService->notifyCategoryRequest(
            $data['name'],
            \Illuminate\Support\Facades\Auth::user()->name,
            $accountId
        );

        return $request;
    }

    /**
     * Approve a category request — create the category and link it.
     */
    public function approveCategoryRequest(int $requestId, int $accountId): CategoryRequest
    {
        $catRequest = CategoryRequest::forAccount($accountId)->findOrFail($requestId);

        if ($catRequest->status !== CategoryRequest::STATUS_PENDING) {
            throw new \App\Exceptions\CashflowException('Only pending requests can be approved.');
        }

        return \Illuminate\Support\Facades\DB::transaction(function () use ($catRequest, $accountId) {
            $category = $this->create([
                'name' => $catRequest->name,
                'description' => $catRequest->description,
            ], $accountId);

            $catRequest->update([
                'status' => CategoryRequest::STATUS_APPROVED,
                'category_id' => $category->id,
            ]);

            $this->auditService->log(
                CashflowAuditLog::ACTION_APPROVED,
                CashflowAuditLog::ENTITY_CATEGORY_REQUEST,
                $catRequest->id,
                ['status' => 'pending'],
                ['status' => 'approved', 'category_id' => $category->id]
            );

            return $catRequest->fresh()->load('category:id,name');
        });
    }

    /**
     * Dismiss a category request.
     */
    public function dismissCategoryRequest(int $requestId, ?string $adminNotes, int $accountId): CategoryRequest
    {
        $catRequest = CategoryRequest::forAccount($accountId)->findOrFail($requestId);

        if ($catRequest->status !== CategoryRequest::STATUS_PENDING) {
            throw new \App\Exceptions\CashflowException('Only pending requests can be dismissed.');
        }

        $catRequest->update([
            'status' => CategoryRequest::STATUS_DISMISSED,
            'admin_notes' => $adminNotes,
        ]);

        $this->auditService->log(
            CashflowAuditLog::ACTION_REJECTED,
            CashflowAuditLog::ENTITY_CATEGORY_REQUEST,
            $catRequest->id,
            ['status' => 'pending'],
            ['status' => 'dismissed', 'admin_notes' => $adminNotes],
            $adminNotes
        );

        return $catRequest->fresh();
    }

    private function clearCache(int $accountId): void
    {
        Cache::forget("cashflow_categories_{$accountId}");
    }
}
