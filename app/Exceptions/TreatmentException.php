<?php

namespace App\Exceptions;

use Exception;

class TreatmentException extends Exception
{
    protected $statusCode;
    protected $errorData;

    public function __construct(string $message = 'Treatment operation failed', int $statusCode = 400, array $errorData = [])
    {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->errorData = $errorData;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorData(): array
    {
        return $this->errorData;
    }

    public static function notFound(string $message = 'Treatment not found'): self
    {
        return new self($message, 404);
    }

    public static function unauthorized(string $message = 'Unauthorized access to treatment'): self
    {
        return new self($message, 403);
    }

    public static function validationFailed(string $message = 'Validation failed', array $errors = []): self
    {
        return new self($message, 422, $errors);
    }

    public static function operationFailed(string $message = 'Operation failed'): self
    {
        return new self($message, 500);
    }
}
