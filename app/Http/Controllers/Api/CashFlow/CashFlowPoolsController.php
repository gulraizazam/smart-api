<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CashFlow;

use App\Exceptions\CashflowException;
use App\Http\Controllers\Controller;
use App\Services\CashFlow\PoolService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CashFlowPoolsController extends Controller
{
    public function __construct(
        private readonly PoolService $poolService,
    ) {}

    /**
     * Get all pools.
     */
    public function poolsIndex(): JsonResponse
    {

        try {

            $accountId = Auth::user()->account_id;

            return response()->json([

                'success' => true,

                'data' => $this->poolService->getAllPools($accountId),

            ]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Create a new pool (head office or bank account).
     */
    public function poolsStore(Request $request): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow_pool_manage')) {

                throw CashflowException::unauthorized('manage pools');

            }



            $request->validate([

                'name' => 'required|string|max:255',

                'type' => 'required|in:head_office_cash,bank_account',

                'opening_balance' => 'nullable|numeric|min:0',

            ]);



            $accountId = Auth::user()->account_id;

            $pool = $this->poolService->createPool($request->all(), $accountId);



            return response()->json(['success' => true, 'data' => $pool, 'message' => 'Pool created successfully.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Illuminate\Validation\ValidationException $e) {

            throw $e;

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 422);

        }

    }

    /**
     * Update a pool.
     */
    public function poolsUpdate(Request $request, int $id): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow_pool_manage')) {

                throw CashflowException::unauthorized('manage pools');

            }



            $request->validate([

                'name' => 'nullable|string|max:255',

                'opening_balance' => 'nullable|numeric|min:0',

                'is_active' => 'nullable|boolean',

            ]);



            $accountId = Auth::user()->account_id;



            // Opening balance frozen after first period lock (Sec 4.2)

            $data = $request->all();

            if (isset($data['opening_balance'])) {

                $hasLocks = \App\Models\CashFlow\PeriodLock::where('account_id', $accountId)->exists();

                if ($hasLocks) {

                    unset($data['opening_balance']);

                }

            }



            $pool = $this->poolService->updatePool($id, $data, $accountId);



            return response()->json(['success' => true, 'data' => $pool, 'message' => 'Pool updated successfully.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Illuminate\Validation\ValidationException $e) {

            throw $e;

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 422);

        }

    }

    /**
     * Delete a pool.
     */
    public function poolsDelete(int $id): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow_pool_manage')) {

                throw CashflowException::unauthorized('manage pools');

            }



            $accountId = Auth::user()->account_id;

            $this->poolService->deletePool($id, $accountId);



            return response()->json(['success' => true, 'message' => 'Pool deleted successfully.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Illuminate\Validation\ValidationException $e) {

            throw $e;

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 422);

        }

    }

    /**
     * Initialize pools for existing branches.
     */
    public function poolsInit(): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow_pool_manage')) {

                throw CashflowException::unauthorized('manage pools');

            }



            $accountId = Auth::user()->account_id;

            $count = $this->poolService->initializePoolsForExistingBranches($accountId);



            return response()->json([

                'success' => true,

                'message' => $count > 0

                    ? "{$count} branch pool(s) created successfully."

                    : 'All branches already have pools.',

            ]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Recalculate all pool balances from opening balances + all transactions since go-live.
     */
    public function poolsRecalculate(): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow_settings')) {

                throw CashflowException::unauthorized('recalculate pool balances');

            }



            $accountId = Auth::user()->account_id;

            $results = $this->poolService->recalculatePoolBalances($accountId);



            $message = !empty($results)

                ? count($results) . ' pool(s) adjusted.'

                : 'All pool balances are already accurate.';



            return response()->json([

                'success' => true,

                'message' => $message,

                'data' => $results,

            ]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }
}
