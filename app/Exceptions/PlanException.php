<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

final class PlanException extends Exception
{
    public static function notFound(int $id): self
    {
        return new self("Plan with ID {$id} not found.", 404);
    }

    public static function unauthorized(): self
    {
        return new self('You are not authorized to access this resource.', 403);
    }

    public static function validationFailed(string $message): self
    {
        return new self($message, 422);
    }

    public static function hasChildRecords(string $details = ''): self
    {
        $message = 'Cannot delete plan. Child records exist.';
        if ($details !== '') {
            $message .= " ({$details})";
        }

        return new self($message, 409);
    }

    public static function invalidOperation(string $message): self
    {
        return new self($message, 400);
    }

    public static function packageNotFound(): self
    {
        return new self('Package not found.', 404);
    }

    public static function alreadySettled(): self
    {
        return new self('Plan is already settled. You cannot add further treatment in this plan.', 400);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'data'    => null,
            'errors'  => [],
        ], $this->getCode() ?: 400);
    }
}
