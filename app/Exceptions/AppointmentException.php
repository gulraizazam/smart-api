<?php

namespace App\Exceptions;

use Exception;

class AppointmentException extends Exception
{
    public static function notFound()
    {
        return new self('Appointment not found.', 404);
    }

    public static function cannotDelete($reason = null)
    {
        $message = $reason ?? "Consultation or Treatment can't be deleted when invoice generated.";
        return new self($message, 422);
    }

    public static function invalidStatus()
    {
        return new self('Invalid appointment status.', 422);
    }

    public static function invalidType()
    {
        return new self('Invalid appointment type.', 422);
    }

    public static function scheduleConflict()
    {
        return new self('Schedule conflict detected. This time slot is already booked.', 422);
    }

    public static function invalidLocation()
    {
        return new self('Invalid location selected.', 422);
    }

    public static function invalidDoctor()
    {
        return new self('Invalid doctor selected.', 422);
    }

    public static function invalidService()
    {
        return new self('Invalid service selected.', 422);
    }

    public static function patientNotFound()
    {
        return new self('Patient not found.', 404);
    }

    public static function leadNotFound()
    {
        return new self('Lead not found.', 404);
    }

    public static function unauthorized($message = 'You are not authorized to perform this action.')
    {
        return new self($message, 403);
    }

    public static function invalidData($message = 'Invalid data provided.')
    {
        return new self($message, 422);
    }

    public static function creationFailed($message = 'Failed to create appointment.')
    {
        return new self($message, 500);
    }

    public static function updateFailed($message = 'Failed to update appointment.')
    {
        return new self($message, 500);
    }
}
