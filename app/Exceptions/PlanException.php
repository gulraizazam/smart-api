<?php

namespace App\Exceptions;

use Exception;

class PlanException extends Exception
{
    /**
     * Create a new exception instance for plan not found
     */
    public static function notFound(int $id): self
    {
        return new self("Plan with ID {$id} not found.", 404);
    }

    /**
     * Create a new exception instance for unauthorized access
     */
    public static function unauthorized(): self
    {
        return new self("You are not authorized to access this resource.", 403);
    }

    /**
     * Create a new exception instance for validation errors
     */
    public static function validationFailed(string $message): self
    {
        return new self($message, 422);
    }

    /**
     * Create a new exception instance for child records exist
     */
    public static function hasChildRecords(): self
    {
        return new self("Cannot delete plan. Child records exist.", 409);
    }

    /**
     * Create a new exception instance for invalid operation
     */
    public static function invalidOperation(string $message): self
    {
        return new self($message, 400);
    }
}
