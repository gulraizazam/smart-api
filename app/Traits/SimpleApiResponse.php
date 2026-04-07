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

    private function unauthorized(): JsonResponse
    {
        return response()->json([
            'status'  => false,
            'message' => 'You are not authorized to access this resource.',
            'data'    => null,
        ], 401);
    }
}
