<?php

declare(strict_types=1);

namespace App\Exceptions\Voucher;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Thrown when a voucher consumption attempt finds zero remaining
 * balance after acquiring the row-level lock. Mapped to HTTP 422 with
 * a stable machine code so mobile/admin clients can branch on it.
 */
class VoucherExhaustedException extends Exception
{
    public const CODE = 'VOUCHER_EXHAUSTED';

    protected $code = 422;

    public function __construct(
        public readonly int $voucherId,
        public readonly int|string $userId,
        string $message = 'This voucher has no remaining balance.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 422, $previous);
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $this->getMessage(),
                'code' => self::CODE,
                'data' => null,
                'errors' => [],
            ], 422);
        }

        return redirect()->back()
            ->withErrors(['error' => $this->getMessage()])
            ->withInput();
    }
}
