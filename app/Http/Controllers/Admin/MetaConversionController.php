<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MetaConversionApiService;
use Illuminate\Http\Request;

class MetaConversionController extends Controller
{
    protected $metaService;

    public function __construct(MetaConversionApiService $metaService)
    {
        $this->metaService = $metaService;
    }

    /**
     * Test the Meta Conversion API connection
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function testConnection(Request $request)
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
    public function sendLeadStatus(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'status' => 'required|string',
            'lead_id' => 'nullable|string',
            'email' => 'nullable|email',
            'currency' => 'nullable|string|size:3',
            'value' => 'nullable|numeric',
        ]);

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
