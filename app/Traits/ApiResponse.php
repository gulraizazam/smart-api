<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

trait ApiResponse
{
    protected function successResponse(string $message, mixed $data = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => [],
        ], $code);
    }

    protected function errorResponse(string $message, int $code = 400, array $errors = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
        ], $code);
    }

    protected function handleException(\Throwable $e, string $context): JsonResponse
    {
        Log::error("{$context}: {$e->getMessage()}", [
            'exception' => $e::class,
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        $message = config('app.debug')
            ? $e->getMessage()
            : 'Something went wrong, please try again later.';

        $code = $this->resolveExceptionCode($e);

        return $this->errorResponse($message, $code);
    }

    private function resolveExceptionCode(\Throwable $e): int
    {
        $code = method_exists($e, 'getCode') ? (int) $e->getCode() : 500;

        return ($code >= 100 && $code < 600) ? $code : 500;
    }
}
