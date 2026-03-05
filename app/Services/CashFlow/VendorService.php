<?php

namespace App\Services\CashFlow;

use App\Exceptions\CashflowException;
use App\Models\CashFlow\CashflowAuditLog;
use App\Models\CashFlow\Vendor;
use App\Models\CashFlow\VendorRequest;
use App\Models\CashFlow\VendorTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class VendorService
{
    private CashflowAuditService $auditService;
    private NotificationService $notificationService;

    public function __construct(CashflowAuditService $auditService, NotificationService $notificationService)
    {
        $this->auditService = $auditService;
        $this->notificationService = $notificationService;
    }

    // ===================== VENDORS =====================

    /**
     * Get all vendors for account (paginated).
     */
    public function getVendors(int $accountId, array $filters = [], int $perPage = 25)
    {
        $query = Vendor::forAccount($accountId)
            ->with('creator:id,name')
            ->orderBy('name');

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('contact_person', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a vendor.
     */
    public function createVendor(array $data, int $accountId): Vendor
    {
        $vendor = Vendor::create([
            'account_id' => $accountId,
            'name' => $data['name'],
            'contact_person' => $data['contact_person'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'payment_terms' => $data['payment_terms'] ?? 'upfront',
            'category' => $data['category'] ?? null,
            'opening_balance' => $data['opening_balance'] ?? 0,
            'cached_balance' => $data['opening_balance'] ?? 0,
            'is_active' => 1,
            'notes' => $data['notes'] ?? null,
            'created_by' => Auth::id(),
        ]);

        $this->auditService->log(
            CashflowAuditLog::ACTION_CREATED,
            CashflowAuditLog::ENTITY_VENDOR,
            $vendor->id,
            null,
            $vendor->toArray()
        );

        $this->clearCache($accountId);
        return $vendor;
    }

    /**
     * Update a vendor.
     */
    public function updateVendor(int $vendorId, array $data, int $accountId): Vendor
    {
        $vendor = Vendor::forAccount($accountId)->findOrFail($vendorId);
        $oldValues = $vendor->toArray();

        $allowed = ['name', 'contact_person', 'phone', 'email', 'address', 'payment_terms', 'category', 'notes', 'is_active', 'opening_balance'];
        $updateData = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        // If opening_balance changed, adjust cached_balance by the same delta
        if (array_key_exists('opening_balance', $updateData)) {
            $oldOpening = (float) $vendor->opening_balance;
            $newOpening = (float) $updateData['opening_balance'];
            $delta = $newOpening - $oldOpening;
            if ($delta != 0) {
                $updateData['cached_balance'] = (float) $vendor->cached_balance + $delta;
            }
        }

        $vendor->update($updateData);

        $this->auditService->log(
            CashflowAuditLog::ACTION_UPDATED,
            CashflowAuditLog::ENTITY_VENDOR,
            $vendor->id,
            $oldValues,
            $vendor->fresh()->toArray()
        );

        $this->clearCache($accountId);
        return $vendor->fresh();
    }

    // ===================== VENDOR LEDGER =====================

    /**
     * Get vendor ledger (transactions).
     */
    public function getVendorLedger(int $vendorId, int $accountId, array $filters = [], int $perPage = 25)
    {
        $vendor = Vendor::forAccount($accountId)->findOrFail($vendorId);

        $query = VendorTransaction::forAccount($accountId)
            ->where('vendor_id', $vendorId)
            ->with(['expense:id,description,expense_date', 'creator:id,name'])
            ->orderBy('created_at', 'desc');

        if (!empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return [
            'vendor' => $vendor,
            'transactions' => $query->paginate($perPage),
        ];
    }

    /**
     * Record a standalone vendor transaction (purchase without expense link).
     */
    public function recordTransaction(array $data, int $accountId): VendorTransaction
    {
        $vendor = Vendor::forAccount($accountId)->findOrFail($data['vendor_id']);

        $transaction = DB::transaction(function () use ($data, $accountId) {
            // Observer handles vendor balance updates
            return VendorTransaction::create([
                'account_id' => $accountId,
                'vendor_id' => $data['vendor_id'],
                'type' => $data['type'],
                'amount' => $data['amount'],
                'expense_id' => $data['expense_id'] ?? null,
                'description' => $data['description'] ?? null,
                'reference_no' => $data['reference_no'] ?? null,
                'created_by' => Auth::id(),
            ]);
        });

        $this->auditService->log(
            CashflowAuditLog::ACTION_CREATED,
            CashflowAuditLog::ENTITY_VENDOR_TRANSACTION,
            $transaction->id,
            null,
            $transaction->toArray()
        );

        $this->clearCache($accountId);
        return $transaction;
    }

    // ===================== VENDOR REQUESTS =====================

    /**
     * Get vendor requests.
     */
    public function getVendorRequests(int $accountId, ?string $status = null, int $perPage = 25)
    {
        $query = VendorRequest::forAccount($accountId)
            ->with('requester:id,name')
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        return $query->paginate($perPage);
    }

    /**
     * Create a vendor request (staff can request new vendors).
     */
    public function createVendorRequest(array $data, int $accountId): VendorRequest
    {
        $request = VendorRequest::create([
            'account_id' => $accountId,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'note' => $data['note'] ?? null,
            'requested_by' => Auth::id(),
            'status' => VendorRequest::STATUS_PENDING,
        ]);

        $this->auditService->log(
            CashflowAuditLog::ACTION_CREATED,
            CashflowAuditLog::ENTITY_VENDOR_REQUEST,
            $request->id,
            null,
            $request->toArray()
        );

        $this->notificationService->notifyVendorRequest(
            $data['name'],
            Auth::user()->name,
            $accountId
        );

        return $request;
    }

    /**
     * Approve a vendor request — create the vendor and link it.
     */
    public function approveVendorRequest(int $requestId, int $accountId): VendorRequest
    {
        $vendorRequest = VendorRequest::forAccount($accountId)->findOrFail($requestId);

        if ($vendorRequest->status !== VendorRequest::STATUS_PENDING) {
            throw new CashflowException('Only pending requests can be approved.');
        }

        return DB::transaction(function () use ($vendorRequest, $accountId) {
            $vendor = $this->createVendor([
                'name' => $vendorRequest->name,
                'phone' => $vendorRequest->phone,
                'notes' => 'Created from vendor request #' . $vendorRequest->id,
            ], $accountId);

            $vendorRequest->update([
                'status' => VendorRequest::STATUS_APPROVED,
                'vendor_id' => $vendor->id,
            ]);

            $this->auditService->log(
                CashflowAuditLog::ACTION_APPROVED,
                CashflowAuditLog::ENTITY_VENDOR_REQUEST,
                $vendorRequest->id,
                ['status' => 'pending'],
                ['status' => 'approved', 'vendor_id' => $vendor->id]
            );

            return $vendorRequest->fresh()->load('vendor:id,name');
        });
    }

    /**
     * Dismiss a vendor request.
     */
    public function dismissVendorRequest(int $requestId, ?string $adminNotes, int $accountId): VendorRequest
    {
        $vendorRequest = VendorRequest::forAccount($accountId)->findOrFail($requestId);

        if ($vendorRequest->status !== VendorRequest::STATUS_PENDING) {
            throw new CashflowException('Only pending requests can be dismissed.');
        }

        $vendorRequest->update([
            'status' => VendorRequest::STATUS_DISMISSED,
            'admin_notes' => $adminNotes,
        ]);

        $this->auditService->log(
            CashflowAuditLog::ACTION_REJECTED,
            CashflowAuditLog::ENTITY_VENDOR_REQUEST,
            $vendorRequest->id,
            ['status' => 'pending'],
            ['status' => 'dismissed', 'admin_notes' => $adminNotes],
            $adminNotes
        );

        return $vendorRequest->fresh();
    }

    private function clearCache(int $accountId): void
    {
        Cache::forget("cashflow_vendors_{$accountId}");
    }
}
