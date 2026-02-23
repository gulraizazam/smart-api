<?php

namespace App\Exceptions;

use Exception;

class BundleException extends Exception
{
    protected $statusCode;
    protected $errors;

    public function __construct(string $message = 'Bundle operation failed', int $statusCode = 400, array $errors = [], Exception $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->errors = $errors;
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

    public static function validationFailed(string $message, array $errors = []): self
    {
        return new self($message, 422, $errors);
    }

    public static function hasChildRecords(string $message = 'Cannot delete bundle with existing child records'): self
    {
        return new self($message, 409);
    }

    public static function unauthorized(string $message = 'You are not authorized to perform this action'): self
    {
        return new self($message, 403);
    }

    public static function invalidDateRange(string $message = 'Invalid date range. Start date must be before or equal to end date'): self
    {
        return new self($message, 422);
    }

    public static function serviceNotFound(string $message = 'One or more services not found'): self
    {
        return new self($message, 404);
    }

    public static function operationFailed(string $message = 'Bundle operation failed'): self
    {
        return new self($message, 500);
    }
}
