<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class ServiceBundleException extends Exception
{
    public function __construct(
        string $message = 'Service bundle operation failed',
        protected int $statusCode = 400,
        protected array $errors = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public static function notFound(string $message = 'Bundle not found'): self
    {
        return new self($message, 404);
    }

    public static function hasChildRecords(string $message = 'Cannot delete bundle with existing plan records'): self
    {
        return new self($message, 409);
    }

    public static function operationFailed(string $message = 'Service bundle operation failed'): self
    {
        return new self($message, 500);
    }

    public static function serviceInactive(string $message = 'Cannot create bundle for an inactive service'): self
    {
        return new self($message, 422);
    }
}
