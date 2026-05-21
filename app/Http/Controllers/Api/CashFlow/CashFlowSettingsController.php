<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CashFlow;

use App\Exceptions\CashflowException;
use App\Helpers\CashflowHelper;
use App\Http\Controllers\Controller;
use App\Models\CashFlow\CashflowAuditLog;
use App\Services\CashFlow\CashflowAuditService;
use App\Services\CashFlow\CashflowSettingService;
use App\Services\CashFlow\CategoryService;
use App\Services\CashFlow\PoolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CashFlowSettingsController extends Controller
{
    public function __construct(
        private readonly CashflowSettingService $settingService,
        private readonly PoolService $poolService,
        private readonly CategoryService $categoryService,
        private readonly CashflowAuditService $auditService,
    ) {}

    /**
     * Get all settings data for the settings screen.
     */
    public function settingsData(): JsonResponse
    {

        if (Gate::denies('cashflow.settings.manage')) { return response()->json(['success' => false, 'message' => 'You are not authorized to access this resource.', 'data' => null], 403); }

        try {

            $accountId = Auth::user()->account_id;



            return response()->json([

                'success' => true,

                'data' => [

                    'settings' => $this->settingService->getAll($accountId),

                    'pools' => $this->poolService->getAllPools($accountId),

                    'categories' => $this->categoryService->getAll($accountId),

                    'payment_modes' => CashflowHelper::getActivePaymentModes(),

                    'has_period_locks' => \App\Models\CashFlow\PeriodLock::where('account_id', $accountId)->exists(),

                ],

            ]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Update settings.
     */
    public function settingsUpdate(Request $request): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow.settings.manage')) {

                throw CashflowException::unauthorized('manage settings');

            }



            $accountId = Auth::user()->account_id;

            $settings = $request->input('settings', []);

            // Support flat key-value pairs (e.g. from PM mapping save)

            if (empty($settings)) {

                $settings = $request->except(['_token']);

            }

            $oldSettings = $this->settingService->getAll($accountId);

            $this->settingService->updateMany($settings, $accountId);



            $this->auditService->log(

                CashflowAuditLog::ACTION_UPDATED,

                CashflowAuditLog::ENTITY_SETTING,

                0,

                $oldSettings,

                $settings

            );



            return response()->json(['success' => true, 'message' => 'Settings updated successfully.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Illuminate\Validation\ValidationException $e) {

            throw $e;

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Reset module (first month only, before any period lock).
     */
    public function settingsResetModule(Request $request): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow.settings.manage')) {

                throw CashflowException::unauthorized('reset module');

            }



            $accountId = Auth::user()->account_id;



            // Check no period locks exist

            $hasLocks = \App\Models\CashFlow\PeriodLock::where('account_id', $accountId)->exists();

            if ($hasLocks) {

                throw new CashflowException('Module cannot be reset after a period has been locked.');

            }



            // Double confirmation

            if ($request->input('confirm') !== 'RESET') {

                return response()->json(['success' => false, 'message' => 'Type RESET to confirm module reset.'], 422);

            }



            \Illuminate\Support\Facades\DB::transaction(function () use ($accountId) {

                \App\Models\CashFlow\CashflowNotification::where('account_id', $accountId)->delete();

                \App\Models\CashFlow\StaffReturn::where('account_id', $accountId)->forceDelete();

                \App\Models\CashFlow\StaffAdvance::where('account_id', $accountId)->forceDelete();

                \App\Models\CashFlow\VendorTransaction::where('account_id', $accountId)->forceDelete();

                \App\Models\CashFlow\Expense::where('account_id', $accountId)->forceDelete();

                \App\Models\CashFlow\CashTransfer::where('account_id', $accountId)->forceDelete();

                \App\Models\CashFlow\Vendor::where('account_id', $accountId)->forceDelete();

                \App\Models\CashFlow\CashPool::where('account_id', $accountId)->update(['cached_balance' => \Illuminate\Support\Facades\DB::raw('opening_balance')]);



                $this->auditService->log(

                    \App\Models\CashFlow\CashflowAuditLog::ACTION_RESET,

                    \App\Models\CashFlow\CashflowAuditLog::ENTITY_MODULE,

                    0,

                    null,

                    ['action' => 'full_module_reset'],

                    'Full module reset performed'

                );

            });



            CashflowHelper::clearAllCaches($accountId);



            return response()->json(['success' => true, 'message' => 'Module has been reset. All transaction data cleared.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Illuminate\Validation\ValidationException $e) {

            throw $e;

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * List all non-patient staff with their advance eligibility flag.
     */
    public function eligibleStaffList(): JsonResponse
    {

        if (Gate::denies('cashflow.settings.manage')) { return response()->json(['success' => false, 'message' => 'You are not authorized to access this resource.', 'data' => null], 403); }

        try {

            $accountId = Auth::user()->account_id;



            $staff = \App\Models\User::where('account_id', $accountId)

                ->where('active', 1)

                ->whereNull('deleted_at')

                ->whereNotIn('user_type_id', [3])

                ->orderBy('name')

                ->get(['id', 'name', 'email', 'is_advance_eligible']);



            return response()->json(['success' => true, 'data' => $staff]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Toggle advance eligibility for a staff member.
     */
    public function toggleStaffEligibility(Request $request): JsonResponse
    {

        if (Gate::denies('cashflow.settings.manage')) { return response()->json(['success' => false, 'message' => 'You are not authorized to access this resource.', 'data' => null], 403); }

        try {

            $request->validate([

                'user_id' => 'required|exists:users,id',

                'is_advance_eligible' => 'required|boolean',

            ]);



            $accountId = Auth::user()->account_id;

            $user = \App\Models\User::where('account_id', $accountId)->findOrFail($request->user_id);



            $user->update(['is_advance_eligible' => $request->is_advance_eligible]);



            $this->auditService->log(

                'updated',

                'user',

                $user->id,

                ['is_advance_eligible' => !$request->is_advance_eligible],

                ['is_advance_eligible' => (bool) $request->is_advance_eligible],

                'Advance eligibility toggled'

            );



            return response()->json([

                'success' => true,

                'message' => $user->name . ' advance eligibility ' . ($request->is_advance_eligible ? 'enabled' : 'disabled') . '.',

            ]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 422);

        }

    }

    /**
     * Paginated audit logs for admin viewer.
     *
     * Gated on `cashflow_audit_view` (the canonical audit-trail slug used by
     * the per-entity audit dialogs on Expenses / Vendors / Transfers / Staff).
     * Previously this endpoint had no inline gate beyond the route-level
     * `cashflow_settings`; that allowed users with settings access but no
     * audit-view permission to read every cashflow audit row.
     */
    public function auditLogs(Request $request): JsonResponse
    {

        try {

            if (!\Illuminate\Support\Facades\Gate::allows('cashflow.audit.view')) {

                throw CashflowException::unauthorized('view audit log');

            }

            $accountId = Auth::user()->account_id;

            $query = \App\Models\CashFlow\CashflowAuditLog::where('account_id', $accountId)

                ->with('user:id,name')

                ->orderByDesc('id');



            if ($request->filled('entity_type')) {

                $query->where('entity_type', $request->input('entity_type'));

            }



            if ($request->filled('action')) {

                $query->where('action', $request->input('action'));

            }



            $perPage = min((int) $request->input('per_page', 25), 100);

            $logs = $query->paginate($perPage);



            return response()->json([

                'success' => true,

                'data' => $logs->items(),

                'meta' => [

                    'current_page' => $logs->currentPage(),

                    'last_page' => $logs->lastPage(),

                    'per_page' => $logs->perPage(),

                    'total' => $logs->total(),

                ],

            ]);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }
}
