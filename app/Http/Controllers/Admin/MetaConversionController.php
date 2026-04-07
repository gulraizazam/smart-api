<?php

declare(strict_types=1);
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendLeadStatusRequest;
use App\Services\MetaConversionApiService;

class MetaConversionController extends Controller
{
    public function __construct(
        protected readonly MetaConversionApiService $metaService,
    ) {}

    /**
     * Test the Meta Conversion API connection
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function testConnection(Request $request): mixed
    {
        $result = $this->metaService->testConnection();
        return response()->json($result);
    }

    /**
     * Send Lead Status to Meta
     * Call this when a lead status changes in your CRM
     * Supported statuses: booked, arrived, converted, no_show, cancelled
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendLeadStatus(SendLeadStatusRequest $request): mixed
    {

        $result = $this->metaService->sendLeadStatus(
            $request->phone,
            $request->status,
            $request->lead_id,
            $request->email,
            $request->currency,
            $request->value
        );

        return response()->json($result);
    }
}
