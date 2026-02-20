<?php

namespace App\Exceptions;

use Exception;

class ServiceException extends Exception
{
    protected array $context;

    public function __construct(string $message, int $code = 400, array $context = [], ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Service not found
     */
    public static function notFound(int $id): self
    {
        return new self("Service with ID {$id} not found.", 404, ['id' => $id]);
    }

    /**
     * Service has dependencies and cannot be deleted
     */
    public static function hasDependencies(int $id, string $dependency): self
    {
        return new self(
            "Service cannot be deleted because it has associated {$dependency}.",
            422,
            ['id' => $id, 'dependency' => $dependency]
        );
    }

    /**
     * Service has child services
     */
    public static function hasChildServices(int $id): self
    {
        return new self(
            "Service cannot be modified because it has child services.",
            422,
            ['id' => $id]
        );
    }

    /**
     * Parent service change not allowed
     */
    public static function parentChangeNotAllowed(int $id): self
    {
        return new self(
            "Parent service cannot be changed because child services are associated with it.",
            422,
            ['id' => $id]
        );
    }

    /**
     * Service has appointments
     */
    public static function hasAppointments(int $id): self
    {
        return new self(
            "Service cannot be updated because treatments are associated with it.",
            422,
            ['id' => $id]
        );
    }

    /**
     * Cannot deactivate parent with active children
     */
    public static function hasActiveChildren(int $id): self
    {
        return new self(
            "This parent has active child services. Please deactivate them first.",
            422,
            ['id' => $id]
        );
    }

    /**
     * Unauthorized access
     */
    public static function unauthorized(string $action = 'access'): self
    {
        return new self(
            "You are not authorized to {$action} this resource.",
            403,
            ['action' => $action]
        );
    }

    /**
     * Invalid data provided
     */
    public static function invalidData(string $message, array $errors = []): self
    {
        return new self($message, 422, ['errors' => $errors]);
    }

    /**
     * Generic operation failed
     */
    public static function operationFailed(string $operation): self
    {
        return new self(
            "Failed to {$operation}. Please try again later.",
            500,
            ['operation' => $operation]
        );
    }
}
