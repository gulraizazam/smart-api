<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;

trait ApiResponse
{
    protected function successResponse(string $message, mixed $data = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'status'  => true,
            'message' => $message,
            'data' => $data,
            'errors' => [],
        ], $code);
    }

    protected function paginatedResponse(
        string $message,
        LengthAwarePaginator|Paginator|CursorPaginator $paginator,
        ?string $resourceClass = null,
        int $code = 200,
        array $extraMeta = [],
    ): JsonResponse {
        $items = $paginator->getCollection();

        $data = $resourceClass !== null && is_subclass_of($resourceClass, JsonResource::class)
            ? $resourceClass::collection($items)
            : $items;

        if ($paginator instanceof LengthAwarePaginator) {
            $meta = [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ];
            $links = [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ];
        } elseif ($paginator instanceof CursorPaginator) {
            $meta = [
                'per_page' => $paginator->perPage(),
                'next_cursor' => $paginator->nextCursor()?->encode(),
                'prev_cursor' => $paginator->previousCursor()?->encode(),
            ];
            $links = [
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ];
        } else {
            $meta = [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more' => $paginator->hasMorePages(),
            ];
            $links = [
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ];
        }

        return response()->json([
            'success' => true,
            'status'  => true,
            'message' => $message,
            'data' => $data,
            'meta' => array_merge($meta, $extraMeta),
            'links' => $links,
            'errors' => [],
        ], $code);
    }

    protected function errorResponse(string $message, int $code = 400, array $errors = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'status'  => false,
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
