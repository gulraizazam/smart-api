<?php

declare(strict_types=1);
namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashflowException extends Exception
{
    public function __construct(string $message = '', protected readonly int $statusCode = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], $this->statusCode);
    }

    public static function periodLocked(int $month, int $year): self
    {
        return new self("Period {$month}/{$year} is locked. No modifications allowed.", 403);
    }

    public static function insufficientBalance(string $poolName, float|int|string $balance, float|int|string $required): self
    {
        return new self("Insufficient balance in '{$poolName}'. Available: {$balance}, Required: {$required}.", 422);
    }

    public static function unauthorized(string $action = ''): self
    {
        return new self("You are not authorized to perform this action" . ($action ? ": {$action}" : '.'), 403);
    }

    public static function moduleNotConfigured(): self
    {
        return new self('Cash Flow module is not configured yet. Please set the go-live date in Settings.', 422);
    }

    public static function openingBalanceFrozen(): self
    {
        return new self('Opening balances are frozen after the first period lock.', 422);
    }

    public static function validationFailed(string $message): self
    {
        return new self($message, 422);
    }

    public static function notFound(string $entity): self
    {
        return new self("{$entity} not found.", 404);
    }
}
