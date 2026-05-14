<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CashFlow;

use App\Exceptions\CashflowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CashFlow\StoreTransferRequest;
use App\Services\CashFlow\CashflowAuditService;
use App\Services\CashFlow\TransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CashFlowTransfersController extends Controller
{
    public function __construct(
        private readonly TransferService $transferService,
        private readonly CashflowAuditService $auditService,
    ) {}

    /**
     * Get transfers list (paginated with filters).
     */
    public function transfersData(Request $request): JsonResponse
    {

        try {

            $accountId = Auth::user()->account_id;

            $filters = $request->only(['date_from', 'date_to', 'pool_id', 'method', 'search']);

            // Request input is always string-typed; cast to int to satisfy
            // the service's strict `int $perPage` parameter.
            $transfers = $this->transferService->getTransfers($accountId, $filters, (int) $request->input('per_page', 25));



            return response()->json([

                'success' => true,

                'data' => $transfers->items(),

                'meta' => [

                    'current_page' => $transfers->currentPage(),

                    'last_page' => $transfers->lastPage(),

                    'per_page' => $transfers->perPage(),

                    'total' => $transfers->total(),

                ],

            ]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Store a new cash transfer.
     */
    public function transfersStore(StoreTransferRequest $request): JsonResponse
    {

        try {

            $accountId = Auth::user()->account_id;

            $transfer = $this->transferService->create($request->validated(), $accountId);



            return response()->json([

                'success' => true,

                'data' => $transfer,

                'message' => 'Transfer recorded successfully.',

            ]);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Illuminate\Validation\ValidationException $e) {

            // Let Laravel's default handler render the field-level 422.
            throw $e;

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Void a cash transfer.
     */
    public function transfersVoid(Request $request, int $id): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow_transfer_void')) {

                throw CashflowException::unauthorized('void transfers');

            }



            $request->validate([

                'void_reason' => 'required|string|min:5|max:100',

            ]);



            $accountId = Auth::user()->account_id;

            $transfer = $this->transferService->void($id, $request->void_reason, $accountId);



            return response()->json(['success' => true, 'data' => $transfer, 'message' => 'Transfer voided successfully.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Illuminate\Validation\ValidationException $e) {

            // Let Laravel's default handler render the field-level 422.
            throw $e;

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Edit a cash transfer.
     */
    public function transfersEdit(Request $request, int $id): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow_transfer_edit')) {

                throw CashflowException::unauthorized('edit transfers');

            }



            $request->validate([

                'amount' => 'required|numeric|min:1|integer',

                'from_pool_id' => 'required|exists:cash_pools,id',

                'to_pool_id' => 'required|exists:cash_pools,id|different:from_pool_id',

                'method' => 'required|in:physical_cash,bank_deposit',

                'attachment_url' => ['required', 'string', 'max:500'],

                'description' => 'nullable|string|max:100',

                'edit_reason' => 'required|string|min:5|max:50',

            ]);



            $accountId = Auth::user()->account_id;

            $transfer = $this->transferService->edit($id, $request->all(), $accountId);



            return response()->json(['success' => true, 'data' => $transfer, 'message' => 'Transfer updated successfully.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Illuminate\Validation\ValidationException $e) {

            // Let Laravel's default handler render the field-level 422.
            throw $e;

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }

    /**
     * Get audit trail for a specific transfer.
     */
    public function transfersAudit(int $id): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow_audit_view')) {

                throw CashflowException::unauthorized('view audit trail');

            }



            $accountId = Auth::user()->account_id;

            $logs = $this->auditService->getEntityLogs('transfer', $id, $accountId);



            return response()->json(['success' => true, 'data' => $logs]);

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 500);

        }

    }
}
