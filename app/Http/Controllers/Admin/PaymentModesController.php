<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Helpers\Filters;
use App\Http\Controllers\Controller;
use App\Services\PaymentMode\PaymentModeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class PaymentModesController extends Controller
{
    public function __construct(
        private readonly PaymentModeService $paymentModeService,
    ) {}

    public function index(): \Illuminate\View\View
    {
        if (! Gate::allows('payment_modes.list.view')) {
            return abort(403);
        }
        $filters = Filters::all(Auth::user()->id, 'payment_modes');

        return view('admin.payment_modes.index', compact('filters'));
    }

    /**
     * Display a listing of Payment Modes.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function datatable(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            if (! Gate::allows('payment_modes.list.view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $records = $this->paymentModeService->getDatatableData($request, Auth::user()->account_id);

            return response()->json($records);
        } catch (\Exception $e) {
            return $this->handleException($e, 'PaymentModesController');
        }
    }

    /**
     * Show the form for creating new Permission.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(): \Illuminate\View\View
    {
        if (! Gate::allows('payment_modes.create')) {
            return abort(403);
        }

        return view('admin.payment_modes.create', compact('city'));
    }

    public function sortorder_save(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            if (! Gate::allows('payment_modes.sort')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            if ($this->paymentModeService->saveSortOrder($request->item_ids)) {
                return $this->successResponse('Records are sorted Successfully!');
            }

            return $this->errorResponse('Something went Wrong! Records are not sorted', 404);
        } catch (\Exception $e) {
            return $this->handleException($e, 'PaymentModesController');
        }
    }

    public function sortorder(): \Illuminate\View\View
    {
        if (! Gate::allows('payment_modes.sort')) {
            return abort(403);
        }

        return view('admin.payment_modes.sort');
    }

    /**
     * get records for sorting Payment Modes
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function sortOrderGet(): \Illuminate\Http\JsonResponse
    {
        try {
            if (! Gate::allows('payment_modes.sort')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }
            $payment_modes = $this->paymentModeService->getSortedPaymentModes(Auth::user()->account_id);

            return $this->successResponse('Success', $payment_modes);
        } catch (\Exception $e) {
            return $this->handleException($e, 'PaymentModesController');
        }
    }

    /**
     * Update record of Payment Modes
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            if (! Gate::allows('payment_modes.create')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $result = $this->paymentModeService->validateAndCreate($request->all(), Auth::user()->account_id);

            if ($result['success']) {
                return $this->successResponse($result['message']);
            }

            return $this->successResponse($result['error'], false, $result['errors'] ?? null);
        } catch (\Exception $e) {
            return $this->handleException($e, 'PaymentModesController');
        }
    }

    /**
     * Get data for edit Payment Mode
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function edit(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            if (! Gate::allows('payment_modes.edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $result = $this->paymentModeService->getEditData($id);

            if (! $result['success']) {
                return $this->errorResponse($result['error'], 404);
            }

            return $this->successResponse('Success', $result['payment_mode']);
        } catch (\Exception $e) {
            return $this->handleException($e, 'PaymentModesController');
        }
    }

    /**
     * Update record of Payment Modes
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        try {
            if (! Gate::allows('payment_modes.edit')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $result = $this->paymentModeService->validateAndUpdate($request->all(), $id, Auth::user()->account_id);

            if ($result['success']) {
                return $this->successResponse($result['message']);
            }

            return $this->successResponse($result['error'], false, $result['errors'] ?? null);
        } catch (\Exception $e) {
            return $this->handleException($e, 'PaymentModesController');
        }
    }

    /**
     * Remove Payment Mode
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        try {
            if (! Gate::allows('payment_modes.destroy')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $response = $this->paymentModeService->deletePaymentMode($id);

            return $this->successResponse($response['message'], $response['status']);
        } catch (\Exception $e) {
            return $this->handleException($e, 'PaymentModesController');
        }
    }

    /**
     * Change status of Payment Modes
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function status(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            // Legacy payment_modes_active / payment_modes_inactive split
            // by the request payload; new catalog keeps that semantics.
            if ((int) $request->status === 0) {
                if (! Gate::allows('payment_modes.deactivate')) {
                    return $this->errorResponse('You are not authorized to access this resource.', 403);
                }
            } else {
                if (! Gate::allows('payment_modes.activate')) {
                    return $this->errorResponse('You are not authorized to access this resource.', 403);
                }
            }

            $response = $this->paymentModeService->changeStatus((int) $request->id, (int) $request->status);

            return $this->successResponse($response['message'], $response['status']);
        } catch (\Exception $e) {
            return $this->handleException($e, 'PaymentModesController');
        }
    }
}
