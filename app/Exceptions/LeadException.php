<?php

namespace App\Exceptions;

use Exception;

class LeadException extends Exception
{
    public function __construct(
        string $message,
        int $code = 400,
        protected readonly array $context = [],
        ?Exception $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public static function notFound(int|string $id): self
    {
        return new self("Lead with ID {$id} not found.", 404, ['lead_id' => $id]);
    }

    public static function phoneAlreadyExists(string $phone): self
    {
        return new self("A lead with phone number {$phone} already exists.", 409, ['phone' => $phone]);
    }

    public static function statusChangeNotAllowed(string $currentStatus): self
    {
        return new self("Cannot change status. Lead is already marked as {$currentStatus}.", 422, ['current_status' => $currentStatus]);
    }

    public static function unauthorized(string $action = 'perform this action'): self
    {
        return new self("You are not authorized to {$action}.", 403, ['action' => $action]);
    }

    public static function invalidData(string $field, string $reason): self
    {
        return new self("Invalid data for field '{$field}': {$reason}", 422, ['field' => $field, 'reason' => $reason]);
    }

    public static function importFailed(string $reason, array $details = []): self
    {
        return new self("Lead import failed: {$reason}", 422, ['reason' => $reason, ...$details]);
    }

    public static function serviceNotFound(string $serviceName): self
    {
        return new self("Service '{$serviceName}' not found.", 404, ['service_name' => $serviceName]);
    }

    public static function bulkOperationFailed(string $operation, int $failedCount): self
    {
        return new self("Bulk {$operation} operation failed for {$failedCount} records.", 422, ['operation' => $operation, 'failed_count' => $failedCount]);
    }
}
