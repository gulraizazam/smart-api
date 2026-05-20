<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Consultancy\CalculateConsultancyPriceRequest;
use App\Http\Requests\Consultancy\CustomDiscountRequest;
use App\Http\Requests\Consultancy\FinalCalculationRequest;
use App\Http\Requests\Consultancy\StoreConsultancyInvoiceRequest;
use App\Http\Resources\Consultancy\ConsultancyInvoiceResource;
use App\Services\Consultancy\ConsultancyInvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ConsultancyInvoiceController extends Controller
{
    public function __construct(
        private readonly ConsultancyInvoiceService $invoiceService,
    ) {}

    /**
     * Get consultancy invoice details for an appointment.
     */
    public function show(int $id): JsonResponse
    {
        try {
            if (!Gate::allows('consultations.invoice.view')) {
                return $this->errorResponse('You are not authorized to access this resource.', 403);
            }

            $invoiceData = $this->invoiceService->getInvoiceDetails($id);

            return $this->successResponse(
                'Invoice details retrieved successfully.',
                new ConsultancyInvoiceResource($invoiceData),
            );
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Error fetching invoice details');
        }
    }

    /**
     * Calculate consultancy price with discounts and tax.
     */
    public function calculate(CalculateConsultancyPriceRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('consultations.invoice.create')) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            $result = $this->invoiceService->calculatePrice($request->validated());

            return $this->successResponse('Price calculated successfully.', $result);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Error calculating consultancy price');
        }
    }

    /**
     * Calculate custom discount price.
     */
    public function calculateCustomDiscount(CustomDiscountRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('consultations.invoice.create')) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            $result = $this->invoiceService->calculateCustomDiscount($request->validated());

            return $this->successResponse('Custom discount calculated successfully.', $result);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Error calculating custom discount');
        }
    }

    /**
     * Check if a discount is custom type.
     */
    public function checkCustomDiscount(Request $request): JsonResponse
    {
        try {
            if (!Gate::allows('consultations.invoice.create')) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            $discountId = (int) $request->input('discount_id', 0);

            if ($discountId <= 0) {
                return $this->successResponse('Discount check completed.', ['is_custom' => false]);
            }

            $isCustom = $this->invoiceService->isCustomDiscount($discountId);

            return $this->successResponse('Discount check completed.', ['is_custom' => $isCustom]);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Error checking discount type');
        }
    }

    /**
     * Calculate final outstanding and settle amounts.
     */
    public function calculateFinal(FinalCalculationRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('consultations.invoice.create')) {
                return $this->errorResponse('You are not authorized to perform this action.', 403);
            }

            $result = $this->invoiceService->calculateFinal($request->validated());

            return $this->successResponse('Final calculation completed.', $result);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Error calculating final amounts');
        }
    }

    /**
     * Save consultancy invoice.
     */
    public function store(StoreConsultancyInvoiceRequest $request): JsonResponse
    {
        try {
            if (!Gate::allows('consultations.invoice.create')) {
                return $this->errorResponse('You are not authorized to create invoices.', 403);
            }

            $result = $this->invoiceService->saveInvoice($request->validated());

            return $this->successResponse('Invoice created successfully.', $result, 201);
        } catch (\Throwable $e) {
            return $this->handleException($e, 'Error saving consultancy invoice');
        }
    }
}
