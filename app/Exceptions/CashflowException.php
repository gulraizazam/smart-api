<?php

namespace App\Exceptions;

use Exception;

class CashflowException extends Exception
{
    protected $statusCode;

    public function __construct(string $message = '', int $statusCode = 422, ?\Throwable $previous = null)
    {
        $this->statusCode = $statusCode;
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function render($request)
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

    public static function insufficientBalance(string $poolName, $balance, $required): self
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
}
