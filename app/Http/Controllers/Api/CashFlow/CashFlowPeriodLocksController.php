<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CashFlow;

use App\Exceptions\CashflowException;
use App\Http\Controllers\Controller;
use App\Services\CashFlow\PeriodLockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CashFlowPeriodLocksController extends Controller
{
    public function __construct(
        private readonly PeriodLockService $periodLockService,
    ) {}

    /**
     * Get all period locks.
     */
    public function periodLocksData(): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow.settings.manage')) {

                throw CashflowException::unauthorized('view period locks');

            }



            $accountId = Auth::user()->account_id;

            $data = $this->periodLockService->getLocks($accountId);



            return response()->json(['success' => true, 'data' => $data]);

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
     * Lock a period.
     */
    public function periodLocksLock(Request $request): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow.settings.manage')) {

                throw CashflowException::unauthorized('lock periods');

            }



            $request->validate([

                'month' => 'required|integer|between:1,12',

                'year' => 'required|integer|min:2020',

            ]);



            $accountId = Auth::user()->account_id;

            $lock = $this->periodLockService->lockPeriod(

                (int) $request->input('month'),

                (int) $request->input('year'),

                $accountId

            );



            return response()->json(['success' => true, 'data' => $lock, 'message' => 'Period locked successfully.']);

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
     * Unlock a period (mandatory reason).
     */
    public function periodLocksUnlock(Request $request, int $id): JsonResponse
    {

        try {

            if (!Gate::allows('cashflow.settings.manage')) {

                throw CashflowException::unauthorized('unlock periods');

            }



            $request->validate([

                'reason' => 'required|string|min:5',

            ]);



            $accountId = Auth::user()->account_id;

            $lock = $this->periodLockService->unlockPeriod($id, $request->input('reason'), $accountId);



            return response()->json(['success' => true, 'data' => $lock, 'message' => 'Period unlocked.']);

        } catch (CashflowException $e) {

            return $e->render(request());

        } catch (\Illuminate\Validation\ValidationException $e) {

            throw $e;

        } catch (\Exception $e) {

            \Illuminate\Support\Facades\Log::error($e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            return response()->json(['success' => false, 'message' => 'An error occurred. Please try again.'], 422);

        }

    }
}
