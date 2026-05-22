<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Lightweight response helpers used by API controllers that return
 * {status, message, data} without the full ApiResponse envelope.
 * Format preserved exactly — DO NOT change without auditing all consumers.
 */
trait SimpleApiResponse
{
    private function success(string $message, mixed $data = null, bool $status = true): JsonResponse
    {
        return response()->json([
            'status'  => $status,
            'message' => $message,
            'data'    => $data,
        ], 200);
    }

    private function error(string $message): JsonResponse
    {
        return response()->json([
            'status'  => false,
            'message' => $message,
            'data'    => null,
        ], 500);
    }

    /**
     * Authz refusal helper — used after a Gate::denies(...) check, so the
     * user is already authenticated and the right HTTP status is 403
     * (Forbidden), not 401 (Unauthenticated). The SPA's `api.ts` treats
     * any 401 as session-expired and force-logs-the-user-out; returning
     * 403 here keeps the session intact and surfaces a real "you can't
     * do that" error instead.
     */
    private function unauthorized(): JsonResponse
    {
        return response()->json([
            'status'  => false,
            'message' => 'You are not authorized to access this resource.',
            'data'    => null,
        ], 403);
    }
}
