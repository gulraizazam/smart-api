<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Consultancy\ConsultancyInvoiceService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Legacy admin controller - delegates to ConsultancyInvoiceService.
 *
 * New consumers should use App\Http\Controllers\Api\ConsultancyInvoiceController instead.
 * This controller is retained for backward compatibility with existing web routes.
 */
class ConsultancyInvoiceController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly ConsultancyInvoiceService $invoiceService,
    ) {}

    public function invoiceconsultancy(int $id, ?string $type = null): JsonResponse|View
    {
        if (!Gate::allows('appointments_manage') && !Gate::allows('appointments_view')) {
            return $this->errorResponse('You are not authorized to access this resource.', 403);
        }

        try {
            $data = $this->invoiceService->getInvoiceDetails($id);

            // When type is provided (e.g. from calendar JS), return blade view for modal injection
            if ($type !== null) {
                return view('admin.appointments.consultancyinvoice.create', [
                    'price' => $data['price'],
                    'appointment_type' => $data['appointment_type'],
                    'id' => $id,
                    'service' => $data['service'],
                    'balance' => $data['balance'],
                    'settleamount' => $data['settle_amount'],
                    'outstanding' => $data['outstanding'],
                    'paymentmodes' => $data['payment_modes'],
                    'location_info' => $data['location_info'],
                    'tax' => $data['tax'],
                    'tax_amt' => $data['tax_amt'],
                    'invoice_status' => $data['invoice_status'],
                    'discounts' => $data['discounts'],
                    'cash' => $data['cash'],
                    'price_tax' => $data['price_tax'],
                    'patient' => $data['patient'],
                    'doctor' => $data['doctor'],
                    'account' => $data['account'],
                ]);
            }

            // API response (no type param)
            return $this->successResponse('Data found.', $data);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Error fetching invoice details');
        }
    }

    public function getconsultancycalculation(Request $request): JsonResponse
    {
        if (!Gate::allows('appointments_manage') && !Gate::allows('appointments_invoice')) {
            return $this->errorResponse('You are not authorized to perform this action.', 403);
        }

        try {
            $result = $this->invoiceService->calculatePrice($request->all());

            return response()->json($result);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Error calculating price');
        }
    }

    public function getcustomcalculation(Request $request): JsonResponse
    {
        if (!Gate::allows('appointments_manage') && !Gate::allows('appointments_invoice')) {
            return $this->errorResponse('You are not authorized to perform this action.', 403);
        }

        try {
            $result = $this->invoiceService->calculateCustomDiscount($request->all());

            return response()->json($result);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Error calculating custom discount');
        }
    }

    public function checkedcustom(Request $request): JsonResponse
    {
        if (!Gate::allows('appointments_manage') && !Gate::allows('appointments_invoice')) {
            return $this->errorResponse('You are not authorized to perform this action.', 403);
        }

        try {
            $discountId = (int) $request->input('discount_id', 0);
            $isCustom = $discountId > 0 ? $this->invoiceService->isCustomDiscount($discountId) : false;

            return response()->json(['status' => $isCustom]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Error checking discount');
        }
    }

    public function getfinalcalculation(Request $request): JsonResponse
    {
        if (!Gate::allows('appointments_manage') && !Gate::allows('appointments_invoice')) {
            return $this->errorResponse('You are not authorized to perform this action.', 403);
        }

        try {
            $result = $this->invoiceService->calculateFinal($request->all());

            // Legacy response format for backward compatibility
            return response()->json([
                'status' => $result['status'],
                'outstdanding' => $result['outstanding'],
                'settleamount' => $result['settle_amount'],
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Error calculating final amounts');
        }
    }

    public function saveinvoice(Request $request): JsonResponse
    {
        if (!Gate::allows('appointments_manage')) {
            return response()->json(['status' => false, 'message' => 'You are not authorized to create invoices.'], 403);
        }

        try {
            $result = $this->invoiceService->saveInvoice($request->all());

            // Legacy response format - JS checks resposne.status and resposne.data.invoice_id
            return response()->json([
                'status' => true,
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'Something went wrong, please try again later.',
            ]);
        }
    }
}
