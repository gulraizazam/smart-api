<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CancellationReasons;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CancellationReasonsController extends Controller
{
    /**
     * GET /api/cancellation-reasons
     *
     * Account-scoped list of active cancellation reasons. Used by the
     * status-update dialogs (consultation + treatment) to populate the
     * required Cancellation reason dropdown when an `is_cancelled`
     * status is picked. Optional `appointment_type_id` query param
     * filters to reasons relevant to that type.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'appointment_type_id' => ['nullable', 'integer', 'exists:appointment_types,id'],
        ]);

        $accountId = (int) Auth::user()->account_id;

        $query = CancellationReasons::query()
            ->where('account_id', $accountId)
            ->where('active', 1);

        if ($request->filled('appointment_type_id')) {
            $query->where('appointment_type_id', $request->integer('appointment_type_id'));
        }

        $reasons = $query
            ->orderBy('sort_no')
            ->orderBy('name')
            ->get(['id', 'name', 'appointment_type_id']);

        return $this->successResponse('Cancellation reasons retrieved.', $reasons);
    }
}
