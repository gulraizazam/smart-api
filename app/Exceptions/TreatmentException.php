<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

final class TreatmentException extends Exception
{
    public function __construct(
        string $message = 'Treatment operation failed',
        private readonly int $statusCode = 400,
        private readonly array $errorData = [],
    ) {
        parent::__construct($message, $statusCode);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrorData(): array
    {
        return $this->errorData;
    }

    public static function notFound(string $message = 'Treatment not found.'): self
    {
        return new self($message, 404);
    }

    public static function unauthorized(string $message = 'Unauthorized access to treatment.'): self
    {
        return new self($message, 403);
    }

    public static function validationFailed(string $message = 'Validation failed.', array $errors = []): self
    {
        return new self($message, 422, $errors);
    }

    public static function operationFailed(string $message = 'Operation failed.'): self
    {
        return new self($message, 500);
    }

    public static function invalidData(string $message = 'Invalid data provided.'): self
    {
        return new self($message, 422);
    }

    public static function resourceNotFound(string $resource): self
    {
        return new self("{$resource} not found.", 422);
    }

    public static function doctorUnavailable(string $detail = ''): self
    {
        $message = 'Doctor is not available.';
        if ($detail) {
            $message .= " {$detail}";
        }

        return new self($message, 422);
    }

    public static function invoiceExists(): self
    {
        return new self('Invoice already generated. Treatment cannot be rescheduled.', 422);
    }

    public static function permissionDenied(string $field): self
    {
        return new self("You do not have permission to change the {$field}.");
    }

    public static function patientNotRegistered(): self
    {
        return new self(
            'No registered patient with this phone. Book a consultation first to register the patient.',
            422,
            ['code' => 'PATIENT_NOT_REGISTERED'],
        );
    }
}
