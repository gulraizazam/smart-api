<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class AppointmentException extends Exception
{
    public static function notFound(): self
    {
        return new self('Appointment not found.', 404);
    }

    public static function cannotDelete(?string $reason = null): self
    {
        $message = $reason ?? "Consultation or Treatment can't be deleted when invoice generated.";
        return new self($message, 422);
    }

    public static function invalidStatus(?string $reason = null): self
    {
        return new self($reason ?? 'Invalid appointment status.', 422);
    }

    public static function invalidType(): self
    {
        return new self('Invalid appointment type.', 422);
    }

    public static function scheduleConflict(): self
    {
        return new self('Schedule conflict detected. This time slot is already booked.', 422);
    }

    public static function invalidLocation(): self
    {
        return new self('Invalid location selected.', 422);
    }

    public static function invalidDoctor(): self
    {
        return new self('Invalid doctor selected.', 422);
    }

    public static function invalidService(): self
    {
        return new self('Invalid service selected.', 422);
    }

    public static function patientNotFound(): self
    {
        return new self('Patient not found.', 404);
    }

    public static function leadNotFound(): self
    {
        return new self('Lead not found.', 404);
    }

    public static function unauthorized(string $message = 'You are not authorized to perform this action.'): self
    {
        return new self($message, 403);
    }

    public static function invalidData(string $message = 'Invalid data provided.'): self
    {
        return new self($message, 422);
    }

    public static function creationFailed(string $message = 'Failed to create appointment.'): self
    {
        return new self($message, 500);
    }

    public static function updateFailed(string $message = 'Failed to update appointment.'): self
    {
        return new self($message, 500);
    }

    public static function patientCreationNotAllowed(): self
    {
        return new self(
            'No registered patient with this phone. Book a consultation first to register the patient.',
            422,
        );
    }
}
